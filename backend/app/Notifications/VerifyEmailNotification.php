<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class VerifyEmailNotification extends Notification
{
    use Queueable;

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $verificationUrl = URL::temporarySignedRoute(
            'api.v1.email.verify',
            now()->addHours(24),
            [
                'id' => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ],
        );

        return (new MailMessage)
            ->subject('Verify your Crazy Defense Heroes account')
            ->greeting('Welcome to the watch, '.$notifiable->name.'!')
            ->line('Verify your email address to protect your account and save your game progress.')
            ->action('Verify email address', $verificationUrl)
            ->line('This link expires in 24 hours.');
    }
}
