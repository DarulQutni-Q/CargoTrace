<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'email' => ['required', 'string', 'email', 'max:180'],
            'password' => ['required', 'string'],
        ];

        // The web sign-in form shows a Terms & Privacy checkbox; the
        // agreement must actually be given, otherwise the form is just
        // decoration. API clients are programmatic and never render the
        // form, so the rule applies to the web route only (POST /login,
        // not /api/...).
        if ($this->is('login')) {
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
