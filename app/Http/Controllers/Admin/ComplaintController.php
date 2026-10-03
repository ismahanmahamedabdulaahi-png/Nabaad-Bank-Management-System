<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Complaint;
use App\Models\User;
use App\Services\ComplaintService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ComplaintController extends Controller
{
    public function __construct(private ComplaintService $service) {}

    public function index(Request $request): Response
    {
        abort_unless(Auth::user()->can('complaints.view'), 403);

        $complaints = Complaint::with(['customer', 'assignedTo'])
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->priority, fn ($q, $p) => $q->where('priority', $p))
            ->when($request->category, fn ($q, $c) => $q->where('category', $c))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Admin/Complaints/Index', [
            'complaints' => $complaints,
            'filters'    => $request->only(['status', 'priority', 'category']),
        ]);
    }

    public function show(Complaint $complaint): Response
    {
        abort_unless(Auth::user()->can('complaints.view'), 403);

        return Inertia::render('Admin/Complaints/Show', [
            'complaint' => $complaint->load(['customer', 'assignedTo', 'relatedAccount', 'relatedTransaction']),
            'staff'     => User::where('status', 'active')->get(['id', 'name']),
        ]);
    }

    public function assign(Request $request, Complaint $complaint): RedirectResponse
    {
        abort_unless(Auth::user()->can('complaints.manage'), 403);

        $data = $request->validate(['assigned_to' => ['nullable', 'exists:users,id']]);
        $this->service->assign($complaint, $data['assigned_to'] ? (int) $data['assigned_to'] : null);

        return back()->with('success', 'Complaint assigned.');
    }

    public function updateStatus(Request $request, Complaint $complaint): RedirectResponse
    {
        abort_unless(Auth::user()->can('complaints.manage'), 403);

        $data = $request->validate([
            'status'           => ['required', 'in:open,in_progress,resolved,closed'],
            'resolution_notes' => ['nullable', 'string', 'max:3000'],
        ]);

        $this->service->updateStatus($complaint, $data['status'], $data['resolution_notes'] ?? null, Auth::id());

        return back()->with('success', 'Complaint updated.');
    }
}
