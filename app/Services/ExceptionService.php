<?php

namespace App\Services;

use App\Enums\ExceptionStatus;
use App\Models\Shipment;
use App\Models\ShipmentException;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Exception lifecycle in one place: raising an exception also writes
 * the linked timeline event so disruptions show up inline [S7], and
 * resolution stamps the resolver and the resolution time server-side [S8].
 * Lifecycle changes raise audit entries and customer notifications [S10].
 */
class ExceptionService
{
    public function __construct(
        private NotificationService $notifications,
        private AuditLogger $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(Shipment $shipment, array $data, User $creator): ShipmentException
    {
        return DB::transaction(function () use ($shipment, $data, $creator) {
            $exception = $shipment->exceptions()->create([
                'type' => $data['type'],
                'severity' => $data['severity'],
                'status' => ExceptionStatus::Open->value,
                'description' => $data['description'] ?? null,
                'detected_at' => $data['detected_at'],
            ]);

            $lastEvent = $shipment->trackingEvents()->orderByDesc('occurred_at')->first();

            $shipment->trackingEvents()->create([
                'event_type' => 'EXCEPTION',
                'occurred_at' => $exception->detected_at,
                'location_name' => $data['location_name'] ?? ($lastEvent?->location_name ?? $shipment->origin->name),
                'description' => $exception->type->label().' raised ('.$exception->severity->value.
                    (! empty($data['description']) ? '): '.$data['description'] : ')'),
                'created_by' => $creator->id,
                'exception_id' => $exception->id,
            ]);

            $this->audit->record($creator, 'exception.created', $exception, [
                'type' => $exception->type->value,
                'severity' => $exception->severity->value,
            ]);

            $this->notifications->notifyCustomer(
                $shipment,
                'EXCEPTION_CREATED',
                $exception->type->label().' on '.$shipment->shipment_number,
                'Severity '.$exception->severity->value.(! empty($data['description']) ? ' — '.$data['description'] : ''),
            );

            return $exception;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(ShipmentException $exception, array $data, User $user): ShipmentException
    {
        return DB::transaction(function () use ($exception, $data, $user) {
            $newStatus = isset($data['status']) ? ExceptionStatus::from($data['status']) : null;
            $oldStatus = $exception->status;

            if ($newStatus !== null) {
                $this->assertLifecycleAllowed($oldStatus, $newStatus);
            }

            $exception->update([
                'severity' => $data['severity'] ?? $exception->severity->value,
                'status' => $newStatus?->value ?? $exception->status->value,
                'description' => array_key_exists('description', $data) ? $data['description'] : $exception->description,
                'resolved_at' => $newStatus === ExceptionStatus::Resolved ? now() : $exception->resolved_at,
                'resolved_by' => $newStatus === ExceptionStatus::Resolved ? $user->id : $exception->resolved_by,
            ]);

            if ($newStatus !== null && $newStatus !== $oldStatus) {
                $this->audit->record($user, 'exception.status_changed', $exception, [
                    'from' => $oldStatus->value,
                    'to' => $newStatus->value,
                ]);
            }

            if ($newStatus === ExceptionStatus::Resolved) {
                $this->notifications->notifyCustomer(
                    $exception->shipment,
                    'EXCEPTION_RESOLVED',
                    $exception->type->label().' resolved on '.$exception->shipment->shipment_number,
                    'Resolution took '.number_format($exception->detected_at->floatDiffInHours($exception->resolved_at), 1).'H.',
                );
            }

            return $exception->refresh();
        });
    }

    private function assertLifecycleAllowed(ExceptionStatus $current, ExceptionStatus $target): void
    {
        if (! in_array($target, $current->allowedNext(), true)) {
            throw ValidationException::withMessages([
                'status' => "An exception in {$current->value} can only move to: ".
                    (implode(', ', array_map(fn (ExceptionStatus $s) => $s->value, $current->allowedNext())) ?: 'nothing — it is resolved.'),
            ]);
        }
    }
}
