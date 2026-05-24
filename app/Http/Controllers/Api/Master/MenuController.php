<?php

namespace App\Http\Controllers\Api\Master;

use App\Http\Controllers\Controller;
use App\Models\MenuModel;
use App\Http\Requests\Master\MenuRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Helpers\LogActivity;

class MenuController extends Controller
{
    public function store(MenuRequest $request): JsonResponse
    {
        // 1. CEK TOKEN VALIDITAS - Apakah user sudah login?
        $user = Auth::guard('api')->user();
        // dd($user);
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Token tidak valid atau expired.',
            ], 401);
        }

        $appId = $user->jwt_claims['app_id'] ?? null; // Ambil aplikasi pertama yang terkait dengan user (asumsi user hanya terkait dengan satu aplikasi)
        // 4. CEK DUPLIKASI - Apakah menu dengan nama yang sama sudah ada?
        $data = $request->validated();

        $existingMenu = MenuModel::where('name', $data['name'])
            ->whereHas('apps', function ($query) use ($appId) {
                $query->where('app_id', $appId);
            })
            ->first();

        if ($existingMenu) {
            return response()->json([
                'success' => false,
                'message' => 'Menu dengan nama "' . $data['name'] . '" sudah ada di aplikasi ini.',
            ], 409); // 409 Conflict
        }

        // 5. CEK PARENT MENU (jika ada)
        if (isset($data['parent_id']) && $data['parent_id']) {
            $parentMenu = MenuModel::find($data['parent_id']);

            if (!$parentMenu) {
                return response()->json([
                    'success' => false,
                    'message' => 'Parent menu tidak ditemukan.',
                ], 404);
            }

            // Cek apakah parent menu berada di aplikasi yang sama
            if (!$parentMenu->apps()->where('app_id', $appId)->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Parent menu tidak berada di aplikasi yang sama.',
                ], 400);
            }
        }

        DB::beginTransaction();

        try {
            // Tambahkan created_by untuk audit trail
            $data['created_by'] = $user->id;
            $data['created_at'] = now();

            $menu = MenuModel::create($data);

            // Attach menu ke app
            $menu->apps()->attach($appId);

            DB::commit();

            // Log activity (opsional)
            LogActivity::logAuthActivity('menu.create', request(), $user->id, (int) $appId, 'menu');
            return response()->json([
                'success' => true,
                'message' => 'Menu created successfully',
                'data' => $menu,
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();

            // Log error untuk debugging
            Log::error('Failed to create menu: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'user_id' => $user->id,
                'data' => $data
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create menu',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }
    // ambil semua data menu 
    public function index(): JsonResponse
    {
        $user = Auth::guard('api')->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        // Ambil semua menu dengan sorting
        // Hanya ambil root menu (parent_id = null)
        $menus = MenuModel::whereNull('parent_id')
            ->where('is_active', 1)
            ->with('children')  // Tidak perlu nested manual
            ->orderBy('sort_order', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $menus,
        ]);
    }
    // ambil data menu berdasarkan app_id (untuk menampilkan menu sesuai aplikasi yang digunakan)

    // Optional: Method untuk update dengan cek otorisasi serupa
    public function update(MenuRequest $request, int $id): JsonResponse
    {
        $user = Auth::guard('api')->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        if (!in_array($user->role, ['admin', 'superadmin'])) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden',
            ], 403);
        }

        $menu = MenuModel::find($id);

        if (!$menu) {
            return response()->json([
                'success' => false,
                'message' => 'Menu not found',
            ], 404);
        }

        $data = $request->validated();
        $data['updated_by'] = $user->id;
        $data['updated_at'] = now();

        $menu->update($data);

        // Log activity (opsional)
        LogActivity::logAuthActivity('menu.update', request(), $user->id, null, 'menu');

        return response()->json([
            'success' => true,
            'message' => 'Menu updated successfully',
            'data' => $menu,
        ]);
    }

    // Optional: Method untuk delete
    public function destroy(int $id): JsonResponse
    {
        $user = Auth::guard('api')->user();

        if (!$user || !in_array($user->role, ['admin', 'superadmin'])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        $menu = MenuModel::find($id);

        if (!$menu) {
            return response()->json([
                'success' => false,
                'message' => 'Menu not found',
            ], 404);
        }

        // Cek apakah menu memiliki child
        if ($menu->children()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete menu that has children',
            ], 400);
        }

        DB::beginTransaction();

        try {
            $menu->apps()->detach();
            $menu->delete();

            DB::commit();

            // Log activity (opsional)
            LogActivity::logAuthActivity('menu.delete', request(), $user->id, null, 'menu');

            return response()->json([
                'success' => true,
                'message' => 'Menu deleted successfully',
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete menu',
            ], 500);
        }
    }
}
