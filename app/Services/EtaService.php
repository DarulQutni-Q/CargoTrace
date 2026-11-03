<?php

namespace App\Services;

use App\Models\Shipment;

/**
 * The deterministic delay rule from the brief: compare estimated arrival
 * against expected arrival and classify the gap with configurable
 * thresholds. Pure and testable — no ML, no surprises [S8].
 */
class EtaService
{
    /**
     * @return array{state: string, delay_hours: float, threshold_low: int, threshold_high: int}
     */
    public function assess(Shipment $shipment): array
    {
        $thresholds = config('cargotrace.delay');
        $thresholdLow = $thresholds['low_threshold_hours'];
        $thresholdHigh = $thresholds['high_threshold_hours'];

        if ($shipment->estimated_arrival_at === null) {
            return ['state' => 'pending', 'delay_hours' => 0.0, 'threshold_low' => $thresholdLow, 'threshold_high' => $thresholdHigh];
        }

        $delayHours = max(0.0, $shipment->expected_arrival_at->floatDiffInHours($shipment->estimated_arrival_at, false));

        $state = match (true) {
            $delayHours <= 0.0 => 'on_time',
            $delayHours <= $thresholdLow => 'low',
            $delayHours <= $thresholdHigh => 'medium',
            default => 'high',
        };

        return ['state' => $state, 'delay_hours' => round($delayHours, 2), 'threshold_low' => $thresholdLow, 'threshold_high' => $thresholdHigh];
    }
}
