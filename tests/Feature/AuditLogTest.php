<?php

namespace Tests\Feature;

use App\Enums\ShipmentStatus;
use App\Models\AuditLog;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_important_changes_land_in_the_audit_log_with_their_actor(): void
    {
        $operator = User::factory()->operator()->create();
        $shipment = Shipment::factory()->status(ShipmentStatus::Created)->create();

        $this->actingAs($operator)->post(route('shipments.tracking.store', $shipment), [
            'event_type' => ShipmentStatus::Booked->value,
            'occurred_at' => now()->format('Y-m-d\TH:i'),
            'location_name' => 'Jakarta origin warehouse',
        ]);

        $entry = AuditLog::query()->where('action', 'shipment.status_changed')->firstOrFail();
        $this->assertSame($operator->id, $entry->user_id);
        $this->assertSame(Shipment::class, $entry->entity_type);
        $this->assertSame($shipment->id, $entry->entity_id);
        $this->assertSame('CREATED', $entry->changes['from']);
        $this->assertSame('BOOKED', $entry->changes['to']);
    }

    public function test_shipment_updates_record_the_values_that_moved(): void
    {
        $operator = User::factory()->operator()->create();
        $shipment = Shipment::factory()->create(['cargo_type' => 'Electronics']);

        $this->actingAs($operator)->put(route('shipments.update', $shipment), [
            'customer_id' => $shipment->customer_id,
            'carrier_id' => $shipment->carrier_id,
            'origin_location_id' => $shipment->origin_location_id,
            'destination_location_id' => $shipment->destination_location_id,
            'cargo_type' => 'Medical supplies',
            'weight_kg' => $shipment->weight_kg,
            'volume_m3' => $shipment->volume_m3,
            'expected_departure_at' => $shipment->expected_departure_at->format('Y-m-d\TH:i'),
            'expected_arrival_at' => $shipment->expected_arrival_at->format('Y-m-d\TH:i'),
        ]);

        $entry = AuditLog::query()->where('action', 'shipment.updated')->firstOrFail();
        $this->assertSame('Electronics', $entry->changes['cargo_type']['from']);
        $this->assertSame('Medical supplies', $entry->changes['cargo_type']['to']);
    }

    public function test_archiving_is_audited(): void
    {
        $admin = User::factory()->admin()->create();
        $shipment = Shipment::factory()->create();

        $this->actingAs($admin)->delete(route('shipments.destroy', $shipment));

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'shipment.archived',
            'user_id' => $admin->id,
            'entity_id' => $shipment->id,
        ]);
    }

    public function test_only_admins_open_the_audit_page(): void
    {
        $this->actingAs(User::factory()->operator()->create())
            ->get(route('audit.index'))
            ->assertForbidden();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('audit.index'))
            ->assertOk()
            ->assertSee('Audit log');
    }
}
