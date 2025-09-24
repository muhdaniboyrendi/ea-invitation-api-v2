<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Facades\Socialite;

class GoogleOAuthController extends Controller
{
    /**
     * Redirect to Google OAuth
     */
    public function redirectToGoogle()
    {
        try {
            $url = Socialite::driver('google')
                ->stateless()
                ->redirect()
                ->getTargetUrl();

            return response()->json([
                'status' => 'success',
                'message' => 'Google OAuth URL generated',
                'data' => [
                    'auth_url' => $url
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to generate Google OAuth URL',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Handle Google OAuth Callback
     */
    public function handleGoogleCallback(Request $request)
    {
        try {
            // Configure Socialite driver with custom Guzzle options for development
            $driver = Socialite::driver('google')->stateless();
            
            // Add this for development environment to bypass SSL verification
            if (config('app.env') !== 'production') {
                $driver->setHttpClient(
                    new \GuzzleHttp\Client([
                        'verify' => false, // Only for development!
                    ])
                );
            }

            // Handle authorization code flow
            if ($request->has('code')) {
                $googleUser = $driver->user();
            } 
            // Handle access token flow (from frontend)
            else if ($request->has('access_token')) {
                $googleUser = $driver->userFromToken($request->access_token);
            } else {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No authorization code or access token provided'
                ], 400);
            }

            return DB::transaction(function () use ($googleUser) {
                // Cari user berdasarkan email
                $user = User::where('email', $googleUser->email)->first();

                if ($user) {
                    // Update Google ID jika belum ada
                    if (!$user->google_id) {
                        $user->update([
                            'google_id' => $googleUser->id,
                            'avatar' => $googleUser->avatar
                        ]);
                    }
                } else {
                    // Buat user baru
                    $user = User::create([
                        'name' => $googleUser->name,
                        'email' => $googleUser->email,
                        'google_id' => $googleUser->id,
                        'avatar' => $googleUser->avatar,
                        'phone' => null, // Bisa diisi nanti
                        'password' => Hash::make(Str::random(16)), // Random password
                        'email_verified_at' => now(), // Langsung verified karena dari Google
                    ]);
                }

                // Hapus token lama dan buat token baru
                $user->tokens()->delete();
                $token = $user->createToken('google_auth_token')->plainTextToken;

                return response()->json([
                    'status' => 'success',
                    'message' => 'Google OAuth login successful',
                    'data' => [
                        'user' => $user,
                        'token' => $token,
                        'is_new_user' => !User::where('email', $googleUser->email)->exists()
                    ]
                ], 200);
            });

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Google OAuth authentication failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Link Google account ke user yang sudah login
     */
    public function linkGoogleAccount(Request $request)
    {
        $request->validate([
            'access_token' => 'required|string'
        ]);

        try {
            $user = $request->user();
            
            if ($user->google_id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Google account is already linked'
                ], 400);
            }

            $googleUser = Socialite::driver('google')
                ->stateless()
                ->userFromToken($request->access_token);

            // Cek apakah Google account sudah digunakan user lain
            $existingUser = User::where('google_id', $googleUser->id)->first();
            if ($existingUser) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'This Google account is already linked to another user'
                ], 400);
            }

            // Link Google account
            $user->update([
                'google_id' => $googleUser->id,
                'avatar' => $googleUser->avatar ?? $user->avatar
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Google account linked successfully',
                'data' => [
                    'user' => $user->fresh()
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to link Google account',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Unlink Google account
     */
    public function unlinkGoogleAccount(Request $request)
    {
        try {
            $user = $request->user();
            
            if (!$user->google_id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No Google account is linked'
                ], 400);
            }

            $user->update([
                'google_id' => null
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Google account unlinked successfully'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to unlink Google account',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
