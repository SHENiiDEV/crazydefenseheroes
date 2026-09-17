<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class LogoutController
{
    public function __invoke(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json([
            'success' => true,
            'data' => ['message' => 'Signed out successfully.'],
        ]);
    }
}
