<?php

namespace App\Http\Requests;

use App\Models\Location;
use App\Services\IndonesianPortCatalog;
use Illuminate\Foundation\Http\FormRequest;

class StoreShipmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The shipment form's port comboboxes accept catalog presets that are
     * not active locations yet (e.g. KRG): resolve them to a real location
     * id first, registering the preset, mirroring the reference
     * ensureLocationPreset behaviour.
     */
    protected function prepareForValidation(): void
    {
        $input = $this->all();

        // The form always renders the transit-stop hidden fields; drop the
        // whole group when nothing was picked so required_with stays quiet.
        if (isset($input['transit_stop']) && is_array($input['transit_stop'])) {
            $stop = array_filter($input['transit_stop'], fn ($v) => $v !== null && $v !== '');
            if (empty($stop)) {
                unset($input['transit_stop']);
            }
        }

        $input['origin_location_id'] = $this->resolvePortReference($input['origin_location_id'] ?? null);
        $input['destination_location_id'] = $this->resolvePortReference($input['destination_location_id'] ?? null);

        // replace() (not merge()) so a dropped transit_stop key is really gone.
        $this->replace($input);
    }

    private function resolvePortReference(mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return $value;
        }
        if (is_numeric($value) && Location::where('id', (int) $value)->exists()) {
            return (int) $value;
        }

        $code = strtoupper(trim((string) $value));
        if (preg_match('/^([A-Z]{2,5})\b/', $code, $matches)) {
            $code = $matches[1];
        }

        $existing = Location::where('code', $code)->first();
        if ($existing !== null) {
            return $existing->id;
        }

        $preset = collect(IndonesianPortCatalog::presets())->firstWhere('code', $code);
        if ($preset === null) {
            return $value;
        }

        $location = Location::create([
            'name' => $preset['name'],
            'code' => $preset['code'],
            'city' => $preset['province'] ? $preset['city'].' ('.$preset['province'].')' : $preset['city'],
            'province' => $preset['province'],
            'country' => $preset['country'],
            'latitude' => $preset['latitude'],
            'longitude' => $preset['longitude'],
        ]);

        return $location->id;
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'integer', 'exists:users,id'],
            'carrier_id' => ['required', 'integer', 'exists:carriers,id'],
            'origin_location_id' => ['required', 'integer', 'exists:locations,id'],
            'destination_location_id' => ['required', 'integer', 'exists:locations,id', 'different:origin_location_id'],
            'cargo_type' => ['required', 'string', 'max:120'],
            'weight_kg' => ['required', 'numeric', 'gt:0'],
            'volume_m3' => ['required', 'numeric', 'gte:0'],
            'expected_departure_at' => ['nullable', 'date'],
            'expected_arrival_at' => ['required', 'date', 'after:expected_departure_at'],
            'estimated_arrival_at' => ['nullable', 'date'],
            'transit_stop' => ['nullable', 'array'],
            'transit_stop.location_name' => ['required_with:transit_stop', 'string', 'max:180'],
            'transit_stop.latitude' => ['required_with:transit_stop', 'numeric', 'between:-90,90'],
            'transit_stop.longitude' => ['required_with:transit_stop', 'numeric', 'between:-180,180'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.weight_kg' => ['required', 'numeric', 'gt:0'],
            'items.*.volume_m3' => ['required', 'numeric', 'gte:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'destination_location_id.different' => 'Origin and destination ports cannot be identical.',
            'expected_arrival_at.after' => 'Expected arrival must be later than expected departure.',
        ];
    }
}
