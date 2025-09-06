<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Trade;
use App\Models\Deposit;
use App\Models\Withdrawal;
use App\Models\Pair;
use App\Models\MarketData;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ReportsController extends Controller
{
    /**
     * Get comprehensive analytics data
     */
    public function analytics(Request $request): JsonResponse
    {
        $period = $request->get('period', 'week'); // today, week, month, year
        $startDate = $this->getStartDate($period);
        $endDate = now();

        $data = [
            'period' => $period,
            'date_range' => [
                'start' => $startDate->toISOString(),
                'end' => $endDate->toISOString(),
            ],
            'trading' => $this->getTradingStats($startDate, $endDate),
            'users' => $this->getUserStats($startDate, $endDate),
            'financial' => $this->getFinancialStats($startDate, $endDate),
            'pairs' => $this->getPairStats($startDate, $endDate),
            'trends' => $this->getTrendData($period),
        ];

        return response()->json(['data' => $data]);
    }

    /**
     * Get trading statistics
     */
    private function getTradingStats($startDate, $endDate): array
    {
        $trades = Trade::whereBetween('created_at', [$startDate, $endDate]);

        $totalTrades = $trades->count();
        $totalVolume = $trades->sum('amount');
        $winningTrades = $trades->where('result', 'WIN')->count();
        $losingTrades = $trades->where('result', 'LOSE')->count();
        $pendingTrades = $trades->where('result', 'PENDING')->count();

        $winRate = $totalTrades > 0 ? ($winningTrades / $totalTrades) * 100 : 0;

        // Calculate total profit/loss
        $totalProfit = $trades->where('result', 'WIN')->sum('amount') - 
                      $trades->where('result', 'LOSE')->sum('amount');

        // Get previous period for comparison
        $prevStartDate = $startDate->copy()->sub($endDate->diffInDays($startDate), 'days');
        $prevEndDate = $startDate->copy();
        
        $prevTrades = Trade::whereBetween('created_at', [$prevStartDate, $prevEndDate]);
        $prevTotalTrades = $prevTrades->count();
        $prevTotalVolume = $prevTrades->sum('amount');
        $prevWinRate = $prevTotalTrades > 0 ? 
            ($prevTrades->where('result', 'WIN')->count() / $prevTotalTrades) * 100 : 0;

        return [
            'total_trades' => $totalTrades,
            'total_volume' => $totalVolume,
            'winning_trades' => $winningTrades,
            'losing_trades' => $losingTrades,
            'pending_trades' => $pendingTrades,
            'win_rate' => round($winRate, 2),
            'total_profit' => $totalProfit,
            'avg_trade_size' => $totalTrades > 0 ? round($totalVolume / $totalTrades, 2) : 0,
            'growth' => [
                'trades' => $this->calculateGrowth($totalTrades, $prevTotalTrades),
                'volume' => $this->calculateGrowth($totalVolume, $prevTotalVolume),
                'win_rate' => round($winRate - $prevWinRate, 2),
            ]
        ];
    }

    /**
     * Get user statistics
     */
    private function getUserStats($startDate, $endDate): array
    {
        $totalUsers = User::count();
        $newUsers = User::whereBetween('created_at', [$startDate, $endDate])->count();
        $activeUsers = User::whereHas('trades', function ($query) use ($startDate, $endDate) {
            $query->whereBetween('created_at', [$startDate, $endDate]);
        })->count();
        $adminUsers = User::where('is_admin', true)->count();

        // Get previous period for comparison
        $prevStartDate = $startDate->copy()->sub($endDate->diffInDays($startDate), 'days');
        $prevEndDate = $startDate->copy();
        $prevNewUsers = User::whereBetween('created_at', [$prevStartDate, $prevEndDate])->count();

        return [
            'total_users' => $totalUsers,
            'new_users' => $newUsers,
            'active_users' => $activeUsers,
            'admin_users' => $adminUsers,
            'user_growth_rate' => $this->calculateGrowth($newUsers, $prevNewUsers),
        ];
    }

    /**
     * Get financial statistics
     */
    private function getFinancialStats($startDate, $endDate): array
    {
        $totalDeposits = Deposit::where('status', 'approved')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->sum('amount');

        $totalWithdrawals = Withdrawal::where('status', 'approved')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->sum('amount');

        $pendingDeposits = Deposit::where('status', 'pending')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->sum('amount');

        $pendingWithdrawals = Withdrawal::where('status', 'pending')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->sum('amount');

        // Get previous period for comparison
        $prevStartDate = $startDate->copy()->sub($endDate->diffInDays($startDate), 'days');
        $prevEndDate = $startDate->copy();
        
        $prevDeposits = Deposit::where('status', 'approved')
            ->whereBetween('created_at', [$prevStartDate, $prevEndDate])
            ->sum('amount');
        
        $prevWithdrawals = Withdrawal::where('status', 'approved')
            ->whereBetween('created_at', [$prevStartDate, $prevEndDate])
            ->sum('amount');

        return [
            'total_deposits' => $totalDeposits,
            'total_withdrawals' => $totalWithdrawals,
            'pending_deposits' => $pendingDeposits,
            'pending_withdrawals' => $pendingWithdrawals,
            'net_flow' => $totalDeposits - $totalWithdrawals,
            'growth' => [
                'deposits' => $this->calculateGrowth($totalDeposits, $prevDeposits),
                'withdrawals' => $this->calculateGrowth($totalWithdrawals, $prevWithdrawals),
            ]
        ];
    }

    /**
     * Get trading pairs statistics
     */
    private function getPairStats($startDate, $endDate): array
    {
        $pairs = Pair::withCount(['trades' => function ($query) use ($startDate, $endDate) {
            $query->whereBetween('created_at', [$startDate, $endDate]);
        }])->get();

        $pairStats = $pairs->map(function ($pair) use ($startDate, $endDate) {
            $trades = $pair->trades()->whereBetween('created_at', [$startDate, $endDate]);
            $totalTrades = $trades->count();
            $totalVolume = $trades->sum('amount');
            $winningTrades = $trades->where('result', 'WIN')->count();
            $winRate = $totalTrades > 0 ? ($winningTrades / $totalTrades) * 100 : 0;
            $totalProfit = $trades->where('result', 'WIN')->sum('amount') - 
                          $trades->where('result', 'LOSE')->sum('amount');

            return [
                'id' => $pair->id,
                'symbol' => $pair->symbol,
                'slug' => $pair->slug,
                'trades' => $totalTrades,
                'volume' => $totalVolume,
                'win_rate' => round($winRate, 2),
                'profit' => $totalProfit,
                'avg_trade_size' => $totalTrades > 0 ? round($totalVolume / $totalTrades, 2) : 0,
            ];
        })->sortByDesc('volume')->values();

        return $pairStats->toArray();
    }

    /**
     * Get trend data for charts
     */
    private function getTrendData($period): array
    {
        $days = $this->getDaysForPeriod($period);
        $trends = [];

        foreach ($days as $day) {
            $startOfDay = $day->startOfDay();
            $endOfDay = $day->endOfDay();

            $trades = Trade::whereBetween('created_at', [$startOfDay, $endOfDay]);
            $deposits = Deposit::where('status', 'approved')
                ->whereBetween('created_at', [$startOfDay, $endOfDay]);
            $withdrawals = Withdrawal::where('status', 'approved')
                ->whereBetween('created_at', [$startOfDay, $endOfDay]);
            $newUsers = User::whereBetween('created_at', [$startOfDay, $endOfDay]);

            $trends[] = [
                'date' => $day->toDateString(),
                'trades' => $trades->count(),
                'volume' => $trades->sum('amount'),
                'deposits' => $deposits->sum('amount'),
                'withdrawals' => $withdrawals->sum('amount'),
                'new_users' => $newUsers->count(),
                'win_rate' => $trades->count() > 0 ? 
                    round(($trades->where('result', 'WIN')->count() / $trades->count()) * 100, 2) : 0,
            ];
        }

        return $trends;
    }

    /**
     * Get start date based on period
     */
    private function getStartDate($period): Carbon
    {
        switch ($period) {
            case 'today':
                return now()->startOfDay();
            case 'week':
                return now()->subWeek()->startOfDay();
            case 'month':
                return now()->subMonth()->startOfDay();
            case 'year':
                return now()->subYear()->startOfDay();
            default:
                return now()->subWeek()->startOfDay();
        }
    }

    /**
     * Get days array for trend data
     */
    private function getDaysForPeriod($period): array
    {
        $days = [];
        $startDate = $this->getStartDate($period);
        $endDate = now();

        $current = $startDate->copy();
        while ($current->lte($endDate)) {
            $days[] = $current->copy();
            $current->addDay();
        }

        return $days;
    }

    /**
     * Calculate growth percentage
     */
    private function calculateGrowth($current, $previous): float
    {
        if ($previous == 0) {
            return $current > 0 ? 100 : 0;
        }
        return round((($current - $previous) / $previous) * 100, 2);
    }

    /**
     * Export reports data
     */
    public function export(Request $request)
    {
        $format = $request->get('format', 'csv'); // json, csv
        $period = $request->get('period', 'week');
        
        $data = $this->analytics($request)->getData(true);
        
        if ($format === 'csv') {
            return $this->exportToCsv($data['data'], $period);
        }

        return response()->json($data);
    }

    /**
     * Export data to CSV format
     */
    private function exportToCsv($data, $period)
    {
        $filename = 'trading_analytics_' . $period . '_' . now()->format('Y-m-d_H-i-s') . '.csv';
        
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function() use ($data) {
            $file = fopen('php://output', 'w');
            
            // Write headers
            fputcsv($file, ['Metric', 'Value', 'Period']);
            
            // Write trading data
            fputcsv($file, ['Total Trades', $data['trading']['total_trades'], $data['period']]);
            fputcsv($file, ['Total Volume', $data['trading']['total_volume'], $data['period']]);
            fputcsv($file, ['Win Rate (%)', $data['trading']['win_rate'], $data['period']]);
            fputcsv($file, ['Winning Trades', $data['trading']['winning_trades'], $data['period']]);
            fputcsv($file, ['Losing Trades', $data['trading']['losing_trades'], $data['period']]);
            fputcsv($file, ['Pending Trades', $data['trading']['pending_trades'], $data['period']]);
            fputcsv($file, ['Total Profit', $data['trading']['total_profit'], $data['period']]);
            fputcsv($file, ['Average Trade Size', $data['trading']['avg_trade_size'], $data['period']]);
            
            // Write user data
            fputcsv($file, ['Total Users', $data['users']['total_users'], $data['period']]);
            fputcsv($file, ['New Users', $data['users']['new_users'], $data['period']]);
            fputcsv($file, ['Active Users', $data['users']['active_users'], $data['period']]);
            fputcsv($file, ['Admin Users', $data['users']['admin_users'], $data['period']]);
            fputcsv($file, ['User Growth Rate (%)', $data['users']['user_growth_rate'], $data['period']]);
            
            // Write financial data
            fputcsv($file, ['Total Deposits', $data['financial']['total_deposits'], $data['period']]);
            fputcsv($file, ['Total Withdrawals', $data['financial']['total_withdrawals'], $data['period']]);
            fputcsv($file, ['Pending Deposits', $data['financial']['pending_deposits'], $data['period']]);
            fputcsv($file, ['Pending Withdrawals', $data['financial']['pending_withdrawals'], $data['period']]);
            fputcsv($file, ['Net Flow', $data['financial']['net_flow'], $data['period']]);
            fputcsv($file, ['Deposit Growth (%)', $data['financial']['growth']['deposits'], $data['period']]);
            fputcsv($file, ['Withdrawal Growth (%)', $data['financial']['growth']['withdrawals'], $data['period']]);
            
            // Write pair data
            fputcsv($file, ['', '', '']); // Empty row
            fputcsv($file, ['Pair', 'Trades', 'Volume', 'Win Rate (%)', 'Profit', 'Average Trade Size']);
            foreach ($data['pairs'] as $pair) {
                fputcsv($file, [
                    $pair['symbol'],
                    $pair['trades'],
                    $pair['volume'],
                    $pair['win_rate'],
                    $pair['profit'],
                    $pair['avg_trade_size']
                ]);
            }
            
            // Write trend data
            fputcsv($file, ['', '', '']); // Empty row
            fputcsv($file, ['Date', 'Trades', 'Volume', 'Deposits', 'Withdrawals', 'New Users', 'Win Rate (%)']);
            foreach ($data['trends'] as $trend) {
                fputcsv($file, [
                    $trend['date'],
                    $trend['trades'],
                    $trend['volume'],
                    $trend['deposits'],
                    $trend['withdrawals'],
                    $trend['new_users'],
                    $trend['win_rate']
                ]);
            }
            
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
