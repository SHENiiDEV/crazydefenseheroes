<?php

namespace App\Services;

use App\Jobs\SendPurchaseReceipt;
use App\Models\Invoice;
use App\Models\Order;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class InvoiceService
{
    public function issueForOrder(Order $order): Invoice
    {
        $order->loadMissing(['items', 'user.address']);
        $invoice = Invoice::query()->where('order_id', $order->id)->first();

        if (! $invoice) {
            $invoice = Invoice::create([
                'order_id' => $order->id,
                'user_id' => $order->user_id,
                'invoice_number' => 'pending-'.Str::uuid(),
                'subtotal_minor' => $order->total_minor,
                'tax_minor' => 0,
                'total_minor' => $order->total_minor,
                'currency' => $order->currency,
                'issued_at' => now(),
            ]);
            $invoice->update([
                'invoice_number' => config('invoice.number_prefix')
                    .now()->format('Y')
                    .'-'
                    .str_pad((string) $invoice->id, 6, '0', STR_PAD_LEFT),
            ]);
        }

        if (! $invoice->pdf_path || ! Storage::disk(config('invoice.storage_disk'))->exists($invoice->pdf_path)) {
            $path = 'invoices/'.$invoice->invoice_number.'.pdf';
            $pdf = Pdf::loadView('invoices.pdf', [
                'invoice' => $invoice->fresh()->load(['order.items', 'user.address']),
                'company' => config('company'),
            ])->setPaper('a4');

            Storage::disk(config('invoice.storage_disk'))->put($path, $pdf->output());
            $invoice->update(['pdf_path' => $path]);
        }

        if (! $invoice->sent_at) {
            SendPurchaseReceipt::dispatch($invoice->getKey());
        }

        return $invoice->fresh();
    }
}
