<?php

namespace App\Notifications;

use App\Models\Transaction;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TransactionRejected extends Notification
{
    use Queueable;

    public function __construct(private readonly Transaction $transaction, private readonly string $reason) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $txn  = $this->transaction;
        $verb = match ($txn->type) {
            'deposit'    => 'Deposit',
            'withdrawal' => 'Withdrawal',
            'transfer'   => 'Transfer',
            default      => ucfirst($txn->type),
        };

        return (new MailMessage)
            ->subject("Transaction Rejected — {$verb}")
            ->markdown('mail.transaction-receipt', [
                'customerName' => $notifiable->name,
                'intro'        => "Your {$verb} request has been rejected and was not processed.",
                'verb'         => $verb,
                'isCredit'     => false,
                'currency'     => $txn->currency,
                'amount'       => number_format((float) $txn->amount, 2),
                'statusLabel'  => 'Rejected',
                'statusVariant'=> 'rejected',
                'reference'    => $txn->reference,
                'account'      => $txn->account->account_number,
                'balance'      => null,
                'date'         => now()->format('d M Y, H:i'),
                'reason'       => $this->reason !== '' ? $this->reason : null,
                'url'          => route('customer.accounts.show', $txn->account_id),
                'footerNote'   => 'Please visit your nearest NABAAD Bank branch for more information.',
            ]);
    }
}
