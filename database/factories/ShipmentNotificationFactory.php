<?php

namespace Database\Factories;

use App\Models\Shipment;
use App\Models\ShipmentNotification;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShipmentNotification>
 */
class ShipmentNotificationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->customer(),
            'shipment_id' => Shipment::factory(),
            'type' => fake()->randomElement(['STATUS_CHANGED', 'EXCEPTION_CREATED', 'EXCEPTION_RESOLVED']),
            'title' => fake()->sentence(),
            'body' => fake()->sentence(),
            'read_at' => null,
        ];
    }
}
