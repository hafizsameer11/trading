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
use App\Http\Controllers\Admin\AdminPairController;
use App\Http\Controllers\Admin\ControlController;
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
    Route::get('/admin/overview', [AdminOverviewController::class, 'index']);
    Route::get('/admin/pairs', [AdminPairController::class, 'index']);
    Route::post('/admin/pairs', [AdminPairController::class, 'store']);
    Route::put('/admin/pairs/{pair}', [AdminPairController::class, 'update']);
    Route::get('/admin/controls', [ControlController::class, 'show']);
    Route::put('/admin/controls', [ControlController::class, 'update']);
});
