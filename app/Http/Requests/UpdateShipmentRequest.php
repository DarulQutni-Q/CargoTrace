<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateShipmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->canOperateCorridor();
    }

    public function rules(): array
    {
        return [
            'cargo_type' => ['sometimes', 'string', 'max:120'],
            'weight_kg' => ['sometimes', 'numeric', 'gt:0'],
            'volume_m3' => ['sometimes', 'numeric', 'gte:0'],
            'expected_departure_at' => ['sometimes', 'nullable', 'date'],
            'expected_arrival_at' => ['sometimes', 'date', 'after:expected_departure_at'],
            'estimated_arrival_at' => ['sometimes', 'nullable', 'date'],
        ];
    }
}
