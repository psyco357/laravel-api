<?php
// app/Http/Controllers/Api/Master/UserController.php

namespace App\Http\Controllers\Api\Master;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\User\StoreUserRequest;
use App\Http\Requests\Master\User\UpdateUserRequest;
use App\Http\Requests\Master\User\ChangePasswordRequest;
use App\Http\Requests\Master\User\AssignAppRequest;
use App\Http\Requests\Master\User\AssignRoleRequest;
use App\Models\User;
use App\Models\UserProfile;
use App\Helpers\LogActivity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class UserController extends Controller
{
    /**
     * GET /api/users
     * List all users with pagination and filters
     */
    public function index(Request $request): JsonResponse
    {
        // 1. CEK TOKEN VALIDITAS
        $user = $this->authenticatedUser();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Token tidak valid atau expired.',
            ], 401);
        }

        try {
            $query = User::with('profile', 'roles', 'apps');

            // Search by username, email, or full name
            if ($request->has('search') && $request->search) {
                $query->search($request->search);
            }

            // Filter by status
            if ($request->has('is_active') && $request->is_active !== '') {
                $query->where('is_active', $request->is_active);
            }

            // Filter by email verified
            if ($request->has('email_verified') && $request->email_verified !== '') {
                $query->where('email_verified', $request->email_verified);
            }

            // Filter by role
            if ($request->has('role_id') && $request->role_id) {
                $query->whereHas('roles', function ($q) use ($request) {
                    $q->where('role_id', $request->role_id);
                });
            }

            // Filter by app
            if ($request->has('app_id') && $request->app_id) {
                $query->whereHas('apps', function ($q) use ($request) {
                    $q->where('app_id', $request->app_id);
                });
            }

            // Sort
            $sortBy = $request->get('sort_by', 'created_at');
            $sortOrder = $request->get('sort_order', 'desc');

            if ($sortBy === 'full_name') {
                $query->join('mst_profiles', 'mst_user.id', '=', 'mst_profiles.user_id')
                    ->orderBy('mst_profiles.full_name', $sortOrder)
                    ->select('mst_user.*');
            } else {
                $query->orderBy($sortBy, $sortOrder);
            }

            // Pagination
            $perPage = $request->get('per_page', 15);
            $users = $query->paginate($perPage);

            // Transform data
            $users->getCollection()->transform(function ($user) {
                return $this->transformUserData($user);
            });

            return response()->json([
                'success' => true,
                'message' => 'Users retrieved successfully',
                'data' => $users->items(),
                'meta' => [
                    'current_page' => $users->currentPage(),
                    'last_page' => $users->lastPage(),
                    'per_page' => $users->perPage(),
                    'total' => $users->total(),
                ],
            ], 200);
        } catch (\Throwable $e) {
            Log::error('Failed to get users: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve users',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * POST /api/users
     * Create new user
     */
    public function store(StoreUserRequest $request): JsonResponse
    {
        $authUser = $this->authenticatedUser();
        if (!$authUser) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Token tidak valid atau expired.',
            ], 401);
        }

        DB::beginTransaction();

        try {
            // Create user
            $userData = [
                'username' => $request->username,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'is_active' => $request->is_active ?? true,
                'email_verified' => $request->email_verified ?? false,
                // 'created_by' => $authUser->id,
                'created_at' => now(),
            ];

            $user = User::create($userData);

            // Create profile
            $profileData = [
                'user_id' => $user->id,
                'full_name' => $request->full_name,
                'nik' => $request->nik,
                'phone' => $request->phone,
            ];

            // Handle avatar upload
            if ($request->hasFile('avatar')) {
                $avatarPath = $request->file('avatar')->store('avatars', 'public');
                $profileData['avatar'] = basename($avatarPath);
            }

            UserProfile::create($profileData);

            // Assign roles
            if ($request->has('role_ids') && !empty($request->role_ids)) {
                $user->roles()->sync($request->role_ids);
            }

            // Assign apps
            if ($request->has('app_ids') && !empty($request->app_ids)) {
                $appData = [];
                foreach ($request->app_ids as $appId) {
                    $appData[$appId] = [
                        'is_active' => true,
                        'role_id' => $request->default_app_role_id ?? null,
                    ];
                }
                $user->apps()->sync($appData);
            }

            DB::commit();

            // Log activity
            LogActivity::logAuthActivity('user.create', request(), $authUser->id, null, 'user');

            // Load relationships
            $user->load('profile', 'roles', 'apps');
            $userData = $this->transformUserData($user);

            return response()->json([
                'success' => true,
                'message' => 'User created successfully',
                'data' => $userData,
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();

            // Delete uploaded avatar if exists
            if (isset($avatarPath) && Storage::disk('public')->exists($avatarPath)) {
                Storage::disk('public')->delete($avatarPath);
            }

            Log::error('Failed to create user: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'user_id' => $authUser->id,
                'data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create user',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * GET /api/users/{id}
     * Get single user detail
     */
    public function show($id): JsonResponse
    {
        $authUser = $this->authenticatedUser();
        if (!$authUser) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Token tidak valid atau expired.',
            ], 401);
        }

        try {
            $user = User::with('profile', 'roles', 'apps', 'userApps.role')
                ->findOrFail($id);

            $userData = $this->transformUserData($user);

            return response()->json([
                'success' => true,
                'message' => 'User retrieved successfully',
                'data' => $userData
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'User not found',
            ], 404);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve user',
            ], 500);
        }
    }

    /**
     * PUT /api/users/{id}
     * Update user
     */
    public function update(UpdateUserRequest $request, $id): JsonResponse
    {
        $authUser = $this->authenticatedUser();
        if (!$authUser) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Token tidak valid atau expired.',
            ], 401);
        }

        try {
            $user = User::findOrFail($id);

            // Prevent updating own status if trying to deactivate self
            if ($user->id === $authUser->id && $request->has('is_active') && !$request->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'You cannot deactivate your own account',
                ], 403);
            }
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'User not found',
            ], 404);
        }

        DB::beginTransaction();

        try {
            // Update user
            $userData = [
                'username' => $request->username,
                'email' => $request->email,
                'is_active' => $request->is_active ?? $user->is_active,
                'email_verified' => $request->email_verified ?? $user->email_verified,
                'updated_by' => $authUser->id,
                'updated_at' => now(),
            ];

            // Update password if provided
            if ($request->filled('password')) {
                $userData['password'] = Hash::make($request->password);
            }

            $user->update($userData);

            // Update or create profile
            $profileData = [
                'full_name' => $request->full_name,
                'nik' => $request->nik,
                'phone' => $request->phone,
            ];

            // Handle avatar
            if ($request->hasFile('avatar')) {
                // Delete old avatar
                if ($user->profile && $user->profile->avatar) {
                    Storage::disk('public')->delete('avatars/' . $user->profile->avatar);
                }
                $avatarPath = $request->file('avatar')->store('avatars', 'public');
                $profileData['avatar'] = basename($avatarPath);
            }

            // Remove avatar if requested
            if ($request->remove_avatar && $user->profile && $user->profile->avatar) {
                Storage::disk('public')->delete('avatars/' . $user->profile->avatar);
                $profileData['avatar'] = null;
            }

            if ($user->profile) {
                $user->profile->update($profileData);
            } else {
                $profileData['user_id'] = $user->id;
                UserProfile::create($profileData);
            }

            // Update roles if provided
            if ($request->has('role_ids')) {
                $user->roles()->sync($request->role_ids);
            }

            // Update apps if provided
            if ($request->has('app_ids')) {
                $appData = [];
                foreach ($request->app_ids as $appId) {
                    $existingApp = $user->apps()->where('app_id', $appId)->first();
                    $appData[$appId] = [
                        'is_active' => $existingApp ? $existingApp->pivot->is_active : true,
                        'role_id' => $existingApp ? $existingApp->pivot->role_id : null,
                    ];
                }
                $user->apps()->sync($appData);
            }

            DB::commit();

            // Log activity
            LogActivity::logAuthActivity('user.update', request(), $authUser->id, null, 'user');

            // Load relationships
            $user->load('profile', 'roles', 'apps');
            $userData = $this->transformUserData($user);

            return response()->json([
                'success' => true,
                'message' => 'User updated successfully',
                'data' => $userData
            ], 200);
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Failed to update user: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to update user',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * DELETE /api/users/{id}
     * Soft delete user
     */
    public function destroy($id): JsonResponse
    {
        $authUser = $this->authenticatedUser();
        if (!$authUser) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Token tidak valid atau expired.',
            ], 401);
        }

        try {
            $user = User::findOrFail($id);

            // Prevent deleting self
            if ($user->id === $authUser->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'You cannot delete your own account',
                ], 403);
            }
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'User not found',
            ], 404);
        }

        DB::beginTransaction();

        try {
            $username = $user->username;
            $user->delete();

            DB::commit();

            LogActivity::logAuthActivity('user.delete', request(), $authUser->id, null, 'user');

            return response()->json([
                'success' => true,
                'message' => 'User deleted successfully'
            ], 200);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete user',
            ], 500);
        }
    }

    /**
     * POST /api/users/{id}/change-password
     * Change user password
     */
    public function changePassword(ChangePasswordRequest $request, $id): JsonResponse
    {
        $authUser = $this->authenticatedUser();
        if (!$authUser) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Token tidak valid atau expired.',
            ], 401);
        }

        // Check if user is changing own password or has permission
        $isOwnAccount = ($authUser->id == $id);
        $hasPermission = $authUser->hasPermission('change_user_password');

        if (!$isOwnAccount && !$hasPermission) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to change this user\'s password',
            ], 403);
        }

        try {
            $user = User::findOrFail($id);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'User not found',
            ], 404);
        }

        // Verify current password if changing own password
        if ($isOwnAccount && !Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Current password is incorrect',
            ], 400);
        }

        DB::beginTransaction();

        try {
            $user->password = Hash::make($request->new_password);
            $user->updated_by = $authUser->id;
            $user->save();

            DB::commit();

            LogActivity::logAuthActivity('user.change_password', request(), $authUser->id, null, 'user');

            return response()->json([
                'success' => true,
                'message' => 'Password changed successfully'
            ], 200);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to change password',
            ], 500);
        }
    }

    /**
     * POST /api/users/{id}/assign-apps
     * Assign apps to user
     */
    public function assignApps(AssignAppRequest $request, $id): JsonResponse
    {
        $authUser = $this->authenticatedUser();
        if (!$authUser) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Token tidak valid atau expired.',
            ], 401);
        }

        try {
            $user = User::findOrFail($id);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'User not found',
            ], 404);
        }

        $apps = $request->apps;
        $syncType = $request->get('sync_type', 'sync');

        DB::beginTransaction();

        try {
            $appData = [];
            foreach ($apps as $app) {
                $appData[$app['app_id']] = [
                    'role_id' => $app['role_id'] ?? null,
                    'is_active' => $app['is_active'] ?? true,
                ];
            }

            switch ($syncType) {
                case 'attach':
                    $user->apps()->attach($appData);
                    $message = 'Apps assigned to user successfully';
                    break;
                case 'detach':
                    $appIds = array_keys($appData);
                    $user->apps()->detach($appIds);
                    $message = 'Apps detached from user successfully';
                    break;
                default: // sync
                    $user->apps()->sync($appData);
                    $message = 'Apps synced successfully';
                    break;
            }

            DB::commit();

            LogActivity::logAuthActivity('user.assign_apps', request(), $authUser->id, null, 'user');

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => [
                    'user_id' => $user->id,
                    'apps' => $user->apps()->withPivot('role_id', 'is_active')->get()
                ]
            ], 200);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to assign apps',
            ], 500);
        }
    }

    /**
     * POST /api/users/{id}/assign-roles
     * Assign roles to user
     */
    public function assignRoles(AssignRoleRequest $request, $id): JsonResponse
    {
        $authUser = $this->authenticatedUser();
        if (!$authUser) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Token tidak valid atau expired.',
            ], 401);
        }

        try {
            $user = User::findOrFail($id);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'User not found',
            ], 404);
        }

        $roleIds = $request->role_ids;
        $syncType = $request->get('sync_type', 'sync');

        DB::beginTransaction();

        try {
            switch ($syncType) {
                case 'attach':
                    $user->roles()->attach($roleIds);
                    $message = 'Roles assigned to user successfully';
                    break;
                case 'detach':
                    $user->roles()->detach($roleIds);
                    $message = 'Roles detached from user successfully';
                    break;
                default: // sync
                    $user->roles()->sync($roleIds);
                    $message = 'Roles synced successfully';
                    break;
            }

            DB::commit();

            LogActivity::logAuthActivity('user.assign_roles', request(), $authUser->id, null, 'user');

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => [
                    'user_id' => $user->id,
                    'roles' => $user->roles()->get()
                ]
            ], 200);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to assign roles',
            ], 500);
        }
    }

    /**
     * GET /api/users/{id}/apps
     * Get user's apps
     */
    public function getUserApps($id): JsonResponse
    {
        $authUser = $this->authenticatedUser();
        if (!$authUser) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Token tidak valid atau expired.',
            ], 401);
        }

        try {
            $user = User::with(['userApps.app', 'userApps.role'])->findOrFail($id);

            return response()->json([
                'success' => true,
                'message' => 'User apps retrieved successfully',
                'data' => [
                    'user_id' => $user->id,
                    'username' => $user->username,
                    'apps' => $user->userApps->map(function ($userApp) {
                        return [
                            'app_id' => $userApp->app_id,
                            'app_name' => trim(($userApp->app->app_first_name ?? '') . ' ' . ($userApp->app->app_last_name ?? '')),
                            'app_code' => $userApp->app->app_code ?? null,
                            'role_id' => $userApp->role_id,
                            'role_name' => $userApp->role->display_name ?? null,
                            'is_active' => $userApp->is_active,
                            'created_at' => $userApp->created_at,
                        ];
                    })
                ]
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'User not found',
            ], 404);
        }
    }

    /**
     * GET /api/users/summary
     * Get summary statistics
     */
    public function summary(Request $request): JsonResponse
    {
        $authUser = $this->authenticatedUser();
        if (!$authUser) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Token tidak valid atau expired.',
            ], 401);
        }

        try {
            $totalUsers = User::count();
            $activeUsers = User::where('is_active', true)->count();
            $inactiveUsers = $totalUsers - $activeUsers;
            $verifiedEmails = User::where('email_verified', true)->count();
            $deletedUsers = User::onlyTrashed()->count();

            // Users by role
            $usersByRole = DB::table('model_has_roles')
                ->join('role', 'model_has_roles.role_id', '=', 'role.id')
                ->select('role.name', 'role.display_name', DB::raw('count(*) as total'))
                ->groupBy('role.id', 'role.name', 'role.display_name')
                ->get();

            // Recent users (last 7 days)
            $recentUsers = User::where('created_at', '>=', now()->subDays(7))
                ->count();

            return response()->json([
                'success' => true,
                'message' => 'User summary retrieved successfully',
                'data' => [
                    'total_users' => $totalUsers,
                    'active_users' => $activeUsers,
                    'inactive_users' => $inactiveUsers,
                    'verified_emails' => $verifiedEmails,
                    'deleted_users' => $deletedUsers,
                    'recent_users_7days' => $recentUsers,
                    'users_by_role' => $usersByRole,
                ]
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve summary',
            ], 500);
        }
    }

    /**
     * POST /api/users/bulk-delete
     * Bulk soft delete users
     */
    public function bulkDelete(Request $request): JsonResponse
    {
        $authUser = $this->authenticatedUser();
        if (!$authUser) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Token tidak valid atau expired.',
            ], 401);
        }

        $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:mst_user,id'
        ]);

        // Remove current user from ids
        $ids = array_filter($request->ids, function ($id) use ($authUser) {
            return $id != $authUser->id;
        });

        if (empty($ids)) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete your own account',
            ], 400);
        }

        DB::beginTransaction();

        try {
            $deletedCount = User::whereIn('id', $ids)->delete();

            DB::commit();

            LogActivity::logAuthActivity('user.bulk_delete', request(), $authUser->id, null, 'user');

            return response()->json([
                'success' => true,
                'message' => "{$deletedCount} user(s) deleted successfully",
                'data' => [
                    'deleted_count' => $deletedCount
                ]
            ], 200);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete users',
            ], 500);
        }
    }

    private function authenticatedUser(): ?User
    {
        $user = Auth::guard('api')->user();

        return $user instanceof User ? $user : null;
    }

    /**
     * Transform user data for response
     */
    private function transformUserData($user)
    {
        return [
            'id' => $user->id,
            'username' => $user->username,
            'email' => $user->email,
            'email_verified' => $user->email_verified,
            'is_active' => $user->is_active,
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
            'deleted_at' => $user->deleted_at,
            'profile' => $user->profile ? [
                'nik' => $user->profile->nik,
                'full_name' => $user->profile->full_name,
                'avatar' => $user->profile->avatar,
                'avatar_url' => $user->profile->avatar_url,
                'phone' => $user->profile->phone,
            ] : null,
            'roles' => $user->roles->map(function ($role) {
                return [
                    'id' => $role->id,
                    'name' => $role->name,
                    'display_name' => $role->display_name,
                ];
            }),
            'apps' => $user->apps->map(function ($app) {
                return [
                    'id' => $app->id,
                    'app_code' => $app->app_code,
                    'app_name' => $app->app_first_name . ' ' . $app->app_last_name,
                    'pivot' => [
                        'role_id' => $app->pivot->role_id,
                        'is_active' => $app->pivot->is_active,
                    ]
                ];
            }),
        ];
    }
}
