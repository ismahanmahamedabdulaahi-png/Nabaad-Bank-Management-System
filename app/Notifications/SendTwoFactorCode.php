<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SendTwoFactorCode extends Notification
{
    use Queueable;

    public function __construct(private readonly string $code) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your NABAAD Bank Verification Code')
            ->markdown('mail.code-message', [
                'name'        => $notifiable->name,
                'intro'       => 'Use the code below to complete your sign-in.',
                'code'        => $this->code,
                'expiryText'  => 'Expires in 10 minutes',
                'extraLines'  => [],
                'url'         => null,
                'buttonText'  => null,
                'footerNote'  => "If you didn't try to sign in, please contact NABAAD Bank immediately.",
            ]);
    }
}
