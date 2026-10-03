<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CustomerInvitation extends Notification
{
    use Queueable;

    public function __construct(private readonly string $acceptUrl)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Welcome to NABAAD Bank')
            ->markdown('mail.invitation', [
                'name'        => $notifiable->name,
                'intro'       => "An account has been opened for you at NABAAD Bank. Please confirm you'd like to activate it.",
                'badgeLabel'  => 'Customer Number',
                'badgeValue'  => $notifiable->customer_number,
                'points'      => [
                    'Manage every account, anywhere',
                    'Instant transfers & standing orders',
                    'Two-factor secured sign-in',
                ],
                'url'         => $this->acceptUrl,
                'buttonText'  => 'Accept & Activate Account',
                'expiresIn'   => '7 days',
            ]);
    }
}
