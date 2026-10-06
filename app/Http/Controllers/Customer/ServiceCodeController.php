<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\ServiceRequestCode;
use App\Services\ServiceCodeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ServiceCodeController extends Controller
{
    public function __construct(private ServiceCodeService $service) {}

    public function index(): Response
    {
        $customer = Auth::guard('customer')->user();

        $codes = ServiceRequestCode::where('customer_id', $customer->id)
            ->with('account')
            ->latest()
            ->limit(20)
            ->get();

        $accounts = Account::where('customer_id', $customer->id)
            ->where('status', 'active')
            ->get(['id', 'account_number', 'account_type', 'balance', 'currency']);

        return Inertia::render('Customer/ServiceCodes/Index', [
            'codes'    => $codes,
            'accounts' => $accounts,
            'receipt'  => session('withdrawal_receipt'),
        ]);
    }

    public function withdraw(Request $request): RedirectResponse
    {
        return $this->direct($request, 'withdrawal');
    }

    public function deposit(Request $request): RedirectResponse
    {
        return $this->direct($request, 'deposit');
    }

    // Demo money movement: no cash or wallet transfer actually happens —
    // the account balance changes and the receipt shows it as done.
    private function direct(Request $request, string $type): RedirectResponse
    {
        $customer = Auth::guard('customer')->user();

        $data = $request->validate([
            'account_id'  => ['required', 'exists:accounts,id'],
            'amount'      => ['required', 'numeric', 'min:1'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $account     = Account::findOrFail($data['account_id']);
        $transaction = $type === 'deposit'
            ? $this->service->depositDirect($customer, $account, (float) $data['amount'], $data['description'] ?? null)
            : $this->service->withdrawDirect($customer, $account, (float) $data['amount'], $data['description'] ?? null);

        return redirect()->route('customer.service-codes.index')
            ->with('success', ucfirst($type) . " of {$transaction->currency} {$transaction->amount} completed. Ref: {$transaction->reference}")
            ->with('withdrawal_receipt', [
                'type'           => $type,
                'reference'      => $transaction->reference,
                'amount'         => $transaction->amount,
                'currency'       => $transaction->currency,
                'account_number' => $account->account_number,
                'balance_before' => $transaction->balance_before,
                'balance_after'  => $transaction->balance_after,
                'completed_at'   => $transaction->completed_at?->toIso8601String(),
            ]);
    }
}
