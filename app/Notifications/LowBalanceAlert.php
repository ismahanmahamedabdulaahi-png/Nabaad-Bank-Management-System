<?php

namespace App\Notifications;

use App\Models\Account;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LowBalanceAlert extends Notification
{
    use Queueable;

    public function __construct(private readonly Account $account) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Low Balance Alert — ' . $this->account->account_number)
            ->markdown('mail.alert-message', [
                'name'        => $notifiable->name,
                'level'       => 'danger',
                'title'       => 'Low balance alert',
                'message'     => "Your account {$this->account->account_number} has dropped below your alert threshold.",
                'rows'        => [
                    ['label' => 'Current Balance', 'value' => $this->account->currency . ' ' . number_format((float) $this->account->balance, 2)],
                ],
                'extraLines'  => [],
                'url'         => route('customer.accounts.show', $this->account->id),
                'buttonText'  => 'View Account',
                'buttonColor' => 'error',
                'footerNote'  => 'Thank you for banking with NABAAD Bank.',
            ]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'icon'    => 'bi-wallet2',
            'level'   => 'danger',
            'title'   => 'Low balance alert',
            'message' => "Account {$this->account->account_number} has dropped below your alert threshold — balance: " .
                $this->account->currency . ' ' . number_format((float) $this->account->balance, 2) . '.',
            'url'     => route('customer.accounts.show', $this->account->id),
        ];
    }
}
