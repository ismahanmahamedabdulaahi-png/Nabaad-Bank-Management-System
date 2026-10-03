<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Budget;
use App\Notifications\BudgetExceeded;
use App\Notifications\BudgetThresholdReached;
use App\Notifications\LowBalanceAlert;

class AlertService
{
    /**
     * Re-evaluate low-balance and budget alerts for an account after
     * one of its transactions completes.
     */
    public function evaluateAccount(Account $account): void
    {
        $this->checkLowBalance($account);
        $this->checkBudgets($account);
    }

    private function checkLowBalance(Account $account): void
    {
        if ($account->low_balance_alert_threshold === null) return;

        $balance   = (float) $account->balance;
        $threshold = (float) $account->low_balance_alert_threshold;

        if ($balance < $threshold && !$account->low_balance_alerted) {
            $account->customer?->notify(new LowBalanceAlert($account));
            $account->update(['low_balance_alerted' => true]);
        } elseif ($balance >= $threshold && $account->low_balance_alerted) {
            // Balance recovered — re-arm the alert for the next dip
            $account->update(['low_balance_alerted' => false]);
        }
    }

    private function checkBudgets(Account $account): void
    {
        $budgets = Budget::where('is_active', true)
            ->where('customer_id', $account->customer_id)
            ->where(fn ($q) => $q->whereNull('account_id')->orWhere('account_id', $account->id))
            ->get();

        foreach ($budgets as $budget) {
            $this->evaluateBudget($budget);
        }
    }

    public function evaluateBudget(Budget $budget): void
    {
        $spent        = $budget->spentThisPeriod();
        $percent      = $budget->percentUsed();
        $limit        = (float) $budget->limit_amount;
        $alertedSoFar = $budget->currentPeriodAlertPercent();

        if ($spent >= $limit && $alertedSoFar < 100) {
            $budget->customer?->notify(new BudgetExceeded($budget, $spent));
            $budget->update(['last_alert_percent' => 100, 'last_alert_period_start' => $budget->periodStart()]);
            return;
        }

        if ($percent >= $budget->threshold_percent
            && $percent < 100
            && $alertedSoFar < $budget->threshold_percent) {
            $budget->customer?->notify(new BudgetThresholdReached($budget, $percent));
            $budget->update(['last_alert_percent' => $budget->threshold_percent, 'last_alert_period_start' => $budget->periodStart()]);
        }
    }
}
