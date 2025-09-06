<?php

namespace App\Http\Controllers;

use App\Models\Deposit;
use App\Models\PaymentMethod;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class DepositController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $deposits = $user->deposits()
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'data' => $deposits->items(),
            'pagination' => [
                'current_page' => $deposits->currentPage(),
                'last_page' => $deposits->lastPage(),
                'per_page' => $deposits->perPage(),
                'total' => $deposits->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'payment_method_id' => 'required|exists:payment_methods,id',
            'amount' => 'required|numeric|min:0',
            'transaction_id' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:1000',
            'proof' => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120', // 5MB max
        ]);

        $user = $request->user();
        $paymentMethod = PaymentMethod::findOrFail($request->payment_method_id);

        // Validate amount against payment method limits
        if (!$paymentMethod->isAmountValid($request->amount)) {
            return response()->json([
                'error' => 'Amount must be between ' . $paymentMethod->min_amount . ' and ' . $paymentMethod->max_amount
            ], 400);
        }

        // Store proof file
        $proofPath = $request->file('proof')->store('deposits', 'public');

        $deposit = Deposit::create([
            'user_id' => $user->id,
            'payment_method_id' => $paymentMethod->id,
            'method' => $paymentMethod->name,
            'amount' => $request->amount,
            'status' => 'pending',
            'proof_url' => $proofPath,
            'transaction_id' => $request->transaction_id,
            'notes' => $request->notes,
        ]);

        return response()->json([
            'message' => 'Deposit request submitted successfully',
            'data' => $deposit->load('paymentMethod'),
        ], 201);
    }
}


