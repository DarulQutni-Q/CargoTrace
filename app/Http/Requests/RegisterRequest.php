<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:180', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];

        // Same gate as the web sign-in form: the Terms & Privacy
        // checkbox shown on the sign-up page must actually be ticked.
        // API registration is programmatic, so the rule applies to the
        // web route only (POST /register, not /api/...).
        if ($this->is('register')) {
            $rules['agree'] = ['required', 'accepted'];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'agree.required' => 'You must agree to the Terms & Privacy to continue.',
            'agree.accepted' => 'You must agree to the Terms & Privacy to continue.',
        ];
    }
}
