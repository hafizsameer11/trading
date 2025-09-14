<?php

namespace App\Services;

use App\Models\Pair;
use App\Models\SystemControl;
use App\Models\Trade;
use Illuminate\Support\Facades\Cache;

class OtcPriceService
{
    public function getOrSeedSpot(Pair $pair): float
    {
        $spotKey = "spot:{$pair->id}";
        $currentPrice = Cache::get($spotKey);
        
        if ($currentPrice === null) {
            $anchorPrice = $pair->anchor_price ?? $this->getDefaultAnchorPrice($pair);
            Cache::put($spotKey, $anchorPrice, 86400);
            return $anchorPrice;
        }
        
        return (float) $currentPrice;
    }

    public function gbmStepWithTrendAndCap(Pair $pair, float $spot, int $timestamp): float
    {
        $baseVolatility = $pair->volatility_decimal ?? $this->getVolatilityFromEnum($pair->volatility);
        $timeVolatility = $this->getTimeBasedVolatility($timestamp);
        $volatility = $baseVolatility * $timeVolatility;
        
        $randomFactor = $this->boxMullerTransform();
        $naturalMovement = $spot * $volatility * $randomFactor;
        
        // Get market-wide trend from system controls
        $trendMovement = $this->getMarketTrendInfluence($spot, $timestamp);
        
        $totalStep = $naturalMovement + $trendMovement;
        
        $maxPctPerTick = $this->getMaxPctPerTick($pair);
        $maxStep = $spot * $maxPctPerTick;
        
        if (abs($totalStep) > $maxStep) {
            $totalStep = $totalStep > 0 ? $maxStep : -$maxStep;
        }
        
        return $totalStep;
    }

    public function gentlyNudgeForOpenTrades(Pair $pair, float $spot, int $timestamp): float
    {
        $systemControl = SystemControl::first();
        if (!$systemControl || $systemControl->daily_win_percent >= 50) {
            return $spot;
        }
        
        $expiryStart = $timestamp + 60;
        $expiryEnd = $timestamp + 120;
        
        $openTrades = Trade::where('pair_id', $pair->id)
            ->whereIn('result', ['PENDING'])
            ->whereBetween('expiry_at', [$expiryStart, $expiryEnd])
            ->get();
        
        if ($openTrades->isEmpty()) {
            return $spot;
        }
        
        $nudgeAmount = 0;
        $nudgeTime = 60;
        
        foreach ($openTrades as $trade) {
            $entryPrice = $trade->entry_price;
            $direction = $trade->direction;
            
            $wouldWin = ($direction === 'UP' && $spot > $entryPrice) || 
                       ($direction === 'DOWN' && $spot < $entryPrice);
            
            if ($wouldWin && $systemControl->daily_win_percent < 50) {
                $requiredNudge = $this->calculateRequiredNudge($spot, $entryPrice, $direction);
                $nudgeAmount += $requiredNudge / $nudgeTime;
            }
        }
        
        $maxPctPerTick = $this->getMaxPctPerTick($pair);
        $maxNudge = $spot * $maxPctPerTick;
        
        if (abs($nudgeAmount) > $maxNudge) {
            $nudgeAmount = $nudgeAmount > 0 ? $maxNudge : -$maxNudge;
        }
        
        return $spot + $nudgeAmount;
    }

    private function getDefaultAnchorPrice(Pair $pair): float
    {
        $defaults = [
            'XAU/USD' => 2000.00,
            'XAG/USD' => 25.00,
            'EUR/USD' => 1.1000,
            'GBP/USD' => 1.2500,
            'USD/JPY' => 150.00,
        ];
        
        return $defaults[$pair->symbol] ?? 1.0000;
    }

    private function getTimeBasedVolatility(int $timestamp): float
    {
        $hour = date('H', $timestamp);
        
        if ($hour >= 8 && $hour <= 16) {
            return 1.2;
        } elseif ($hour >= 0 && $hour <= 6) {
            return 0.8;
        }
        
        return 1.0;
    }

    private function getMarketTrendInfluence(float $spot, int $timestamp): float
    {
        $systemControl = SystemControl::first();
        if (!$systemControl) {
            return 0;
        }
        
        // Get current session trend from system controls
        $currentSession = $this->getCurrentSession($timestamp);
        $sessionTrend = $this->getSessionTrend($systemControl, $currentSession);
        $trendStrength = $systemControl->trend_strength ?? 0;
        
        if ($trendStrength === 0) {
            return 0;
        }
        
        $trendMultiplier = $trendStrength / 10.0;
        $baseInfluence = $spot * 0.0001;
        
        switch ($sessionTrend) {
            case 'UP':
                return $baseInfluence * $trendMultiplier;
            case 'DOWN':
                return -$baseInfluence * $trendMultiplier;
            case 'SIDEWAYS':
            default:
                return 0;
        }
    }

    private function getCurrentSession(int $timestamp): string
    {
        $hour = date('H', $timestamp);
        
        if ($hour >= 0 && $hour < 12) {
            return 'morning';
        } elseif ($hour >= 12 && $hour < 18) {
            return 'afternoon';
        } else {
            return 'evening';
        }
    }

    private function getSessionTrend(SystemControl $systemControl, string $session): string
    {
        switch ($session) {
            case 'morning':
                return $systemControl->morning_trend ?? 'SIDEWAYS';
            case 'afternoon':
                return $systemControl->afternoon_trend ?? 'SIDEWAYS';
            case 'evening':
                return $systemControl->evening_trend ?? 'SIDEWAYS';
            default:
                return 'SIDEWAYS';
        }
    }

    private function getMaxPctPerTick(Pair $pair): float
    {
        $symbol = $pair->symbol;
        
        if (strpos($symbol, 'XAU') !== false || strpos($symbol, 'XAG') !== false) {
            return 0.0008;
        } elseif (strpos($symbol, 'JPY') !== false) {
            return 0.001;
        } else {
            return 0.0005;
        }
    }

    private function calculateRequiredNudge(float $currentPrice, float $strikePrice, string $direction): float
    {
        $buffer = $currentPrice * 0.0001;
        
        if ($direction === 'UP') {
            return $strikePrice - $currentPrice - $buffer;
        } else {
            return $strikePrice - $currentPrice + $buffer;
        }
    }

    private function getVolatilityFromEnum(?string $volatility): float
    {
        switch ($volatility) {
            case 'LOW':
                return 0.05;
            case 'MID':
                return 0.08;
            case 'HIGH':
                return 0.12;
            default:
                return 0.08;
        }
    }

    private function boxMullerTransform(): float
    {
        static $spare = null;
        static $hasSpare = false;

        if ($hasSpare) {
            $hasSpare = false;
            return $spare;
        }

        $hasSpare = true;
        $u = mt_rand() / mt_getrandmax();
        $v = mt_rand() / mt_getrandmax();
        $mag = sqrt(-2 * log($u));
        $spare = $mag * sin(2 * M_PI * $v);
        return $mag * cos(2 * M_PI * $v);
    }
}
