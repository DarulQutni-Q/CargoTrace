<?php

namespace Tests\Feature;

use App\Models\ShipmentException;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\CreatesCorridor;
use Tests\TestCase;

class TrackingAndExceptionTest extends TestCase
{
    use CreatesCorridor;
    use RefreshDatabase;

    private User $operator;
    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->operator = $this->makeUser(User::ROLE_OPERATOR, 'operator@cargotrace.io', 'Bimo Santoso');
        $this->customer = $this->makeUser(User::ROLE_CUSTOMER, 'customer@cargotrace.io', 'Citra Hapsari');
    }

    private function shipmentWithEvents(): array
    {
        $carrier = $this->makeCarrier();
        $origin = $this->makeLocation('JKT', 'Jakarta', -6.1050, 106.8800);
        $dest = $this->makeLocation('RTM', 'Rotterdam', 51.9496, 4.1425, 'Netherlands');
        $shipment = $this->makeShipment($this->customer, $carrier, $origin, $dest);

        return [$shipment, $carrier, $origin, $dest];
    }

    public function test_tracking_timeline_is_append_only_and_paginated(): void
    {
        [$shipment] = $this->shipmentWithEvents();

        $this->withHeaders($this->authHeaders($this->operator))
            ->postJson('/api/v1/shipments/' . $shipment->id . '/tracking-events', [
                'event_type' => 'BOOKED',
                'occurred_at' => '2026-11-07 09:00:00',
                'location_name' => 'Tanjung Priok Port',
            ])
            ->assertCreated();

        $this->withHeaders($this->authHeaders($this->operator))
            ->postJson('/api/v1/shipments/' . $shipment->id . '/tracking-events', [
                'event_type' => 'CHECKPOINT',
                'occurred_at' => '2026-11-08 09:00:00',
                'location_name' => 'Singapore Changi Air Cargo',
            ])
            ->assertCreated();

        $this->withHeaders($this->authHeaders($this->operator))
            ->getJson('/api/v1/shipments/' . $shipment->id . '/tracking?per_page=1')
            ->assertOk()
            ->assertJsonPath('data.total', 2)
            ->assertJsonPath('data.per_page', 1);
    }

    public function test_raising_exception_writes_timeline_event_and_notifies_customer(): void
    {
        [$shipment] = $this->shipmentWithEvents();

        $response = $this->withHeaders($this->authHeaders($this->operator))
            ->postJson('/api/v1/shipments/' . $shipment->id . '/exceptions', [
                'type' => 'CUSTOMS_HOLD',
                'severity' => 'HIGH',
                'status' => 'OPEN',
                'detected_at' => '2026-11-08 12:00:00',
                'description' => 'Container selected for physical inspection.',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.type', 'CUSTOMS_HOLD')
            ->assertJsonPath('data.status', ShipmentException::STATUS_OPEN);

        $this->assertDatabaseHas('tracking_events', [
            'shipment_id' => $shipment->id,
            'event_type' => 'EXCEPTION',
        ]);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->customer->id,
            'shipment_id' => $shipment->id,
            'type' => 'EXCEPTION_RAISED',
        ]);
    }

    public function test_exception_type_must_match_the_brief_mandated_list(): void
    {
        [$shipment] = $this->shipmentWithEvents();

        $this->withHeaders($this->authHeaders($this->operator))
            ->postJson('/api/v1/shipments/' . $shipment->id . '/exceptions', [
                'type' => 'CARRIER_DELAY',
                'severity' => 'HIGH',
                'detected_at' => '2026-11-08 12:00:00',
            ])
            ->assertUnprocessable();

        $this->withHeaders($this->authHeaders($this->operator))
            ->postJson('/api/v1/shipments/' . $shipment->id . '/exceptions', [
                'type' => 'DOCUMENTATION_ISSUE',
                'severity' => 'MEDIUM',
                'detected_at' => '2026-11-08 12:00:00',
            ])
            ->assertCreated();
    }

    public function test_exception_lifecycle_transitions_are_validated_and_stamped(): void
    {
        [$shipment] = $this->shipmentWithEvents();

        $id = $this->withHeaders($this->authHeaders($this->operator))
            ->postJson('/api/v1/shipments/' . $shipment->id . '/exceptions', [
                'type' => 'WEATHER_DELAY',
                'severity' => 'MEDIUM',
                'detected_at' => '2026-11-08 12:00:00',
            ])
            ->assertCreated()
            ->json('data.id');

        $this->withHeaders($this->authHeaders($this->operator))
            ->patchJson('/api/v1/exceptions/' . $id, ['status' => 'INVESTIGATING'])
            ->assertOk()
            ->assertJsonPath('data.status', ShipmentException::STATUS_INVESTIGATING);

        $this->withHeaders($this->authHeaders($this->operator))
            ->patchJson('/api/v1/exceptions/' . $id, ['status' => 'RESOLVED'])
            ->assertOk()
            ->assertJsonPath('data.status', ShipmentException::STATUS_RESOLVED)
            ->assertJsonStructure(['data' => ['resolved_at']]);

        // Resolved is terminal.
        $this->withHeaders($this->authHeaders($this->operator))
            ->patchJson('/api/v1/exceptions/' . $id, ['status' => 'OPEN'])
            ->assertUnprocessable();
    }

    public function test_customer_cannot_raise_or_resolve_exceptions(): void
    {
        [$shipment] = $this->shipmentWithEvents();

        $id = $this->withHeaders($this->authHeaders($this->operator))
            ->postJson('/api/v1/shipments/' . $shipment->id . '/exceptions', [
                'type' => 'DAMAGED',
                'severity' => 'LOW',
                'detected_at' => '2026-11-08 12:00:00',
            ])
            ->assertCreated()
            ->json('data.id');

        $this->withHeaders($this->authHeaders($this->customer))
            ->postJson('/api/v1/shipments/' . $shipment->id . '/exceptions', [
                'type' => 'DAMAGED',
                'severity' => 'LOW',
                'detected_at' => '2026-11-08 12:00:00',
            ])
            ->assertForbidden();

        $this->withHeaders($this->authHeaders($this->customer))
            ->patchJson('/api/v1/exceptions/' . $id, ['status' => 'RESOLVED'])
            ->assertForbidden();
    }
}
