<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Pair;
use App\Services\OtcPriceService;
use Carbon\Carbon;

class RealTimeDataGeneratorCommand extends Command
{
    protected $signature = 'market:real-time {--duration=300} {--interval=5}';
    protected $description = 'Generate real-time market data continuously for live charts';

    protected OtcPriceService $otcService;
    protected bool $shouldStop = false;

    public function __construct(OtcPriceService $otcService)
    {
        parent::__construct();
        $this->otcService = $otcService;
    }

    public function handle()
    {
        $this->info('🚀 Starting REAL-TIME market data generation...');
        $this->info('Press Ctrl+C to stop');
        
        $duration = (int) $this->option('duration'); // Default 5 minutes
        $interval = (int) $this->option('interval'); // Default 5 seconds
        
        $pairs = Pair::take(10)->get(); // Focus on main pairs for performance
        $this->info("Generating data for {$pairs->count()} pairs every {$interval} seconds");
        
        $startTime = time();
        $tickCount = 0;
        
        while (!$this->shouldStop && (time() - $startTime) < $duration) {
            try {
                $tickCount++;
                $this->info("Tick #{$tickCount} - " . date('H:i:s'));
                
                foreach ($pairs as $pair) {
                    // Generate new tick
                    $candle = $this->otcService->nextTick($pair);
                    
                    // Save to database for 1-minute timeframe
                    $this->otcService->addCandle($pair, 60, $candle);
                    
                    // Also save for 5-second timeframe for real-time movement
                    $this->otcService->addCandle($pair, 5, $candle);
                }
                
                $this->info("✅ Generated data for {$pairs->count()} pairs");
                
                // Wait for next tick
                sleep($interval);
                
            } catch (\Exception $e) {
                $this->error("❌ Error: " . $e->getMessage());
                sleep(1);
            }
        }
        
        $this->info("🎯 Real-time data generation completed! Generated {$tickCount} ticks");
        return 0;
    }
    
    public function stop()
    {
        $this->shouldStop = true;
    }
}

