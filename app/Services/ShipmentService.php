<?php

namespace App\Services;

use App\Enums\ShipmentStatus;
use App\Models\Shipment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Shipment persistence with its invariants in one place: the number is
 * generated when absent, every new shipment starts at CREATED, and item
 * rows are written inside the same transaction as their parent.
 */
class ShipmentService
{
    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, array<string, mixed>>  $items
     */
    public function create(array $data, array $items = []): Shipment
    {
        return DB::transaction(function () use ($data, $items) {
            $shipment = Shipment::create([
                ...$data,
                'shipment_number' => $data['shipment_number'] ?? $this->generateShipmentNumber(),
                'current_status' => ShipmentStatus::Created->value,
            ]);

            $this->syncItems($shipment, $items);

            return $shipment->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, array<string, mixed>>  $items
     */
    public function update(Shipment $shipment, array $data, ?array $items = null): Shipment
    {
        DB::transaction(function () use ($shipment, $data, $items) {
            $shipment->update($data);

            if ($items !== null) {
                $this->syncItems($shipment, $items);
            }
        });

        return $shipment->refresh();
    }

    /**
     * Soft delete keeps tracking history and audit rows coherent.
     */
    public function delete(Shipment $shipment): void
    {
        $shipment->delete();
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
