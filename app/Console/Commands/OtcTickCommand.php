<?php

namespace App\Console\Commands;

use App\Models\Pair;
use App\Models\SystemControl;
use App\Services\OtcPriceService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class OtcTickCommand extends Command
{
    protected $signature = 'otc:tick';
    protected $description = 'Update OTC pair prices';

    public function __construct(
        private OtcPriceService $otcService
    ) {
        parent::__construct();
    }

    public function handle()
    {
        $controls = SystemControl::instance();
        $activeOtcPairs = Pair::active()->otc()->get();

        foreach ($activeOtcPairs as $pair) {
            // Generate a single tick
            $tick = $this->otcService->nextTick($pair);
            
            // All timeframes from single tick stream
            $timeframes = [5, 10, 15, 30, 60, 120, 300, 600, 900, 1800, 3600, 7200, 14400];
            
            foreach ($timeframes as $timeframe) {
                // Use the proper addCandle method that handles timeframe logic
                $this->otcService->addCandle($pair, $timeframe, [
                    'ts' => $tick['ts'],
                    'o' => $tick['o'],
                    'h' => $tick['h'],
                    'l' => $tick['l'],
                    'c' => $tick['c'],
                    'v' => 1000, // Default volume
                ]);
            }
        }

        $this->info("Updated {$activeOtcPairs->count()} OTC pairs");
    }
}
