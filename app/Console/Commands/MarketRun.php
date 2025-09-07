<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

use App\Models\Pair;
use App\Models\SystemControl;
use App\Models\Trade;
use App\Services\OtcPriceService;
use App\Services\CandleAggregationService;
use App\Services\LoggingService;

class MarketRun extends Command
{
    protected $signature = 'market:run {--base=1} {--pairs=*}';
    protected $description = 'Generate live market data with SystemControl, realistic motion, and win-rate enforcement';

    private bool $isRunning = true;

    private OtcPriceService $otcPriceService;
    private CandleAggregationService $candleAggregationService;

    // micro caches (seconds)
    private int $systemCtlTtl = 5;
    private int $winrateTtl   = 10;

    public function __construct()
    {
        parent::__construct();
        $this->otcPriceService = new OtcPriceService();
        $this->candleAggregationService = new CandleAggregationService();
    }

    public function handle()
    {
        $this->info('🚀 MarketRun starting (SystemControl + realistic downtrends + enforced win-rate)...');

        // single-run lock (avoid two workers racing)
        $lock = Cache::lock('market_run_lock', 5);
        if (!$lock->get()) {
            $this->warn('Another MarketRun appears to be active. Exiting.');
            return 1;
        }

        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGTERM, [$this, 'handleShutdown']);
            pcntl_signal(SIGINT,  [$this, 'handleShutdown']);
        }

        try {
            $this->initializeSystem();
            $this->startMainLoop();
        } finally {
            optional($lock)->release();
        }

        return 0;
    }

    private function initializeSystem(): void
    {
        $pairs = $this->activePairs();
        $this->info("Found {$pairs->count()} active pairs");

        foreach ($pairs as $pair) {
            $spot = $this->otcPriceService->getOrSeedSpot($pair);
            $this->info("Initialized {$pair->symbol} at {$spot}");
        }

        if (class_exists(LoggingService::class)) {
            LoggingService::log('market_run_started', 'MarketRun started', null, null, null, 'info', [
                'pairs_count' => $pairs->count(),
            ]);
        }
    }

    private function startMainLoop(): void
    {
        $tickCount     = 0;
        $lastStatsTime = time();

        while ($this->isRunning) {
            $loopStart = microtime(true);

            try {
                $system = $this->systemCtlSnapshot();
                $this->maybeLogTrend($this->trendSnapshot($system));

                $pairs  = $this->activePairs();
                $nowTs  = time();

                // sane bounds for tick interval
                $tickMs = (int) max(100, min(5000, $system['otc_tick_ms'] ?? 1000));

                foreach ($pairs as $pair) {
                    // 1) Build price (realistic step + soft win-rate + gentle nudge)
                    $finalPrice = $this->buildPriceForPair($pair, $nowTs, $system);

                    // 2) Enforce daily win-rate for trades expiring this tick
                    $finalPrice = $this->enforceWinRateAtExpiry($pair, $finalPrice, $nowTs, $tickMs, $system);

                    // 3) Persist spot & aggregate (you can pass wick hints inside service if supported)
                    Cache::put("spot:{$pair->id}", $finalPrice, 86400);
                    $this->candleAggregationService->aggregateTickIntoAllBuckets($pair, $finalPrice, $nowTs);
                }

                $tickCount++;

                if ((time() - $lastStatsTime) >= 60) {
                    $this->showStats($tickCount, time());
                    $lastStatsTime = time();
                }

                // sleep to honor otc_tick_ms
                $elapsed   = microtime(true) - $loopStart;
                $sleepSecs = max(0, ($tickMs / 1000.0) - $elapsed);
                if ($sleepSecs > 0) {
                    usleep((int)round($sleepSecs * 1_000_000));
                }

                if (function_exists('pcntl_signal_dispatch')) {
                    pcntl_signal_dispatch();
                }
            } catch (\Throwable $e) {
                $this->error("Loop error: {$e->getMessage()}");
                if (class_exists(LoggingService::class)) {
                    LoggingService::logError('MarketRun loop error', [
                        'message' => $e->getMessage(),
                        'file'    => $e->getFile(),
                        'line'    => $e->getLine(),
                    ]);
                }
                usleep(500_000); // backoff
            }
        }

        $this->info('🛑 MarketRun stopped.');
        if (class_exists(LoggingService::class)) {
            LoggingService::log('market_run_stopped', 'MarketRun stopped gracefully', null, null, null, 'info');
        }
    }

    /**
     * Build price with realistic step + soft win-rate + gentle open-trade nudge.
     */
    private function buildPriceForPair(Pair $pair, int $timestamp, array $system): float
    {
        $currentSpot = $this->otcPriceService->getOrSeedSpot($pair);

        // realistic step (impulse/pullback/pauses + EWMA vol + per-tick cap)
        $step = $this->realisticStep($pair, $currentSpot, $system);

        // soft daily win-rate influence (keeps drift toward target)
        $winRateInfluence = $this->computeWinRateInfluence($pair->id, $system); // [-0.5, 0.5]
        $softNudge        = $currentSpot * 0.0001 * $winRateInfluence;

        $newPrice  = $currentSpot + $step + $softNudge;

        // gentle per-open-trade nudge your service already applies
        $finalPrice = $this->otcPriceService->gentlyNudgeForOpenTrades($pair, $newPrice, $timestamp);

        // clamp precision & bounds
        $precision  = max(0, (int)$pair->price_precision);
        $finalPrice = round($finalPrice, $precision);
        if (!is_null($pair->min_price)) $finalPrice = max((float)$pair->min_price, $finalPrice);
        if (!is_null($pair->max_price)) $finalPrice = min((float)$pair->max_price, $finalPrice);

        return $finalPrice;
    }

    /**
     * HARD enforcement at expiry using your Trade schema:
     * - choose winners requiring the least move from current price
     * - compute feasible band; cap final nudge; persist settlements
     */
    private function enforceWinRateAtExpiry(Pair $pair, float $finalPrice, int $nowTs, int $tickMs, array $system): float
    {
        // window based on seconds; widen by 1s on lower bound to avoid boundary misses
        $windowEnd   = Carbon::createFromTimestamp($nowTs);
        $windowStart = (clone $windowEnd)->subMilliseconds($tickMs)->subSecond();

        $expiring = Trade::query()
            ->where('pair_id', $pair->id)
            ->where('result', 'PENDING')
            ->where('expiry_at', '>', $windowStart)
            ->where('expiry_at', '<=', $windowEnd)
            ->get(['id','direction','entry_price','expiry_at']);

        if ($expiring->isEmpty()) {
            return $finalPrice;
        }

        // Today's realized stats
        $todayStart = now()->startOfDay();
        $todayTrades = Trade::query()
            ->where('pair_id', $pair->id)
            ->where('created_at', '>=', $todayStart)
            ->whereIn('result', ['WIN','LOSE'])
            ->select('result')
            ->get();

        $winsSoFar  = $todayTrades->where('result','WIN')->count();
        $totalSoFar = $todayTrades->count();

        $targetFrac = max(0.0, min(1.0, ((float)($system['daily_win_percent'] ?? 50.0))/100.0));
        $neededWins = $this->winsNeededForBatch($winsSoFar, $totalSoFar, $expiring->count(), $targetFrac);

        // Winner selection by minimum move from pre-enforcement price (reduces cliffs)
        $preClose = $finalPrice;
        $scored = $expiring->map(function ($t) use ($preClose) {
            $entry = (float)$t->entry_price;
            // distance needed to flip to WIN from current preClose
            if ($t->direction === 'UP') {
                $need = max(0.0, ($entry - $preClose) + 1e-12); // need close > entry
            } else {
                $need = max(0.0, ($preClose - $entry) + 1e-12); // need close < entry
            }
            return ['trade' => $t, 'need' => $need];
        })->sortBy('need')->values();

        $assignWins = $scored->take($neededWins)->pluck('trade');
        $assignLose = $expiring->reject(fn($t) => $assignWins->contains('id', $t->id))->values();

        // Build constraints for a single closing price
        $precision = max(0, (int)$pair->price_precision);
        $epsilon   = pow(10, -$precision) * 0.9;

        $upWinEntries   = [];
        $downWinEntries = [];
        foreach ($assignWins as $t) {
            if ($t->direction === 'UP')   $upWinEntries[]   = (float)$t->entry_price;
            if ($t->direction === 'DOWN') $downWinEntries[] = (float)$t->entry_price;
        }

        $upLoseEntries   = [];
        $downLoseEntries = [];
        foreach ($assignLose as $t) {
            if ($t->direction === 'UP')   $upLoseEntries[]   = (float)$t->entry_price;
            if ($t->direction === 'DOWN') $downLoseEntries[] = (float)$t->entry_price;
        }

        // Feasible band math
        $hiWin  = !empty($downLoseEntries) ? max($downLoseEntries) + $epsilon : null; // keep DOWN losers as losers: close >
        $loWin  = !empty($upLoseEntries)   ? min($upLoseEntries)   - $epsilon : null; // keep UP losers as losers:   close <
        $hiNeed = !empty($upWinEntries)    ? max($upWinEntries)    + $epsilon : null; // enforce UP winners:          close >
        $loNeed = !empty($downWinEntries)  ? min($downWinEntries)  - $epsilon : null; // enforce DOWN winners:        close <

        $L = -INF;
        $R =  INF;
        foreach ([$hiWin, $hiNeed] as $v) { if ($v !== null) $L = max($L, $v); }
        foreach ([$loWin, $loNeed] as $v) { if ($v !== null) $R = min($R, $v); }

        // If infeasible, relax loser guards first, then satisfy winner side by majority
        if ($L > $R) {
            $L = max($hiNeed ?? -INF, -INF);
            $R = min($loNeed ??  INF,  INF);
            if ($L > $R) {
                // still infeasible: choose side with more winners
                $target = $preClose;
                if (count($upWinEntries) >= count($downWinEntries)) {
                    $target = $hiNeed ?? $target;
                } else {
                    $target = $loNeed ?? $target;
                }
            } else {
                $target = min(max($preClose, $L), $R);
            }
        } else {
            $target = min(max($preClose, $L), $R);
        }

        // Cap the final nudge (use EWMA true range as guide)
        $trKey    = "ewma_tr:{$pair->id}";
        $ewmaTR   = Cache::get($trKey, $preClose * 0.0015);
        $maxNudge = $ewmaTR * 0.8; // tight cap
        $target   = max($preClose - $maxNudge, min($preClose + $maxNudge, $target));

        // Clamp to pair bounds & precision
        if (!is_null($pair->min_price)) $target = max((float)$pair->min_price, $target);
        if (!is_null($pair->max_price)) $target = min((float)$pair->max_price, $target);
        $target = round($target, $precision);

        // Persist settlements (closing_price, result, settled_at)
        DB::transaction(function () use ($assignWins, $assignLose, $target) {
            $now = now();

            if ($assignWins->isNotEmpty()) {
                Trade::whereIn('id', $assignWins->pluck('id')->all())
                    ->update([
                        'result'        => 'WIN',
                        'closing_price' => $target,
                        'settled_at'    => $now,
                    ]);
            }
            if ($assignLose->isNotEmpty()) {
                Trade::whereIn('id', $assignLose->pluck('id')->all())
                    ->update([
                        'result'        => 'LOSE',
                        'closing_price' => $target,
                        'settled_at'    => $now,
                    ]);
            }
        });

        return $target;
    }

    /**
     * Needed wins for this batch to keep the day near target.
     */
    private function winsNeededForBatch(int $winsSoFar, int $totalSoFar, int $batchSize, float $target): int
    {
        $afterTotal = $totalSoFar + $batchSize;
        $targetWins = (int)round($afterTotal * $target);
        $need       = $targetWins - $winsSoFar;
        return max(0, min($batchSize, $need));
    }

    /**
     * Realistic step: impulse/pullback/pauses + EWMA vol + per-tick cap, trend-aware.
     */
    private function realisticStep(Pair $pair, float $spot, array $system): float
    {
        $precision = max(0, (int)$pair->price_precision);
    
        // 0) Trend sign & magnitude
        $trendInfluence = $this->computeTrendInfluence($system); // e.g. +0.3 for UP, -0.3 for DOWN
        $dir = $trendInfluence >= 0 ? +1 : -1;                  // +1 = UP impulse, -1 = DOWN impulse
        $mag = abs($trendInfluence);                            // 0..0.3
    
        // 1) Regime state (IMPULSE with trend, PULLBACK against, PAUSE)
        $regKey = "regime:{$pair->id}";
        $reg = Cache::get($regKey, ['state' => 'IMPULSE', 'ticks_left' => 0]);
    
        if ($reg['ticks_left'] <= 0) {
            // probabilities biased by trend magnitude
            $pImpulse  = 0.55 + $mag * 0.20;  // 55–61% as trend strengthens
            $pPullback = 0.25 - $mag * 0.10;  // fewer pullbacks when trend is strong
            $pImpulse  = min(0.85, max(0.30, $pImpulse));
            $pPullback = min(0.50, max(0.10, $pPullback));
    
            $r = mt_rand() / mt_getrandmax();
            if ($r < $pImpulse)            $reg['state'] = 'IMPULSE';
            elseif ($r < $pImpulse+$pPullback) $reg['state'] = 'PULLBACK';
            else                            $reg['state'] = 'PAUSE';
    
            $reg['ticks_left'] = ($reg['state'] === 'IMPULSE')   ? mt_rand(5, 20)
                               : (($reg['state'] === 'PULLBACK') ? mt_rand(3, 8)
                                                               : mt_rand(2, 6));
        } else {
            $reg['ticks_left']--;
        }
        Cache::put($regKey, $reg, 60);
    
        // 2) EWMA volatility / true-range
        $volKey = "ewma_vol:{$pair->id}";
        $trKey  = "ewma_tr:{$pair->id}";
        $ewma = Cache::get($volKey, 0.0);
        $tr   = Cache::get($trKey, $spot * 0.0015); // 15 bps fallback
        $alpha = 0.15;
        if ($ewma <= 0) $ewma = (float)($spot * 1e-5);
    
        // 3) Base sigma + directional drift scaled by trend strength
        $baseSigma = 0.0006;
        $sigma = $baseSigma * (0.5 + 0.5 * ($ewma / max(1e-12, $baseSigma * $spot)));
    
        $baseDrift = 0.0004 * $mag; // 0..4 bps per tick
        switch ($reg['state']) {
            case 'IMPULSE':
                $mu  =  $dir * $baseDrift * 1.6; // with trend
                $sig =  $sigma * 1.2;
                break;
            case 'PULLBACK':
                $mu  = -$dir * $baseDrift * 0.6; // against trend
                $sig =  $sigma * 0.9;
                break;
            default: // PAUSE
                $mu  =  $dir * $baseDrift * 0.4;
                $sig =  $sigma;
        }
    
        // 4) Gaussian shock
        $u1 = max(1e-12, mt_rand() / mt_getrandmax());
        $u2 = mt_rand() / mt_getrandmax();
        $z  = sqrt(-2 * log($u1)) * cos(2 * M_PI * $u2);
    
        // 5) Raw step
        $step = $spot * ($mu + $sig * $z);
    
        // 6) Per-tick cap (~1/10 TR or 15 bps)
        $cap  = max($spot * 0.0015, $tr * 0.1);
        $step = max(-$cap, min($cap, $step));
    
        // 7) Update EWMA trackers
        $newEwma = (1-$alpha) * $ewma + $alpha * abs($step);
        Cache::put($volKey, $newEwma, 3600);
        $newTr   = (1-$alpha) * $tr   + $alpha * max($spot * 1e-5, abs($step));
        Cache::put($trKey, $newTr, 3600);
    
        return round($step, $precision);
    }
    

    /**
     * Trend influence from SystemControl (UP/DOWN/SIDEWAYS with strength).
     * Returns a signed coefficient; negative means down bias.
     */
    private function computeTrendInfluence(array $system): float
    {
        $timeStr = now()->format('H:i:00');
        $session = $this->resolveSession($timeStr, $system);
        $trend   = match ($session) {
            'morning'   => $system['morning_trend']   ?? 'SIDEWAYS',
            'afternoon' => $system['afternoon_trend'] ?? 'SIDEWAYS',
            'evening'   => $system['evening_trend']   ?? 'SIDEWAYS',
            default     => 'SIDEWAYS',
        };

        $strength = (float)($system['trend_strength'] ?? 5.0) / 10.0; // 0..1
        return match ($trend) {
            'UP'   => +0.3 * $strength,
            'DOWN' => -0.3 * $strength,
            default => 0.0,
        };
    }

    private function resolveSession(string $timeStr, array $system): string
    {
        $mStart = $system['morning_start']   ?? '09:00:00';
        $mEnd   = $system['morning_end']     ?? '12:00:00';
        $aStart = $system['afternoon_start'] ?? '12:00:00';
        $aEnd   = $system['afternoon_end']   ?? '17:00:00';
        $eStart = $system['evening_start']   ?? '17:00:00';
        $eEnd   = $system['evening_end']     ?? '21:00:00';

        if ($timeStr >= $mStart && $timeStr < $mEnd) return 'morning';
        if ($timeStr >= $aStart && $timeStr < $aEnd) return 'afternoon';
        if ($timeStr >= $eStart && $timeStr < $eEnd) return 'evening';
        return 'morning';
    }

    private function computeWinRateInfluence(int $pairId, array $system): float
    {
        $cacheKey = "winrate:pair:{$pairId}";
        if (($cached = Cache::get($cacheKey)) !== null) return $cached;

        $today = now()->startOfDay();
        $trades = Trade::query()
            ->where('pair_id', $pairId)
            ->where('created_at', '>=', $today)
            ->whereIn('result', ['WIN','LOSE'])
            ->select('result')
            ->get();

        if ($trades->isEmpty()) {
            Cache::put($cacheKey, 0.0, $this->winrateTtl);
            return 0.0;
        }

        $wins   = $trades->where('result','WIN')->count();
        $curr   = $wins / max(1, $trades->count());
        $target = ((float)($system['daily_win_percent'] ?? 50.0)) / 100.0;

        $influence = max(-0.5, min(0.5, ($target - $curr) * 2.0)); // [-0.5, 0.5]
        Cache::put($cacheKey, $influence, $this->winrateTtl);
        return $influence;
    }

    private function systemCtlSnapshot(): array
    {
        return Cache::remember('system_control:snapshot', $this->systemCtlTtl, function () {
            $sc = SystemControl::instance();
            return [
                'daily_win_percent' => (float)$sc->daily_win_percent,
                'otc_tick_ms'       => (int)$sc->otc_tick_ms,
                'morning_trend'     => $sc->morning_trend,
                'afternoon_trend'   => $sc->afternoon_trend,
                'evening_trend'     => $sc->evening_trend,
                'morning_start'     => $sc->morning_start,
                'morning_end'       => $sc->morning_end,
                'afternoon_start'   => $sc->afternoon_start,
                'afternoon_end'     => $sc->afternoon_end,
                'evening_start'     => $sc->evening_start,
                'evening_end'       => $sc->evening_end,
                'trend_strength'    => (float)$sc->trend_strength,
            ];
        });
    }

    private function activePairs()
    {
        return Cache::remember('active_pairs:list', 5, function () {
            return Pair::where('is_active', true)->get();
        });
    }

    public function handleShutdown(): void
    {
        $this->info('Received shutdown signal, stopping gracefully...');
        $this->isRunning = false;
    }

    private function showStats(int $tickCount, int $timestamp): void
    {
        $pairs = $this->activePairs();
        $this->info("=== Market Stats (Tick #{$tickCount}) ===");
        $this->info("Time: " . date('Y-m-d H:i:s', $timestamp));
        foreach ($pairs as $pair) {
            $p = $this->candleAggregationService->getCurrentPrice($pair);
            if ($p) $this->info("{$pair->symbol}: {$p}");
        }
        $this->info("Cache Driver: " . config('cache.default'));
    }
    // --- TREND LOGGING HELPERS ---

private function trendSnapshot(array $system): array
{
    $timeStr = now()->format('H:i:00');
    $session = $this->resolveSession($timeStr, $system);
    $trend   = match ($session) {
        'morning'   => $system['morning_trend']   ?? 'SIDEWAYS',
        'afternoon' => $system['afternoon_trend'] ?? 'SIDEWAYS',
        'evening'   => $system['evening_trend']   ?? 'SIDEWAYS',
        default     => 'SIDEWAYS',
    };
    $strength  = (float)($system['trend_strength'] ?? 5.0);
    // this must match computeTrendInfluence()
    $influence = match ($trend) {
        'UP'   => +0.3 * ($strength / 10.0),
        'DOWN' => -0.3 * ($strength / 10.0),
        default => 0.0,
    };

    return [
        'session'   => $session,
        'trend'     => $trend,
        'strength'  => $strength,
        'influence' => round($influence, 4),
    ];
}

private function maybeLogTrend(array $snap): void
{
    // Log only when changed or once per 60s.
    $key = 'market_run:last_trend_snapshot';
    $last = Cache::get($key);

    $nowSec  = time();
    $lastT   = (int) Cache::get($key.':t', 0);
    $changed = !$last || $last['session'] !== $snap['session'] || $last['trend'] !== $snap['trend'] || $last['strength'] !== $snap['strength'];

    if ($changed || ($nowSec - $lastT) >= 60) {
        $msg = sprintf(
            "[trend] session=%s trend=%s strength=%.1f/10 influence=%s",
            $snap['session'],
            $snap['trend'],
            $snap['strength'],
            $snap['influence']
        );

        $this->info($msg);
        if (class_exists(LoggingService::class)) {
            LoggingService::log('market_trend_context', $msg, null, null, null, 'info', $snap);
        }

        Cache::put($key, $snap, 3600);
        Cache::put($key.':t', $nowSec, 3600);
    }
}

}
