<?php

namespace Tests\Feature\Api;

use App\Enums\ShipmentStatus;
use App\Models\Carrier;
use App\Models\Location;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiShipmentTest extends TestCase
{
    use RefreshDatabase;

    private User $operator;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->operator = User::factory()->operator()->create();
        $this->token = $this->operator->createToken('api')->plainTextToken;
    }

    private function payload(array $overrides = []): array
    {
        return [
            'shipment_number' => 'CTAPI00001X',
            'customer_id' => User::factory()->customer()->create()->id,
            'carrier_id' => Carrier::factory()->create()->id,
            'origin_location_id' => Location::factory()->create()->id,
            'destination_location_id' => Location::factory()->create()->id,
            'cargo_type' => 'Electronics',
            'weight_kg' => 900.5,
            'volume_m3' => 14.2,
            'expected_departure_at' => now()->addDay()->toIso8601String(),
            'expected_arrival_at' => now()->addDays(5)->toIso8601String(),
            ...$overrides,
        ];
    }

    public function test_operators_create_shipments_and_get_the_resource_back(): void
    {
        $response = $this->withToken($this->token)->postJson('/api/shipments', $this->payload());

        $response->assertCreated();
        $response->assertJsonPath('data.current_status', 'CREATED');
        $response->assertJsonStructure(['data' => ['shipment_number', 'origin' => ['code'], 'destination' => ['code'], 'carrier' => ['code']]]);
    }

    public function test_validation_errors_use_the_422_envelope(): void
    {
        $response = $this->withToken($this->token)->postJson('/api/shipments', [
            'cargo_type' => '',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonStructure(['message', 'errors']);
        $response->assertJsonMissing(['trace']);
    }

    public function test_duplicate_shipment_numbers_are_rejected(): void
    {
        Shipment::factory()->create(['shipment_number' => 'CTAPI00001X']);

        $this->withToken($this->token)->postJson('/api/shipments', $this->payload())
            ->assertJsonValidationErrors('shipment_number');
    }

    public function test_customers_only_receive_their_own_shipments(): void
    {
        $own = Shipment::factory()->status(ShipmentStatus::InTransit)->create(['shipment_number' => 'CTOWNAPI001']);
        $other = Shipment::factory()->status(ShipmentStatus::InTransit)->create(['shipment_number' => 'CTOTHAPI002']);

        $customerToken = $own->customer->createToken('api')->plainTextToken;

        $list = $this->withToken($customerToken)->getJson('/api/shipments');
        $list->assertOk();
        $this->assertSame(['CTOWNAPI001'], collect($list->json('data'))->pluck('shipment_number')->all());

        $this->withToken($customerToken)->getJson("/api/shipments/{$other->id}")
            ->assertNotFound();
    }

    public function test_the_list_supports_status_and_delay_filters(): void
    {
        Shipment::factory()->status(ShipmentStatus::InTransit)->delayedBy(30)->create(['shipment_number' => 'CTFILT00001']);
        Shipment::factory()->status(ShipmentStatus::Delivered)->onTimeEstimate()->create(['shipment_number' => 'CTFILT00002']);

        $delayed = $this->withToken($this->token)->getJson('/api/shipments?delayed=1');
        $this->assertSame(['CTFILT00001'], collect($delayed->json('data'))->pluck('shipment_number')->all());

        $inTransit = $this->withToken($this->token)->getJson('/api/shipments?status=IN_TRANSIT');
        $this->assertSame(['CTFILT00001'], collect($inTransit->json('data'))->pluck('shipment_number')->all());
    }

    public function test_tracking_events_flow_through_the_api(): void
    {
        $shipment = Shipment::factory()->status(ShipmentStatus::Created)->create();

        $response = $this->withToken($this->token)->postJson("/api/shipments/{$shipment->id}/tracking", [
            'event_type' => ShipmentStatus::Booked->value,
            'occurred_at' => now()->toIso8601String(),
            'location_name' => 'Jakarta origin warehouse',
        ]);

        $response->assertCreated();
        $this->assertSame(ShipmentStatus::Booked->value, $shipment->refresh()->current_status->value);

        $index = $this->withToken($this->token)->getJson("/api/shipments/{$shipment->id}/tracking");
        $index->assertOk();
        $this->assertCount(1, $index->json('data'));
    }

    public function test_exceptions_can_be_raised_and_resolved_through_the_api(): void
    {
        $shipment = Shipment::factory()->status(ShipmentStatus::InTransit)->create();

        $created = $this->withToken($this->token)->postJson("/api/shipments/{$shipment->id}/exceptions", [
            'type' => 'PORT_CONGESTION',
            'severity' => 'MEDIUM',
            'detected_at' => now()->toIso8601String(),
        ]);

        $created->assertCreated();
        $exceptionId = $created->json('data.id');
        $this->assertSame('OPEN', $created->json('data.status'));

        $resolved = $this->withToken($this->token)->patchJson("/api/exceptions/{$exceptionId}", [
            'status' => 'RESOLVED',
        ]);

        $resolved->assertOk();
        $this->assertSame('RESOLVED', $resolved->json('data.status'));
        $this->assertNotNull($resolved->json('data.resolved_at'));
    }

    public function test_carriers_endpoints_are_staff_only(): void
    {
        $this->withToken($this->token)->getJson('/api/carriers')->assertOk();

        $customer = User::factory()->customer()->create();
        $customerToken = $customer->createToken('api')->plainTextToken;

        $this->app['auth']->forgetGuards(); // fresh guard for the new identity
        $this->withToken($customerToken)->getJson('/api/carriers')->assertForbidden();

        $this->withToken($this->token)->postJson('/api/carriers', [
            'name' => 'Fictional Freight', 'code' => 'FFX',
        ])->assertForbidden(); // operator cannot write, admin can
    }

    public function test_admins_create_carriers_through_the_api(): void
    {
        $admin = User::factory()->admin()->create();
        $adminToken = $admin->createToken('api')->plainTextToken;

        $this->withToken($adminToken)->postJson('/api/carriers', [
            'name' => 'Meridian Air Cargo', 'code' => 'MAC', 'service_type' => 'AIR',
        ])->assertCreated();

        $this->assertDatabaseHas('carriers', ['code' => 'MAC']);
    }
}
