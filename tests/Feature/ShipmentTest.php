<?php

namespace Tests\Feature;

use App\Models\Carrier;
use App\Models\Location;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShipmentTest extends TestCase
{
    use RefreshDatabase;

    private function validPayload(): array
    {
        return [
            'customer_id' => User::factory()->customer()->create()->id,
            'carrier_id' => Carrier::factory()->create()->id,
            'origin_location_id' => Location::factory()->create()->id,
            'destination_location_id' => Location::factory()->create()->id,
            'cargo_type' => 'Electronics',
            'weight_kg' => 1250.5,
            'volume_m3' => 18.4,
            'expected_departure_at' => now()->addDay()->format('Y-m-d\TH:i'),
            'expected_arrival_at' => now()->addDays(5)->format('Y-m-d\TH:i'),
        ];
    }

    public function test_operators_can_create_shipments(): void
    {
        $operator = User::factory()->operator()->create();

        $response = $this->actingAs($operator)->post(route('shipments.store'), $this->validPayload());

        $shipment = Shipment::query()->firstOrFail();
        $response->assertRedirect(route('shipments.show', $shipment));
        $this->assertSame('CREATED', $shipment->current_status->value);
        $this->assertStringStartsWith('CT', $shipment->shipment_number);
    }

    public function test_customers_cannot_create_shipments(): void
    {
        $customer = User::factory()->customer()->create();

        $response = $this->actingAs($customer)->post(route('shipments.store'), $this->validPayload());

        $response->assertForbidden();
        $this->assertSame(0, Shipment::count());
    }

    public function test_customers_only_see_their_own_shipments(): void
    {
        $own = Shipment::factory()->create(['shipment_number' => 'CTOWNXXXXXX']);
        $other = Shipment::factory()->create(['shipment_number' => 'CTOTHERXXX']);

        $response = $this->actingAs($own->customer)->get(route('shipments.index'));

        $response->assertOk();
        $response->assertSee('CTOWNXXXXXX');
        $response->assertDontSee('CTOTHERXXX');
        $this->assertSame($own->customer_id, $own->refresh()->customer_id);
        $this->assertNotSame($own->customer_id, $other->customer_id);
    }

    public function test_customers_cannot_open_other_customers_shipments(): void
    {
        $other = Shipment::factory()->create();

        $response = $this->actingAs(User::factory()->customer()->create())
            ->get(route('shipments.show', $other));

        $response->assertNotFound();
    }

    public function test_operators_can_open_any_shipment(): void
    {
        $operator = User::factory()->operator()->create();
        $shipment = Shipment::factory()->create();

        $response = $this->actingAs($operator)->get(route('shipments.show', $shipment));

        $response->assertOk();
        $response->assertSee($shipment->shipment_number);
    }

    public function test_same_origin_and_destination_is_rejected(): void
    {
        $operator = User::factory()->operator()->create();
        $payload = $this->validPayload();
        $payload['destination_location_id'] = $payload['origin_location_id'];

        $response = $this->actingAs($operator)->from(route('shipments.create'))
            ->post(route('shipments.store'), $payload);

        $response->assertRedirect(route('shipments.create'));
        $response->assertSessionHasErrors('destination_location_id');
    }

    public function test_arrival_must_come_after_departure(): void
    {
        $operator = User::factory()->operator()->create();
        $payload = $this->validPayload();
        $payload['expected_arrival_at'] = now()->subDay()->format('Y-m-d\TH:i');

        $response = $this->actingAs($operator)->post(route('shipments.store'), $payload);

        $response->assertSessionHasErrors('expected_arrival_at');
    }

    public function test_operators_can_update_shipments(): void
    {
        $operator = User::factory()->operator()->create();
        $shipment = Shipment::factory()->hasItems(2)->create();
        $payload = $this->validPayload();
        $payload['cargo_type'] = 'Medical supplies';
        $payload['items'] = [
            ['description' => 'Crated medical devices', 'quantity' => 6, 'weight_kg' => 320.5, 'volume_m3' => 9.5],
        ];

        $response = $this->actingAs($operator)->put(route('shipments.update', $shipment), $payload);

        $response->assertRedirect(route('shipments.show', $shipment->refresh()));
        $this->assertSame('Medical supplies', $shipment->cargo_type);
        $this->assertSame(1, $shipment->items()->count());
        $this->assertSame('Crated medical devices', $shipment->items()->first()->description);
    }

    public function test_only_admins_can_archive_shipments(): void
    {
        $operator = User::factory()->operator()->create();
        $shipment = Shipment::factory()->create();

        $this->actingAs($operator)->delete(route('shipments.destroy', $shipment))
            ->assertForbidden();
        $this->assertNull($shipment->fresh()->deleted_at);

        $this->actingAs(User::factory()->admin()->create())
            ->delete(route('shipments.destroy', $shipment->refresh()))
            ->assertRedirect(route('shipments.index'));
        $this->assertNotNull($shipment->fresh()->deleted_at);
    }

    public function test_the_tracking_list_shows_the_reference_data_shape(): void
    {
        $shipment = Shipment::factory()
            ->for(User::factory()->customer()->create(), 'customer')
            ->create();

        $response = $this->actingAs($shipment->customer)->get(route('shipments.index'));

        $response->assertOk();
        $response->assertSee($shipment->origin->code);
        $response->assertSee($shipment->destination->code);
        $response->assertSee($shipment->current_status->label());
    }
}
