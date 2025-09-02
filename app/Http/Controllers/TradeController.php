<?php

namespace App\Http\Controllers;

use App\Models\Trade;
use App\Models\Pair;
use App\Models\User;
use App\Services\TradeEngine;
use App\Services\OtcPriceService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class TradeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = $user->trades()->with('pair');

        // Filter by status
        if ($request->has('status')) {
            if ($request->status === 'pending') {
                $query->pending();
            } elseif ($request->status === 'settled') {
                $query->settled();
            }
        }

        // Filter by date
        if ($request->has('date') && $request->date === 'today') {
            $query->today();
        }

        // Pagination
        $trades = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 20));

        // Calculate summary for today
        $todayTrades = $user->trades()->today()->get();
        $summary = [
            'today_pl' => $todayTrades->sum('pnl'),
            'today_wins' => $todayTrades->where('result', 'WIN')->count(),
            'today_losses' => $todayTrades->where('result', 'LOSE')->count(),
            'today_trades' => $todayTrades->count(),
        ];

        return response()->json([
            'data' => $trades->items(),
            'summary' => $summary,
            'pagination' => [
                'current_page' => $trades->currentPage(),
                'last_page' => $trades->lastPage(),
                'per_page' => $trades->perPage(),
                'total' => $trades->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'pair_id' => 'required|exists:pairs,id',
            'timeframe_sec' => 'required|integer|min:5',
            'direction' => 'required|in:UP,DOWN',
            'amount' => 'required|numeric|min:1',
            'account_type' => 'required|in:DEMO,LIVE',
        ]);

        $user = $request->user();
        $pair = Pair::findOrFail($request->pair_id);

        // Check if pair is active
        if (!$pair->is_active) {
            return response()->json(['error' => 'Trading pair is not active'], 400);
        }

        // Check balance
        $balance = $request->account_type === 'DEMO' ? $user->demo_balance : $user->live_balance;
        if ($balance < $request->amount) {
            return response()->json(['error' => 'Insufficient balance'], 400);
        }

        DB::beginTransaction();
        try {
            // Deduct balance
            if ($request->account_type === 'DEMO') {
                $user->demo_balance -= $request->amount;
            } else {
                $user->live_balance -= $request->amount;
            }
            $user->save();

            // Get current price for entry
            $otcService = app(OtcPriceService::class);
            $currentPrice = $otcService->getOrSeedSpot($pair);
            
            // Create trade
            $trade = Trade::create([
                'user_id' => $user->id,
                'pair_id' => $pair->id,
                'pair_symbol' => $pair->symbol,
                'timeframe_sec' => $request->timeframe_sec,
                'direction' => $request->direction,
                'amount' => $request->amount,
                'entry_price' => $currentPrice,
                'expiry_at' => now()->addSeconds($request->timeframe_sec),
                'result' => 'PENDING',
                'account_type' => $request->account_type,
                'payout_rate' => 1.7, // 70% payout
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Trade placed successfully',
                'trade' => $trade->load('pair'),
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to place trade'], 500);
        }
    }

    public function show(Trade $trade): JsonResponse
    {
        // Ensure user can only see their own trades
        if ($trade->user_id !== auth()->id()) {
            return response()->json(['error' => 'Trade not found'], 404);
        }

        return response()->json([
            'data' => $trade->load('pair'),
        ]);
    }

    public function settle(Trade $trade): JsonResponse
    {
        // Ensure user can only settle their own trades
        if ($trade->user_id !== auth()->id()) {
            return response()->json(['error' => 'Trade not found'], 404);
        }

        if ($trade->result !== 'PENDING') {
            return response()->json(['error' => 'Trade already settled'], 400);
        }

        $tradeEngine = new TradeEngine(app(OtcPriceService::class));
        $tradeEngine->settle($trade);

        return response()->json([
            'message' => 'Trade settled',
            'trade' => $trade->load('pair'),
        ]);
    }
}
