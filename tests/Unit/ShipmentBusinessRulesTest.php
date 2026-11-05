<?php

namespace Tests\Unit;

use App\Models\Shipment;
use App\Models\ShipmentException;
use App\Services\DelayClassifierService;
use App\Services\ShipmentLifecycleService;
use PHPUnit\Framework\TestCase;

class ShipmentBusinessRulesTest extends TestCase
{
    public function test_allows_sequential_forward_transitions_from_created_to_delivered(): void
    {
        $service = new ShipmentLifecycleService();

        $this->assertTrue($service->canTransitionStatus(Shipment::STATUS_CREATED, Shipment::STATUS_BOOKED));
        $this->assertTrue($service->canTransitionStatus(Shipment::STATUS_BOOKED, Shipment::STATUS_PICKED_UP));
        $this->assertTrue($service->canTransitionStatus(Shipment::STATUS_PICKED_UP, Shipment::STATUS_IN_TRANSIT));
        $this->assertTrue($service->canTransitionStatus(Shipment::STATUS_IN_TRANSIT, Shipment::STATUS_DELIVERED));
    }

    public function test_enforces_delivered_as_terminal_state_and_blocks_backward_or_skipped_transitions(): void
    {
        $service = new ShipmentLifecycleService();

        $this->assertSame([], $service->allowedNextStatuses(Shipment::STATUS_DELIVERED));
        $this->assertFalse($service->canTransitionStatus(Shipment::STATUS_DELIVERED, Shipment::STATUS_IN_TRANSIT));
        $this->assertFalse($service->canTransitionStatus(Shipment::STATUS_CREATED, Shipment::STATUS_DELIVERED));
    }

    public function test_classifies_deterministic_eta_delay_using_group_thresholds(): void
    {
        $classifier = new DelayClassifierService();

        $onTime = $classifier->assess('2026-11-20 10:00:00', '2026-11-20 09:30:00', 6, 24);
        $this->assertFalse($onTime['delayed']);
        $this->assertSame('on_time', $onTime['state']);

        $lowDelay = $classifier->assess('2026-11-20 10:00:00', '2026-11-20 14:00:00', 6, 24);
        $this->assertTrue($lowDelay['delayed']);
        $this->assertSame('low', $lowDelay['state']);
        $this->assertSame(4.0, $lowDelay['delay_hours']);

        $highDelay = $classifier->assess('2026-11-20 10:00:00', '2026-11-21 16:00:00', 6, 24);
        $this->assertTrue($highDelay['delayed']);
        $this->assertSame('high', $highDelay['state']);
        $this->assertSame(30.0, $highDelay['delay_hours']);
    }

    public function test_enforces_exception_resolution_state_machine(): void
    {
        $service = new ShipmentLifecycleService();

        $this->assertTrue($service->canTransitionExceptionStatus(ShipmentException::STATUS_OPEN, ShipmentException::STATUS_INVESTIGATING));
        $this->assertTrue($service->canTransitionExceptionStatus(ShipmentException::STATUS_INVESTIGATING, ShipmentException::STATUS_RESOLVED));
        $this->assertFalse($service->canTransitionExceptionStatus(ShipmentException::STATUS_RESOLVED, ShipmentException::STATUS_OPEN));
    }
}
