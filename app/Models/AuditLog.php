<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuditLog extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_type', 'user_id', 'user_name', 'action', 'module',
        'description', 'old_values', 'new_values', 'ip_address', 'user_agent', 'url',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
        ];
    }

    public static function record(
        string $action,
        string $module,
        string $description,
        array $oldValues = [],
        array $newValues = []
    ): void {
        $request = app(Request::class);

        $user     = Auth::guard('web')->user() ?? Auth::guard('customer')->user();
        $userType = $user instanceof Customer ? Customer::class : User::class;

        static::create([
            'user_type'   => $user ? $userType : null,
            'user_id'     => $user?->id,
            'user_name'   => $user?->name,
            'action'      => $action,
            'module'      => $module,
            'description' => $description,
            'old_values'  => $oldValues ?: null,
            'new_values'  => $newValues ?: null,
            'ip_address'  => $request->ip(),
            'user_agent'  => $request->userAgent(),
            'url'         => $request->fullUrl(),
        ]);
    }
}
