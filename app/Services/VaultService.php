<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\BusinessDay;
use App\Models\TellerTill;
use App\Models\TillCashMovement;
use App\Models\Vault;
use App\Models\VaultTransaction;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VaultService
{
    public function __construct(private GeneralLedgerService $gl)
    {
    }

    // ── Vault Access ──────────────────────────────────────────────────────────

    public function getVault(): Vault
    {
        return Vault::firstOrFail();
    }

    // ── Open Vault ────────────────────────────────────────────────────────────

    public function open(?string $notes): Vault
    {
        $this->requireBusinessDay();

        return DB::transaction(function () use ($notes) {
            $vault = Vault::lockForUpdate()->firstOrFail();

            if ($vault->isOpen()) {
                throw ValidationException::withMessages(['vault' => 'Vault is already open.']);
            }

            $vault->update([
                'status'    => 'open',
                'opened_by' => Auth::id(),
                'opened_at' => now(),
                'closed_by' => null,
                'closed_at' => null,
                'last_updated_by' => Auth::id(),
            ]);

            AuditLog::record('vault.open', 'vault',
                'Vault opened. Balance: USD ' . number_format($vault->balance, 2), [], [
                    'vault_id' => $vault->id,
                ]);

            return $vault->fresh();
        });
    }

    // ── Close Vault ───────────────────────────────────────────────────────────

    public function close(?string $notes): Vault
    {
        return DB::transaction(function () use ($notes) {
            $vault = Vault::lockForUpdate()->firstOrFail();

            if ($vault->isClosed()) {
                throw ValidationException::withMessages(['vault' => 'Vault is already closed.']);
            }

            // All tills must be closed before vault can close
            $openTills = TellerTill::where('business_date', today())->where('status', 'open')->count();
            if ($openTills > 0) {
                throw ValidationException::withMessages([
                    'vault' => "Cannot close vault: {$openTills} till(s) still open. Close all tills first.",
                ]);
            }

            $vault->update([
                'status'          => 'closed',
                'closed_by'       => Auth::id(),
                'closed_at'       => now(),
                'last_updated_by' => Auth::id(),
            ]);

            AuditLog::record('vault.close', 'vault',
                'Vault closed. Final balance: USD ' . number_format($vault->balance, 2), [], [
                    'vault_id' => $vault->id,
                ]);

            return $vault->fresh();
        });
    }

    // ── Cash In (external deposit into vault) ─────────────────────────────────

    public function cashIn(float $amount, ?string $notes): VaultTransaction
    {
        $this->requireBusinessDay();
        $this->requireVaultOpen();

        return DB::transaction(function () use ($amount, $notes) {
            $vault  = Vault::lockForUpdate()->firstOrFail();
            $before = (float) $vault->balance;
            $after  = $before + $amount;

            $vault->update(['balance' => $after, 'last_updated_by' => Auth::id()]);

            $txn = VaultTransaction::create([
                'vault_id'       => $vault->id,
                'type'           => 'cash_in',
                'amount'         => $amount,
                'balance_before' => $before,
                'balance_after'  => $after,
                'reference'      => $this->generateReference(),
                'notes'          => $notes,
                'processed_by'   => Auth::id(),
            ]);

            AuditLog::record('vault.cash_in', 'vault_transactions',
                "Vault cash-in: USD {$amount}. Balance {$before} → {$after}", [], [
                    'vault_id' => $vault->id, 'amount' => $amount,
                ]);

            $this->gl->post([
                ['account' => $this->gl->accountByCode('1000'), 'debit'  => $amount],
                ['account' => $this->gl->accountByCode('2200'), 'credit' => $amount],
            ], "Vault cash-in ({$txn->reference})", 'vault_transaction', $txn->id);

            return $txn;
        });
    }

    // ── Cash Out (external withdrawal from vault) ─────────────────────────────

    public function cashOut(float $amount, ?string $notes): VaultTransaction
    {
        $this->requireBusinessDay();
        $this->requireVaultOpen();

        return DB::transaction(function () use ($amount, $notes) {
            $vault  = Vault::lockForUpdate()->firstOrFail();
            $before = (float) $vault->balance;

            if ($amount > $before) {
                throw ValidationException::withMessages([
                    'amount' => 'Insufficient vault balance. Available: USD ' . number_format($before, 2),
                ]);
            }

            $after = $before - $amount;
            $vault->update(['balance' => $after, 'last_updated_by' => Auth::id()]);

            $txn = VaultTransaction::create([
                'vault_id'       => $vault->id,
                'type'           => 'cash_out',
                'amount'         => $amount,
                'balance_before' => $before,
                'balance_after'  => $after,
                'reference'      => $this->generateReference(),
                'notes'          => $notes,
                'processed_by'   => Auth::id(),
            ]);

            AuditLog::record('vault.cash_out', 'vault_transactions',
                "Vault cash-out: USD {$amount}. Balance {$before} → {$after}", [], [
                    'vault_id' => $vault->id, 'amount' => $amount,
                ]);

            $this->gl->post([
                ['account' => $this->gl->accountByCode('2200'), 'debit'  => $amount],
                ['account' => $this->gl->accountByCode('1000'), 'credit' => $amount],
            ], "Vault cash-out ({$txn->reference})", 'vault_transaction', $txn->id);

            return $txn;
        });
    }

    // ── Transfer to Teller (vault debited, till credited) ─────────────────────

    public function transferToTeller(TellerTill $till, float $amount, ?string $notes): VaultTransaction
    {
        $this->requireBusinessDay();
        $this->requireVaultOpen();

        if (!$till->isOpen()) {
            throw ValidationException::withMessages(['till' => 'Till must be open to receive cash.']);
        }

        return DB::transaction(function () use ($till, $amount, $notes) {
            $vault  = Vault::lockForUpdate()->firstOrFail();
            $before = (float) $vault->balance;

            if ($amount > $before) {
                throw ValidationException::withMessages([
                    'amount' => 'Insufficient vault balance. Available: USD ' . number_format($before, 2),
                ]);
            }

            $after = $before - $amount;
            $vault->update(['balance' => $after, 'last_updated_by' => Auth::id()]);

            $till->increment('current_balance', $amount);

            TillCashMovement::create([
                'till_id'      => $till->id,
                'type'         => 'replenishment',
                'amount'       => $amount,
                'notes'        => $notes,
                'processed_by' => Auth::id(),
            ]);

            $txn = VaultTransaction::create([
                'vault_id'       => $vault->id,
                'type'           => 'transfer_to_teller',
                'amount'         => $amount,
                'balance_before' => $before,
                'balance_after'  => $after,
                'teller_till_id' => $till->id,
                'reference'      => $this->generateReference(),
                'notes'          => $notes,
                'processed_by'   => Auth::id(),
            ]);

            AuditLog::record('vault.transfer_to_teller', 'vault_transactions',
                "Vault → Till '{$till->till_name}': USD {$amount}. Vault {$before} → {$after}", [], [
                    'vault_id' => $vault->id, 'till_id' => $till->id, 'amount' => $amount,
                ]);

            $this->gl->post([
                ['account' => $this->gl->accountByCode('1010'), 'debit'  => $amount, 'memo' => "Till: {$till->till_name}"],
                ['account' => $this->gl->accountByCode('1000'), 'credit' => $amount],
            ], "Vault → Till '{$till->till_name}' ({$txn->reference})", 'vault_transaction', $txn->id);

            return $txn;
        });
    }

    // ── Record Till Opening (vault debited, till balance already set) ──────────
    // Called when opening a till with an opening balance. Does NOT increment
    // till balance (openTill() already set it). Only records the vault debit.

    public function recordTillOpening(TellerTill $till, float $amount, ?string $notes): VaultTransaction
    {
        $this->requireBusinessDay();
        $this->requireVaultOpen();

        return DB::transaction(function () use ($till, $amount, $notes) {
            $vault  = Vault::lockForUpdate()->firstOrFail();
            $before = (float) $vault->balance;

            if ($amount > $before) {
                throw ValidationException::withMessages([
                    'amount' => 'Insufficient vault balance for till opening. Available: USD ' . number_format($before, 2),
                ]);
            }

            $after = $before - $amount;
            $vault->update(['balance' => $after, 'last_updated_by' => Auth::id()]);

            $txn = VaultTransaction::create([
                'vault_id'       => $vault->id,
                'type'           => 'transfer_to_teller',
                'amount'         => $amount,
                'balance_before' => $before,
                'balance_after'  => $after,
                'teller_till_id' => $till->id,
                'reference'      => $this->generateReference(),
                'notes'          => $notes ?? "Opening cash for till {$till->till_name}",
                'processed_by'   => Auth::id(),
            ]);

            AuditLog::record('vault.till_opening', 'vault_transactions',
                "Vault → Till '{$till->till_name}' (opening): USD {$amount}. Vault {$before} → {$after}", [], [
                    'vault_id' => $vault->id, 'till_id' => $till->id, 'amount' => $amount,
                ]);

            $this->gl->post([
                ['account' => $this->gl->accountByCode('1010'), 'debit'  => $amount, 'memo' => "Till: {$till->till_name}"],
                ['account' => $this->gl->accountByCode('1000'), 'credit' => $amount],
            ], "Vault → Till '{$till->till_name}' opening cash ({$txn->reference})", 'vault_transaction', $txn->id);

            return $txn;
        });
    }

    // ── Receive from Teller (till debited, vault credited) ───────────────────

    public function receiveFromTeller(TellerTill $till, float $amount, ?string $notes): VaultTransaction
    {
        $this->requireBusinessDay();
        $this->requireVaultOpen();

        if (!$till->isOpen()) {
            throw ValidationException::withMessages(['till' => 'Till must be open to return cash.']);
        }

        if ($amount > (float) $till->current_balance) {
            throw ValidationException::withMessages([
                'amount' => 'Return amount exceeds current till balance (USD ' . number_format($till->current_balance, 2) . ').',
            ]);
        }

        return DB::transaction(function () use ($till, $amount, $notes) {
            $vault  = Vault::lockForUpdate()->firstOrFail();
            $before = (float) $vault->balance;
            $after  = $before + $amount;

            $vault->update(['balance' => $after, 'last_updated_by' => Auth::id()]);

            $till->decrement('current_balance', $amount);

            TillCashMovement::create([
                'till_id'      => $till->id,
                'type'         => 'return',
                'amount'       => $amount,
                'notes'        => $notes,
                'processed_by' => Auth::id(),
            ]);

            $txn = VaultTransaction::create([
                'vault_id'       => $vault->id,
                'type'           => 'returned_from_teller',
                'amount'         => $amount,
                'balance_before' => $before,
                'balance_after'  => $after,
                'teller_till_id' => $till->id,
                'reference'      => $this->generateReference(),
                'notes'          => $notes,
                'processed_by'   => Auth::id(),
            ]);

            AuditLog::record('vault.received_from_teller', 'vault_transactions',
                "Till '{$till->till_name}' → Vault: USD {$amount}. Vault {$before} → {$after}", [], [
                    'vault_id' => $vault->id, 'till_id' => $till->id, 'amount' => $amount,
                ]);

            $this->gl->post([
                ['account' => $this->gl->accountByCode('1000'), 'debit'  => $amount],
                ['account' => $this->gl->accountByCode('1010'), 'credit' => $amount, 'memo' => "Till: {$till->till_name}"],
            ], "Till '{$till->till_name}' → Vault ({$txn->reference})", 'vault_transaction', $txn->id);

            return $txn;
        });
    }

    // ── Guards ────────────────────────────────────────────────────────────────

    private function requireBusinessDay(): void
    {
        if (!BusinessDay::isOpenToday()) {
            throw ValidationException::withMessages([
                'business_day' => 'No open business day. The Branch Manager must open the business day first.',
            ]);
        }
    }

    private function requireVaultOpen(): void
    {
        if (Vault::where('status', 'open')->doesntExist()) {
            throw ValidationException::withMessages([
                'vault' => 'Vault is closed. The Branch Manager must open the vault first.',
            ]);
        }
    }

    // ── Reference Generation ──────────────────────────────────────────────────

    private function generateReference(): string
    {
        $date  = now()->format('Ymd');
        $count = VaultTransaction::whereDate('created_at', today())->count() + 1;
        return 'VLT-' . $date . '-' . str_pad($count, 5, '0', STR_PAD_LEFT);
    }
}
