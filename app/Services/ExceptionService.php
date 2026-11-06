<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Notification;
use App\Models\Shipment;
use App\Models\ShipmentException;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Slide 15 Architecture Guideline: ExceptionService
 * Manages operational exceptions (OPEN -> INVESTIGATING -> RESOLVED) with notification and audit logging (Slide 8).
 */
class ExceptionService
{
    public function raiseException(Shipment $shipment, array $validated, User $actor): ShipmentException
    {
        return DB::transaction(function () use ($shipment, $validated, $actor) {
            $exception = ShipmentException::create([
                'shipment_id' => $shipment->id,
                'type' => $validated['type'],
                'severity' => $validated['severity'] ?? 'MEDIUM',
                'status' => ShipmentException::STATUS_OPEN,
                'detected_at' => $validated['detected_at'] ?? now(),
                'description' => $validated['description'],
            ]);

            Notification::create([
                'user_id' => $shipment->customer_id,
                'shipment_id' => $shipment->id,
                'type' => 'EXCEPTION_RAISED',
                'title' => "Exception {$exception->type} on {$shipment->shipment_number}",
                'body' => $exception->description ?? 'Operational disruption detected.',
            ]);

            AuditLog::create([
                'actor_id' => $actor->id,
                'action' => 'EXCEPTION_RAISED',
                'entity_type' => 'ShipmentException',
                'entity_id' => $exception->id,
                'changes' => [
                    'type' => ['from' => null, 'to' => $exception->type],
                    'status' => ['from' => null, 'to' => ShipmentException::STATUS_OPEN],
                ],
            ]);

            return $exception;
        });
    }

    public function transitionStatus(ShipmentException $exception, string $nextStatus, User $actor): ShipmentException
    {
        if ($exception->status === ShipmentException::STATUS_RESOLVED) {
            throw new InvalidArgumentException('Resolved exceptions cannot be reopened.');
        }

        return DB::transaction(function () use ($exception, $nextStatus, $actor) {
            $prev = $exception->status;
            $exception->status = $nextStatus;
            if ($nextStatus === ShipmentException::STATUS_RESOLVED) {
                $exception->resolved_at = now();
            }
            $exception->save();

            AuditLog::create([
                'actor_id' => $actor->id,
                'action' => 'EXCEPTION_STATUS_UPDATED',
                'entity_type' => 'ShipmentException',
                'entity_id' => $exception->id,
                'changes' => [
                    'status' => ['from' => $prev, 'to' => $nextStatus],
                ],
            ]);

            return $exception;
        });
    }
}
