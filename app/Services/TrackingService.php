<?php

namespace App\Services;

use App\Enums\ShipmentStatus;
use App\Models\Shipment;
use App\Models\TrackingEvent;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The single door through which tracking events enter the system.
 * It enforces the two business rules the brief is strict about:
 * status transitions validated server-side against one map [S6], and
 * an append-only, chronologically ordered history [S7].
 */
class TrackingService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function addEvent(Shipment $shipment, array $data, User $creator): TrackingEvent
    {
        return DB::transaction(function () use ($shipment, $data, $creator) {
            $status = ShipmentStatus::tryFrom((string) $data['event_type']);
            $occurredAt = Carbon::parse($data['occurred_at']);

            $lastEvent = $shipment->trackingEvents()
                ->orderByDesc('occurred_at')
                ->orderByDesc('id')
                ->first();

            if ($status !== null) {
                $this->assertTransitionAllowed($shipment->current_status, $status);
            }

            if ($lastEvent !== null && $occurredAt->lt($lastEvent->occurred_at)) {
                throw ValidationException::withMessages([
                    'occurred_at' => 'Events are historical — this timestamp is earlier than the last recorded event ('.
                        $lastEvent->occurred_at->format('M j, H:i').').',
                ]);
            }

            $event = $shipment->trackingEvents()->create([
                'event_type' => $data['event_type'],
                'occurred_at' => $occurredAt,
                'location_name' => $data['location_name'],
                'latitude' => $data['latitude'] ?? null,
                'longitude' => $data['longitude'] ?? null,
                'description' => $data['description'] ?? null,
                'created_by' => $creator->id,
            ]);

            if ($status !== null) {
                $shipment->update(['current_status' => $status->value]);
            }

            return $event;
        });
    }

    private function assertTransitionAllowed(ShipmentStatus $current, ShipmentStatus $target): void
    {
        if ($current === $target) {
            return; // re-logging the same status is informational, not a transition
        }

        $allowed = $current->allowedNext();

        if (! in_array($target, $allowed, true)) {
            throw ValidationException::withMessages([
                'event_type' => $allowed === []
                    ? "{$current->value} is terminal — the shipment cannot return to an earlier status without a special procedure."
                    : "A shipment in {$current->value} can only move to: ".implode(', ', array_map(fn (ShipmentStatus $s) => $s->value, $allowed)).'.',
            ]);
        }
    }
}
