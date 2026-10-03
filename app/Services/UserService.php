<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use App\Notifications\StaffInvitation;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class UserService
{
    public function __construct(private UserRepositoryInterface $repo) {}

    public function list(array $filters = []): LengthAwarePaginator
    {
        return $this->repo->paginate($filters);
    }

    // Staff are onboarded via invitation, never given a password directly: the
    // account starts unusable (random hash, status=invited) until the invitee
    // accepts and is emailed a one-time password — see StaffInvitationController.
    public function create(array $data): User
    {
        $this->guardSuperAdminAssignment($data['role'] ?? null);

        $staffId = $this->generateStaffId();

        $user = $this->repo->create([
            'staff_id'          => $staffId,
            'name'              => $data['name'],
            'email'             => $data['email'],
            'phone'             => $data['phone'] ?? null,
            'password'          => Hash::make(Str::random(40)),
            'status'            => 'invited',
            'must_change_password' => true,
            'transaction_limit' => $data['transaction_limit'] ?? 5000.00,
            'email_verified_at' => now(),
        ]);

        if (!empty($data['role'])) {
            $user->assignRole($data['role']);
        }

        $acceptUrl = URL::temporarySignedRoute(
            'staff-invitation.show',
            now()->addDays(7),
            ['user' => $user->id]
        );

        $user->notify(new StaffInvitation($data['role'] ?? 'staff member', $acceptUrl));

        AuditLog::record('created', 'users', "Invited staff {$user->name} ({$staffId})", [], [
            'name'     => $user->name,
            'staff_id' => $staffId,
            'role'     => $data['role'] ?? null,
        ]);

        return $user;
    }

    private function generateStaffId(): string
    {
        return DB::transaction(function () {
            $setting = DB::table('settings')
                ->where('key', 'staff_id_sequence')
                ->lockForUpdate()
                ->first();

            if (!$setting) {
                $next = 1;
                DB::table('settings')->insert([
                    'key'        => 'staff_id_sequence',
                    'value'      => '1',
                    'group'      => 'system',
                    'label'      => 'Staff ID Sequence',
                    'type'       => 'integer',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                $next = (int) $setting->value + 1;
                DB::table('settings')->where('key', 'staff_id_sequence')->update([
                    'value'      => (string) $next,
                    'updated_at' => now(),
                ]);
            }

            return 'STF-' . str_pad($next, 4, '0', STR_PAD_LEFT);
        });
    }

    public function update(User $user, array $data): User
    {
        $this->guardSelfRoleChange($user, $data['role'] ?? null);
        $this->guardRoleChangeRequiresSuperAdmin($user, $data['role'] ?? null);
        $this->guardSuperAdminAssignment($data['role'] ?? null);

        $old = ['name' => $user->name, 'email' => $user->email, 'status' => $user->status, 'role' => $user->roles->first()?->name];

        $updateData = [
            'name'              => $data['name'],
            'email'             => $data['email'],
            'phone'             => $data['phone'] ?? null,
            'status'            => $data['status'],
            'transaction_limit' => $data['transaction_limit'],
        ];

        if (!empty($data['password'])) {
            $updateData['password'] = Hash::make($data['password']);
        }

        $user = $this->repo->update($user, $updateData);

        if (!empty($data['role'])) {
            $user->syncRoles([$data['role']]);
        }

        AuditLog::record('updated', 'users', "Updated user {$user->name} ({$user->email})", $old, [
            'name'   => $user->name,
            'email'  => $user->email,
            'status' => $user->status,
            'role'   => $data['role'] ?? null,
        ]);

        return $user;
    }

    public function toggleStatus(User $user): User
    {
        $this->guardSelfAction($user, 'suspend/activate');

        $old    = $user->status;
        $new    = $user->status === 'active' ? 'suspended' : 'active';

        $user->update(['status' => $new]);

        AuditLog::record('status_changed', 'users', "Changed {$user->name} status from {$old} to {$new}", ['status' => $old], ['status' => $new]);

        return $user;
    }

    public function resetPassword(User $user): string
    {
        $temp = 'Nabaad@' . random_int(1000, 9999);
        $user->update(['password' => Hash::make($temp), 'must_change_password' => true]);

        AuditLog::record('password_reset', 'users', "Password reset for user {$user->name} ({$user->email})");

        return $temp;
    }

    public function delete(User $user): void
    {
        $this->guardSelfAction($user, 'delete');

        AuditLog::record('deleted', 'users', "Deleted user {$user->name} ({$user->email})");
        $this->repo->delete($user);
    }

    public function availableRoles(): array
    {
        $roles = Role::where('guard_name', 'web')->pluck('name')->toArray();

        // Only Super Admins can assign the Super Admin role
        if (!Auth::user()?->hasRole('Super Admin')) {
            $roles = array_filter($roles, fn ($r) => $r !== 'Super Admin');
        }

        return array_values($roles);
    }

    // ── Guards ────────────────────────────────────────────────────────────────

    private function guardSuperAdminAssignment(?string $role): void
    {
        if ($role === 'Super Admin' && !Auth::user()?->hasRole('Super Admin')) {
            throw ValidationException::withMessages([
                'role' => 'Only a Super Admin can assign the Super Admin role.',
            ]);
        }
    }

    private function guardSelfRoleChange(User $user, ?string $role): void
    {
        if ($user->id === Auth::id() && $role && $role !== $user->roles->first()?->name) {
            throw ValidationException::withMessages([
                'role' => 'You cannot change your own role.',
            ]);
        }
    }

    private function guardRoleChangeRequiresSuperAdmin(User $user, ?string $role): void
    {
        $isChanging = $role && $role !== $user->roles->first()?->name;

        if ($isChanging && !Auth::user()?->hasRole('Super Admin')) {
            throw ValidationException::withMessages([
                'role' => 'Only a Super Admin can change a staff member\'s role.',
            ]);
        }
    }

    private function guardSelfAction(User $user, string $action): void
    {
        if ($user->id === Auth::id()) {
            throw ValidationException::withMessages([
                'user' => "You cannot {$action} your own account.",
            ]);
        }
    }
}
