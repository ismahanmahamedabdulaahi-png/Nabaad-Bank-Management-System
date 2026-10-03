<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    public function index(): Response
    {
        $customer = Auth::guard('customer')->user();

        return Inertia::render('Customer/Notifications/Index', [
            'notifications' => $customer->notifications()->latest()->paginate(20),
        ]);
    }

    public function markRead(string $id): RedirectResponse
    {
        $customer = Auth::guard('customer')->user();
        $customer->unreadNotifications()->where('id', $id)->first()?->markAsRead();

        return back();
    }

    public function markAllRead(): RedirectResponse
    {
        Auth::guard('customer')->user()->unreadNotifications->markAsRead();

        return back();
    }
}
