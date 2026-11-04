<?php

namespace App\Http\Requests;

use App\Models\Shipment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreShipmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Shipment::class);
    }

    /**
     * The shipment number may be supplied (API clients) or generated
     * server-side (web form); either way uniqueness is enforced and
     * status never comes from user input.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'shipment_number' => ['sometimes', 'nullable', 'string', 'max:30', 'unique:shipments,shipment_number'],
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
            'estimated_arrival_at' => ['nullable', 'date'],
            'items' => ['sometimes', 'array'],
            'items.*.description' => ['required_with:items', 'string', 'max:255'],
            'items.*.quantity' => ['required_with:items', 'integer', 'min:1'],
            'items.*.weight_kg' => ['required_with:items', 'numeric', 'min:0'],
            'items.*.volume_m3' => ['required_with:items', 'numeric', 'min:0'],
        ];
    }
}
