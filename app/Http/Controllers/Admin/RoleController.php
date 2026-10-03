<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function index(): Response
    {
        $this->authorize('roles.manage');

        $roles = Role::withCount('users')
            ->with('permissions:id,name')
            ->orderBy('name')
            ->get()
            ->map(fn ($r) => [
                'id'                => $r->id,
                'name'              => $r->name,
                'users_count'       => $r->users_count,
                'permissions_count' => $r->permissions->count(),
                'is_super_admin'    => $r->name === 'Super Admin',
            ]);

        return Inertia::render('Admin/Roles/Index', [
            'roles'             => $roles,
            'total_permissions' => Permission::count(),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('roles.manage');

        return Inertia::render('Admin/Roles/Create', [
            'permission_groups' => $this->groupedPermissions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('roles.manage');

        $data = $request->validate([
            'name'          => 'required|string|max:191|unique:roles,name',
            'permissions'   => 'array',
            'permissions.*' => 'string|exists:permissions,name',
        ]);

        $role = Role::create(['name' => $data['name'], 'guard_name' => 'web']);
        $role->syncPermissions($data['permissions'] ?? []);

        AuditLog::record('created', 'roles',
            "Created role '{$role->name}' with " . count($data['permissions'] ?? []) . ' permission(s).');

        return redirect()->route('admin.roles.index')->with('success', "Role '{$role->name}' created.");
    }

    public function edit(Role $role): Response
    {
        $this->authorize('roles.manage');

        $role->load('permissions:id,name');

        return Inertia::render('Admin/Roles/Edit', [
            'role' => [
                'id'             => $role->id,
                'name'           => $role->name,
                'permissions'    => $role->permissions->pluck('name'),
                'users_count'    => $role->users()->count(),
                'is_super_admin' => $role->name === 'Super Admin',
            ],
            'permission_groups' => $this->groupedPermissions(),
        ]);
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        $this->authorize('roles.manage');

        if ($role->name === 'Super Admin') {
            throw ValidationException::withMessages([
                'role' => 'The Super Admin role always has full access and cannot be edited.',
            ]);
        }

        $data = $request->validate([
            'name'          => "required|string|max:191|unique:roles,name,{$role->id}",
            'permissions'   => 'array',
            'permissions.*' => 'string|exists:permissions,name',
        ]);

        $oldName = $role->name;
        $role->update(['name' => $data['name']]);
        $role->syncPermissions($data['permissions'] ?? []);

        AuditLog::record('updated', 'roles',
            "Updated role '{$oldName}'" . ($oldName !== $role->name ? " → '{$role->name}'" : '') .
            ' (' . count($data['permissions'] ?? []) . ' permission(s)).');

        return redirect()->route('admin.roles.index')->with('success', "Role '{$role->name}' updated.");
    }

    public function destroy(Role $role): RedirectResponse
    {
        $this->authorize('roles.manage');

        if ($role->name === 'Super Admin') {
            throw ValidationException::withMessages([
                'role' => 'The Super Admin role cannot be deleted.',
            ]);
        }

        $usersCount = $role->users()->count();
        if ($usersCount > 0) {
            throw ValidationException::withMessages([
                'role' => "Cannot delete '{$role->name}': {$usersCount} staff member(s) are still assigned to it. Reassign them first.",
            ]);
        }

        $name = $role->name;
        $role->delete();

        AuditLog::record('deleted', 'roles', "Deleted role '{$name}'.");

        return redirect()->route('admin.roles.index')->with('success', "Role '{$name}' deleted.");
    }

    private function groupedPermissions(): array
    {
        return Permission::orderBy('name')
            ->pluck('name')
            ->groupBy(fn ($p) => explode('.', $p)[0])
            ->map(fn ($g) => $g->values())
            ->toArray();
    }
}
