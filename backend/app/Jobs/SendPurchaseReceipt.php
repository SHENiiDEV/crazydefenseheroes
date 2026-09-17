<?php

namespace App\Jobs;

use App\Mail\PurchaseReceipt;
use App\Models\Invoice;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

class SendPurchaseReceipt implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public readonly int $invoiceId) {}

    public function uniqueId(): string
    {
        return (string) $this->invoiceId;
    }

    public function handle(): void
    {
        $invoice = Invoice::query()->with(['order.items', 'user.address'])->findOrFail($this->invoiceId);

        if ($invoice->sent_at) {
            return;
        }

        Mail::to($invoice->user->email)->send(new PurchaseReceipt($invoice));
        $invoice->update(['sent_at' => now()]);
    }
}
