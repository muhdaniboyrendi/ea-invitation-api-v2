<?php

namespace App\Http\Controllers\Api;

use App\Models\Bride;
use App\Models\Invitation;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class BrideController extends Controller
{
    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'invitation_id' => 'required|exists:invitations,id',
            'full_name' => 'required|string|max:255',
            'father' => 'required|string|max:255',
            'mother' => 'required|string|max:255',
            'instagram' => 'nullable|string|max:255',
            'photo' => 'nullable|file|mimes:jpg,jpeg,png,webp|max:2048',
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

            // Check if bride already exists
            $existingBride = Bride::where('invitation_id', $validated['invitation_id'])->first();
            
            if ($existingBride) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Bride info already exists for this invitation. Use update instead.'
                ], 409);
            }

            return DB::transaction(function () use ($validated, $request) {
                // Handle file upload
                if ($request->hasFile('photo')) {
                    $validated['photo'] = $this->uploadFile($request->file('photo'), 'bride/photos');
                }

                $bride = Bride::create($validated);
                $bride->load('invitation');

                return response()->json([
                    'status' => 'success',
                    'message' => 'Bride info created successfully',
                    'data' => $bride,
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
                'message' => 'Failed to create bride info',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $invitationId)
    {
        try {
            $bride = Bride::with('invitation')
                ->where('invitation_id', $invitationId)
                ->first();

            if (!$bride) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Bride info not found'
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Bride info retrieved successfully',
                'data' => $bride
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve bride info',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Bride $bride)
    {
        // Load relationship to check ownership
        $bride->load('invitation');
        
        // Check ownership
        if ($bride->invitation->user_id !== Auth::id()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Forbidden access'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'full_name' => 'required|string|max:255',
            'father' => 'required|string|max:255',
            'mother' => 'required|string|max:255',
            'instagram' => 'nullable|string|max:255',
            'photo' => 'nullable|file|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $validated = $validator->validated();
        $oldPhoto = $bride->photo;

        try {
            return DB::transaction(function () use ($bride, $validated, $request, $oldPhoto) {
                // Handle file upload
                if ($request->hasFile('photo')) {
                    $validated['photo'] = $this->uploadFile($request->file('photo'), 'bride/photos');
                    $this->deleteFile($oldPhoto);
                }

                $bride->update($validated);
                $bride->load('invitation');

                return response()->json([
                    'status' => 'success',
                    'message' => 'Bride info updated successfully',
                    'data' => $bride
                ]);
            });

        } catch (\Exception $e) {
            // Cleanup newly uploaded files on error
            $this->cleanupFiles($validated);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update bride info',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Bride $bride)
    {
        try {
            // Eager load to avoid N+1
            $bride->load('invitation');
            
            // Check ownership
            if ($bride->invitation->user_id !== Auth::id()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Forbidden access'
                ], 403);
            }

            return DB::transaction(function () use ($bride) {
                $photo = $bride->photo;

                $bride->delete();

                // Delete associated file
                $this->deleteFile($photo);

                return response()->json([
                    'status' => 'success',
                    'message' => 'Bride info deleted successfully',
                ]);
            });

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete bride info',
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
        if (isset($data['photo'])) {
            $this->deleteFile($data['photo']);
        }
    }
}
