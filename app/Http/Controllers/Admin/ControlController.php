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
            'trend_strength'
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
            ]
        ]);
    }
}
