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
        ]);
    }

    public function requestWithdrawal(Request $request): RedirectResponse
    {
        $customer = Auth::guard('customer')->user();

        $data = $request->validate([
            'account_id'  => ['required', 'exists:accounts,id'],
            'amount'      => ['required', 'numeric', 'min:1'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $account = Account::findOrFail($data['account_id']);
        $code = $this->service->requestWithdrawal($customer, $account, (float) $data['amount'], $data['description'] ?? null);

        return redirect()->route('customer.service-codes.index')
            ->with('success', "Withdrawal code {$code->code} generated. Show it at any branch counter to collect your cash.");
    }

    public function requestDeposit(Request $request): RedirectResponse
    {
        $customer = Auth::guard('customer')->user();

        $data = $request->validate([
            'account_id'  => ['required', 'exists:accounts,id'],
            'amount'      => ['required', 'numeric', 'min:1'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $account = Account::findOrFail($data['account_id']);
        $code = $this->service->requestDeposit($customer, $account, (float) $data['amount'], $data['description'] ?? null);

        return redirect()->route('customer.service-codes.index')
            ->with('success', "Deposit code {$code->code} generated. Show it at any branch counter when you bring your cash in.");
    }
}
