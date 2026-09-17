<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EndlessCheckpointRequest extends FormRequest
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
            'run_id' => ['required', 'uuid'],
            'wave' => ['required', 'integer', 'min:1', 'max:100000'],
            'defeated_enemies' => ['required', 'integer', 'min:0', 'max:10000000'],
            'lives_remaining' => ['required', 'integer', 'min:0', 'max:1000'],
        ];
    }
}
