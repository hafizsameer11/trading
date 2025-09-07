<?php

namespace App\Services;

use App\Models\Pair;
use App\Models\Candle;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class CandleAggregationService
{
    private $timeframes = [
        5, 10, 15, 30, 60, 120, 300, 600, 900, 1800, 3600, 7200, 14400
    ];

    public function aggregateTickIntoAllBuckets(Pair $pair, float $price, int $timestamp): void
    {
        foreach ($this->timeframes as $tf) {
            $this->aggregateTickForTimeframe($pair, $price, $timestamp, $tf);
        }
    }

    private function aggregateTickForTimeframe(Pair $pair, float $price, int $timestamp, int $timeframe): void
    {
        $bucket = intdiv($timestamp, $timeframe) * $timeframe;
        $redisKey = "candle:{$pair->id}:{$timeframe}";
        
        // Get current in-progress candle from Cache
        $currentCandle = Cache::get($redisKey);
        
        if ($currentCandle) {
            $candle = json_decode($currentCandle, true);
            
            // Check if we need to finalize the previous candle
            if ($candle['timestamp'] < $bucket) {
                // Finalize previous candle
                $this->finalizeCandle($pair, $timeframe, $candle);
                
                // Start new candle with previous close price as open
                $candle = [
                    'timestamp' => $bucket,
                    'open' => $candle['close'], // Use previous close as new open
                    'high' => $candle['close'],
                    'low' => $candle['close'],
                    'close' => $candle['close'],
                    'volume' => 0
                ];
            } else {
                // Update current candle
                $candle['high'] = max($candle['high'], $price);
                $candle['low'] = min($candle['low'], $price);
                $candle['close'] = $price;
                $candle['volume'] += $this->generateVolume();
            }
        } else {
            // First tick for this timeframe
            $candle = [
                'timestamp' => $bucket,
                'open' => $price,
                'high' => $price,
                'low' => $price,
                'close' => $price,
                'volume' => $this->generateVolume()
            ];
        }
        
        // Save back to Cache
        Cache::put($redisKey, json_encode($candle), 3600);
    }

    private function finalizeCandle(Pair $pair, int $timeframe, array $candle): void
    {
        // Save to database using updateOrCreate to avoid duplicates
        Candle::updateOrCreate(
            [
                'pair_id' => $pair->id,
                'timeframe_sec' => $timeframe,
                'timestamp' => $candle['timestamp'],
            ],
            [
                'open' => round($candle['open'], $pair->price_precision),
                'high' => round($candle['high'], $pair->price_precision),
                'low' => round($candle['low'], $pair->price_precision),
                'close' => round($candle['close'], $pair->price_precision),
                'volume' => round($candle['volume'], 8),
                'created_at' => now(),
                'updated_at' => now()
            ]
        );

        // Add to Cache list (keep last 1000 candles)
        $listKey = "candles:{$pair->id}:{$timeframe}";
        $candles = Cache::get($listKey, []);
        array_unshift($candles, $candle);
        $candles = array_slice($candles, 0, 1000); // Keep only last 1000
        Cache::put($listKey, $candles, 86400); // Expire after 24 hours
    }

    private function generateVolume(): float
    {
        // Generate realistic volume based on normal distribution
        $baseVolume = 1000;
        $volatility = 0.3;
        $random = $this->boxMullerTransform();
        return max(0, $baseVolume * (1 + $volatility * $random));
    }

    private function boxMullerTransform(): float
    {
        static $spare = null;
        static $hasSpare = false;

        if ($hasSpare) {
            $hasSpare = false;
            return $spare;
        }

        $hasSpare = true;
        $u = mt_rand() / mt_getrandmax();
        $v = mt_rand() / mt_getrandmax();
        $mag = sqrt(-2 * log($u));
        $spare = $mag * sin(2 * M_PI * $v);
        return $mag * cos(2 * M_PI * $v);
    }

    public function getCandlesFromCache(Pair $pair, int $timeframe, int $limit = 500): array
    {
        $listKey = "candles:{$pair->id}:{$timeframe}";
        $candles = Cache::get($listKey, []);
        
        $result = [];
        foreach (array_slice($candles, 0, $limit) as $candle) {
            $result[] = [
                'time' => $candle['timestamp'],
                'open' => (float) $candle['open'],
                'high' => (float) $candle['high'],
                'low' => (float) $candle['low'],
                'close' => (float) $candle['close'],
                'volume' => (float) $candle['volume']
            ];
        }
        
        // Sort by timestamp ascending
        usort($result, function($a, $b) {
            return $a['time'] <=> $b['time'];
        });
        
        return $result;
    }

    public function getCurrentPrice(Pair $pair): ?float
    {
        $spotKey = "spot:{$pair->id}";
        $price = Cache::get($spotKey);
        return $price ? (float) $price : null;
    }
}
