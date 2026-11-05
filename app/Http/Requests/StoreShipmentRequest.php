<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreShipmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
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
