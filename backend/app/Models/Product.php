<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Visible;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'code',
    'name',
    'product_type',
    'grant_key',
    'grant_quantity',
    'price_minor',
    'currency',
    'metadata',
    'active',
])]
#[Visible([
    'code',
    'name',
    'product_type',
    'grant_key',
    'grant_quantity',
    'price_minor',
    'currency',
    'metadata',
])]
class Product extends Model
{
    protected function casts(): array
    {
        return [
            'grant_quantity' => 'integer',
            'price_minor' => 'integer',
            'metadata' => 'array',
            'active' => 'boolean',
        ];
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
