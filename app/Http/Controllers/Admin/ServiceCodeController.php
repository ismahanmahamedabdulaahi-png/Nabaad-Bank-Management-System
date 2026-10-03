<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ServiceCodeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ServiceCodeController extends Controller
{
    public function __construct(private ServiceCodeService $service) {}

    public function create(): Response
    {
        abort_unless(Auth::user()->can('service-codes.redeem'), 403);

        return Inertia::render('Admin/ServiceCodes/Redeem');
    }

    public function lookup(Request $request): Response
    {
        abort_unless(Auth::user()->can('service-codes.redeem'), 403);

        $data = $request->validate(['code' => ['required', 'string']]);

        // findActiveCode() throws ValidationException for not-found/expired/
        // already-used codes — left to bubble naturally, same as every other
        // validated action in this app; Laravel redirects back with the
        // error flashed to session and Inertia's shared `errors` prop picks it up.
        $found = $this->service->findActiveCode($data['code']);

        return Inertia::render('Admin/ServiceCodes/Redeem', ['found' => $found]);
    }

    public function redeemWithdrawal(Request $request): RedirectResponse
    {
        abort_unless(Auth::user()->can('service-codes.redeem'), 403);

        $data = $request->validate(['code' => ['required', 'string']]);

        $code        = $this->service->findActiveCode($data['code']);
        $transaction = $this->service->redeemWithdrawal($code, Auth::user());

        return redirect()->route('admin.service-codes.create')
            ->with('success', "Withdrawal of {$transaction->currency} {$transaction->amount} paid out. Ref: {$transaction->reference}");
    }

    public function redeemDeposit(Request $request): RedirectResponse
    {
        abort_unless(Auth::user()->can('service-codes.redeem'), 403);

        $data = $request->validate([
            'code'   => ['required', 'string'],
            'amount' => ['required', 'numeric', 'min:1'],
            'notes'  => ['nullable', 'string', 'max:500'],
        ]);

        $code        = $this->service->findActiveCode($data['code']);
        $transaction = $this->service->redeemDeposit($code, Auth::user(), (float) $data['amount'], $data['notes'] ?? null);

        return redirect()->route('admin.service-codes.create')
            ->with('success', "Deposit of {$transaction->currency} {$transaction->amount} posted. Ref: {$transaction->reference}");
    }
}
