<?php

namespace App\Services;

use App\Models\Pair;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class OtcPriceService
{
    public function getOrSeedSpot(Pair $pair): float
    {
        $cacheKey = "otc:spot:{$pair->id}";
        
        // Check if we have a cached price
        $cachedPrice = Cache::get($cacheKey);
        
        if ($cachedPrice !== null) {
            // Generate very small, realistic price movements (like real forex)
            $maxChangePercent = 0.0001; // Max 0.01% change per call (very small)
            
            // Calculate a tiny random change within limits
            $randomChange = (mt_rand(-50, 50) / 1000000); // ±0.005% random change
            $newPrice = $cachedPrice * (1 + $randomChange);
            
            // Ensure the change is within very reasonable limits
            $maxChange = $cachedPrice * $maxChangePercent;
            $minPrice = $cachedPrice - $maxChange;
            $maxPrice = $cachedPrice + $maxChange;
            
            $newPrice = max($minPrice, min($maxPrice, $newPrice));
            
            // Clamp to min/max if set
            if ($pair->min_price && $newPrice < $pair->min_price) {
                $newPrice = $pair->min_price;
            }
            if ($pair->max_price && $newPrice > $pair->max_price) {
                $newPrice = $pair->max_price;
            }
            
            // Update cache with new price
            Cache::put($cacheKey, $newPrice, 30); // Cache for 30 seconds (more frequent updates)
            
            return round($newPrice, $pair->price_precision);
        }
        
        // First time, seed with anchor price
        $anchor = $pair->meta['anchor'] ?? 1.00000;
        Cache::put($cacheKey, $anchor, 60);
        return $anchor;
    }

    public function nextTick(Pair $pair): array
    {
        $currentSpot = $this->getOrSeedSpot($pair);
        
        // Calculate drift based on trend mode
        $drift = $this->calculateDrift($pair, $currentSpot);
        
        // Calculate volatility noise
        $volatility = $this->calculateVolatility($pair, $currentSpot);
        
        // Generate new price
        $newPrice = $currentSpot + $drift + $volatility;
        
        // Clamp to min/max if set
        if ($pair->min_price && $newPrice < $pair->min_price) {
            $newPrice = $pair->min_price;
        }
        if ($pair->max_price && $newPrice > $pair->max_price) {
            $newPrice = $pair->max_price;
        }
        
        // Update cache
        $cacheKey = "otc:spot:{$pair->id}";
        Cache::put($cacheKey, $newPrice, 3600);
        
        // Generate realistic OHLC data with proper proportions
        $open = $currentSpot;
        $close = $newPrice;
        
        // Calculate realistic high/low based on price movement
        $priceChange = abs($close - $open);
        $volatilityRange = $priceChange * 0.3; // 30% of price change as volatility
        
        if ($close > $open) {
            // Bullish candle
            $high = $close + $volatilityRange;
            $low = $open - ($volatilityRange * 0.5);
        } else {
            // Bearish candle
            $high = $open + ($volatilityRange * 0.5);
            $low = $close - $volatilityRange;
        }
        
        // Ensure high/low make sense
        $high = max($high, max($open, $close));
        $low = min($low, min($open, $close));
        
        return [
            'o' => round($open, $pair->price_precision),
            'h' => round($high, $pair->price_precision),
            'l' => round($low, $pair->price_precision),
            'c' => round($close, $pair->price_precision),
            'ts' => time(),
        ];
    }

    private function calculateDrift(Pair $pair, float $currentPrice): float
    {
        // Much more controlled drift
        $baseDriftRate = 0.00002; // 0.002% base drift (much smaller)
        
        // Add some market momentum but keep it small
        $momentum = $this->getMarketMomentum($pair);
        $driftRate = $baseDriftRate + ($momentum * 0.00001); // Reduced momentum influence
        
        return match ($pair->trend_mode) {
            'UP' => $currentPrice * $driftRate,
            'DOWN' => -$currentPrice * $driftRate,
            'SIDEWAYS' => $currentPrice * ($momentum * 0.000005), // Very small random drift
            default => $currentPrice * ($momentum * 0.000005),
        };
    }

    private function calculateVolatility(Pair $pair, float $currentPrice): float
    {
        // More realistic volatility based on market conditions
        $baseVolatility = match ($pair->volatility) {
            'LOW' => 0.00005,   // 0.005% - very stable
            'MID' => 0.00015,   // 0.015% - moderate
            'HIGH' => 0.0003,   // 0.03% - volatile
            default => 0.00015,
        };
        
        // Add time-based volatility (more volatile during certain hours)
        $hour = (int)date('H');
        $timeMultiplier = 1.0;
        if ($hour >= 8 && $hour <= 16) { // Market hours
            $timeMultiplier = 1.5; // More volatile during market hours
        }
        
        // Generate much smaller, controlled noise
        $noise = $this->controlledRandom() * $currentPrice * $baseVolatility * $timeMultiplier;
        
        return $noise;
    }
    
    private function getMarketMomentum(Pair $pair): float
    {
        // Simulate market momentum that changes over time
        $cacheKey = "momentum:{$pair->id}";
        $momentum = Cache::get($cacheKey, 0.0);
        
        // Gradually change momentum (mean reversion)
        $momentum += (mt_rand(-100, 100) / 10000); // Small random change
        $momentum *= 0.95; // Decay factor
        
        // Clamp momentum to reasonable range
        $momentum = max(-2.0, min(2.0, $momentum));
        
        Cache::put($cacheKey, $momentum, 300); // Cache for 5 minutes
        return $momentum;
    }
    
    private function controlledRandom(): float
    {
        // Generate much smaller, controlled random numbers
        $u1 = mt_rand() / mt_getrandmax();
        $u2 = mt_rand() / mt_getrandmax();
        
        // Box-Muller transform for normal distribution
        $normal = sqrt(-2 * log($u1)) * cos(2 * M_PI * $u2);
        
        // Clamp to reasonable range (no extreme movements)
        $normal = max(-1.5, min(1.5, $normal));
        
        return $normal;
    }
    
    private function realisticRandom(): float
    {
        // Generate more realistic random numbers with fat tails (like real markets)
        $u1 = mt_rand() / mt_getrandmax();
        $u2 = mt_rand() / mt_getrandmax();
        
        // Box-Muller transform with fat tail adjustment
        $normal = sqrt(-2 * log($u1)) * cos(2 * M_PI * $u2);
        
        // Add fat tails (more extreme movements occasionally)
        if (mt_rand(1, 100) <= 5) { // 5% chance of extreme movement
            $normal *= 2.5;
        }
        
        return $normal;
    }

    private function normalRandom(): float
    {
        // Box-Muller transform for normal distribution
        $u1 = mt_rand() / mt_getrandmax();
        $u2 = mt_rand() / mt_getrandmax();
        
        return sqrt(-2 * log($u1)) * cos(2 * M_PI * $u2);
    }

    public function getCandles(Pair $pair, int $timeframe, int $limit = 200): array
    {
        $cacheKey = "otc:candles:{$pair->id}:{$timeframe}";
        $candles = Cache::get($cacheKey, []);
        
        // Try to get from database first
        try {
            $dbCandles = \App\Models\MarketData::where('pair_id', $pair->id)
                ->where('timeframe_sec', $timeframe)
                ->orderBy('timestamp', 'desc')
                ->limit($limit)
                ->get()
                ->map(function ($candle) {
                    return [
                        't' => $candle->timestamp,
                        'o' => $candle->open,
                        'h' => $candle->high,
                        'l' => $candle->low,
                        'c' => $candle->close,
                        'volume' => $candle->volume,
                    ];
                })
                ->reverse()
                ->values()
                ->toArray();
            
            if (count($dbCandles) >= $limit) {
                // Use database data
                Cache::put($cacheKey, $dbCandles, 3600);
                return array_slice($dbCandles, -$limit);
            }
        } catch (\Exception $e) {
            \Log::error('Failed to fetch market data from DB: ' . $e->getMessage());
        }
        
        if (count($candles) < $limit) {
            // Generate backfill candles
            $candles = $this->generateBackfillCandles($pair, $timeframe, $limit);
            Cache::put($cacheKey, $candles, 3600);
        }
        
        return array_slice($candles, -$limit);
    }

    public function generateBackfillCandles(Pair $pair, int $timeframe, int $limit): array
    {
        $candles = [];
        $currentPrice = $this->getOrSeedSpot($pair);
        $now = time();
        $momentum = 0.0;
        
        for ($i = $limit - 1; $i >= 0; $i--) {
            $timestamp = $now - ($i * $timeframe);
            
            // Generate more realistic candle data with momentum
            $open = $currentPrice;
            
            // Calculate realistic price movement
            $baseChange = $this->calculateRealisticChange($pair, $currentPrice, $momentum);
            $close = $currentPrice + $baseChange;
            
            // Ensure price doesn't go negative
            $close = max($close, $currentPrice * 0.5);
            
            // Generate realistic high/low based on open/close
            $priceRange = abs($close - $open);
            $volatility = $this->calculateVolatility($pair, $currentPrice);
            
            $high = max($open, $close) + ($volatility * 0.3);
            $low = min($open, $close) - ($volatility * 0.3);
            
            // Ensure high/low make sense
            $high = max($high, max($open, $close));
            $low = min($low, min($open, $close));
            
            $candles[] = [
                'ts' => $timestamp,
                'o' => round($open, $pair->price_precision),
                'h' => round($high, $pair->price_precision),
                'l' => round($low, $pair->price_precision),
                'c' => round($close, $pair->price_precision),
            ];
            
            // Update momentum and price for next iteration
            $momentum = ($close - $open) / $open; // Price change percentage
            $momentum = max(-0.02, min(0.02, $momentum)); // Clamp to ±2%
            $currentPrice = $close;
        }
        
        return $candles;
    }
    
    private function calculateRealisticChange(Pair $pair, float $currentPrice, float $momentum): float
    {
        // Base change based on volatility
        $baseVolatility = match ($pair->volatility) {
            'LOW' => 0.0002,   // 0.02%
            'MID' => 0.0005,   // 0.05%
            'HIGH' => 0.001,   // 0.1%
            default => 0.0005,
        };
        
        // Add momentum influence
        $momentumInfluence = $momentum * 0.3;
        
        // Add random component
        $randomComponent = $this->realisticRandom() * $baseVolatility;
        
        // Calculate final change
        $change = $currentPrice * ($baseVolatility + $momentumInfluence + $randomComponent);
        
        // Clamp to reasonable range
        $maxChange = $currentPrice * 0.005; // Max 0.5% change per candle
        return max(-$maxChange, min($maxChange, $change));
    }

    public function addCandle(Pair $pair, int $timeframe, array $candle): void
    {
        $cacheKey = "otc:candles:{$pair->id}:{$timeframe}";
        $candles = Cache::get($cacheKey, []);
        
        $candles[] = $candle;
        
        // Keep only last 1000 candles
        if (count($candles) > 1000) {
            $candles = array_slice($candles, -1000);
        }
        
        Cache::put($cacheKey, $candles, 3600);
        
        // Also save to database for persistence
        try {
            // Handle both formats: ['o', 'h', 'l', 'c', 'ts'] and ['open', 'high', 'low', 'close', 'timestamp']
            $timestamp = $candle['ts'] ?? $candle['timestamp'] ?? time();
            $open = $candle['o'] ?? $candle['open'] ?? 0;
            $high = $candle['h'] ?? $candle['high'] ?? 0;
            $low = $candle['l'] ?? $candle['low'] ?? 0;
            $close = $candle['c'] ?? $candle['close'] ?? 0;
            $volume = $candle['v'] ?? $candle['volume'] ?? 1000;
            
            \App\Models\MarketData::updateOrCreate(
                [
                    'pair_id' => $pair->id,
                    'timeframe_sec' => $timeframe,
                    'timestamp' => $timestamp,
                ],
                [
                    'pair_symbol' => $pair->symbol,
                    'open' => $open,
                    'high' => $high,
                    'low' => $low,
                    'close' => $close,
                    'volume' => $volume,
                ]
            );
        } catch (\Exception $e) {
            // Log error but don't break the flow
            \Log::error('Failed to save market data: ' . $e->getMessage());
        }
    }
}

