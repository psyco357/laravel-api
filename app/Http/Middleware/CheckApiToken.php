<?php

namespace App\Http\Middleware;

use App\Services\JwtService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = app(JwtService::class)->authenticate($request);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Token tidak valid atau telah kadaluarsa'
            ], 401);
        }

        Auth::setUser($user);
        $request->setUserResolver(static fn() => $user);

        return $next($request);
    }
}
