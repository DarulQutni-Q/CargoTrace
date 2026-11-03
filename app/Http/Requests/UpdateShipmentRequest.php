<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateShipmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('shipment'));
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'customer_id' => ['required', Rule::exists('users', 'id')],
            'carrier_id' => ['required', Rule::exists('carriers', 'id')],
            'origin_location_id' => ['required', Rule::exists('locations', 'id')],
            'destination_location_id' => [
                'required', Rule::exists('locations', 'id'), 'different:origin_location_id',
            ],
            'cargo_type' => ['required', 'string', 'max:50'],
            'weight_kg' => ['required', 'numeric', 'min:0'],
            'volume_m3' => ['required', 'numeric', 'min:0'],
            'expected_departure_at' => ['required', 'date'],
            'expected_arrival_at' => ['required', 'date', 'after:expected_departure_at'],
            'items' => ['sometimes', 'array'],
            'items.*.description' => ['required_with:items', 'string', 'max:255'],
            'items.*.quantity' => ['required_with:items', 'integer', 'min:1'],
            'items.*.weight_kg' => ['required_with:items', 'numeric', 'min:0'],
            'items.*.volume_m3' => ['required_with:items', 'numeric', 'min:0'],
        ];
    }
}
