<?php

namespace App\Http\Controllers\Api;

use App\Models\Video;
use App\Models\Invitation;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class VideoController extends Controller
{
     /**
     * Display a listing of the resource.
     */
    public function index(string $invitationId)
    {
        try {
            $videos = Video::where('invitation_id', $invitationId)->get();

            return response()->json([
                'status' => 'success',
                'message' => 'Videos retrieved successfully',
                'data' => $videos
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve videos',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     * Supports single or multiple image uploads
     */
    public function store(Request $request)
    {
        try {
            $invitation = Invitation::with('order.package')
                ->where('id', $request->invitation_id)
                ->where('user_id', Auth::id())
                ->firstOrFail();

            $packageId = $invitation->order->package_id ?? null;
            $maxVideos = $this->getMaxVideosForPackage($packageId);

            if ($packageId === 1) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Video upload is not allowed for your package',
                    'data' => [
                        'package_id' => $packageId,
                        'max_allowed' => 0
                    ]
                ], 422);
            }

            $currentVideoCount = Video::where('invitation_id', $invitation->id)->count();

            $validationRules = [
                'invitation_id' => 'required|exists:invitations,id',
                'videos' => 'required|array|min:1',
                'videos.*' => 'required|file|mimes:mp4,webm,mov,avi,wmv|max:102400',
            ];

            if ($maxVideos !== null) {
                $validationRules['videos'] .= "|max:{$maxVideos}";

                $requestedVideosCount = count($request->file('videos') ?? []);
                $totalAfterUpload = $currentVideoCount + $requestedVideosCount;

                if ($totalAfterUpload > $maxVideos) {
                    $remainingSlots = $maxVideos - $currentVideoCount;
                    return response()->json([
                        'status' => 'error',
                        'message' => "Video limit exceeded. You can only upload {$remainingSlots} more video(s). Current: {$currentVideoCount}, Max: {$maxVideos}",
                        'data' => [
                            'current_count' => $currentVideoCount,
                            'max_allowed' => $maxVideos,
                            'remaining_slots' => $remainingSlots,
                            'requested_count' => $requestedVideosCount
                        ]
                    ], 422);
                }
            }

            $validator = Validator::make($request->all(), $validationRules, [
                'videos.max' => "Maximum {$maxVideos} videos allowed for your package.",
                'videos.*.mimes' => 'Each video must be a file of type: mp4, mov, avi, wmv.',
                'videos.*.max' => 'Each video must not be greater than 2MB.',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $validated = $validator->validated();

            return DB::transaction(function () use ($validated, $request, $maxVideos, $currentVideoCount) {
                $uploadedVideos = [];
                $videoIds = [];

                try {
                    // Upload all videos
                    foreach ($request->file('videos') as $video) {
                        $videoPath = $this->uploadFile($video, 'galleries/videos');
                        $uploadedVideos[] = $videoPath;

                        $video = Video::create([
                            'invitation_id' => $validated['invitation_id'],
                            'video' => $videoPath,
                        ]);

                        $videoIds[] = $video->id;
                    }

                    $videos = Video::whereIn('id', $videoIds)->get();

                    $message = count($videos) . ' video(s) created successfully';

                    if ($maxVideos !== null) {
                        $newCount = $currentVideoCount + count($videos);
                        $responseData['video_info'] = [
                            'current_count' => $newCount,
                            'max_allowed' => $maxVideos,
                            'remaining_slots' => $maxVideos - $newCount
                        ];
                    }

                    return response()->json([
                        'status' => 'success',
                        'message' => $message,
                        'data' => $videos,
                    ], 201);

                } catch (\Exception $e) {
                    // Cleanup all uploaded videos if any error occurs
                    foreach ($uploadedVideos as $videoPath) {
                        $this->deleteFile($videoPath);
                    }
                    throw $e;
                }
            });

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invitation not found or access denied'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create gallery',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Video $video)
    {
        try {
            // Eager load to avoid N+1
            $video->load('invitation');
            
            // Check ownership
            if ($video->invitation->user_id !== Auth::id()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Forbidden access'
                ], 403);
            }

            return DB::transaction(function () use ($video) {
                $videoPath = $video->video;

                $video->delete();

                // Delete associated file
                $this->deleteFile($videoPath);

                return response()->json([
                    'status' => 'success',
                    'message' => 'Video deleted successfully',
                ]);
            });

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete gallery',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Bulk delete videos
     */
    public function bulkDestroy(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ids' => 'required|array|min:1',
            'ids.*' => 'required|exists:videos,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Get videos with ownership check
            $videos = Video::whereIn('id', $request->ids)
                ->with('invitation')
                ->get();

            // Check ownership for all videos
            foreach ($videos as $video) {
                if ($video->invitation->user_id !== Auth::id()) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Forbidden access to one or more videos'
                    ], 403);
                }
            }

            return DB::transaction(function () use ($videos) {
                $videoPaths = $videos->pluck('video')->toArray();
                $deletedCount = $videos->count();

                // Delete all videos
                Video::whereIn('id', $videos->pluck('id'))->delete();

                // Delete all associated files
                foreach ($videoPaths as $videoPath) {
                    $this->deleteFile($videoPath);
                }

                return response()->json([
                    'status' => 'success',
                    'message' => $deletedCount . ' video(s) deleted successfully',
                ]);
            });

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete galleries',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * ✅ Helper: Get max videos allowed based on package_id
     * 
     * Package limits:
     * - package_id = 1: 0 videos (not allowed)
     * - package_id = 2: 1 video
     * - package_id = 3: 10 videos
     * - package_id > 3: unlimited (null)
     */
    private function getMaxVideosForPackage(?int $packageId): ?int
    {
        if ($packageId === null) {
            return 1; // Default limit if no package
        }

        switch ($packageId) {
            case 1:
                return 0; // No videos allowed
            case 2:
                return 1; // 1 video allowed
            case 3:
                return 10; // 10 videos allowed
            default:
                // package_id > 3 = unlimited
                return null;
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
        if (isset($data['video'])) {
            $this->deleteFile($data['video']);
        }
    }
}