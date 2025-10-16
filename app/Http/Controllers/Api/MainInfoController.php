<?php

namespace App\Http\Controllers\Api;

use App\Models\MainInfo;
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
     * Display a listing of the resource.
     */
    // public function index()
    // {
    //     try {
    //         $user = Auth::user();
            
    //         $mainInfos = MainInfo::with(['invitation', 'backsound'])
    //             ->whereHas('invitation', function($query) use ($user) {
    //                 $query->where('user_id', $user->id);
    //             })
    //             ->paginate(10);

    //         return response()->json([
    //             'status' => 'success',
    //             'message' => 'Main infos retrieved successfully',
    //             'data' => $mainInfos,
    //         ]);
    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'status' => 'error',
    //             'message' => 'Failed to retrieve main infos',
    //             'error' => config('app.debug') ? $e->getMessage() : null
    //         ], 500);
    //     }
    // }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'invitation_id' => 'required|exists:invitations,id',
            'backsound_id' => 'nullable|exists:backsounds,id',
            'main_photo' => 'nullable|file|mimes:jpg,jpeg,png,webp|max:2048',
            'wedding_date' => 'required|date|after_or_equal:today',
            'wedding_time' => 'required|date_format:H:i',
            'time_zone' => 'required|in:WIB,WITA,WIT',
            'custom_backsound' => 'nullable|file|mimes:mp3,wav,ogg|max:10240',
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
            return DB::transaction(function () use ($validated, $request) {
                // Check if main info already exists
                $existingMainInfo = MainInfo::where('invitation_id', $validated['invitation_id'])->first();
                
                if ($existingMainInfo) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Main info already exists for this invitation. Use update instead.'
                    ], 409);
                }

                // Handle file uploads
                if ($request->hasFile('main_photo')) {
                    $validated['main_photo'] = $this->uploadFile($request->file('main_photo'), 'main/photos');
                }

                if ($request->hasFile('custom_backsound')) {
                    $validated['custom_backsound'] = $this->uploadFile($request->file('custom_backsound'), 'main/backsounds');
                }

                $mainInfo = MainInfo::create($validated);
                $mainInfo->load(['invitation', 'backsound']);

                return response()->json([
                    'status' => 'success',
                    'message' => 'Main info created successfully',
                    'data' => $mainInfo,
                ], 201);
            });

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
            $mainInfo = MainInfo::with(['invitation', 'backsound'])
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
        $validator = Validator::make($request->all(), [
            'backsound_id' => 'nullable|exists:backsounds,id',
            'main_photo' => 'nullable|file|mimes:jpg,jpeg,png,webp|max:2048',
            'wedding_date' => 'required|date|after_or_equal:today',
            'wedding_time' => 'required|date_format:H:i',
            'time_zone' => 'required|in:WIB,WITA,WIT',
            'custom_backsound' => 'nullable|file|mimes:mp3,wav,ogg|max:10240',
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
                $mainInfo->load(['invitation', 'backsound']);

                return response()->json([
                    'status' => 'success',
                    'message' => 'Main info updated successfully',
                    'data' => $mainInfo->fresh(['invitation', 'backsound'])
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
     * Update main info by invitation ID.
     */
    public function updateByInvitationId(Request $request, string $invitationId)
    {
        try {
            $mainInfo = MainInfo::where('invitation_id', $invitationId)->firstOrFail();
            
            // Reuse the update logic
            return $this->update($request, $mainInfo);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Main info not found for this invitation',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 404);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(MainInfo $mainInfo)
    {
        try {
            $user = Auth::user();
            
            // Check ownership
            if ($mainInfo->invitation->user_id !== $user->id) {
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
     * Add or update photo for main info
     */
    public function addOrUpdatePhoto(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'invitation_id' => 'required|exists:invitations,id',
            'main_photo' => 'required|file|mimes:jpg,jpeg,png,webp|max:2048',
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
            $mainInfo = MainInfo::where('invitation_id', $validated['invitation_id'])->first();
            
            if (!$mainInfo) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Main info not found for this invitation'
                ], 404);
            }

            return DB::transaction(function () use ($mainInfo, $request) {
                $oldMainPhoto = $mainInfo->main_photo;

                $newPhotoPath = $this->uploadFile($request->file('main_photo'), 'main/photos');

                $mainInfo->update(['main_photo' => $newPhotoPath]);

                // Delete old photo
                $this->deleteFile($oldMainPhoto);

                $mainInfo->load(['invitation', 'backsound']);

                return response()->json([
                    'status' => 'success',
                    'message' => 'Main photo updated successfully',
                    'data' => $mainInfo,
                ]);
            });

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update main photo',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get photo for main info
     */
    public function getPhoto(string $invitationId)
    {
        $validator = Validator::make(['invitation_id' => $invitationId], [
            'invitation_id' => 'required|exists:invitations,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $mainInfo = MainInfo::where('invitation_id', $invitationId)->first();
            
            if (!$mainInfo) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Main info not found for this invitation'
                ], 404);
            }

            if (!$mainInfo->main_photo) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No photo found for this main info'
                ], 404);
            }

            if (!Storage::disk('public')->exists($mainInfo->main_photo)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Photo file not found in storage'
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Main photo retrieved successfully',
                'data' => [
                    'photo_url' => $mainInfo->main_photo_url,
                    'photo_path' => $mainInfo->main_photo,
                    'invitation_id' => $invitationId
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve main photo',
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
