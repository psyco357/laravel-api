<?php

namespace App\Http\Controllers\Api\Master;

use App\Helpers\GenerateHelpers;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use App\Helpers\LogActivity;
use Illuminate\Http\Request;
use App\Models\MstApp;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\MstConnection;

// use App\Helpers\ConfigAppHelpers;


class AppController extends Controller
{

    // ambil semua data app 
    public function index(Request $request): JsonResponse
    {
        $user = Auth::guard('api')->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }

        $perPage = (int) $request->get('per_page', 10);
        $search = trim((string) $request->get('search', ''));

        $query = MstApp::with('connection');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('app_code', 'like', "%{$search}%")
                    ->orWhere('app_first_name', 'like', "%{$search}%")
                    ->orWhere('app_last_name', 'like', "%{$search}%")
                    ->orWhere('app_url', 'like', "%{$search}%")
                    ->orWhereHas('connection', function ($connection) use ($search) {
                        $connection->where('name', 'like', "%{$search}%")
                            ->orWhere('driver', 'like', "%{$search}%")
                            ->orWhere('host', 'like', "%{$search}%");
                    });
            });
        }

        $apps = $query->paginate($perPage);

        LogActivity::logAuthActivity(
            'app.index',
            $request,
            null,
            (int) $user->id,
            'app'
        );

        return response()->json([
            'success' => true,
            'data' => $apps->items(),
            'pagination' => [
                'currentPage' => $apps->currentPage(),
                'lastPage' => $apps->lastPage(),
                'total' => $apps->total(),
                'perPage' => $apps->perPage(),
            ]
        ]);
    }

    // ambil data app berdasarkan id
    public function show(Request $request, int $id): JsonResponse
    {
        // 1. CEK TOKEN VALIDITAS - Apakah user sudah login?
        $user = Auth::guard('api')->user();
        // Log dengan informasi app dan connection
        LogActivity::logAuthActivity('app.show', $request, null, (int) $user->id, 'app');
        $app_data = MstApp::with('connection')->find($id);
        if (!$app_data) {
            return response()->json([
                'success' => false,
                'message' => 'App tidak ditemukan.',
            ], 404);
        }
        return response()->json([
            'success' => true,
            'data' => $app_data,
        ]);
    }

    // insert data apps
    public function store(Request $request): JsonResponse
    {
        DB::beginTransaction();
        try {

            // 1. Create Connection
            $connection = MstConnection::create([
                'name' => $request->connection_name,
                'host' => $request->host,
                'db_name' => $request->db_name,
                'port' => $request->port,
                'username' => $request->username,
                'password' => $request->password,
                'driver' => 'mysql',
                'is_active' => true,
            ]);



            // 3. Create App
            $generateHelper = new GenerateHelpers();
            $app = MstApp::create([
                'app_code' => $request->app_code ?? GenerateHelpers::getNextAppCode(),
                'api_key' => $generateHelper->generateApiKey(),
                'connection_id' => $connection->id,
                'app_first_name' => $request->app_first_name,
                'app_last_name' => $request->app_last_name,
                'app_version' => $request->app_version ?? '1.0.0',
                'app_author' => $request->app_author ?? 'developers',
                'app_license' => $generateHelper->generateApiLicense(),

                'app_url' => $request->app_url,
                'is_active' => true,
            ]);

            $app->load('connection');

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $app
            ], 201);
        } catch (\Throwable $th) {

            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => $th->getMessage()
            ], 500);
        }
    }

    // update data apps
    public function update(Request $request, int $id): JsonResponse
    {
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $app = MstApp::findOrFail($id);
        $app->update($validatedData);

        // Log dengan informasi app dan connection
        LogActivity::logAuthActivity('app.update', $request, null, (int) $app->id, 'app');

        return response()->json([
            'success' => true,
            'data' => $app,
        ]);
    }
}
