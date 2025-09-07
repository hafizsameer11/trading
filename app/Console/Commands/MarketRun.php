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
    // protected $signature = 'market:run {--base=1} {--pairs=*}';
    protected $signature = 'market:run {--base=1} {--pairs=*} {--duration=55}';

    protected $description = 'Generate live market data with SystemControl, realistic zig-zag motion, and win-rate enforcement';

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
        $this->info('🚀 MarketRun starting (SystemControl + zig-zag microstructure + enforced win-rate)...');

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
        $duration = (int) $this->option('duration');
        if ($duration < 5 || $duration > 58) {
            $duration = 55; // keep a little headroom for cron-per-minute
        }

        try {
            $this->initializeSystem();
            $this->startMainLoop($duration);
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

  /**
 * Main loop.
 * Runs ticks until either:
 *  - process receives a shutdown signal, OR
 *  - duration (seconds) is reached (default 55s for cron)
 */
private function startMainLoop(int $duration = 55): void
{
    $tickCount     = 0;
    $lastStatsTime = time();
    $startTime     = microtime(true);

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
                // 1) Build price (zig-zag step + soft win-rate + gentle nudge)
                $finalPrice = $this->buildPriceForPair($pair, $nowTs, $system);

                // 2) Enforce daily win-rate for trades expiring this tick
                $finalPrice = $this->enforceWinRateAtExpiry($pair, $finalPrice, $nowTs, $tickMs, $system);

                // 3) Persist spot & aggregate
                Cache::put("spot:{$pair->id}", $finalPrice, 86400);
                $this->candleAggregationService->aggregateTickIntoAllBuckets($pair, $finalPrice, $nowTs);
            }

            $tickCount++;

            if ((time() - $lastStatsTime) >= 60) {
                $this->showStats($tickCount, time());
                $lastStatsTime = time();
            }

            // honor otc_tick_ms
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
            usleep(500_000);
        }

        // --- Check duration limit ---
        if ((microtime(true) - $startTime) >= max(10, $duration)) {
            $this->info("⏱️ Duration {$duration}s reached, exiting cleanly for cron restart.");
            break;
        }
    }

    $this->info('🛑 MarketRun stopped.');
    if (class_exists(LoggingService::class)) {
        LoggingService::log('market_run_stopped', 'MarketRun stopped gracefully', null, null, null, 'info');
    }
}


    /**
     * Build price with zig-zag step + soft win-rate + gentle open-trade nudge.
     */
    private function buildPriceForPair(Pair $pair, int $timestamp, array $system): float
    {
        $currentSpot = $this->otcPriceService->getOrSeedSpot($pair);

        // realistic step (swing state + AR(1) flicker + per-tick cap)
        $step = $this->zigZagStep($pair, $currentSpot, $system);

        // soft daily win-rate influence (very tiny; keeps path natural)
        $winRateInfluence = $this->computeWinRateInfluence($pair->id, $system); // [-0.5, 0.5]
        $softNudge        = $currentSpot * 0.00005 * $winRateInfluence;

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
     * HARD enforcement at expiry with physics limits + strict comparison truth.
     * If target win-rate is 100%, we allow breaking the nudge cap to satisfy outcomes.
     */
    private function enforceWinRateAtExpiry(Pair $pair, float $finalPrice, int $nowTs, int $tickMs, array $system): float
    {
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

        $preClose = $finalPrice;

        // Score trades by distance to flip to WIN from preClose
        $scored = $expiring->map(function ($t) use ($preClose) {
            $e = (float)$t->entry_price;
            $need = ($t->direction === 'UP')
                ? max(0.0, ($e - $preClose) + 1e-12)     // need close > entry
                : max(0.0, ($preClose - $e) + 1e-12);    // need close < entry
            return ['trade' => $t, 'need' => $need, 'entry' => $e];
        })->sortBy('need')->values();

        $assignWins = $scored->take($neededWins)->pluck('trade');
        $assignLose = $expiring->reject(fn($t) => $assignWins->contains('id', $t->id))->values();

        // Build constraints for a single closing price
        $precision = max(0, (int)$pair->price_precision);
        $epsilon   = pow(10, -$precision) * 0.9;

        $upWin = []; $downWin = []; $upLose = []; $downLose = [];
        foreach ($assignWins as $t) {
            if ($t->direction === 'UP')   $upWin[]   = (float)$t->entry_price;
            if ($t->direction === 'DOWN') $downWin[] = (float)$t->entry_price;
        }
        foreach ($assignLose as $t) {
            if ($t->direction === 'UP')   $upLose[]   = (float)$t->entry_price;
            if ($t->direction === 'DOWN') $downLose[] = (float)$t->entry_price;
        }

        // Feasible band
        $L = -INF; $R = INF;
        $hiNeed = !empty($upWin)   ? max($upWin)   + $epsilon : null; // enforce UP winners   : close >
        $loNeed = !empty($downWin) ? min($downWin) - $epsilon : null; // enforce DOWN winners : close <
        $hiGuard = !empty($downLose) ? max($downLose) + $epsilon : null; // keep DOWN losers: close >
        $loGuard = !empty($upLose)   ? min($upLose)   - $epsilon : null; // keep UP losers  : close <

        foreach ([$hiNeed, $hiGuard] as $v) { if ($v !== null) $L = max($L, $v); }
        foreach ([$loNeed, $loGuard] as $v) { if ($v !== null) $R = min($R, $v); }

        // Choose target inside band (or relax by winners if infeasible)
        if ($L > $R) {
            // prefer winner side
            if (count($upWin) >= count($downWin)) { $L = $hiNeed ?? $L; $R = INF; }
            else                                  { $R = $loNeed ?? $R; $L = -INF; }
        }

        $target = min(max($preClose, $L), $R);

        // EWMA TR for nudge cap
        $trKey    = "ewma_tr:{$pair->id}";
        $ewmaTR   = Cache::get($trKey, $preClose * 0.0015);
        $maxNudge = $ewmaTR * 0.8; // default cap

        // If 100% win-rate requested, allow breaking the cap just enough
        if ($targetFrac >= 0.999) {
            // push just across the strictest required threshold
            if (!empty($upWin))   $target = max($target, max($upWin) + $epsilon);
            if (!empty($downWin)) $target = min($target, min($downWin) - $epsilon);
        } else {
            // obey cap
            $target = max($preClose - $maxNudge, min($preClose + $maxNudge, $target));
        }

        // Clamp & small jitter to avoid exact pinning
        if (!is_null($pair->min_price)) $target = max((float)$pair->min_price, $target);
        if (!is_null($pair->max_price)) $target = min((float)$pair->max_price, $target);
        $target = round($target, $precision);
        $jit = pow(10, -$precision) * (mt_rand(-3,3) / 10.0); // ±0.3 tick
        $target = round($target + $jit, $precision);

        // --- FINAL TRUTH: write outcomes strictly by comparison ---
        $wins = []; $loses = [];
        foreach ($expiring as $t) {
            $res = $this->outcomeFor((float)$t->entry_price, $target, $t->direction, $precision);
            if ($res === 'WIN') $wins[] = $t->id; else $loses[] = $t->id;
        }

        if (class_exists(LoggingService::class)) {
            LoggingService::log('market_winrate_batch', 'settlement batch', null, null, null, 'info', [
                'pair'         => $pair->symbol,
                'preClose'     => $preClose,
                'targetClose'  => $target,
                'expiring'     => $expiring->count(),
                'neededWins'   => $neededWins,
                'actualWins'   => count($wins),
                'winsSoFar'    => $winsSoFar,
                'totalSoFar'   => $totalSoFar,
                'targetPct'    => $targetFrac,
            ]);
        }

        DB::transaction(function () use ($wins, $loses, $target) {
            $now = now();
            if (!empty($wins)) {
                Trade::whereIn('id', $wins)->update([
                    'result'        => 'WIN',
                    'closing_price' => $target,
                    'settled_at'    => $now,
                ]);
            }
            if (!empty($loses)) {
                Trade::whereIn('id', $loses)->update([
                    'result'        => 'LOSE',
                    'closing_price' => $target,
                    'settled_at'    => $now,
                ]);
            }
        });

        return $target;
    }

    private function outcomeFor(?float $entry, ?float $close, string $dir, int $precision): string
    {
        if ($entry === null || $close === null) return 'LOSE';
        $eps = pow(10, -max(0, $precision)) * 0.5; // half-tick tolerance
        if ($dir === 'UP')   return ($close > $entry + $eps) ? 'WIN' : 'LOSE';
        if ($dir === 'DOWN') return ($close < $entry - $eps) ? 'WIN' : 'LOSE';
        return 'LOSE';
    }

    private function winsNeededForBatch(int $winsSoFar, int $totalSoFar, int $batchSize, float $target): int
    {
        $afterTotal = $totalSoFar + $batchSize;
        $targetWins = (int)round($afterTotal * $target);
        $need       = $targetWins - $winsSoFar;
        return max(0, min($batchSize, $need));
    }

    /**
     * Zig-zag generator:
     *  - Swing state machine: SWING_UP / SWING_DOWN / PAUSE with durations
     *  - Biased by SystemControl trend (but never deterministic)
     *  - AR(1) flicker to alternate candle colors naturally
     *  - Per-tick cap using EWMA true range
     */
    private function zigZagStep(Pair $pair, float $spot, array $system): float
    {
        $precision = max(0, (int)$pair->price_precision);

        // Trend influence
        $ti  = $this->computeTrendInfluence($system);  // [-0.3..+0.3]
        $mag = abs($ti);
        $trendDir = $ti >= 0 ? +1 : -1;

        // Swing state
        $key = "swing:{$pair->id}";
        $s = Cache::get($key, [
            'phase'      => 'PAUSE', // SWING_UP, SWING_DOWN, PAUSE
            'ticks_left' => 0,
            'last_step'  => 0.0,
        ]);

        if ($s['ticks_left'] <= 0) {
            // Choose next phase. Bias towards the trend, but keep reversals possible.
            $pUp    = 0.35 + 0.30 * max(0,  $trendDir) * $mag; // 0.35..0.65
            $pDown  = 0.35 + 0.30 * max(0, -$trendDir) * $mag; // 0.35..0.65
            $pPause = 1.0 - min(0.9, $pUp + $pDown);           // keep some pauses

            $r = mt_rand() / mt_getrandmax();
            if     ($r < $pUp)                $s['phase'] = 'SWING_UP';
            elseif ($r < $pUp + $pDown)       $s['phase'] = 'SWING_DOWN';
            else                               $s['phase'] = 'PAUSE';

            // Durations
            $s['ticks_left'] = match ($s['phase']) {
                'SWING_UP', 'SWING_DOWN' => mt_rand(3, 10),
                default                  => mt_rand(2, 6),
            };
        } else {
            $s['ticks_left']--;
        }

        // EWMA volatility / true range
        $volKey = "ewma_vol:{$pair->id}";
        $trKey  = "ewma_tr:{$pair->id}";
        $ewma = Cache::get($volKey, 0.0);
        $tr   = Cache::get($trKey, $spot * 0.0015); // 15 bps fallback
        $alpha = 0.15;
        if ($ewma <= 0) $ewma = (float)($spot * 1e-5);

        // Base sigma and drift per phase
        $baseSigma = 0.00055;
        $sigma = $baseSigma * (0.5 + 0.5 * ($ewma / max(1e-12, $baseSigma * $spot)));

        // mean slope in bps (converted to fraction below)
        $bp = match ($s['phase']) {
            'SWING_UP'   =>  0.05 + 0.20 * $mag,   // 5–25 bps
            'SWING_DOWN' => -0.05 - 0.20 * $mag,   // -5–-25 bps
            default      =>  0.00,
        };

        // Gaussian shock
        $z  = $this->gauss01();

        // AR(1) flicker to encourage alternating colors (mean-reverting)
        $fkKey = "flick:{$pair->id}";
        $f = Cache::get($fkKey, 0.0);
        $rho   = 0.45;                 // persistence
        $fSigma= $spot * 0.0002;       // ~2 bps
        $f = $rho * $f + (1.0 - $rho) * 0 + $fSigma * $this->gauss01();
        Cache::put($fkKey, $f, 3600);

        // Raw step (swing slope + noise + flicker)
        $step = $spot * ( ($bp / 10000.0) + $sigma * $z ) + $f;

        // Forced reversal probability: if last step had same sign many times, flip occasionally
        $lastKey = "last_sign:{$pair->id}";
        $last = (int) Cache::get($lastKey, 0); // positive => consecutive ups; negative => consecutive downs
        $sign = $step >= 0 ? 1 : -1;
        $consec = ($sign === 0) ? 0 : (($sign * $last) > 0 ? $last + $sign : $sign);

        // if too many same-direction steps, flip with small chance (keeps "breathing")
        $flipProb = min(0.25, max(0.0, (abs($consec) - 4) * 0.04)); // kicks in after ~4 same-colored ticks
        if ((mt_rand() / mt_getrandmax()) < $flipProb) {
            $step = -$step * (0.4 + 0.3 * (mt_rand() / mt_getrandmax())); // small counter poke
            $sign = -$sign;
            $consec = -1;
        }
        Cache::put($lastKey, $consec, 600);

        // Per-tick cap using EWMA TR (keep wicks believable)
        $cap  = max($spot * 0.0015, $tr * 0.1);
        $step = max(-$cap, min($cap, $step));

        // Update EWMA trackers
        $newEwma = (1-$alpha) * $ewma + $alpha * abs($step);
        Cache::put($volKey, $newEwma, 3600);
        $newTr   = (1-$alpha) * $tr   + $alpha * max($spot * 1e-5, abs($step));
        Cache::put($trKey, $newTr, 3600);

        Cache::put($key, $s, 120);

        return round($step, $precision);
    }

    private function gauss01(): float
    {
        $u1 = max(1e-12, mt_rand() / mt_getrandmax());
        $u2 = mt_rand() / mt_getrandmax();
        return sqrt(-2 * log($u1)) * cos(2 * M_PI * $u2);
    }

    /**
     * Trend influence from SystemControl (UP/DOWN/SIDEWAYS with strength).
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
