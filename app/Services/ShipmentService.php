<?php

namespace App\Services;

use App\Enums\ShipmentStatus;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Shipment persistence with its invariants in one place: the number is
 * generated when absent, every new shipment starts at CREATED, item
 * rows are written inside the same transaction as their parent, and
 * every important change lands in the audit log with its actor [S10].
 */
class ShipmentService
{
    public function __construct(private AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, array<string, mixed>>  $items
     */
    public function create(array $data, array $items = [], ?User $actor = null): Shipment
    {
        return DB::transaction(function () use ($data, $items, $actor) {
            $shipment = Shipment::create([
                ...$data,
                'shipment_number' => $data['shipment_number'] ?? $this->generateShipmentNumber(),
                'current_status' => ShipmentStatus::Created->value,
            ]);

            $this->syncItems($shipment, $items);
            $this->audit->record($actor, 'shipment.created', $shipment);

            return $shipment->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, array<string, mixed>>  $items
     */
    public function update(Shipment $shipment, array $data, ?array $items = null, ?User $actor = null): Shipment
    {
        return DB::transaction(function () use ($shipment, $data, $items, $actor) {
            $before = $shipment->getAttributes();

            $shipment->update($data);

            if ($items !== null) {
                $this->syncItems($shipment, $items);
            }

            $changes = collect($shipment->getChanges())
                ->except(['updated_at'])
                ->mapWithKeys(fn ($to, $key) => [$key => ['from' => $before[$key] ?? null, 'to' => $to]])
                ->all();

            if ($changes !== []) {
                $this->audit->record($actor, 'shipment.updated', $shipment, $changes);
            }

            return $shipment->refresh();
        });
    }

    /**
     * Soft delete keeps tracking history and audit rows coherent.
     */
    public function delete(Shipment $shipment, ?User $actor = null): void
    {
        $shipment->delete();

        $this->audit->record($actor, 'shipment.archived', $shipment);
    }

    /**
     * Replacing the item set wholesale keeps weight and volume totals
     * unambiguous; there is no partial item editing to reconcile.
     *
     * @param  array<int, array<string, mixed>>  $items
     */
    private function syncItems(Shipment $shipment, array $items): void
    {
        $shipment->items()->delete();

        foreach ($items as $item) {
            $shipment->items()->create($item);
        }
    }

    private function generateShipmentNumber(): string
    {
        do {
            $number = 'CT'.strtoupper(Str::random(10));
        } while (Shipment::withTrashed()->where('shipment_number', $number)->exists());

        return $number;
    }
}
