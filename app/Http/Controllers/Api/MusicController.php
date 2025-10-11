<?php

namespace App\Http\Controllers\Api;

use App\Models\Music;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class MusicController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try {
            $musics = Music::all();
            
            return response()->json([
                'status' => 'success',
                'message' => 'musics retrieved successfully',
                'data' => $musics
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve musics',
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
            'artist' => 'nullable|string|max:255',
            'audio' => 'required|file|mimes:mp3,wav,ogg,m4a|max:20480',
            'thumbnail' => 'nullable|file|mimes:jpg,jpeg,png,webp|max:5120',
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
            return DB::transaction(function () use ($request, $validated) {
                if ($request->hasFile('audio')) {
                    $audioFile = $request->file('audio');
                    $audioExtension = $audioFile->getClientOriginalExtension();
                    $audioUuid = Str::uuid();
                    $audioFileName = $audioUuid . '.' . $audioExtension;
                    
                    $validated['audio'] = $audioFile->storeAs('musics', $audioFileName, 'public');
                }
        
                if ($request->hasFile('thumbnail')) {
                    $thumbnailFile = $request->file('thumbnail');
                    $thumbnailExtension = $thumbnailFile->getClientOriginalExtension();
                    $thumbnailUuid = Str::uuid();
                    $thumbnailFileName = $thumbnailUuid . '.' . $thumbnailExtension;
                    
                    $validated['thumbnail'] = $thumbnailFile->storeAs('musics/thumbnails', $thumbnailFileName, 'public');
                }

                $music = Music::create([
                    'name' => $validated['name'],
                    'artist' => $validated['artist'] ?? null,
                    'audio' => $validated['audio'] ?? null,
                    'thumbnail' => $validated['thumbnail'] ?? null,
                ]);

                return response()->json([
                    'status' => 'success',
                    'message' => 'music created successfully',
                    'data' => $music
                ], 201);
            });
        } catch (\Exception $e) {
            // Clean up uploaded files on error
            if (isset($validated['audio']) && Storage::disk('public')->exists($validated['audio'])) {
                Storage::disk('public')->delete($validated['audio']);
            }
            
            if (isset($validated['thumbnail']) && Storage::disk('public')->exists($validated['thumbnail'])) {
                Storage::disk('public')->delete($validated['thumbnail']);
            }

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create backsound',
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
            $music = Music::find($id);

            if (!$music) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'music not found'
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'music retrieved successfully',
                'data' => $music
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve music',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Music $music)
    {
        $user = Auth::user();

        if ($user->role != 'admin') {
            return response()->json([
                'status' => 'error',
                'message' => 'Forbidden access'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'artist' => 'nullable|string|max:255',
            'audio' => 'sometimes|file|mimes:mp3,wav,ogg,m4a|max:51200',
            'thumbnail' => 'sometimes|file|mimes:jpg,jpeg,png,webp|max:2048',
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
            return DB::transaction(function () use ($request, $music, $validated) {
                $oldAudioPath = $music->audio;
                $oldThumbnailPath = $music->thumbnail;

                if ($request->hasFile('audio')) {
                    $audioFile = $request->file('audio');
                    $audioExtension = $audioFile->getClientOriginalExtension();
                    $audioUuid = Str::uuid();
                    $audioFileName = $audioUuid . '.' . $audioExtension;
                    
                    $validated['audio'] = $audioFile->storeAs('musics', $audioFileName, 'public');
                }

                if ($request->hasFile('thumbnail')) {
                    $thumbnailFile = $request->file('thumbnail');
                    $thumbnailExtension = $thumbnailFile->getClientOriginalExtension();
                    $thumbnailUuid = Str::uuid();
                    $thumbnailFileName = $thumbnailUuid . '.' . $thumbnailExtension;
                    
                    $validated['thumbnail'] = $thumbnailFile->storeAs('musics/thumbnails', $thumbnailFileName, 'public');
                }

                $music->update($validated);

                // Clean up old files
                if ($request->hasFile('audio') && $oldAudioPath && Storage::disk('public')->exists($oldAudioPath)) {
                    Storage::disk('public')->delete($oldAudioPath);
                }

                if ($request->hasFile('thumbnail') && $oldThumbnailPath && Storage::disk('public')->exists($oldThumbnailPath)) {
                    Storage::disk('public')->delete($oldThumbnailPath);
                }

                return response()->json([
                    'status' => 'success',
                    'message' => 'Music updated successfully',
                    'data' => $music->fresh()
                ]);
            });
        } catch (\Exception $e) {
            // Clean up new uploaded files on error
            if (isset($validated['audio']) && Storage::disk('public')->exists($validated['audio'])) {
                Storage::disk('public')->delete($validated['audio']);
            }
            
            if (isset($validated['thumbnail']) && Storage::disk('public')->exists($validated['thumbnail'])) {
                Storage::disk('public')->delete($validated['thumbnail']);
            }

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update backsound',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Music $music)
    {
        $user = Auth::user();

        if ($user->role != 'admin') {
            return response()->json([
                'status' => 'error',
                'message' => 'Forbidden access'
            ], 403);
        }

        try {
            return DB::transaction(function () use ($music) {
                $audioPath = $music->audio;
                $thumbnailPath = $music->thumbnail;

                $music->delete();

                // Clean up files
                if ($audioPath && Storage::disk('public')->exists($audioPath)) {
                    Storage::disk('public')->delete($audioPath);
                }

                if ($thumbnailPath && Storage::disk('public')->exists($thumbnailPath)) {
                    Storage::disk('public')->delete($thumbnailPath);
                }

                return response()->json([
                    'status' => 'success',
                    'message' => 'Music deleted successfully'
                ]);
            });
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete music',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
}
