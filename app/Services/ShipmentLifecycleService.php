<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Notification;
use App\Models\Shipment;
use App\Models\ShipmentException;
use App\Models\TrackingEvent;
use App\Models\User;
use Carbon\Carbon;
use InvalidArgumentException;

class ShipmentLifecycleService
{
    /**
     * Allowed forward-only lifecycle transitions (Slide 6).
     * DELIVERED is strictly terminal.
     */
    private const ALLOWED_TRANSITIONS = [
        Shipment::STATUS_CREATED => [Shipment::STATUS_BOOKED],
        Shipment::STATUS_BOOKED => [Shipment::STATUS_PICKED_UP],
        Shipment::STATUS_PICKED_UP => [Shipment::STATUS_IN_TRANSIT],
        Shipment::STATUS_IN_TRANSIT => [Shipment::STATUS_DELIVERED],
        Shipment::STATUS_DELIVERED => [],
    ];

    private const ALLOWED_EXCEPTION_TRANSITIONS = [
        ShipmentException::STATUS_OPEN => [
            ShipmentException::STATUS_INVESTIGATING,
            ShipmentException::STATUS_RESOLVED,
        ],
        ShipmentException::STATUS_INVESTIGATING => [
            ShipmentException::STATUS_RESOLVED,
        ],
        ShipmentException::STATUS_RESOLVED => [],
    ];

    public function allowedNextStatuses(string $currentStatus): array
    {
        return self::ALLOWED_TRANSITIONS[$currentStatus] ?? [];
    }

    public function canTransitionStatus(string $from, string $to): bool
    {
        return in_array($to, $this->allowedNextStatuses($from), true);
    }

    public function allowedNextExceptionStatuses(string $currentStatus): array
    {
        return self::ALLOWED_EXCEPTION_TRANSITIONS[$currentStatus] ?? [];
    }

    public function canTransitionExceptionStatus(string $from, string $to): bool
    {
        return in_array($to, $this->allowedNextExceptionStatuses($from), true);
    }

    /**
     * Append a chronological tracking event (or mid-route transit checkpoint) and optionally transition status.
     */
    public function appendTrackingEvent(Shipment $shipment, array $data, ?User $actor = null): TrackingEvent
    {
        $eventType = strtoupper(trim($data['event_type'] ?? 'CHECKPOINT'));
        $occurredAt = Carbon::parse($data['occurred_at']);

        // 1. Validate chronology against the latest existing event
        $latestEvent = $shipment->trackingEvents()->orderByDesc('occurred_at')->first();
        if ($latestEvent && $occurredAt->lt($latestEvent->occurred_at)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Out-of-order timestamp rejected: event time (%s) cannot be earlier than latest checkpoint (%s).',
                    $occurredAt->toIso8601String(),
                    $latestEvent->occurred_at->toIso8601String()
                )
            );
        }

        // 2. If event_type is a lifecycle state transition, validate sequential state machine rules
        $lifecycleStates = array_keys(self::ALLOWED_TRANSITIONS);
        $oldStatus = $shipment->current_status;
        if (in_array($eventType, $lifecycleStates, true) && $eventType !== $oldStatus) {
            if (! $this->canTransitionStatus($oldStatus, $eventType)) {
                throw new InvalidArgumentException(
                    sprintf('Invalid lifecycle transition from %s to %s.', $oldStatus, $eventType)
                );
            }
            $shipment->current_status = $eventType;
        }

        if (! empty($data['estimated_arrival_at'])) {
            $shipment->estimated_arrival_at = Carbon::parse($data['estimated_arrival_at']);
        }

        $shipment->save();

        $event = TrackingEvent::create([
            'shipment_id' => $shipment->id,
            'event_type' => $eventType,
            'occurred_at' => $occurredAt,
            'location_name' => trim($data['location_name']),
            'latitude' => isset($data['latitude']) && $data['latitude'] !== '' ? (float) $data['latitude'] : null,
            'longitude' => isset($data['longitude']) && $data['longitude'] !== '' ? (float) $data['longitude'] : null,
            'description' => $data['description'] ?? null,
            'created_by' => $actor?->id,
        ]);

        if ($oldStatus !== $shipment->current_status) {
            AuditLog::create([
                'actor_id' => $actor?->id,
                'action' => 'shipment.status_changed',
                'entity_type' => 'Shipment',
                'entity_id' => $shipment->id,
                'changes' => [
                    'current_status' => ['from' => $oldStatus, 'to' => $shipment->current_status],
                ],
            ]);

            Notification::create([
                'user_id' => $shipment->customer_id,
                'shipment_id' => $shipment->id,
                'type' => 'STATUS_CHANGED',
                'title' => sprintf('%s advanced to %s', $shipment->shipment_number, $shipment->current_status),
                'body' => sprintf('Latest scan recorded at %s.', $event->location_name),
            ]);
        }

        return $event;
    }

    /**
     * Raise an operational exception on a shipment: create the exception,
     * append an EXCEPTION event on the timeline, optionally shift the
     * estimated arrival, and notify the customer. Shared by the API and
     * the web dossier form so both paths behave identically.
     */
    public function raiseException(Shipment $shipment, array $data, ?User $actor = null): ShipmentException
    {
        return \Illuminate\Support\Facades\DB::transaction(function () use ($shipment, $data, $actor) {
            $exc = ShipmentException::create([
                'shipment_id' => $shipment->id,
                'type' => $data['type'],
                'severity' => $data['severity'],
                'status' => ShipmentException::STATUS_OPEN,
                'detected_at' => Carbon::parse($data['detected_at']),
                'description' => $data['description'] ?? null,
            ]);

            if (! empty($data['estimated_arrival_at'])) {
                $shipment->update(['estimated_arrival_at' => Carbon::parse($data['estimated_arrival_at'])]);
            }

            TrackingEvent::create([
                'shipment_id' => $shipment->id,
                'event_type' => 'EXCEPTION',
                'occurred_at' => Carbon::parse($data['detected_at']),
                'location_name' => $data['location_name'] ?? $shipment->origin->name,
                'description' => sprintf('%s raised (%s): %s', $exc->type, $exc->severity, $exc->description ?? ''),
                'created_by' => $actor?->id,
            ]);

            Notification::create([
                'user_id' => $shipment->customer_id,
                'shipment_id' => $shipment->id,
                'type' => 'EXCEPTION_CREATED',
                'title' => sprintf('%s on %s', ShipmentException::typeLabel($exc->type), $shipment->shipment_number),
                'body' => sprintf('Severity %s — %s', $exc->severity, $exc->description ?? 'Operational exception raised on your corridor shipment.'),
            ]);

            if ($actor !== null && (int) $actor->id !== (int) $shipment->customer_id) {
                Notification::create([
                    'user_id' => $actor->id,
                    'shipment_id' => $shipment->id,
                    'type' => 'EXCEPTION_CREATED',
                    'title' => sprintf('%s on %s', ShipmentException::typeLabel($exc->type), $shipment->shipment_number),
                    'body' => sprintf('Severity %s — %s', $exc->severity, $exc->description ?? 'Operational exception raised on your corridor shipment.'),
                ]);
            }

            AuditLog::create([
                'actor_id' => $actor?->id,
                'action' => 'exception.created',
                'entity_type' => 'ShipmentException',
                'entity_id' => $exc->id,
                'changes' => [
                    'type' => ['from' => null, 'to' => $exc->type],
                    'severity' => ['from' => null, 'to' => $exc->severity],
                ],
            ]);

            return $exc;
        });
    }

    /**
     * Build ordered multi-leg corridor waypoints: Origin -> Intermediate Transit Stops -> Destination.
     * Prevents backtracking to Origin when lifecycle events at Origin are recorded after departure.
     */
    public function buildOrderedCorridorWaypoints(Shipment $shipment): array
    {
        $origin = $shipment->origin;
        $destination = $shipment->destination;

        $eventsChrono = $shipment->trackingEvents()
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->orderBy('occurred_at', 'asc')
            ->get();

        $intermediateStops = [];
        foreach ($eventsChrono as $ev) {
            $nearOrigin = abs($ev->latitude - $origin->latitude) < 0.02
                && abs($ev->longitude - $origin->longitude) < 0.02;
            $nearDest = abs($ev->latitude - $destination->latitude) < 0.02
                && abs($ev->longitude - $destination->longitude) < 0.02;

            if ($nearOrigin || $nearDest) {
                continue;
            }

            $duplicate = false;
            foreach ($intermediateStops as $existing) {
                if (
                    abs($existing['latitude'] - $ev->latitude) < 0.02
                    && abs($existing['longitude'] - $ev->longitude) < 0.02
                ) {
                    $duplicate = true;
                    break;
                }
            }

            if (! $duplicate) {
                $intermediateStops[] = [
                    'id' => $ev->id,
                    'location_name' => $ev->location_name,
                    'latitude' => (float) $ev->latitude,
                    'longitude' => (float) $ev->longitude,
                    'occurred_at' => $ev->occurred_at->toIso8601String(),
                    'event_type' => $ev->event_type,
                ];
            }
        }

        return [
            'origin' => [
                'code' => $origin->code,
                'location_name' => $origin->name,
                'city' => $origin->city,
                'latitude' => (float) $origin->latitude,
                'longitude' => (float) $origin->longitude,
            ],
            'intermediate_stops' => $intermediateStops,
            'destination' => [
                'code' => $destination->code,
                'location_name' => $destination->name,
                'city' => $destination->city,
                'latitude' => (float) $destination->latitude,
                'longitude' => (float) $destination->longitude,
            ],
        ];
    }
}
