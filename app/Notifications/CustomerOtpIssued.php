<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CustomerOtpIssued extends Notification
{
    use Queueable;

    public function __construct(private readonly string $otp)
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
                'greeting'    => 'Thanks for confirming, ' . $notifiable->name . '!',
                'intro'       => 'Use these credentials to sign in once your account is fully active:',
                'rows'        => [
                    ['label' => 'Email', 'value' => $notifiable->email],
                    ['label' => 'Temporary Password', 'value' => $this->otp],
                ],
                'url'         => route('customer.login'),
                'buttonText'  => 'Log In to Your Account',
                'extraLines'  => ['You will be required to set your own password immediately after signing in — this temporary one cannot be used again once you do.'],
                'footerNote'  => "If you didn't request this, please contact NABAAD Bank immediately.",
            ]);
    }
}
