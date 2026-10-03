<?php

namespace App\Services;

use App\Models\Account;
use App\Models\AuditLog;
use App\Models\StandingOrder;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class StandingOrderService
{
    public function __construct(private TransactionService $txnService) {}

    public function create(array $data): StandingOrder
    {
        $start = Carbon::parse($data['start_date']);

        $order = StandingOrder::create([
            'source_account_id'      => $data['source_account_id'],
            'beneficiary_account_id' => $data['beneficiary_account_id'],
            'amount'                 => $data['amount'],
            'frequency'              => $data['frequency'],
            'start_date'             => $start,
            'next_execution_date'    => $start,
            'end_date'               => !empty($data['end_date']) ? $data['end_date'] : null,
            'description'            => $data['description'] ?? null,
            'status'                 => 'active',
            'created_by_user'        => Auth::id(),
        ]);

        AuditLog::record('standing_order_created', 'standing_orders',
            "Created {$data['frequency']} standing order of {$data['amount']} from {$data['source_account_id']}");

        return $order->load(['sourceAccount.customer', 'beneficiaryAccount.customer']);
    }

    public function pause(StandingOrder $order): StandingOrder
    {
        if (!$order->isActive()) {
            throw ValidationException::withMessages([
                'order' => 'Only active standing orders can be paused.',
            ]);
        }

        $order->update(['status' => 'paused']);
        AuditLog::record('standing_order_paused', 'standing_orders', "Paused standing order #{$order->id}");
        return $order->fresh();
    }

    public function resume(StandingOrder $order): StandingOrder
    {
        if ($order->status !== 'paused') {
            throw ValidationException::withMessages([
                'order' => 'Only paused standing orders can be resumed.',
            ]);
        }

        $order->update(['status' => 'active']);
        AuditLog::record('standing_order_resumed', 'standing_orders', "Resumed standing order #{$order->id}");
        return $order->fresh();
    }

    public function cancel(StandingOrder $order): StandingOrder
    {
        if (in_array($order->status, ['cancelled', 'expired'])) {
            throw ValidationException::withMessages([
                'order' => 'This standing order is already cancelled or expired.',
            ]);
        }

        $order->update(['status' => 'cancelled']);
        AuditLog::record('standing_order_cancelled', 'standing_orders', "Cancelled standing order #{$order->id}");
        return $order->fresh();
    }

    public function executeDue(): array
    {
        $results = ['success' => 0, 'failed' => 0, 'skipped' => 0];

        $dueOrders = StandingOrder::where('status', 'active')
            ->where('next_execution_date', '<=', today())
            ->with(['sourceAccount', 'beneficiaryAccount'])
            ->get();

        foreach ($dueOrders as $order) {
            // Skip if end_date has already passed — mark expired
            if ($order->end_date && today()->gt($order->end_date)) {
                $order->update(['status' => 'expired']);
                $results['skipped']++;
                continue;
            }

            // Skip if either account is not in a transactable state
            if (!$order->sourceAccount || !$order->sourceAccount->isOperational()) {
                $order->update(['last_executed_at' => now(), 'last_execution_status' => 'failed']);
                $results['failed']++;
                continue;
            }
            if (!$order->beneficiaryAccount || !$order->beneficiaryAccount->isOperational()) {
                $order->update(['last_executed_at' => now(), 'last_execution_status' => 'failed']);
                $results['failed']++;
                continue;
            }

            try {
                $this->txnService->transfer(
                    $order->sourceAccount,
                    $order->beneficiaryAccount,
                    [
                        'amount'      => $order->amount,
                        'description' => $order->description ?? "Standing order #{$order->id}",
                    ]
                );

                $nextDate = $this->nextExecutionDate($order);
                $expired  = $order->end_date && $nextDate->gt($order->end_date);

                $order->update([
                    'executions_count'      => $order->executions_count + 1,
                    'last_executed_at'      => now(),
                    'last_execution_status' => 'success',
                    'next_execution_date'   => $nextDate,
                    'status'                => $expired ? 'expired' : 'active',
                ]);

                $results['success']++;
            } catch (\Throwable $e) {
                $order->update([
                    'last_executed_at'      => now(),
                    'last_execution_status' => 'failed',
                ]);
                $results['failed']++;
            }
        }

        return $results;
    }

    private function nextExecutionDate(StandingOrder $order): Carbon
    {
        $date = Carbon::parse($order->next_execution_date);

        return match ($order->frequency) {
            'weekly'  => $date->addWeek(),
            'monthly' => $date->addMonth(),
            default   => $date->addMonth(),
        };
    }
}
