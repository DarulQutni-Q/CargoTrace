<?php

namespace App\Services;

use App\Models\Shipment;
use App\Models\TrackingEvent;
use App\Models\User;

/**
 * Slide 15 Architecture Guideline: TrackingService
 * Handles append-only historical tracking events, chronology validation, and coordinate integrity (Slide 7).
 */
class TrackingService
{
    public function __construct(
        protected ShipmentLifecycleService $lifecycleService
    ) {}

    public function recordCheckpoint(Shipment $shipment, array $payload, User $actor): TrackingEvent
    {
        return $this->lifecycleService->appendTrackingEvent($shipment, $payload, $actor);
    }
}
