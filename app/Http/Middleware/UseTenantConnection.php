<?php

namespace App\Http\Middleware;

use App\Models\MstConnection;
use App\Support\TenantConnectionManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class UseTenantConnection
{
    public function __construct(
        protected TenantConnectionManager $tenantConnectionManager,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $connectionId = $request->header('X-Tenant-Connection')
            ?? $request->input('tenant_connection_id')
            ?? $request->route('tenantConnection');

        if (blank($connectionId)) {
            $this->tenantConnectionManager->useCentralConnection();

            return $next($request);
        }

        $connection = MstConnection::query()
            ->whereKey($connectionId)
            ->where('is_active', true)
            ->first();

        if ($connection === null) {
            abort(Response::HTTP_NOT_FOUND, 'Tenant connection is not registered or inactive.');
        }

        $this->tenantConnectionManager->useTenantConnection($connection);

        return $next($request);
    }
}
