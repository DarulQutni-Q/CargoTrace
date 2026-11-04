<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreExceptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manageExceptions', $this->route('shipment'));
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', 'in:CUSTOMS_HOLD,WEATHER_DELAY,PORT_CONGESTION,DAMAGED,MISROUTED,DOCUMENTATION_ISSUE'],
            'severity' => ['required', 'in:LOW,MEDIUM,HIGH'],
            'detected_at' => ['required', 'date'],
            'description' => ['nullable', 'string', 'max:500'],
            'location_name' => ['nullable', 'string', 'max:255'],
        ];
    }
}
