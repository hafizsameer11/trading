<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AdminUserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = User::query();

        // Search functionality
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Filter by role
        if ($request->has('role') && $request->role !== 'all') {
            if ($request->role === 'admin') {
                $query->where('is_admin', true);
            } elseif ($request->role === 'user') {
                $query->where('is_admin', false);
            }
        }

        // Get users with their trading statistics
        $users = $query->withCount([
            'trades as total_trades',
            'trades as won_trades' => function ($query) {
                $query->where('result', 'WIN');
            },
            'trades as lost_trades' => function ($query) {
                $query->where('result', 'LOSE');
            }
        ])
        ->withSum('deposits as total_deposits', 'amount')
        ->withSum('withdrawals as total_withdrawals', 'amount')
        ->orderBy('created_at', 'desc')
        ->paginate(20);

        // Calculate win rate for each user
        $users->getCollection()->transform(function ($user) {
            $user->win_rate = $user->total_trades > 0 
                ? round(($user->won_trades / $user->total_trades) * 100, 1) 
                : 0;
            return $user;
        });

        return response()->json($users);
    }

    public function show($user): JsonResponse
    {
        $user = User::find($user);
        // Load relationships
        $user->load([
            'trades' => function ($query) {
                $query->orderBy('created_at', 'desc')->limit(10);
            },
            'deposits' => function ($query) {
                $query->orderBy('created_at', 'desc')->limit(5);
            },
            'withdrawals' => function ($query) {
                $query->orderBy('created_at', 'desc')->limit(5);
            }
        ]);

        // Calculate statistics
        $totalTrades = $user->trades()->count();
        $wonTrades = $user->trades()->where('result', 'WIN')->count();
        $lostTrades = $user->trades()->where('result', 'LOSE')->count();
        $winRate = $totalTrades > 0 ? round(($wonTrades / $totalTrades) * 100, 1) : 0;
        $totalDeposits = $user->deposits()->sum('amount') ?? 0;
        $totalWithdrawals = $user->withdrawals()->sum('amount') ?? 0;
        
        // Calculate total profit/loss from trades
        $totalProfit = $user->trades()->get()->sum(function ($trade) {
            return $trade->pnl; // Use the accessor method
        });

        // Return user data with calculated statistics
        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'is_admin' => $user->is_admin,
            'created_at' => $user->created_at,
            'last_login' => $user->last_login_attempt,
            'demo_balance' => $user->demo_balance ?? 0,
            'live_balance' => $user->live_balance ?? 0,
            'total_trades' => $totalTrades,
            'won_trades' => $wonTrades,
            'lost_trades' => $lostTrades,
            'win_rate' => $winRate,
            'total_deposits' => $totalDeposits,
            'total_withdrawals' => $totalWithdrawals,
            'total_profit' => $totalProfit,
            'trades' => $user->trades,
            'deposits' => $user->deposits,
            'withdrawals' => $user->withdrawals,
        ]);
    }

    public function getTrades($user): JsonResponse
    {
        $user = User::find($user);
        $trades = $user->trades()
            ->with('pair')
            ->orderBy('created_at', 'desc')
            ->paginate(50);

        // Transform trades to include profit/loss (keep pair as object)
        $trades->getCollection()->transform(function ($trade) {
            $trade->profit = $trade->pnl; // Add profit/loss using the accessor
            return $trade;
        });

        return response()->json($trades);
    }

    public function updateBalance(Request $request, $user): JsonResponse
    {
        $request->validate([
            'account_type' => 'required|in:demo,live',
            'amount' => 'required|numeric|min:0',
            'action' => 'required|in:add,subtract,set'
        ]);
        $user = User::find($user);
        $field = $request->account_type === 'live' ? 'live_balance' : 'demo_balance';
        $currentBalance = $user->$field;

        switch ($request->action) {
            case 'add':
                $newBalance = $currentBalance + $request->amount;
                break;
            case 'subtract':
                $newBalance = max(0, $currentBalance - $request->amount);
                break;
            case 'set':
                $newBalance = $request->amount;
                break;
        }

        $user->update([$field => $newBalance]);

        return response()->json([
            'message' => 'Balance updated successfully',
            'new_balance' => $newBalance,
            'account_type' => $request->account_type
        ]);
    }

    public function toggleAdmin($user): JsonResponse
    {
        $user = User::find($user);
        $user->update(['is_admin' => !$user->is_admin]);

        return response()->json([
            'message' => 'Admin status updated successfully',
            'is_admin' => $user->is_admin
        ]);
    }
}