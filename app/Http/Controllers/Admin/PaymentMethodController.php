<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentMethod;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

class PaymentMethodController extends Controller
{
    /**
     * Get all payment methods
     */
    public function index(Request $request): JsonResponse
    {
        $query = PaymentMethod::query();

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        if ($request->has('active')) {
            $query->where('is_active', $request->boolean('active'));
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        $paymentMethods = $query->orderBy('name')->get();

        return response()->json([
            'data' => $paymentMethods
        ]);
    }

    /**
     * Get a specific payment method
     */
    public function show($id): JsonResponse
    {
        $paymentMethod = PaymentMethod::findOrFail($id);
        return response()->json([
            'data' => $paymentMethod
        ]);
    }

    /**
     * Create a new payment method
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'type' => ['required', Rule::in(['crypto', 'bank', 'digital_wallet'])],
            'slug' => 'required|string|max:255|unique:payment_methods',
            'details' => 'required|array',
            'is_active' => 'boolean',
            'min_amount' => 'required|numeric|min:0',
            'max_amount' => 'required|numeric|min:0|gte:min_amount',
            'fee_percentage' => 'required|numeric|min:0|max:100',
            'fee_fixed' => 'required|numeric|min:0',
            'instructions' => 'nullable|string',
            'required_fields' => 'nullable|array',
            'processing_time_minutes' => 'required|integer|min:1',
        ]);

        $paymentMethod = PaymentMethod::create($request->all());

        return response()->json([
            'message' => 'Payment method created successfully',
            'data' => $paymentMethod
        ], 201);
    }

    /**
     * Update a payment method
     */
    public function update(Request $request, $id): JsonResponse
    {
        $paymentMethod = PaymentMethod::findOrFail($id);
        
        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'type' => ['sometimes', Rule::in(['crypto', 'bank', 'digital_wallet'])],
            'slug' => ['sometimes', 'string', 'max:255', Rule::unique('payment_methods')->ignore($paymentMethod->id)],
            'details' => 'sometimes|required|array',
            'is_active' => 'boolean',
            'min_amount' => 'sometimes|required|numeric|min:0',
            'max_amount' => 'sometimes|required|numeric|min:0|gte:min_amount',
            'fee_percentage' => 'sometimes|required|numeric|min:0|max:100',
            'fee_fixed' => 'sometimes|required|numeric|min:0',
            'instructions' => 'nullable|string',
            'required_fields' => 'nullable|array',
            'processing_time_minutes' => 'sometimes|required|integer|min:1',
        ]);

        $paymentMethod->update($request->all());

        return response()->json([
            'message' => 'Payment method updated successfully',
            'data' => $paymentMethod
        ]);
    }

    /**
     * Delete a payment method
     */
    public function destroy($id): JsonResponse
    {
        $paymentMethod = PaymentMethod::findOrFail($id);
        
        // Check if payment method has deposits
        if ($paymentMethod->deposits()->count() > 0) {
            return response()->json([
                'error' => 'Cannot delete payment method with existing deposits'
            ], 400);
        }

        $paymentMethod->delete();

        return response()->json([
            'message' => 'Payment method deleted successfully'
        ]);
    }

    /**
     * Toggle payment method status
     */
    public function toggleStatus($id): JsonResponse
    {
        $paymentMethod = PaymentMethod::findOrFail($id);
        $paymentMethod->update(['is_active' => !$paymentMethod->is_active]);

        return response()->json([
            'message' => 'Payment method status updated successfully',
            'data' => $paymentMethod
        ]);
    }
}