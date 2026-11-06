<?php

namespace App\Services;

use App\Models\Shipment;

/**
 * Slide 15 Architecture Guideline: EtaService
 * Deterministic ETA comparison and configurable group delay threshold classification (Slide 8 & Slide 23).
 */
class EtaService
{
    public function __construct(
        protected DelayClassifierService $delayClassifier
    ) {}

    public function evaluateShipmentDelay(Shipment $shipment, ?int $lowHours = null, ?int $highHours = null): array
    {
        return $this->delayClassifier->assess(
            $shipment->expected_arrival_at?->toIso8601String(),
            $shipment->estimated_arrival_at?->toIso8601String(),
            $lowHours,
            $highHours
        );
    }
}
