<?php

namespace App\Observers;

use App\Models\Transaction;
use App\Notifications\TransactionCompleted;
use App\Services\AlertService;

class TransactionObserver
{
    public function __construct(private readonly AlertService $alerts) {}

    public function created(Transaction $transaction): void
    {
        if ($transaction->status === 'completed') {
            $this->evaluate($transaction);
        }
    }

    public function updated(Transaction $transaction): void
    {
        if ($transaction->isDirty('status') && $transaction->status === 'completed') {
            $this->evaluate($transaction);
        }
    }

    private function evaluate(Transaction $transaction): void
    {
        // Transfers create one Transaction row per side (debit leg + credit leg),
        // each already carrying its own account_id — no need to also walk relatedAccount here.
        if ($transaction->account) {
            $this->alerts->evaluateAccount($transaction->account);
            $transaction->account->customer?->notify(new TransactionCompleted($transaction));
        }
    }
}
