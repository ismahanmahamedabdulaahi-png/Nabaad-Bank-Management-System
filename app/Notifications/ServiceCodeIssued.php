<?php

namespace App\Notifications;

use App\Models\ServiceRequestCode;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ServiceCodeIssued extends Notification
{
    use Queueable;

    public function __construct(private readonly ServiceRequestCode $code) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $isWithdrawal = $this->code->type === 'withdrawal';
        $verb         = $isWithdrawal ? 'withdraw' : 'deposit';

        return (new MailMessage)
            ->subject('Your NABAAD Bank ' . ucfirst($this->code->type) . ' Code')
            ->markdown('mail.code-message', [
                'name'        => $notifiable->name,
                'intro'       => "Use this code at any NABAAD Bank counter to {$verb} " . $this->code->account->currency . ' ' . number_format((float) $this->code->amount, 2) . " on account {$this->code->account->account_number}.",
                'code'        => $this->code->code,
                'expiryText'  => 'Expires ' . $this->code->expires_at->format('d M Y, H:i'),
                'extraLines'  => $isWithdrawal
                    ? ['The amount will be deducted from your account when you collect the cash at the counter — your balance is unaffected until then.']
                    : [],
                'url'         => null,
                'buttonText'  => null,
                'footerNote'  => "If you didn't request this, please contact NABAAD Bank immediately.",
            ]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'icon'    => $this->code->type === 'withdrawal' ? 'bi-cash-stack' : 'bi-piggy-bank',
            'level'   => 'info',
            'title'   => ucfirst($this->code->type) . ' code issued',
            'message' => "Your {$this->code->type} code {$this->code->code} for " .
                $this->code->account->currency . ' ' . number_format((float) $this->code->amount, 2) .
                ' expires ' . $this->code->expires_at->format('d M Y, H:i') . '.',
            'url' => route('customer.accounts.show', $this->code->account_id),
        ];
    }
}
