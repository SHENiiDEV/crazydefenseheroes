<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Product;
use Illuminate\Http\JsonResponse;

final class CatalogController
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'products' => Product::query()
                    ->where('active', true)
                    ->orderBy('price_minor')
                    ->get(),
            ],
        ]);
    }
}
