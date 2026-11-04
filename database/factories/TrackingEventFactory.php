<?php

namespace Database\Factories;

use App\Enums\ShipmentStatus;
use App\Models\Shipment;
use App\Models\TrackingEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TrackingEvent>
 */
class TrackingEventFactory extends Factory
{
    public function definition(): array
    {
        return [
            'shipment_id' => Shipment::factory(),
            'event_type' => ShipmentStatus::InTransit->value,
            'occurred_at' => fake()->dateTimeBetween('-6 days', 'now'),
            'location_name' => fake()->city(),
            'latitude' => fake()->latitude(),
            'longitude' => fake()->longitude(),
            'description' => fake()->randomElement([
                'Departed origin facility', 'Arrived at transit hub', 'Customs cleared',
                'Loaded onto vessel', 'Out for final delivery',
            ]),
            'created_by' => User::factory()->operator(),
        ];
    }
}
