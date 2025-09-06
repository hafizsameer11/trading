<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Pair;
use Illuminate\Http\Request;

class AdminPairController extends Controller
{
    public function index(Request $request)
    {
        $query = Pair::query();

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        if ($request->has('active')) {
            $query->where('is_active', $request->boolean('active'));
        }

        $pairs = $query->paginate(50);

        return response()->json([
            'data' => $pairs->items(),
            'pagination' => [
                'current_page' => $pairs->currentPage(),
                'last_page' => $pairs->lastPage(),
                'per_page' => $pairs->perPage(),
                'total' => $pairs->total(),
            ]
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'symbol' => 'required|string|max:20',
            'type' => 'required|in:LIVE,OTC',
            'base_currency' => 'nullable|string|max:10',
            'quote_currency' => 'nullable|string|max:10',
            'price_precision' => 'integer|min:0|max:8',
            'meta' => 'nullable|array',
        ]);

        $slug = str_replace('/', '-', $request->symbol);
        if ($request->type === 'OTC') {
            $slug .= '-OTC';
        }

        $pair = Pair::create([
            'symbol' => $request->symbol,
            'slug' => $slug,
            'type' => $request->type,
            'base_currency' => $request->base_currency,
            'quote_currency' => $request->quote_currency,
            'price_precision' => $request->price_precision ?? 5,
            'meta' => $request->meta,
        ]);

        return response()->json([
            'message' => 'Pair created successfully',
            'data' => $pair
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $pair = Pair::findOrFail($id);
        
        $request->validate([
            'is_active' => 'boolean',
            'trend_mode' => 'in:UP,DOWN,SIDEWAYS',
            'volatility' => 'in:LOW,MID,HIGH',
            'min_price' => 'nullable|numeric|min:0',
            'max_price' => 'nullable|numeric|min:0',
        ]);

        $pair->update($request->only([
            'is_active',
            'trend_mode',
            'volatility',
            'min_price',
            'max_price',
        ]));

        return response()->json([
            'message' => 'Pair updated successfully',
            'data' => $pair
        ]);
    }
}
