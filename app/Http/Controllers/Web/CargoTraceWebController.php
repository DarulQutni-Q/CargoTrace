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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CargoTraceWebController extends Controller
{
    public function __construct(
        private readonly ShipmentLifecycleService $lifecycleService,
        private readonly DelayClassifierService $delayClassifier,
        private readonly ExternalTelemetryService $telemetryService
    ) {}

    /**
     * Render the main CargoTrace application shell (White Mode default, #080B0C / #FFFFFF / #C6F432 palette).
     * Supports both the compiled Vite bundle and server-rendered Blade templates.
     */
    public function dashboard(Request $request): View
    {
        $scenario = DB::table('scenario_configs')->first();
        $shipments = Shipment::with(['origin', 'destination', 'carrier', 'exceptions'])->active()->get();

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

        return view('dashboard.index', [
            'scenario' => $scenario,
            'metrics' => $metrics,
            'shipments' => $shipments,
            'carriers' => Carrier::all(),
        ]);
    }

    /**
     * Render the Shipments split-map & tracking feed view.
     */
    public function shipmentsIndex(Request $request): View
    {
        $shipments = Shipment::with(['origin', 'destination', 'carrier', 'trackingEvents', 'exceptions'])
            ->active()
            ->orderByDesc('created_at')
            ->get();

        return view('shipments.index', [
            'shipments' => $shipments,
            'carriers' => Carrier::all(),
            'locations' => Location::all(),
        ]);
    }

    /**
     * Render the Shipment Dossier, Multi-Leg Route Map (Origin -> Transit Stops -> Destination),
     * Append-Only Timeline, Cargo Manifest, Live Weather, and Printable Bill of Lading (e-B/L).
     */
    public function shipmentShow(Shipment $shipment): View
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
        $allowedNext = $this->lifecycleService->allowedNextStatuses($shipment->current_status);

        return view('shipments.show', [
            'shipment' => $shipment,
            'delay' => $delay,
            'corridor' => $corridor,
            'allowedNext' => $allowedNext,
            'provinces' => IndonesianPortCatalog::PROVINCES,
            'portPresets' => IndonesianPortCatalog::presets(),
        ]);
    }

    /**
     * Render the Printable Official Electronic Bill of Lading (e-B/L) view.
     */
    public function billOfLading(Shipment $shipment): View
    {
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

    /**
     * Render the Carriers Directory view.
     */
    public function carriersIndex(): View
    {
        return view('carriers.index', [
            'carriers' => Carrier::withCount('shipments')->orderBy('name')->get(),
        ]);
    }

    /**
     * Render the Immutable Audit Trail view.
     */
    public function auditIndex(): View
    {
        return view('audit.index', [
            'logs' => AuditLog::with('actor')->orderByDesc('created_at')->limit(100)->get(),
        ]);
    }
}
