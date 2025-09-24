<?php

namespace App\Http\Controllers\Api;

use App\Models\Order;
use App\Models\Package;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class PaymentController extends Controller
{
    public function createPayment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'package_id' => 'required|exists:packages,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = Auth::user();
        $package = Package::find($request->package_id);
        
        if (!$package) {
            return response()->json([
                'status' => 'error',
                'message' => 'Package not found'
            ], 404);
        }

        try {
            return DB::transaction(function () use ($user, $package) {
                $finalPrice = $package->price;
                
                if ($package->discount) {
                    $finalPrice = $package->price - ($package->price * $package->discount / 100);
                }

                do {
                    $orderId = 'ORDER-' . strtoupper(Str::random(6));
                } while (Order::where('order_id', $orderId)->exists());

                $order = Order::create([
                    'user_id' => $user->id,
                    'package_id' => $package->id,
                    'order_id' => $orderId,
                    'amount' => $finalPrice,
                    'payment_status' => 'pending'
                ]);

                $grossAmount = (int) $order->amount;

                $params = [
                    'transaction_details' => [
                        'order_id' => $order->order_id,
                        'gross_amount' => $grossAmount,
                    ],
                    'customer_details' => [
                        'first_name' => $user->name,
                        'email' => $user->email,
                        'phone' => $user->phone ?? '',
                    ],
                    'item_details' => [
                        [
                            'id' => $package->id,
                            'price' => $grossAmount,
                            'quantity' => 1,
                            'name' => $package->name,
                        ]
                    ],
                ];

                $snapData = $this->getSnapTokenWithHttpRequest($params);
                
                $order->update([
                    'snap_token' => $snapData['token'],
                    'midtrans_url' => $snapData['redirect_url']
                ]);

                return response()->json([
                    'status' => 'success',
                    'message' => 'Payment created successfully',
                    'data' => [
                        'order_id' => $order->order_id,
                        'amount' => $order->amount,
                        'snap_token' => $snapData['token'],
                        'redirect_url' => $snapData['redirect_url']
                    ]
                ], 201);
            });
        } catch (\Exception $e) {
            // Update payment status to canceled if order was created
            if (isset($order)) {
                $order->update(['payment_status' => 'canceled']);
            }
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create payment',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    public function updatePayment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'order_id' => 'required|exists:orders,order_id',
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
                    'message' => 'Forbidden access'
                ], 403);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Payment retrieved successfully',
                'data' => [
                    'order_id' => $order->order_id,
                    'amount' => $order->amount,
                    'snap_token' => $order->snap_token,
                    'redirect_url' => $order->midtrans_url
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve payment',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    public function updatePaymentStatus(Request $request) 
    {
        $validator = Validator::make($request->all(), [
            'order_id' => 'required|exists:orders,order_id',
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
            return DB::transaction(function () use ($request, $user) {
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
                        'message' => 'Forbidden access'
                    ], 403);
                }

                $order->update(['payment_status' => 'paid']);

                return response()->json([
                    'status' => 'success',
                    'message' => 'Payment status updated successfully',
                    'data' => $order->fresh()
                ]);
            });
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update payment status',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    public function handleNotification(Request $request)
    {
        try {
            return DB::transaction(function () use ($request) {
                $notificationBody = json_decode($request->getContent(), true);
                
                $signatureKey = env('MIDTRANS_SERVER_KEY');
                $orderId = $notificationBody['order_id'];
                $statusCode = $notificationBody['status_code'];
                $grossAmount = $notificationBody['gross_amount'];
                $serverKey = $signatureKey;
                $signature = hash('sha512', $orderId . $statusCode . $grossAmount . $serverKey);
                
                if ($signature !== $notificationBody['signature_key']) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Invalid signature'
                    ], 403);
                }

                $order = Order::where('order_id', $orderId)->first();

                if (!$order) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Order not found'
                    ], 404);
                }

                $transactionStatus = $notificationBody['transaction_status'];
                $fraudStatus = $notificationBody['fraud_status'] ?? null;
                $paymentType = $notificationBody['payment_type'] ?? null;
                $transactionId = $notificationBody['transaction_id'] ?? null;

                if ($transactionStatus == 'capture') {
                    if ($fraudStatus == 'challenge') {
                        $paymentStatus = 'pending';
                    } else if ($fraudStatus == 'accept') {
                        $paymentStatus = 'paid';
                    }
                } else if ($transactionStatus == 'settlement') {
                    $paymentStatus = 'paid';
                } else if ($transactionStatus == 'deny') {
                    $paymentStatus = 'canceled';
                } else if ($transactionStatus == 'cancel' || $transactionStatus == 'expire') {
                    $paymentStatus = $transactionStatus == 'cancel' ? 'canceled' : 'expired';
                } else if ($transactionStatus == 'pending') {
                    $paymentStatus = 'pending';
                }

                $order->update([
                    'payment_status' => $paymentStatus,
                    'payment_method' => $paymentType,
                    'midtrans_transaction_id' => $transactionId
                ]);

                if ($paymentStatus === 'paid') {
                    // make something
                }

                return response()->json([
                    'status' => 'success',
                    'message' => 'Notification processed successfully'
                ]);
            });
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to process notification',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Handle recurring notification from Midtrans
     */
    public function handleRecurringNotification(Request $request)
    {
        try {
            return DB::transaction(function () use ($request) {
                $notificationBody = json_decode($request->getContent(), true);
                
                // Verifikasi signature seperti di handleNotification
                $signatureKey = env('MIDTRANS_SERVER_KEY');
                $orderId = $notificationBody['order_id'];
                $statusCode = $notificationBody['status_code'];
                $grossAmount = $notificationBody['gross_amount'];
                $serverKey = $signatureKey;
                $signature = hash('sha512', $orderId . $statusCode . $grossAmount . $serverKey);
                
                if ($signature !== $notificationBody['signature_key']) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Invalid signature'
                    ], 403);
                }

                // Logika untuk menangani recurring payment
                // Mirip dengan handleNotification tetapi untuk subscription/recurring

                return response()->json([
                    'status' => 'success',
                    'message' => 'Recurring notification processed successfully'
                ]);
            });
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to process recurring notification',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Handle account notification from Midtrans
     */
    public function handleAccountNotification(Request $request)
    {
        try {
            return DB::transaction(function () use ($request) {
                $notificationBody = json_decode($request->getContent(), true);
                
                // Verifikasi signature
                $signatureKey = env('MIDTRANS_SERVER_KEY');
                $orderId = $notificationBody['order_id'];
                $statusCode = $notificationBody['status_code'];
                $grossAmount = $notificationBody['gross_amount'];
                $serverKey = $signatureKey;
                $signature = hash('sha512', $orderId . $statusCode . $grossAmount . $serverKey);
                
                if ($signature !== $notificationBody['signature_key']) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Invalid signature'
                    ], 403);
                }

                // Logika untuk menangani notifikasi akun pembayaran

                return response()->json([
                    'status' => 'success',
                    'message' => 'Account notification processed successfully'
                ]);
            });
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to process account notification',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    private function getSnapTokenWithHttpRequest(array $params)
    {
        $isProduction = env('MIDTRANS_IS_PRODUCTION', false);
        $serverKey = env('MIDTRANS_SERVER_KEY');
        
        if (empty($serverKey)) {
            throw new \Exception('Midtrans Server Key is not set');
        }
        
        $baseUrl = $isProduction
            ? 'https://app.midtrans.com/snap/v1/transactions'
            : 'https://app.sandbox.midtrans.com/snap/v1/transactions';
        
        $client = new \GuzzleHttp\Client();
        
        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'Authorization' => 'Basic ' . base64_encode($serverKey . ':')
        ];
        
        $response = $client->post($baseUrl, [
            'headers' => $headers,
            'json' => $params
        ]);
        
        $statusCode = $response->getStatusCode();
        
        if ($statusCode !== 201) {
            throw new \Exception('Failed to get Snap Token from Midtrans. Status code: ' . $statusCode);
        }
        
        $responseData = json_decode($response->getBody()->getContents(), true);
        
        if (!isset($responseData['token']) || !isset($responseData['redirect_url'])) {
            throw new \Exception('Invalid response from Midtrans');
        }
        
        return [
            'token' => $responseData['token'],
            'redirect_url' => $responseData['redirect_url']
        ];
    }
}
