<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    public function index(): Response
    {
        $user = Auth::guard('web')->user();

        return Inertia::render('Admin/Notifications/Index', [
            'notifications' => $user->notifications()->latest()->paginate(20),
        ]);
    }

    public function markRead(string $id): RedirectResponse
    {
        $user = Auth::guard('web')->user();
        $user->unreadNotifications()->where('id', $id)->first()?->markAsRead();

        return back();
    }

    public function markAllRead(): RedirectResponse
    {
        Auth::guard('web')->user()->unreadNotifications->markAsRead();

        return back();
    }
}
