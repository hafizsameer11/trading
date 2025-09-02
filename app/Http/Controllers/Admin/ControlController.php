<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemControl;
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
            ]
        ]);
    }

    public function update(Request $request)
    {
        $request->validate([
            'daily_win_percent' => 'numeric|min:0|max:100',
            'otc_tick_ms' => 'integer|min:100|max:10000',
        ]);

        $controls = SystemControl::instance();
        $controls->update($request->only(['daily_win_percent', 'otc_tick_ms']));

        return response()->json([
            'message' => 'System controls updated successfully',
            'data' => [
                'daily_win_percent' => $controls->daily_win_percent,
                'otc_tick_ms' => $controls->otc_tick_ms,
            ]
        ]);
    }
}
