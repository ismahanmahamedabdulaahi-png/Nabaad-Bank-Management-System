<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class StaffOtpIssued extends Notification
{
    use Queueable;

    public function __construct(private readonly string $staffId, private readonly string $otp)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your NABAAD Bank Login Credentials')
            ->markdown('mail.credentials', [
                'name'        => $notifiable->name,
                'greeting'    => 'Welcome, ' . $notifiable->name . '!',
                'intro'       => 'Your account is ready. Use these credentials to sign in:',
                'rows'        => [
                    ['label' => 'Staff ID', 'value' => $this->staffId],
                    ['label' => 'Temporary Password', 'value' => $this->otp],
                ],
                'url'         => null,
                'buttonText'  => null,
                'extraLines'  => ['You will be required to set your own password immediately after signing in — this temporary one cannot be used again once you do.'],
                'footerNote'  => "If you didn't request this, please contact NABAAD Bank immediately.",
            ]);
    }
}
