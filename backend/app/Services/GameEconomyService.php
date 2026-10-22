<?php

namespace App\Services;

use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class GameEconomyService
{
    public function openChest(User $user, string $chestId): array
    {
        $chest = config('game.chests.'.$chestId);

        return DB::transaction(function () use ($user, $chest): array {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $wallet = $user->wallet()->lockForUpdate()->firstOrFail();
            if ($wallet->soft_currency < $chest['cost_gold'] || $wallet->diamonds < $chest['cost_diamonds']) {
                throw ValidationException::withMessages(['wallet' => ['Insufficient currency. This chest costs '.$chest['cost_gold'].' Gold and '.$chest['cost_diamonds'].' Diamonds.']]);
            }
            $wallet->decrement('soft_currency', $chest['cost_gold']);
            $wallet->decrement('diamonds', $chest['cost_diamonds']);
            $card = $user->towerCards()->firstOrCreate(['code' => $chest['card_code']], ['level' => 1, 'quantity' => 1, 'is_equipped' => true]);
            $wasNew = $card->wasRecentlyCreated;
            if (! $wasNew) {
                $card->increment('level');
                $card->increment('quantity');
            }
            $boost = $user->boosts()->firstOrCreate(['code' => $chest['boost_code']], ['quantity' => 0]);
            $boost->increment('quantity', $chest['boost_quantity']);
            $loadout = $user->loadout()->firstOrCreate([], ['slots' => []]);
            $loadout->update(['slots' => array_values(array_unique([...$loadout->slots, $chest['card_code']]))]);
            WalletTransaction::create([
                'user_id' => $user->id, 'wallet_id' => $wallet->id, 'reference' => 'chest:'.Str::uuid(), 'type' => 'chest',
                'diamonds_delta' => -$chest['cost_diamonds'], 'soft_currency_delta' => -$chest['cost_gold'],
                'metadata' => ['chest_id' => $chest['id'], 'card_code' => $chest['card_code'], 'boost_code' => $chest['boost_code'], 'boost_quantity' => $chest['boost_quantity']],
            ]);

            return ['coins' => 0, 'diamonds' => 0, 'card_code' => $chest['card_code'], 'boost_code' => $chest['boost_code'],
                'boost_quantity' => $chest['boost_quantity'], 'was_new' => $wasNew, 'new_level' => $card->fresh()->level];
        });
    }

    public function upgrade(User $user, string $code, string $currency): void
    {
        DB::transaction(function () use ($user, $code, $currency): void {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $wallet = $user->wallet()->lockForUpdate()->firstOrFail();
            $card = $user->towerCards()->where('code', $code)->first();
            if (! $card) {
                throw ValidationException::withMessages(['code' => ['Unlock this tower in the Chest Vault before upgrading it.']]);
            }
            $cost = $card->level * (int) config('game.upgrade_'.$currency.'_per_level');
            $column = $currency === 'gold' ? 'soft_currency' : 'diamonds';
            if ($wallet->{$column} < $cost) {
                throw ValidationException::withMessages(['wallet' => ['Not enough '.$currency.' for this upgrade.']]);
            }
            $wallet->decrement($column, $cost);
            $card->increment('level');
            WalletTransaction::create([
                'user_id' => $user->id, 'wallet_id' => $wallet->id, 'reference' => 'upgrade:'.Str::uuid(), 'type' => 'upgrade',
                'diamonds_delta' => $currency === 'diamonds' ? -$cost : 0, 'soft_currency_delta' => $currency === 'gold' ? -$cost : 0,
                'metadata' => ['tower_code' => $code, 'new_level' => $card->fresh()->level],
            ]);
        });
    }

    public function useBoost(User $user, string $code): void
    {
        DB::transaction(function () use ($user, $code): void {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $boost = $user->boosts()->where('code', $code)->lockForUpdate()->first();
            if (! $boost || $boost->quantity < 1 || ($boost->expires_at && $boost->expires_at->isPast())) {
                throw ValidationException::withMessages(['code' => ['No available charge for this boost.']]);
            }
            $boost->decrement('quantity');
        });
    }
}
