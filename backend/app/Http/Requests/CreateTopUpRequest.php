<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CreateTopUpRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'diamonds' => ['required', 'integer', 'min:0', 'max:'.config('topup.max_quantity')],
            'gold' => ['required', 'integer', 'min:0', 'max:'.config('topup.max_quantity')],
            'idempotency_key' => ['required', 'string', 'min:8', 'max:160'],
        ];
    }
}
