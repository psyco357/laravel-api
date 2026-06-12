<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PermissionMiddleware
{
    public function handle(Request $request, Closure $next, string $permissions): Response|JsonResponse
    {
        $user = $request->user();
        $requiredPermissions = array_values(array_filter(array_map('trim', explode('|', $permissions))));

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Token not provided or invalid.',
                'errors' => [
                    'auth' => ['Access token is required or invalid.'],
                ],
            ], 401);
        }

        foreach ($requiredPermissions as $permission) {
            if ($user->can($permission)) {
                return $next($request);
            }
        }

        return response()->json([
            'success' => false,
            'message' => 'Forbidden. You do not have the required permission.',
            'errors' => [
                'permission' => ['You do not have the required permission.'],
            ],
            'required_permissions' => $requiredPermissions,
        ], 403);
    }
}
