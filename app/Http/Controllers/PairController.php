<?php

namespace App\Http\Controllers;

use App\Models\Pair;
use Illuminate\Http\Request;

class PairController extends Controller
{
    public function index()
    {
        $pairs = Pair::active()->get();
        
        return response()->json([
            'data' => $pairs->map(function ($pair) {
                return [
                    'id' => $pair->id,
                    'symbol' => $pair->symbol,
                    'slug' => $pair->slug,
                    'type' => $pair->type,
                    'base_currency' => $pair->base_currency,
                    'quote_currency' => $pair->quote_currency,
                    'price_precision' => $pair->price_precision,
                ];
            })
        ]);
    }
}
