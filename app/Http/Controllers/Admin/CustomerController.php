<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CreateCustomerRequest;
use App\Http\Requests\Admin\UpdateCustomerRequest;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Notifications\CustomerPasswordReset;
use App\Services\CustomerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    public function __construct(private CustomerService $service) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Customer::class);

        $filters = $request->only(['search', 'status', 'sort', 'direction']);

        return Inertia::render('Admin/Customers/Index', [
            'customers' => $this->service->list($filters),
            'filters'   => $filters,
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Customer::class);

        return Inertia::render('Admin/Customers/Create');
    }

    public function store(CreateCustomerRequest $request): RedirectResponse
    {
        $this->authorize('create', Customer::class);

        $customer = $this->service->create($request->validated());

        AuditLog::record('customer_created', 'customers',
            "Customer {$customer->name} ({$customer->customer_number}) created.");

        return redirect()->route('admin.customers.show', $customer)
            ->with('success', "Customer {$customer->name} ({$customer->customer_number}) invited. An invitation email has been sent to {$customer->email}.");
    }

    public function show(Customer $customer): Response
    {
        $this->authorize('view', $customer);

        $customer->load(['kyc.documents', 'accounts.branch', 'loans']);

        $recentAuditLogs = AuditLog::where('user_id', $customer->id)
            ->where('user_type', 'App\Models\Customer')
            ->latest()
            ->limit(10)
            ->get(['id', 'action', 'module', 'description', 'ip_address', 'created_at']);

        return Inertia::render('Admin/Customers/Show', [
            'customer'        => $customer,
            'recentAuditLogs' => $recentAuditLogs,
        ]);
    }

    public function edit(Customer $customer): Response
    {
        $this->authorize('update', $customer);

        return Inertia::render('Admin/Customers/Edit', [
            'customer' => $customer,
        ]);
    }

    public function update(UpdateCustomerRequest $request, Customer $customer): RedirectResponse
    {
        $this->authorize('update', $customer);

        $this->service->update($customer, $request->validated());

        AuditLog::record('customer_updated', 'customers',
            "Customer {$customer->name} ({$customer->customer_number}) profile updated.");

        return redirect()->route('admin.customers.show', $customer)
            ->with('success', 'Customer updated successfully.');
    }

    public function toggleStatus(Customer $customer): RedirectResponse
    {
        $this->authorize('update', $customer);

        $customer = $this->service->toggleStatus($customer);
        $label    = ucfirst($customer->status);

        AuditLog::record('customer_status_changed', 'customers',
            "Customer {$customer->name} status changed to {$label}.");

        return back()->with('success', "Customer status changed to {$label}.");
    }

    public function resetPassword(Customer $customer): RedirectResponse
    {
        $this->authorize('update', $customer);

        $temp = $this->service->resetPassword($customer);

        $customer->notify(new CustomerPasswordReset($temp));

        return back()->with('success', "Password reset. Temporary password: {$temp}");
    }

    public function servePhoto(Customer $customer): HttpResponse
    {
        $this->authorize('view', $customer);
        return $this->serveFile($customer->photo_path);
    }

    public function serveSignature(Customer $customer): HttpResponse
    {
        $this->authorize('view', $customer);
        return $this->serveFile($customer->signature_path);
    }

    private function serveFile(?string $path): HttpResponse
    {
        if (!$path || !Storage::disk('local')->exists($path)) {
            abort(404);
        }
        $ext     = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mimeMap = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png'];
        return response(Storage::disk('local')->get($path), 200, [
            'Content-Type'        => $mimeMap[$ext] ?? 'image/jpeg',
            'Content-Disposition' => 'inline; filename="' . basename($path) . '"',
        ]);
    }

    public function destroy(Customer $customer): RedirectResponse
    {
        $this->authorize('delete', $customer);

        $this->service->delete($customer);

        return redirect()->route('admin.customers.index')
            ->with('success', 'Customer deleted successfully.');
    }
}
