<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RegistrationReceived extends Notification
{
    use Queueable;

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('We Received Your NABAAD Bank Registration')
            ->markdown('mail.credentials', [
                'name'        => $notifiable->name,
                'intro'       => 'Thank you for registering with NABAAD Bank. Your application is pending review.',
                'rows'        => [
                    ['label' => 'Customer Number', 'value' => $notifiable->customer_number],
                ],
                'url'         => null,
                'buttonText'  => null,
                'extraLines'  => ['We will notify you by email once your account is approved.'],
                'footerNote'  => 'Thank you for choosing NABAAD Bank.',
            ]);
    }
}
