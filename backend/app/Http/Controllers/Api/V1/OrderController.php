<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\CreateOrderRequest;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class OrderController
{
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'success' => true,
            'data' => [
                'orders' => $user->orders()->with('items')->latest()->limit(50)->get(),
            ],
        ]);
    }

    public function store(CreateOrderRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $data = $request->validated();
        $existing = $user->orders()
            ->where('idempotency_key', $data['idempotency_key'])
            ->with('items')
            ->first();

        if ($existing) {
            return $this->orderResponse($existing, true);
        }

        $product = Product::query()
            ->where('code', $data['product_code'])
            ->where('active', true)
            ->firstOrFail();

        $order = DB::transaction(function () use ($user, $data, $product): Order {
            $quantity = (int) $data['quantity'];

            $order = $user->orders()->create([
                'public_id' => (string) Str::uuid(),
                'status' => 'pending',
                'idempotency_key' => $data['idempotency_key'],
                'total_minor' => $product->price_minor * $quantity,
                'currency' => $product->currency,
            ]);

            $order->items()->create([
                'product_id' => $product->id,
                'product_code' => $product->code,
                'product_name' => $product->name,
                'product_type' => $product->product_type,
                'grant_key' => $product->grant_key,
                'quantity' => $quantity,
                'grant_quantity' => $product->grant_quantity,
                'unit_price_minor' => $product->price_minor,
                'currency' => $product->currency,
                'metadata' => $product->metadata,
            ]);

            return $order->load('items');
        });

        return $this->orderResponse($order, false, 201);
    }

    private function orderResponse(Order $order, bool $idempotent, int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'order' => $order->loadMissing('items'),
                'idempotent' => $idempotent,
                'checkout' => [
                    'provider' => config('payments.provider'),
                    'status' => $order->status,
                ],
            ],
        ], $status);
    }
}
