<?php

namespace Tests\Feature;

use App\Services\GeocodingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GeocodingTest extends TestCase
{
    use RefreshDatabase;

    private GeocodingService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new GeocodingService;
    }

    public function test_it_returns_coordinates_from_the_provider(): void
    {
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([
                ['lat' => '51.9496', 'lon' => '4.1425', 'display_name' => 'Rotterdam, Netherlands'],
            ]),
        ]);

        $result = $this->service->search('Port of Rotterdam');

        $this->assertNotNull($result);
        $this->assertSame(51.9496, $result['latitude']);
        $this->assertSame(4.1425, $result['longitude']);
    }

    public function test_empty_results_return_null(): void
    {
        Http::fake(['nominatim.openstreetmap.org/*' => Http::response([])]);

        $this->assertNull($this->service->search('Nowhereville'));
    }

    public function test_error_responses_return_null(): void
    {
        Http::fake(['nominatim.openstreetmap.org/*' => Http::response('server exploded', 500)]);

        $this->assertNull($this->service->search('Rotterdam'));
    }

    public function test_timeouts_return_null(): void
    {
        Http::fake(function () {
            throw new ConnectionException('cURL timeout');
        });

        $this->assertNull($this->service->search('Rotterdam'));
    }

    public function test_empty_queries_never_hit_the_provider(): void
    {
        Http::fake();

        $this->assertNull($this->service->search('   '));
        Http::assertNothingSent();
    }
}
