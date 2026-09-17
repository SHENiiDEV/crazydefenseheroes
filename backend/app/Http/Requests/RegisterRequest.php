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
        return [
            'email' => ['required', 'email:rfc', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'max:72'],
            'name' => ['required', 'string', 'max:100'],
            'surname' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:32', 'regex:/^\+?[0-9 ()-]{7,31}$/'],
            'date_of_birth' => ['required', 'date', 'before:today', 'after:1900-01-01'],
            'address' => ['required', 'array'],
            'address.street' => ['required', 'string', 'max:255'],
            'address.city' => ['required', 'string', 'max:120'],
            'address.country' => ['required', 'string', 'in:'.implode(',', config('registration.allowed_countries'))],
            'address.postcode' => ['required', 'string', 'max:32'],
            'terms_accepted' => ['accepted'],
        ];
    }
}
