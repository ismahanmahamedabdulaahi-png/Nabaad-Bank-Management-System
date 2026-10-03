<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless(auth()->user()->hasPermissionTo('audit.view'), 403);

        $logs = AuditLog::query()
            ->when($request->user_name,  fn ($q, $v) => $q->where('user_name', 'like', "%{$v}%"))
            ->when($request->module,     fn ($q, $v) => $q->where('module', $v))
            ->when($request->action,     fn ($q, $v) => $q->where('action', $v))
            ->when($request->date_from,  fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($request->date_to,    fn ($q, $v) => $q->whereDate('created_at', '<=', $v))
            ->when($request->search,     fn ($q, $v) => $q->where('description', 'like', "%{$v}%"))
            ->latest()
            ->paginate(50)
            ->withQueryString();

        $modules = AuditLog::distinct()->orderBy('module')->pluck('module');
        $actions = AuditLog::distinct()->orderBy('action')->pluck('action');

        return Inertia::render('Admin/AuditLogs/Index', [
            'logs'    => $logs,
            'modules' => $modules,
            'actions' => $actions,
            'filters' => $request->only(['user_name', 'module', 'action', 'date_from', 'date_to', 'search']),
        ]);
    }

    public function show(AuditLog $auditLog): Response
    {
        abort_unless(auth()->user()->hasPermissionTo('audit.view'), 403);

        return Inertia::render('Admin/AuditLogs/Show', [
            'log' => $auditLog,
        ]);
    }
}
