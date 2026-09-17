<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Visible;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'public_id',
    'user_id',
    'status',
    'provider',
    'provider_payment_id',
    'idempotency_key',
    'total_minor',
    'currency',
    'paid_at',
])]
#[Visible([
    'id',
    'public_id',
    'status',
    'provider',
    'total_minor',
    'currency',
    'paid_at',
    'created_at',
])]
class Order extends Model
{
    protected function casts(): array
    {
        return [
            'total_minor' => 'integer',
            'paid_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }
}
