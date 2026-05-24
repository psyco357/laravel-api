<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\Master\AppController;
use App\Http\Controllers\Api\Master\PermissionController;
use App\Http\Middleware\ValidateAppCredentials;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Master\MenuController;
use App\Http\Controllers\Api\Master\RoleController;
use App\Http\Middleware\CheckApiToken;
use App\Http\Controllers\Api\Master\UserController;
use App\Http\Controllers\Api\Master\ProfileController;
// use App\Http\Controllers\Api\Master\AppController;
/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Public routes (no validation needed)
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now(),
        'service' => 'Auth API'
    ]);
});

// Auth routes dengan validasi app credentials
Route::prefix('auth')->middleware([ValidateAppCredentials::class])->group(function () {

    // Authentication tanpa token
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/refresh', [AuthController::class, 'refresh']);
    // Authentication dengan token
    Route::middleware('auth:api')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/sessions', [AuthController::class, 'sessions']); // lihat session aktif
    });
});
// Route untuk role user (admin, user, guest) bisa ditambahkan di middleware auth:api dengan menggunakan spatie/laravel-permission atau custom middleware untuk role checking.

// App management routes (admin only)
Route::prefix('apps')
    ->middleware(['auth:api'])
    ->group(function () {
        Route::get('/', [AppController::class, 'index']);
        Route::get('/{id}', [AppController::class, 'show']);
        // Route::get('/{app}/stats', [AppController::class, 'stats']);
    });

// Activity logs (admin only)
Route::prefix('logs')
    ->middleware(['auth:api'])
    ->group(function () {
        Route::get('/auth', [AuthController::class, 'getActivityLogs']);
        Route::get('/auth/summary', [AuthController::class, 'getActivitySummary']);
    });

// routes untuk manajemen Menu (admin only)
Route::prefix('menus')
    ->middleware(['auth:api'])
    ->group(function () {
        Route::post('/', [MenuController::class, 'store']);
        Route::get('/', [MenuController::class, 'index']);
        Route::put('/{menu}', [MenuController::class, 'update']);
        Route::delete('/{menu}', [MenuController::class, 'destroy']);
    });

// Permission and role-permission management.
// Semua endpoint ini wajib membawa access token via Authorization: Bearer <token>.
Route::prefix('permissions')
    ->middleware(['auth:api'])
    ->group(function () {
        Route::get('/', [PermissionController::class, 'index']);
        Route::get('/all', [PermissionController::class, 'all']);
        Route::get('/groups', [PermissionController::class, 'getGroups']);
        Route::get('/grouped', [PermissionController::class, 'getGrouped']);
        Route::get('/summary', [PermissionController::class, 'summary']);
        Route::post('/', [PermissionController::class, 'store']);
        Route::post('/bulk-delete', [PermissionController::class, 'bulkDelete']);
        Route::post('/assign-to-role', [PermissionController::class, 'assignToRole']);
        Route::get('/role/{roleId}', [PermissionController::class, 'getRolePermissions']);
        Route::get('/{id}', [PermissionController::class, 'show']);
        Route::put('/{id}', [PermissionController::class, 'update']);
        Route::delete('/{id}', [PermissionController::class, 'destroy']);
    });

// Role Management Routes
Route::prefix('roles')->middleware(['auth:api'])->group(function () {
    // List roles
    Route::get('/', [RoleController::class, 'index']);              // GET /api/roles
    Route::get('/all', [RoleController::class, 'all']);             // GET /api/roles/all
    Route::get('/summary', [RoleController::class, 'summary']);     // GET /api/roles/summary

    // User roles
    Route::get('/user/{userId}', [RoleController::class, 'getUserRoles']); // GET /api/roles/user/{userId}

    // CRUD operations
    Route::post('/', [RoleController::class, 'store']);             // POST /api/roles
    Route::get('/{id}', [RoleController::class, 'show']);           // GET /api/roles/{id}
    Route::put('/{id}', [RoleController::class, 'update']);         // PUT /api/roles/{id}
    Route::delete('/{id}', [RoleController::class, 'destroy']);     // DELETE /api/roles/{id}

    // Bulk operations
    Route::post('/bulk-delete', [RoleController::class, 'bulkDelete']); // POST /api/roles/bulk-delete

    // Permission assignments
    Route::post('/{id}/assign-permissions', [RoleController::class, 'assignPermissions']); // POST /api/roles/{id}/assign-permissions
    Route::get('/{id}/permissions', [RoleController::class, 'getPermissions']);           // GET /api/roles/{id}/permissions
});


// User Management Routes
Route::prefix('users')->middleware(['auth:api'])->group(function () {
    // List users
    Route::get('/', [UserController::class, 'index']);              // GET /api/users
    Route::get('/summary', [UserController::class, 'summary']);     // GET /api/users/summary

    // CRUD operations
    Route::post('/', [UserController::class, 'store']);             // POST /api/users
    Route::get('/{id}', [UserController::class, 'show']);           // GET /api/users/{id}
    Route::put('/{id}', [UserController::class, 'update']);         // PUT /api/users/{id}
    Route::delete('/{id}', [UserController::class, 'destroy']);     // DELETE /api/users/{id}

    // Bulk operations
    Route::post('/bulk-delete', [UserController::class, 'bulkDelete']); // POST /api/users/bulk-delete

    // Password management
    Route::post('/{id}/change-password', [UserController::class, 'changePassword']); // POST /api/users/{id}/change-password

    // Assignments
    Route::post('/{id}/assign-apps', [UserController::class, 'assignApps']);   // POST /api/users/{id}/assign-apps
    Route::post('/{id}/assign-roles', [UserController::class, 'assignRoles']); // POST /api/users/{id}/assign-roles
    Route::get('/{id}/apps', [UserController::class, 'getUserApps']);          // GET /api/users/{id}/apps
});

// Profile Management Routes (for current logged in user)
Route::prefix('profile')->middleware(['auth:api'])->group(function () {
    Route::get('/', [ProfileController::class, 'show']);                  // GET /api/profile
    Route::put('/', [ProfileController::class, 'update']);                // PUT /api/profile
    Route::post('/change-password', [ProfileController::class, 'changePassword']); // POST /api/profile/change-password
});
