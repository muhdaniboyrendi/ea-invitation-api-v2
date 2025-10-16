<?php

namespace App\Http\Controllers\Api;

use App\Models\Order;
use App\Models\Invitation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class InvitationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try {
            $invitations = Invitation::with(['user', 'order', 'theme'])
                ->paginate(15);

            return response()->json([
                'status' => 'success',
                'message' => 'Invitations retrieved successfully',
                'data' => $invitations
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve invitations',
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
            'order_id' => 'required',
            'theme_id' => 'required|exists:themes,id',
            'groom' => 'required|string|max:50',
            'bride' => 'required|string|max:50'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = Auth::user();

        $order = Order::where('order_id', $request->order_id)->first();

        if (!$order) {
            return response()->json([
                'status' => 'error',
                'message' => 'Order not found'
            ], 404);
        }

        if ($order->user_id !== $user->id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Forbidden: You do not have permission to create an invitation for this order'
            ], 403);
        }

        // Check duplicate invitation
        $existingInvitation = Invitation::where('order_id', $request->order_id)->exists();

        if ($existingInvitation) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invitation already exists for this order'
            ], 409);
        }

        // Calculate expiry date based on package
        $expiryDate = $this->calculateExpiryDate($order->package->id);

        try {
            return DB::transaction(function () use ($user, $order, $request, $expiryDate) {
                $invitation = Invitation::create([
                    'user_id' => $user->id,
                    'order_id' => $order->id,
                    'theme_id' => $request->theme_id,
                    'status' => 'draft',
                    'expiry_date' => $expiryDate,
                    'groom' => $request->groom,
                    'bride' => $request->bride,
                ]);

                return response()->json([
                    'status' => 'success',
                    'message' => 'Invitation created successfully',
                    'data' => $invitation->load(['user', 'order', 'theme'])
                ], 201);
            });

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create invitation',
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
            $invitation = Invitation::with(['order.package', 'theme', 'guests'])->find($id);

            if (!$invitation) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invitation not found'
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Invitation retrieved successfully',
                'data' => $invitation
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve invitation',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $validator = Validator::make(array_merge($request->all(), ['id' => $id]), [
            'id' => 'required|exists:invitations,id',
            'theme_id' => 'sometimes|exists:themes,id',
            'groom' => 'sometimes|string|max:50',
            'bride' => 'sometimes|string|max:50'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = Auth::user();

        try {
            return DB::transaction(function () use ($user, $validator, $id) {
                $invitation = Invitation::with(['order.package'])->find($id);

                if (!$invitation) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Invitation not found'
                    ], 404);
                }

                // Authorization check
                if ($invitation->user_id !== $user->id) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Forbidden: You do not have permission to update this invitation'
                    ], 403);
                }

                // Expiry check
                if ($invitation->expiry_date && now()->gt($invitation->expiry_date)) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Cannot update expired invitation'
                    ], 400);
                }

                // Prepare update data
                $updateData = $validator->validated();
                unset($updateData['id']);

                $invitation->update($updateData);

                return response()->json([
                    'status' => 'success',
                    'message' => 'Invitation updated successfully',
                    'data' => $invitation->fresh()->load(['user', 'order', 'theme'])
                ], 200);
            });

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update invitation',
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

        try {
            return DB::transaction(function () use ($user, $id) {
                $invitation = Invitation::find($id);

                if (!$invitation) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Invitation not found'
                    ], 404);
                }

                // Authorization check
                if ($invitation->user_id !== $user->id) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Forbidden: You do not have permission to delete this invitation'
                    ], 403);
                }

                $invitation->delete();

                return response()->json([
                    'status' => 'success',
                    'message' => 'Invitation deleted successfully'
                ], 200);
            });

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete invitation',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Check invitation by order ID.
     */
    public function checkByOrderId($orderId)
    {
        $user = Auth::user();
        $order = Order::where('order_id', $orderId)->first();

        if (!$order) {
            return response()->json([
                'status' => 'error',
                'message' => 'Order not found'
            ], 404);
        }

        // Authorization check
        if ($user->id !== $order->user_id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Forbidden: You do not have permission to check this order'
            ], 403);
        }

        try {
            $invitation = Invitation::where('order_id', $order->id)->first();

            if (!$invitation) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invitation not found for this order',
                ], 404);
            }
            
            return response()->json([
                'status' => 'success',
                'message' => 'Invitation found',
                'data' => $invitation
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error checking invitation',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Display all invitations for the authenticated user.
     */
    public function showUserInvitations()
    {
        try {
            $user = Auth::user();
            
            $invitations = Invitation::with(['guests'])
                ->where('user_id', $user->id)
                ->get();

            return response()->json([
                'status' => 'success',
                'message' => 'User invitations retrieved successfully',
                'data' => $invitations
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve user invitations',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Publish invitation and generate slug.
     */
    public function completeInvitation($id)
    {
        $user = Auth::user();

        try {
            return DB::transaction(function () use ($user, $id) {
                $invitation = Invitation::findOrFail($id);

                // Authorization check
                if ($invitation->user_id !== $user->id) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Forbidden: You do not have permission to publish this invitation'
                    ], 403);
                }

                if (empty($invitation->slug)) {
                    $invitation->slug = $invitation->generateUniqueSlug();
                }
                
                $invitation->status = 'published';
                $invitation->save();

                return response()->json([
                    'status' => 'success',
                    'message' => 'Invitation published successfully',
                    'data' => $invitation
                ], 200);
            });

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update invitation status',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get invitation by slug (public access).
     */
    public function getInvitationBySlug(string $slug)
    {
        try {
            $invitation = Invitation::where('slug', $slug)
                ->where('status', 'published')
                ->with(['theme'])
                ->first();

            if (!$invitation) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invitation not found'
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Invitation retrieved successfully',
                'data' => $invitation
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve invitation',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get full invitation detail by slug (public access).
     */
    public function getInvitationDetailBySlug(string $slug)
    {
        try {
            $invitation = Invitation::where('slug', $slug)
                ->where('status', 'published')
                ->with([
                    'theme', 
                    'guests', 
                    'mainInfo.backsound', 
                    'groomInfo', 
                    'brideInfo', 
                    'events', 
                    'loveStories', 
                    'galleryImages', 
                    'giftInfo', 
                    'comments'
                ])
                ->first();

            if (!$invitation) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invitation not found'
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Invitation retrieved successfully',
                'data' => $invitation
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve invitation',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Helper: Calculate expiry date based on package ID.
     */
    private function calculateExpiryDate(int $packageId)
    {
        $expiryDays = match($packageId) {
            1 => 30,
            2 => 90,
            3 => 180,
            4 => 360,
            default => throw new \Exception('Invalid package ID: ' . $packageId)
        };

        return now()->addDays($expiryDays);
    }
}
