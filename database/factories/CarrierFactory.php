<?php

namespace Database\Factories;

use App\Models\Carrier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Carrier>
 */
class CarrierFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name.' Cargo',
            'code' => strtoupper(substr(fake()->unique()->lexify('???'), 0, 3)),
            'service_type' => fake()->randomElement(['AIR', 'SEA', 'ROAD']),
            'contact_email' => fake()->companyEmail(),
            'contact_phone' => fake()->phoneNumber(),
            'is_active' => true,
        ];
    }
}
