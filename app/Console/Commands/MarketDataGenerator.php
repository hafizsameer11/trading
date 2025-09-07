<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Pair;
use App\Models\SystemControl;
use App\Models\Trade;
use App\Services\LoggingService;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class MarketDataGenerator extends Command
{
    protected $signature = 'market:generate-live {--pair=all} {--stop}';
    protected $description = 'Generate live market data for all pairs with realistic price movements';

    private $timeframes = [
        '5s' => 5,
        '10s' => 10,
        '15s' => 15,
        '30s' => 30,
        '1m' => 60,
        '2m' => 120,
        '5m' => 300,
        '10m' => 600,
        '15m' => 900,
        '30m' => 1800,
        '1h' => 3600,
        '2h' => 7200,
        '4h' => 14400,
    ];

    private $currentPrices = [];
    private $candleData = [];
    private $isRunning = true;

    public function handle()
    {
        $this->info('🚀 Starting Live Market Data Generator...');
        
        if ($this->option('stop')) {
            $this->stopGenerator();
            return;
        }

        $this->initializeSystem();
        $this->startMainLoop();
    }

    private function initializeSystem()
    {
        $this->info('📊 Initializing market data system...');
        
        $pairs = Pair::where('is_active', true)->get();
        $this->info("Found {$pairs->count()} active pairs");

        foreach ($pairs as $pair) {
            $initialPrice = (float) $pair->min_price + ((float) $pair->max_price - (float) $pair->min_price) * 0.5;
            $this->currentPrices[$pair->id] = $initialPrice;
            $this->candleData[$pair->id] = [];
            
            foreach ($this->timeframes as $tf => $seconds) {
                $this->candleData[$pair->id][$tf] = [
                    'open' => $initialPrice,
                    'high' => $initialPrice,
                    'low' => $initialPrice,
                    'close' => $initialPrice,
                    'volume' => 1000.0,
                    'timestamp' => $this->getCurrentTimestamp($seconds),
                    'is_new' => true
                ];
            }
        }

        LoggingService::log('market_generator_started', 'Live market data generator started', null, null, null, 'info', [
            'pairs_count' => $pairs->count(),
            'timeframes' => array_keys($this->timeframes)
        ]);

        $this->info('✅ System initialized successfully');
    }

    private function startMainLoop()
    {
        $this->info('🔄 Starting main generation loop...');
        $this->info('Press Ctrl+C to stop gracefully');

        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGTERM, [$this, 'handleShutdown']);
            pcntl_signal(SIGINT, [$this, 'handleShutdown']);
        }

        $tickCount = 0;
        $lastStatsTime = time();

        while ($this->isRunning) {
            $startTime = microtime(true);
            
            try {
                $this->generatePriceTicks();
                $this->updateCandles();
                $this->saveCandlesToDatabase();
                // Cache update removed - using database only
                
                $tickCount++;
                
                if (time() - $lastStatsTime >= 60) {
                    $this->showStats($tickCount);
                    $lastStatsTime = time();
                }
                
                $executionTime = microtime(true) - $startTime;
                $sleepTime = max(0, 1.0 - $executionTime);
                
                if ($sleepTime > 0) {
                    usleep($sleepTime * 1000000);
                }
                
                if (function_exists('pcntl_signal_dispatch')) {
                    pcntl_signal_dispatch();
                }
                
            } catch (\Exception $e) {
                $this->error("❌ Error in main loop: " . $e->getMessage());
                LoggingService::logError("Market generator error: " . $e->getMessage(), [
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ]);
                sleep(5);
            }
        }

        $this->info('🛑 Market data generator stopped gracefully');
    }

    public function handleShutdown($signal)
    {
        $this->info('🛑 Received shutdown signal, stopping gracefully...');
        $this->isRunning = false;
        LoggingService::log('market_generator_stopped', 'Live market data generator stopped gracefully', null, null, null, 'info');
    }

    private function stopGenerator()
    {
        $this->info('🛑 Stopping market data generator...');
        $this->info('To stop the generator, use Ctrl+C or kill the process');
    }

    private function generatePriceTicks()
    {
        $systemControls = SystemControl::instance();
        $pairs = Pair::where('is_active', true)->get();
        
        foreach ($pairs as $pair) {
            $currentPrice = $this->currentPrices[$pair->id];
            
            $trendInfluence = $this->getTrendInfluence($systemControls);
            $winRateInfluence = $this->getWinRateInfluence($systemControls, $pair);
            
            $newPrice = $this->generateRealisticPriceMovement(
                $currentPrice,
                $pair,
                $trendInfluence,
                $winRateInfluence
            );
            
            $newPrice = max($pair->min_price, min($pair->max_price, $newPrice));
            $this->currentPrices[$pair->id] = $newPrice;
        }
    }

    private function generateRealisticPriceMovement($currentPrice, $pair, $trendInfluence, $winRateInfluence)
    {
        $baseVolatility = 0.001;
        $timeVolatility = $this->getTimeBasedVolatility();
        $pairVolatility = $this->getVolatilityMultiplier($pair->volatility ?? 'MID');
        $volatility = $baseVolatility * $timeVolatility * $pairVolatility;
        
        $randomFactor = $this->boxMullerTransform();
        $naturalMovement = $currentPrice * $volatility * $randomFactor;
        
        $trendMovement = $naturalMovement * $trendInfluence;
        $winRateMovement = $currentPrice * 0.0001 * $winRateInfluence;
        
        return $currentPrice + $naturalMovement + $trendMovement + $winRateMovement;
    }

    private function getTrendInfluence($systemControls)
    {
        $currentTime = now();
        $currentHour = $currentTime->hour;
        $currentMinute = $currentTime->minute;
        $currentTimeStr = sprintf('%02d:%02d:00', $currentHour, $currentMinute);
        
        $session = $this->getCurrentSession($currentTimeStr, $systemControls);
        $trend = $this->getSessionTrend($session, $systemControls);
        $strength = $systemControls->trend_strength / 10.0;
        
        $influence = 0;
        switch ($trend) {
            case 'UP':
                $influence = $strength * 0.3;
                break;
            case 'DOWN':
                $influence = -$strength * 0.3;
                break;
            case 'SIDEWAYS':
                $influence = 0;
                break;
        }
        
        return $influence;
    }

    private function getWinRateInfluence($systemControls, $pair)
    {
        $today = now()->startOfDay();
        $trades = Trade::where('pair_id', $pair->id)
            ->where('created_at', '>=', $today)
            ->whereIn('result', ['WIN', 'LOSE'])
            ->get();
        
        if ($trades->count() === 0) {
            return 0;
        }
        
        $winningTrades = $trades->where('result', 'WIN')->count();
        $currentWinRate = $winningTrades / $trades->count();
        $targetWinRate = $systemControls->daily_win_percent / 100;
        
        $influence = ($targetWinRate - $currentWinRate) * 2;
        return max(-0.5, min(0.5, $influence));
    }

    private function getCurrentSession($currentTime, $systemControls)
    {
        if ($currentTime >= $systemControls->morning_start && $currentTime < $systemControls->morning_end) {
            return 'morning';
        } elseif ($currentTime >= $systemControls->afternoon_start && $currentTime < $systemControls->afternoon_end) {
            return 'afternoon';
        } elseif ($currentTime >= $systemControls->evening_start && $currentTime < $systemControls->evening_end) {
            return 'evening';
        }
        
        return 'morning';
    }

    private function getSessionTrend($session, $systemControls)
    {
        switch ($session) {
            case 'morning':
                return $systemControls->morning_trend;
            case 'afternoon':
                return $systemControls->afternoon_trend;
            case 'evening':
                return $systemControls->evening_trend;
            default:
                return 'SIDEWAYS';
        }
    }

    private function getTimeBasedVolatility()
    {
        $hour = now()->hour;
        
        if ($hour >= 9 && $hour <= 17) {
            return 1.5;
        } elseif ($hour >= 6 && $hour <= 9) {
            return 1.2;
        } elseif ($hour >= 17 && $hour <= 21) {
            return 1.3;
        } else {
            return 0.5;
        }
    }

    private function boxMullerTransform()
    {
        $u1 = mt_rand() / mt_getrandmax();
        $u2 = mt_rand() / mt_getrandmax();
        return sqrt(-2 * log($u1)) * cos(2 * pi() * $u2);
    }

    private function getVolatilityMultiplier($volatility)
    {
        switch ($volatility) {
            case 'LOW':
                return 0.5;
            case 'MID':
                return 1.0;
            case 'HIGH':
                return 2.0;
            default:
                return 1.0;
        }
    }

    private function updateCandles()
    {
        foreach ($this->currentPrices as $pairId => $currentPrice) {
            foreach ($this->timeframes as $tf => $seconds) {
                $candle = &$this->candleData[$pairId][$tf];
                $candleTimestamp = $this->getCurrentTimestamp($seconds);
                
                if ($candleTimestamp > $candle['timestamp']) {
                    $this->saveCandleToDatabase($pairId, $tf, $candle);
                    
                    $candle = [
                        'open' => (float) $currentPrice,
                        'high' => (float) $currentPrice,
                        'low' => (float) $currentPrice,
                        'close' => (float) $currentPrice,
                        'volume' => (float) $this->generateVolume(),
                        'timestamp' => $candleTimestamp,
                        'is_new' => true
                    ];
                } else {
                    $candle['high'] = (float) max($candle['high'], $currentPrice);
                    $candle['low'] = (float) min($candle['low'], $currentPrice);
                    $candle['close'] = (float) $currentPrice;
                    $candle['volume'] = (float) ($candle['volume'] + $this->generateVolume());
                    $candle['is_new'] = false;
                }
            }
        }
    }

    private function generateVolume()
    {
        $baseVolume = 1000.0;
        $randomFactor = mt_rand(500, 2000) / 1000.0;
        return (float) ($baseVolume * $randomFactor);
    }

    private function getCurrentTimestamp($seconds)
    {
        $currentTime = now();
        $alignedTime = $currentTime->copy()->startOfMinute()->addSeconds(
            floor($currentTime->second / $seconds) * $seconds
        );
        
        return $alignedTime;
    }

    private function saveCandlesToDatabase()
    {
        $candlesToSave = [];
        
        foreach ($this->candleData as $pairId => $timeframes) {
            foreach ($timeframes as $tf => $candle) {
                if ($candle['is_new'] && $candle['timestamp'] < now()->subSeconds(5)) {
                    // Check if candle already exists to prevent duplicates
                    $exists = DB::table('candles')
                        ->where('pair_id', $pairId)
                        ->where('timeframe', $tf)
                        ->where('timestamp', $candle['timestamp'])
                        ->exists();
                    
                    if (!$exists) {
                        $candlesToSave[] = [
                            'pair_id' => $pairId,
                            'timeframe' => $tf,
                            'open' => $candle['open'],
                            'high' => $candle['high'],
                            'low' => $candle['low'],
                            'close' => $candle['close'],
                            'volume' => $candle['volume'],
                            'timestamp' => $candle['timestamp'],
                            'created_at' => now(),
                            'updated_at' => now()
                        ];
                    }
                }
            }
        }
        
        if (!empty($candlesToSave)) {
            DB::table('candles')->insert($candlesToSave);
        }
    }

    private function saveCandleToDatabase($pairId, $timeframe, $candle)
    {
        // Check if candle already exists to prevent duplicates
        $exists = DB::table('candles')
            ->where('pair_id', $pairId)
            ->where('timeframe', $timeframe)
            ->where('timestamp', $candle['timestamp'])
            ->exists();
        
        if (!$exists) {
            DB::table('candles')->insert([
                'pair_id' => $pairId,
                'timeframe' => $timeframe,
                'open' => $candle['open'],
                'high' => $candle['high'],
                'low' => $candle['low'],
                'close' => $candle['close'],
                'volume' => $candle['volume'],
                'timestamp' => $candle['timestamp'],
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }
    }

    // Redis cache removed - using database only

    private function showStats($tickCount)
    {
        $pairsCount = count($this->currentPrices);
        $memoryUsage = memory_get_usage(true) / 1024 / 1024;
        $this->info("📊 Stats - Ticks: {$tickCount}, Pairs: {$pairsCount}, Memory: {$memoryUsage}MB");
    }
}