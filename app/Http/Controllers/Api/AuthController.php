<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\RefreshToken;
use App\Models\User;
use App\Services\JwtService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use App\Helpers\LogActivity;


class AuthController extends Controller
{
    public function __construct(private readonly JwtService $jwtService) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $app = $request->attributes->get('validated_app');

        $payload = $request->validated();

        $user = DB::connection('central')->transaction(function () use ($payload) {
            $user = User::create([
                'username' => $payload['username'],
                'email' => $payload['email'],
                'email_verified' => false,
                'password' => bcrypt($payload['password']),
                'is_active' => true,
            ]);

            DB::connection('central')->table('mst_profiles')->insert([
                'user_id' => $user->id,
                'nik' => $payload['nik'] ?? null,
                'full_name' => $payload['full_name'],
                'avatar' => null,
                'phone' => $payload['phone'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $user;
        });

        $this->attachUserToApp($user->id, $app->id, 'viewer');

        // Log dengan informasi app dan connection
        LogActivity::logAuthActivity('auth.register', $request, $user->id, (int) $app->id, 'auth');

        $userApps = $this->loadUserApps($user->id);
        $tokens = $this->jwtService->issueTokenPair($user, (int) $app->id, $payload['device_name'] ?? null, $request);

        return response()->json([
            'message' => 'User registered successfully.',
            'user' => $user->fresh(),
            'apps' => $userApps,
            'active_app' => $userApps->firstWhere('id', $app->id),
            'app_info' => [
                'code' => $app->app_code,
                'name' => trim($app->app_first_name . ' ' . $app->app_last_name),
            ],
            ...$tokens,
        ], Response::HTTP_CREATED);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $app = $request->attributes->get('validated_app');

        $payload = $request->validated();

        $user = User::query()
            ->where('email', $payload['login'])
            ->orWhere('username', $payload['login'])
            ->first();

        if (!$user || !\Illuminate\Support\Facades\Hash::check($payload['password'], $user->password)) {
            return response()->json([
                'message' => 'The provided credentials are incorrect.'
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $hasAccess = DB::connection('central')
            ->table('user_has_apps')
            ->where('user_id', $user->id)
            ->where('app_id', $app->id)
            ->where('is_active', true)
            ->exists();

        if (!$hasAccess) {
            return response()->json([
                'message' => 'You do not have access to this application.'
            ], Response::HTTP_FORBIDDEN);
        }

        $tokens = $this->jwtService->issueTokenPair($user, (int) $app->id, $payload['device_name'] ?? null, $request);

        LogActivity::logAuthActivity('auth.login', $request, $user->id, (int) $app->id, 'auth');

        return response()->json([
            'message' => 'Login successful.',
            'user' => $user,
            ...$tokens,
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'user' => $user,
            'apps' => $this->loadUserApps((int) $user->id),
            'claims' => $request->attributes->get('jwt_claims'),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        $claims = (array) $request->attributes->get('jwt_claims', []);

        if ($user) {
            $this->jwtService->revokeSession($claims['sid'] ?? null, (int) $user->id);
        }

        return response()->json([
            'message' => 'Logout successful.',
        ]);
    }

    public function refresh(Request $request): JsonResponse
    {
        $app = $request->attributes->get('validated_app');

        $payload = $request->validate([
            'refresh_token' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        $tokens = $this->jwtService->refreshToken(
            $payload['refresh_token'],
            (int) $app->id,
            $payload['device_name'] ?? null,
            $request,
        );

        return response()->json([
            'message' => 'Token refreshed successfully.',
            ...$tokens,
        ]);
    }

    public function sessions(Request $request): JsonResponse
    {
        $app = $request->attributes->get('validated_app');

        $sessions = RefreshToken::query()
            ->where('user_id', (int) $request->user()->id)
            ->where('app_id', (int) $app->id)
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->orderByDesc('created_at')
            ->get([
                'session_id',
                'device_name',
                'ip_address',
                'user_agent',
                'last_used_at',
                'expires_at',
                'created_at',
            ]);

        return response()->json([
            'data' => $sessions,
        ]);
    }

    private function attachUserToApp(int $userId, int $appId, string $defaultRoleName): void
    {
        $connection = DB::connection('central');
        $roleId = $connection->table('role')->where('name', $defaultRoleName)->value('id');

        $connection->table('user_has_apps')->updateOrInsert(
            [
                'user_id' => $userId,
                'app_id' => $appId,
            ],
            [
                'role_id' => $roleId,
                'is_active' => true,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    private function loadUserApps(int $userId): Collection
    {
        return DB::connection('central')->table('user_has_apps')
            ->join('mst_app', 'mst_app.id', '=', 'user_has_apps.app_id')
            ->leftJoin('role', 'role.id', '=', 'user_has_apps.role_id')
            ->where('user_has_apps.user_id', $userId)
            ->where('user_has_apps.is_active', true)
            ->where('mst_app.is_active', true)
            ->whereNull('mst_app.deleted_at')
            ->orderBy('mst_app.id')
            ->get([
                'mst_app.id',
                'mst_app.app_first_name',
                'mst_app.app_last_name',
                'mst_app.app_url',
                'role.id as role_id',
                'role.name as role_name',
                'role.display_name as role_display_name',
            ])
            ->map(function (object $app) {
                return [
                    'id' => $app->id,
                    'name' => trim(collect([$app->app_first_name, $app->app_last_name])->filter()->implode(' ')),
                    'url' => $app->app_url,
                    'role' => $app->role_id === null ? null : [
                        'id' => $app->role_id,
                        'name' => $app->role_name,
                        'display_name' => $app->role_display_name,
                    ],
                ];
            })
            ->values();
    }
}
