<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreExceptionRequest;
use App\Http\Requests\StoreShipmentRequest;
use App\Http\Requests\StoreTrackingEventRequest;
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
            ->active();

        if ($request->user()) {
            $query->visibleTo($request->user());
        }

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

        $shipments = $query->orderByDesc('created_at')->paginate((int) $request->query('per_page', 15));

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
    public function show(Shipment $shipment): JsonResponse
    {
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
        $validated = $request->validated();
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
     * POST /api/v1/shipments/{shipment}/tracking-events
     * Append a chronological tracking checkpoint or transition shipment lifecycle status.
     */
    public function storeTrackingEvent(StoreTrackingEventRequest $request, Shipment $shipment): JsonResponse
    {
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
        $validated = $request->validated();

        $exception = DB::transaction(function () use ($shipment, $validated, $request) {
            $exc = ShipmentException::create([
                'shipment_id' => $shipment->id,
                'type' => $validated['type'],
                'severity' => $validated['severity'],
                'status' => ShipmentException::STATUS_OPEN,
                'detected_at' => $validated['detected_at'],
                'description' => $validated['description'] ?? null,
            ]);

            if (! empty($validated['estimated_arrival_at'])) {
                $shipment->update(['estimated_arrival_at' => $validated['estimated_arrival_at']]);
            }

            TrackingEvent::create([
                'shipment_id' => $shipment->id,
                'event_type' => 'EXCEPTION',
                'occurred_at' => $validated['detected_at'],
                'location_name' => $validated['location_name'] ?? $shipment->origin->name,
                'description' => sprintf('%s raised (%s): %s', $exc->type, $exc->severity, $exc->description ?? ''),
                'created_by' => $request->user()?->id,
            ]);

            Notification::create([
                'user_id' => $shipment->customer_id,
                'shipment_id' => $shipment->id,
                'type' => 'EXCEPTION_RAISED',
                'title' => sprintf('Exception on %s (%s)', $shipment->shipment_number, $exc->type),
                'body' => $exc->description ?? 'Operational exception raised on your corridor shipment.',
            ]);

            return $exc;
        });

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
