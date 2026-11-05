<?php

namespace Tests\Feature\Concerns;

use App\Models\Carrier;
use App\Models\Location;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

trait CreatesCorridor
{
    protected function makeUser(string $role, string $email, string $name = 'Test User'): User
    {
        return User::create([
            'name' => $name,
            'email' => $email,
            'password' => 'password123',
            'role' => $role,
        ]);
    }

    protected function makeCarrier(string $code = 'NFR'): Carrier
    {
        return Carrier::create([
            'name' => 'Nusantara Freight',
            'code' => $code,
            'service_type' => 'SEA',
            'is_active' => true,
        ]);
    }

    protected function makeLocation(string $code, string $city, float $lat, float $lng, string $country = 'Indonesia'): Location
    {
        return Location::create([
            'name' => $city . ' Port',
            'code' => $code,
            'city' => $city,
            'country' => $country,
            'latitude' => $lat,
            'longitude' => $lng,
        ]);
    }

    /**
     * Create a shipment in CREATED state with one item and no tracking events.
     */
    protected function makeShipment(User $customer, Carrier $carrier, Location $origin, Location $dest, array $overrides = []): Shipment
    {
        $shipment = Shipment::create(array_merge([
            'shipment_number' => 'CT' . str_pad((string) (Shipment::max('id') + 1), 4, '0', STR_PAD_LEFT) . $carrier->code,
            'customer_id' => $customer->id,
            'carrier_id' => $carrier->id,
            'origin_location_id' => $origin->id,
            'destination_location_id' => $dest->id,
            'current_status' => Shipment::STATUS_CREATED,
            'cargo_type' => 'Electronics',
            'weight_kg' => 800,
            'volume_m3' => 12,
            'expected_departure_at' => '2026-11-07 08:30:00',
            'expected_arrival_at' => '2026-11-25 08:30:00',
            'estimated_arrival_at' => null,
        ], $overrides));

        $shipment->items()->create([
            'description' => 'Palletized Electronics',
            'quantity' => 12,
            'weight_kg' => 800,
            'volume_m3' => 12,
        ]);

        return $shipment;
    }

    protected function tokenFor(User $user): string
    {
        return $user->createToken('test')->plainTextToken;
    }

    protected function authHeaders(User $user): array
    {
        // The application instance persists across HTTP calls inside one
        // test, and the Sanctum request guard caches the authenticated user
        // (and the request it was resolved from). Forget the guards so the
        // next request re-authenticates from its own Bearer token, exactly
        // like a real HTTP request against a fresh application instance.
        Auth::forgetGuards();

        return ['Authorization' => 'Bearer ' . $this->tokenFor($user)];
    }
}
