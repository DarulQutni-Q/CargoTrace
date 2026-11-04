<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Geocoding through the external provider, via the Laravel HTTP Client
 * with explicit timeout, error-response, and empty-data handling [S9].
 * Returns null on any failure so callers can fall back to manual
 * coordinates — the map never breaks because the provider did.
 */
class GeocodingService
{
    /**
     * @return array{latitude: float, longitude: float, display_name: string}|null
     */
    public function search(string $query): ?array
    {
        $query = trim($query);

        if ($query === '') {
            return null;
        }

        try {
            $response = Http::withUserAgent(config('cargotrace.geocoding.user_agent'))
                ->timeout(config('cargotrace.geocoding.timeout'))
                ->get(config('cargotrace.geocoding.url'), [
                    'q' => $query,
                    'format' => 'jsonv2',
                    'limit' => 1,
                ]);
        } catch (ConnectionException) {
            return null;
        }

        if ($response->failed()) {
            return null;
        }

        $result = $response->json('0');

        if (! is_array($result) || ! isset($result['lat'], $result['lon'])) {
            return null;
        }

        return [
            'latitude' => (float) $result['lat'],
            'longitude' => (float) $result['lon'],
            'display_name' => (string) ($result['display_name'] ?? ''),
        ];
    }
}
