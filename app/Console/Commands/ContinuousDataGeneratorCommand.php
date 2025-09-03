<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Pair;
use App\Services\OtcPriceService;
use Carbon\Carbon;

class ContinuousDataGeneratorCommand extends Command
{
    protected $signature = 'market:continuous {--interval=30} {--timeframe=60}';
    protected $description = 'Generate continuous real-time market data';

    protected OtcPriceService $otcService;

    public function __construct(OtcPriceService $otcService)
    {
        parent::__construct();
        $this->otcService = $otcService;
    }

    public function handle()
    {
        $interval = (int) $this->option('interval'); // seconds
        $timeframe = (int) $this->option('timeframe');
        
        $this->info("🚀 Starting CONTINUOUS market data generation...");
        $this->info("⏱️  Interval: {$interval} seconds");
        $this->info("📊 Timeframe: {$timeframe} seconds");
        $this->info("🔄 Press Ctrl+C to stop");
        $this->newLine();
        
        $pairs = Pair::all();
        $this->info("📈 Generating data for {$pairs->count()} pairs...");
        
        $counter = 0;
        
        while (true) {
            $counter++;
            $now = Carbon::now();
            
            $this->info("🔄 Tick #{$counter} at {$now->format('H:i:s')}");
            
            foreach ($pairs as $pair) {
                try {
                    // Generate new tick
                    $candle = $this->otcService->nextTick($pair);
                    
                    // Save to database
                    $this->otcService->addCandle($pair, $timeframe, $candle);
                    
                    $this->line("  ✅ {$pair->symbol}: {$candle['o']} → {$candle['c']}");
                } catch (\Exception $e) {
                    $this->error("  ❌ {$pair->symbol}: " . $e->getMessage());
                }
            }
            
            $this->info("⏳ Waiting {$interval} seconds for next tick...");
            $this->newLine();
            
            sleep($interval);
        }
    }
}

