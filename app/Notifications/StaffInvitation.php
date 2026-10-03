<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class StaffInvitation extends Notification
{
    use Queueable;

    public function __construct(private readonly string $roleName, private readonly string $acceptUrl)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('You\'ve Been Invited to NABAAD Bank')
            ->markdown('mail.invitation', [
                'name'        => $notifiable->name,
                'intro'       => "You've been selected to join NABAAD Bank's staff system. Do you accept this position?",
                'badgeLabel'  => 'Role',
                'badgeValue'  => $this->roleName,
                'points'      => [
                    'Two-factor protected sessions',
                    'Maker-checker approval controls',
                    'Full audit trail on every action',
                ],
                'url'         => $this->acceptUrl,
                'buttonText'  => 'Accept Invitation',
                'expiresIn'   => '7 days',
            ]);
    }
}
