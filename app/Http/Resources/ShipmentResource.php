<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Shipment payload with the relations the interface needs. The delay
 * assessment is included so API consumers see the same deterministic
 * rule output the UI shows [S8].
 */
class ShipmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'shipment_number' => $this->shipment_number,
            'current_status' => $this->current_status->value,
            'delay' => $this->estimated_arrival_at === null ? null : $this->delayAssessment(),
            'cargo_type' => $this->cargo_type,
            'weight_kg' => $this->weight_kg,
            'volume_m3' => $this->volume_m3,
            'expected_departure_at' => $this->expected_departure_at?->toIso8601String(),
            'expected_arrival_at' => $this->expected_arrival_at?->toIso8601String(),
            'estimated_arrival_at' => $this->estimated_arrival_at?->toIso8601String(),
            'customer' => $this->whenLoaded('customer', fn () => ['id' => $this->customer->id, 'name' => $this->customer->name]),
            'carrier' => $this->whenLoaded('carrier', fn () => [
                'id' => $this->carrier->id,
                'name' => $this->carrier->name,
                'code' => $this->carrier->code,
                'service_type' => $this->carrier->service_type,
            ]),
            'origin' => $this->whenLoaded('origin', fn () => [
                'id' => $this->origin->id,
                'code' => $this->origin->code,
                'name' => $this->origin->name,
                'city' => $this->origin->city,
                'country' => $this->origin->country,
                'latitude' => (float) $this->origin->latitude,
                'longitude' => (float) $this->origin->longitude,
            ]),
            'destination' => $this->whenLoaded('destination', fn () => [
                'id' => $this->destination->id,
                'code' => $this->destination->code,
                'name' => $this->destination->name,
                'city' => $this->destination->city,
                'country' => $this->destination->country,
                'latitude' => (float) $this->destination->latitude,
                'longitude' => (float) $this->destination->longitude,
            ]),
            'items' => ShipmentItemResource::collection($this->whenLoaded('items')),
            'tracking_events' => TrackingEventResource::collection($this->whenLoaded('trackingEvents')),
            'exceptions' => ShipmentExceptionResource::collection($this->whenLoaded('exceptions')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
