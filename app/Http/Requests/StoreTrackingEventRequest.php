<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTrackingEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->canOperateCorridor();
    }

    public function rules(): array
    {
        return [
            'event_type' => ['required', 'string', 'in:CREATED,BOOKED,PICKED_UP,IN_TRANSIT,DELIVERED,CHECKPOINT,EXCEPTION,INFO'],
            'occurred_at' => ['required', 'date'],
            'location_name' => ['required', 'string', 'max:180'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'description' => ['nullable', 'string', 'max:1000'],
            'estimated_arrival_at' => ['nullable', 'date'],
        ];
    }
}
