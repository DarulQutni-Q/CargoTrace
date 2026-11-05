<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Carrier;
use App\Models\Location;
use App\Models\Notification;
use App\Models\Shipment;
use App\Models\ShipmentException;
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
    public function shipmentsIndex(Request $request): View
    {
        $query = Shipment::with(['origin', 'destination', 'carrier', 'trackingEvents', 'exceptions'])
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

        $shipments = $query->paginate(10)->withQueryString();

        // Map points for the current page: origin, destination, intermediate stops.
        $mapPoints = $shipments->getCollection()->map(function (Shipment $s) {
            return [
                'id' => $s->id,
                'shipment_number' => $s->shipment_number,
                'status' => $s->current_status,
                'cargo_type' => $s->cargo_type,
                'corridor' => $this->lifecycleService->buildOrderedCorridorWaypoints($s),
            ];
        })->values();

        return view('shipments.index', [
            'shipments' => $shipments,
            'carriers' => Carrier::all(),
            'locations' => Location::all(),
            'mapPoints' => $mapPoints,
            'filters' => $request->only(['q', 'status', 'carrier_id', 'origin_location_id', 'destination_location_id', 'date_from', 'date_to', 'delayed', 'sort']),
        ]);
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

        return view('shipments.show', [
            'shipment' => $shipment,
            'delay' => $delay,
            'corridor' => $corridor,
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
