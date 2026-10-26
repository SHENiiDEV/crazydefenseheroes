<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\CorefyPaymentService;
use Illuminate\Console\Command;

class ReconcileCorefyPayment extends Command
{
    protected $signature = 'cdh:reconcile-payment {reference} {--invoice= : Provider invoice ID when creation timed out}';

    protected $description = 'Verify a Sterling Pay invoice with the provider and reconcile one order.';

    public function handle(CorefyPaymentService $payments): int
    {
        $order = Order::query()->where('public_id', $this->argument('reference'))->where('provider', 'corefy')->first();
        if (! $order) {
            $this->error('Order not found.');

            return self::FAILURE;
        }
        try {
            $order = $payments->synchronise($order, $this->option('invoice'));
        } catch (\Throwable) {
            $this->error('Reconciliation failed. Verify the reference, invoice, amount and payment mode. No new checkout was created.');

            return self::FAILURE;
        }
        $this->info($order->public_id.': '.$order->status);

        return self::SUCCESS;
    }
}
