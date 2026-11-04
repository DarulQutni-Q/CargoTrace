<?php

namespace Tests\Unit;

use App\Enums\ShipmentStatus;
use App\Models\Shipment;
use App\Services\EtaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EtaServiceTest extends TestCase
{
    use RefreshDatabase;

    private EtaService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new EtaService;
    }

    public function test_shipments_without_an_estimate_are_pending(): void
    {
        $shipment = Shipment::factory()->status(ShipmentStatus::InTransit)->create();

        $this->assertSame('pending', $this->service->assess($shipment)['state']);
    }

    public function test_estimates_at_or_before_expected_arrival_are_on_time(): void
    {
        $shipment = Shipment::factory()->status(ShipmentStatus::InTransit)->onTimeEstimate()->create();

        $this->assertSame('on_time', $this->service->assess($shipment)['state']);
        $this->assertSame(0.0, $this->service->assess($shipment)['delay_hours']);
    }

    public function test_delay_severity_follows_the_configured_thresholds(): void
    {
        // Defaults from the brief: LOW up to 6h, MEDIUM up to 24h, HIGH beyond.
        $low = Shipment::factory()->status(ShipmentStatus::InTransit)->delayedBy(5)->create();
        $edge = Shipment::factory()->status(ShipmentStatus::InTransit)->delayedBy(6)->create();
        $medium = Shipment::factory()->status(ShipmentStatus::InTransit)->delayedBy(12)->create();
        $high = Shipment::factory()->status(ShipmentStatus::InTransit)->delayedBy(30)->create();

        $this->assertSame('low', $this->service->assess($low)['state']);
        $this->assertSame('low', $this->service->assess($edge)['state']);
        $this->assertSame('medium', $this->service->assess($medium)['state']);
        $this->assertSame('high', $this->service->assess($high)['state']);
    }

    public function test_thresholds_come_from_config_not_hardcoded_values(): void
    {
        config(['cargotrace.delay.low_threshold_hours' => 1]);
        config(['cargotrace.delay.high_threshold_hours' => 2]);

        $shipment = Shipment::factory()->status(ShipmentStatus::InTransit)->delayedBy(3)->create();

        $assessment = $this->service->assess($shipment);
        $this->assertSame('high', $assessment['state']);
        $this->assertSame(1, $assessment['threshold_low']);
        $this->assertSame(2, $assessment['threshold_high']);
    }
}
