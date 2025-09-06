<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Withdrawal;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AdminWithdrawalController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Withdrawal::with('user');

        // Search functionality
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('transaction_id', 'like', "%{$search}%")
                  ->orWhereHas('user', function ($userQuery) use ($search) {
                      $userQuery->where('name', 'like', "%{$search}%")
                               ->orWhere('email', 'like', "%{$search}%");
                  });
            });
        }

        // Filter by status
        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        $withdrawals = $query->orderBy('created_at', 'desc')->paginate(20);

        return response()->json($withdrawals);
    }

    public function show($id): JsonResponse
    {
        $withdrawal = Withdrawal::with('user')->findOrFail($id);
        return response()->json($withdrawal);
    }

    public function approve($id): JsonResponse
    {
        $withdrawal = Withdrawal::with('user')->findOrFail($id);
        
        if ($withdrawal->status !== 'PENDING') {
            return response()->json([
                'error' => 'Only pending withdrawals can be approved'
            ], 400);
        }

        // Check if user has sufficient balance
        $user = $withdrawal->user;
        if ($user->live_balance < $withdrawal->amount) {
            return response()->json([
                'error' => 'Insufficient user balance'
            ], 400);
        }

        // Update withdrawal status
        $withdrawal->update([
            'status' => 'APPROVED',
            'processed_at' => now()
        ]);

        // Deduct amount from user's live balance
        $user->decrement('live_balance', $withdrawal->amount);

        return response()->json([
            'message' => 'Withdrawal approved successfully',
            'withdrawal' => $withdrawal->fresh(['user'])
        ]);
    }

    public function reject($id): JsonResponse
    {
        $withdrawal = Withdrawal::with('user')->findOrFail($id);
        
        if ($withdrawal->status !== 'PENDING') {
            return response()->json([
                'error' => 'Only pending withdrawals can be rejected'
            ], 400);
        }

        $withdrawal->update([
            'status' => 'REJECTED',
            'processed_at' => now()
        ]);

        return response()->json([
            'message' => 'Withdrawal rejected successfully',
            'withdrawal' => $withdrawal->fresh(['user'])
        ]);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $withdrawal = Withdrawal::with('user')->findOrFail($id);
        
        $request->validate([
            'notes' => 'nullable|string|max:500',
            'amount' => 'nullable|numeric|min:0'
        ]);

        $updateData = [];
        if ($request->has('notes')) {
            $updateData['notes'] = $request->notes;
        }
        if ($request->has('amount')) {
            $updateData['amount'] = $request->amount;
        }

        $withdrawal->update($updateData);

        return response()->json([
            'message' => 'Withdrawal updated successfully',
            'withdrawal' => $withdrawal->fresh(['user'])
        ]);
    }
}