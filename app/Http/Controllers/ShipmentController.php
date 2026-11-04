<?php

namespace App\Http\Controllers;

use App\Enums\ShipmentStatus;
use App\Http\Requests\StoreExceptionRequest;
use App\Http\Requests\StoreShipmentRequest;
use App\Http\Requests\StoreTrackingRequest;
use App\Http\Requests\UpdateExceptionRequest;
use App\Http\Requests\UpdateShipmentRequest;
use App\Models\Carrier;
use App\Models\Location;
use App\Models\Shipment;
use App\Models\ShipmentException;
use App\Models\User;
use App\Services\ExceptionService;
use App\Services\ShipmentService;
use App\Services\TrackingService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ShipmentController extends Controller
{
    public function __construct(
        private ShipmentService $shipments,
        private TrackingService $tracking,
        private ExceptionService $exceptions,
    ) {}

    /**
     * The tracking screen: role-scoped list on the left, the live map
     * fills the right side once the map milestone lands.
     */
    public function index(Request $request): View
    {
        $query = Shipment::query()
            ->visibleTo($request->user())
            ->with(['origin', 'destination', 'carrier']);

        if ($search = trim((string) $request->query('q'))) {
            $query->where(function ($q) use ($search) {
                $q->where('shipment_number', 'like', "%{$search}%")
                    ->orWhereHas('origin', fn ($loc) => $loc->where('city', 'like', "%{$search}%"))
                    ->orWhereHas('destination', fn ($loc) => $loc->where('city', 'like', "%{$search}%"));
            });
        }

        if ($status = $request->query('status')) {
            $query->where('current_status', $status);
        }

        if ($request->boolean('delayed')) {
            $query
                ->whereNotNull('estimated_arrival_at')
                ->whereColumn('estimated_arrival_at', '>', 'expected_arrival_at');
        }

        if ($request->boolean('open_exceptions')) {
            $query->whereHas('exceptions', fn ($exception) => $exception->where('status', '!=', 'RESOLVED'));
        }

        $shipments = $query->orderByDesc('created_at')->paginate(12)->withQueryString();

        // The map view: every visible shipment with its endpoints and up
        // to the two most recent geolocated events (current position +
        // the one before it, for the heading of the live marker).
        $mapData = Shipment::query()
            ->visibleTo($request->user())
            ->with(['origin', 'destination', 'trackingEvents' => fn ($events) => $events
                ->whereNotNull('latitude')
                ->whereNotNull('longitude')
                ->orderByDesc('occurred_at')
                ->orderByDesc('id')
                ->limit(2),
            ])
            ->get()
            ->map(fn (Shipment $shipment) => [
                'id' => $shipment->id,
                'shipment_number' => $shipment->shipment_number,
                'status' => $shipment->current_status->value,
                'origin' => $shipment->origin === null ? null : [
                    'latitude' => (float) $shipment->origin->latitude,
                    'longitude' => (float) $shipment->origin->longitude,
                    'location_name' => $shipment->origin->code.' · '.$shipment->origin->city,
                    'time' => $shipment->expected_departure_at?->format('M j · H:i'),
                ],
                'destination' => $shipment->destination === null ? null : [
                    'latitude' => (float) $shipment->destination->latitude,
                    'longitude' => (float) $shipment->destination->longitude,
                    'location_name' => $shipment->destination->code.' · '.$shipment->destination->city,
                    'time' => $shipment->expected_arrival_at?->format('M j · H:i'),
                ],
                'current_position' => ($event = $shipment->trackingEvents->first()) === null ? null : [
                    'latitude' => (float) $event->latitude,
                    'longitude' => (float) $event->longitude,
                    'location_name' => $event->location_name,
                    'time' => $event->occurred_at->format('M j · H:i'),
                ],
                'previous_position' => ($event = $shipment->trackingEvents->get(1)) === null ? null : [
                    'latitude' => (float) $event->latitude,
                    'longitude' => (float) $event->longitude,
                ],
            ])
            ->all();

        $focus = null;
        if ($focusId = (int) $request->query('focus')) {
            $focus = Shipment::query()
                ->visibleTo($request->user())
                ->with(['origin', 'destination', 'carrier', 'customer'])
                ->find($focusId);
        }

        return view('shipments.index', [
            'shipments' => $shipments,
            'mapData' => $mapData,
            'focus' => $focus,
            'filters' => [
                'q' => $search,
                'status' => $status,
                'delayed' => $request->boolean('delayed'),
                'open_exceptions' => $request->boolean('open_exceptions'),
            ],
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Shipment::class);

        return view('shipments.create', $this->formOptions());
    }

    public function store(StoreShipmentRequest $request): RedirectResponse
    {
        $shipment = $this->shipments->create(
            $request->safe()->except(['items']),
            $request->validated('items', []),
            $request->user(),
        );

        return redirect()->route('shipments.show', $shipment);
    }

    public function show(Request $request, Shipment $shipment): View
    {
        $visible = Shipment::query()->visibleTo($request->user())->findOrFail($shipment->id);

        $visible->load(['origin', 'destination', 'carrier', 'customer', 'items', 'trackingEvents.creator', 'trackingEvents.exception', 'exceptions.resolver']);

        return view('shipments.show', [
            'shipment' => $visible,
            'lastEvent' => $visible->trackingEvents->last(),
        ]);
    }

    /**
     * Staff append tracking events from the shipment page; the service
     * owns every business rule, the controller only routes input.
     */
    public function storeTracking(StoreTrackingRequest $request, Shipment $shipment): RedirectResponse
    {
        $this->tracking->addEvent($shipment, $request->validated(), $request->user());

        return redirect()->route('shipments.show', $shipment)->with('status', 'Tracking event recorded.');
    }

    /**
     * Raising an exception also writes its timeline event (service).
     */
    public function storeException(StoreExceptionRequest $request, Shipment $shipment): RedirectResponse
    {
        $this->exceptions->create($shipment, $request->validated(), $request->user());

        return redirect()->route('shipments.show', $shipment)->with('status', 'Exception recorded.');
    }

    public function updateException(UpdateExceptionRequest $request, ShipmentException $exception): RedirectResponse
    {
        $this->exceptions->update($exception, $request->validated(), $request->user());

        return redirect()->route('shipments.show', $exception->shipment)->with('status', 'Exception updated.');
    }

    public function edit(Request $request, Shipment $shipment): View
    {
        $this->authorize('update', $shipment);

        $shipment->load(['items']);

        return view('shipments.edit', ['shipment' => $shipment, ...$this->formOptions()]);
    }

    public function update(UpdateShipmentRequest $request, Shipment $shipment): RedirectResponse
    {
        $this->shipments->update(
            $shipment,
            $request->safe()->except(['items']),
            $request->validated('items'),
            $request->user(),
        );

        return redirect()->route('shipments.show', $shipment);
    }

    public function destroy(Shipment $shipment): RedirectResponse
    {
        $this->authorize('delete', $shipment);

        $this->shipments->delete($shipment, auth()->user());

        return redirect()->route('shipments.index');
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'customers' => User::query()->where('role', 'CUSTOMER')->orderBy('name')->get(['id', 'name']),
            'carriers' => Carrier::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']),
            'locations' => Location::query()->orderBy('city')->get(['id', 'name', 'code', 'city']),
            'statuses' => ShipmentStatus::cases(),
        ];
    }
}
