<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Visible;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'endless_run_id',
    'wave',
    'defeated_enemies',
    'lives_remaining',
    'reward',
])]
#[Visible([
    'wave',
    'defeated_enemies',
    'lives_remaining',
    'reward',
    'created_at',
])]
class WaveCheckpoint extends Model
{
    protected function casts(): array
    {
        return [
            'wave' => 'integer',
            'defeated_enemies' => 'integer',
            'lives_remaining' => 'integer',
            'reward' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(EndlessRun::class, 'endless_run_id');
    }
}
