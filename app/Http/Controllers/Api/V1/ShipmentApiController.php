<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreExceptionRequest;
use App\Http\Requests\StoreShipmentRequest;
use App\Http\Requests\StoreTrackingEventRequest;
use App\Http\Requests\UpdateShipmentRequest;
use App\Models\AuditLog;
use App\Models\Carrier;
use App\Models\Location;
use App\Models\Notification;
use App\Models\Shipment;
use App\Models\ShipmentException;
use App\Models\ShipmentItem;
use App\Models\TrackingEvent;
use App\Services\DelayClassifierService;
use App\Services\ExternalTelemetryService;
use App\Services\IndonesianPortCatalog;
use App\Services\ShipmentLifecycleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

class ShipmentApiController extends Controller
{
    public function __construct(
        private readonly ShipmentLifecycleService $lifecycleService,
        private readonly DelayClassifierService $delayClassifier,
        private readonly ExternalTelemetryService $telemetryService
    ) {}

    /**
     * GET /api/v1/shipments
     * List shipments with filtering, search, sorting, pagination, and delay assessment.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Shipment::with(['customer', 'carrier', 'origin', 'destination', 'exceptions'])
            ->active()
            ->visibleTo($request->user());

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
                    ->orWhereHas('origin', fn ($oq) => $oq->where('code', 'like', "%{$q}%")->orWhere('city', 'like', "%{$q}%"))
                    ->orWhereHas('destination', fn ($dq) => $dq->where('code', 'like', "%{$q}%")->orWhere('city', 'like', "%{$q}%"));
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

        $sort = $request->query('sort', 'latest');
        $query->when($sort === 'oldest', fn ($q) => $q->orderBy('created_at'))
            ->when($sort === 'eta_asc', fn ($q) => $q->orderBy('expected_arrival_at'))
            ->when($sort === 'eta_desc', fn ($q) => $q->orderByDesc('expected_arrival_at'))
            ->when($sort === 'ref_asc', fn ($q) => $q->orderBy('shipment_number'))
            ->when(! in_array($sort, ['oldest', 'eta_asc', 'eta_desc', 'ref_asc'], true), fn ($q) => $q->orderByDesc('created_at'));

        $shipments = $query->paginate((int) $request->query('per_page', 15));

        $scenario = DB::table('scenario_configs')->first();
        $lowH = (int) ($scenario->low_threshold_hours ?? 6);
        $highH = (int) ($scenario->high_threshold_hours ?? 24);

        $shipments->getCollection()->transform(function (Shipment $s) use ($lowH, $highH) {
            return array_merge($s->toArray(), [
                'delay_assessment' => $this->delayClassifier->assess(
                    $s->expected_arrival_at,
                    $s->estimated_arrival_at,
                    $lowH,
                    $highH
                ),
            ]);
        });

        return response()->json([
            'status' => 'ok',
            'scenario_rule' => $scenario,
            'data' => $shipments,
        ]);
    }

    /**
     * GET /api/v1/shipments/map
     * GeoJSON & ordered multi-leg trajectory coordinates (Origin -> Transit Stops -> Destination).
     */
    public function map(Request $request): JsonResponse
    {
        $shipments = Shipment::with(['origin', 'destination', 'carrier', 'trackingEvents'])
            ->active()
            ->visibleTo($request->user())
            ->get();

        $scenario = DB::table('scenario_configs')->first();
        $lowH = (int) ($scenario->low_threshold_hours ?? 6);
        $highH = (int) ($scenario->high_threshold_hours ?? 24);

        $features = $shipments->map(function (Shipment $s) use ($lowH, $highH) {
            $corridor = $this->lifecycleService->buildOrderedCorridorWaypoints($s);
            $delay = $this->delayClassifier->assess(
                $s->expected_arrival_at,
                $s->estimated_arrival_at,
                $lowH,
                $highH
            );

            return [
                'id' => $s->id,
                'shipment_number' => $s->shipment_number,
                'status' => $s->current_status,
                'cargo_type' => $s->cargo_type,
                'carrier_name' => $s->carrier->name,
                'carrier_mode' => $s->carrier->service_type,
                'delayed' => $delay['delayed'],
                'delay_assessment' => $delay,
                'origin' => $corridor['origin'],
                'intermediate_stops' => $corridor['intermediate_stops'],
                'destination' => $corridor['destination'],
            ];
        });

        return response()->json([
            'status' => 'ok',
            'count' => $features->count(),
            'data' => $features,
        ]);
    }

    /**
     * GET /api/v1/shipments/{shipment}
     * Inspect full shipment dossier, chronological timeline, exceptions, manifest, and live weather.
     */
    public function show(Request $request, Shipment $shipment): JsonResponse
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

        return response()->json([
            'status' => 'ok',
            'data' => [
                'shipment' => $shipment,
                'delay_assessment' => $delay,
                'corridor_waypoints' => $corridor,
                'allowed_next_statuses' => $this->lifecycleService->allowedNextStatuses($shipment->current_status),
                'weather' => [
                    'origin' => $this->telemetryService->fetchCorridorWeather($shipment->origin->latitude, $shipment->origin->longitude),
                    'destination' => $this->telemetryService->fetchCorridorWeather($shipment->destination->latitude, $shipment->destination->longitude),
                ],
            ],
        ]);
    }

    /**
     * POST /api/v1/shipments
     * Create a new shipment with optional planned intermediate transit stop (e.g. KRG -> Port of Singapore -> SUB).
     */
    public function store(StoreShipmentRequest $request): JsonResponse
    {
        Gate::authorize('create', Shipment::class);

        $validated = $request->validated();

        // Customers can only book under their own account.
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

            // Record initial CREATED anchor at Origin port
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

            // Optional Planned Intermediate Transit Stop (e.g. KRG -> Port of Singapore -> SUB)
            if (! empty($validated['transit_stop'])) {
                $stop = $validated['transit_stop'];
                TrackingEvent::create([
                    'shipment_id' => $s->id,
                    'event_type' => 'CHECKPOINT',
                    'occurred_at' => $depTime->copy()->addMinutes(5),
                    'location_name' => $stop['location_name'],
                    'latitude' => $stop['latitude'],
                    'longitude' => $stop['longitude'],
                    'description' => sprintf('Planned intermediate transit stop via %s.', $stop['location_name']),
                    'created_by' => $request->user()?->id,
                ]);
            }

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

        return response()->json([
            'status' => 'ok',
            'data' => $shipment->load(['items', 'trackingEvents', 'origin', 'destination']),
        ], 201);
    }

    /**
     * PATCH /api/v1/shipments/{shipment}
     * Update planning fields. Status changes are NOT allowed here; they must
     * go through tracking events so the lifecycle state machine is enforced.
     */
    public function update(UpdateShipmentRequest $request, Shipment $shipment): JsonResponse
    {
        Gate::authorize('operate', $shipment);

        $validated = $request->validated();

        if (! empty($validated['expected_arrival_at']) && ! empty($validated['estimated_arrival_at'])) {
            // no-op guard: keep the delay assessment consistent
        }

        $shipment->update($validated);

        AuditLog::create([
            'actor_id' => $request->user()?->id,
            'action' => 'SHIPMENT_UPDATED',
            'entity_type' => 'Shipment',
            'entity_id' => $shipment->id,
            'changes' => ['fields' => ['from' => null, 'to' => array_keys($validated)]],
        ]);

        return response()->json([
            'status' => 'ok',
            'data' => $shipment->fresh()->load(['customer', 'carrier', 'origin', 'destination', 'items']),
        ]);
    }

    /**
     * DELETE /api/v1/shipments/{shipment}
     * Archive the shipment (sets archived_at). Archived shipments leave the
     * active list and map; their history, items, and audit trail are kept.
     */
    public function destroy(Request $request, Shipment $shipment): JsonResponse
    {
        Gate::authorize('delete', $shipment);

        $shipment->update(['archived_at' => now()]);

        AuditLog::create([
            'actor_id' => $request->user()?->id,
            'action' => 'SHIPMENT_ARCHIVED',
            'entity_type' => 'Shipment',
            'entity_id' => $shipment->id,
            'changes' => [
                'archived_at' => ['from' => null, 'to' => $shipment->archived_at->toIso8601String()],
            ],
        ]);

        return response()->json([
            'status' => 'ok',
            'data' => $shipment->fresh(),
        ]);
    }

    /**
     * GET /api/v1/shipments/{shipment}/tracking
     * Chronological tracking timeline (newest first), server-side paginated.
     */
    public function tracking(Request $request, Shipment $shipment): JsonResponse
    {
        Gate::authorize('view', $shipment);

        $events = $shipment->trackingEvents()
            ->with('creator')
            ->orderByDesc('occurred_at')
            ->paginate((int) $request->query('per_page', 15));

        return response()->json(['status' => 'ok', 'data' => $events]);
    }

    /**
     * GET /api/v1/shipments/{shipment}/exceptions
     */
    public function exceptions(Request $request, Shipment $shipment): JsonResponse
    {
        Gate::authorize('view', $shipment);

        $exceptions = $shipment->exceptions()
            ->when($request->query('status'), fn ($query, $status) => $query->where('status', strtoupper($status)))
            ->orderByDesc('detected_at')
            ->paginate((int) $request->query('per_page', 15));

        return response()->json(['status' => 'ok', 'data' => $exceptions]);
    }

    /**
     * POST /api/v1/shipments/{shipment}/tracking-events
     * Append a chronological tracking checkpoint or transition shipment lifecycle status.
     */
    public function storeTrackingEvent(StoreTrackingEventRequest $request, Shipment $shipment): JsonResponse
    {
        Gate::authorize('operate', $shipment);

        try {
            $event = $this->lifecycleService->appendTrackingEvent(
                $shipment,
                $request->validated(),
                $request->user()
            );

            return response()->json([
                'status' => 'ok',
                'data' => $event,
                'shipment_status' => $shipment->fresh()->current_status,
            ], 201);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * POST /api/v1/shipments/{shipment}/exceptions
     * Raise an operational exception and append an EXCEPTION event on the timeline.
     */
    public function storeException(StoreExceptionRequest $request, Shipment $shipment): JsonResponse
    {
        Gate::authorize('operate', $shipment);

        $exception = $this->lifecycleService->raiseException(
            $shipment,
            $request->validated(),
            $request->user()
        );

        return response()->json([
            'status' => 'ok',
            'data' => $exception,
        ], 201);
    }

    /**
     * GET /api/v1/locations/catalog
     * Search active locations + 38 Indonesian Provinces catalog (Aceh: Bireuen, Krueng Geukuh, Lhokseumawe, etc.).
     */
    public function locationsCatalog(Request $request): JsonResponse
    {
        $q = strtolower(trim((string) $request->query('q', '')));
        $province = trim((string) $request->query('province', ''));

        $presets = collect(IndonesianPortCatalog::presets())
            ->when($province !== '', fn ($c) => $c->filter(fn ($p) => strcasecmp((string) $p['province'], $province) === 0))
            ->when($q !== '', fn ($c) => $c->filter(function ($p) use ($q) {
                return str_contains(strtolower($p['code']), $q)
                    || str_contains(strtolower($p['name']), $q)
                    || str_contains(strtolower($p['city']), $q)
                    || str_contains(strtolower((string) $p['province']), $q)
                    || str_contains(strtolower($p['country']), $q);
            }))
            ->values();

        return response()->json([
            'status' => 'ok',
            'provinces' => IndonesianPortCatalog::PROVINCES,
            'active_locations' => Location::orderBy('name')->get(),
            'catalog_presets' => $presets,
        ]);
    }
}
