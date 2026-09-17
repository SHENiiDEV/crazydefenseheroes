<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['code', 'level', 'quantity', 'is_equipped'])]
class TowerCard extends Model
{
    protected function casts(): array
    {
        return [
            'level' => 'integer',
            'quantity' => 'integer',
            'is_equipped' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
