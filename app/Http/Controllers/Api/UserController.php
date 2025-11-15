<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /**
     * Get authenticated user profile
     */
    public function profile()
    {
        $user = Auth::user();

        return response()->json([
            'status' => 'success',
            'data' => $user
        ]);
    }

    /**
     * Update authenticated user profile
     */
    public function updateProfile(Request $request)
    {
        $user = Auth::user();

        // User dengan Google ID tidak bisa mengubah avatar
        $rules = [
            'name' => 'required|string|max:255',
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users')->ignore($user->id)
            ],
            'phone' => 'nullable|string|max:20',
        ];

        // Hanya user tanpa google_id yang bisa upload avatar
        if (!$user->google_id) {
            $rules['avatar'] = 'nullable|image|mimes:jpeg,jpg,png|max:2048'; // max 2MB
        }

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            return DB::transaction(function () use ($request, $user) {
                $data = [
                    'name' => $request->name,
                    'email' => $request->email,
                    'phone' => $request->phone,
                ];

                // Handle avatar upload untuk user non-Google
                if ($request->hasFile('avatar') && !$user->google_id) {
                    // Hapus avatar lama jika ada
                    if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
                        Storage::disk('public')->delete($user->avatar);
                    }

                    // Upload avatar baru
                    $avatarPath = $request->file('avatar')->store('avatars', 'public');
                    $data['avatar'] = $avatarPath;
                }

                $user->update($data);

                return response()->json([
                    'status' => 'success',
                    'message' => 'Profil berhasil diperbarui',
                    'data' => $user->fresh()
                ]);
            });
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal memperbarui profil',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Update authenticated user password
     */
    public function updatePassword(Request $request)
    {
        $user = Auth::user();

        // User dengan Google ID tidak bisa mengubah password
        if ($user->google_id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Akun Google tidak dapat mengubah password'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Verifikasi password saat ini
        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Password saat ini tidak sesuai'
            ], 422);
        }

        try {
            $user->update([
                'password' => Hash::make($request->new_password)
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Password berhasil diubah'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal mengubah password',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Delete authenticated user avatar
     */
    public function deleteAvatar()
    {
        $user = Auth::user();

        // User dengan Google ID tidak bisa menghapus avatar
        if ($user->google_id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Akun Google tidak dapat menghapus avatar'
            ], 403);
        }

        try {
            // Hapus avatar dari storage jika ada
            if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
                Storage::disk('public')->delete($user->avatar);
            }

            $user->update(['avatar' => null]);

            return response()->json([
                'status' => 'success',
                'message' => 'Avatar berhasil dihapus'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal menghapus avatar',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
}