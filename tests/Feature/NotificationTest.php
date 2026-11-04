<?php

namespace Tests\Feature;

use App\Enums\ShipmentStatus;
use App\Models\Shipment;
use App\Models\ShipmentNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_status_changes_notify_the_customer(): void
    {
        $operator = User::factory()->operator()->create();
        $shipment = Shipment::factory()->status(ShipmentStatus::Created)->create();

        $this->actingAs($operator)->post(route('shipments.tracking.store', $shipment), [
            'event_type' => ShipmentStatus::Booked->value,
            'occurred_at' => now()->format('Y-m-d\TH:i'),
            'location_name' => 'Jakarta origin warehouse',
        ]);

        $notification = $shipment->customer->notifications()->firstOrFail();
        $this->assertSame('STATUS_CHANGED', $notification->type);
        $this->assertTrue($notification->isUnread());
    }

    public function test_raising_and_resolving_exceptions_notify_the_customer(): void
    {
        $operator = User::factory()->operator()->create();
        $shipment = Shipment::factory()->status(ShipmentStatus::InTransit)->create();

        $this->actingAs($operator)->post(route('shipments.exceptions.store', $shipment), [
            'type' => 'WEATHER_DELAY',
            'severity' => 'MEDIUM',
            'detected_at' => now()->format('Y-m-d\TH:i'),
        ]);

        $this->assertSame(1, $shipment->customer->notifications()->where('type', 'EXCEPTION_CREATED')->count());

        $exception = $shipment->exceptions()->firstOrFail();
        $this->actingAs($operator)->patch(route('exceptions.update', $exception), [
            'status' => 'RESOLVED',
        ]);

        $this->assertSame(1, $shipment->customer->notifications()->where('type', 'EXCEPTION_RESOLVED')->count());
    }

    public function test_the_bell_shows_the_unread_count_and_marking_works(): void
    {
        $customer = User::factory()->customer()->create();
        $shipment = Shipment::factory()->for($customer, 'customer')->create();

        $customer->notifications()->create([
            'shipment_id' => $shipment->id,
            'type' => 'STATUS_CHANGED',
            'title' => 'Shipment '.$shipment->shipment_number.' is now In transit',
            'body' => 'Latest position: Singapore',
        ]);

        $response = $this->actingAs($customer)->get(route('shipments.index'));
        $response->assertOk();
        $response->assertSee('1'); // unread badge

        $notification = $customer->notifications()->firstOrFail();
        $this->actingAs($customer)->post(route('notifications.read', $notification));
        $this->assertNotNull($notification->refresh()->read_at);

        $this->actingAs($customer)->post(route('notifications.read-all'));
        $this->assertSame(0, $customer->notifications()->whereNull('read_at')->count());
    }

    public function test_customers_cannot_read_someone_elses_notification(): void
    {
        $other = ShipmentNotification::factory()->create();

        $this->actingAs(User::factory()->customer()->create())
            ->post(route('notifications.read', $other))
            ->assertForbidden();

        $this->assertNull($other->refresh()->read_at);
    }

    public function test_the_notifications_page_lists_the_users_own_rows(): void
    {
        $customer = User::factory()->customer()->create();
        $shipment = Shipment::factory()->for($customer, 'customer')->create();

        ShipmentNotification::factory()->for($customer, 'user')->for($shipment, 'shipment')
            ->create(['title' => 'Your shipment moved']);

        $response = $this->actingAs($customer)->get(route('notifications.index'));

        $response->assertOk();
        $response->assertSee('Your shipment moved');
    }
}
