<?php
// app/Http/Controllers/Api/Master/PermissionController.php

namespace App\Http\Controllers\Api\Master;

use App\Helpers\LogActivity;
use App\Http\Controllers\Controller;
use App\Http\Requests\Master\Permission\BulkDeletePermissionRequest;
use App\Http\Requests\Master\Permission\StorePermissionRequest;
use App\Http\Requests\Master\Permission\UpdatePermissionRequest;
use App\Http\Resources\Permissions\PermissionCollection;
use App\Http\Resources\Permissions\PermissionResource;
use App\Models\PermissionModel;
use App\Models\RoleModel;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;


class PermissionController extends Controller
{
    /**
     * Constructor - Apply middleware
     */
    public function __construct()
    {
        $this->middleware('auth:api');
        $this->middleware('permission:view_permission')->only(['index', 'show', 'getGroups', 'getGrouped']);
        $this->middleware('permission:create_permission')->only(['store']);
        $this->middleware('permission:edit_permission')->only(['update']);
        $this->middleware('permission:delete_permission')->only(['destroy', 'bulkDelete']);
    }

    /**
     * GET /api/permissions
     * Get list of permissions with pagination and filters
     */
    public function index(Request $request): JsonResponse
    {
        $query = PermissionModel::query();

        // Filter by group
        if ($request->has('group') && $request->group) {
            $query->byGroup($request->group);
        }

        // Search by name or display_name
        if ($request->has('search') && $request->search) {
            $query->search($request->search);
        }

        // Sort
        $sortField = $request->get('sort_by', 'name');
        $sortOrder = $request->get('sort_order', 'asc');
        $query->orderBy($sortField, $sortOrder);

        // Pagination
        $perPage = $request->get('per_page', 15);
        $permissions = $query->paginate($perPage);

        // Load roles count
        $permissions->loadCount('roles');

        return response()->json([
            'success' => true,
            'message' => 'Permissions retrieved successfully',
            'data' => new PermissionCollection($permissions)
        ], 200);
    }

    /**
     * GET /api/permissions/all
     * Get all permissions without pagination (for dropdown/selection)
     */
    public function all(Request $request): JsonResponse
    {
        $query = PermissionModel::query();

        if ($request->has('group') && $request->group) {
            $query->byGroup($request->group);
        }

        $permissions = $query->orderBy('group')->orderBy('name')->get();

        return response()->json([
            'success' => true,
            'message' => 'All permissions retrieved successfully',
            'data' => PermissionResource::collection($permissions),
            'total' => $permissions->count()
        ], 200);
    }

    /**
     * GET /api/permissions/groups
     * Get all unique groups
     */
    public function getGroups(): JsonResponse
    {
        $groups = PermissionModel::select('group')
            ->whereNotNull('group')
            ->distinct()
            ->orderBy('group')
            ->pluck('group');

        return response()->json([
            'success' => true,
            'message' => 'Groups retrieved successfully',
            'data' => $groups
        ], 200);
    }

    /**
     * GET /api/permissions/grouped
     * Get permissions grouped by group
     */
    public function getGrouped(): JsonResponse
    {
        $permissions = PermissionModel::withCount('roles')
            ->orderBy('group')
            ->orderBy('name')
            ->get();

        $grouped = $permissions->groupBy('group')->map(function ($items, $group) {
            return [
                'group' => $group ?: 'Ungrouped',
                'permissions' => PermissionResource::collection($items),
                'total' => $items->count()
            ];
        })->values();

        return response()->json([
            'success' => true,
            'message' => 'Grouped permissions retrieved successfully',
            'data' => $grouped
        ], 200);
    }

    /**
     * POST /api/permissions
     * Create a new permission
     */
    public function store(StorePermissionRequest $request): JsonResponse
    {
        $permission = PermissionModel::create([
            'name' => $request->name,
            'display_name' => $request->display_name,
            'group' => $request->group,
        ]);

        // Log activity
        $this->logActivity('create', 'permissions', 'Created permission: ' . $permission->name);

        return response()->json([
            'success' => true,
            'message' => 'Permission created successfully',
            'data' => new PermissionResource($permission)
        ], 201);
    }

    /**
     * GET /api/permissions/{id}
     * Get single permission details
     */
    public function show($id): JsonResponse
    {
        $permission = PermissionModel::with('roles')->findOrFail($id);

        return response()->json([
            'success' => true,
            'message' => 'Permission retrieved successfully',
            'data' => new PermissionResource($permission)
        ], 200);
    }

    /**
     * PUT /api/permissions/{id}
     * Update permission
     */
    public function update(UpdatePermissionRequest $request, $id): JsonResponse
    {
        $permission = PermissionModel::findOrFail($id);

        $oldName = $permission->name;
        $permission->update([
            'name' => $request->name,
            'display_name' => $request->display_name,
            'group' => $request->group,
        ]);

        // Log activity
        $this->logActivity('update', 'permissions', 'Updated permission: ' . $oldName . ' to ' . $permission->name);

        return response()->json([
            'success' => true,
            'message' => 'Permission updated successfully',
            'data' => new PermissionResource($permission)
        ], 200);
    }

    /**
     * DELETE /api/permissions/{id}
     * Delete single permission
     */
    public function destroy($id): JsonResponse
    {
        $permission = PermissionModel::findOrFail($id);

        // Check if permission is assigned to any role
        $rolesCount = $permission->roles()->count();
        if ($rolesCount > 0) {
            return response()->json([
                'success' => false,
                'message' => "Cannot delete permission because it is assigned to {$rolesCount} role(s)",
                'data' => [
                    'roles_count' => $rolesCount,
                    'roles' => $permission->roles->pluck('name')
                ]
            ], 400);
        }

        $permissionName = $permission->display_name;
        $permission->delete();

        // Log activity
        $this->logActivity('delete', 'permissions', 'Deleted permission: ' . $permissionName);

        return response()->json([
            'success' => true,
            'message' => 'Permission deleted successfully'
        ], 200);
    }

    /**
     * POST /api/permissions/bulk-delete
     * Delete multiple permissions
     */
    public function bulkDelete(BulkDeletePermissionRequest $request): JsonResponse
    {
        $deleted = [];
        $failed = [];

        foreach ($request->ids as $id) {
            $permission = PermissionModel::find($id);
            if ($permission && $permission->roles()->count() === 0) {
                $deleted[] = [
                    'id' => $id,
                    'name' => $permission->display_name
                ];
                $permission->delete();
            } else {
                $failed[] = [
                    'id' => $id,
                    'name' => $permission->display_name ?? 'Unknown',
                    'reason' => $permission && $permission->roles()->count() > 0
                        ? "Assigned to {$permission->roles()->count()} role(s)"
                        : 'Permission not found'
                ];
            }
        }

        // Log activity
        $this->logActivity('delete', 'permissions', "Bulk deleted " . count($deleted) . " permissions");

        return response()->json([
            'success' => true,
            'message' => count($deleted) . " permission(s) deleted successfully",
            'data' => [
                'deleted_count' => count($deleted),
                'failed_count' => count($failed),
                'deleted' => $deleted,
                'failed' => $failed
            ]
        ], 200);
    }

    /**
     * POST /api/permissions/assign-to-role
     * Assign permissions to a role
     */
    public function assignToRole(Request $request): JsonResponse
    {
        $request->validate([
            'role_id' => 'required|exists:role,id',
            'permission_ids' => 'required|array',
            'permission_ids.*' => 'exists:permissions,id'
        ]);

        $role = RoleModel::findOrFail($request->role_id);

        // Get permission names for logging
        $permissions = PermissionModel::whereIn('id', $request->permission_ids)->pluck('name')->toArray();

        $role->permissions()->sync($request->permission_ids);

        // Log activity
        $this->logActivity('assign', 'permissions', "Assigned permissions to role: {$role->name}", [
            'role_id' => $role->id,
            'permissions' => $permissions
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Permissions assigned to role successfully',
            'data' => [
                'role_id' => $role->id,
                'role_name' => $role->name,
                'permission_ids' => $request->permission_ids,
                'permissions_count' => count($request->permission_ids)
            ]
        ], 200);
    }

    /**
     * GET /api/permissions/role/{roleId}
     * Get permissions assigned to a specific role
     */
    public function getRolePermissions($roleId): JsonResponse
    {
        $role = RoleModel::findOrFail($roleId);
        $permissions = $role->permissions()->orderBy('group')->orderBy('name')->get();

        return response()->json([
            'success' => true,
            'message' => 'Role permissions retrieved successfully',
            'data' => [
                'role' => [
                    'id' => $role->id,
                    'name' => $role->name,
                    'display_name' => $role->display_name
                ],
                'permissions' => PermissionResource::collection($permissions),
                'total' => $permissions->count()
            ]
        ], 200);
    }

    /**
     * GET /api/permissions/summary
     * Get summary statistics
     */
    public function summary(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Permission summary retrieved successfully',
            'data' => [
                'total_permissions' => PermissionModel::count(),
                'total_groups' => PermissionModel::whereNotNull('group')->distinct('group')->count('group'),
                'permissions_by_group' => PermissionModel::select('group')
                    ->selectRaw('count(*) as total')
                    ->groupBy('group')
                    ->get(),
                'most_used_permissions' => PermissionModel::withCount('roles')
                    ->having('roles_count', '>', 0)
                    ->orderBy('roles_count', 'desc')
                    ->limit(5)
                    ->get()
            ]
        ], 200);
    }

    /**
     * Helper method to log activities
     */
    private function logActivity($action, $module, $description = null, $payload = null)
    {
        if (Auth::check()) {
            LogActivity::logAuthActivity(
                $action,
                request(),
                Auth::id(),
                null,
                $module
            );
        }
    }
}
