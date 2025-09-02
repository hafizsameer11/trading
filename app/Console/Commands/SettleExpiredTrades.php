<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Trade;
use App\Services\TradeEngine;
use App\Services\OtcPriceService;
use Carbon\Carbon;

class SettleExpiredTrades extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'trades:settle-expired';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Settle all expired trades by determining win/loss based on current price';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting to settle expired trades...');
        
        // Get all expired trades that are still pending
        $expiredTrades = Trade::where('result', 'PENDING')
            ->where('expiry_at', '<=', now())
            ->get();
        
        if ($expiredTrades->isEmpty()) {
            $this->info('No expired trades found.');
            return;
        }
        
        $this->info("Found {$expiredTrades->count()} expired trades to settle.");
        
        $tradeEngine = new TradeEngine(app(OtcPriceService::class));
        $settledCount = 0;
        
        foreach ($expiredTrades as $trade) {
            try {
                $this->info("Settling trade ID: {$trade->id} ({$trade->pair_symbol} {$trade->direction})");
                
                // Settle the trade
                $tradeEngine->settle($trade);
                
                $settledCount++;
                $this->info("Trade {$trade->id} settled successfully. Result: {$trade->result}");
                
            } catch (\Exception $e) {
                $this->error("Failed to settle trade {$trade->id}: " . $e->getMessage());
            }
        }
        
        $this->info("Completed! Settled {$settledCount} out of {$expiredTrades->count()} trades.");
    }
}
