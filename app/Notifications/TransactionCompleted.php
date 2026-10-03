<?php

namespace App\Notifications;

use App\Models\Transaction;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TransactionCompleted extends Notification
{
    use Queueable;

    public function __construct(private readonly Transaction $transaction) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $txn      = $this->transaction;
        $isCredit = (float) $txn->balance_after >= (float) $txn->balance_before;
        $verb     = match ($txn->type) {
            'deposit'         => 'Deposit',
            'withdrawal'      => 'Withdrawal',
            'transfer'        => $isCredit ? 'Transfer In' : 'Transfer Out',
            'reversal'        => 'Reversal',
            'loan_disbursement' => 'Loan Disbursement',
            'loan_repayment'  => 'Loan Repayment',
            default           => ucfirst($txn->type),
        };

        return (new MailMessage)
            ->subject("Transaction Receipt — {$verb}")
            ->markdown('mail.transaction-receipt', [
                'customerName' => $notifiable->name,
                'intro'        => "A {$verb} was just completed on your account.",
                'verb'         => $verb,
                'isCredit'     => $isCredit,
                'currency'     => $txn->currency,
                'amount'       => number_format((float) $txn->amount, 2),
                'statusLabel'  => null,
                'statusVariant'=> null,
                'reference'    => $txn->reference,
                'account'      => $txn->account->account_number,
                'balance'      => number_format((float) $txn->balance_after, 2),
                'date'         => $txn->completed_at?->format('d M Y, H:i'),
                'reason'       => null,
                'url'          => route('customer.accounts.show', $txn->account_id),
                'footerNote'   => "If you didn't recognize this transaction, please contact NABAAD Bank immediately.",
            ]);
    }
}
