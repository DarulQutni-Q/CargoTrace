<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTrackingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('addTracking', $this->route('shipment'));
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'event_type' => ['required', 'string', 'max:30'],
            'occurred_at' => ['required', 'date'],
            'location_name' => ['required', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'description' => ['nullable', 'string', 'max:500'],
        ];
    }
}
