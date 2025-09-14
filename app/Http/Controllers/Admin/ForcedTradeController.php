<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ForcedTradeResult;
use App\Models\Trade;
use App\Services\LoggingService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ForcedTradeController extends Controller
{
    /**
     * Get all trades with their forced results status.
     */
    public function index(Request $request): JsonResponse
    {
        $trades = Trade::with(['user', 'pair', 'forcedResult.admin'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'data' => $trades->items(),
            'pagination' => [
                'current_page' => $trades->currentPage(),
                'last_page' => $trades->lastPage(),
                'per_page' => $trades->perPage(),
                'total' => $trades->total(),
            ]
        ]);
    }

    /**
     * Force a trade result.
     */
    public function forceResult(Request $request): JsonResponse
    {
        $request->validate([
            'trade_id' => 'required|exists:trades,id',
            'result' => 'required|in:WIN,LOSS',
            'reason' => 'nullable|string|max:500',
        ]);

        $trade = Trade::findOrFail($request->trade_id);

        // Check if trade is still pending
        if ($trade->result !== 'PENDING') {
            return response()->json([
                'error' => 'Trade has already been settled'
            ], 400);
        }

        // Check if already has forced result
        if (ForcedTradeResult::hasForcedResult($trade->id)) {
            return response()->json([
                'error' => 'Trade already has a forced result'
            ], 400);
        }

        // Create forced result
        $forcedResult = ForcedTradeResult::createForcedResult(
            $trade->id,
            $request->result,
            auth()->id(),
            $request->reason
        );

        // Log the action
        LoggingService::logAdminAction(
            'forced_trade_result',
            [
                'trade_id' => $trade->id,
                'forced_result' => $request->result,
                'reason' => $request->reason,
            ],
            request()
        );

        return response()->json([
            'message' => 'Trade result forced successfully',
            'data' => $forcedResult->load('admin')
        ]);
    }

    /**
     * Remove a forced trade result.
     */
    public function removeForcedResult(Request $request): JsonResponse
    {
        $request->validate([
            'trade_id' => 'required|exists:trades,id',
        ]);

        $forcedResult = ForcedTradeResult::where('trade_id', $request->trade_id)->first();

        if (!$forcedResult) {
            return response()->json([
                'error' => 'No forced result found for this trade'
            ], 404);
        }

        // Check if already applied
        if ($forcedResult->is_applied) {
            return response()->json([
                'error' => 'Cannot remove applied forced result'
            ], 400);
        }

        $forcedResult->delete();

        // Log the action
        LoggingService::logAdminAction(
            'removed_forced_trade_result',
            [
                'trade_id' => $request->trade_id,
            ],
            request()
        );

        return response()->json([
            'message' => 'Forced result removed successfully'
        ]);
    }

    /**
     * Get pending trades that can be forced.
     */
    public function getPendingTrades(): JsonResponse
    {
        $trades = Trade::with(['user', 'pair'])
            ->where('result', 'PENDING')
            ->whereDoesntHave('forcedResult')
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get();

        return response()->json([
            'data' => $trades
        ]);
    }

    /**
     * Get trades with forced results.
     */
    public function getForcedTrades(): JsonResponse
    {
        $trades = Trade::with(['user', 'pair', 'forcedResult.admin'])
            ->whereHas('forcedResult')
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get();

        return response()->json([
            'data' => $trades
        ]);
    }
}
