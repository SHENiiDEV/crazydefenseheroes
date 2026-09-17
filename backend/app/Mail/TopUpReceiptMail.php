<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TopUpReceiptMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly string $packageCode,
        public readonly int $diamondsGranted,
        public readonly int $coinsGranted,
        public readonly string $priceFormatted,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: config('mail.from.address', 'info@crazydefensehereoes.co.uk'),
            subject: '💎 Treasury Top-Up Confirmation | Crazy Defense Hereoes',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.topup-receipt',
            with: [
                'user' => $this->user,
                'packageCode' => $this->packageCode,
                'diamondsGranted' => $this->diamondsGranted,
                'coinsGranted' => $this->coinsGranted,
                'priceFormatted' => $this->priceFormatted,
                'company' => config('company'),
            ],
        );
    }
}
