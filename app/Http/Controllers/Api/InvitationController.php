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
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {   
        $validator = Validator::make($request->all(), [
            'order_id' => 'required',
            'theme_id' => 'required|exists:themes,id',
            'groom_name' => 'required|string|max:50',
            'bride_name' => 'required|string|max:50',
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
                    'groom_name' => $request->groom_name,
                    'bride_name' => $request->bride_name,
                    'status' => 'draft',
                    'expiry_date' => $expiryDate,
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
            $invitation = Invitation::with(['order.package', 'theme', 'mainInfo', 'guests'])->find($id);

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
            
            $invitations = Invitation::with(['mainInfo', 'guests'])
                ->where('user_id', $user->id)
                ->latest()
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
                $invitation = Invitation::with('order.package')->findOrFail($id);

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
     * Update invitation couple.
     */
    public function updateCouple(Request $request, string $id)
    {
        $validator = Validator::make($request->all(), [
            'groom_name' => 'required|string|max:50',
            'bride_name' => 'required|string|max:50'
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
            return DB::transaction(function () use ($user, $request, $id) {
                $invitation = Invitation::findOrFail($id);

                if ($invitation->user_id !== $user->id) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Forbidden: You do not have permission to update this invitation'
                    ], 403);
                }

                $invitation->update([
                    'groom_name' => $request->groom_name,
                    'bride_name' => $request->bride_name,
                ]);

                return response()->json([
                    'status' => 'success',
                    'message' => 'Invitation created successfully',
                    'data' => $invitation
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
     * Update invitation theme.
     */
    public function updateTheme(Request $request, string $id)
    {
        $validator = Validator::make($request->all(), [
            'theme_id' => 'required|exists:themes,id'
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
            return DB::transaction(function () use ($user, $request, $id) {
                $invitation = Invitation::findOrFail($id);

                if ($invitation->user_id !== $user->id) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Forbidden: You do not have permission to update this invitation'
                    ], 403);
                }

                $invitation->update([
                    'theme_id' => $request->theme_id,
                ]);

                return response()->json([
                    'status' => 'success',
                    'message' => 'Invitation created successfully',
                    'data' => $invitation
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
    public function checkInvitationBySlug(string $slug)
    {
        try {
            $invitation = Invitation::with(['theme'])->where('slug', $slug)->first();

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
                    'mainInfo.music', 
                    'groom', 
                    'bride', 
                    'events', 
                    'loveStories', 
                    'galleries', 
                    'gifts', 
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
