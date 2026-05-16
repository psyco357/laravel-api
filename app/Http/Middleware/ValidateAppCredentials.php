<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Log;

class ValidateAppCredentials
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Ambil credentials dari header
        $apiKey = $request->header('X-API-Key');
        $appLicense = $request->header('X-App-License');

        // Validasi kedua header harus ada
        if (empty($apiKey) || empty($appLicense)) {
            return response()->json([
                'success' => false,
                'message' => 'Missing required headers',
                'errors' => [
                    'X-API-Key' => empty($apiKey) ? 'The X-API-Key header is required.' : null,
                    'X-App-License' => empty($appLicense) ? 'The X-App-License header is required.' : null,
                ]
            ], Response::HTTP_BAD_REQUEST);
        }

        // Cari app berdasarkan API Key dan License
        $app = DB::connection('central')
            ->table('mst_app')
            ->select('id', 'app_code', 'app_first_name', 'app_last_name', 'app_url', 'connection_id')
            ->where('api_key', $apiKey)
            ->where('app_license', $appLicense)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->first();

        // Jika app tidak ditemukan
        if (!$app) {
            // Log attempt dengan credential invalid
            Log::channel('daily')->warning('Invalid app credentials attempt', [
                'api_key' => substr($apiKey, 0, 10) . '...',
                'license' => substr($appLicense, 0, 10) . '...',
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'timestamp' => now()->toDateTimeString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Invalid or inactive application credentials.',
                'errors' => [
                    'credentials' => 'The provided API Key and App License do not match any active application.'
                ]
            ], Response::HTTP_UNAUTHORIZED);
        }

        // Validasi koneksi database app masih aktif
        $connection = DB::connection('central')
            ->table('mst_connection')
            ->where('id', $app->connection_id)
            ->where('is_active', true)
            ->first();

        if (!$connection) {
            return response()->json([
                'success' => false,
                'message' => 'Application database connection is inactive.',
                'errors' => [
                    'connection' => 'The database connection for this application is not active.'
                ]
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        // Simpan informasi app yang valid ke request untuk digunakan di controller
        $request->attributes->set('validated_app', $app);
        $request->attributes->set('validated_connection', $connection);

        // Optional: Set header untuk response
        $request->attributes->set('app_code', $app->app_code);

        return $next($request);
    }
}
