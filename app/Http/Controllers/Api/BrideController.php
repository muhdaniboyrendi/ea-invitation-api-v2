<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
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

            // Check if groom already exists
            $existingGroom = Groom::where('invitation_id', $validated['invitation_id'])->first();
            
            if ($existingGroom) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Groom info already exists for this invitation. Use update instead.'
                ], 409);
            }

            return DB::transaction(function () use ($validated, $request) {
                // Handle file upload
                if ($request->hasFile('photo')) {
                    $validated['photo'] = $this->uploadFile($request->file('photo'), 'groom/photos');
                }

                $groom = Groom::create($validated);
                $groom->load('invitation');

                return response()->json([
                    'status' => 'success',
                    'message' => 'Groom info created successfully',
                    'data' => $groom,
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
                'message' => 'Failed to create groom info',
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
            $groom = Groom::with('invitation')
                ->where('invitation_id', $invitationId)
                ->first();

            if (!$groom) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Groom info not found'
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Groom info retrieved successfully',
                'data' => $groom
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve groom info',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Groom $groom)
    {
        // Load relationship to check ownership
        $groom->load('invitation');
        
        // Check ownership
        if ($groom->invitation->user_id !== Auth::id()) {
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
        $oldPhoto = $groom->photo;

        try {
            return DB::transaction(function () use ($groom, $validated, $request, $oldPhoto) {
                // Handle file upload
                if ($request->hasFile('photo')) {
                    $validated['photo'] = $this->uploadFile($request->file('photo'), 'groom/photos');
                    $this->deleteFile($oldPhoto);
                }

                $groom->update($validated);
                $groom->load('invitation');

                return response()->json([
                    'status' => 'success',
                    'message' => 'Groom info updated successfully',
                    'data' => $groom
                ]);
            });

        } catch (\Exception $e) {
            // Cleanup newly uploaded files on error
            $this->cleanupFiles($validated);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update groom info',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Groom $groom)
    {
        try {
            // Eager load to avoid N+1
            $groom->load('invitation');
            
            // Check ownership
            if ($groom->invitation->user_id !== Auth::id()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Forbidden access'
                ], 403);
            }

            return DB::transaction(function () use ($groom) {
                $photo = $groom->photo;

                $groom->delete();

                // Delete associated file
                $this->deleteFile($photo);

                return response()->json([
                    'status' => 'success',
                    'message' => 'Groom info deleted successfully',
                ]);
            });

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete groom info',
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
