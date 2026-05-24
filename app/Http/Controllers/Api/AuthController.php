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
use Illuminate\Support\Facades\Auth;
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

    public function getActivityLogs(Request $request): JsonResponse
    {
        $user = Auth::guard('api')->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Token tidak valid atau expired.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        if (!$this->canAccessLogs($user->role ?? null)) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden. Only admin can access activity logs.',
            ], Response::HTTP_FORBIDDEN);
        }

        $perPage = max(1, min((int) $request->get('per_page', 15), 100));
        $sortBy = in_array($request->get('sort_by'), ['created_at', 'action', 'module', 'ip_address'], true)
            ? $request->get('sort_by')
            : 'created_at';
        $sortOrder = strtolower((string) $request->get('sort_order', 'desc')) === 'asc' ? 'asc' : 'desc';

        $query = $this->authActivityLogsQuery();

        if ($request->filled('user_id')) {
            $query->where('logs.user_id', (int) $request->get('user_id'));
        }

        if ($request->filled('action')) {
            $query->where('logs.action', (string) $request->get('action'));
        }

        if ($request->filled('ip_address')) {
            $query->where('logs.ip_address', 'like', '%' . trim((string) $request->get('ip_address')) . '%');
        }

        if ($request->filled('date_from')) {
            $query->whereDate('logs.created_at', '>=', (string) $request->get('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('logs.created_at', '<=', (string) $request->get('date_to'));
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->get('search'));

            $query->where(function ($builder) use ($search) {
                $builder->where('logs.action', 'like', "%{$search}%")
                    ->orWhere('logs.module', 'like', "%{$search}%")
                    ->orWhere('logs.ip_address', 'like', "%{$search}%")
                    ->orWhere('users.username', 'like', "%{$search}%")
                    ->orWhere('users.email', 'like', "%{$search}%")
                    ->orWhere('profiles.full_name', 'like', "%{$search}%");
            });
        }

        $logs = $query
            ->select([
                'logs.id',
                'logs.user_id',
                'logs.action',
                'logs.module',
                'logs.payload',
                'logs.ip_address',
                'logs.created_at',
                'logs.updated_at',
                'users.username',
                'users.email',
                'profiles.full_name',
            ])
            ->orderBy('logs.' . $sortBy, $sortOrder)
            ->paginate($perPage)
            ->appends($request->query());

        $logs->getCollection()->transform(function (object $log) {
            $payload = null;

            if (is_string($log->payload) && $log->payload !== '') {
                $decoded = json_decode($log->payload, true);
                $payload = is_array($decoded) ? $decoded : null;
            }

            return [
                'id' => $log->id,
                'action' => $log->action,
                'module' => $log->module,
                'ip_address' => $log->ip_address,
                'created_at' => $log->created_at,
                'updated_at' => $log->updated_at,
                'payload' => $payload,
                'user' => $log->user_id === null ? null : [
                    'id' => $log->user_id,
                    'username' => $log->username,
                    'email' => $log->email,
                    'full_name' => $log->full_name,
                ],
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Activity logs retrieved successfully',
            'data' => $logs->items(),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
            ],
            'filters' => [
                'search' => $request->get('search'),
                'user_id' => $request->get('user_id'),
                'action' => $request->get('action'),
                'ip_address' => $request->get('ip_address'),
                'date_from' => $request->get('date_from'),
                'date_to' => $request->get('date_to'),
            ],
        ]);
    }

    public function getActivitySummary(Request $request): JsonResponse
    {
        $user = Auth::guard('api')->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Token tidak valid atau expired.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        if (!$this->canAccessLogs($user->role ?? null)) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden. Only admin can access activity logs.',
            ], Response::HTTP_FORBIDDEN);
        }

        $baseQuery = $this->authActivityLogsQuery();
        $totalLogs = (clone $baseQuery)->count();
        $todayLogs = (clone $baseQuery)->whereDate('logs.created_at', today())->count();
        $last7DaysLogs = (clone $baseQuery)->where('logs.created_at', '>=', now()->subDays(7))->count();
        $uniqueUsers = (clone $baseQuery)->whereNotNull('logs.user_id')->distinct('logs.user_id')->count('logs.user_id');
        $uniqueIpAddresses = (clone $baseQuery)->whereNotNull('logs.ip_address')->distinct('logs.ip_address')->count('logs.ip_address');

        $actions = (clone $baseQuery)
            ->select('logs.action', DB::raw('COUNT(*) as total'))
            ->groupBy('logs.action')
            ->orderByDesc('total')
            ->get();

        $dailyActivity = (clone $baseQuery)
            ->selectRaw('DATE(logs.created_at) as activity_date, COUNT(*) as total')
            ->where('logs.created_at', '>=', now()->subDays(6)->startOfDay())
            ->groupBy(DB::raw('DATE(logs.created_at)'))
            ->orderBy('activity_date')
            ->get();

        $topUsers = (clone $baseQuery)
            ->whereNotNull('logs.user_id')
            ->select([
                'logs.user_id',
                'users.username',
                'users.email',
                'profiles.full_name',
                DB::raw('COUNT(*) as total'),
            ])
            ->groupBy('logs.user_id', 'users.username', 'users.email', 'profiles.full_name')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        $latestLog = (clone $baseQuery)
            ->select([
                'logs.id',
                'logs.action',
                'logs.module',
                'logs.ip_address',
                'logs.created_at',
                'users.username',
                'profiles.full_name',
            ])
            ->orderByDesc('logs.created_at')
            ->first();

        return response()->json([
            'success' => true,
            'message' => 'Activity log summary retrieved successfully',
            'data' => [
                'total_logs' => $totalLogs,
                'today_logs' => $todayLogs,
                'last_7_days_logs' => $last7DaysLogs,
                'unique_users' => $uniqueUsers,
                'unique_ip_addresses' => $uniqueIpAddresses,
                'actions' => $actions,
                'daily_activity' => $dailyActivity,
                'top_users' => $topUsers,
                'latest_log' => $latestLog,
            ],
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

    private function authActivityLogsQuery(): \Illuminate\Database\Query\Builder
    {
        return DB::connection('central')
            ->table('activity_logs as logs')
            ->leftJoin('mst_user as users', 'users.id', '=', 'logs.user_id')
            ->leftJoin('mst_profiles as profiles', 'profiles.user_id', '=', 'users.id')
            ->where(function ($query) {
                $query->where('logs.module', 'auth')
                    ->orWhere('logs.action', 'like', 'auth.%');
            });
    }

    private function canAccessLogs(?string $role): bool
    {
        return in_array($role, ['admin', 'superadmin'], true);
    }
}
