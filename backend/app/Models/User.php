<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'surname', 'email', 'phone', 'date_of_birth', 'password', 'email_verified_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    public function address(): HasOne
    {
        return $this->hasOne(Address::class);
    }

    public function policyAcceptances(): HasMany
    {
        return $this->hasMany(PolicyAcceptance::class);
    }

    public function wallet(): HasOne
    {
        return $this->hasOne(Wallet::class);
    }

    public function towerCards(): HasMany
    {
        return $this->hasMany(TowerCard::class);
    }

    public function inventoryItems(): HasMany
    {
        return $this->hasMany(InventoryItem::class);
    }

    public function boosts(): HasMany
    {
        return $this->hasMany(Boost::class);
    }

    public function loadout(): HasOne
    {
        return $this->hasOne(Loadout::class);
    }

    public function endlessRuns(): HasMany
    {
        return $this->hasMany(EndlessRun::class);
    }

    public function waveCheckpoints(): HasMany
    {
        return $this->hasMany(WaveCheckpoint::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function walletTransactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
