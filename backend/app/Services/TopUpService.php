<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TopUpService
{
    public function purchase(User $user, int $diamonds, int $gold, string $idempotencyKey): Order
    {
        abort_unless(config('topup.manual_enabled'), 403, 'Manual purchases are currently disabled.');

        if ($diamonds + $gold === 0) {
            throw ValidationException::withMessages(['diamonds' => ['Choose at least one diamond or gold coin.']]);
        }

        $order = DB::transaction(function () use ($user, $diamonds, $gold, $idempotencyKey): Order {
            // Serialize requests from the same player, including duplicate clicks and retries.
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $existing = $user->orders()->with('items')->where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                $amounts = $existing->items->pluck('grant_quantity', 'product_code');
                if ((int) ($amounts['custom-diamonds'] ?? 0) !== $diamonds || (int) ($amounts['custom-gold'] ?? 0) !== $gold) {
                    throw ValidationException::withMessages(['idempotency_key' => ['This purchase reference was already used for a different amount.']]);
                }

                return $existing;
            }

            $order = $user->orders()->create([
                'public_id' => (string) Str::uuid(),
                'status' => 'pending',
                'idempotency_key' => $idempotencyKey,
                'total_minor' => 0,
                'currency' => config('topup.currency'),
            ]);

            foreach ([['diamonds', $diamonds, 'diamonds_per_euro', 'Diamonds'], ['gold', $gold, 'gold_per_euro', 'Gold coins']] as [$code, $amount, $rate, $label]) {
                if ($amount === 0) {
                    continue;
                }

                $unitsPerEuro = (int) config('topup.'.$rate);
                $price = intdiv($amount * 100 + $unitsPerEuro - 1, $unitsPerEuro);
                $product = Product::firstOrCreate(['code' => 'custom-'.$code], [
                    'name' => 'Custom '.$label,
                    'product_type' => $code === 'gold' ? 'soft_currency' : 'diamonds',
                    'grant_quantity' => 1,
                    'price_minor' => 1,
                    'currency' => config('topup.currency'),
                    'active' => false,
                ]);
                $order->items()->create([
                    'product_id' => $product->id,
                    'product_code' => $product->code,
                    'product_name' => number_format($amount, 0, '.', ',').' '.$label,
                    'product_type' => $product->product_type,
                    'quantity' => 1,
                    'grant_quantity' => $amount,
                    'unit_price_minor' => $price,
                    'currency' => config('topup.currency'),
                    'metadata' => ['units_per_euro' => $unitsPerEuro, 'manual' => true],
                ]);
            }

            $order->update(['total_minor' => $order->items()->sum('unit_price_minor')]);

            return $order;
        });

        return app(PaymentService::class)->markPaid($order, 'manual', 'manual-'.$order->public_id);
    }
}
