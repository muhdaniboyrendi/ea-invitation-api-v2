<?php

namespace App\Http\Controllers\Api;

use App\Models\Comment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;

class CommentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'invitation_id' => 'required|exists:invitations,id',
            'name' => 'required|string|max:100',
            'message' => 'required|string|max:1000',
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
            return DB::transaction(function () use ($validated) {
                $comment = Comment::create([
                    'invitation_id' => $validated['invitation_id'],
                    'name' => $validated['name'],
                    'message' => $validated['message'],
                ]);

                return response()->json([
                    'status' => 'success',
                    'message' => 'Comment created successfully',
                    'data' => $comment
                ], 201);
            });
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create comment',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Comment $comment)
    {
        try {
            return response()->json([
                'status' => 'success',
                'message' => 'Comment retrieved successfully',
                'data' => $comment
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve comment',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Comment $comment)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
            'message' => 'required|string|max:1000',
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
            return DB::transaction(function () use ($comment, $validated) {
                $comment->update([
                    'name' => $validated['name'],
                    'message' => $validated['message'],
                ]);

                return response()->json([
                    'status' => 'success',
                    'message' => 'Comment updated successfully',
                    'data' => $comment->fresh()
                ]);
            });
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update comment',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Comment $comment)
    {
        try {
            return DB::transaction(function () use ($comment) {
                $comment->delete();

                return response()->json([
                    'status' => 'success',
                    'message' => 'Comment deleted successfully'
                ]);
            });
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete comment',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
}
