<?php

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\DB;
use App\Models\Pair;
use App\Models\SystemControl;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "🚀 Testing Market Data System\n";
echo "============================\n\n";

// Test 1: Check pairs
echo "1. Testing Pairs:\n";
$pairs = Pair::where('is_active', true)->get();
echo "   Found {$pairs->count()} active pairs\n";
foreach ($pairs as $pair) {
    echo "   - {$pair->symbol}: {$pair->min_price} - {$pair->max_price}\n";
}
echo "\n";

// Test 2: Check system controls
echo "2. Testing System Controls:\n";
$controls = SystemControl::instance();
echo "   Daily Win %: {$controls->daily_win_percent}\n";
echo "   Morning Trend: {$controls->morning_trend}\n";
echo "   Afternoon Trend: {$controls->afternoon_trend}\n";
echo "   Evening Trend: {$controls->evening_trend}\n";
echo "   Trend Strength: {$controls->trend_strength}\n";
echo "\n";

// Test 3: Check candles table
echo "3. Testing Candles Table:\n";
$candleCount = DB::table('candles')->count();
echo "   Total candles: {$candleCount}\n";

if ($candleCount > 0) {
    $latestCandle = DB::table('candles')->orderBy('timestamp', 'desc')->first();
    echo "   Latest candle: Pair {$latestCandle->pair_id}, {$latestCandle->timeframe}, Close: {$latestCandle->close}\n";
    
    $timeframes = DB::table('candles')->distinct()->pluck('timeframe');
    echo "   Available timeframes: " . implode(', ', $timeframes->toArray()) . "\n";
}
echo "\n";

// Test 4: Test API endpoints
echo "4. Testing API Endpoints:\n";
$baseUrl = 'http://127.0.0.1:8000/api';

$endpoints = [
    '/market-data/all-pairs',
    '/market-data/current-price?pair_id=1',
    '/market-data/system-status',
    '/market-data/latest-candles?pair_id=1&timeframes=1m,5m',
    '/market-data/bulk-candles?pair_id=1&timeframes=1m&limit=5'
];

foreach ($endpoints as $endpoint) {
    $url = $baseUrl . $endpoint;
    $response = @file_get_contents($url);
    
    if ($response) {
        $data = json_decode($response, true);
        if ($data && isset($data['success']) && $data['success']) {
            echo "   ✅ {$endpoint} - OK\n";
        } else {
            echo "   ❌ {$endpoint} - Failed\n";
        }
    } else {
        echo "   ❌ {$endpoint} - No response\n";
    }
}
echo "\n";

// Test 5: Check generator status
echo "5. Testing Generator Status:\n";
$recentCandle = DB::table('candles')
    ->orderBy('timestamp', 'desc')
    ->first();

if ($recentCandle) {
    $lastUpdate = \Carbon\Carbon::parse($recentCandle->timestamp);
    $isRecent = $lastUpdate->isAfter(now()->subMinutes(2));
    
    echo "   Last update: {$lastUpdate->toDateTimeString()}\n";
    echo "   Is recent (within 2 min): " . ($isRecent ? 'YES' : 'NO') . "\n";
    echo "   Generator status: " . ($isRecent ? 'RUNNING' : 'STOPPED') . "\n";
} else {
    echo "   No candles found - Generator not running\n";
}
echo "\n";

echo "🎉 Market Data System Test Complete!\n";
echo "====================================\n";
echo "\nTo start the generator, run:\n";
echo "php artisan market:generate-live\n";
echo "\nTo stop the generator, press Ctrl+C\n";

