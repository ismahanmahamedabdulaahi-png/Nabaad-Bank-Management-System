<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CreateUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function __construct(private UserService $service) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', User::class);

        $filters = $request->only(['search', 'role', 'status', 'sort', 'direction']);

        return Inertia::render('Admin/Users/Index', [
            'users'   => $this->service->list($filters),
            'roles'   => $this->service->availableRoles(),
            'filters' => $filters,
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', User::class);

        return Inertia::render('Admin/Users/Create', [
            'roles' => $this->service->availableRoles(),
        ]);
    }

    public function store(CreateUserRequest $request): RedirectResponse
    {
        $user = $this->service->create($request->validated());

        return redirect()->route('admin.users.show', $user)
            ->with('success', "Staff {$user->name} invited. Staff ID: {$user->staff_id}. An invitation email has been sent to {$user->email}.");
    }

    public function show(User $user): Response
    {
        $this->authorize('view', $user);

        $user->load('roles');

        $recentAuditLogs = AuditLog::where('user_id', $user->id)
            ->where('user_type', 'App\Models\User')
            ->latest()
            ->limit(10)
            ->get(['id', 'action', 'module', 'description', 'ip_address', 'created_at']);

        return Inertia::render('Admin/Users/Show', [
            'user' => array_merge($user->toArray(), [
                'roles'       => $user->roles->map(fn ($r) => ['name' => $r->name])->values(),
                'permissions' => $user->getAllPermissions()->pluck('name')->values(),
            ]),
            'recentAuditLogs' => $recentAuditLogs,
        ]);
    }

    public function edit(User $user): Response
    {
        $this->authorize('update', $user);

        $user->load('roles');

        return Inertia::render('Admin/Users/Edit', [
            'user'  => array_merge($user->toArray(), [
                'role' => $user->roles->first()?->name,
            ]),
            'roles' => $this->service->availableRoles(),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $this->authorize('update', $user);

        $this->service->update($user, $request->validated());

        return redirect()->route('admin.users.show', $user)
            ->with('success', 'User updated successfully.');
    }

    public function toggleStatus(User $user): RedirectResponse
    {
        $this->authorize('update', $user);

        $user = $this->service->toggleStatus($user);
        $label = ucfirst($user->status);

        return back()->with('success', "User status changed to {$label}.");
    }

    public function resetPassword(User $user): RedirectResponse
    {
        $this->authorize('update', $user);

        $tempPassword = $this->service->resetPassword($user);

        return back()->with('success', "Password reset. Temporary password: {$tempPassword}");
    }

    public function destroy(User $user): RedirectResponse
    {
        $this->authorize('delete', $user);

        $this->service->delete($user);

        return redirect()->route('admin.users.index')
            ->with('success', 'User deleted successfully.');
    }
}
