<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateExceptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $exception = $this->route('exception');

        return $this->user()->can('manageExceptions', $exception->shipment);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'severity' => ['sometimes', 'in:LOW,MEDIUM,HIGH'],
            'status' => ['sometimes', 'in:OPEN,INVESTIGATING,RESOLVED'],
            'description' => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }
}
