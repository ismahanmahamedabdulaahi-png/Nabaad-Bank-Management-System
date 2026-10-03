<?php

namespace App\Http\Middleware;

use App\Models\Transaction;
use App\Services\ApprovalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $user     = Auth::guard('web')->user();
        $customer = Auth::guard('customer')->user();

        return [
            ...parent::share($request),
            'auth' => [
                'user'        => $user ? [
                    'id'               => $user->id,
                    'name'             => $user->name,
                    'email'            => $user->email,
                    'status'           => $user->status,
                    'transaction_limit'=> $user->transaction_limit,
                    'roles'            => $user->roles->map(fn ($r) => ['name' => $r->name]),
                ] : null,
                'permissions' => $user ? $user->getAllPermissions()->pluck('name') : [],
            ],
            'customer_auth' => $customer ? [
                'id'              => $customer->id,
                'name'            => $customer->name,
                'email'           => $customer->email,
                'customer_number' => $customer->customer_number,
                'status'          => $customer->status,
                'last_login_at'   => $customer->last_login_at?->format('d M Y, H:i'),
            ] : null,
            'pending_approvals' => function () use ($user) {
                if (!$user || !$user->hasPermissionTo('approvals.view')) return 0;
                $myLevel = app(ApprovalService::class)->userApprovalLevel($user);
                if ($user->hasRole('Super Admin')) {
                    return Transaction::where('status', 'pending')
                        ->where('requires_approval', true)
                        ->whereColumn('approval_level_reached', '<', 'approval_level_required')
                        ->where('processed_by', '!=', $user->id)
                        ->count();
                }

                return $myLevel > 0
                    ? Transaction::where('status', 'pending')
                        ->where('requires_approval', true)
                        ->where('approval_level_required', '>=', $myLevel)
                        ->where('approval_level_reached', $myLevel - 1)
                        ->where('processed_by', '!=', $user->id)
                        ->count()
                    : 0;
            },
            'notifications' => function () use ($user, $customer) {
                $notifiable = $user ?? $customer;
                if (!$notifiable) return ['unread_count' => 0, 'recent' => []];

                return [
                    'unread_count' => $notifiable->unreadNotifications()->count(),
                    'recent'       => $notifiable->notifications()
                        ->latest()
                        ->limit(8)
                        ->get()
                        ->map(fn ($n) => [
                            'id'         => $n->id,
                            'icon'       => $n->data['icon'] ?? 'bi-bell',
                            'level'      => $n->data['level'] ?? 'info',
                            'title'      => $n->data['title'] ?? 'Notification',
                            'message'    => $n->data['message'] ?? '',
                            'url'        => $n->data['url'] ?? null,
                            'read'       => $n->read_at !== null,
                            'created_at' => $n->created_at->diffForHumans(),
                        ]),
                ];
            },
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error'   => fn () => $request->session()->get('error'),
                'warning' => fn () => $request->session()->get('warning'),
                'info'    => fn () => $request->session()->get('info'),
            ],
        ];
    }
}
