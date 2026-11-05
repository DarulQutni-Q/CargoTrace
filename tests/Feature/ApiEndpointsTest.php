<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\CreatesCorridor;
use Tests\TestCase;

class ApiEndpointsTest extends TestCase
{
    use CreatesCorridor;
    use RefreshDatabase;

    private User $admin;
    private User $operator;
    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->makeUser(User::ROLE_ADMIN, 'admin@cargotrace.io', 'Aria Wibowo');
        $this->operator = $this->makeUser(User::ROLE_OPERATOR, 'operator@cargotrace.io', 'Bimo Santoso');
        $this->customer = $this->makeUser(User::ROLE_CUSTOMER, 'customer@cargotrace.io', 'Citra Hapsari');
    }

    public function test_health_endpoint_is_public(): void
    {
        $this->getJson('/api/v1/health')
            ->assertOk()
            ->assertJsonPath('status', 'ok');
    }

    public function test_carriers_crud_rules(): void
    {
        $this->withHeaders($this->authHeaders($this->operator))
            ->getJson('/api/v1/carriers')
            ->assertOk()
            ->assertJsonPath('data.total', 0);

        $created = $this->withHeaders($this->authHeaders($this->operator))
            ->postJson('/api/v1/carriers', [
                'name' => 'Meridian Air Cargo',
                'code' => 'MAC',
                'service_type' => 'AIR',
                'contact_email' => 'desk@meridianair.aero',
            ])
            ->assertCreated()
            ->assertJsonPath('data.code', 'MAC');

        $this->withHeaders($this->authHeaders($this->operator))
            ->postJson('/api/v1/carriers', [
                'name' => 'Duplicate',
                'code' => 'MAC',
                'service_type' => 'SEA',
            ])
            ->assertUnprocessable();

        $this->withHeaders($this->authHeaders($this->customer))
            ->postJson('/api/v1/carriers', [
                'name' => 'Nope',
                'code' => 'NOP',
                'service_type' => 'SEA',
            ])
            ->assertForbidden();

        $this->withHeaders($this->authHeaders($this->operator))
            ->getJson('/api/v1/carriers/' . $created->json('data.id'))
            ->assertOk()
            ->assertJsonPath('data.shipments_count', 0);
    }

    public function test_locations_crud_rules_and_catalog(): void
    {
        $this->withHeaders($this->authHeaders($this->operator))
            ->postJson('/api/v1/locations', [
                'name' => 'Tanjung Priok Port',
                'code' => 'JKT',
                'city' => 'Jakarta',
                'province' => 'DKI Jakarta',
                'country' => 'Indonesia',
                'latitude' => -6.1050,
                'longitude' => 106.8800,
            ])
            ->assertCreated();

        $this->withHeaders($this->authHeaders($this->customer))
            ->postJson('/api/v1/locations', [
                'name' => 'Nope',
                'code' => 'NOP',
                'city' => 'Nowhere',
                'country' => 'Indonesia',
                'latitude' => 0,
                'longitude' => 0,
            ])
            ->assertForbidden();

        $this->withHeaders($this->authHeaders($this->operator))
            ->getJson('/api/v1/locations?q=jakarta')
            ->assertOk()
            ->assertJsonPath('data.total', 1);

        $this->withHeaders($this->authHeaders($this->operator))
            ->getJson('/api/v1/locations/catalog')
            ->assertOk()
            ->assertJsonStructure(['provinces', 'active_locations', 'catalog_presets']);
    }

    public function test_shipments_map_endpoint_returns_corridor_waypoints(): void
    {
        $carrier = $this->makeCarrier();
        $origin = $this->makeLocation('JKT', 'Jakarta', -6.1050, 106.8800);
        $dest = $this->makeLocation('RTM', 'Rotterdam', 51.9496, 4.1425, 'Netherlands');
        $shipment = $this->makeShipment($this->customer, $carrier, $origin, $dest);

        $this->withHeaders($this->authHeaders($this->operator))
            ->getJson('/api/v1/shipments/map')
            ->assertOk()
            ->assertJsonPath('count', 1)
            ->assertJsonPath('data.0.shipment_number', $shipment->shipment_number)
            ->assertJsonPath('data.0.origin.code', 'JKT')
            ->assertJsonPath('data.0.destination.code', 'RTM');
    }

    public function test_shipments_index_filters_search_sort_and_pagination(): void
    {
        $carrier = $this->makeCarrier();
        $origin = $this->makeLocation('JKT', 'Jakarta', -6.1050, 106.8800);
        $dest = $this->makeLocation('RTM', 'Rotterdam', 51.9496, 4.1425, 'Netherlands');

        $this->makeShipment($this->customer, $carrier, $origin, $dest, [
            'cargo_type' => 'Electronics',
            'expected_arrival_at' => '2026-11-25 08:30:00',
            'estimated_arrival_at' => '2026-11-26 08:30:00',
        ]);
        $this->makeShipment($this->customer, $carrier, $origin, $dest, [
            'cargo_type' => 'Coffee beans',
            'expected_arrival_at' => '2026-11-25 08:30:00',
            'estimated_arrival_at' => null,
        ]);

        // Search by cargo type.
        $this->withHeaders($this->authHeaders($this->operator))
            ->getJson('/api/v1/shipments?q=coffee')
            ->assertOk()
            ->assertJsonPath('data.total', 1);

        // Delayed filter (estimated > expected).
        $this->withHeaders($this->authHeaders($this->operator))
            ->getJson('/api/v1/shipments?delayed=1')
            ->assertOk()
            ->assertJsonPath('data.total', 1);

        $this->withHeaders($this->authHeaders($this->operator))
            ->getJson('/api/v1/shipments?delayed=0')
            ->assertOk()
            ->assertJsonPath('data.total', 1);

        // Date range on expected departure.
        $this->withHeaders($this->authHeaders($this->operator))
            ->getJson('/api/v1/shipments?date_from=2026-11-07&date_to=2026-11-07')
            ->assertOk()
            ->assertJsonPath('data.total', 2);

        // Pagination.
        $this->withHeaders($this->authHeaders($this->operator))
            ->getJson('/api/v1/shipments?per_page=1')
            ->assertOk()
            ->assertJsonPath('data.total', 2)
            ->assertJsonPath('data.per_page', 1)
            ->assertJsonPath('data.last_page', 2);

        // Unknown shipment returns 404.
        $this->withHeaders($this->authHeaders($this->operator))
            ->getJson('/api/v1/shipments/99999')
            ->assertNotFound();
    }
}
