<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CandleController;
use App\Http\Controllers\PairController;
use App\Http\Controllers\TradeController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\DepositController;
use App\Http\Controllers\WithdrawalController;
use App\Http\Controllers\Admin\AdminPairController;
use App\Http\Controllers\Admin\ControlController;
use App\Http\Controllers\Admin\AdminOverviewController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Hash;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Public routes
Route::get('/pairs', [PairController::class, 'index']);
Route::get('/candles', [CandleController::class, 'getCandles']);
Route::get('/candles/current-price', [CandleController::class, 'getCurrentPrice']);
Route::post('/candles/add', [CandleController::class, 'addCandle']);
Route::get('/candles/tick', [CandleController::class, 'generateTick']);
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



// Auth routes
Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    
    Route::get('/me', function (Request $request) {
        return response()->json([
            'user' => $request->user(),
            'balances' => [
                'demo' => $request->user()->demo_balance,
                'live' => $request->user()->live_balance,
            ],
            'unread_notifications' => $request->user()->notifications()->unread()->count(),
        ]);
    });
    
    // Profile update route
    Route::put('/me', function (Request $request) {
        $user = $request->user();
        
        $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $user->id,
            'phone' => 'sometimes|nullable|string|max:20',
        ]);
        
        $user->update($request->only(['name', 'email', 'phone']));
        
        return response()->json([
            'message' => 'Profile updated successfully',
            'user' => $user->fresh(),
        ]);
    });
    
    // Password change route
    Route::put('/me/password', function (Request $request) {
        $user = $request->user();
        
        $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
        ]);
        
        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json(['error' => 'Current password is incorrect'], 422);
        }
        
        $user->update([
            'password' => Hash::make($request->new_password)
        ]);
        
        return response()->json(['message' => 'Password updated successfully']);
    });
    
    // Notification settings update route
    Route::put('/me/notifications', function (Request $request) {
        $user = $request->user();
        
        $request->validate([
            'tradeResults' => 'sometimes|boolean',
            'depositApprovals' => 'sometimes|boolean',
            'withdrawalApprovals' => 'sometimes|boolean',
            'systemUpdates' => 'sometimes|boolean',
            'marketing' => 'sometimes|boolean',
        ]);
        
        // For now, just return success since we don't have a notifications table yet
        // You can implement this later by creating a user_preferences table
        return response()->json([
            'message' => 'Notification settings updated successfully',
            'settings' => $request->all()
        ]);
    });
    
    // Trade routes
    Route::get('/trades', [TradeController::class, 'index']);
    Route::post('/trades', [TradeController::class, 'store']);
    Route::get('/trades/{trade}', [TradeController::class, 'show']);
    
    // Deposit/Withdrawal routes
    Route::get('/deposits', [DepositController::class, 'index']);
    Route::post('/deposits', [DepositController::class, 'store']);
    Route::get('/withdrawals', [WithdrawalController::class, 'index']);
    Route::post('/withdrawals', [WithdrawalController::class, 'store']);
    
    // Notification routes
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::patch('/notifications/{notification}/read', [NotificationController::class, 'markAsRead']);
    Route::patch('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
});

// Admin routes
Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->group(function () {
    Route::get('/overview', [AdminOverviewController::class, 'index']);
    
    Route::get('/pairs', [AdminPairController::class, 'index']);
    Route::post('/pairs', [AdminPairController::class, 'store']);
    Route::put('/pairs/{pair}', [AdminPairController::class, 'update']);
    
    Route::get('/controls', [ControlController::class, 'show']);
    Route::put('/controls', [ControlController::class, 'update']);
});
