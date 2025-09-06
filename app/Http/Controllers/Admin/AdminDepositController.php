<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Deposit;
use App\Models\User;
use App\Services\LoggingService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AdminDepositController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Deposit::with('user');

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

        $deposits = $query->orderBy('created_at', 'desc')->paginate(20);

        return response()->json($deposits);
    }

    public function show($id): JsonResponse
    {
        $deposit = Deposit::with('user')->findOrFail($id);
        return response()->json($deposit);
    }

    public function approve($id): JsonResponse
    {
        $deposit = Deposit::with('user')->findOrFail($id);
        
        if ($deposit->status !== 'PENDING') {
            return response()->json([
                'error' => 'Only pending deposits can be approved'
            ], 400);
        }

        // Update deposit status
        $deposit->update([
            'status' => 'APPROVED',
            'processed_at' => now()
        ]);

        // Add amount to user's live balance
        $user = $deposit->user;
        $user->increment('live_balance', $deposit->amount);

        // Log the approval
        LoggingService::logDepositApproved($deposit, request());

        return response()->json([
            'message' => 'Deposit approved successfully',
            'deposit' => $deposit->fresh(['user'])
        ]);
    }

    public function reject($id): JsonResponse
    {
        $deposit = Deposit::with('user')->findOrFail($id);
        
        if ($deposit->status !== 'PENDING') {
            return response()->json([
                'error' => 'Only pending deposits can be rejected'
            ], 400);
        }

        $deposit->update([
            'status' => 'REJECTED',
            'processed_at' => now()
        ]);

        // Log the rejection
        LoggingService::logDepositRejected($deposit, request());

        return response()->json([
            'message' => 'Deposit rejected successfully',
            'deposit' => $deposit->fresh(['user'])
        ]);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $deposit = Deposit::with('user')->findOrFail($id);
        
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

        $deposit->update($updateData);

        return response()->json([
            'message' => 'Deposit updated successfully',
            'deposit' => $deposit->fresh(['user'])
        ]);
    }
}