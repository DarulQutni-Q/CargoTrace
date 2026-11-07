<?php

namespace Tests\Feature;

use App\Models\Carrier;
use App\Models\Location;
use App\Models\Notification;
use App\Models\Shipment;
use App\Models\ShipmentException;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Concerns\CreatesCorridor;
use Tests\TestCase;

/**
 * End-to-end coverage of the server-rendered web flows (Blade forms,
 * modals, role gates, validation errors). The API suite covers the same
 * domain logic through JSON; these tests prove the buttons and forms the
 * user actually clicks do what they promise.
 */
class WebFlowsTest extends TestCase
{
    use RefreshDatabase;
    use CreatesCorridor;

    protected User $admin;
    protected User $operator;
    protected User $customer;
    protected Carrier $carrier;
    protected Location $origin;
    protected Location $dest;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake();

        DB::table('scenario_configs')->insert([
            'id' => 1,
            'group_code' => 'DEFAULT',
            'route_corridor' => 'Southeast Asia → Europe',
            'low_threshold_hours' => 6,
            'high_threshold_hours' => 24,
            'primary_exception' => 'CUSTOMS_HOLD',
            'kpi_focus' => 'On-Time Delivery & Exception Rate',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->admin = $this->makeUser(User::ROLE_ADMIN, 'admin@cargotrace.io', 'Aria');
        $this->operator = $this->makeUser(User::ROLE_OPERATOR, 'operator@cargotrace.io', 'Bimo');
        $this->customer = $this->makeUser(User::ROLE_CUSTOMER, 'customer@cargotrace.io', 'Citra');
        $this->carrier = $this->makeCarrier('NFR');
        $this->origin = $this->makeLocation('SIN', 'Singapore', 1.2644, 103.9915, 'Singapore');
        $this->dest = $this->makeLocation('RTM', 'Rotterdam', 51.9496, 4.1425, 'Netherlands');
    }

    protected function validShipmentPayload(array $overrides = []): array
    {
        return array_merge([
            'customer_id' => $this->customer->id,
            'carrier_id' => $this->carrier->id,
            'origin_location_id' => $this->origin->id,
            'destination_location_id' => $this->dest->id,
            'cargo_type' => 'Electronics',
            'weight_kg' => 800,
            'volume_m3' => 12,
            'expected_departure_at' => '2026-11-07 08:30:00',
            'expected_arrival_at' => '2026-11-25 08:30:00',
            'items' => [
                ['description' => 'Palletized Electronics', 'quantity' => 12, 'weight_kg' => 800, 'volume_m3' => 12],
            ],
        ], $overrides);
    }

    public function test_web_create_shipment_as_operator_creates_records(): void
    {
        $response = $this->actingAs($this->operator)
            ->post('/shipments', $this->validShipmentPayload());

        $shipment = Shipment::first();
        $response->assertRedirect('/shipments/' . $shipment->id);
        $this->assertSame(Shipment::STATUS_CREATED, $shipment->current_status);
        $this->assertCount(1, $shipment->items);
        $this->assertSame(1, $shipment->trackingEvents()->count());
        $this->assertSame('CREATED', $shipment->trackingEvents()->first()->event_type);
        $this->assertDatabaseHas('audit_logs', ['action' => 'shipment.created', 'entity_id' => $shipment->id]);
        $this->assertDatabaseHas('notifications', ['shipment_id' => $shipment->id, 'type' => 'SHIPMENT_CREATED']);
    }

    public function test_web_create_shipment_rejects_invalid_payload(): void
    {
        $response = $this->actingAs($this->operator)
            ->post('/shipments', $this->validShipmentPayload([
                'destination_location_id' => $this->origin->id,
                'cargo_type' => '',
            ]));

        $response->assertSessionHasErrors(['destination_location_id', 'cargo_type']);
        $this->assertSame(0, Shipment::count());
    }

    public function test_web_create_as_customer_forces_own_customer_id(): void
    {
        $other = $this->makeUser(User::ROLE_CUSTOMER, 'other@example.com', 'Other');

        $this->actingAs($this->customer)
            ->post('/shipments', $this->validShipmentPayload(['customer_id' => $other->id]))
            ->assertRedirect();

        $this->assertSame($this->customer->id, Shipment::first()->customer_id);
    }

    public function test_web_advance_moves_status_and_appends_event(): void
    {
        $shipment = $this->makeShipment($this->customer, $this->carrier, $this->origin, $this->dest);

        $this->actingAs($this->operator)
            ->post('/shipments/' . $shipment->id . '/advance')
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertSame(Shipment::STATUS_BOOKED, $shipment->fresh()->current_status);
        $this->assertSame(1, $shipment->trackingEvents()->count());
        $this->assertSame('BOOKED', $shipment->trackingEvents()->first()->event_type);
    }

    public function test_web_advance_terminal_shipment_is_rejected(): void
    {
        $shipment = $this->makeShipment($this->customer, $this->carrier, $this->origin, $this->dest,
            ['current_status' => Shipment::STATUS_DELIVERED]);

        $this->actingAs($this->operator)
            ->post('/shipments/' . $shipment->id . '/advance')
            ->assertSessionHasErrors('status');

        $this->assertSame(Shipment::STATUS_DELIVERED, $shipment->fresh()->current_status);
    }

    public function test_web_customer_cannot_operate_or_archive(): void
    {
        $shipment = $this->makeShipment($this->customer, $this->carrier, $this->origin, $this->dest);

        $this->actingAs($this->customer)
            ->post('/shipments/' . $shipment->id . '/advance')
            ->assertForbidden();

        $this->actingAs($this->customer)
            ->post('/shipments/' . $shipment->id . '/archive')
            ->assertForbidden();
    }

    public function test_web_add_checkpoint_and_reject_bad_type(): void
    {
        $shipment = $this->makeShipment($this->customer, $this->carrier, $this->origin, $this->dest);

        $this->actingAs($this->operator)->post('/shipments/' . $shipment->id . '/tracking-events', [
            'event_type' => 'CHECKPOINT',
            'occurred_at' => '2026-11-08 10:00:00',
            'location_name' => 'Port of Singapore',
            'latitude' => 1.2644,
            'longitude' => 103.9915,
            'description' => 'Departed Singapore.',
        ])->assertRedirect()->assertSessionHas('status');

        $this->assertSame(1, $shipment->trackingEvents()->where('event_type', 'CHECKPOINT')->count());

        $this->actingAs($this->operator)->post('/shipments/' . $shipment->id . '/tracking-events', [
            'event_type' => 'TELEPORTED',
            'occurred_at' => '2026-11-08 11:00:00',
            'location_name' => 'Nowhere',
        ])->assertSessionHasErrors('event_type');
    }

    public function test_web_raise_exception_and_walk_status_chain(): void
    {
        $shipment = $this->makeShipment($this->customer, $this->carrier, $this->origin, $this->dest);

        $this->actingAs($this->operator)->post('/shipments/' . $shipment->id . '/exceptions', [
            'type' => 'CUSTOMS_HOLD',
            'severity' => 'HIGH',
            'detected_at' => '2026-11-08 09:00:00',
            'location_name' => 'Rotterdam',
            'description' => 'Selected for inspection.',
        ])->assertRedirect()->assertSessionHas('status');

        $exception = ShipmentException::first();
        $this->assertSame(ShipmentException::STATUS_OPEN, $exception->status);
        $this->assertDatabaseHas('notifications', ['shipment_id' => $shipment->id, 'type' => 'EXCEPTION_CREATED']);

        // OPEN may go straight to RESOLVED (fast resolution is legal);
        // RESOLVED is terminal and cannot go back.
        $this->actingAs($this->operator)
            ->patch('/exceptions/' . $exception->id, ['status' => 'RESOLVED'])
            ->assertRedirect();
        $this->assertSame(ShipmentException::STATUS_RESOLVED, $exception->fresh()->status);
        $this->assertNotNull($exception->fresh()->resolved_at);
        $this->assertDatabaseHas('notifications', ['shipment_id' => $shipment->id, 'type' => 'EXCEPTION_RESOLVED']);

        $this->actingAs($this->operator)
            ->patch('/exceptions/' . $exception->id, ['status' => 'OPEN'])
            ->assertSessionHasErrors('status');
        $this->assertSame(ShipmentException::STATUS_RESOLVED, $exception->fresh()->status);
    }

    public function test_web_edit_does_not_duplicate_unchanged_transit_stop(): void
    {
        $payload = $this->validShipmentPayload([
            'transit_stop' => [
                'location_name' => 'Suez Canal · Port Said Transit',
                'latitude' => 31.2653,
                'longitude' => 32.3019,
            ],
        ]);

        $this->actingAs($this->operator)->post('/shipments', $payload)->assertRedirect();
        $shipment = Shipment::first();
        $this->assertSame(1, $shipment->trackingEvents()->where('event_type', 'CHECKPOINT')->count());

        // Saving the edit twice without touching the stop must not add
        // a checkpoint each time; a genuinely new stop adds exactly one.
        $this->actingAs($this->operator)->put('/shipments/' . $shipment->id, $payload)->assertRedirect();
        $this->actingAs($this->operator)->put('/shipments/' . $shipment->id, $payload)->assertRedirect();
        $this->assertSame(1, $shipment->trackingEvents()->where('event_type', 'CHECKPOINT')->count());

        $payload['transit_stop'] = [
            'location_name' => 'Colombo Deepwater Hub',
            'latitude' => 6.9344,
            'longitude' => 79.8428,
        ];
        $this->actingAs($this->operator)->put('/shipments/' . $shipment->id, $payload)->assertRedirect();
        $this->assertSame(2, $shipment->trackingEvents()->where('event_type', 'CHECKPOINT')->count());
    }

    public function test_web_archive_shipment(): void
    {
        $shipment = $this->makeShipment($this->customer, $this->carrier, $this->origin, $this->dest);

        $this->actingAs($this->operator)
            ->post('/shipments/' . $shipment->id . '/archive')
            ->assertRedirect('/shipments');

        $this->assertNotNull($shipment->fresh()->archived_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'shipment.archived', 'entity_id' => $shipment->id]);
    }

    public function test_web_scenario_preset_and_manual_rules(): void
    {
        $this->actingAs($this->admin)
            ->post('/scenario', ['preset' => 'GROUP_B'])
            ->assertRedirect()->assertSessionHas('status');

        $row = DB::table('scenario_configs')->first();
        $this->assertSame('GROUP_B', $row->group_code);
        $this->assertSame(18, (int) $row->low_threshold_hours);
        $this->assertDatabaseHas('audit_logs', ['action' => 'scenario.updated']);

        $this->actingAs($this->admin)->post('/scenario', [
            'group_code' => 'CUSTOM',
            'route_corridor' => 'Test corridor',
            'low_threshold_hours' => 24,
            'high_threshold_hours' => 12,
            'primary_exception' => 'CUSTOMS_HOLD',
            'kpi_focus' => 'Test KPI',
        ])->assertSessionHasErrors('high_threshold_hours');

        $this->actingAs($this->operator)
            ->post('/scenario', ['preset' => 'GROUP_A'])
            ->assertForbidden();
    }

    public function test_web_add_user_and_edit_role(): void
    {
        $response = $this->actingAs($this->admin)->post('/users', [
            'name' => 'New Operator',
            'email' => 'newop@example.com',
            'role' => 'LOGISTICS_OPERATOR',
        ]);
        $response->assertRedirect()->assertSessionHas('status');
        $this->assertStringContainsString('Temporary password:', session('status'));
        $this->assertDatabaseHas('users', ['email' => 'newop@example.com', 'role' => 'LOGISTICS_OPERATOR']);

        $this->actingAs($this->admin)->patch('/users/' . $this->operator->id, [
            'name' => 'Bimo Santoso',
            'email' => 'operator@cargotrace.io',
            'role' => 'CUSTOMER',
        ])->assertRedirect();
        $this->assertSame('CUSTOMER', $this->operator->fresh()->role);

        // The only admin cannot demote their own account.
        $this->actingAs($this->admin)->patch('/users/' . $this->admin->id, [
            'name' => 'Aria',
            'email' => 'admin@cargotrace.io',
            'role' => 'CUSTOMER',
        ])->assertSessionHasErrors('role');

        $this->actingAs($this->operator)->post('/users', [
            'name' => 'Nope',
            'email' => 'nope@example.com',
            'role' => 'CUSTOMER',
        ])->assertForbidden();
    }

    public function test_web_notifications_read_and_ownership(): void
    {
        $shipment = $this->makeShipment($this->customer, $this->carrier, $this->origin, $this->dest);
        $mine = Notification::create([
            'user_id' => $this->customer->id, 'shipment_id' => $shipment->id,
            'type' => 'STATUS_CHANGED', 'title' => 'T', 'body' => 'B',
        ]);
        $other = Notification::create([
            'user_id' => $this->admin->id, 'shipment_id' => $shipment->id,
            'type' => 'STATUS_CHANGED', 'title' => 'T', 'body' => 'B',
        ]);

        // Another user's notification cannot be marked read by me.
        $this->actingAs($this->customer)
            ->post('/notifications/' . $other->id . '/read')
            ->assertForbidden();

        $this->actingAs($this->customer)
            ->post('/notifications/' . $mine->id . '/read')
            ->assertRedirect();
        $this->assertNotNull($mine->fresh()->read_at);

        Notification::create([
            'user_id' => $this->customer->id, 'shipment_id' => $shipment->id,
            'type' => 'STATUS_CHANGED', 'title' => 'T2', 'body' => 'B2',
        ]);
        $this->actingAs($this->customer)
            ->post('/notifications/read-all')
            ->assertRedirect();
        $this->assertSame(0, Notification::where('user_id', $this->customer->id)->whereNull('read_at')->count());
    }

    public function test_web_carrier_and_location_crud(): void
    {
        $this->actingAs($this->operator)->post('/carriers', [
            'name' => 'Test Air', 'code' => 'TST', 'service_type' => 'AIR',
            'contact_email' => 'ops@test.example',
        ])->assertRedirect('/carriers');
        $this->assertDatabaseHas('carriers', ['code' => 'TST']);

        $this->actingAs($this->operator)->post('/carriers', [
            'name' => 'Dupe', 'code' => 'TST', 'service_type' => 'SEA',
        ])->assertSessionHasErrors('code');

        $this->actingAs($this->operator)->post('/locations', [
            'name' => 'Test Port', 'code' => 'TPT', 'city' => 'Test City',
            'country' => 'Indonesia', 'latitude' => -6.1, 'longitude' => 106.8,
        ])->assertRedirect('/locations');
        $this->assertDatabaseHas('locations', ['code' => 'TPT']);

        // Quick-add resolves a catalog preset (Krueng Geukueh) into a real location.
        $this->actingAs($this->operator)->post('/locations/quick-add', ['code' => 'KRG'])
            ->assertRedirect();
        $this->assertDatabaseHas('locations', ['code' => 'KRG']);
    }

    public function test_web_claim_shipment_links_to_customer(): void
    {
        $shipment = $this->makeShipment($this->customer, $this->carrier, $this->origin, $this->dest);
        $shipment->update(['customer_id' => $this->admin->id]);

        $this->actingAs($this->customer)
            ->post('/shipments/claim', ['shipment_number' => strtolower($shipment->shipment_number)])
            ->assertRedirect('/shipments/' . $shipment->id);

        $this->assertSame($this->customer->id, $shipment->fresh()->customer_id);
        $this->assertDatabaseHas('notifications', ['shipment_id' => $shipment->id, 'type' => 'SHIPMENT_LINKED']);

        $this->actingAs($this->customer)
            ->post('/shipments/claim', ['shipment_number' => 'CT9999XXX'])
            ->assertSessionHasErrors('shipment_number');
    }

    public function test_web_role_gates_on_pages(): void
    {
        $shipment = $this->makeShipment($this->customer, $this->carrier, $this->origin, $this->dest);
        $others = $this->makeShipment($this->admin, $this->carrier, $this->origin, $this->dest);

        $this->actingAs($this->customer)->get('/scenario')->assertForbidden();
        $this->actingAs($this->customer)->get('/audit')->assertForbidden();
        $this->actingAs($this->operator)->get('/scenario')->assertForbidden();
        $this->actingAs($this->operator)->get('/audit')->assertForbidden();

        $this->actingAs($this->customer)->get('/shipments/' . $shipment->id)->assertOk();
        $this->actingAs($this->customer)->get('/shipments/' . $others->id)->assertForbidden();
        $this->actingAs($this->customer)->get('/shipments/' . $shipment->id . '/edit')->assertForbidden();
        $this->actingAs($this->customer)->get('/shipments/' . $shipment->id . '/bill-of-lading')->assertOk();
        $this->actingAs($this->customer)->get('/shipments/' . $others->id . '/bill-of-lading')->assertForbidden();
    }

    public function test_web_shipment_focus_returns_inspector_html(): void
    {
        $a = $this->makeShipment($this->customer, $this->carrier, $this->origin, $this->dest);
        $b = $this->makeShipment($this->customer, $this->carrier, $this->origin, $this->dest);

        $response = $this->actingAs($this->admin)->getJson('/shipments/' . $b->id . '/focus');

        $response->assertOk()->assertJsonStructure(['html']);
        $response->assertSee($b->shipment_number);
        $response->assertSee('Inspect shipment');
        $response->assertDontSee($a->shipment_number);
    }

    public function test_web_shipment_focus_respects_visibility(): void
    {
        $mine = $this->makeShipment($this->customer, $this->carrier, $this->origin, $this->dest);
        $others = $this->makeShipment($this->admin, $this->carrier, $this->origin, $this->dest);

        $this->actingAs($this->customer)->getJson('/shipments/' . $mine->id . '/focus')->assertOk();
        $this->actingAs($this->customer)->getJson('/shipments/' . $others->id . '/focus')->assertNotFound();
    }

    public function test_web_shipment_focus_requires_auth_and_rejects_archived(): void
    {
        $shipment = $this->makeShipment($this->customer, $this->carrier, $this->origin, $this->dest);

        $this->get('/shipments/' . $shipment->id . '/focus')->assertRedirect('/login');

        $shipment->update(['archived_at' => now()]);

        $this->actingAs($this->admin)->getJson('/shipments/' . $shipment->id . '/focus')->assertNotFound();
    }
}
