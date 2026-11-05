<?php

namespace Tests\Feature;

use App\Models\Carrier;
use App\Models\Location;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShipmentApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_shipment_with_intermediate_transit_stop_and_returns_multi_leg_waypoints(): void
    {
        $customer = User::create([
            'name' => 'Citra Hapsari',
            'email' => 'customer@cargotrace.io',
            'password' => 'password123',
            'role' => User::ROLE_CUSTOMER,
        ]);

        $operator = User::create([
            'name' => 'Bimo Santoso',
            'email' => 'operator@cargotrace.io',
            'password' => 'password123',
            'role' => User::ROLE_OPERATOR,
        ]);

        $carrier = Carrier::create([
            'name' => 'Meridian Air Cargo',
            'code' => 'MAC',
            'service_type' => 'AIR',
            'is_active' => true,
        ]);

        $origin = Location::create([
            'name' => 'Tanjung Priok Port',
            'code' => 'JKT',
            'city' => 'Jakarta',
            'province' => 'DKI Jakarta',
            'country' => 'Indonesia',
            'latitude' => -6.1050,
            'longitude' => 106.8800,
        ]);

        $dest = Location::create([
            'name' => 'Port of Rotterdam',
            'code' => 'RTM',
            'city' => 'Rotterdam',
            'country' => 'Netherlands',
            'latitude' => 51.9496,
            'longitude' => 4.1425,
        ]);

        $response = $this->actingAs($operator)->postJson('/api/v1/shipments', [
            'customer_id' => $customer->id,
            'carrier_id' => $carrier->id,
            'origin_location_id' => $origin->id,
            'destination_location_id' => $dest->id,
            'cargo_type' => 'Electronics',
            'weight_kg' => 500,
            'volume_m3' => 8,
            'expected_departure_at' => '2026-11-06 08:00:00',
            'expected_arrival_at' => '2026-11-12 08:00:00',
            'transit_stop' => [
                'location_name' => 'Pelabuhan Lhokseumawe & KEK Arun (LSW)',
                'latitude' => 5.1801,
                'longitude' => 97.1507,
            ],
            'items' => [
                [
                    'description' => 'Industrial Sensors',
                    'quantity' => 10,
                    'weight_kg' => 500,
                    'volume_m3' => 8,
                ],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.current_status', Shipment::STATUS_CREATED);

        $shipmentId = $response->json('data.id');
        $mapResponse = $this->getJson("/api/v1/shipments/{$shipmentId}");
        $mapResponse->assertOk()
            ->assertJsonPath('data.corridor_waypoints.origin.code', 'JKT')
            ->assertJsonPath('data.corridor_waypoints.intermediate_stops.0.location_name', 'Pelabuhan Lhokseumawe & KEK Arun (LSW)')
            ->assertJsonPath('data.corridor_waypoints.destination.code', 'RTM');
    }
}
