<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Pair;
use App\Services\OtcPriceService;
use Carbon\Carbon;

class GenerateMarketDataCommand extends Command
{
    protected $signature = 'market:generate-data {--pairs=all} {--timeframe=60}';
    protected $description = 'Generate continuous market data for trading pairs';

    protected OtcPriceService $otcService;

    public function __construct(OtcPriceService $otcService)
    {
        parent::__construct();
        $this->otcService = $otcService;
    }

    public function handle()
    {
        $this->info('Starting market data generation...');
        
        $pairs = Pair::all();
        $timeframe = (int) $this->option('timeframe');
        
        $this->info("Generating data for {$pairs->count()} pairs with {$timeframe}s timeframe");
        
        $bar = $this->output->createProgressBar($pairs->count());
        $bar->start();
        
        foreach ($pairs as $pair) {
            try {
                // Generate a new tick
                $candle = $this->otcService->nextTick($pair);
                
                // Add to database
                $this->otcService->addCandle($pair, $timeframe, $candle);
                
                $bar->advance();
            } catch (\Exception $e) {
                $this->error("Failed to generate data for {$pair->symbol}: " . $e->getMessage());
            }
        }
        
        $bar->finish();
        $this->newLine();
        $this->info('Market data generation completed!');
        
        return 0;
    }
}
