<?php

namespace Tests\Feature;

use App\Enums\ShipmentStatus;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_screen(): void
    {
        $this->get('/')->assertRedirect(route('login'));
    }

    public function test_the_dashboard_reports_real_database_numbers(): void
    {
        $operator = User::factory()->operator()->create();
        Shipment::factory()->status(ShipmentStatus::InTransit)->count(3)->create();
        Shipment::factory()->status(ShipmentStatus::Delivered)->create();
        Shipment::factory()->status(ShipmentStatus::InTransit)->delayedBy(30)->create();

        $response = $this->actingAs($operator)->get('/');

        $response->assertOk();
        $this->assertSame(5, $response->viewData('stats')['total']);
        $this->assertSame(4, $response->viewData('stats')['in_transit']);
        $this->assertSame(1, $response->viewData('stats')['delivered']);
        $this->assertSame(1, $response->viewData('stats')['delayed']);
    }

    public function test_customers_only_see_their_own_totals(): void
    {
        $customer = User::factory()->customer()->create();
        Shipment::factory()->for($customer, 'customer')->count(2)->create();
        Shipment::factory()->count(4)->create(); // other customers

        $response = $this->actingAs($customer)->get('/');

        $this->assertSame(2, $response->viewData('stats')['total']);
    }

    public function test_the_role_label_is_rendered_in_the_shell(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/')
            ->assertOk()
            ->assertSee('Administrator');
    }
}
