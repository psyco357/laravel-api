<?php
// app/Http/Controllers/Api/Master/ProfileController.php

namespace App\Http\Controllers\Api\Master;

use App\Http\Controllers\Controller;
use App\Helpers\LogActivity;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    /**
     * GET /api/profile
     * Get current user profile
     */
    public function show(): JsonResponse
    {
        $user = $this->authenticatedUser();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Token tidak valid atau expired.',
            ], 401);
        }

        try {
            $user->load('profile', 'roles', 'apps');

            return response()->json([
                'success' => true,
                'message' => 'Profile retrieved successfully',
                'data' => [
                    'id' => $user->id,
                    'username' => $user->username,
                    'email' => $user->email,
                    'email_verified' => $user->email_verified,
                    'is_active' => $user->is_active,
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
                            'app_name' => $app->app_first_name,
                        ];
                    }),
                ]
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve profile',
            ], 500);
        }
    }

    /**
     * PUT /api/profile
     * Update current user profile
     */
    public function update(Request $request): JsonResponse
    {
        $user = $this->authenticatedUser();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Token tidak valid atau expired.',
            ], 401);
        }

        $request->validate([
            'full_name' => 'required|string|max:150',
            'nik' => 'nullable|string|max:50|unique:mst_profiles,nik,' . $user->id . ',user_id',
            'phone' => 'nullable|string|max:30',
            'avatar' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            'remove_avatar' => 'boolean',
        ]);

        DB::beginTransaction();

        try {
            // Update profile
            $profileData = [
                'full_name' => $request->full_name,
                'nik' => $request->nik,
                'phone' => $request->phone,
            ];

            // Handle avatar upload
            if ($request->hasFile('avatar')) {
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
                \App\Models\UserProfile::create($profileData);
            }

            DB::commit();

            LogActivity::logAuthActivity('profile.update', request(), $user->id, null, 'profile');

            $user->load('profile');

            return response()->json([
                'success' => true,
                'message' => 'Profile updated successfully',
                'data' => [
                    'nik' => $user->profile->nik ?? null,
                    'full_name' => $user->profile->full_name ?? null,
                    'avatar' => $user->profile->avatar ?? null,
                    'avatar_url' => $user->profile->avatar_url ?? null,
                    'phone' => $user->profile->phone ?? null,
                ]
            ], 200);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Failed to update profile: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update profile',
            ], 500);
        }
    }

    /**
     * POST /api/profile/change-password
     * Change current user password
     */
    public function changePassword(Request $request): JsonResponse
    {
        $user = $this->authenticatedUser();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Token tidak valid atau expired.',
            ], 401);
        }

        $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed|different:current_password',
        ]);

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Current password is incorrect',
            ], 400);
        }

        try {
            $user->password = Hash::make($request->new_password);
            $user->save();

            LogActivity::logAuthActivity('profile.change_password', request(), $user->id, null, 'profile');

            return response()->json([
                'success' => true,
                'message' => 'Password changed successfully'
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to change password',
            ], 500);
        }
    }

    private function authenticatedUser(): ?User
    {
        $user = Auth::guard('api')->user();

        return $user instanceof User ? $user : null;
    }
}
