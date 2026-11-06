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

    /**
     * POST /api/register (Slide 12 Authentication)
     */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['nullable', 'in:ADMIN,LOGISTICS_OPERATOR,CUSTOMER'],
        ]);

        $user = \App\Models\User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => \Illuminate\Support\Facades\Hash::make($validated['password']),
            'role' => $validated['role'] ?? 'CUSTOMER',
        ]);

        return response()->json(['status' => 'ok', 'data' => $user], 201);
    }

    /**
     * POST /api/login (Slide 12 Authentication)
     */
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = \App\Models\User::where('email', $credentials['email'])->first();
        if (! $user || ! \Illuminate\Support\Facades\Hash::check($credentials['password'], $user->password)) {
            return response()->json(['status' => 'error', 'message' => 'Invalid credentials.'], 401);
        }

        return response()->json([
            'status' => 'ok',
            'token' => base64_encode($user->id . '|' . $user->email . '|' . now()->timestamp),
            'user' => $user,
        ]);
    }

    /**
     * POST /api/logout & GET /api/user (Slide 12 Authentication)
     */
    public function logout(): JsonResponse
    {
        return response()->json(['status' => 'ok', 'message' => 'Logged out.']);
    }

    public function currentUser(Request $request): JsonResponse
    {
        return response()->json(['status' => 'ok', 'data' => $request->user()]);
    }

    /**
     * PUT/PATCH /api/shipments/{shipment} & DELETE /api/shipments/{shipment} (Slide 12 Shipments)
     */
    public function update(Request $request, Shipment $shipment): JsonResponse
    {
        $validated = $request->validate([
            'cargo_type' => ['sometimes', 'string', 'max:255'],
            'weight_kg' => ['sometimes', 'numeric', 'gt:0'],
            'volume_m3' => ['sometimes', 'numeric', 'gt:0'],
            'expected_arrival_at' => ['sometimes', 'date'],
            'estimated_arrival_at' => ['nullable', 'date'],
        ]);

        $shipment->update($validated);

        return response()->json(['status' => 'ok', 'data' => $shipment->fresh()]);
    }

    public function destroy(Shipment $shipment): JsonResponse
    {
        $shipment->update(['archived_at' => now()]);

        return response()->json(['status' => 'ok', 'message' => 'Shipment archived.']);
    }

    /**
     * GET /api/shipments/{shipment}/tracking (Slide 12 Tracking)
     */
    public function trackingIndex(Shipment $shipment): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'data' => $shipment->trackingEvents()->orderBy('occurred_at')->get(),
        ]);
    }

    /**
     * GET/POST /api/carriers & GET/POST /api/locations (Slide 12 Related Resources)
     */
    public function carriersIndex(): JsonResponse
    {
        return response()->json(['status' => 'ok', 'data' => Carrier::orderBy('name')->get()]);
    }

    public function carriersStore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:16', 'unique:carriers,code'],
            'service_type' => ['required', 'in:SEA,AIR,ROAD'],
            'contact_email' => ['nullable', 'email'],
            'is_active' => ['boolean'],
        ]);

        return response()->json(['status' => 'ok', 'data' => Carrier::create($validated)], 201);
    }

    public function locationsIndex(): JsonResponse
    {
        return response()->json(['status' => 'ok', 'data' => Location::orderBy('name')->get()]);
    }

    public function locationsStore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:16', 'unique:locations,code'],
            'city' => ['required', 'string', 'max:255'],
            'province' => ['nullable', 'string', 'max:255'],
            'country' => ['required', 'string', 'max:255'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);

        return response()->json(['status' => 'ok', 'data' => Location::create($validated)], 201);
    }

    /**
     * GET /api/shipments/{shipment}/exceptions & PATCH /api/exceptions/{exception} (Slide 12 Exceptions)
     */
    public function exceptionsIndex(Shipment $shipment): JsonResponse
    {
        return response()->json(['status' => 'ok', 'data' => $shipment->exceptions]);
    }

    public function updateException(Request $request, ShipmentException $exception): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:OPEN,INVESTIGATING,RESOLVED'],
        ]);

        if ($exception->status === ShipmentException::STATUS_RESOLVED) {
            return response()->json(['status' => 'error', 'message' => 'Resolved exceptions cannot be reopened.'], 422);
        }

        $exception->status = $validated['status'];
        if ($validated['status'] === ShipmentException::STATUS_RESOLVED) {
            $exception->resolved_at = now();
        }
        $exception->save();

        return response()->json(['status' => 'ok', 'data' => $exception]);
    }
}
