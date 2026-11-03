<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed one account per role so every permission set can be tried
     * immediately. Rich demo data (shipments, carriers, locations) is
     * seeded by its own seeder once those entities exist.
     */
    public function run(): void
    {
        User::factory()->create([
            'name' => 'Aria Wibowo',
            'email' => 'admin@cargotrace.test',
            'role' => UserRole::Admin,
        ]);

        User::factory()->create([
            'name' => 'Bimo Santoso',
            'email' => 'operator@cargotrace.test',
            'role' => UserRole::Operator,
        ]);

        User::factory()->create([
            'name' => 'Citra Hapsari',
            'email' => 'customer@cargotrace.test',
            'role' => UserRole::Customer,
        ]);

        $this->call(OpsSeeder::class);
    }
}
