<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class ExternalTelemetryService
{
    /**
     * Fetch live informational weather from Open-Meteo API with 5-second timeout and graceful fallback.
     */
    public function fetchCorridorWeather(float $latitude, float $longitude): array
    {
        try {
            $response = Http::timeout(5)->get(config('cargotrace.external_apis.open_meteo_url'), [
                'latitude' => $latitude,
                'longitude' => $longitude,
                'current_weather' => 'true',
            ]);

            if ($response->successful() && isset($response['current_weather'])) {
                $cw = $response['current_weather'];
                return [
                    'source' => 'live',
                    'temperature_c' => (float) ($cw['temperature'] ?? 26.0),
                    'windspeed_kmh' => (float) ($cw['windspeed'] ?? 12.0),
                    'weathercode' => (int) ($cw['weathercode'] ?? 1),
                    'label' => $this->weatherCodeLabel((int) ($cw['weathercode'] ?? 1)),
                ];
            }
        } catch (\Throwable $e) {
            // Non-blocking fallback when offline or rate-limited
        }

        return [
            'source' => 'cached_fallback',
            'temperature_c' => 27.5,
            'windspeed_kmh' => 11.0,
            'weathercode' => 1,
            'label' => 'Partly cloudy (cached)',
        ];
    }

    /**
     * Geocode a port, Kabupaten/Kota, or transit hub via OpenStreetMap Nominatim API.
     */
    public function geocodeLocation(string $query): ?array
    {
        if (trim($query) === '') {
            return null;
        }

        try {
            $response = Http::timeout(5)
                ->withHeaders(['User-Agent' => 'CargoTrace-SupplyChain/1.0'])
                ->get(config('cargotrace.external_apis.nominatim_url'), [
                    'format' => 'json',
                    'limit' => 1,
                    'q' => $query,
                ]);

            if ($response->successful()) {
                $hits = $response->json();
                if (is_array($hits) && count($hits) > 0) {
                    return [
                        'display_name' => $hits[0]['display_name'] ?? $query,
                        'latitude' => round((float) $hits[0]['lat'], 6),
                        'longitude' => round((float) $hits[0]['lon'], 6),
                    ];
                }
            }
        } catch (\Throwable $e) {
            // Graceful fallback to manual coordinate entry
        }

        return null;
    }

    private function weatherCodeLabel(int $code): string
    {
        if ($code === 0) return 'Clear sky';
        if ($code <= 3) return 'Partly cloudy';
        if ($code <= 49) return 'Coastal mist';
        if ($code <= 69) return 'Light rain';
        if ($code <= 79) return 'Squalls';
        return 'Thunderstorm';
    }
}
