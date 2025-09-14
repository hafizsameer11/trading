<?php

namespace App\Http\Controllers;

use App\Services\LiveMarketService;
use App\Models\Pair;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class LiveMarketController extends Controller
{
    private $liveMarketService;

    public function __construct(LiveMarketService $liveMarketService)
    {
        $this->liveMarketService = $liveMarketService;
    }

    /**
     * Get real-time price for a live market pair
     */
    public function getRealTimePrice(Request $request): JsonResponse
    {
        $request->validate([
            'symbol' => 'required|string'
        ]);

        $price = $this->liveMarketService->getRealTimePrice($request->symbol);

        if ($price === null) {
            return response()->json([
                'error' => 'Failed to fetch live price'
            ], 500);
        }

        return response()->json([
            'symbol' => $request->symbol,
            'price' => $price,
            'timestamp' => time()
        ]);
    }

    /**
     * Get historical candles for a live market pair
     */
    public function getHistoricalCandles(Request $request): JsonResponse
    {
        $request->validate([
            'symbol' => 'required|string',
            'timeframe' => 'required|integer|in:5,10,15,30,60,120,300,600,900,1800,3600,7200',
            'limit' => 'integer|min:1|max:500'
        ]);

        $candles = $this->liveMarketService->getHistoricalCandles(
            $request->symbol,
            $request->timeframe,
            $request->limit ?? 100
        );

        return response()->json([
            'symbol' => $request->symbol,
            'timeframe' => $request->timeframe,
            'candles' => $candles,
            'count' => count($candles)
        ]);
    }

    /**
     * Get available live market pairs
     */
    public function getAvailablePairs(): JsonResponse
    {
        $pairs = $this->liveMarketService->getAvailablePairs();

        return response()->json([
            'pairs' => $pairs
        ]);
    }

    /**
     * Get live market data for chart (combines price and candles)
     */
    public function getChartData(Request $request): JsonResponse
    {
        $request->validate([
            'symbol' => 'required|string',
            'timeframe' => 'required|integer|in:5,10,15,30,60,120,300,600,900,1800,3600,7200',
            'limit' => 'integer|min:1|max:500'
        ]);

        $symbol = $request->symbol;
        $timeframe = $request->timeframe;
        $limit = $request->limit ?? 100;

        // Get both real-time price and historical candles
        $currentPrice = $this->liveMarketService->getRealTimePrice($symbol);
        $candles = $this->liveMarketService->getHistoricalCandles($symbol, $timeframe, $limit);

        return response()->json([
            'symbol' => $symbol,
            'timeframe' => $timeframe,
            'current_price' => $currentPrice,
            'candles' => $candles,
            'count' => count($candles),
            'timestamp' => time()
        ]);
    }

    /**
     * Get live market status
     */
    public function getMarketStatus(): JsonResponse
    {
        $pairs = $this->liveMarketService->getAvailablePairs();
        $status = [];

        foreach ($pairs as $symbol => $name) {
            $price = $this->liveMarketService->getRealTimePrice($symbol);
            $status[] = [
                'symbol' => $symbol,
                'name' => $name,
                'price' => $price,
                'status' => $price ? 'active' : 'inactive',
                'timestamp' => time()
            ];
        }

        return response()->json([
            'market_status' => $status,
            'timestamp' => time()
        ]);
    }
}
