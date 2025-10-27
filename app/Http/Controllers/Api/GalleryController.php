<?php

namespace App\Http\Controllers\Api;

use App\Models\Gallery;
use App\Models\Invitation;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class GalleryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(string $invitationId)
    {
        try {
            $galleries = Gallery::where('invitation_id', $invitationId)->get();

            return response()->json([
                'status' => 'success',
                'message' => 'Galleries retrieved successfully',
                'data' => $galleries
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve galleries',
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
            $maxImages = $this->getMaxImagesForPackage($packageId);

            $currentGalleryCount = Gallery::where('invitation_id', $invitation->id)->count();

            if ($maxImages !== null) {
                $requestedImagesCount = count($request->file('images') ?? []);
                $totalAfterUpload = $currentGalleryCount + $requestedImagesCount;

                if ($totalAfterUpload > $maxImages) {
                    $remainingSlots = max(0, $maxImages - $currentGalleryCount);
                    return response()->json([
                        'status' => 'error',
                        'message' => "Gallery limit exceeded. You can only upload {$remainingSlots} more image(s). Current: {$currentGalleryCount}, Max: {$maxImages}",
                        'data' => [
                            'current_count' => $currentGalleryCount,
                            'max_allowed' => $maxImages,
                            'remaining_slots' => $remainingSlots,
                            'requested_count' => $requestedImagesCount
                        ]
                    ], 422);
                }
            }

            $validationRules = [
                'invitation_id' => 'required|exists:invitations,id',
                'images' => 'required|array|min:1',
                'images.*' => 'required|file|mimes:jpg,jpeg,png,webp|max:2048',
            ];

            if ($maxImages !== null) {
                $validationRules['images'] .= "|max:{$maxImages}";
            }

            $validator = Validator::make($request->all(), $validationRules, [
                'images.max' => $maxImages ? "Maximum {$maxImages} images allowed for your package." : "Too many images.",
                'images.*.mimes' => 'Each image must be a file of type: jpg, jpeg, png, webp.',
                'images.*.max' => 'Each image must not be greater than 2MB.',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $validated = $validator->validated();

            return DB::transaction(function () use ($validated, $request, $maxImages, $currentGalleryCount) {
                $uploadedImages = [];
                $galleryIds = [];

                try {
                    // Upload all images
                    foreach ($request->file('images') as $image) {
                        $imagePath = $this->uploadFile($image, 'galleries/images');
                        $uploadedImages[] = $imagePath;

                        $gallery = Gallery::create([
                            'invitation_id' => $validated['invitation_id'],
                            'image' => $imagePath,
                        ]);

                        $galleryIds[] = $gallery->id;
                    }

                    $galleries = Gallery::whereIn('id', $galleryIds)->get();

                    $message = count($galleries) . ' gallery image(s) created successfully';

                    if ($maxImages !== null) {
                        $newCount = $currentGalleryCount + count($galleries);
                        $responseData['gallery_info'] = [
                            'current_count' => $newCount,
                            'max_allowed' => $maxImages,
                            'remaining_slots' => $maxImages - $newCount
                        ];
                    }

                    return response()->json([
                        'status' => 'success',
                        'message' => $message,
                        'data' => $galleries,
                    ], 201);

                } catch (\Exception $e) {
                    // Cleanup all uploaded images if any error occurs
                    foreach ($uploadedImages as $imagePath) {
                        $this->deleteFile($imagePath);
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
                // 'trace' => config('app.debug') ? $e->getTraceAsString() : null // Untuk debugging
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Gallery $gallery)
    {
        try {
            // Eager load to avoid N+1
            $gallery->load('invitation');
            
            // Check ownership
            if ($gallery->invitation->user_id !== Auth::id()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Forbidden access'
                ], 403);
            }

            return DB::transaction(function () use ($gallery) {
                $image = $gallery->image;

                $gallery->delete();

                // Delete associated file
                $this->deleteFile($image);

                return response()->json([
                    'status' => 'success',
                    'message' => 'Gallery deleted successfully',
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
     * Bulk delete galleries
     */
    public function bulkDestroy(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ids' => 'required|array|min:1',
            'ids.*' => 'required|exists:galleries,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Get galleries with ownership check
            $galleries = Gallery::whereIn('id', $request->ids)
                ->with('invitation')
                ->get();

            // Check ownership for all galleries
            foreach ($galleries as $gallery) {
                if ($gallery->invitation->user_id !== Auth::id()) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Forbidden access to one or more galleries'
                    ], 403);
                }
            }

            return DB::transaction(function () use ($galleries) {
                $imagePaths = $galleries->pluck('image')->toArray();
                $deletedCount = $galleries->count();

                // Delete all galleries
                Gallery::whereIn('id', $galleries->pluck('id'))->delete();

                // Delete all associated files
                foreach ($imagePaths as $imagePath) {
                    $this->deleteFile($imagePath);
                }

                return response()->json([
                    'status' => 'success',
                    'message' => $deletedCount . ' gallery image(s) deleted successfully',
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
     * ✅ Helper: Get max images allowed based on package_id
     */
    private function getMaxImagesForPackage(?int $packageId): ?int
    {
        if ($packageId === null) {
            return 10; // Default limit if no package
        }

        switch ($packageId) {
            case 1:
                return 4;
            case 2:
                return 10;
            case 3:
                return 50;
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
        if (isset($data['image'])) {
            $this->deleteFile($data['image']);
        }
    }
}