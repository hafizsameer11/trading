<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Pair;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class MarketDataController extends Controller
{
    /**
     * Get bulk candles for all timeframes
     */
    public function getBulkCandles(Request $request): JsonResponse
    {
        $request->validate([
            'pair_id' => 'required|exists:pairs,id',
            'timeframes' => 'required|string',
            'from' => 'nullable|date',
            'to' => 'nullable|date',
            'limit' => 'nullable|integer|min:1|max:1000'
        ]);

        $pairId = $request->pair_id;
        $timeframes = explode(',', $request->timeframes);
        $from = $request->from ? Carbon::parse($request->from) : now()->subDays(7);
        $to = $request->to ? Carbon::parse($request->to) : now();
        $limit = $request->limit ?? 500;

        $candles = [];

        foreach ($timeframes as $timeframe) {
            $timeframeCandles = DB::table('candles')
                ->where('pair_id', $pairId)
                ->where('timeframe', trim($timeframe))
                ->whereBetween('timestamp', [$from, $to])
                ->orderBy('timestamp', 'desc')
                ->limit($limit)
                ->get()
                ->map(function ($candle) {
                    return [
                        'time' => Carbon::parse($candle->timestamp)->timestamp,
                        'open' => (float) $candle->open,
                        'high' => (float) $candle->high,
                        'low' => (float) $candle->low,
                        'close' => (float) $candle->close,
                        'volume' => (float) $candle->volume,
                    ];
                })
                ->reverse()
                ->values();

            $candles[trim($timeframe)] = $timeframeCandles;
        }

        return response()->json([
            'success' => true,
            'data' => $candles,
            'pair_id' => $pairId,
            'from' => $from->toISOString(),
            'to' => $to->toISOString()
        ]);
    }

    /**
     * Get latest candles for real-time updates
     */
    public function getLatestCandles(Request $request): JsonResponse
    {
        $request->validate([
            'pair_id' => 'required|exists:pairs,id',
            'timeframes' => 'required|string'
        ]);

        $pairId = $request->pair_id;
        $timeframes = explode(',', $request->timeframes);
        $candles = [];

        foreach ($timeframes as $timeframe) {
            $timeframe = trim($timeframe);
            
            // Get latest candle from database
            $latestCandle = DB::table('candles')
                ->where('pair_id', $pairId)
                ->where('timeframe', $timeframe)
                ->orderBy('timestamp', 'desc')
                ->first();

            if ($latestCandle) {
                $candles[$timeframe] = [
                    'time' => Carbon::parse($latestCandle->timestamp)->timestamp,
                    'open' => (float) $latestCandle->open,
                    'high' => (float) $latestCandle->high,
                    'low' => (float) $latestCandle->low,
                    'close' => (float) $latestCandle->close,
                    'volume' => (float) $latestCandle->volume,
                ];
            }
        }

        return response()->json([
            'success' => true,
            'data' => $candles,
            'pair_id' => $pairId,
            'timestamp' => now()->toISOString()
        ]);
    }

    /**
     * Get current price for a pair
     */
    public function getCurrentPrice(Request $request): JsonResponse
    {
        $request->validate([
            'pair_id' => 'required|exists:pairs,id'
        ]);

        $pairId = $request->pair_id;
        
        // Get current price from latest candle
        $latestCandle = DB::table('candles')
            ->where('pair_id', $pairId)
            ->orderBy('timestamp', 'desc')
            ->first();
        
        $price = $latestCandle ? (float) $latestCandle->close : 0;

        return response()->json([
            'success' => true,
            'data' => [
                'pair_id' => $pairId,
                'price' => $price,
                'timestamp' => now()->toISOString()
            ]
        ]);
    }

    /**
     * Get market data for all active pairs
     */
    public function getAllPairsData(): JsonResponse
    {
        $pairs = Pair::where('is_active', true)->get();
        $marketData = [];

        foreach ($pairs as $pair) {
            // Get current price from latest candle
            $latestCandle = DB::table('candles')
                ->where('pair_id', $pair->id)
                ->orderBy('timestamp', 'desc')
                ->first();
            
            $price = $latestCandle ? (float) $latestCandle->close : $pair->min_price;

            $marketData[] = [
                'id' => $pair->id,
                'symbol' => $pair->symbol,
                'slug' => $pair->slug,
                'type' => $pair->type,
                'current_price' => $price,
                'min_price' => (float) $pair->min_price,
                'max_price' => (float) $pair->max_price,
                'price_precision' => $pair->price_precision,
                'is_active' => $pair->is_active,
            ];
        }

        return response()->json([
            'success' => true,
            'data' => $marketData,
            'timestamp' => now()->toISOString()
        ]);
    }

    /**
     * Get system status and controls
     */
    public function getSystemStatus(): JsonResponse
    {
        $systemControls = \App\Models\SystemControl::instance();
        
        return response()->json([
            'success' => true,
            'data' => [
                'system_controls' => [
                    'daily_win_percent' => $systemControls->daily_win_percent,
                    'otc_tick_ms' => $systemControls->otc_tick_ms,
                    'morning_trend' => $systemControls->morning_trend,
                    'afternoon_trend' => $systemControls->afternoon_trend,
                    'evening_trend' => $systemControls->evening_trend,
                    'trend_strength' => $systemControls->trend_strength,
                ],
                'current_session' => $this->getCurrentSession($systemControls),
                'generator_status' => $this->getGeneratorStatus(),
                'timestamp' => now()->toISOString()
            ]
        ]);
    }

    private function getCurrentSession($systemControls)
    {
        $currentTime = now();
        $currentTimeStr = $currentTime->format('H:i:s');
        
        if ($currentTimeStr >= $systemControls->morning_start && $currentTimeStr < $systemControls->morning_end) {
            return [
                'name' => 'morning',
                'trend' => $systemControls->morning_trend,
                'start' => $systemControls->morning_start,
                'end' => $systemControls->morning_end
            ];
        } elseif ($currentTimeStr >= $systemControls->afternoon_start && $currentTimeStr < $systemControls->afternoon_end) {
            return [
                'name' => 'afternoon',
                'trend' => $systemControls->afternoon_trend,
                'start' => $systemControls->afternoon_start,
                'end' => $systemControls->afternoon_end
            ];
        } elseif ($currentTimeStr >= $systemControls->evening_start && $currentTimeStr < $systemControls->evening_end) {
            return [
                'name' => 'evening',
                'trend' => $systemControls->evening_trend,
                'start' => $systemControls->evening_start,
                'end' => $systemControls->evening_end
            ];
        }
        
        return [
            'name' => 'morning',
            'trend' => $systemControls->morning_trend,
            'start' => $systemControls->morning_start,
            'end' => $systemControls->morning_end
        ];
    }

    private function getGeneratorStatus()
    {
        // Check if generator is running by looking for recent candle data
        $hasRecentData = false;
        $lastUpdate = null;
        
        $recentCandle = DB::table('candles')
            ->orderBy('timestamp', 'desc')
            ->first();
        
        if ($recentCandle) {
            $lastUpdateTime = Carbon::parse($recentCandle->timestamp);
            $hasRecentData = $lastUpdateTime->isAfter(now()->subMinutes(2)); // Data within last 2 minutes
            $lastUpdate = $lastUpdateTime->toISOString();
        }
        
        return [
            'is_running' => $hasRecentData,
            'last_update' => $lastUpdate
        ];
    }
}