<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreShipmentRequest;
use App\Models\AuditLog;
use App\Models\Carrier;
use App\Models\Location;
use App\Models\Notification;
use App\Models\Shipment;
use App\Models\ShipmentException;
use App\Models\ShipmentItem;
use App\Models\TrackingEvent;
use App\Models\User;
use App\Services\DelayClassifierService;
use App\Services\ExternalTelemetryService;
use App\Services\IndonesianPortCatalog;
use App\Services\ShipmentLifecycleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;
use InvalidArgumentException;

class CargoTraceWebController extends Controller
{
    public function __construct(
        private readonly ShipmentLifecycleService $lifecycleService,
        private readonly DelayClassifierService $delayClassifier,
        private readonly ExternalTelemetryService $telemetryService
    ) {}

    /**
     * Render the main CargoTrace dashboard (White Mode default, #080B0C / #FFFFFF / #C6F432 palette).
     * All metrics come from the live database, never from static numbers.
     */
    public function dashboard(Request $request): View
    {
        $scenario = DB::table('scenario_configs')->first();
        $shipments = Shipment::with(['origin', 'destination', 'carrier', 'exceptions'])
            ->active()
            ->visibleTo($request->user())
            ->get();

        $lowH = (int) ($scenario->low_threshold_hours ?? 6);
        $highH = (int) ($scenario->high_threshold_hours ?? 24);

        $delayedCount = $shipments->filter(function (Shipment $s) use ($lowH, $highH) {
            return $this->delayClassifier->assess($s->expected_arrival_at, $s->estimated_arrival_at, $lowH, $highH)['delayed'];
        })->count();

        $metrics = [
            'total_shipments' => $shipments->count(),
            'in_transit' => $shipments->where('current_status', Shipment::STATUS_IN_TRANSIT)->count(),
            'delivered' => $shipments->where('current_status', Shipment::STATUS_DELIVERED)->count(),
            'delayed' => $delayedCount,
            'open_exceptions' => ShipmentException::where('status', '!=', ShipmentException::STATUS_RESOLVED)->count(),
            'on_time_rate' => $shipments->count() > 0
                ? round((($shipments->count() - $delayedCount) / $shipments->count()) * 100)
                : 100,
        ];

        // Charts: shipments by lifecycle status + by carrier (on-time vs delayed)
        $byStatus = [];
        foreach ([Shipment::STATUS_CREATED, Shipment::STATUS_BOOKED, Shipment::STATUS_PICKED_UP, Shipment::STATUS_IN_TRANSIT, Shipment::STATUS_DELIVERED] as $status) {
            $byStatus[$status] = $shipments->where('current_status', $status)->count();
        }

        $byCarrier = Carrier::with(['shipments' => fn ($q) => $q->active()])->get()->map(function (Carrier $carrier) use ($lowH, $highH) {
            $delayed = $carrier->shipments->filter(function (Shipment $s) use ($lowH, $highH) {
                return $this->delayClassifier->assess($s->expected_arrival_at, $s->estimated_arrival_at, $lowH, $highH)['delayed'];
            })->count();

            return [
                'name' => $carrier->name,
                'total' => $carrier->shipments->count(),
                'delayed' => $delayed,
                'on_time' => $carrier->shipments->count() - $delayed,
            ];
        })->filter(fn ($row) => $row['total'] > 0)->values();

        return view('dashboard.index', [
            'scenario' => $scenario,
            'metrics' => $metrics,
            'shipments' => $shipments,
            'carriers' => Carrier::all(),
            'byStatus' => $byStatus,
            'byCarrier' => $byCarrier,
        ]);
    }

    /**
     * Render the Shipments split-map & tracking feed view.
     * Server-side search, filters, sorting, and pagination.
     */
    /**
     * Shipments index: split map view (tracking feed + corridor map + inspector)
     * or full table view, mirroring the original React UI.
     * Server-side search, filters, sorting; split view shows every filtered
     * shipment in the feed, table view paginates 10 per page.
     */
    public function shipmentsIndex(Request $request): View
    {
        $user = $request->user();
        $visibleCount = Shipment::active()->visibleTo($user)->count();

        $query = Shipment::with(['origin', 'destination', 'carrier', 'trackingEvents', 'exceptions'])
            ->active()
            ->visibleTo($user);

        if ($status = $request->query('status')) {
            $query->where('current_status', strtoupper($status));
        }

        if ($carrierId = $request->query('carrier_id')) {
            $query->where('carrier_id', (int) $carrierId);
        }

        if ($originId = $request->query('origin_location_id')) {
            $query->where('origin_location_id', (int) $originId);
        }

        if ($destId = $request->query('destination_location_id')) {
            $query->where('destination_location_id', (int) $destId);
        }

        if ($q = trim((string) $request->query('q', ''))) {
            $query->where(function ($sub) use ($q) {
                $sub->where('shipment_number', 'like', "%{$q}%")
                    ->orWhere('cargo_type', 'like', "%{$q}%")
                    ->orWhereHas('carrier', fn ($cq) => $cq->where('code', 'like', "%{$q}%")->orWhere('name', 'like', "%{$q}%"))
                    ->orWhereHas('origin', fn ($oq) => $oq->where('code', 'like', "%{$q}%")->orWhere('city', 'like', "%{$q}%")->orWhere('name', 'like', "%{$q}%"))
                    ->orWhereHas('destination', fn ($dq) => $dq->where('code', 'like', "%{$q}%")->orWhere('city', 'like', "%{$q}%")->orWhere('name', 'like', "%{$q}%"));
            });
        }

        if ($request->query('date_from')) {
            $query->whereDate('expected_departure_at', '>=', $request->query('date_from'));
        }

        if ($request->query('date_to')) {
            $query->whereDate('expected_departure_at', '<=', $request->query('date_to'));
        }

        if ($request->query('delayed') !== null && $request->query('delayed') !== '') {
            if (filter_var($request->query('delayed'), FILTER_VALIDATE_BOOLEAN)) {
                $query->whereColumn('estimated_arrival_at', '>', 'expected_arrival_at');
            } else {
                $query->where(function ($sub) {
                    $sub->whereNull('estimated_arrival_at')
                        ->orWhereColumn('estimated_arrival_at', '<=', 'expected_arrival_at');
                });
            }
        }

        if ($request->query('exceptions') === '1') {
            $query->whereHas('exceptions', fn ($eq) => $eq->where('status', '!=', 'RESOLVED'));
        }

        $sort = $request->query('sort', 'latest');
        $query->when($sort === 'oldest', fn ($q) => $q->orderBy('id'))
            ->when($sort === 'eta_asc', fn ($q) => $q->orderByRaw('COALESCE(estimated_arrival_at, expected_arrival_at) asc'))
            ->when($sort === 'eta_desc', fn ($q) => $q->orderByRaw('COALESCE(estimated_arrival_at, expected_arrival_at) desc'))
            ->when($sort === 'ref_asc', fn ($q) => $q->orderBy('shipment_number'))
            ->when($sort === 'weight_desc', fn ($q) => $q->orderByDesc('weight_kg'))
            ->when(! in_array($sort, ['oldest', 'eta_asc', 'eta_desc', 'ref_asc', 'weight_desc'], true), fn ($q) => $q->orderByDesc('id'));

        $view = $request->query('view', 'split') === 'table' ? 'table' : 'split';

        if ($view === 'table') {
            $page = $query->paginate(10)->withQueryString();
            $list = $page->getCollection();
            $filteredCount = $page->total();
        } else {
            $list = $query->get();
            $page = null;
            $filteredCount = $list->count();
        }

        $cards = $list->map(fn (Shipment $s) => $this->shipmentCard($s))->values();

        // Focused shipment: explicit ?focus=, else first IN_TRANSIT, else first row.
        $focusId = (int) $request->query('focus', 0);
        if ($focusId === 0 || ! $cards->contains('id', $focusId)) {
            $focusId = $cards->firstWhere('current_status', 'IN_TRANSIT')['id']
                ?? $cards->first()['id']
                ?? 0;
        }
        $focused = $cards->firstWhere('id', $focusId);

        $mapEntries = $cards->map(function (array $c) {
            return [
                'id' => $c['id'],
                'shipment_number' => $c['shipment_number'],
                'status' => $c['current_status'],
                'carrier_name' => $c['carrier_name'],
                'service_type' => $c['service_type'],
                'delayed' => $c['delayed'],
                'origin' => $c['map_origin'],
                'destination' => $c['map_destination'],
                'current_position' => $c['map_current'],
                'previous_position' => $c['map_previous'],
                'checkpoints' => $c['map_checkpoints'],
            ];
        })->values();

        return view('shipments.index', [
            'view' => $view,
            'page' => $page,
            'cards' => $cards,
            'focused' => $focused,
            'focusId' => $focusId,
            'visibleCount' => $visibleCount,
            'filteredCount' => $filteredCount,
            'carriers' => Carrier::all(),
            'locations' => Location::all(),
            'mapEntries' => $mapEntries,
            'filters' => $request->only(['q', 'status', 'carrier_id', 'origin_location_id', 'destination_location_id', 'date_from', 'date_to', 'delayed', 'exceptions', 'sort', 'view', 'focus']),
        ]);
    }

    /**
     * Per-shipment presentation data for the tracking feed card, the map,
     * and the inspector — the same fields the original React UI computed
     * client-side (ShipmentCard + ProgressRoute + WorldMap).
     */
    private function shipmentCard(Shipment $s): array
    {
        $origin = $s->origin;
        $destination = $s->destination;
        $carrier = $s->carrier;

        $delay = $this->delayClassifier->assess($s->expected_arrival_at, $s->estimated_arrival_at);
        $delayed = (bool) $delay['delayed'];
        $delayHours = (float) $delay['delay_hours'];

        $hasOpenException = $s->exceptions->contains(fn ($e) => $e->status !== 'RESOLVED');

        $events = $s->trackingEvents->sortByDesc('occurred_at')->values();
        $coordEvents = $events->filter(fn ($e) => $e->latitude !== null && $e->longitude !== null)->values();
        $latest = $events->first();
        $latestCoord = $coordEvents->first();
        $previousCoord = $coordEvents->get(1);

        $isAtOrigin = $latestCoord !== null
            && abs($latestCoord->latitude - $origin->latitude) < 0.02
            && abs($latestCoord->longitude - $origin->longitude) < 0.02;

        $hasTransitCheckpoint = false;
        if ($latest !== null && $latest->location_name) {
            $name = strtolower($latest->location_name);
            $hasTransitCheckpoint = ! str_contains($name, strtolower($origin->city))
                && ! str_contains($name, strtolower($origin->name))
                && ! str_contains($name, strtolower($destination->city));
        }

        // Time-based corridor progress (0-100) from departure to arrival.
        $timePercent = null;
        if ($s->expected_departure_at && $s->expected_arrival_at) {
            $total = $s->expected_arrival_at->getTimestamp() - $s->expected_departure_at->getTimestamp();
            if ($total > 0) {
                $timePercent = max(0, min(100, (time() - $s->expected_departure_at->getTimestamp()) / $total * 100));
            }
        }

        // Card progress (ShipmentCard rules).
        if ($s->current_status === 'DELIVERED') {
            $cardPercent = 100;
        } elseif ($hasTransitCheckpoint) {
            $cardPercent = (int) max(42, min(85, $timePercent ?? 45));
        } elseif ($s->current_status === 'CREATED') {
            $cardPercent = 0;
        } elseif ($s->current_status === 'BOOKED') {
            $cardPercent = 14;
        } elseif ($s->current_status === 'PICKED_UP') {
            $cardPercent = 28;
        } else {
            $cardPercent = (int) max(35, min(92, $timePercent ?? 50));
        }

        // Inspector progress (ProgressRoute rules: geographic when a checkpoint exists).
        if ($s->current_status === 'DELIVERED') {
            $inspectorPercent = 100;
        } elseif ($latestCoord !== null && ! $isAtOrigin) {
            $d1 = $this->haversineKm([$origin->latitude, $origin->longitude], [$latestCoord->latitude, $latestCoord->longitude]);
            $d2 = $this->haversineKm([$latestCoord->latitude, $latestCoord->longitude], [$destination->latitude, $destination->longitude]);
            $inspectorPercent = (int) min(92, max(18, round($d1 / max(1, $d1 + $d2) * 100)));
        } elseif ($s->current_status === 'CREATED') {
            $inspectorPercent = 0;
        } elseif ($s->current_status === 'BOOKED') {
            $inspectorPercent = 14;
        } elseif ($s->current_status === 'PICKED_UP') {
            $inspectorPercent = 28;
        } else {
            $inspectorPercent = (int) max(35, min(92, $timePercent ?? 50));
        }

        // Transit stops (chronological, between origin and destination only).
        $stops = [];
        $seen = [];
        foreach ($coordEvents->sortBy('occurred_at') as $ev) {
            $nearOrigin = abs($ev->latitude - $origin->latitude) < 0.02 && abs($ev->longitude - $origin->longitude) < 0.02;
            $nearDest = abs($ev->latitude - $destination->latitude) < 0.02 && abs($ev->longitude - $destination->longitude) < 0.02;
            if ($nearOrigin || $nearDest) {
                continue;
            }
            $key = round($ev->latitude, 2).','.round($ev->longitude, 2);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $d1 = $this->haversineKm([$origin->latitude, $origin->longitude], [$ev->latitude, $ev->longitude]);
            $d2 = $this->haversineKm([$ev->latitude, $ev->longitude], [$destination->latitude, $destination->longitude]);
            $stops[] = [
                'id' => $ev->id,
                'name' => $ev->location_name,
                'latitude' => (float) $ev->latitude,
                'longitude' => (float) $ev->longitude,
                'percentAlong' => (int) min(88, max(14, round($d1 / max(1, $d1 + $d2) * 100))),
            ];
        }

        $durationHours = 0;
        if ($s->expected_departure_at && $s->expected_arrival_at) {
            $durationHours = (int) round(($s->expected_arrival_at->getTimestamp() - $s->expected_departure_at->getTimestamp()) / 3600);
        }

        $etaAt = $s->estimated_arrival_at ?? $s->expected_arrival_at;

        return [
            'id' => $s->id,
            'shipment_number' => $s->shipment_number,
            'cargo_type' => $s->cargo_type,
            'current_status' => $s->current_status,
            'status_label' => match ($s->current_status) {
                'CREATED' => 'Created',
                'BOOKED' => 'Booked',
                'PICKED_UP' => 'Picked up',
                'IN_TRANSIT' => 'In transit',
                'DELIVERED' => 'Delivered',
                default => $s->current_status,
            },
            'origin_code' => $origin->code,
            'origin_city' => $origin->city,
            'destination_code' => $destination->code,
            'destination_city' => $destination->city,
            'carrier_name' => $carrier->name,
            'carrier_code' => $carrier->code,
            'service_type' => $carrier->service_type,
            'service_label' => match ($carrier->service_type) {
                'SEA' => 'Sea',
                'ROAD' => 'Road',
                default => 'Air',
            },
            'delayed' => $delayed,
            'delay_hours' => $delayHours,
            'delay_state' => $delay['state'] ?? 'pending',
            'has_open_exception' => $hasOpenException,
            'latest_checkpoint_name' => $latest?->location_name,
            'latest_checkpoint_time' => $latest ? $this->formatDateTime($latest->occurred_at) : null,
            'has_transit_checkpoint' => $hasTransitCheckpoint,
            'card_percent' => $cardPercent,
            'inspector_percent' => $inspectorPercent,
            'progress_label' => $this->progressLabel($s, $etaAt),
            'transit_stops' => $stops,
            'transit_stop_count' => count($stops),
            'duration_hours' => $durationHours,
            'weight_kg' => $s->weight_kg,
            'volume_m3' => $s->volume_m3,
            'eta_short' => $etaAt ? $this->formatShortDate($etaAt) : '—',
            'plan_short' => $s->expected_arrival_at ? $this->formatShortDate($s->expected_arrival_at) : '—',
            'dep_datetime' => $s->expected_departure_at ? $this->formatDateTime($s->expected_departure_at) : '—',
            'arr_datetime' => $etaAt ? $this->formatDateTime($etaAt) : '—',
            'arr_expected_datetime' => $s->expected_arrival_at ? $this->formatDateTime($s->expected_arrival_at) : '—',
            'map_origin' => [
                'latitude' => (float) $origin->latitude,
                'longitude' => (float) $origin->longitude,
                'location_name' => $origin->code.' · '.$origin->city,
                'code' => $origin->code,
                'time' => $s->expected_departure_at ? 'Dep: '.$this->formatLongDate($s->expected_departure_at) : '',
            ],
            'map_destination' => [
                'latitude' => (float) $destination->latitude,
                'longitude' => (float) $destination->longitude,
                'location_name' => $destination->code.' · '.$destination->city,
                'code' => $destination->code,
                'time' => $etaAt ? 'ETA: '.$this->formatLongDate($etaAt) : '',
            ],
            'map_current' => $latestCoord ? [
                'latitude' => (float) $latestCoord->latitude,
                'longitude' => (float) $latestCoord->longitude,
                'location_name' => $latestCoord->location_name,
                'time' => $this->formatDateTime($latestCoord->occurred_at),
            ] : [
                'latitude' => (float) $origin->latitude,
                'longitude' => (float) $origin->longitude,
                'location_name' => $origin->name,
            ],
            'map_previous' => $previousCoord ? [
                'latitude' => (float) $previousCoord->latitude,
                'longitude' => (float) $previousCoord->longitude,
            ] : [
                'latitude' => (float) $origin->latitude,
                'longitude' => (float) $origin->longitude,
            ],
            'map_checkpoints' => $coordEvents
                ->filter(function ($ev) use ($origin, $destination) {
                    $nearOrigin = abs($ev->latitude - $origin->latitude) < 0.02 && abs($ev->longitude - $origin->longitude) < 0.02;
                    $nearDest = abs($ev->latitude - $destination->latitude) < 0.02 && abs($ev->longitude - $destination->longitude) < 0.02;

                    return ! $nearOrigin && ! $nearDest;
                })
                ->map(fn ($ev) => [
                    'latitude' => (float) $ev->latitude,
                    'longitude' => (float) $ev->longitude,
                    'location_name' => $ev->location_name,
                    'time' => $this->formatDateTime($ev->occurred_at),
                ])
                ->values()
                ->all(),
        ];
    }

    private function haversineKm(array $a, array $b): float
    {
        $r = 6371.0;
        $dLat = deg2rad($b[0] - $a[0]);
        $dLng = deg2rad($b[1] - $a[1]);
        $h = sin($dLat / 2) ** 2 + cos(deg2rad($a[0])) * cos(deg2rad($b[0])) * sin($dLng / 2) ** 2;

        return 2 * $r * asin(min(1, sqrt($h)));
    }

    private function formatDateTime($d): string
    {
        return $d->format('M j · H:i');
    }

    private function formatShortDate($d): string
    {
        return $d->format('M j');
    }

    private function formatLongDate($d): string
    {
        return $d->format('M j, Y H:i');
    }

    private function progressLabel(Shipment $s, $etaAt): string
    {
        if ($s->current_status === 'DELIVERED') {
            return 'Delivered — POD confirmed';
        }
        $now = time();
        if ($s->expected_departure_at && $s->expected_departure_at->getTimestamp() > $now) {
            return 'Scheduled — departs in '.$this->humanDuration($s->expected_departure_at->getTimestamp() - $now);
        }
        if ($etaAt) {
            $diff = $etaAt->getTimestamp() - $now;
            if ($diff >= 0) {
                return 'On the way — ETA in '.$this->humanDuration($diff);
            }

            return 'Overdue by '.$this->humanDuration(-$diff);
        }

        return 'On the way';
    }

    private function humanDuration(int $seconds): string
    {
        $hours = (int) round($seconds / 3600);
        $days = (int) floor($hours / 24);
        $rest = $hours % 24;

        return $days > 0 ? $days.'D '.$rest.'H' : $hours.'H';
    }

    /**
     * New-shipment form (same fields as the original ShipmentFormView).
     */
    public function createShipment(Request $request): View
    {
        Gate::authorize('create', Shipment::class);

        return view('shipments.create', [
            'carriers' => Carrier::all(),
            'locations' => Location::all(),
            'customers' => User::where('role', 'CUSTOMER')->orderBy('name')->get(),
            'isCustomer' => $request->user()->isCustomer(),
        ]);
    }

    /**
     * Store a new shipment from the web form. Mirrors the API store:
     * shipment + one manifest item + initial CREATED anchor at origin,
     * plus the SHIPMENT_CREATED audit entry.
     */
    public function storeShipment(StoreShipmentRequest $request): RedirectResponse
    {
        Gate::authorize('create', Shipment::class);

        $validated = $request->validated();

        if ($request->user()->isCustomer()) {
            $validated['customer_id'] = $request->user()->id;
        }

        $carrier = Carrier::findOrFail($validated['carrier_id']);
        $origin = Location::findOrFail($validated['origin_location_id']);

        $nextSeq = Shipment::max('id') + 1;
        $shipmentNumber = sprintf('CT%04d%s', $nextSeq, strtoupper($carrier->code));

        $shipment = DB::transaction(function () use ($validated, $shipmentNumber, $origin, $request) {
            $s = Shipment::create([
                'shipment_number' => $shipmentNumber,
                'customer_id' => $validated['customer_id'],
                'carrier_id' => $validated['carrier_id'],
                'origin_location_id' => $validated['origin_location_id'],
                'destination_location_id' => $validated['destination_location_id'],
                'current_status' => Shipment::STATUS_CREATED,
                'cargo_type' => $validated['cargo_type'],
                'weight_kg' => $validated['weight_kg'],
                'volume_m3' => $validated['volume_m3'],
                'expected_departure_at' => $validated['expected_departure_at'] ?? now(),
                'expected_arrival_at' => $validated['expected_arrival_at'],
                'estimated_arrival_at' => $validated['estimated_arrival_at'] ?? $validated['expected_arrival_at'],
            ]);

            foreach ($validated['items'] as $item) {
                ShipmentItem::create([
                    'shipment_id' => $s->id,
                    'description' => $item['description'],
                    'quantity' => $item['quantity'],
                    'weight_kg' => $item['weight_kg'],
                    'volume_m3' => $item['volume_m3'],
                ]);
            }

            $depTime = $s->expected_departure_at ?? now();
            TrackingEvent::create([
                'shipment_id' => $s->id,
                'event_type' => Shipment::STATUS_CREATED,
                'occurred_at' => $depTime,
                'location_name' => sprintf('%s (%s)', $origin->name, $origin->code),
                'latitude' => $origin->latitude,
                'longitude' => $origin->longitude,
                'description' => 'Shipment registered at origin port.',
                'created_by' => $request->user()?->id,
            ]);

            AuditLog::create([
                'actor_id' => $request->user()?->id,
                'action' => 'SHIPMENT_CREATED',
                'entity_type' => 'Shipment',
                'entity_id' => $s->id,
                'changes' => [
                    'shipment_number' => ['from' => null, 'to' => $s->shipment_number],
                    'current_status' => ['from' => null, 'to' => $s->current_status],
                ],
            ]);

            return $s;
        });

        return redirect()->route('shipments.show', $shipment)
            ->with('status', 'Shipment '.$shipment->shipment_number.' created.');
    }

    /**
     * Render the Shipment Dossier, Multi-Leg Route Map (Origin -> Transit Stops -> Destination),
     * Append-Only Timeline, Cargo Manifest, Live Weather, and Printable Bill of Lading (e-B/L).
     */
    public function shipmentShow(Request $request, Shipment $shipment): View
    {
        Gate::authorize('view', $shipment);

        $shipment->load(['customer', 'carrier', 'origin', 'destination', 'items', 'trackingEvents.creator', 'exceptions']);
        $scenario = DB::table('scenario_configs')->first();

        $delay = $this->delayClassifier->assess(
            $shipment->expected_arrival_at,
            $shipment->estimated_arrival_at,
            (int) ($scenario->low_threshold_hours ?? 6),
            (int) ($scenario->high_threshold_hours ?? 24)
        );

        $corridor = $this->lifecycleService->buildOrderedCorridorWaypoints($shipment);
        $allowedNext = $this->lifecycleService->allowedNextStatuses($shipment->current_status);

        // Single-entry payload for the 3D globe (same shape as the index mapEntries).
        $stops = $corridor['intermediate_stops'];
        $lastStop = end($stops) ?: null;
        $globeEntry = [
            'id' => $shipment->id,
            'shipment_number' => $shipment->shipment_number,
            'status' => $shipment->current_status,
            'delayed' => (bool) ($delay['delayed'] ?? false),
            'origin' => [
                'latitude' => (float) $corridor['origin']['latitude'],
                'longitude' => (float) $corridor['origin']['longitude'],
                'location_name' => $corridor['origin']['location_name'],
                'code' => $corridor['origin']['code'],
            ],
            'destination' => [
                'latitude' => (float) $corridor['destination']['latitude'],
                'longitude' => (float) $corridor['destination']['longitude'],
                'location_name' => $corridor['destination']['location_name'],
                'code' => $corridor['destination']['code'],
            ],
            'current_position' => $lastStop ? [
                'latitude' => (float) $lastStop['latitude'],
                'longitude' => (float) $lastStop['longitude'],
                'location_name' => $lastStop['location_name'],
            ] : [
                'latitude' => (float) $corridor['origin']['latitude'],
                'longitude' => (float) $corridor['origin']['longitude'],
                'location_name' => $corridor['origin']['location_name'],
            ],
            'checkpoints' => array_map(fn (array $s) => [
                'latitude' => (float) $s['latitude'],
                'longitude' => (float) $s['longitude'],
                'location_name' => $s['location_name'],
            ], $corridor['intermediate_stops']),
        ];

        return view('shipments.show', [
            'shipment' => $shipment,
            'delay' => $delay,
            'corridor' => $corridor,
            'globeEntry' => $globeEntry,
            'allowedNext' => $allowedNext,
            'provinces' => IndonesianPortCatalog::PROVINCES,
            'portPresets' => IndonesianPortCatalog::presets(),
            'weather' => [
                'origin' => $this->telemetryService->fetchCorridorWeather($shipment->origin->latitude, $shipment->origin->longitude),
                'destination' => $this->telemetryService->fetchCorridorWeather($shipment->destination->latitude, $shipment->destination->longitude),
            ],
        ]);
    }

    /**
     * 1-click lifecycle advance (operator/admin only): append a tracking event
     * for the next allowed status. Invalid transitions are rejected by the
     * lifecycle service, never by hiding the button alone.
     */
    public function advanceShipment(Request $request, Shipment $shipment): RedirectResponse
    {
        Gate::authorize('operate', $shipment);

        $next = $this->lifecycleService->allowedNextStatuses($shipment->current_status)[0] ?? null;

        if ($next === null) {
            return back()->withErrors(['status' => 'This shipment is DELIVERED (terminal) and cannot advance.']);
        }

        $lastEvent = $shipment->trackingEvents()->orderByDesc('occurred_at')->first();

        try {
            $this->lifecycleService->appendTrackingEvent($shipment, [
                'event_type' => $next,
                'occurred_at' => now()->toIso8601String(),
                'location_name' => $lastEvent?->location_name ?? $shipment->origin->name,
                'latitude' => $lastEvent?->latitude,
                'longitude' => $lastEvent?->longitude,
                'description' => sprintf('Lifecycle advanced to %s.', $next),
            ], $request->user());
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        return back()->with('status', sprintf('%s advanced to %s.', $shipment->shipment_number, $next));
    }

    /**
     * Append a tracking checkpoint / lifecycle event from the dossier form.
     */
    public function storeTrackingEvent(Request $request, Shipment $shipment): RedirectResponse
    {
        Gate::authorize('operate', $shipment);

        $validated = $request->validate([
            'event_type' => ['required', 'string', 'in:CREATED,BOOKED,PICKED_UP,IN_TRANSIT,DELIVERED,CHECKPOINT,INFO'],
            'occurred_at' => ['required', 'date'],
            'location_name' => ['required', 'string', 'max:180'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'description' => ['nullable', 'string', 'max:1000'],
            'estimated_arrival_at' => ['nullable', 'date'],
        ]);

        try {
            $this->lifecycleService->appendTrackingEvent($shipment, $validated, $request->user());
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['event' => $e->getMessage()])->withInput();
        }

        return back()->with('status', 'Tracking event recorded.');
    }

    /**
     * Raise an exception from the dossier form.
     */
    public function storeException(Request $request, Shipment $shipment): RedirectResponse
    {
        Gate::authorize('operate', $shipment);

        $validated = $request->validate([
            'type' => ['required', 'string', 'in:' . implode(',', ShipmentException::TYPES)],
            'severity' => ['required', 'string', 'in:LOW,MEDIUM,HIGH'],
            'detected_at' => ['required', 'date'],
            'location_name' => ['nullable', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:1000'],
            'estimated_arrival_at' => ['nullable', 'date'],
        ]);

        $this->lifecycleService->raiseException($shipment, $validated, $request->user());

        return back()->with('status', 'Exception raised and the customer was notified.');
    }

    /**
     * Move an exception through OPEN -> INVESTIGATING -> RESOLVED.
     */
    public function updateException(Request $request, ShipmentException $exception): RedirectResponse
    {
        Gate::authorize('operate', $exception->shipment);

        $validated = $request->validate([
            'status' => ['required', 'string', 'in:OPEN,INVESTIGATING,RESOLVED'],
        ]);

        $targetStatus = $validated['status'];

        if (! $this->lifecycleService->canTransitionExceptionStatus($exception->status, $targetStatus)) {
            return back()->withErrors(['status' => sprintf(
                'An exception in %s can only move to: %s.',
                $exception->status,
                implode(', ', $this->lifecycleService->allowedNextExceptionStatuses($exception->status)) ?: 'nothing — it is resolved'
            )]);
        }

        $oldStatus = $exception->status;
        $exception->status = $targetStatus;

        if ($targetStatus === ShipmentException::STATUS_RESOLVED) {
            $exception->resolved_at = now();
        }

        $exception->save();

        AuditLog::create([
            'actor_id' => $request->user()?->id,
            'action' => 'EXCEPTION_STATUS_CHANGED',
            'entity_type' => 'ShipmentException',
            'entity_id' => $exception->id,
            'changes' => ['status' => ['from' => $oldStatus, 'to' => $targetStatus]],
        ]);

        return back()->with('status', sprintf('Exception moved to %s.', $targetStatus));
    }

    /**
     * Render the Printable Official Electronic Bill of Lading (e-B/L) view.
     */
    public function billOfLading(Request $request, Shipment $shipment): View
    {
        Gate::authorize('view', $shipment);

        $shipment->load(['customer', 'carrier', 'origin', 'destination', 'items', 'trackingEvents']);
        $corridor = $this->lifecycleService->buildOrderedCorridorWaypoints($shipment);

        return view('shipments.bill-of-lading', [
            'shipment' => $shipment,
            'corridor' => $corridor,
        ]);
    }

    /**
     * Render the Port Locations & 38 Indonesian Provinces Registry view.
     */
    public function locationsIndex(Request $request): View
    {
        return view('locations.index', [
            'locations' => Location::orderBy('name')->get(),
            'provinces' => IndonesianPortCatalog::PROVINCES,
            'presets' => IndonesianPortCatalog::presets(),
        ]);
    }

    public function storeLocation(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:180'],
            'code' => ['required', 'string', 'max:16', 'unique:locations,code'],
            'city' => ['required', 'string', 'max:120'],
            'province' => ['nullable', 'string', 'max:120'],
            'country' => ['required', 'string', 'max:120'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);

        Location::create($validated);

        return back()->with('status', sprintf('Location %s registered.', $validated['code']));
    }

    /**
     * Render the Carriers Directory view.
     */
    public function carriersIndex(): View
    {
        return view('carriers.index', [
            'carriers' => Carrier::withCount('shipments')->orderBy('name')->get(),
        ]);
    }

    public function storeCarrier(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'code' => ['required', 'string', 'max:16', 'unique:carriers,code'],
            'service_type' => ['required', 'string', 'in:SEA,AIR,ROAD'],
            'contact_email' => ['nullable', 'email', 'max:180'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        Carrier::create($validated);

        return back()->with('status', sprintf('Carrier %s registered.', $validated['code']));
    }

    /**
     * Render the current user's in-app notifications.
     */
    public function notificationsIndex(Request $request): View
    {
        $notifications = Notification::where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->paginate(15);

        return view('notifications.index', [
            'notifications' => $notifications,
            'unreadCount' => Notification::where('user_id', $request->user()->id)->whereNull('read_at')->count(),
        ]);
    }

    public function markNotificationRead(Request $request, Notification $notification): RedirectResponse
    {
        if ((int) $notification->user_id !== (int) $request->user()->id) {
            abort(403);
        }

        $notification->update(['read_at' => now()]);

        return back();
    }

    public function markAllNotificationsRead(Request $request): RedirectResponse
    {
        Notification::where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return back()->with('status', 'All notifications marked as read.');
    }

    /**
     * Render the Immutable Audit Trail view (admin only, route-level).
     */
    public function auditIndex(): View
    {
        return view('audit.index', [
            'logs' => AuditLog::with('actor')->orderByDesc('created_at')->limit(100)->get(),
        ]);
    }

    /**
     * Admin: users & live delay-rule configuration.
     */
    public function scenarioIndex(): View
    {
        return view('scenario.index', [
            'scenario' => DB::table('scenario_configs')->first(),
            'presets' => config('cargotrace.presets'),
            'users' => User::orderBy('name')->get(),
        ]);
    }

    public function updateScenario(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'group_code' => ['required', 'string', 'in:DEFAULT,GROUP_A,GROUP_B,GROUP_C,CUSTOM'],
            'route_corridor' => ['required', 'string', 'max:180'],
            'low_threshold_hours' => ['required', 'integer', 'min:1', 'max:168'],
            'high_threshold_hours' => ['required', 'integer', 'min:2', 'max:720', 'gt:low_threshold_hours'],
            'primary_exception' => ['required', 'string', 'in:' . implode(',', ShipmentException::TYPES)],
            'kpi_focus' => ['required', 'string', 'max:180'],
        ]);

        $old = DB::table('scenario_configs')->first();

        DB::table('scenario_configs')->updateOrInsert(
            ['id' => 1],
            array_merge($validated, ['updated_at' => now()])
        );

        AuditLog::create([
            'actor_id' => $request->user()?->id,
            'action' => 'SCENARIO_UPDATED',
            'entity_type' => 'ScenarioConfig',
            'entity_id' => 1,
            'changes' => [
                'group_code' => ['from' => $old->group_code ?? null, 'to' => $validated['group_code']],
                'low_threshold_hours' => ['from' => $old->low_threshold_hours ?? null, 'to' => $validated['low_threshold_hours']],
                'high_threshold_hours' => ['from' => $old->high_threshold_hours ?? null, 'to' => $validated['high_threshold_hours']],
            ],
        ]);

        return back()->with('status', 'Delay rule updated. Classifications recalculate immediately.');
    }

    public function storeUser(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:180', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', 'string', 'in:ADMIN,LOGISTICS_OPERATOR,CUSTOMER'],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
        ]);

        AuditLog::create([
            'actor_id' => $request->user()?->id,
            'action' => 'USER_CREATED',
            'entity_type' => 'User',
            'entity_id' => $user->id,
            'changes' => ['email' => ['from' => null, 'to' => $user->email], 'role' => ['from' => null, 'to' => $user->role]],
        ]);

        return back()->with('status', sprintf('User %s created.', $user->email));
    }

    public function updateUserRole(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'role' => ['required', 'string', 'in:ADMIN,LOGISTICS_OPERATOR,CUSTOMER'],
        ]);

        if ((int) $user->id === (int) $request->user()->id && $validated['role'] !== User::ROLE_ADMIN) {
            return back()->withErrors(['role' => 'You cannot demote your own administrator account.']);
        }

        $oldRole = $user->role;
        $user->update(['role' => $validated['role']]);

        AuditLog::create([
            'actor_id' => $request->user()?->id,
            'action' => 'USER_ROLE_CHANGED',
            'entity_type' => 'User',
            'entity_id' => $user->id,
            'changes' => ['role' => ['from' => $oldRole, 'to' => $validated['role']]],
        ]);

        return back()->with('status', sprintf('%s is now %s.', $user->email, $validated['role']));
    }
}
