<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CustomerWelcome extends Notification
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
            ->subject('Welcome to NABAAD Bank')
            ->markdown('mail.credentials', [
                'name'        => $notifiable->name,
                'intro'       => 'An account has been opened for you at NABAAD Bank.',
                'rows'        => [
                    ['label' => 'Customer Number',    'value' => $notifiable->customer_number],
                    ['label' => 'Temporary Password', 'value' => $this->tempPassword],
                ],
                'url'         => route('customer.login'),
                'buttonText'  => 'Log In to Your Account',
                'extraLines'  => ['Please log in and change your password as soon as possible.'],
                'footerNote'  => 'Thank you for banking with NABAAD Bank.',
            ]);
    }
}
