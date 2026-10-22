<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;

final class GameRulesController
{
    public function __invoke(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => config('game')]);
    }
}
