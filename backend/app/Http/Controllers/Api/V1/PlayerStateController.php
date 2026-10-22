<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\UpdateLoadoutRequest;
use App\Models\User;
use App\Services\GameEconomyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

    public function upgradeTower(Request $request, GameEconomyService $economy): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'in:ember-archer,stone-warden,vault-mage'],
            'currency' => ['sometimes', 'required', 'in:gold,diamonds'],
        ]);
        $economy->upgrade($request->user(), $data['code'], $data['currency'] ?? 'gold');

        return $this->show($request);
    }

    public function openChest(Request $request, GameEconomyService $economy): JsonResponse
    {
        $data = $request->validate(['chest_id' => ['required', 'string', 'in:bronze,silver,gold']]);
        $loot = $economy->openChest($request->user(), $data['chest_id']);
        $user = $request->user()->fresh()->load(['address', 'wallet', 'towerCards', 'inventoryItems', 'boosts', 'loadout']);

        return response()->json(['success' => true, 'data' => ['player' => $this->state($user), 'loot' => $loot]]);
    }

    public function useBoost(Request $request, GameEconomyService $economy): JsonResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'in:speed-burst,fortify,meteor-strike']]);
        $economy->useBoost($request->user(), $data['code']);

        return $this->show($request);
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
