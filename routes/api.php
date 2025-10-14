<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\GuestController;
use App\Http\Controllers\Api\MusicController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ThemeController;
use App\Http\Controllers\Api\CommentController;
use App\Http\Controllers\Api\PackageController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\InvitationController;
use App\Http\Controllers\Api\GoogleOAuthController;
use App\Http\Controllers\Api\ThemeCategoryController;

// Health Check and API Info
Route::get('/health', function () {
    return response()->json([
        'status' => 'healthy',
        'timestamp' => now()->toISOString(),
        'uptime' => app()->hasBeenBootstrapped(),
        'database' => 'connected' // You can add actual DB check here
    ]);
})->name('health');

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

/*
|--------------------------------------------------------------------------
| Public Authentication Routes
|--------------------------------------------------------------------------
*/
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Google Auth
Route::prefix('auth')->group(function () {
    Route::prefix('google')->name('google.')->group(function () {
        // Web OAuth Flow
        Route::get('/redirect', [GoogleOAuthController::class, 'redirectToGoogle'])->name('redirect');
        Route::get('/callback', [GoogleOAuthController::class, 'handleGoogleCallback'])->name('callback');
        Route::post('/callback', [GoogleOAuthController::class, 'handleGoogleCallback'])->name('callback.post');
    });
});

/*
|--------------------------------------------------------------------------
| Public API Routes
|--------------------------------------------------------------------------
*/
// Packages (Public)
Route::prefix('packages')->group(function () {
    Route::get('/', [PackageController::class, 'index']);
    Route::get('/{id}', [PackageController::class, 'show']);
});

// Themes (Public)
Route::prefix('themes')->group(function () {
    Route::get('/', [ThemeController::class, 'index']);
    Route::get('/{id}', [ThemeController::class, 'show']);
});

// Theme Categories (Public)
Route::prefix('categories')->group(function () {
    Route::get('/', [ThemeCategoryController::class, 'index']);
});

// Musics (Public)
Route::prefix('musics')->group(function () {
    Route::get('/', [MusicController::class, 'index']);
    Route::get('/{id}', [MusicController::class, 'show']);
});

// Guests (Public)
Route::put('/guests/{id}/rsvp', [GuestController::class, 'updateAttendance']);
Route::get('/guests/{invitationId}', [GuestController::class, 'getGuestsByInvitationId']);
Route::get('/guest/{slug}', [GuestController::class, 'checkGuest']);

// Comments (Public)
Route::post('/comments', [CommentController::class, 'store']);

// Payment Webhooks (Public)
Route::prefix('payments')->group(function () {
    Route::post('/notification', [PaymentController::class, 'handleNotification']);
    Route::post('/recurring-notification', [PaymentController::class, 'handleRecurringNotification']);
    Route::post('/account-notification', [PaymentController::class, 'handleAccountNotification']);
});

/*
|--------------------------------------------------------------------------
| Protected API Routes (Authentication Required)
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Authentication Routes (Protected)
    |--------------------------------------------------------------------------
    */
    Route::post('/logout', [AuthController::class, 'logout']);

    // Google OAuth Management (for authenticated users)
    Route::prefix('google')->name('google.')->group(function () {
        Route::post('/link', [GoogleOAuthController::class, 'linkGoogleAccount'])->name('link');
        Route::delete('/unlink', [GoogleOAuthController::class, 'unlinkGoogleAccount'])->name('unlink');
        Route::get('/status', function (Request $request) {
            $user = $request->user();
            return response()->json([
                'status' => 'success',
                'data' => [
                    'is_google_linked' => $user->hasGoogleAccount(),
                    'is_google_user' => $user->isGoogleUser(),
                    'google_id' => $user->google_id,
                    'avatar' => $user->avatar
                ]
            ]);
        })->name('status');
    });

    /*
    |--------------------------------------------------------------------------
    | Package Management
    |--------------------------------------------------------------------------
    */
    Route::apiResource('packages', PackageController::class)->except(['index', 'show']);
    Route::get('/invitation/{invitationId}/package', [PackageController::class, 'getPackageByInvitationId']);

    /*
    |--------------------------------------------------------------------------
    | Theme Management
    |--------------------------------------------------------------------------
    */
    Route::apiResource('themes', ThemeController::class)->except(['index', 'show']);
    Route::post('/themes/orderthemes', [ThemeController::class, 'getThemeByOrderId']);
    Route::get('/invitation/{invitationId}/theme', [ThemeController::class, 'getThemeByInvitationId']);

    /*
    |--------------------------------------------------------------------------
    | Theme Category Management
    |--------------------------------------------------------------------------
    */
    Route::apiResource('categories', ThemeCategoryController::class)->except(['index', 'show']);

    /*
    |--------------------------------------------------------------------------
    | Music Management
    |--------------------------------------------------------------------------
    */
    Route::apiResource('musics', MusicController::class)->except(['index', 'show']);

    /*
    |--------------------------------------------------------------------------
    | Guest Management
    |--------------------------------------------------------------------------
    */
    Route::apiResource('guests', GuestController::class)->except(['index', 'show']);

    /*
    |--------------------------------------------------------------------------
    | Payment & Order Management
    |--------------------------------------------------------------------------
    */
    Route::prefix('payments')->group(function () {
        Route::post('/create', [PaymentController::class, 'createPayment']);
        Route::put('/update', [PaymentController::class, 'updatePayment']);
        Route::get('/orders', [OrderController::class, 'getUserOrders']);
        Route::get('/orders/{orderId}', [OrderController::class, 'getOrderStatus']);
        // Route::get('/orders/{orderId}/update', [OrderController::class, 'updatePaymentStatus']);
        Route::put('/orders/{orderId}/success', [PaymentController::class, 'handlePaymentSuccess']);
        Route::put('/orders/{orderId}/cancel', [PaymentController::class, 'handlePaymentCanceled']);
    });

    // Orders
    Route::get('/orders', [OrderController::class, 'getOrders']);
    Route::get('/orders/{orderId}', [OrderController::class, 'show']);
    Route::get('/order/{order_id}', [OrderController::class, 'getOrder']);

    /*
    |--------------------------------------------------------------------------
    | Invitation Management
    |--------------------------------------------------------------------------
    */
    Route::prefix('invitations')->group(function () {
        // Route::post('/', [InvitationController::class, 'store']);
        Route::get('/user', [InvitationController::class, 'showUserInvitations']);
        // Route::post('/check', [InvitationController::class, 'checkByOrderId']);
        // Route::get('/{id}', [InvitationController::class, 'show']);
        // Route::put('/{id}', [InvitationController::class, 'update']);
        // Route::put('/{id}/complete', [InvitationController::class, 'completeInvitation']);
    });
});

// Fallback route for undefined API endpoints
Route::fallback(function () {
    return response()->json([
        'status' => 'error',
        'message' => 'API endpoint not found',
        'available_endpoints' => [
            'auth' => [
                'POST /api/auth/register',
                'POST /api/auth/login',
                'POST /api/auth/logout',
                'GET /api/auth/me'
            ],
            'google_oauth' => [
                'GET /api/auth/google/redirect',
                'GET /api/auth/google/callback',
                'POST /api/auth/google/login',
                'POST /api/auth/google/link',
                'DELETE /api/auth/google/unlink',
                'GET /api/auth/google/status'
            ],
            'user' => [
                'GET /api/user/profile',
                'PUT /api/user/profile',
                'PUT /api/user/password',
                'POST /api/user/password/set'
            ]
        ]
    ], 404);
});