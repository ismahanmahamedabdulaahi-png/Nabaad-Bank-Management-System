<?php

namespace App\Notifications;

use App\Models\Budget;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BudgetThresholdReached extends Notification
{
    use Queueable;

    public function __construct(private readonly Budget $budget, private readonly float $percentUsed) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Budget Alert — ' . $this->budget->name)
            ->markdown('mail.alert-message', [
                'name'        => $notifiable->name,
                'level'       => 'warning',
                'title'       => 'Budget threshold reached',
                'message'     => "Your budget \"{$this->budget->name}\" has used {$this->percentUsed}% of its limit for this period.",
                'rows'        => [
                    ['label' => 'Limit', 'value' => number_format((float) $this->budget->limit_amount, 2)],
                    ['label' => 'Used',  'value' => $this->percentUsed . '%'],
                ],
                'extraLines'  => [],
                'url'         => route('customer.budgets.index'),
                'buttonText'  => 'View Budgets',
                'buttonColor' => 'primary',
                'footerNote'  => 'Thank you for banking with NABAAD Bank.',
            ]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'icon'    => 'bi-piggy-bank',
            'level'   => 'warning',
            'title'   => 'Budget threshold reached',
            'message' => "\"{$this->budget->name}\" has used {$this->percentUsed}% of its " .
                number_format((float) $this->budget->limit_amount, 2) . ' limit this period.',
            'url'     => route('customer.budgets.index'),
        ];
    }
}
