<?php

namespace App\Services;

use App\Models\Trade;
use App\Models\SystemControl;
use App\Models\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TradeEngine
{
    public function __construct(
        private OtcPriceService $otcService
    ) {}

    public function settle(Trade $trade): void
    {
        DB::transaction(function () use ($trade) {
            // Get close price at expiry
            $closePrice = $this->getClosePriceAtExpiry($trade);
            
            // Determine result
            $result = $this->determineResult($trade, $closePrice);
            
            // Apply bias for demo OTC trades
            if ($trade->account_type === 'DEMO' && $trade->pair->type === 'OTC') {
                $result = $this->applyBias($result);
            }
            
            // Update trade
            $trade->update([
                'result' => $result,
                'settled_at' => now(),
            ]);
            
            // Update user balance
            $this->updateUserBalance($trade);
            
            // Create notification
            $this->createNotification($trade);
            
            Log::info("Trade {$trade->id} settled: {$result}", [
                'trade_id' => $trade->id,
                'result' => $result,
                'entry_price' => $trade->entry_price,
                'close_price' => $closePrice,
                'direction' => $trade->direction,
            ]);
        });
    }

    private function getClosePriceAtExpiry(Trade $trade): float
    {
        if ($trade->pair->type === 'LIVE') {
            // For LIVE pairs, we would fetch from market provider
            // For now, return a simulated price
            return $trade->entry_price * (1 + (mt_rand(-50, 50) / 10000));
        }
        
        // For OTC pairs, get the last candle close
        $candles = $this->otcService->getCandles($trade->pair, $trade->timeframe_sec, 1);
        return $candles[0]['c'] ?? $trade->entry_price;
    }

    private function determineResult(Trade $trade, float $closePrice): string
    {
        $priceChange = $closePrice - $trade->entry_price;
        $priceChangePercent = ($priceChange / $trade->entry_price) * 100;
        
        // Define minimum change for win/lose (0.1%)
        $minChange = 0.001;
        
        if (abs($priceChangePercent) < $minChange) {
            return 'TIE';
        }
        
        if ($trade->direction === 'UP') {
            return $priceChangePercent > 0 ? 'WIN' : 'LOSE';
        } else {
            return $priceChangePercent < 0 ? 'WIN' : 'LOSE';
        }
    }

    private function applyBias(string $originalResult): string
    {
        if ($originalResult === 'TIE') {
            return 'TIE'; // Don't bias ties
        }
        
        $controls = SystemControl::instance();
        $winPercent = $controls->daily_win_percent / 100;
        
        // If it's a LOSE and we want to bias towards WIN
        if ($originalResult === 'LOSE' && mt_rand(1, 100) <= ($winPercent * 100)) {
            return 'WIN';
        }
        
        // If it's a WIN and we want to bias towards LOSE
        if ($originalResult === 'WIN' && mt_rand(1, 100) <= ((1 - $winPercent) * 100)) {
            return 'LOSE';
        }
        
        return $originalResult;
    }

    private function updateUserBalance(Trade $trade): void
    {
        $balanceField = $trade->account_type === 'LIVE' ? 'live_balance' : 'demo_balance';
        
        if ($trade->result === 'WIN') {
            $payout = $trade->amount + ($trade->amount * $trade->payout_rate / 100);
            $trade->user->increment($balanceField, $payout);
        } elseif ($trade->result === 'LOSE') {
            // Amount is already deducted when trade was placed
            // No additional deduction needed
        }
        // TIE: no change to balance
    }

    private function createNotification(Trade $trade): void
    {
        $title = match ($trade->result) {
            'WIN' => 'Trade Won! 🎉',
            'LOSE' => 'Trade Lost',
            'TIE' => 'Trade Tied',
            default => 'Trade Settled',
        };
        
        $body = match ($trade->result) {
            'WIN' => "Your {$trade->pair_symbol} trade won! You earned $" . number_format($trade->payout, 2),
            'LOSE' => "Your {$trade->pair_symbol} trade lost. Amount: $" . number_format($trade->amount, 2),
            'TIE' => "Your {$trade->pair_symbol} trade tied. Amount returned: $" . number_format($trade->amount, 2),
            default => "Your {$trade->pair_symbol} trade has been settled.",
        };
        
        Notification::create([
            'user_id' => $trade->user_id,
            'type' => 'trade_settled',
            'title' => $title,
            'body' => $body,
            'meta' => [
                'trade_id' => $trade->id,
                'result' => $trade->result,
                'amount' => $trade->amount,
                'payout' => $trade->result === 'WIN' ? $trade->payout : 0,
            ],
        ]);
    }
}


