<?php

namespace App\Http\Controllers\Api;

use App\Models\Order;
use App\Models\Theme;
use App\Models\Invitation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ThemeController extends Controller
{
    /**
     * Generate sanitized filename with EA-inv prefix
     */
    private function generateSafeFilename($file)
    {
        $extension = $file->getClientOriginalExtension();
        
        $uniqueId = time() . '_' . Str::random(10);
        
        $fileName = 'EA-inv_' . $uniqueId . '.' . $extension;
        
        return $fileName;
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try {
            $themes = Theme::with('themeCategory')->get();

            return response()->json([
                'status' => 'success',
                'message' => 'Themes retrieved successfully',
                'data' => $themes
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error fetching themes',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $user = Auth::user();

        if ($user->role != 'admin') {
            return response()->json([
                'status' => 'error',
                'message' => 'Forbidden access'
            ], 403);
        }
        
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'theme_category_id' => 'required|exists:theme_categories,id',
            'thumbnail' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
            'link' => 'nullable|string',
            'is_premium' => 'required|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            return DB::transaction(function () use ($request) {
                $thumbnailPath = null;

                if ($request->hasFile('thumbnail')) {
                    $file = $request->file('thumbnail');
                    $fileName = $this->generateSafeFilename($file);
                    $thumbnailPath = $file->storeAs('themes/thumbnails', $fileName, 'public');
                }

                $isPremium = in_array($request->is_premium, ['1', 'true', true], true);

                $theme = Theme::create([
                    'name' => $request->name,
                    'theme_category_id' => $request->theme_category_id,
                    'link' => $request->link,
                    'thumbnail' => $thumbnailPath,
                    'is_premium' => $isPremium
                ]);

                if ($thumbnailPath) {
                    $theme->thumbnail_url = url('storage/' . $thumbnailPath);
                }

                return response()->json([
                    'status' => 'success',
                    'message' => 'Theme created successfully',
                    'data' => $theme
                ], 201);
            });
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create theme',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(String $id)
    {
        try {
            $theme = Theme::with('themeCategory')->find($id);

            if (!$theme) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Theme not found'
                ], 404);
            }
            
            return response()->json([
                'status' => 'success',
                'message' => 'Theme retrieved successfully',
                'data' => $theme
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve theme',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Theme $theme)
    {
        $user = Auth::user();

        if ($user->role != 'admin') {
            return response()->json([
                'status' => 'error',
                'message' => 'Forbidden access'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'theme_category_id' => 'required|exists:theme_categories,id',
            'thumbnail' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
            'link' => 'nullable|string',
            'is_premium' => 'required|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            return DB::transaction(function () use ($theme, $request) {
                if ($request->hasFile('thumbnail')) {
                    // Delete old thumbnail
                    if ($theme->thumbnail) {
                        Storage::disk('public')->delete($theme->thumbnail);
                    }
                    
                    $file = $request->file('thumbnail');
                    $fileName = $this->generateSafeFilename($file);
                    $thumbnailPath = $file->storeAs('themes/thumbnails', $fileName, 'public');
                    
                    $theme->thumbnail = $thumbnailPath;
                }

                $theme->update([
                    'name' => $request->name,
                    'theme_category_id' => $request->theme_category_id,
                    'link' => $request->link,
                    'is_premium' => $request->is_premium
                ]);

                return response()->json([
                    'status' => 'success',
                    'message' => 'Theme updated successfully',
                    'data' => $theme->fresh()->load('themeCategory')
                ]);
            });
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update theme',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(String $id)
    {
        $user = Auth::user();

        if ($user->role != 'admin') {
            return response()->json([
                'status' => 'error',
                'message' => 'Forbidden access'
            ], 403);
        }

        try {
            return DB::transaction(function () use ($id) {
                $theme = Theme::findOrFail($id);
                
                // Delete thumbnail file if exists
                if ($theme->thumbnail) {
                    Storage::disk('public')->delete($theme->thumbnail);
                }
                
                $theme->delete();
                
                return response()->json([
                    'status' => 'success',
                    'message' => 'Theme deleted successfully'
                ]);
            });
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete theme',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    public function getThemeByOrderId($orderId)
    {
        try {
            $order = Order::where('order_id', $orderId)->first();
        
            if (!$order) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Order not found'
                ], 404);
            }

            if ($order->package_id === 1) {
                $themes = Theme::where('is_premium', false || 0)
                    ->with('themeCategory')
                    ->get();
            } else {
                $themes = Theme::with('themeCategory')->get();
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Themes retrieved successfully',
                'data' => $themes
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get themes',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    public function getThemeByInvitationId(String $invitationId)
    {
        try {
            $invitation = Invitation::find($invitationId);

            if (!$invitation) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invitation not found'
                ], 404);
            }

            $theme = Theme::with('themeCategory')->find($invitation->theme_id);

            if (!$theme) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Theme not found'
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Theme retrieved successfully',
                'data' => $theme
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve theme',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get free themes only
     */
    public function getFreeThemes()
    {
        try {
            $themes = Theme::where('is_premium', false)
                ->with('themeCategory')
                ->get();

            return response()->json([
                'status' => 'success',
                'message' => 'Free themes retrieved successfully',
                'data' => $themes
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error fetching free themes',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get premium themes only
     */
    public function getPremiumThemes()
    {
        try {
            $themes = Theme::where('is_premium', true)
                ->with('themeCategory')
                ->get();

            return response()->json([
                'status' => 'success',
                'message' => 'Premium themes retrieved successfully',
                'data' => $themes
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error fetching premium themes',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
}