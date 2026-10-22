<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\CreateTopUpRequest;
use App\Models\User;
use App\Services\TopUpService;
use Illuminate\Http\JsonResponse;

final class TopUpController
{
    public function show(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => config('topup')]);
    }

    public function store(CreateTopUpRequest $request, TopUpService $topups): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $data = $request->validated();
        $order = $topups->purchase($user, (int) $data['diamonds'], (int) $data['gold'], $data['idempotency_key'], $data['currency'] ?? 'EUR');

        return response()->json(['success' => true, 'data' => [
            'order' => $order->load(['items', 'invoice']),
            'wallet' => $user->wallet()->firstOrFail(),
        ]]);
    }

    public function retired(): JsonResponse
    {
        return response()->json(['success' => false, 'error' => [
            'message' => 'Please use the Treasury tab for custom purchases.',
        ]], 410);
    }
}
