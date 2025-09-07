<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Pair;
use App\Services\OtcPriceService;
use App\Services\CandleAggregationService;
use Illuminate\Support\Facades\Cache;

class MarketRun extends Command
{
    protected $signature = 'market:run {--base=1} {--pairs=*}';
    protected $description = 'Generate live market data with proper time progression and no duplicates';

    private $timeframes = [5, 10, 15, 30, 60, 120, 300, 600, 900, 1800, 3600, 7200, 14400];
    private $isRunning = true;
    private $otcPriceService;
    private $candleAggregationService;

    public function __construct()
    {
        parent::__construct();
        $this->otcPriceService = new OtcPriceService();
        $this->candleAggregationService = new CandleAggregationService();
    }

    public function handle()
    {
        $this->info('🚀 Starting Professional Market Data Generator...');
        
        // Handle graceful shutdown
        pcntl_signal(SIGTERM, [$this, 'handleShutdown']);
        pcntl_signal(SIGINT, [$this, 'handleShutdown']);
        
        $this->initializeSystem();
        $this->startMainLoop();
    }

    private function initializeSystem()
    {
        $this->info('Initializing market data system...');
        
        // Get active pairs
        $pairs = Pair::where('is_active', true)->get();
        $this->info("Found {$pairs->count()} active pairs");
        
        // Initialize spot prices for all pairs
        foreach ($pairs as $pair) {
            $spot = $this->otcPriceService->getOrSeedSpot($pair);
            $this->info("Initialized {$pair->symbol} at {$spot}");
        }
    }

    private function startMainLoop()
    {
        $this->info('Starting main generation loop...');
        $tickCount = 0;
        $lastStatsTime = time();
        
        while ($this->isRunning) {
            $startTime = microtime(true);
            $currentTimestamp = time();
            
            // Process all active pairs
            $pairs = Pair::where('is_active', true)->get();
            
            foreach ($pairs as $pair) {
                $this->processPair($pair, $currentTimestamp);
            }
            
            $tickCount++;
            
            // Show stats every 60 seconds
            if ($currentTimestamp - $lastStatsTime >= 60) {
                $this->showStats($tickCount, $currentTimestamp);
                $lastStatsTime = $currentTimestamp;
            }
            
            // Sleep for 1 second
            $elapsed = microtime(true) - $startTime;
            $sleepTime = max(0, 1.0 - $elapsed);
            
            if ($sleepTime > 0) {
                usleep($sleepTime * 1000000);
            }
            
            // Handle signals
            pcntl_signal_dispatch();
        }
        
        $this->info('Market data generator stopped.');
    }

    private function processPair(Pair $pair, int $timestamp)
    {
        try {
            // Get current spot price
            $currentSpot = $this->otcPriceService->getOrSeedSpot($pair);
            
            // Generate price step with trend and cap
            $step = $this->otcPriceService->gbmStepWithTrendAndCap($pair, $currentSpot, $timestamp);
            
            // Calculate new price
            $newPrice = $currentSpot + $step;
            
            // Apply win-rate nudging
            $finalPrice = $this->otcPriceService->gentlyNudgeForOpenTrades($pair, $newPrice, $timestamp);
            
            // Round to pair precision
            $finalPrice = round($finalPrice, $pair->price_precision);
            
            // Update spot price in Cache
            $spotKey = "spot:{$pair->id}";
            Cache::put($spotKey, $finalPrice, 86400);
            
            // Aggregate tick into all timeframes
            $this->candleAggregationService->aggregateTickIntoAllBuckets($pair, $finalPrice, $timestamp);
            
        } catch (\Exception $e) {
            $this->error("Error processing pair {$pair->symbol}: " . $e->getMessage());
        }
    }

    private function showStats(int $tickCount, int $timestamp)
    {
        $pairs = Pair::where('is_active', true)->get();
        $this->info("=== Market Stats (Tick #{$tickCount}) ===");
        $this->info("Time: " . date('Y-m-d H:i:s', $timestamp));
        
        foreach ($pairs as $pair) {
            $currentPrice = $this->candleAggregationService->getCurrentPrice($pair);
            if ($currentPrice) {
                $this->info("{$pair->symbol}: {$currentPrice}");
            }
        }
        
        // Show Cache info
        $this->info("Cache Driver: " . config('cache.default'));
    }

    public function handleShutdown()
    {
        $this->info('Received shutdown signal, stopping gracefully...');
        $this->isRunning = false;
    }
}