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
        // Basic counts
        $totalUsers = User::count();
        $pendingDeposits = Deposit::where('status', 'PENDING')->count();
        $pendingWithdrawals = Withdrawal::where('status', 'PENDING')->count();
        $activeTrades = Trade::where('result', 'PENDING')->count();

        // Financial totals
        $totalDeposits = Deposit::where('status', 'APPROVED')->sum('amount');
        $totalWithdrawals = Withdrawal::where('status', 'APPROVED')->sum('amount');
        $totalTrades = Trade::count();

        // Win rate calculation
        $wonTrades = Trade::where('result', 'WIN')->count();
        $winRate = $totalTrades > 0 ? round(($wonTrades / $totalTrades) * 100, 1) : 0;

        // System controls
        $systemControl = SystemControl::first();
        $dailyWinPercent = $systemControl ? $systemControl->daily_win_percent : 50;
        $otcTickMs = $systemControl ? $systemControl->otc_tick_ms : 1000;

        $stats = [
            'total_users' => $totalUsers,
            'pending_deposits' => $pendingDeposits,
            'pending_withdrawals' => $pendingWithdrawals,
            'active_trades' => $activeTrades,
            'total_deposits' => $totalDeposits,
            'total_withdrawals' => $totalWithdrawals,
            'total_trades' => $totalTrades,
            'win_rate' => $winRate,
            'daily_win_percent' => $dailyWinPercent,
            'otc_tick_ms' => $otcTickMs,
        ];

        return response()->json($stats);
    }
}


