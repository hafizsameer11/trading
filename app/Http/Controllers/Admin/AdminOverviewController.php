<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Trade;
use App\Models\Deposit;
use App\Models\Withdrawal;
use App\Models\SystemControl;

class AdminOverviewController extends Controller
{
    public function index()
    {
        $stats = [
            'users_count' => User::count(),
            'pending_deposits' => Deposit::pending()->count(),
            'pending_withdrawals' => Withdrawal::pending()->count(),
            'active_trades' => Trade::pending()->count(),
            'daily_win_percent' => SystemControl::instance()->daily_win_percent,
            'otc_tick_ms' => SystemControl::instance()->otc_tick_ms,
        ];

        return response()->json($stats);
    }
}


