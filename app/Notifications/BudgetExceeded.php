<?php

namespace App\Notifications;

use App\Models\Budget;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BudgetExceeded extends Notification
{
    use Queueable;

    public function __construct(private readonly Budget $budget, private readonly float $spent) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Budget Exceeded — ' . $this->budget->name)
            ->markdown('mail.alert-message', [
                'name'        => $notifiable->name,
                'level'       => 'danger',
                'title'       => 'Budget exceeded',
                'message'     => "Your budget \"{$this->budget->name}\" has gone over its limit this period.",
                'rows'        => [
                    ['label' => 'Limit',   'value' => number_format((float) $this->budget->limit_amount, 2)],
                    ['label' => 'Spent',   'value' => number_format($this->spent, 2)],
                ],
                'extraLines'  => [],
                'url'         => route('customer.budgets.index'),
                'buttonText'  => 'View Budgets',
                'buttonColor' => 'error',
                'footerNote'  => 'Thank you for banking with NABAAD Bank.',
            ]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'icon'    => 'bi-exclamation-octagon',
            'level'   => 'danger',
            'title'   => 'Budget exceeded',
            'message' => "\"{$this->budget->name}\" has gone over its " .
                number_format((float) $this->budget->limit_amount, 2) . ' limit — spent ' .
                number_format($this->spent, 2) . ' so far this period.',
            'url'     => route('customer.budgets.index'),
        ];
    }
}
