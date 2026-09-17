<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'product_code' => ['required', 'string', 'max:80'],
            'quantity' => ['required', 'integer', 'min:1', 'max:20'],
            'idempotency_key' => ['required', 'string', 'min:8', 'max:160'],
        ];
    }
}
