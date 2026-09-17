<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Visible;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id',
    'client_run_id',
    'highest_wave',
    'defeated_enemies',
    'lives_remaining',
    'soft_currency_earned',
])]
#[Visible([
    'client_run_id',
    'highest_wave',
    'defeated_enemies',
    'lives_remaining',
    'soft_currency_earned',
    'created_at',
    'updated_at',
])]
class EndlessRun extends Model
{
    protected function casts(): array
    {
        return [
            'highest_wave' => 'integer',
            'defeated_enemies' => 'integer',
            'lives_remaining' => 'integer',
            'soft_currency_earned' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function checkpoints(): HasMany
    {
        return $this->hasMany(WaveCheckpoint::class);
    }
}
