<?php

namespace App\Helpers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LogActivity
{
    public static function logAuthActivity(string $action, Request $request, ?int $userId = null, ?int $appId = null, ?string $module = null): void
    {
        DB::connection('central')->table('activity_logs')->insert([
            'user_id' => $userId,
            'action' => $action,
            'module' => $module ?? '',
            'payload' => json_encode([
                'app_id' => $appId,
                'client_ip' => $request->ip(),
                'client_ips' => $request->ips(),
                'user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
                'request_host' => $request->getHost(),
            ], JSON_THROW_ON_ERROR),
            'ip_address' => $request->ip(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
