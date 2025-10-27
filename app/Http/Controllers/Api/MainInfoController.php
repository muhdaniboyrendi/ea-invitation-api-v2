<?php

namespace App\Http\Controllers\Api;

use App\Models\MainInfo;
use App\Models\Invitation;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class MainInfoController extends Controller
{
    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'invitation_id' => 'required|exists:invitations,id',
            'music_id' => 'nullable|exists:music,id',
            'main_photo' => 'nullable|file|mimes:jpg,jpeg,png,webp|max:2048',
            'groom' => 'required|string|max:50',
            'bride' => 'required|string|max:50',
            'wedding_date' => 'required|date|after_or_equal:' . now()->toDateString(),
            'wedding_time' => 'required|date_format:H:i',
            'time_zone' => 'required|in:WIB,WITA,WIT',
            'custom_backsound' => 'nullable|file|mimes:mp3,wav,ogg|max:20480',
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
            $invitation = Invitation::with('order.package')
                ->where('id', $validated['invitation_id'])
                ->where('user_id', Auth::id())
                ->firstOrFail();

            // Check if main info already exists
            $existingMainInfo = MainInfo::where('invitation_id', $validated['invitation_id'])->first();
            
            if ($existingMainInfo) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Main info already exists for this invitation. Use update instead.'
                ], 409);
            }

            // Validate package restrictions BEFORE transaction
            if ($invitation->order->package_id == 1 && $request->hasFile('custom_backsound')) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Forbidden: economy package is not allowed to use custom backsound.'
                ], 403);
            }

            return DB::transaction(function () use ($validated, $request) {
                // Handle file uploads
                if ($request->hasFile('main_photo')) {
                    $validated['main_photo'] = $this->uploadFile($request->file('main_photo'), 'main/photos');
                }

                if ($request->hasFile('custom_backsound')) {
                    $validated['custom_backsound'] = $this->uploadFile($request->file('custom_backsound'), 'main/backsounds');
                }

                $mainInfo = MainInfo::create($validated);
                $mainInfo->load(['invitation', 'music']);

                return response()->json([
                    'status' => 'success',
                    'message' => 'Main info created successfully',
                    'data' => $mainInfo,
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
                'message' => 'Failed to create main info',
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
            $mainInfo = MainInfo::with(['invitation', 'music'])
                ->where('invitation_id', $invitationId)
                ->first();

            if (!$mainInfo) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Main info not found'
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Main info retrieved successfully',
                'data' => $mainInfo
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve main info',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, MainInfo $mainInfo)
    {
        // Load relationship to check ownership
        $mainInfo->load('invitation');
        
        // Check ownership
        if ($mainInfo->invitation->user_id !== Auth::id()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Forbidden access'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'music_id' => 'nullable|exists:music,id',
            'main_photo' => 'nullable|file|mimes:jpg,jpeg,png,webp|max:2048',
            'groom' => 'required|string|max:50',
            'bride' => 'required|string|max:50',
            'wedding_date' => 'required|date|after_or_equal:' . now()->toDateString(),
            'wedding_time' => 'required|date_format:H:i',
            'time_zone' => 'required|in:WIB,WITA,WIT',
            'custom_backsound' => 'nullable|file|mimes:mp3,wav,ogg|max:20480',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $validated = $validator->validated();
        $oldFiles = [
            'main_photo' => $mainInfo->main_photo,
            'custom_backsound' => $mainInfo->custom_backsound
        ];

        try {
            // Validate package restrictions BEFORE transaction
            $invitation = Invitation::with('order.package')
                ->findOrFail($mainInfo->invitation_id); // ✅ Fixed: object property access

            if ($invitation->order->package_id == 1 && $request->hasFile('custom_backsound')) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Forbidden: economy package is not allowed to use custom backsound.'
                ], 403);
            }

            return DB::transaction(function () use ($mainInfo, $validated, $request, $oldFiles) {
                // Handle file uploads
                if ($request->hasFile('main_photo')) {
                    $validated['main_photo'] = $this->uploadFile($request->file('main_photo'), 'main/photos');
                    $this->deleteFile($oldFiles['main_photo']);
                }

                if ($request->hasFile('custom_backsound')) {
                    $validated['custom_backsound'] = $this->uploadFile($request->file('custom_backsound'), 'main/backsounds');
                    $this->deleteFile($oldFiles['custom_backsound']);
                }

                $mainInfo->update($validated);
                $mainInfo->load(['invitation', 'music']);

                return response()->json([
                    'status' => 'success',
                    'message' => 'Main info updated successfully',
                    'data' => $mainInfo
                ]);
            });

        } catch (\Exception $e) {
            // Cleanup newly uploaded files on error
            $this->cleanupFiles($validated);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update main info',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(MainInfo $mainInfo)
    {
        try {
            // Eager load to avoid N+1
            $mainInfo->load('invitation');
            
            // Check ownership
            if ($mainInfo->invitation->user_id !== Auth::id()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Forbidden access'
                ], 403);
            }

            return DB::transaction(function () use ($mainInfo) {
                $mainPhoto = $mainInfo->main_photo;
                $customBacksound = $mainInfo->custom_backsound;

                $mainInfo->delete();

                // Delete associated files
                $this->deleteFile($mainPhoto);
                $this->deleteFile($customBacksound);

                return response()->json([
                    'status' => 'success',
                    'message' => 'Main info deleted successfully',
                ]);
            });

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete main info',
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
        if (isset($data['main_photo'])) {
            $this->deleteFile($data['main_photo']);
        }
        if (isset($data['custom_backsound'])) {
            $this->deleteFile($data['custom_backsound']);
        }
    }
}