<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreExceptionRequest;
use App\Http\Requests\StoreShipmentRequest;
use App\Http\Requests\StoreTrackingRequest;
use App\Http\Requests\UpdateShipmentRequest;
use App\Http\Resources\ShipmentResource;
use App\Models\Shipment;
use App\Services\ExceptionService;
use App\Services\ShipmentService;
use App\Services\TrackingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ShipmentController extends Controller
{
    public function __construct(
        private ShipmentService $shipments,
        private TrackingService $tracking,
        private ExceptionService $exceptions,
    ) {}

    /**
     * Role-scoped list with the filters the brief requires [S10]:
     * reference, status, carrier, origin, destination, delay, dates,
     * plus sorting and server-side pagination.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Shipment::query()
            ->visibleTo($request->user())
            ->with(['origin', 'destination', 'carrier']);

        if ($number = trim((string) $request->query('shipment_number'))) {
            $query->where('shipment_number', 'like', "%{$number}%");
        }

        if ($status = $request->query('status')) {
            $query->where('current_status', strtoupper($status));
        }

        if ($carrierId = $request->query('carrier_id')) {
            $query->where('carrier_id', $carrierId);
        }

        foreach (['origin' => 'origin_location_id', 'destination' => 'destination_location_id'] as $param => $column) {
            if ($value = $request->query($param)) {
                $query->where($column, $value);
            }
        }

        if ($request->boolean('delayed')) {
            $query->whereNotNull('estimated_arrival_at')
                ->whereColumn('estimated_arrival_at', '>', 'expected_arrival_at');
        }

        if ($from = $request->query('date_from')) {
            $query->where('expected_departure_at', '>=', $from);
        }

        if ($to = $request->query('date_to')) {
            $query->where('expected_departure_at', '<=', $to);
        }

        $sortable = ['shipment_number', 'expected_departure_at', 'expected_arrival_at', 'created_at'];
        $sort = (string) $request->query('sort', '-created_at');
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $column = ltrim($sort, '-');

        if (in_array($column, $sortable, true)) {
            $query->orderBy($column, $direction);
        }

        return ShipmentResource::collection(
            $query->paginate(min($request->integer('per_page', 15), 100)),
        );
    }

    public function show(Request $request, Shipment $shipment): ShipmentResource
    {
        $shipment = Shipment::query()->visibleTo($request->user())->findOrFail($shipment->id);

        $shipment->load(['origin', 'destination', 'carrier', 'customer', 'items', 'trackingEvents.creator', 'exceptions']);

        return new ShipmentResource($shipment);
    }

    public function store(StoreShipmentRequest $request): ShipmentResource
    {
        $shipment = $this->shipments->create(
            $request->safe()->except(['items']),
            $request->validated('items', []),
        );

        return new ShipmentResource($shipment->load(['origin', 'destination', 'carrier']));
    }

    public function update(UpdateShipmentRequest $request, Shipment $shipment): ShipmentResource
    {
        $shipment = $this->shipments->update(
            $shipment,
            $request->safe()->except(['items']),
            $request->validated('items'),
        );

        return new ShipmentResource($shipment->load(['origin', 'destination', 'carrier']));
    }

    public function destroy(Shipment $shipment): JsonResponse
    {
        $this->authorize('delete', $shipment);

        $this->shipments->delete($shipment);

        return response()->json(['message' => 'Shipment archived.']);
    }

    public function trackingIndex(Request $request, Shipment $shipment): JsonResponse
    {
        $shipment = Shipment::query()->visibleTo($request->user())->findOrFail($shipment->id);

        return response()->json([
            'data' => $shipment->trackingEvents()->with('creator')->orderBy('occurred_at')->orderBy('id')->get(),
        ]);
    }

    public function trackingStore(StoreTrackingRequest $request, Shipment $shipment): JsonResponse
    {
        $event = $this->tracking->addEvent($shipment, $request->validated(), $request->user());

        return response()->json(['data' => $event->load('creator')], 201);
    }

    public function exceptionsIndex(Request $request, Shipment $shipment): JsonResponse
    {
        $shipment = Shipment::query()->visibleTo($request->user())->findOrFail($shipment->id);

        return response()->json([
            'data' => $shipment->exceptions()->with('resolver')->orderBy('detected_at')->get(),
        ]);
    }

    public function exceptionsStore(StoreExceptionRequest $request, Shipment $shipment): JsonResponse
    {
        $exception = $this->exceptions->create($shipment, $request->validated(), $request->user());

        return response()->json(['data' => $exception->load('resolver')], 201);
    }
}
