<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CustomerPasswordReset extends Notification
{
    use Queueable;

    public function __construct(private readonly string $tempPassword) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your NABAAD Bank Password Was Reset')
            ->markdown('mail.credentials', [
                'name'        => $notifiable->name,
                'intro'       => 'Your password was reset by NABAAD Bank staff.',
                'rows'        => [
                    ['label' => 'Temporary Password', 'value' => $this->tempPassword],
                ],
                'url'         => route('customer.login'),
                'buttonText'  => 'Log In to Your Account',
                'extraLines'  => ['Please log in and change your password as soon as possible.'],
                'footerNote'  => "If you didn't request this, please contact NABAAD Bank immediately.",
            ]);
    }
}
