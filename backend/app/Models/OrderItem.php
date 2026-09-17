<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Visible;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'order_id',
    'product_id',
    'product_code',
    'product_name',
    'product_type',
    'grant_key',
    'quantity',
    'grant_quantity',
    'unit_price_minor',
    'currency',
    'metadata',
])]
#[Visible([
    'product_code',
    'product_name',
    'product_type',
    'quantity',
    'grant_quantity',
    'unit_price_minor',
    'currency',
])]
class OrderItem extends Model
{
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'grant_quantity' => 'integer',
            'unit_price_minor' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
