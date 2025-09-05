<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Pair;
use App\Models\MarketData;
use App\Services\OtcPriceService;

class Generate24HourDataCommand extends Command
{
    protected $signature = 'data:generate-24h {--clear : Clear existing market data}';
    protected $description = 'Generate 24 hours of market data for all timeframes and pairs';

    public function handle()
    {
        $this->info('Starting 24-hour data generation...');

        // Clear existing data if requested
        if ($this->option('clear')) {
            $this->info('Clearing existing market data...');
            MarketData::truncate();
        }

        $otcService = app(OtcPriceService::class);
        
        // Get all active pairs
        $pairs = Pair::where('is_active', true)->get();
        $this->info("Found {$pairs->count()} active pairs");

        // Define all timeframes in seconds
        $timeframes = [
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
            '4h' => 14400
        ];

        $totalCandles = 0;

        foreach ($pairs as $pair) {
            $this->info("Generating data for {$pair->symbol}...");
            
            foreach ($timeframes as $timeframeName => $timeframeSeconds) {
                // Calculate how many candles we need for 24 hours
                $candlesNeeded = (24 * 60 * 60) / $timeframeSeconds;
                
                $this->info("  - {$timeframeName}: {$candlesNeeded} candles");
                
                // Generate backfill candles
                $candles = $otcService->generateBackfillCandles($pair, $timeframeSeconds, (int)$candlesNeeded);
                
                // Store in database
                foreach ($candles as $candle) {
                    MarketData::updateOrCreate(
                        [
                            'pair_id' => $pair->id,
                            'timeframe_sec' => $timeframeSeconds,
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
                
                $totalCandles += count($candles);
            }
        }

        $this->info("✅ Generated {$totalCandles} candles total");
        $this->info("✅ 24-hour data generation completed!");
    }
}
