<?php

namespace Tests\Feature;

use App\Models\Carrier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CarrierTest extends TestCase
{
    use RefreshDatabase;

    public function test_operators_cannot_manage_carriers(): void
    {
        $this->actingAs(User::factory()->operator()->create())
            ->get(route('carriers.index'))
            ->assertForbidden();

        $this->assertSame(0, Carrier::count());
    }

    public function test_admins_can_create_carriers(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->post(route('carriers.store'), [
            'name' => 'Meridian Air Cargo',
            'code' => 'MAC',
            'service_type' => 'AIR',
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('carriers.index'));
        $this->assertDatabaseHas('carriers', ['code' => 'MAC', 'is_active' => true]);
    }

    public function test_carrier_codes_must_be_unique(): void
    {
        Carrier::factory()->create(['code' => 'MAC']);
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->post(route('carriers.store'), [
            'name' => 'Duplicate Carrier',
            'code' => 'MAC',
        ]);

        $response->assertSessionHasErrors('code');
    }
}
