<?php

namespace App\Http\Controllers;

use App\Enums\ShipmentStatus;
use App\Models\Shipment;
use App\Models\ShipmentException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * The operations dashboard. Every number is a live database query [S10]
 * scoped to what the signed-in role may see [S5] — customers see their
 * own totals, staff see the whole operation.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $scope = Shipment::query()->visibleTo($request->user());

        $total = (clone $scope)->count();
        $inTransit = (clone $scope)->where('current_status', ShipmentStatus::InTransit->value)->count();
        $delivered = (clone $scope)->where('current_status', ShipmentStatus::Delivered->value)->count();
        $delayed = (clone $scope)
            ->whereNotNull('estimated_arrival_at')
            ->whereColumn('estimated_arrival_at', '>', 'expected_arrival_at')
            ->count();

        $exceptionScope = ShipmentException::query()
            ->whereHas('shipment', fn ($query) => $query->visibleTo($request->user()));
        $openExceptions = (clone $exceptionScope)->where('status', '!=', 'RESOLVED')->count();
        $totalExceptions = (clone $exceptionScope)->count();

        $byStatus = (clone $scope)
            ->selectRaw('current_status, count(*) as total')
            ->groupBy('current_status')
            ->pluck('total', 'current_status');

        $byCarrier = (clone $scope)
            ->join('carriers', 'carriers.id', '=', 'shipments.carrier_id')
            ->selectRaw('carriers.name as carrier, carriers.code as code, count(*) as total')
            ->groupBy('carriers.name', 'carriers.code')
            ->orderByDesc('total')
            ->limit(6)
            ->get();

        return view('dashboard', [
            'stats' => [
                'total' => $total,
                'in_transit' => $inTransit,
                'delivered' => $delivered,
                'delayed' => $delayed,
                'open_exceptions' => $openExceptions,
            ],
            'delayRate' => $total > 0 ? (int) round($delayed / $total * 100) : 0,
            'exceptionRate' => $total > 0 ? (int) round($totalExceptions / $total * 100) : 0,
            'byStatus' => [
                'labels' => collect(ShipmentStatus::cases())->map(fn (ShipmentStatus $status) => $status->label())->all(),
                'values' => collect(ShipmentStatus::cases())->map(fn (ShipmentStatus $status) => $byStatus->get($status->value, 0))->values()->all(),
            ],
            'byCarrier' => [
                'labels' => $byCarrier->pluck('carrier')->all(),
                'codes' => $byCarrier->pluck('code')->all(),
                'values' => $byCarrier->pluck('total')->all(),
            ],
        ]);
    }
}
