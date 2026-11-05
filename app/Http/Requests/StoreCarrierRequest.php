<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCarrierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->canOperateCorridor();
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'code' => ['required', 'string', 'max:16', 'unique:carriers,code'],
            'service_type' => ['required', 'string', 'in:SEA,AIR,ROAD'],
            'contact_email' => ['nullable', 'email', 'max:180'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
