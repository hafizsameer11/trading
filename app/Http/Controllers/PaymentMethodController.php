<?php

namespace App\Http\Controllers;

use App\Models\PaymentMethod;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PaymentMethodController extends Controller
{
    /**
     * Get active payment methods for users
     */
    public function index(Request $request): JsonResponse
    {
        $paymentMethods = PaymentMethod::active()
            ->select(['id', 'name', 'type', 'slug', 'details', 'min_amount', 'max_amount', 'fee_percentage', 'fee_fixed', 'instructions', 'processing_time_minutes'])
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $paymentMethods
        ]);
    }

    /**
     * Get a specific payment method details
     */
    public function show(PaymentMethod $paymentMethod): JsonResponse
    {
        if (!$paymentMethod->is_active) {
            return response()->json(['error' => 'Payment method not available'], 404);
        }

        return response()->json([
            'data' => $paymentMethod->only([
                'id', 'name', 'type', 'slug', 'details', 'min_amount', 'max_amount', 
                'fee_percentage', 'fee_fixed', 'instructions', 'processing_time_minutes'
            ])
        ]);
    }

    /**
     * Calculate fee for a payment method
     */
    public function calculateFee(Request $request, PaymentMethod $paymentMethod): JsonResponse
    {
        $request->validate([
            'amount' => 'required|numeric|min:0'
        ]);

        if (!$paymentMethod->is_active) {
            return response()->json(['error' => 'Payment method not available'], 404);
        }

        $amount = $request->amount;
        
        if (!$paymentMethod->isAmountValid($amount)) {
            return response()->json([
                'error' => 'Amount must be between ' . $paymentMethod->min_amount . ' and ' . $paymentMethod->max_amount
            ], 400);
        }

        $fee = $paymentMethod->calculateFee($amount);
        $total = $amount + $fee;

        return response()->json([
            'data' => [
                'amount' => $amount,
                'fee' => $fee,
                'total' => $total,
                'fee_breakdown' => [
                    'percentage_fee' => ($amount * $paymentMethod->fee_percentage) / 100,
                    'fixed_fee' => $paymentMethod->fee_fixed,
                    'fee_percentage' => $paymentMethod->fee_percentage,
                    'fee_fixed' => $paymentMethod->fee_fixed
                ]
            ]
        ]);
    }
}