<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\UpdateLoadoutRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PlayerStateController
{
    public function show(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $user->load(['address', 'wallet', 'towerCards', 'inventoryItems', 'boosts', 'loadout']);

        return response()->json([
            'success' => true,
            'data' => $this->state($user),
        ]);
    }

    public function wallet(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $user->load('wallet');

        return response()->json([
            'success' => true,
            'data' => ['wallet' => $user->wallet],
        ]);
    }

    public function inventory(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $user->load(['towerCards', 'inventoryItems', 'boosts', 'loadout']);

        return response()->json([
            'success' => true,
            'data' => [
                'tower_cards' => $user->towerCards,
                'items' => $user->inventoryItems,
                'boosts' => $user->boosts,
                'loadout' => $user->loadout,
            ],
        ]);
    }

    public function updateLoadout(UpdateLoadoutRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $slots = $request->validated('slots');
        $ownedCodes = $user->towerCards()->whereIn('code', $slots)->pluck('code')->all();

        if (count($ownedCodes) !== count($slots)) {
            throw ValidationException::withMessages([
                'slots' => ['The loadout can contain only tower cards in your inventory.'],
            ]);
        }

        $loadout = $user->loadout()->updateOrCreate([], ['slots' => array_values($slots)]);
        $user->load(['address', 'wallet', 'towerCards', 'inventoryItems', 'boosts']);

        return response()->json([
            'success' => true,
            'data' => $this->state($user->setRelation('loadout', $loadout)),
        ]);
    }

    public function upgradeTower(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $data = $request->validate([
            'code' => ['required', 'string', 'in:ember-archer,stone-warden,vault-mage'],
        ]);

        $code = $data['code'];
        $wallet = $user->wallet()->firstOrCreate([], ['diamonds' => 0, 'soft_currency' => 100]);
        $card = $user->towerCards()->firstOrCreate(['code' => $code], ['level' => 1, 'quantity' => 1, 'is_equipped' => true]);

        $cost = $card->level * 80;

        if ($wallet->soft_currency < $cost) {
            throw ValidationException::withMessages([
                'soft_currency' => ['Insufficient soft currency for upgrade.'],
            ]);
        }

        DB::transaction(function () use ($wallet, $card, $cost): void {
            $wallet->decrement('soft_currency', $cost);
            $card->increment('level', 1);
        });

        $user->unsetRelation('towerCards')->unsetRelation('wallet')->unsetRelation('boosts')->unsetRelation('loadout');
        $user->load(['address', 'wallet', 'towerCards', 'inventoryItems', 'boosts', 'loadout']);

        return response()->json([
            'success' => true,
            'data' => $this->state($user),
        ]);
    }

    public function openChest(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $data = $request->validate([
            'chest_id' => ['required', 'string', 'in:bronze,silver,gold'],
        ]);

        $chestId = $data['chest_id'];
        $wallet = $user->wallet()->firstOrCreate([], ['diamonds' => 0, 'soft_currency' => 100]);

        $costCoins = match ($chestId) {
            'bronze' => 50,
            'silver' => 120,
            'gold' => 250,
        };
        $costDiamonds = match ($chestId) {
            'gold' => 10,
            default => 0,
        };

        if ($wallet->soft_currency < $costCoins || $wallet->diamonds < $costDiamonds) {
            throw ValidationException::withMessages([
                'wallet' => ['Insufficient currency to open this chest.'],
            ]);
        }

        $result = DB::transaction(function () use ($user, $wallet, $chestId, $costCoins, $costDiamonds) {
            if ($costCoins > 0) {
                $wallet->decrement('soft_currency', $costCoins);
            }
            if ($costDiamonds > 0) {
                $wallet->decrement('diamonds', $costDiamonds);
            }

            $coinsReward = match ($chestId) {
                'bronze' => random_int(60, 140),
                'silver' => random_int(130, 260),
                'gold' => random_int(300, 750),
            };
            $diamondsReward = match ($chestId) {
                'bronze' => 0,
                'silver' => 5,
                'gold' => 20,
            };
            $cardCode = match ($chestId) {
                'bronze' => 'ember-archer',
                'silver' => 'stone-warden',
                'gold' => 'vault-mage',
            };

            if ($coinsReward > 0) {
                $wallet->increment('soft_currency', $coinsReward);
            }
            if ($diamondsReward > 0) {
                $wallet->increment('diamonds', $diamondsReward);
            }

            $card = $user->towerCards()->where('code', $cardCode)->first();
            $wasNew = false;
            if ($card) {
                $card->increment('level', 1);
                $card->increment('quantity', 1);
                $card->refresh();
            } else {
                $card = $user->towerCards()->create([
                    'code' => $cardCode,
                    'level' => 1,
                    'quantity' => 1,
                    'is_equipped' => true,
                ]);
                $wasNew = true;
            }

            // Auto-equip to loadout slots if not yet present
            $loadout = $user->loadout()->firstOrCreate([], ['slots' => ['ember-archer']]);
            $currentSlots = $loadout->slots ?? [];
            if (! in_array($cardCode, $currentSlots, true)) {
                $currentSlots[] = $cardCode;
                $loadout->update(['slots' => array_values($currentSlots)]);
            }

            $boostCode = match ($chestId) {
                'bronze' => 'speed-burst',
                'silver' => 'fortify',
                'gold' => 'meteor-strike',
            };
            $boost = $user->boosts()->firstOrCreate(['code' => $boostCode], ['quantity' => 0]);
            $boost->increment('quantity', match ($chestId) {
                'gold' => 3, 'silver' => 2, default => 1
            });

            return [
                'coins' => $coinsReward,
                'diamonds' => $diamondsReward,
                'card_code' => $cardCode,
                'boost_code' => $boostCode,
                'was_new' => $wasNew,
                'new_level' => $card->level,
            ];
        });

        $user->unsetRelation('towerCards')->unsetRelation('wallet')->unsetRelation('boosts')->unsetRelation('loadout');
        $user->load(['address', 'wallet', 'towerCards', 'inventoryItems', 'boosts', 'loadout']);

        return response()->json([
            'success' => true,
            'data' => [
                'player' => $this->state($user),
                'loot' => $result,
            ],
        ]);
    }

    private function state(User $user): array
    {
        return [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'surname' => $user->surname,
                'email' => $user->email,
                'email_verified' => $user->hasVerifiedEmail(),
                'address' => $user->address,
            ],
            'wallet' => $user->wallet,
            'inventory' => [
                'tower_cards' => $user->towerCards,
                'items' => $user->inventoryItems,
                'boosts' => $user->boosts,
            ],
            'loadout' => $user->loadout,
            'endless_progress' => $user->endlessRuns()->latest('updated_at')->first(),
        ];
    }
}
