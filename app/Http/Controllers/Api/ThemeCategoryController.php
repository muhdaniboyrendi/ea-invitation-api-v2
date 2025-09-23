<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Models\ThemeCategory;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class ThemeCategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try {
            $categories = ThemeCategory::all();
            
            return response()->json([
                'status' => 'success',
                'message' => 'Theme Categories retrieved successfully',
                'data' => $categories
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error fetching categories',
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
            'description' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $validated = $validator->validated();

        try {
            return DB::transaction(function () use ($validated) {
                $theme = ThemeCategory::create($validated);

                return response()->json([
                    'status' => 'success',
                    'message' => 'Theme category created successfully',
                    'data' => $theme
                ], 201);
            });
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create theme category',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        try {
            $themeCategory = ThemeCategory::find($id);

            if (!$themeCategory) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Theme category not found'
                ], 404);
            }
            
            return response()->json([
                'status' => 'success',
                'message' => 'Theme category retrieved successfully',
                'data' => $themeCategory
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve theme category',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
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
            'description' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $validated = $validator->validated();
        
        try {
            return DB::transaction(function () use ($id, $validated) {
                $themeCategory = ThemeCategory::findOrFail($id);
                
                $themeCategory->update($validated);

                return response()->json([
                    'status' => 'success',
                    'message' => 'Theme category updated successfully',
                    'data' => $themeCategory->fresh()
                ]);
            });
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update theme category',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
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
                $themeCategory = ThemeCategory::findOrFail($id);
                
                $themeCategory->delete();
                
                return response()->json([
                    'status' => 'success',
                    'message' => 'Theme category deleted successfully'
                ]);
            });
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete theme category',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
}