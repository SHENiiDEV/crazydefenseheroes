<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;

final class CountriesController
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'countries' => config('registration.allowed_countries'),
                'excluded_countries' => config('registration.excluded_countries'),
            ],
        ]);
    }
}
