<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreExceptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->canOperateCorridor();
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'string', 'in:WEATHER_DELAY,CUSTOMS_HOLD,CARRIER_DELAY,DAMAGED_CARGO,ROUTE_DEVIATION,PORT_CONGESTION'],
            'severity' => ['required', 'string', 'in:LOW,MEDIUM,HIGH'],
            'detected_at' => ['required', 'date'],
            'location_name' => ['nullable', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:1000'],
            'estimated_arrival_at' => ['nullable', 'date'],
        ];
    }
}
