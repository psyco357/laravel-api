<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AppController;
use App\Http\Middleware\ValidateAppCredentials;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Master\MenuController;
use App\Http\Middleware\CheckApiToken;

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

// App management routes (admin only)
Route::prefix('apps')
    ->middleware(['auth:api', ValidateAppCredentials::class])
    ->group(function () {
        // Route::get('/', [AppController::class, 'index']);
        // Route::get('/{app}', [AppController::class, 'show']);
        // Route::get('/{app}/stats', [AppController::class, 'stats']);
    });

// Activity logs (admin only)
Route::prefix('logs')
    ->middleware(['auth:api', ValidateAppCredentials::class])
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
        // Route::put('/{menu}', [MenuController::class, 'update']);
        // Route::delete('/{menu}', [MenuController::class, 'destroy']);    
    });
