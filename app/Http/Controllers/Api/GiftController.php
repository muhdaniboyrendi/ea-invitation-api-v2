<?php

namespace App\Http\Controllers\Api;

use App\Models\Gift;
use App\Models\Invitation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class GiftController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(string $invitationId)
    {
        try {
            $gifts = Gift::where('invitation_id', $invitationId)->get();

            return response()->json([
                'status' => 'success',
                'message' => 'Gift accounts retrieved successfully',
                'data' => $gifts
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve gift accounts',
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
            'bank_name' => 'required|string|max:255',
            'account_number' => 'required|string|max:255',
            'account_holder' => 'required|string|max:255',
        ], [
            'bank_name.required' => 'Bank name or e-wallet name is required',
            'account_number.required' => 'Account number or e-wallet number is required',
            'account_holder.required' => 'Account holder name is required',
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

            return DB::transaction(function () use ($validated) {
                $gift = Gift::create($validated);
                $gift->load('invitation');

                return response()->json([
                    'status' => 'success',
                    'message' => 'Gift account created successfully',
                    'data' => $gift,
                ], 201);
            });

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invitation not found or access denied'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create gift account',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Gift $gift)
    {
        try {
            // Eager load to avoid N+1
            $gift->load('invitation');
            
            // Check ownership
            if ($gift->invitation->user_id !== Auth::id()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Forbidden access'
                ], 403);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Gift account retrieved successfully',
                'data' => $gift
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve gift account',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Gift $gift)
    {
        // Load relationship to check ownership
        $gift->load('invitation');
        
        // Check ownership
        if ($gift->invitation->user_id !== Auth::id()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Forbidden access'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'bank_name' => 'required|string|max:255',
            'account_number' => 'required|string|max:255',
            'account_holder' => 'required|string|max:255',
        ], [
            'bank_name.required' => 'Bank name or e-wallet name is required',
            'account_number.required' => 'Account number or e-wallet number is required',
            'account_holder.required' => 'Account holder name is required',
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
            return DB::transaction(function () use ($gift, $validated) {
                $gift->update($validated);
                $gift->load('invitation');

                return response()->json([
                    'status' => 'success',
                    'message' => 'Gift account updated successfully',
                    'data' => $gift
                ]);
            });

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update gift account',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Gift $gift)
    {
        try {
            // Eager load to avoid N+1
            $gift->load('invitation');
            
            // Check ownership
            if ($gift->invitation->user_id !== Auth::id()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Forbidden access'
                ], 403);
            }

            return DB::transaction(function () use ($gift) {
                $gift->delete();

                return response()->json([
                    'status' => 'success',
                    'message' => 'Gift account deleted successfully',
                ]);
            });

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete gift account',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
}
