<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLoadoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'slots' => ['required', 'array', 'max:5'],
            'slots.*' => ['required', 'string', 'distinct', 'max:80'],
        ];
    }
}
