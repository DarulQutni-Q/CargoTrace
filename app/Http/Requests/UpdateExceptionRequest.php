<?php

namespace App\Http\Requests;

use App\Models\ShipmentException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateExceptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->canOperateCorridor();
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::in([
                ShipmentException::STATUS_OPEN,
                ShipmentException::STATUS_INVESTIGATING,
                ShipmentException::STATUS_RESOLVED,
            ])],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
