<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Visible;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'order_id',
    'user_id',
    'invoice_number',
    'subtotal_minor',
    'tax_minor',
    'total_minor',
    'currency',
    'issued_at',
    'pdf_path',
    'sent_at',
])]
#[Visible([
    'invoice_number',
    'subtotal_minor',
    'tax_minor',
    'total_minor',
    'currency',
    'issued_at',
    'sent_at',
])]
class Invoice extends Model
{
    protected function casts(): array
    {
        return [
            'subtotal_minor' => 'integer',
            'tax_minor' => 'integer',
            'total_minor' => 'integer',
            'issued_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
