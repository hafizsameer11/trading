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
            $candle = $this->otcService->nextTick($pair);
            
            // Add candle to cache for each timeframe
            $timeframes = [5, 10, 15, 30, 60, 120, 300, 900, 1800, 3600];
            
            foreach ($timeframes as $timeframe) {
                $this->otcService->addCandle($pair, $timeframe, [
                    't' => $candle['ts'],
                    'o' => $candle['open'],
                    'h' => $candle['high'],
                    'l' => $candle['low'],
                    'c' => $candle['close'],
                ]);
            }
        }

        $this->info("Updated {$activeOtcPairs->count()} OTC pairs");
    }
}
