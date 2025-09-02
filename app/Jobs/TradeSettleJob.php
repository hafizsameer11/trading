<?php

namespace App\Jobs;

use App\Models\Trade;
use App\Services\TradeEngine;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class TradeSettleJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private int $tradeId
    ) {}

    public function handle(TradeEngine $tradeEngine): void
    {
        $trade = Trade::find($this->tradeId);
        
        if (!$trade) {
            Log::warning("Trade {$this->tradeId} not found for settlement");
            return;
        }
        
        if ($trade->result !== 'PENDING') {
            Log::info("Trade {$this->tradeId} already settled");
            return;
        }
        
        $tradeEngine->settle($trade);
    }
}
