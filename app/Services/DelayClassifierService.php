<?php

namespace App\Services;

use Carbon\Carbon;

class DelayClassifierService
{
    /**
     * Deterministically classify shipment ETA delay against group scenario thresholds (Slide 8 & Slide 23).
     *
     * @return array{
     *   delayed: bool,
     *   delay_hours: float,
     *   state: 'pending'|'on_time'|'low'|'medium'|'high',
     *   threshold_low: int,
     *   threshold_high: int,
     *   reason: string
     * }
     */
    public function assess(
        string|\DateTimeInterface $expectedArrivalAt,
        string|\DateTimeInterface|null $estimatedArrivalAt,
        int $lowThresholdHours = 6,
        int $highThresholdHours = 24
    ): array {
        if ($estimatedArrivalAt === null || $estimatedArrivalAt === '') {
            return [
                'delayed' => false,
                'delay_hours' => 0.0,
                'state' => 'pending',
                'threshold_low' => $lowThresholdHours,
                'threshold_high' => $highThresholdHours,
                'reason' => 'Pending carrier telemetry — no estimated arrival timestamp recorded yet.',
            ];
        }

        $expected = Carbon::parse($expectedArrivalAt);
        $estimated = Carbon::parse($estimatedArrivalAt);

        $diffHours = round(($estimated->getTimestamp() - $expected->getTimestamp()) / 3600, 1);

        if ($diffHours <= 0) {
            return [
                'delayed' => false,
                'delay_hours' => 0.0,
                'state' => 'on_time',
                'threshold_low' => $lowThresholdHours,
                'threshold_high' => $highThresholdHours,
                'reason' => 'Estimated arrival matches or beats planned arrival schedule (0h delay).',
            ];
        }

        if ($diffHours <= $lowThresholdHours) {
            $state = 'low';
        } elseif ($diffHours <= $highThresholdHours) {
            $state = 'medium';
        } else {
            $state = 'high';
        }

        return [
            'delayed' => true,
            'delay_hours' => $diffHours,
            'state' => $state,
            'threshold_low' => $lowThresholdHours,
            'threshold_high' => $highThresholdHours,
            'reason' => sprintf(
                'ETA exceeds planned arrival by +%sh (%s severity band: Low ≤%dh, Med ≤%dh).',
                $diffHours,
                strtoupper($state),
                $lowThresholdHours,
                $highThresholdHours
            ),
        ];
    }
}
