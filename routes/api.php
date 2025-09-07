<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\TradeController;
use App\Http\Controllers\CandleController;
use App\Http\Controllers\PairController;
use App\Http\Controllers\DepositController;
use App\Http\Controllers\WithdrawalController;
use App\Http\Controllers\Admin\AdminOverviewController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\AdminDepositController;
use App\Http\Controllers\Admin\AdminWithdrawalController;
use App\Http\Controllers\Admin\AdminPairController;
use App\Http\Controllers\Admin\ControlController;
use App\Http\Controllers\Admin\AdminNotificationController;
use App\Http\Controllers\Admin\ReportsController;
use App\Http\Controllers\Admin\PaymentMethodController as AdminPaymentMethodController;
use App\Http\Controllers\Admin\AdminLogsController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PaymentMethodController;
use App\Http\Controllers\MarketDataController;
use Illuminate\Support\Facades\Hash;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/
Route::get('/optimize-app', function () {
    Artisan::call('optimize:clear'); // Clears cache, config, route, and view caches
    Artisan::call('cache:clear');    // Clears application cache
    Artisan::call('config:clear');   // Clears configuration cache
    Artisan::call('route:clear');    // Clears route cache
    Artisan::call('view:clear');     // Clears compiled Blade views
    Artisan::call('config:cache');   // Rebuilds configuration cache
    Artisan::call('route:cache');    // Rebuilds route cache
    Artisan::call('view:cache');     // Precompiles Blade templates
    Artisan::call('optimize');       // Optimizes class loading

    return "Application optimized and caches cleared successfully!";
});
Route::get('/migrate', function () {
    Artisan::call('migrate');
    return response()->json(['message' => 'Migration successful'], 200);
});
Route::get('/migrate/rollback', function () {
    Artisan::call('migrate:rollback');
    return response()->json(['message' => 'Migration rollback successfully'], 200);
});

// Public routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);
Route::post('/resend-otp', [AuthController::class, 'resendOtp']);

// Email configuration check (for debugging)
Route::get('/email/check', function () {
    $emailService = app(\App\Services\EmailConfigService::class);
    return response()->json([
        'status' => $emailService->getEmailStatus(),
        'configured' => $emailService->isEmailConfigured(),
    ]);
});

Route::get('/pairs', [PairController::class, 'index']);
Route::get('/candles/current-price', [CandleController::class, 'getCurrentPrice']);
Route::get('/candles', [CandleController::class, 'getCandles']);
Route::get('/candles/next', [CandleController::class, 'getNextCandle']);

// Payment methods
Route::get('/payment-methods', [PaymentMethodController::class, 'index']);
Route::get('/payment-methods/{paymentMethod}', [PaymentMethodController::class, 'show']);
Route::post('/payment-methods/{paymentMethod}/calculate-fee', [PaymentMethodController::class, 'calculateFee']);

// Market data
Route::get('/market-data/bulk-candles', [MarketDataController::class, 'getBulkCandles']);
Route::get('/market-data/latest-candles', [MarketDataController::class, 'getLatestCandles']);
Route::get('/market-data/current-price', [MarketDataController::class, 'getCurrentPrice']);
Route::get('/market-data/all-pairs', [MarketDataController::class, 'getAllPairsData']);
Route::get('/market-data/system-status', [MarketDataController::class, 'getSystemStatus']);

// User notifications
Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/all', [NotificationController::class, 'all']);
    Route::get('/notifications/count', [NotificationController::class, 'count']);
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markAsRead']);
    Route::post('/notifications/mark-all-read', [NotificationController::class, 'markAllAsRead']);
});

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    
    // User profile
    Route::get('/me', function (Request $request) {
        $user = $request->user();
        return response()->json([
            'user' => $user,
            'balances' => [
                'demo' => $user->demo_balance,
                'live' => $user->live_balance,
            ],
        ]);
    });
    
    Route::put('/me', function (Request $request) {
        $user = $request->user();
        $user->update($request->only(['name', 'phone']));
        return response()->json(['message' => 'Profile updated successfully', 'user' => $user]);
    });
    
    Route::put('/me/password', function (Request $request) {
        $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:6|confirmed',
        ]);
        
        $user = $request->user();
        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json(['error' => 'Current password is incorrect'], 400);
        }
        
        $user->update(['password' => Hash::make($request->new_password)]);
        return response()->json(['message' => 'Password updated successfully']);
    });
    
    Route::put('/me/notifications', function (Request $request) {
        // Update notification preferences
        return response()->json(['message' => 'Notification preferences updated']);
    });
    
    // Trading
    Route::get('/trades', [TradeController::class, 'index']);
    Route::post('/trades', [TradeController::class, 'store']);
    Route::get('/trades/{trade}', [TradeController::class, 'show']);
    Route::post('/trades/{trade}/settle', [TradeController::class, 'settle']);
    
    // Deposits and withdrawals
    Route::get('/deposits', [DepositController::class, 'index']);
    Route::post('/deposits', [DepositController::class, 'store']);
    
    Route::get('/withdrawals', [WithdrawalController::class, 'index']);
    Route::post('/withdrawals', [WithdrawalController::class, 'store']);
});

// Admin routes
Route::middleware(['auth:sanctum', 'admin'])->group(function () {
    // Overview
    Route::get('/admin/overview', [AdminOverviewController::class, 'index']);
    
    // Users
    Route::get('/admin/users', [AdminUserController::class, 'index']);
    Route::get('/admin/users/{user}', [AdminUserController::class, 'show']);
    Route::get('/admin/users/{user}/trades', [AdminUserController::class, 'getTrades']);
    Route::put('/admin/users/{user}/balance', [AdminUserController::class, 'updateBalance']);
    Route::put('/admin/users/{user}/admin', [AdminUserController::class, 'toggleAdmin']);
    
    // Deposits
    Route::get('/admin/deposits', [AdminDepositController::class, 'index']);
    Route::get('/admin/deposits/{id}', [AdminDepositController::class, 'show']);
    Route::post('/admin/deposits/{id}/approve', [AdminDepositController::class, 'approve']);
    Route::post('/admin/deposits/{id}/reject', [AdminDepositController::class, 'reject']);
    Route::put('/admin/deposits/{id}', [AdminDepositController::class, 'update']);
    
    // Withdrawals
    Route::get('/admin/withdrawals', [AdminWithdrawalController::class, 'index']);
    Route::get('/admin/withdrawals/{id}', [AdminWithdrawalController::class, 'show']);
    Route::post('/admin/withdrawals/{id}/approve', [AdminWithdrawalController::class, 'approve']);
    Route::post('/admin/withdrawals/{id}/reject', [AdminWithdrawalController::class, 'reject']);
    Route::put('/admin/withdrawals/{id}', [AdminWithdrawalController::class, 'update']);
    
    // Pairs
    Route::get('/admin/pairs', [AdminPairController::class, 'index']);
    Route::post('/admin/pairs', [AdminPairController::class, 'store']);
    Route::put('/admin/pairs/{id}', [AdminPairController::class, 'update']);
    
    // Controls
    Route::get('/admin/controls', [ControlController::class, 'show']);
    Route::put('/admin/controls', [ControlController::class, 'update']);
    
    // Notifications
    Route::get('/admin/notifications', [AdminNotificationController::class, 'index']);
    Route::post('/admin/notifications', [AdminNotificationController::class, 'store']);
    Route::get('/admin/notifications/{id}', [AdminNotificationController::class, 'show']);
    Route::put('/admin/notifications/{id}', [AdminNotificationController::class, 'update']);
    Route::delete('/admin/notifications/{id}', [AdminNotificationController::class, 'destroy']);
    Route::get('/admin/notifications/stats/overview', [AdminNotificationController::class, 'stats']);
    Route::get('/admin/notifications/users/search', [AdminNotificationController::class, 'getUsers']);
    
    // Reports
    Route::get('/admin/reports/analytics', [ReportsController::class, 'analytics']);
    Route::get('/admin/reports/export', [ReportsController::class, 'export']);
    
    // Payment Methods
    Route::get('/admin/payment-methods', [AdminPaymentMethodController::class, 'index']);
    Route::post('/admin/payment-methods', [AdminPaymentMethodController::class, 'store']);
    Route::get('/admin/payment-methods/{id}', [AdminPaymentMethodController::class, 'show']);
    Route::put('/admin/payment-methods/{id}', [AdminPaymentMethodController::class, 'update']);
    Route::delete('/admin/payment-methods/{id}', [AdminPaymentMethodController::class, 'destroy']);
    Route::post('/admin/payment-methods/{id}/toggle-status', [AdminPaymentMethodController::class, 'toggleStatus']);
    
    // System Logs
    Route::get('/admin/logs', [AdminLogsController::class, 'index']);
    Route::get('/admin/logs/stats', [AdminLogsController::class, 'stats']);
    Route::get('/admin/logs/filters', [AdminLogsController::class, 'filters']);
    Route::get('/admin/logs/export', [AdminLogsController::class, 'export']);
});

// New Candle API Routes (no auth required for market data)
Route::get('/candles', [CandleController::class, 'getCandles']);
Route::get('/price', [CandleController::class, 'getCurrentPrice']);
Route::get('/stream/candles', [CandleController::class, 'streamCandles']);
