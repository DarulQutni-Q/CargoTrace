<?php

namespace Tests\Feature;

use App\Enums\ExceptionStatus;
use App\Enums\ShipmentStatus;
use App\Models\Shipment;
use App\Models\ShipmentException;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExceptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_operators_can_raise_exceptions_and_get_a_timeline_event(): void
    {
        $operator = User::factory()->operator()->create();
        $shipment = Shipment::factory()->status(ShipmentStatus::InTransit)->create();

        $response = $this->actingAs($operator)->post(route('shipments.exceptions.store', $shipment), [
            'type' => 'CUSTOMS_HOLD',
            'severity' => 'HIGH',
            'detected_at' => now()->format('Y-m-d\TH:i'),
            'description' => 'Container held at Rotterdam customs',
        ]);

        $response->assertRedirect(route('shipments.show', $shipment));

        $exception = $shipment->exceptions()->firstOrFail();
        $this->assertSame(ExceptionStatus::Open->value, $exception->status->value);
        $this->assertNull($exception->resolved_at);

        $event = $shipment->trackingEvents()->where('exception_id', $exception->id)->firstOrFail();
        $this->assertSame('EXCEPTION', $event->event_type);
        $this->assertTrue($event->isExceptionEvent());
    }

    public function test_exceptions_flow_forward_only_and_resolution_is_stamped(): void
    {
        $operator = User::factory()->operator()->create();
        $exception = ShipmentException::factory()->create();

        $this->actingAs($operator)->patch(route('exceptions.update', $exception), [
            'status' => 'RESOLVED',
        ])->assertSessionHasNoErrors();

        $exception->refresh();
        $this->assertSame(ExceptionStatus::Resolved->value, $exception->status->value);
        $this->assertNotNull($exception->resolved_at);
        $this->assertSame($operator->id, $exception->resolved_by);

        $this->actingAs($operator)->patch(route('exceptions.update', $exception), [
            'status' => 'OPEN',
        ])->assertSessionHasErrors('status');

        $this->assertSame(ExceptionStatus::Resolved->value, $exception->refresh()->status->value);
    }

    public function test_customers_cannot_manage_exceptions(): void
    {
        $customer = User::factory()->customer()->create();
        $shipment = Shipment::factory()->for($customer, 'customer')->create();

        $this->actingAs($customer)->post(route('shipments.exceptions.store', $shipment), [
            'type' => 'DAMAGED',
            'severity' => 'LOW',
            'detected_at' => now()->format('Y-m-d\TH:i'),
        ])->assertForbidden();

        $this->assertSame(0, ShipmentException::count());
    }

    public function test_customers_see_exceptions_on_their_own_shipments_only(): void
    {
        $own = Shipment::factory()->create();
        ShipmentException::factory()->for($own, 'shipment')->create(['type' => 'PORT_CONGESTION']);
        $other = Shipment::factory()->create();
        ShipmentException::factory()->for($other, 'shipment')->create(['type' => 'DAMAGED']);

        $response = $this->actingAs($own->customer)->get(route('shipments.show', $own));

        $response->assertOk();
        $response->assertSee('Port congestion');
        $response->assertDontSee('Damaged');
    }

    public function test_the_exception_filter_lists_shipments_with_open_exceptions(): void
    {
        $operator = User::factory()->operator()->create();
        $withOpen = Shipment::factory()->create();
        ShipmentException::factory()->for($withOpen, 'shipment')->create();
        $resolvedOnly = Shipment::factory()->create();
        ShipmentException::factory()->resolved()->for($resolvedOnly, 'shipment')->create();

        $response = $this->actingAs($operator)->get(route('shipments.index', ['open_exceptions' => 1]));

        $response->assertOk();
        $response->assertSee($withOpen->shipment_number);
        $response->assertDontSee($resolvedOnly->shipment_number);
    }
}
