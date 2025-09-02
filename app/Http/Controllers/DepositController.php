<?php

namespace App\Http\Controllers;

use App\Models\Deposit;
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
            'method' => 'required|in:bank_transfer,crypto,paypal',
            'amount' => 'required|numeric|min:10',
            'proof' => 'required|file|mimes:jpg,jpeg,png,pdf|max:2048',
        ]);

        $user = $request->user();

        // Store proof file
        $proofPath = $request->file('proof')->store('deposits', 'public');

        $deposit = Deposit::create([
            'user_id' => $user->id,
            'method' => $request->method,
            'amount' => $request->amount,
            'status' => 'PENDING',
            'proof_url' => $proofPath,
        ]);

        return response()->json([
            'message' => 'Deposit request submitted successfully',
            'deposit' => $deposit,
        ], 201);
    }
}

