<?php

namespace Tests\Feature;

use App\Enums\ShipmentStatus;
use App\Models\Shipment;
use App\Models\TrackingEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrackingTest extends TestCase
{
    use RefreshDatabase;

    public function test_operators_can_record_a_valid_status_transition(): void
    {
        $operator = User::factory()->operator()->create();
        $shipment = Shipment::factory()->status(ShipmentStatus::Created)->create();

        $response = $this->actingAs($operator)->post(route('shipments.tracking.store', $shipment), [
            'event_type' => ShipmentStatus::Booked->value,
            'occurred_at' => now()->format('Y-m-d\TH:i'),
            'location_name' => 'Jakarta origin warehouse',
            'description' => 'Booking confirmed with the carrier',
        ]);

        $response->assertRedirect(route('shipments.show', $shipment));
        $this->assertSame(ShipmentStatus::Booked->value, $shipment->refresh()->current_status->value);
        $this->assertSame(1, $shipment->trackingEvents()->count());
    }

    public function test_invalid_status_transitions_are_rejected(): void
    {
        $operator = User::factory()->operator()->create();
        $shipment = Shipment::factory()->status(ShipmentStatus::InTransit)->create();

        $response = $this->actingAs($operator)->from(route('shipments.show', $shipment))
            ->post(route('shipments.tracking.store', $shipment), [
                'event_type' => ShipmentStatus::Created->value,
                'occurred_at' => now()->format('Y-m-d\TH:i'),
                'location_name' => 'Somewhere',
            ]);

        $response->assertRedirect(route('shipments.show', $shipment));
        $response->assertSessionHasErrors('event_type');
        $this->assertSame(ShipmentStatus::InTransit->value, $shipment->refresh()->current_status->value);
    }

    public function test_terminal_status_cannot_return_to_an_earlier_one(): void
    {
        $operator = User::factory()->operator()->create();
        $shipment = Shipment::factory()->status(ShipmentStatus::Delivered)->create();

        $this->actingAs($operator)->post(route('shipments.tracking.store', $shipment), [
            'event_type' => ShipmentStatus::InTransit->value,
            'occurred_at' => now()->format('Y-m-d\TH:i'),
            'location_name' => 'Somewhere',
        ])->assertSessionHasErrors('event_type');

        $this->assertSame(ShipmentStatus::Delivered->value, $shipment->refresh()->current_status->value);
    }

    public function test_events_cannot_predate_the_latest_recorded_event(): void
    {
        $operator = User::factory()->operator()->create();
        $shipment = Shipment::factory()->status(ShipmentStatus::InTransit)->create();

        $shipment->trackingEvents()->create([
            'event_type' => ShipmentStatus::InTransit->value,
            'occurred_at' => now()->subDay(),
            'location_name' => 'Transit hub',
            'created_by' => $operator->id,
        ]);

        $this->actingAs($operator)->post(route('shipments.tracking.store', $shipment), [
            'event_type' => ShipmentStatus::InTransit->value,
            'occurred_at' => now()->subDays(2)->format('Y-m-d\TH:i'),
            'location_name' => 'Origin',
        ])->assertSessionHasErrors('occurred_at');

        $this->assertSame(1, $shipment->trackingEvents()->count());
    }

    public function test_history_is_append_only(): void
    {
        $operator = User::factory()->operator()->create();
        $shipment = Shipment::factory()->status(ShipmentStatus::Created)->create();

        foreach ([ShipmentStatus::Booked, ShipmentStatus::PickedUp, ShipmentStatus::InTransit] as $index => $status) {
            $this->actingAs($operator)->post(route('shipments.tracking.store', $shipment), [
                'event_type' => $status->value,
                'occurred_at' => now()->addMinutes($index + 1)->format('Y-m-d\TH:i'),
                'location_name' => "Checkpoint {$index}",
            ]);
        }

        $shipment->refresh();
        $this->assertSame(ShipmentStatus::InTransit->value, $shipment->current_status->value);
        $this->assertSame(3, $shipment->trackingEvents()->count());
        $this->assertSame(
            [ShipmentStatus::Booked->value, ShipmentStatus::PickedUp->value, ShipmentStatus::InTransit->value],
            $shipment->trackingEvents()->orderBy('occurred_at')->pluck('event_type')->all(),
        );
    }

    public function test_customers_cannot_record_tracking_events(): void
    {
        $customer = User::factory()->customer()->create();
        $shipment = Shipment::factory()->status(ShipmentStatus::Created)->create();

        $this->actingAs($customer)->post(route('shipments.tracking.store', $shipment), [
            'event_type' => ShipmentStatus::Booked->value,
            'occurred_at' => now()->format('Y-m-d\TH:i'),
            'location_name' => 'Forged checkpoint',
        ])->assertForbidden();

        $this->assertSame(0, TrackingEvent::count());
        $this->assertSame(ShipmentStatus::Created->value, $shipment->refresh()->current_status->value);
    }
}
