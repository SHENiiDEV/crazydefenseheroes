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
    public function purchase(User $user, int $diamonds, int $gold, string $idempotencyKey, string $currency = 'EUR'): Order
    {
        $isCorefy = config('payments.provider') === 'corefy';
        abort_unless($isCorefy || config('topup.manual_enabled'), 403, 'Purchases are currently disabled.');
        if ($isCorefy) {
            abort_unless($currency === 'EUR', 422, 'Sterling Pay currently supports EUR only.');
            app(CorefyPaymentService::class)->assertConfigured();
        }

        if ($diamonds + $gold === 0) {
            throw ValidationException::withMessages(['diamonds' => ['Choose at least one diamond or gold coin.']]);
        }

        $rates = config('topup.currencies.'.$currency);
        if (! is_array($rates)) {
            throw ValidationException::withMessages(['currency' => ['Choose EUR, GBP or USD.']]);
        }

        $order = DB::transaction(function () use ($user, $diamonds, $gold, $idempotencyKey, $currency, $rates, $isCorefy): Order {
            // Serialize requests from the same player, including duplicate clicks and retries.
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $existing = $user->orders()->with('items')->where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                abort_unless(($existing->provider === 'corefy') === $isCorefy, 422, 'This reference belongs to a different checkout mode.');
                $amounts = $existing->items->pluck('grant_quantity', 'product_code');
                if ((int) ($amounts['custom-diamonds'] ?? 0) !== $diamonds || (int) ($amounts['custom-gold'] ?? 0) !== $gold || $existing->currency !== $currency) {
                    throw ValidationException::withMessages(['idempotency_key' => ['This purchase reference was already used for a different amount.']]);
                }

                return $existing;
            }

            $order = $user->orders()->create([
                'public_id' => (string) Str::uuid(),
                'status' => 'pending',
                'idempotency_key' => $idempotencyKey,
                'total_minor' => 0,
                'currency' => $currency,
                'provider' => $isCorefy ? 'corefy' : 'manual',
                'payment_test_mode' => $isCorefy ? config('corefy.test_mode') : null,
                'digital_content_consent_at' => $isCorefy ? now() : null,
                'purchase_terms_version' => $isCorefy ? config('legal.version') : null,
            ]);

            foreach ([['diamonds', $diamonds, 'diamonds_per_unit', 'Diamonds'], ['gold', $gold, 'gold_per_unit', 'Gold coins']] as [$code, $amount, $rate, $label]) {
                if ($amount === 0) {
                    continue;
                }

                $unitsPerUnit = (int) $rates[$rate];
                $price = intdiv($amount * 100 + $unitsPerUnit - 1, $unitsPerUnit);
                $product = Product::firstOrCreate(['code' => 'custom-'.$code], [
                    'name' => 'Custom '.$label,
                    'product_type' => $code === 'gold' ? 'soft_currency' : 'diamonds',
                    'grant_quantity' => 1,
                    'price_minor' => 1,
                    'currency' => $currency,
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
                    'currency' => $currency,
                    'metadata' => ['units_per_currency_unit' => $unitsPerUnit, 'manual' => ! $isCorefy],
                ]);
            }

            $order->update(['total_minor' => $order->items()->sum('unit_price_minor')]);

            return $order;
        });

        return $isCorefy
            ? app(CorefyPaymentService::class)->checkout($order)
            : app(PaymentService::class)->markPaid($order, 'manual', 'manual-'.$order->public_id);
    }
}
