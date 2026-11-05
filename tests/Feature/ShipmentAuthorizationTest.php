<?php

namespace Tests\Feature;

use App\Models\Shipment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\CreatesCorridor;
use Tests\TestCase;

class ShipmentAuthorizationTest extends TestCase
{
    use CreatesCorridor;
    use RefreshDatabase;

    private User $admin;
    private User $operator;
    private User $customer;
    private User $otherCustomer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->makeUser(User::ROLE_ADMIN, 'admin@cargotrace.io', 'Aria Wibowo');
        $this->operator = $this->makeUser(User::ROLE_OPERATOR, 'operator@cargotrace.io', 'Bimo Santoso');
        $this->customer = $this->makeUser(User::ROLE_CUSTOMER, 'customer@cargotrace.io', 'Citra Hapsari');
        $this->otherCustomer = $this->makeUser(User::ROLE_CUSTOMER, 'dewi@cargotrace.io', 'Dewi Lestari');
    }

    private function corridor(): array
    {
        $carrier = $this->makeCarrier();
        $origin = $this->makeLocation('JKT', 'Jakarta', -6.1050, 106.8800);
        $dest = $this->makeLocation('RTM', 'Rotterdam', 51.9496, 4.1425, 'Netherlands');

        return [$carrier, $origin, $dest];
    }

    public function test_unauthenticated_requests_are_rejected_with_401(): void
    {
        $this->getJson('/api/v1/shipments')->assertUnauthorized();
    }

    public function test_customer_only_sees_own_shipments_in_list_and_detail(): void
    {
        [$carrier, $origin, $dest] = $this->corridor();
        $mine = $this->makeShipment($this->customer, $carrier, $origin, $dest);
        $theirs = $this->makeShipment($this->otherCustomer, $carrier, $origin, $dest);

        $list = $this->withHeaders($this->authHeaders($this->customer))
            ->getJson('/api/v1/shipments')
            ->assertOk();

        $numbers = collect($list->json('data.data'))->pluck('shipment_number')->all();
        $this->assertContains($mine->shipment_number, $numbers);
        $this->assertNotContains($theirs->shipment_number, $numbers);

        $this->withHeaders($this->authHeaders($this->customer))
            ->getJson('/api/v1/shipments/' . $theirs->id)
            ->assertForbidden();

        $this->withHeaders($this->authHeaders($this->customer))
            ->getJson('/api/v1/shipments/' . $mine->id)
            ->assertOk();
    }

    public function test_operator_and_admin_see_all_shipments(): void
    {
        [$carrier, $origin, $dest] = $this->corridor();
        $this->makeShipment($this->customer, $carrier, $origin, $dest);
        $this->makeShipment($this->otherCustomer, $carrier, $origin, $dest);

        $this->withHeaders($this->authHeaders($this->operator))
            ->getJson('/api/v1/shipments')
            ->assertOk()
            ->assertJsonPath('data.total', 2);
    }

    public function test_customer_booking_is_forced_under_their_own_account(): void
    {
        [$carrier, $origin, $dest] = $this->corridor();

        $response = $this->withHeaders($this->authHeaders($this->customer))
            ->postJson('/api/v1/shipments', [
                'customer_id' => $this->otherCustomer->id,
                'carrier_id' => $carrier->id,
                'origin_location_id' => $origin->id,
                'destination_location_id' => $dest->id,
                'cargo_type' => 'Electronics',
                'weight_kg' => 100,
                'volume_m3' => 2,
                'expected_arrival_at' => '2026-11-20 08:00:00',
                'items' => [['description' => 'Box', 'quantity' => 1, 'weight_kg' => 100, 'volume_m3' => 2]],
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.customer_id', $this->customer->id);
    }

    public function test_shipment_validation_rejects_missing_items_and_same_origin_destination(): void
    {
        [$carrier, $origin, $dest] = $this->corridor();

        $base = [
            'customer_id' => $this->customer->id,
            'carrier_id' => $carrier->id,
            'origin_location_id' => $origin->id,
            'destination_location_id' => $dest->id,
            'cargo_type' => 'Electronics',
            'weight_kg' => 100,
            'volume_m3' => 2,
            'expected_arrival_at' => '2026-11-20 08:00:00',
            'items' => [['description' => 'Box', 'quantity' => 1, 'weight_kg' => 100, 'volume_m3' => 2]],
        ];

        $this->withHeaders($this->authHeaders($this->operator))
            ->postJson('/api/v1/shipments', array_merge($base, ['items' => []]))
            ->assertUnprocessable();

        $this->withHeaders($this->authHeaders($this->operator))
            ->postJson('/api/v1/shipments', array_merge($base, ['destination_location_id' => $origin->id]))
            ->assertUnprocessable();
    }

    public function test_operator_advances_lifecycle_and_invalid_transitions_are_rejected(): void
    {
        [$carrier, $origin, $dest] = $this->corridor();
        $shipment = $this->makeShipment($this->customer, $carrier, $origin, $dest);

        // Skipped transition CREATED -> DELIVERED must fail.
        $this->withHeaders($this->authHeaders($this->operator))
            ->postJson('/api/v1/shipments/' . $shipment->id . '/tracking-events', [
                'event_type' => 'DELIVERED',
                'occurred_at' => '2026-11-10 08:00:00',
                'location_name' => 'Port of Rotterdam',
            ])
            ->assertUnprocessable();

        // Valid sequential transition CREATED -> BOOKED.
        $this->withHeaders($this->authHeaders($this->operator))
            ->postJson('/api/v1/shipments/' . $shipment->id . '/tracking-events', [
                'event_type' => 'BOOKED',
                'occurred_at' => '2026-11-07 09:00:00',
                'location_name' => 'Tanjung Priok Port',
            ])
            ->assertCreated()
            ->assertJsonPath('shipment_status', Shipment::STATUS_BOOKED);

        $this->assertSame(Shipment::STATUS_BOOKED, $shipment->fresh()->current_status);
    }

    public function test_out_of_order_tracking_timestamp_is_rejected(): void
    {
        [$carrier, $origin, $dest] = $this->corridor();
        $shipment = $this->makeShipment($this->customer, $carrier, $origin, $dest);

        $this->withHeaders($this->authHeaders($this->operator))
            ->postJson('/api/v1/shipments/' . $shipment->id . '/tracking-events', [
                'event_type' => 'BOOKED',
                'occurred_at' => '2026-11-10 09:00:00',
                'location_name' => 'Tanjung Priok Port',
            ])
            ->assertCreated();

        $this->withHeaders($this->authHeaders($this->operator))
            ->postJson('/api/v1/shipments/' . $shipment->id . '/tracking-events', [
                'event_type' => 'PICKED_UP',
                'occurred_at' => '2026-11-05 09:00:00',
                'location_name' => 'Tanjung Priok Port',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('message', fn ($message) => str_contains($message, 'Out-of-order'));
    }

    public function test_customer_cannot_append_tracking_events_or_update_or_delete(): void
    {
        [$carrier, $origin, $dest] = $this->corridor();
        $shipment = $this->makeShipment($this->customer, $carrier, $origin, $dest);

        $this->withHeaders($this->authHeaders($this->customer))
            ->postJson('/api/v1/shipments/' . $shipment->id . '/tracking-events', [
                'event_type' => 'BOOKED',
                'occurred_at' => '2026-11-07 09:00:00',
                'location_name' => 'Tanjung Priok Port',
            ])
            ->assertForbidden();

        $this->withHeaders($this->authHeaders($this->customer))
            ->patchJson('/api/v1/shipments/' . $shipment->id, ['cargo_type' => 'Toys'])
            ->assertForbidden();

        $this->withHeaders($this->authHeaders($this->customer))
            ->deleteJson('/api/v1/shipments/' . $shipment->id)
            ->assertForbidden();
    }

    public function test_operator_can_update_and_archive_but_not_change_status_directly(): void
    {
        [$carrier, $origin, $dest] = $this->corridor();
        $shipment = $this->makeShipment($this->customer, $carrier, $origin, $dest);

        $this->withHeaders($this->authHeaders($this->operator))
            ->patchJson('/api/v1/shipments/' . $shipment->id, [
                'cargo_type' => 'Toys',
                'current_status' => 'DELIVERED',
            ])
            ->assertOk()
            ->assertJsonPath('data.cargo_type', 'Toys')
            ->assertJsonPath('data.current_status', Shipment::STATUS_CREATED);

        $this->withHeaders($this->authHeaders($this->operator))
            ->deleteJson('/api/v1/shipments/' . $shipment->id)
            ->assertOk();

        $this->assertNotNull($shipment->fresh()->archived_at);

        $this->withHeaders($this->authHeaders($this->operator))
            ->getJson('/api/v1/shipments')
            ->assertOk()
            ->assertJsonPath('data.total', 0);
    }
}
