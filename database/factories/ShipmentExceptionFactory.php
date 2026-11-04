<?php

namespace Database\Factories;

use App\Enums\ExceptionSeverity;
use App\Enums\ExceptionStatus;
use App\Enums\ExceptionType;
use App\Models\Shipment;
use App\Models\ShipmentException;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShipmentException>
 */
class ShipmentExceptionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'shipment_id' => Shipment::factory(),
            'type' => fake()->randomElement(ExceptionType::cases())->value,
            'severity' => fake()->randomElement(ExceptionSeverity::cases())->value,
            'status' => ExceptionStatus::Open->value,
            'description' => fake()->sentence(),
            'detected_at' => fake()->dateTimeBetween('-5 days', 'now'),
            'resolved_at' => null,
            'resolved_by' => null,
        ];
    }

    public function resolved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ExceptionStatus::Resolved->value,
            'resolved_at' => fake()->dateTimeBetween('-2 days', 'now'),
            'resolved_by' => User::factory()->operator(),
        ]);
    }

    public function investigating(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ExceptionStatus::Investigating->value,
        ]);
    }
}
