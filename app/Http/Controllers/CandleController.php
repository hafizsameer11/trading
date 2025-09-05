<?php

namespace App\Http\Controllers;

use App\Models\Pair;
use App\Models\MarketData;
use App\Services\OtcPriceService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class CandleController extends Controller
{
    protected OtcPriceService $otcService;

    public function __construct(OtcPriceService $otcService)
    {
        $this->otcService = $otcService;
    }

    /**
     * Convert frontend pair format (GBP-USD-OTC) to backend format (GBP/USD OTC)
     */
    private function convertPairFormat(string $frontendSymbol): string
    {
        $backendSymbol = str_replace('-', '/', $frontendSymbol);
        $backendSymbol = str_replace('/OTC', ' OTC', $backendSymbol);
        return $backendSymbol;
    }

    public function getCurrentPrice(Request $request): JsonResponse
    {
        $pairSymbol = $request->query('pair');
        
        if (!$pairSymbol) {
            return response()->json(['error' => 'Pair symbol is required'], 400);
        }

        $backendSymbol = $this->convertPairFormat($pairSymbol);
        $pair = Pair::where('symbol', $backendSymbol)->first();
        
        if (!$pair) {
            return response()->json(['error' => 'Pair not found: ' . $backendSymbol], 404);
        }

        try {
            $price = $this->otcService->getOrSeedSpot($pair);
            
            return response()->json([
                'price' => $price,
                'timestamp' => time(),
                'trend' => $pair->trend_mode,
                'volatility' => $pair->volatility,
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to get price'], 500);
        }
    }

    public function getCandles(Request $request): JsonResponse
    {
        $pairSymbol = $request->query('pair');
        $timeframe = (int) $request->query('timeframe', 60);
        $limit = min((int) $request->query('limit', 200), 1000);

        if (!$pairSymbol) {
            return response()->json(['error' => 'Pair symbol is required'], 400);
        }

        $backendSymbol = $this->convertPairFormat($pairSymbol);
        $pair = Pair::where('symbol', $backendSymbol)->first();
        
        if (!$pair) {
            return response()->json(['error' => 'Pair not found: ' . $backendSymbol], 404);
        }

        try {
            // Get candles from database first
            $candles = MarketData::where('pair_id', $pair->id)
                ->where('timeframe_sec', $timeframe)
                ->orderBy('timestamp', 'desc')
                ->limit($limit)
                ->get()
                ->map(function ($candle) {
                    return [
                        't' => (int) $candle->timestamp,
                        'o' => (float) $candle->open,
                        'h' => (float) $candle->high,
                        'l' => (float) $candle->low,
                        'c' => (float) $candle->close,
                        'v' => (float) ($candle->volume ?? 1000),
                    ];
                })
                ->reverse()
                ->values();

            // If not enough data, generate backfill
            if ($candles->count() < $limit) {
                $backfillCandles = $this->otcService->generateBackfillCandles($pair, $timeframe, $limit);
                
                // Save backfill candles to database
                foreach ($backfillCandles as $candle) {
                    MarketData::updateOrCreate(
                        [
                            'pair_id' => $pair->id,
                            'timeframe_sec' => $timeframe,
                            'timestamp' => $candle['ts'],
                        ],
                        [
                            'pair_symbol' => $pair->symbol,
                            'open' => $candle['o'],
                            'high' => $candle['h'],
                            'low' => $candle['l'],
                            'close' => $candle['c'],
                            'volume' => $candle['v'] ?? 1000,
                        ]
                    );
                }
                
                // 🔧 Normalize: ts -> t for API response
                $candles = collect($backfillCandles)->map(function($c) {
                    return [
                        't' => (int)$c['ts'],
                        'o' => (float)$c['o'],
                        'h' => (float)$c['h'],
                        'l' => (float)$c['l'],
                        'c' => (float)$c['c'],
                        'v' => isset($c['v']) ? (float)$c['v'] : 1000,
                    ];
                });
            }

            return response()->json($candles);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to get candles: ' . $e->getMessage()], 500);
        }
    }

    public function addCandle(Request $request): JsonResponse
    {
        $request->validate([
            'pair' => 'required|string',
            'timeframe' => 'required|integer',
            'candle' => 'required|array',
            'candle.ts' => 'required|integer',
            'candle.o' => 'required|numeric',
            'candle.h' => 'required|numeric',
            'candle.l' => 'required|numeric',
            'candle.c' => 'required|numeric',
        ]);

        $backendSymbol = $this->convertPairFormat($request->pair);
        $pair = Pair::where('symbol', $backendSymbol)->first();
        
        if (!$pair) {
            return response()->json(['error' => 'Pair not found: ' . $backendSymbol], 404);
        }

        try {
            $this->otcService->addCandle($pair, $request->timeframe, $request->candle);
            
            return response()->json(['message' => 'Candle added successfully']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to add candle'], 500);
        }
    }

    public function getNextCandle(Request $request): JsonResponse
    {
        $pairSymbol = $request->query('pair');
        $timeframe = (int) $request->query('timeframe', 60);
        
        if (!$pairSymbol) {
            return response()->json(['error' => 'Pair symbol is required'], 400);
        }

        $backendSymbol = $this->convertPairFormat($pairSymbol);
        $pair = Pair::where('symbol', $backendSymbol)->first();
        
        if (!$pair) {
            return response()->json(['error' => 'Pair not found: ' . $backendSymbol], 404);
        }

        try {
            $now = time();
            $currentBucket = floor($now / $timeframe) * $timeframe;
            
            // Get the candle for the current time bucket
            $candle = MarketData::where('pair_id', $pair->id)
                ->where('timeframe_sec', $timeframe)
                ->where('timestamp', $currentBucket)
                ->first();
            
            if (!$candle) {
                // If no candle exists for this time bucket, return null
                return response()->json(['candle' => null, 'currentTime' => $now, 'bucketTime' => $currentBucket]);
            }
            
            return response()->json([
                'candle' => [
                    't' => (int) $candle->timestamp,
                    'o' => (float) $candle->open,
                    'h' => (float) $candle->high,
                    'l' => (float) $candle->low,
                    'c' => (float) $candle->close,
                    'v' => (float) ($candle->volume ?? 1000),
                ],
                'currentTime' => $now,
                'bucketTime' => $currentBucket
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to get next candle: ' . $e->getMessage()], 500);
        }
    }

    public function generateTick(Request $request): JsonResponse
    {
        $pairSymbol = $request->query('pair');
        
        if (!$pairSymbol) {
            return response()->json(['error' => 'Pair symbol is required'], 400);
        }

        $backendSymbol = $this->convertPairFormat($pairSymbol);
        $pair = Pair::where('symbol', $backendSymbol)->first();
        
        if (!$pair) {
            return response()->json(['error' => 'Pair not found: ' . $backendSymbol], 404);
        }

        try {
            $candle = $this->otcService->nextTick($pair);
            
            return response()->json([
                'message' => 'Tick generated successfully',
                'candle' => $candle,
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to generate tick'], 500);
        }
    }
}
