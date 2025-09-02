<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Trade;
use App\Services\TradeEngine;
use App\Services\OtcPriceService;
use Carbon\Carbon;

class ContinuousTradeSettlementCommand extends Command
{
    protected $signature = 'trades:continuous-settlement {--interval=30}';
    protected $description = 'Continuously settle expired trades';

    protected TradeEngine $tradeEngine;

    public function __construct(TradeEngine $tradeEngine)
    {
        parent::__construct();
        $this->tradeEngine = $tradeEngine;
    }

    public function handle()
    {
        $interval = (int) $this->option('interval'); // seconds
        
        $this->info("🚀 Starting CONTINUOUS trade settlement...");
        $this->info("⏱️  Check interval: {$interval} seconds");
        $this->info("🔄 Press Ctrl+C to stop");
        $this->newLine();
        
        $counter = 0;
        
        while (true) {
            $counter++;
            $now = Carbon::now();
            
            $this->info("🔄 Settlement check #{$counter} at {$now->format('H:i:s')}");
            
            try {
                $expiredTrades = Trade::where('result', 'PENDING')
                    ->where('expiry_at', '<=', now())
                    ->get();

                if ($expiredTrades->isEmpty()) {
                    $this->info("  ✅ No expired trades found");
                } else {
                    $this->info("  📊 Found {$expiredTrades->count()} expired trades to settle");
                    
                    $settledCount = 0;
                    foreach ($expiredTrades as $trade) {
                        try {
                            $this->line("  🔄 Settling trade ID: {$trade->id} ({$trade->pair_symbol} {$trade->direction})");
                            $this->tradeEngine->settle($trade);
                            $settledCount++;
                            $this->line("  ✅ Trade {$trade->id} settled: {$trade->result}");
                        } catch (\Exception $e) {
                            $this->error("  ❌ Failed to settle trade {$trade->id}: " . $e->getMessage());
                        }
                    }
                    
                    $this->info("  🎯 Settled {$settledCount} out of {$expiredTrades->count()} trades");
                }
            } catch (\Exception $e) {
                $this->error("  ❌ Settlement error: " . $e->getMessage());
            }
            
            $this->info("⏳ Waiting {$interval} seconds for next check...");
            $this->newLine();
            
            sleep($interval);
        }
    }
}
