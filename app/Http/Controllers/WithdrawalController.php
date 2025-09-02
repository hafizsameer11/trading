<?php

namespace App\Http\Controllers;

use App\Models\Withdrawal;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class WithdrawalController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $withdrawals = $user->withdrawals()
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'data' => $withdrawals->items(),
            'pagination' => [
                'current_page' => $withdrawals->currentPage(),
                'last_page' => $withdrawals->lastPage(),
                'per_page' => $withdrawals->perPage(),
                'total' => $withdrawals->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'method' => 'required|in:bank_transfer,crypto,paypal',
            'amount' => 'required|numeric|min:10',
            'account_type' => 'required|in:DEMO,LIVE',
            'account_details' => 'required|string|max:500',
        ]);

        $user = $request->user();
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

            $withdrawal = Withdrawal::create([
                'user_id' => $user->id,
                'method' => $request->method,
                'amount' => $request->amount,
                'status' => 'PENDING',
                'account_details' => $request->account_details,
                'account_type' => $request->account_type,
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Withdrawal request submitted successfully',
                'withdrawal' => $withdrawal,
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to process withdrawal'], 500);
        }
    }
}

