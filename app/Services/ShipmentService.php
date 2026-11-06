<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Shipment;
use App\Models\ShipmentItem;
use App\Models\TrackingEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Slide 15 Architecture Guideline: ShipmentService
 * Encapsulates shipment creation, manifest persistence, lifecycle transitions, and transactional audit logging.
 */
class ShipmentService
{
    public function __construct(
        protected ShipmentLifecycleService $lifecycleService,
        protected EtaService $etaService
    ) {}

    public function createShipment(array $validated, User $actor): Shipment
    {
        return DB::transaction(function () use ($validated, $actor) {
            $shipment = Shipment::create([
                'shipment_number' => $validated['shipment_number'],
                'customer_id' => $validated['customer_id'],
                'carrier_id' => $validated['carrier_id'],
                'origin_location_id' => $validated['origin_location_id'],
                'destination_location_id' => $validated['destination_location_id'],
                'current_status' => Shipment::STATUS_CREATED,
                'cargo_type' => $validated['cargo_type'],
                'weight_kg' => $validated['weight_kg'],
                'volume_m3' => $validated['volume_m3'],
                'expected_departure_at' => $validated['expected_departure_at'] ?? now(),
                'expected_arrival_at' => $validated['expected_arrival_at'],
                'estimated_arrival_at' => $validated['estimated_arrival_at'] ?? $validated['expected_arrival_at'],
            ]);

            foreach ($validated['items'] ?? [] as $item) {
                ShipmentItem::create([
                    'shipment_id' => $shipment->id,
                    'description' => $item['description'],
                    'quantity' => $item['quantity'] ?? 1,
                    'weight_kg' => $item['weight_kg'] ?? 0,
                    'volume_m3' => $item['volume_m3'] ?? 0,
                ]);
            }

            $origin = $shipment->origin;
            TrackingEvent::create([
                'shipment_id' => $shipment->id,
                'event_type' => Shipment::STATUS_CREATED,
                'occurred_at' => now(),
                'location_name' => $origin ? $origin->name : 'Origin Port',
                'latitude' => $origin?->latitude,
                'longitude' => $origin?->longitude,
                'description' => 'Shipment registered and manifest verified.',
                'created_by' => $actor->id,
            ]);

            AuditLog::create([
                'actor_id' => $actor->id,
                'action' => 'SHIPMENT_CREATED',
                'entity_type' => 'Shipment',
                'entity_id' => $shipment->id,
                'changes' => [
                    'shipment_number' => ['from' => null, 'to' => $shipment->shipment_number],
                    'status' => ['from' => null, 'to' => Shipment::STATUS_CREATED],
                ],
            ]);

            return $shipment->load(['origin', 'destination', 'carrier', 'customer', 'items']);
        });
    }
}
