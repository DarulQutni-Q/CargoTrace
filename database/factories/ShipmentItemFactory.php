<?php

namespace Database\Factories;

use App\Models\Shipment;
use App\Models\ShipmentItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShipmentItem>
 */
class ShipmentItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'shipment_id' => Shipment::factory(),
            'description' => fake()->randomElement([
                'Pallet of consumer electronics', 'Cartons of apparel', 'Crated machine parts',
                'Reefer container produce', 'Drums of industrial lubricant', 'Flat-pack furniture',
            ]),
            'quantity' => fake()->numberBetween(1, 480),
            'weight_kg' => fake()->randomFloat(2, 50, 4000),
            'volume_m3' => fake()->randomFloat(2, 1, 30),
        ];
    }
}
