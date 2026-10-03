<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\BusinessDay;
use App\Models\ReplenishmentRequest;
use App\Models\TellerTill;
use App\Models\TillCashMovement;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TellerService
{
    public function __construct(private GeneralLedgerService $gl)
    {
    }

    // ── Open / Assign ─────────────────────────────────────────────────────────

    public function openTill(User $teller, array $data): TellerTill
    {
        $this->requireBusinessDay();

        $businessDate = $data['business_date'] ?? today()->toDateString();

        $existing = TellerTill::where('teller_id', $teller->id)
            ->where('business_date', $businessDate)
            ->where('status', 'open')
            ->first();

        if ($existing) {
            throw ValidationException::withMessages([
                'teller_id' => "{$teller->name} already has an open till for {$businessDate}.",
            ]);
        }

        // Opening balance starts at 0; vault debit is handled separately in TellerController
        // so the formula (opening_balance + movements) stays accurate.
        $opening = (float) ($data['opening_balance'] ?? 0);

        $till = TellerTill::create([
            'till_name'       => $data['till_name'] ?? "Till - {$teller->name}",
            'teller_id'       => $teller->id,
            'assigned_by'     => Auth::id(),
            'opened_by'       => Auth::id(),
            'status'          => 'open',
            'opening_balance' => 0,          // always 0; opening cash arrives via vault debit
            'current_balance' => 0,
            'business_date'   => $businessDate,
            'opened_at'       => now(),
            'notes'           => $data['notes'] ?? null,
        ]);

        AuditLog::record('teller.open', 'teller_tills',
            "Opened till '{$till->till_name}' for {$teller->name}", [], [
                'till_id'         => $till->id,
                'teller'          => $teller->name,
                'opening_balance' => $opening,
                'business_date'   => $businessDate,
            ]);

        return $till;
    }

    // ── Close / Reconcile ─────────────────────────────────────────────────────

    public function closeTill(TellerTill $till, array $data): TellerTill
    {
        if (!$till->isOpen()) {
            throw ValidationException::withMessages(['till' => 'This till is not open.']);
        }

        $closing  = (float) $data['closing_balance'];
        $expected = $till->expectedBalance();
        $variance = $closing - $expected;

        $till->update([
            'status'           => 'closed',
            'closing_balance'  => $closing,
            'expected_balance' => $expected,
            'variance'         => $variance,
            'closed_at'        => now(),
            'closed_by'        => Auth::id(),
            'notes'            => $data['notes'] ?? $till->notes,
        ]);

        AuditLog::record('teller.close', 'teller_tills',
            "Closed till '{$till->till_name}' — Expected: USD {$expected}, Actual: USD {$closing}, Variance: USD {$variance}", [], [
                'till_id'          => $till->id,
                'expected_balance' => $expected,
                'closing_balance'  => $closing,
                'variance'         => $variance,
            ]);

        return $till->fresh();
    }

    // ── Request Replenishment (Teller → Supervisor) ───────────────────────────

    public function requestReplenishment(TellerTill $till, float $amount, ?string $reason): ReplenishmentRequest
    {
        $this->requireBusinessDay();

        if (!$till->isOpen()) {
            throw ValidationException::withMessages(['till' => 'Till must be open to request replenishment.']);
        }

        // Block duplicate pending requests
        $pending = ReplenishmentRequest::where('till_id', $till->id)
            ->where('status', 'pending')
            ->exists();

        if ($pending) {
            throw ValidationException::withMessages([
                'amount' => 'There is already a pending replenishment request for this till.',
            ]);
        }

        $req = ReplenishmentRequest::create([
            'till_id'      => $till->id,
            'requested_by' => Auth::id(),
            'amount'       => $amount,
            'reason'       => $reason,
            'status'       => 'pending',
        ]);

        AuditLog::record('teller.request_replenishment', 'replenishment_requests',
            "Replenishment requested for till '{$till->till_name}': USD {$amount}", [], [
                'till_id' => $till->id,
                'amount'  => $amount,
            ]);

        return $req;
    }

    public function approveReplenishment(ReplenishmentRequest $req, ?string $notes): void
    {
        if (!$req->isPending()) {
            throw ValidationException::withMessages(['request' => 'Request is no longer pending.']);
        }

        $req->update([
            'status'       => 'approved',
            'reviewed_by'  => Auth::id(),
            'reviewed_at'  => now(),
            'review_notes' => $notes,
        ]);

        AuditLog::record('teller.approve_replenishment', 'replenishment_requests',
            "Replenishment approved for till ID {$req->till_id}: USD {$req->amount}", [], [
                'request_id' => $req->id,
                'amount'     => $req->amount,
            ]);
    }

    public function rejectReplenishment(ReplenishmentRequest $req, string $reason): void
    {
        if (!$req->isPending()) {
            throw ValidationException::withMessages(['request' => 'Request is no longer pending.']);
        }

        $req->update([
            'status'       => 'rejected',
            'reviewed_by'  => Auth::id(),
            'reviewed_at'  => now(),
            'review_notes' => $reason,
        ]);

        AuditLog::record('teller.reject_replenishment', 'replenishment_requests',
            "Replenishment rejected for till ID {$req->till_id}: {$reason}", [], [
                'request_id' => $req->id,
            ]);
    }

    // ── Direct Cash Movements (Supervisor actions) ────────────────────────────

    public function replenish(TellerTill $till, float $amount, ?string $notes): TillCashMovement
    {
        if (!$till->isOpen()) {
            throw ValidationException::withMessages(['till' => 'Till must be open to receive replenishment.']);
        }

        return DB::transaction(function () use ($till, $amount, $notes) {
            $till->increment('current_balance', $amount);

            $movement = TillCashMovement::create([
                'till_id'      => $till->id,
                'type'         => 'replenishment',
                'amount'       => $amount,
                'notes'        => $notes,
                'processed_by' => Auth::id(),
            ]);

            AuditLog::record('teller.replenish', 'till_cash_movements',
                "Replenished till '{$till->till_name}' with USD {$amount}", [], [
                    'till_id' => $till->id, 'amount' => $amount,
                ]);

            return $movement;
        });
    }

    public function returnCash(TellerTill $till, float $amount, ?string $notes): TillCashMovement
    {
        if (!$till->isOpen()) {
            throw ValidationException::withMessages(['till' => 'Till must be open to return cash.']);
        }

        return DB::transaction(function () use ($till, $amount, $notes) {
            // Lock the row before reading the balance to prevent TOCTOU race conditions.
            $locked = TellerTill::lockForUpdate()->findOrFail($till->id);

            if ($amount > (float) $locked->current_balance) {
                throw ValidationException::withMessages(['amount' => 'Return amount exceeds current till balance.']);
            }

            $locked->decrement('current_balance', $amount);

            $movement = TillCashMovement::create([
                'till_id'      => $locked->id,
                'type'         => 'return',
                'amount'       => $amount,
                'notes'        => $notes,
                'processed_by' => Auth::id(),
            ]);

            AuditLog::record('teller.return', 'till_cash_movements',
                "Returned USD {$amount} from till '{$locked->till_name}'", [], [
                    'till_id' => $locked->id, 'amount' => $amount,
                ]);

            return $movement;
        });
    }

    public function transferBetweenTills(TellerTill $from, TellerTill $to, float $amount, ?string $notes): array
    {
        if (!$from->isOpen()) throw ValidationException::withMessages(['from_till_id' => 'Source till must be open.']);
        if (!$to->isOpen())   throw ValidationException::withMessages(['to_till_id'   => 'Destination till must be open.']);
        if ($from->id === $to->id) throw ValidationException::withMessages(['to_till_id' => 'Source and destination tills cannot be the same.']);

        return DB::transaction(function () use ($from, $to, $amount, $notes) {
            // Lock both rows in ascending ID order to prevent deadlocks between concurrent transfers.
            $ids = collect([$from->id, $to->id])->sort()->values()->all();
            $locked = TellerTill::lockForUpdate()->whereIn('id', $ids)->orderBy('id')->get()->keyBy('id');

            $lockedFrom = $locked->get($from->id);
            $lockedTo   = $locked->get($to->id);

            if ($amount > (float) $lockedFrom->current_balance) {
                throw ValidationException::withMessages(['amount' => 'Transfer amount exceeds source till balance.']);
            }

            $lockedFrom->decrement('current_balance', $amount);
            $lockedTo->increment('current_balance', $amount);

            $out = TillCashMovement::create([
                'till_id'         => $lockedFrom->id,
                'related_till_id' => $lockedTo->id,
                'type'            => 'transfer_out',
                'amount'          => $amount,
                'notes'           => $notes,
                'processed_by'    => Auth::id(),
            ]);

            $in = TillCashMovement::create([
                'till_id'         => $lockedTo->id,
                'related_till_id' => $lockedFrom->id,
                'type'            => 'transfer_in',
                'amount'          => $amount,
                'notes'           => $notes,
                'processed_by'    => Auth::id(),
            ]);

            AuditLog::record('teller.transfer', 'till_cash_movements',
                "Transferred USD {$amount} from '{$lockedFrom->till_name}' to '{$lockedTo->till_name}'", [], [
                    'from_till' => $lockedFrom->id, 'to_till' => $lockedTo->id, 'amount' => $amount,
                ]);

            $cashTill = $this->gl->accountByCode('1010');
            $this->gl->post([
                ['account' => $cashTill, 'debit'  => $amount, 'memo' => "Till: {$lockedTo->till_name}"],
                ['account' => $cashTill, 'credit' => $amount, 'memo' => "Till: {$lockedFrom->till_name}"],
            ], "Till transfer — '{$lockedFrom->till_name}' → '{$lockedTo->till_name}'", 'till_cash_movement', $out->id);

            return [$out, $in];
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
}
