<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Visible;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'wallet_id',
    'order_id',
    'reference',
    'type',
    'diamonds_delta',
    'soft_currency_delta',
    'metadata',
])]
#[Visible([
    'reference',
    'type',
    'diamonds_delta',
    'soft_currency_delta',
    'metadata',
    'created_at',
])]
class WalletTransaction extends Model
{
    protected function casts(): array
    {
        return [
            'diamonds_delta' => 'integer',
            'soft_currency_delta' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
