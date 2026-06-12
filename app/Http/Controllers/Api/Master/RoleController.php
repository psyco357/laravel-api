<?php
// app/Http/Controllers/Api/Master/RoleController.php

namespace App\Http\Controllers\Api\Master;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\Role\RoleRequest;
use App\Http\Requests\Master\Role\AssignPermissionRequest;
use App\Models\RoleModel;
use App\Models\Permission;
use App\Helpers\LogActivity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class RoleController extends Controller
{
    /**
     * GET /api/roles
     * List all roles with pagination and filters
     */
    public function index(Request $request): JsonResponse
    {
        // 1. CEK TOKEN VALIDITAS
        $user = Auth::guard('api')->user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Token tidak valid atau expired.',
            ], 401);
        }

        try {
            $query = RoleModel::query();

            // Search
            if ($request->has('search') && $request->search) {
                $query->search($request->search);
            }

            // Filter by has_permissions
            if ($request->has('has_permissions') && $request->has_permissions) {
                $query->has('permissions');
            }

            // Sort
            $sortBy = $request->get('sort_by', 'name');
            $sortOrder = $request->get('sort_order', 'asc');
            $query->orderBy($sortBy, $sortOrder);

            // Pagination
            $perPage = $request->get('per_page', 15);
            $roles = $query->paginate($perPage);

            // Load permissions count for each role
            $roles->loadCount('permissions');
            $roles->loadCount('users');
            // dd($roles);

            return response()->json([
                'success' => true,
                'message' => 'Roles retrieved successfully',
                'data' => $roles->items(),
                'meta' => [
                    'current_page' => $roles->currentPage(),
                    'last_page' => $roles->lastPage(),
                    'per_page' => $roles->perPage(),
                    'total' => $roles->total(),
                ],
            ], 200);
        } catch (\Throwable $e) {
            Log::error('Failed to get roles: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve roles',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * GET /api/roles/all
     * Get all roles (without pagination, for dropdown)
     */
    public function all(Request $request): JsonResponse
    {
        $user = Auth::guard('api')->user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Token tidak valid atau expired.',
            ], 401);
        }

        try {
            $query = RoleModel::query();

            if ($request->has('with_permissions') && $request->with_permissions) {
                $query->with('permissions');
            }

            $roles = $query->orderBy('name')->get();

            return response()->json([
                'success' => true,
                'message' => 'All roles retrieved successfully',
                'data' => $roles,
                'total' => $roles->count()
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve roles',
            ], 500);
        }
    }

    /**
     * POST /api/roles
     * Create new role
     */
    public function store(RoleRequest $request): JsonResponse
    {
        // 1. CEK TOKEN VALIDITAS
        $user = Auth::guard('api')->user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Token tidak valid atau expired.',
            ], 401);
        }

        // 2. CEK DUPLIKASI
        $data = $request->validated();

        $existingRole = RoleModel::where('name', $data['name'])->first();
        if ($existingRole) {
            return response()->json([
                'success' => false,
                'message' => 'Role dengan nama "' . $data['name'] . '" sudah ada.',
            ], 409);
        }

        DB::beginTransaction();

        try {
            // Tambahkan created_by untuk audit trail
            $data['created_by'] = $user->id;
            $data['created_at'] = now();

            $role = RoleModel::create($data);

            // Assign permissions if provided
            if (isset($data['permission_ids']) && !empty($data['permission_ids'])) {
                $role->permissions()->sync($data['permission_ids']);
            }

            DB::commit();

            // Log activity
            LogActivity::logAuthActivity('role.create', request(), $user->id, null, 'role');

            // Load permissions for response
            $role->load('permissions');

            return response()->json([
                'success' => true,
                'message' => 'Role created successfully',
                'data' => $role,
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Failed to create role: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'user_id' => $user->id,
                'data' => $data
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create role',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * GET /api/roles/{id}
     * Get single role detail with its permissions
     */
    public function show($id): JsonResponse
    {
        $user = Auth::guard('api')->user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Token tidak valid atau expired.',
            ], 401);
        }

        try {
            $role = RoleModel::with(['permissions', 'users'])->findOrFail($id);

            // Add counts
            $role->permissions_count = $role->permissions->count();
            $role->users_count = $role->users->count();

            return response()->json([
                'success' => true,
                'message' => 'Role retrieved successfully',
                'data' => $role
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Role not found',
            ], 404);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve role',
            ], 500);
        }
    }

    /**
     * PUT /api/roles/{id}
     * Update role
     */
    public function update(RoleRequest $request, $id): JsonResponse
    {
        $user = Auth::guard('api')->user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Token tidak valid atau expired.',
            ], 401);
        }

        try {
            $role = RoleModel::findOrFail($id);

            // Prevent updating system roles (optional)
            $systemRoles = ['super_admin', 'admin'];
            if (in_array($role->name, $systemRoles) && $role->name !== $request->name) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot modify system role name',
                ], 403);
            }
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Role not found',
            ], 404);
        }

        $data = $request->validated();

        DB::beginTransaction();

        try {
            // Tambahkan updated_by
            $data['updated_by'] = $user->id;
            $data['updated_at'] = now();

            $role->update($data);

            // Update permissions if provided
            if (isset($data['permission_ids'])) {
                $role->permissions()->sync($data['permission_ids']);
            }

            DB::commit();

            // Log activity
            LogActivity::logAuthActivity('role.update', request(), $user->id, null, 'role');

            // Load permissions for response
            $role->load('permissions');

            return response()->json([
                'success' => true,
                'message' => 'Role updated successfully',
                'data' => $role
            ], 200);
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Failed to update role: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to update role',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * DELETE /api/roles/{id}
     * Delete role
     */
    public function destroy($id): JsonResponse
    {
        $user = Auth::guard('api')->user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Token tidak valid atau expired.',
            ], 401);
        }

        try {
            $role = RoleModel::findOrFail($id);

            // Prevent deleting system roles
            $systemRoles = ['super_admin', 'admin'];
            if (in_array($role->name, $systemRoles)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete system role',
                ], 403);
            }
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Role not found',
            ], 404);
        }

        // Check if role has users assigned
        $usersCount = $role->users()->count();
        if ($usersCount > 0) {
            return response()->json([
                'success' => false,
                'message' => "Cannot delete role because it is assigned to {$usersCount} user(s)",
                'data' => [
                    'users_count' => $usersCount
                ]
            ], 400);
        }

        DB::beginTransaction();

        try {
            // Detach all permissions first
            $role->permissions()->detach();

            // Detach all menus (if any)
            if (Schema::hasTable('menu_item_role')) {
                $role->menus()->detach();
            }

            $roleName = $role->display_name;
            $role->delete();

            DB::commit();

            // Log activity
            LogActivity::logAuthActivity('role.delete', request(), $user->id, null, 'role');

            return response()->json([
                'success' => true,
                'message' => 'Role deleted successfully'
            ], 200);
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Failed to delete role: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete role',
            ], 500);
        }
    }

    /**
     * POST /api/roles/bulk-delete
     * Delete multiple roles
     */
    public function bulkDelete(Request $request): JsonResponse
    {
        $user = Auth::guard('api')->user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Token tidak valid atau expired.',
            ], 401);
        }

        $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:role,id'
        ], [
            'ids.required' => 'At least one role ID is required',
            'ids.*.exists' => 'One or more role IDs are invalid',
        ]);

        $deleted = [];
        $failed = [];

        DB::beginTransaction();

        try {
            foreach ($request->ids as $id) {
                $role = RoleModel::find($id);

                if (!$role) {
                    $failed[] = [
                        'id' => $id,
                        'reason' => 'Role not found'
                    ];
                    continue;
                }

                // Skip system roles
                $systemRoles = ['super_admin', 'admin'];
                if (in_array($role->name, $systemRoles)) {
                    $failed[] = [
                        'id' => $id,
                        'name' => $role->display_name,
                        'reason' => 'Cannot delete system role'
                    ];
                    continue;
                }

                // Check if has users
                $usersCount = $role->users()->count();
                if ($usersCount > 0) {
                    $failed[] = [
                        'id' => $id,
                        'name' => $role->display_name,
                        'reason' => "Assigned to {$usersCount} user(s)"
                    ];
                    continue;
                }

                // Detach relations and delete
                $role->permissions()->detach();
                if (Schema::hasTable('menu_item_role')) {
                    $role->menus()->detach();
                }

                $deleted[] = [
                    'id' => $id,
                    'name' => $role->display_name
                ];
                $role->delete();
            }

            DB::commit();

            LogActivity::logAuthActivity('role.bulk_delete', request(), $user->id, null, 'role');

            return response()->json([
                'success' => true,
                'message' => count($deleted) . " role(s) deleted successfully",
                'data' => [
                    'deleted_count' => count($deleted),
                    'failed_count' => count($failed),
                    'deleted' => $deleted,
                    'failed' => $failed
                ]
            ], 200);
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Failed to bulk delete roles: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete roles',
            ], 500);
        }
    }

    /**
     * POST /api/roles/{id}/assign-permissions
     * Assign permissions to role
     */
    public function assignPermissions(AssignPermissionRequest $request, $id): JsonResponse
    {
        $user = Auth::guard('api')->user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Token tidak valid atau expired.',
            ], 401);
        }

        try {
            $role = RoleModel::findOrFail($id);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Role not found',
            ], 404);
        }

        $permissionIds = $request->permission_ids;
        $syncType = $request->get('sync_type', 'sync');

        DB::beginTransaction();

        try {
            switch ($syncType) {
                case 'attach':
                    $role->permissions()->attach($permissionIds);
                    $message = 'Permissions attached to role successfully';
                    break;
                case 'detach':
                    $role->permissions()->detach($permissionIds);
                    $message = 'Permissions detached from role successfully';
                    break;
                default: // sync
                    $role->permissions()->sync($permissionIds);
                    $message = 'Permissions assigned to role successfully';
                    break;
            }

            DB::commit();

            // Log activity
            LogActivity::logAuthActivity('role.assign_permissions', request(), $user->id, null, 'role');

            // Get updated permissions
            $permissions = $role->permissions()->get();

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => [
                    'role_id' => $role->id,
                    'role_name' => $role->name,
                    'permissions' => $permissions,
                    'permissions_count' => $permissions->count()
                ]
            ], 200);
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Failed to assign permissions: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to assign permissions',
            ], 500);
        }
    }

    /**
     * GET /api/roles/{id}/permissions
     * Get permissions of a specific role
     */
    public function getPermissions($id): JsonResponse
    {
        $user = Auth::guard('api')->user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Token tidak valid atau expired.',
            ], 401);
        }

        try {
            $role = RoleModel::with('permissions')->findOrFail($id);

            // Group permissions by group
            $groupedPermissions = $role->permissions->groupBy('group');

            return response()->json([
                'success' => true,
                'message' => 'Role permissions retrieved successfully',
                'data' => [
                    'role' => [
                        'id' => $role->id,
                        'name' => $role->name,
                        'display_name' => $role->display_name,
                    ],
                    'permissions' => $role->permissions,
                    'grouped_permissions' => $groupedPermissions,
                    'total' => $role->permissions->count()
                ]
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Role not found',
            ], 404);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve permissions',
            ], 500);
        }
    }

    /**
     * GET /api/roles/summary
     * Get summary statistics
     */
    public function summary(Request $request): JsonResponse
    {
        $user = Auth::guard('api')->user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Token tidak valid atau expired.',
            ], 401);
        }

        try {
            $totalRoles = RoleModel::count();
            $rolesWithPermissions = RoleModel::has('permissions')->count();
            $rolesWithoutPermissions = $totalRoles - $rolesWithPermissions;

            // Get role with most permissions
            $topRole = RoleModel::withCount('permissions')
                ->orderBy('permissions_count', 'desc')
                ->first();

            // Get most assigned permissions across all roles
            $topPermissions = DB::table('role_has_permissions')
                ->select('permission_id', DB::raw('count(*) as total'))
                ->groupBy('permission_id')
                ->orderBy('total', 'desc')
                ->limit(5)
                ->join('permissions', 'role_has_permissions.permission_id', '=', 'permissions.id')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Role summary retrieved successfully',
                'data' => [
                    'total_roles' => $totalRoles,
                    'roles_with_permissions' => $rolesWithPermissions,
                    'roles_without_permissions' => $rolesWithoutPermissions,
                    'top_role' => $topRole ? [
                        'id' => $topRole->id,
                        'name' => $topRole->name,
                        'display_name' => $topRole->display_name,
                        'permissions_count' => $topRole->permissions_count
                    ] : null,
                    'most_used_permissions' => $topPermissions
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
     * GET /api/roles/user/{userId}
     * Get roles of a specific user
     */
    public function getUserRoles($userId): JsonResponse
    {
        $user = Auth::guard('api')->user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Token tidak valid atau expired.',
            ], 401);
        }

        try {
            $userModel = \App\Models\User::findOrFail($userId);
            $roles = $userModel->roles()->with('permissions')->get();

            return response()->json([
                'success' => true,
                'message' => 'User roles retrieved successfully',
                'data' => [
                    'user_id' => $userId,
                    'username' => $userModel->username,
                    'roles' => $roles,
                    'total' => $roles->count()
                ]
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'User not found',
            ], 404);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve user roles',
            ], 500);
        }
    }
}
