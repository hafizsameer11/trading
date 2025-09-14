<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Pair;
use App\Services\CandleAggregationService;
use Illuminate\Support\Facades\DB;

class CandleController extends Controller
{
    private $candleAggregationService;

    public function __construct()
    {
        $this->candleAggregationService = new CandleAggregationService();
    }

    public function getCandles(Request $request)
    {
        $request->validate([
            'pair' => 'required|string',
            'tf' => 'integer',
            'timeframe' => 'integer',
            'limit' => 'integer|min:1|max:1000'
        ]);

        $pairSymbol = $request->input('pair');
        $timeframe = $request->input('tf') ?? $request->input('timeframe');
        $limit = $request->input('limit', 500);
        
        if (!$timeframe) {
            return response()->json(['error' => 'Timeframe (tf or timeframe) is required'], 400);
        }

        // Convert UI symbol to backend format
        $backendSymbol = $this->convertUiToBackend($pairSymbol);
        
        // Find pair
        $pair = Pair::where('symbol', $backendSymbol)->first();
        if (!$pair) {
            return response()->json(['error' => 'Pair not found'], 404);
        }

        // Ensure that candles are being generated for the requested timeframe
        $this->ensureTimeframeActive($pair, $timeframe);

        // Get candles from Cache first
        $candles = $this->candleAggregationService->getCandlesFromCache($pair, $timeframe, $limit);
        
        // If not enough candles in Cache, backfill from database
        if (count($candles) < $limit) {
            $dbCandles = $this->getCandlesFromDatabase($pair, $timeframe, $limit - count($candles));
            $candles = array_merge($dbCandles, $candles);
        }

        // Sort by timestamp ascending
        usort($candles, function($a, $b) {
            return $a['time'] <=> $b['time'];
        });

        // Validate timestamps are in seconds
        foreach ($candles as $candle) {
            if ($candle['time'] > 1e12) {
                $this->logError("ERROR[CLOCK]: Received millisecond timestamp; expected seconds. Normalizing.");
                $candle['time'] = intval($candle['time'] / 1000);
            }
        }

        return response()->json($candles);
    }

    public function getCurrentPrice(Request $request)
    {
        $request->validate([
            'pair' => 'required|string'
        ]);

        $pairSymbol = $request->input('pair');
        $backendSymbol = $this->convertUiToBackend($pairSymbol);
        
        $pair = Pair::where('symbol', $backendSymbol)->first();
        if (!$pair) {
            return response()->json(['error' => 'Pair not found'], 404);
        }

        $currentPrice = $this->candleAggregationService->getCurrentPrice($pair);
        $timestamp = time();

        return response()->json([
            'time' => $timestamp,
            'price' => $currentPrice
        ]);
    }

    public function streamCandles(Request $request)
    {
        $request->validate([
            'pair' => 'required|string',
            'tf' => 'required|integer'
        ]);

        $pairSymbol = $request->input('pair');
        $timeframe = $request->input('tf');
        $backendSymbol = $this->convertUiToBackend($pairSymbol);
        
        $pair = Pair::where('symbol', $backendSymbol)->first();
        if (!$pair) {
            return response()->json(['error' => 'Pair not found'], 404);
        }

        // Set SSE headers
        return response()->stream(function() use ($pair, $timeframe) {
            $lastCandleTime = 0;
            
            while (true) {
                // Get latest candle from Cache
                $candles = $this->candleAggregationService->getCandlesFromCache($pair, $timeframe, 1);
                
                if (!empty($candles)) {
                    $latestCandle = $candles[0];
                    
                    if ($latestCandle['time'] > $lastCandleTime) {
                        echo "event: candle\n";
                        echo "data: " . json_encode([
                            'tf' => $timeframe,
                            'candle' => $latestCandle
                        ]) . "\n\n";
                        
                        $lastCandleTime = $latestCandle['time'];
                    }
                }
                
                // Get current price
                $currentPrice = $this->candleAggregationService->getCurrentPrice($pair);
                if ($currentPrice) {
                    echo "event: tick\n";
                    echo "data: " . json_encode([
                        'time' => time(),
                        'price' => $currentPrice
                    ]) . "\n\n";
                }
                
                ob_flush();
                flush();
                
                sleep(1);
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
        ]);
    }

    private function getCandlesFromDatabase(Pair $pair, int $timeframe, int $limit): array
    {
        $candles = DB::table('candles')
            ->where('pair_id', $pair->id)
            ->where('timeframe_sec', $timeframe)
            ->orderBy('timestamp', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($candle) {
                return [
                    'time' => $candle->timestamp,
                    'open' => (float) $candle->open,
                    'high' => (float) $candle->high,
                    'low' => (float) $candle->low,
                    'close' => (float) $candle->close,
                    'volume' => (float) $candle->volume
                ];
            })
            ->reverse()
            ->values()
            ->toArray();

        return $candles;
    }

    private function convertUiToBackend(string $uiSymbol): string
    {
        // Convert "XAU-USD-OTC" to "XAU/USD"
        $parts = explode('-', $uiSymbol);
        if (count($parts) >= 2) {
            $base = $parts[0];
            $quote = $parts[1];
            return "{$base}/{$quote}";
        }
        return $uiSymbol;
    }

    private function convertBackendToUi(string $backendSymbol): string
    {
        // Convert "GBP/USD OTC" to "GBP-USD-OTC"
        if (strpos($backendSymbol, ' ') !== false) {
            list($symbol, $type) = explode(' ', $backendSymbol, 2);
            $symbol = str_replace('/', '-', $symbol);
            return "{$symbol}-{$type}";
        }
        return str_replace('/', '-', $backendSymbol);
    }

    private function logError(string $message)
    {
        \Log::error($message);
    }

    // Ensure that candles are being generated for the requested timeframe
    private function ensureTimeframeActive(Pair $pair, int $timeframe): void
    {
        // Get current price and trigger candle generation for this specific timeframe
        $otcService = app(\App\Services\OtcPriceService::class);
        $currentPrice = $otcService->getOrSeedSpot($pair);
        
        // Generate a tick for this specific timeframe
        $this->candleAggregationService->aggregateTickForSpecificTimeframe($pair, $currentPrice, time(), $timeframe);
    }
}
