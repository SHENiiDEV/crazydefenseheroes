<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class PaymentService
{
    public function markPaid(Order $order, string $provider, string $paymentId): Order
    {
        $paidOrder = DB::transaction(function () use ($order, $provider, $paymentId): Order {
            $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($lockedOrder->status === 'paid') {
                return $lockedOrder->load('items');
            }

            $lockedOrder->update([
                'status' => 'paid',
                'provider' => $provider,
                'provider_payment_id' => $paymentId,
                'paid_at' => Carbon::now(),
            ]);

            $this->fulfill($lockedOrder->load('items'));

            return $lockedOrder->fresh()->load('items');
        });

        app(InvoiceService::class)->issueForOrder($paidOrder);

        return $paidOrder->fresh()->load('items');
    }

    private function fulfill(Order $order): void
    {
        $wallet = Wallet::query()
            ->where('user_id', $order->user_id)
            ->lockForUpdate()
            ->first();

        if (! $wallet) {
            $wallet = Wallet::create(['user_id' => $order->user_id]);
        }

        $diamonds = 0;
        $softCurrency = 0;

        foreach ($order->items as $item) {
            $amount = $item->grant_quantity * $item->quantity;

            if ($item->product_type === 'diamonds') {
                $diamonds += $amount;
            }

            if ($item->product_type === 'soft_currency') {
                $softCurrency += $amount;
            }
        }

        $transaction = WalletTransaction::firstOrCreate(
            ['reference' => 'order:'.$order->id],
            [
                'user_id' => $order->user_id,
                'wallet_id' => $wallet->id,
                'order_id' => $order->id,
                'type' => 'purchase',
                'diamonds_delta' => $diamonds,
                'soft_currency_delta' => $softCurrency,
                'metadata' => ['public_order_id' => $order->public_id],
            ],
        );

        if (! $transaction->wasRecentlyCreated) {
            return;
        }

        if ($diamonds > 0 || $softCurrency > 0) {
            $wallet->increment('diamonds', $diamonds);
            $wallet->increment('soft_currency', $softCurrency);
        }

        foreach ($order->items as $item) {
            $amount = $item->grant_quantity * $item->quantity;

            if ($item->product_type === 'boost' && $item->grant_key) {
                $boost = $order->user->boosts()->firstOrCreate(
                    ['code' => $item->grant_key],
                    ['quantity' => 0],
                );
                $boost->increment('quantity', $amount);
            }

            if ($item->product_type === 'tower_card' && $item->grant_key) {
                $card = $order->user->towerCards()->firstOrCreate(
                    ['code' => $item->grant_key],
                    ['level' => 1, 'quantity' => 0, 'is_equipped' => false],
                );
                $card->increment('quantity', $amount);
            }

            if ($item->product_type === 'item' && $item->grant_key) {
                $inventoryItem = $order->user->inventoryItems()->firstOrCreate(
                    ['item_type' => 'purchase', 'item_key' => $item->grant_key],
                    ['quantity' => 0, 'metadata' => $item->metadata],
                );
                $inventoryItem->increment('quantity', $amount);
            }
        }
    }
}
