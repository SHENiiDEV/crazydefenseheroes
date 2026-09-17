<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PaymentWebhookRequest extends FormRequest
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
            'event_id' => ['required', 'string', 'max:160'],
            'type' => ['required', 'in:payment.succeeded'],
            'order_id' => ['required', 'uuid'],
            'payment_id' => ['required', 'string', 'max:160'],
            'amount_minor' => ['required', 'integer', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
        ];
    }
}
