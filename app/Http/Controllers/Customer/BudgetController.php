<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Budget;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class BudgetController extends Controller
{
    public function index(): Response
    {
        $customer = Auth::guard('customer')->user();

        $budgets = Budget::where('customer_id', $customer->id)
            ->with('account:id,account_number,account_type')
            ->latest()
            ->get()
            ->map(fn (Budget $budget) => [
                'id'                => $budget->id,
                'name'              => $budget->name,
                'limit_amount'      => $budget->limit_amount,
                'threshold_percent' => $budget->threshold_percent,
                'is_active'         => $budget->is_active,
                'account'           => $budget->account,
                'spent'             => $budget->spentThisPeriod(),
                'percent_used'      => $budget->percentUsed(),
                'period_start'      => $budget->periodStart()->format('d M Y'),
            ]);

        $accounts = Account::where('customer_id', $customer->id)
            ->where('status', 'active')
            ->get(['id', 'account_number', 'account_type']);

        return Inertia::render('Customer/Budgets/Index', [
            'budgets'  => $budgets,
            'accounts' => $accounts,
        ]);
    }

    public function create(): Response
    {
        $customer = Auth::guard('customer')->user();

        $accounts = Account::where('customer_id', $customer->id)
            ->where('status', 'active')
            ->get(['id', 'account_number', 'account_type']);

        return Inertia::render('Customer/Budgets/Create', [
            'accounts' => $accounts,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $customer = Auth::guard('customer')->user();

        $data = $request->validate([
            'name'               => 'required|string|max:100',
            'account_id'         => 'nullable|exists:accounts,id',
            'limit_amount'       => 'required|numeric|min:1',
            'threshold_percent'  => 'required|integer|min:1|max:100',
        ]);

        if (!empty($data['account_id'])) {
            $account = Account::findOrFail($data['account_id']);
            abort_unless($account->customer_id === $customer->id, 403, 'Account does not belong to you.');
        }

        Budget::create([
            'customer_id'       => $customer->id,
            'account_id'        => $data['account_id'] ?? null,
            'name'              => $data['name'],
            'limit_amount'      => $data['limit_amount'],
            'threshold_percent' => $data['threshold_percent'],
        ]);

        return redirect()->route('customer.budgets.index')->with('success', 'Budget created.');
    }

    public function toggle(Budget $budget): RedirectResponse
    {
        $this->authorizeBudget($budget);
        $budget->update(['is_active' => !$budget->is_active]);
        return back()->with('success', $budget->is_active ? 'Budget activated.' : 'Budget paused.');
    }

    public function destroy(Budget $budget): RedirectResponse
    {
        $this->authorizeBudget($budget);
        $budget->delete();
        return back()->with('success', 'Budget deleted.');
    }

    private function authorizeBudget(Budget $budget): void
    {
        $customer = Auth::guard('customer')->user();
        abort_unless($budget->customer_id === $customer->id, 403);
    }
}
