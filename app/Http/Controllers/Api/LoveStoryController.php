<?php

namespace App\Http\Controllers\Api;

use App\Models\LoveStory;
use App\Models\Invitation;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class LoveStoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(string $invitationId)
    {
        try {
            $loveStories = LoveStory::where('invitation_id', $invitationId)
                ->orderBy('date', 'asc')
                ->orderBy('created_at', 'asc')
                ->get();

            return response()->json([
                'status' => 'success',
                'message' => 'Love stories retrieved successfully',
                'data' => $loveStories
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve love stories',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'invitation_id' => 'required|exists:invitations,id',
            'title' => 'required|string|max:255',
            'date' => 'nullable|date',
            'description' => 'nullable|string',
            'thumbnail' => 'nullable|file|mimes:jpg,jpeg,png,webp|max:2048',
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
            // Check ownership BEFORE transaction
            $invitation = Invitation::where('id', $validated['invitation_id'])
                ->where('user_id', Auth::id())
                ->firstOrFail();

            return DB::transaction(function () use ($validated, $request) {
                // Handle file upload
                if ($request->hasFile('thumbnail')) {
                    $validated['thumbnail'] = $this->uploadFile($request->file('thumbnail'), 'love-stories/thumbnails');
                }

                $loveStory = LoveStory::create($validated);
                $loveStory->load('invitation');

                return response()->json([
                    'status' => 'success',
                    'message' => 'Love story created successfully',
                    'data' => $loveStory,
                ], 201);
            });

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invitation not found or access denied'
            ], 404);
        } catch (\Exception $e) {
            // Cleanup uploaded files on error
            $this->cleanupFiles($validated);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create love story',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, LoveStory $loveStory)
    {
        // Load relationship to check ownership
        $loveStory->load('invitation');
        
        // Check ownership
        if ($loveStory->invitation->user_id !== Auth::id()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Forbidden access'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'date' => 'nullable|date',
            'description' => 'nullable|string',
            'thumbnail' => 'nullable|file|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $validated = $validator->validated();
        $oldThumbnail = $loveStory->thumbnail;

        try {
            return DB::transaction(function () use ($loveStory, $validated, $request, $oldThumbnail) {
                // Handle file upload
                if ($request->hasFile('thumbnail')) {
                    $validated['thumbnail'] = $this->uploadFile($request->file('thumbnail'), 'love-stories/thumbnails');
                    $this->deleteFile($oldThumbnail);
                }

                $loveStory->update($validated);
                $loveStory->load('invitation');

                return response()->json([
                    'status' => 'success',
                    'message' => 'Love story updated successfully',
                    'data' => $loveStory
                ]);
            });

        } catch (\Exception $e) {
            // Cleanup newly uploaded files on error
            $this->cleanupFiles($validated);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update love story',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(LoveStory $loveStory)
    {
        try {
            // Eager load to avoid N+1
            $loveStory->load('invitation');
            
            // Check ownership
            if ($loveStory->invitation->user_id !== Auth::id()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Forbidden access'
                ], 403);
            }

            return DB::transaction(function () use ($loveStory) {
                $thumbnail = $loveStory->thumbnail;

                $loveStory->delete();

                // Delete associated file
                $this->deleteFile($thumbnail);

                return response()->json([
                    'status' => 'success',
                    'message' => 'Love story deleted successfully',
                ]);
            });

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete love story',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Helper: Upload file with UUID filename
     */
    private function uploadFile($file, string $directory): string
    {
        $extension = $file->getClientOriginalExtension();
        $uuid = Str::uuid();
        $fileName = $uuid . '.' . $extension;
        
        return $file->storeAs($directory, $fileName, 'public');
    }

    /**
     * Helper: Delete file from storage
     */
    private function deleteFile(?string $filePath): void
    {
        if ($filePath && Storage::disk('public')->exists($filePath)) {
            Storage::disk('public')->delete($filePath);
        }
    }

    /**
     * Helper: Cleanup files after failed transaction
     */
    private function cleanupFiles(array $data): void
    {
        if (isset($data['thumbnail'])) {
            $this->deleteFile($data['thumbnail']);
        }
    }
}
