<?php

namespace App\Mail;

use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class PurchaseReceipt extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Invoice $invoice)
    {
        $this->invoice->loadMissing(['order.items', 'user']);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Crazy Defense Hereoes receipt '.$this->invoice->invoice_number,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.purchase-receipt',
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        $disk = (string) config('invoice.storage_disk');

        return [
            Attachment::fromData(
                fn (): string => (string) Storage::disk($disk)->get($this->invoice->pdf_path),
                'invoice-'.$this->invoice->invoice_number.'.pdf',
            )->withMime('application/pdf'),
        ];
    }
}
