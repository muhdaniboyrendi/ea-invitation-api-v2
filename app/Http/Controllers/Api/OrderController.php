<?php

namespace App\Http\Controllers\Api;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

class OrderController extends Controller
{
    public function show(Order $order)
    {
        try {
            return response()->json([
                'status' => 'success',
                'message' => 'Order retrieved successfully',
                'data' => $order
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve order',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    public function getOrderStatus($orderId)
    {
        try {
            $order = Order::where('order_id', $orderId)->first();
            
            if (!$order) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Order not found'
                ], 404);
            }

            $user = Auth::user();
            if ($order->user_id !== $user->id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Forbidden access'
                ], 403);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Order status retrieved successfully',
                'data' => $order
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve order status',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    public function getOrders()
    {
        $user = Auth::user();

        if ($user->role != 'admin') {
            return response()->json([
                'status' => 'error',
                'message' => 'Forbidden access'
            ], 403);
        }

        try {
            $orders = Order::with('user', 'package', 'invitation')->get();

            return response()->json([
                'status' => 'success',
                'message' => 'Orders retrieved successfully',
                'data' => $orders
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve orders',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    public function getUserOrders()
    {
        try {
            $user = Auth::user();
            $orders = Order::where('user_id', $user->id)
                ->with('package')
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'status' => 'success',
                'message' => 'User orders retrieved successfully',
                'data' => $orders
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve user orders',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    public function getOrder(string $orderId)
    {
        try {
            $order = Order::where('order_id', $orderId)->with('package')->first();

            if (!$order) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Order not found'
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Order retrieved successfully',
                'data' => $order
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve order',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Cancel order (if still pending)
     */
    public function cancelOrder($orderId)
    {
        try {
            return DB::transaction(function () use ($orderId) {
                $order = Order::where('order_id', $orderId)->first();
                
                if (!$order) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Order not found'
                    ], 404);
                }

                $user = Auth::user();
                if ($order->user_id !== $user->id) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Forbidden access'
                    ], 403);
                }

                if ($order->payment_status !== 'pending') {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Only pending orders can be canceled'
                    ], 400);
                }

                $order->update(['payment_status' => 'canceled']);

                return response()->json([
                    'status' => 'success',
                    'message' => 'Order canceled successfully',
                    'data' => $order->fresh()
                ]);
            });
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to cancel order',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
}
