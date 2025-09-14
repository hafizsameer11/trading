<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemControl;
use App\Services\LoggingService;
use Illuminate\Http\Request;

class ControlController extends Controller
{
    public function show()
    {
        $controls = SystemControl::instance();

        return response()->json([
            'data' => [
                'daily_win_percent' => $controls->daily_win_percent,
                'otc_tick_ms' => $controls->otc_tick_ms,
                'morning_trend' => $controls->morning_trend,
                'afternoon_trend' => $controls->afternoon_trend,
                'evening_trend' => $controls->evening_trend,
                'morning_start' => $controls->morning_start,
                'morning_end' => $controls->morning_end,
                'afternoon_start' => $controls->afternoon_start,
                'afternoon_end' => $controls->afternoon_end,
                'evening_start' => $controls->evening_start,
                'evening_end' => $controls->evening_end,
                'trend_strength' => $controls->trend_strength,
                // Advanced Market Simulation
                'support_resistance_enabled' => $controls->support_resistance_enabled,
                'order_blocks_enabled' => $controls->order_blocks_enabled,
                'fair_value_gaps_enabled' => $controls->fair_value_gaps_enabled,
                'fakeout_probability' => $controls->fakeout_probability,
                // Multi-timeframe settings
                'active_timeframes' => $controls->active_timeframes,
                // Advanced win rate controls
                'base_win_rate' => $controls->base_win_rate,
                'stake_penalty_factor' => $controls->stake_penalty_factor,
                'daily_adjustment_factor' => $controls->daily_adjustment_factor,
                'manual_override_enabled' => $controls->manual_override_enabled,
                // Chart behavior
                'smooth_candle_updates' => $controls->smooth_candle_updates,
                'candle_animation_speed' => $controls->candle_animation_speed,
            ]
        ]);
    }

    public function update(Request $request)
    {
        $request->validate([
            'daily_win_percent' => 'numeric|min:0|max:100',
            'otc_tick_ms' => 'integer|min:100|max:10000',
            'morning_trend' => 'in:UP,DOWN,SIDEWAYS',
            'afternoon_trend' => 'in:UP,DOWN,SIDEWAYS',
            'evening_trend' => 'in:UP,DOWN,SIDEWAYS',
            'morning_start' => 'date_format:H:i:s',
            'morning_end' => 'date_format:H:i:s',
            'afternoon_start' => 'date_format:H:i:s',
            'afternoon_end' => 'date_format:H:i:s',
            'evening_start' => 'date_format:H:i:s',
            'evening_end' => 'date_format:H:i:s',
            'trend_strength' => 'numeric|min:1.0|max:10.0',
            // Advanced Market Simulation
            'support_resistance_enabled' => 'boolean',
            'order_blocks_enabled' => 'boolean',
            'fair_value_gaps_enabled' => 'boolean',
            'fakeout_probability' => 'numeric|min:0|max:1',
            // Multi-timeframe settings
            'active_timeframes' => 'array',
            'active_timeframes.*' => 'string|in:5s,30s,1m,5m,15m,30m,1h,2h,4h',
            // Advanced win rate controls
            'base_win_rate' => 'numeric|min:0|max:1',
            'stake_penalty_factor' => 'numeric|min:0|max:1',
            'daily_adjustment_factor' => 'numeric|min:0|max:1',
            'manual_override_enabled' => 'boolean',
            // Chart behavior
            'smooth_candle_updates' => 'boolean',
            'candle_animation_speed' => 'integer|min:100|max:5000',
        ]);

        $controls = SystemControl::instance();
        $oldData = $controls->toArray();
        
        $controls->update($request->only([
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
        ]));

        // Log the system settings update
        LoggingService::logSystemSettingsUpdated($oldData, $controls->fresh()->toArray(), request());

        return response()->json([
            'message' => 'System controls updated successfully',
            'data' => [
                'daily_win_percent' => $controls->daily_win_percent,
                'otc_tick_ms' => $controls->otc_tick_ms,
                'morning_trend' => $controls->morning_trend,
                'afternoon_trend' => $controls->afternoon_trend,
                'evening_trend' => $controls->evening_trend,
                'morning_start' => $controls->morning_start,
                'morning_end' => $controls->morning_end,
                'afternoon_start' => $controls->afternoon_start,
                'afternoon_end' => $controls->afternoon_end,
                'evening_start' => $controls->evening_start,
                'evening_end' => $controls->evening_end,
                'trend_strength' => $controls->trend_strength,
                // Advanced Market Simulation
                'support_resistance_enabled' => $controls->support_resistance_enabled,
                'order_blocks_enabled' => $controls->order_blocks_enabled,
                'fair_value_gaps_enabled' => $controls->fair_value_gaps_enabled,
                'fakeout_probability' => $controls->fakeout_probability,
                // Multi-timeframe settings
                'active_timeframes' => $controls->active_timeframes,
                // Advanced win rate controls
                'base_win_rate' => $controls->base_win_rate,
                'stake_penalty_factor' => $controls->stake_penalty_factor,
                'daily_adjustment_factor' => $controls->daily_adjustment_factor,
                'manual_override_enabled' => $controls->manual_override_enabled,
                // Chart behavior
                'smooth_candle_updates' => $controls->smooth_candle_updates,
                'candle_animation_speed' => $controls->candle_animation_speed,
            ]
        ]);
    }
}
