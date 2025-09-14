<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemControl extends Model
{
    use HasFactory;

    protected $fillable = [
        'daily_win_percent',
        'otc_tick_ms',
        'morning_trend',
        'afternoon_trend',
        'evening_trend',
        'morning_start',
        'morning_end',
        'afternoon_start',
        'afternoon_end',
        'evening_start',
        'evening_end',
        'trend_strength',
        // Advanced Market Simulation
        'support_resistance_enabled',
        'order_blocks_enabled',
        'fair_value_gaps_enabled',
        'fakeout_probability',
        // Multi-timeframe settings
        'active_timeframes',
        // Advanced win rate controls
        'base_win_rate',
        'stake_penalty_factor',
        'daily_adjustment_factor',
        'manual_override_enabled',
        // Chart behavior
        'smooth_candle_updates',
        'candle_animation_speed',
    ];

    protected $casts = [
        'daily_win_percent' => 'decimal:2',
        'otc_tick_ms' => 'integer',
        'trend_strength' => 'decimal:1',
        // Advanced Market Simulation
        'support_resistance_enabled' => 'boolean',
        'order_blocks_enabled' => 'boolean',
        'fair_value_gaps_enabled' => 'boolean',
        'fakeout_probability' => 'decimal:2',
        // Multi-timeframe settings
        'active_timeframes' => 'array',
        // Advanced win rate controls
        'base_win_rate' => 'decimal:2',
        'stake_penalty_factor' => 'decimal:2',
        'daily_adjustment_factor' => 'decimal:2',
        'manual_override_enabled' => 'boolean',
        // Chart behavior
        'smooth_candle_updates' => 'boolean',
        'candle_animation_speed' => 'integer',
    ];

    public static function instance()
    {
        return static::firstOrCreate(['id' => 1], [
            'daily_win_percent' => 50.00,
            'otc_tick_ms' => 1000,
            'morning_trend' => 'SIDEWAYS',
            'afternoon_trend' => 'SIDEWAYS',
            'evening_trend' => 'SIDEWAYS',
            'morning_start' => '09:00:00',
            'morning_end' => '12:00:00',
            'afternoon_start' => '12:00:00',
            'afternoon_end' => '17:00:00',
            'evening_start' => '17:00:00',
            'evening_end' => '21:00:00',
            'trend_strength' => 5.0,
            // Advanced Market Simulation
            'support_resistance_enabled' => true,
            'order_blocks_enabled' => true,
            'fair_value_gaps_enabled' => true,
            'fakeout_probability' => 0.30,
            // Multi-timeframe settings
            'active_timeframes' => ['5s', '30s', '1m', '5m', '15m', '30m', '1h', '2h', '4h'],
            // Advanced win rate controls
            'base_win_rate' => 0.60,
            'stake_penalty_factor' => 0.05,
            'daily_adjustment_factor' => 0.20,
            'manual_override_enabled' => true,
            // Chart behavior
            'smooth_candle_updates' => true,
            'candle_animation_speed' => 1000,
        ]);
    }
}
