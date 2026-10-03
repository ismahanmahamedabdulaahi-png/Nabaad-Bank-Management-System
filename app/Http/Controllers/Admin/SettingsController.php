<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    private const GROUPS = ['general', 'loan', 'transaction', 'cheque', 'session', 'email'];

    public function index(): Response
    {
        abort_unless(auth()->user()->hasPermissionTo('settings.view'), 403);

        $settings = Setting::orderBy('group')->orderBy('id')->get()
            ->groupBy('group')
            ->map(fn ($g) => $g->keyBy('key'));

        return Inertia::render('Admin/Settings/Index', [
            'settings' => $settings,
            'groups'   => self::GROUPS,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()->hasPermissionTo('settings.edit'), 403);

        $data = $request->validate([
            'settings'        => 'required|array',
            'settings.*.key'  => 'required|string|exists:settings,key',
            'settings.*.value'=> 'nullable|string|max:500',
        ]);

        $old = [];
        $new = [];

        foreach ($data['settings'] as $item) {
            $setting = Setting::where('key', $item['key'])->first();
            if (!$setting) continue;

            $old[$item['key']] = $setting->value;
            $new[$item['key']] = $item['value'];

            $setting->update(['value' => $item['value']]);
            Cache::forget("setting:{$item['key']}");
        }

        AuditLog::record('updated', 'settings', 'System settings updated', $old, $new);

        return back()->with('success', 'Settings saved successfully.');
    }
}
