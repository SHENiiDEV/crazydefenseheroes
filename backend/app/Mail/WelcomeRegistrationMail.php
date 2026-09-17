<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WelcomeRegistrationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly User $user)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: config('mail.from.address', 'info@crazydefenseheroes.co.uk'),
            subject: '⚔️ Welcome to the Watch, '.$this->user->name.'! | Crazy Defense Heroes',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.welcome-registration',
            with: [
                'user' => $this->user,
                'company' => config('company'),
            ],
        );
    }
}
