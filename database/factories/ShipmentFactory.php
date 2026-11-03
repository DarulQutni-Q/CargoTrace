<?php

namespace Database\Factories;

use App\Enums\ShipmentStatus;
use App\Models\Carrier;
use App\Models\Location;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Shipment>
 */
class ShipmentFactory extends Factory
{
    public function definition(): array
    {
        $departure = fake()->dateTimeBetween('-12 days', '+4 days');

        return [
            'shipment_number' => strtoupper(fake()->unique()->bothify('CT########??')),
            'customer_id' => User::factory()->customer(),
            'carrier_id' => Carrier::factory(),
            'origin_location_id' => Location::factory(),
            'destination_location_id' => Location::factory(),
            'cargo_type' => fake()->randomElement(['Electronics', 'Garments', 'Machinery parts', 'Perishables', 'Automotive', 'Chemicals']),
            'weight_kg' => fake()->randomFloat(2, 200, 18000),
            'volume_m3' => fake()->randomFloat(2, 4, 70),
            'expected_departure_at' => $departure,
            'expected_arrival_at' => fake()->dateTimeBetween($departure, (clone $departure)->modify('+9 days')),
            'estimated_arrival_at' => null,
            'current_status' => ShipmentStatus::Created->value,
        ];
    }

    public function status(ShipmentStatus $status): static
    {
        return $this->state(fn (array $attributes) => [
            'current_status' => $status->value,
        ]);
    }

    /**
     * An estimate that lands after the expected arrival by the given
     * hours — the input the delay rule classifies.
     */
    public function delayedBy(int $hours): static
    {
        return $this->state(fn (array $attributes) => [
            'estimated_arrival_at' => (clone $attributes['expected_arrival_at'])->modify("+{$hours} hours"),
        ]);
    }

    public function onTimeEstimate(): static
    {
        return $this->state(fn (array $attributes) => [
            'estimated_arrival_at' => (clone $attributes['expected_arrival_at'])->modify('-3 hours'),
        ]);
    }
}
