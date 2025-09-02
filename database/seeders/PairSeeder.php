<?php

namespace Database\Seeders;

use App\Models\Pair;
use Illuminate\Database\Seeder;

class PairSeeder extends Seeder
{
    public function run(): void
    {
        // LIVE Metals
        $liveMetals = [
            ['symbol' => 'XAU/USD', 'slug' => 'XAU-USD', 'base_currency' => 'XAU', 'quote_currency' => 'USD', 'meta' => ['anchor' => 2000.00]],
            ['symbol' => 'XAG/USD', 'slug' => 'XAG-USD', 'base_currency' => 'XAG', 'quote_currency' => 'USD', 'meta' => ['anchor' => 25.00]],
        ];

        // LIVE Forex Majors & Minors
        $liveForex = [
            ['symbol' => 'EUR/USD', 'slug' => 'EUR-USD', 'base_currency' => 'EUR', 'quote_currency' => 'USD', 'meta' => ['anchor' => 1.1000]],
            ['symbol' => 'GBP/USD', 'slug' => 'GBP-USD', 'base_currency' => 'GBP', 'quote_currency' => 'USD', 'meta' => ['anchor' => 1.2500]],
            ['symbol' => 'USD/JPY', 'slug' => 'USD-JPY', 'base_currency' => 'USD', 'quote_currency' => 'JPY', 'meta' => ['anchor' => 150.00]],
            ['symbol' => 'USD/CHF', 'slug' => 'USD-CHF', 'base_currency' => 'USD', 'quote_currency' => 'CHF', 'meta' => ['anchor' => 0.9000]],
            ['symbol' => 'AUD/USD', 'slug' => 'AUD-USD', 'base_currency' => 'AUD', 'quote_currency' => 'USD', 'meta' => ['anchor' => 0.6500]],
            ['symbol' => 'NZD/USD', 'slug' => 'NZD-USD', 'base_currency' => 'NZD', 'quote_currency' => 'USD', 'meta' => ['anchor' => 0.6000]],
            ['symbol' => 'USD/CAD', 'slug' => 'USD-CAD', 'base_currency' => 'USD', 'quote_currency' => 'CAD', 'meta' => ['anchor' => 1.3500]],
            ['symbol' => 'EUR/GBP', 'slug' => 'EUR-GBP', 'base_currency' => 'EUR', 'quote_currency' => 'GBP', 'meta' => ['anchor' => 0.8800]],
            ['symbol' => 'EUR/JPY', 'slug' => 'EUR-JPY', 'base_currency' => 'EUR', 'quote_currency' => 'JPY', 'meta' => ['anchor' => 165.00]],
            ['symbol' => 'GBP/JPY', 'slug' => 'GBP-JPY', 'base_currency' => 'GBP', 'quote_currency' => 'JPY', 'meta' => ['anchor' => 187.50]],
            ['symbol' => 'GBP/CHF', 'slug' => 'GBP-CHF', 'base_currency' => 'GBP', 'quote_currency' => 'CHF', 'meta' => ['anchor' => 1.1250]],
            ['symbol' => 'EUR/CHF', 'slug' => 'EUR-CHF', 'base_currency' => 'EUR', 'quote_currency' => 'CHF', 'meta' => ['anchor' => 0.9900]],
            ['symbol' => 'AUD/JPY', 'slug' => 'AUD-JPY', 'base_currency' => 'AUD', 'quote_currency' => 'JPY', 'meta' => ['anchor' => 97.50]],
            ['symbol' => 'NZD/JPY', 'slug' => 'NZD-JPY', 'base_currency' => 'NZD', 'quote_currency' => 'JPY', 'meta' => ['anchor' => 90.00]],
            ['symbol' => 'CAD/JPY', 'slug' => 'CAD-JPY', 'base_currency' => 'CAD', 'quote_currency' => 'JPY', 'meta' => ['anchor' => 111.11]],
            ['symbol' => 'USD/TRY', 'slug' => 'USD-TRY', 'base_currency' => 'USD', 'quote_currency' => 'TRY', 'meta' => ['anchor' => 30.00]],
            ['symbol' => 'USD/INR', 'slug' => 'USD-INR', 'base_currency' => 'USD', 'quote_currency' => 'INR', 'meta' => ['anchor' => 83.00]],
            ['symbol' => 'USD/PKR', 'slug' => 'USD-PKR', 'base_currency' => 'USD', 'quote_currency' => 'PKR', 'meta' => ['anchor' => 280.00]],
            ['symbol' => 'USD/ZAR', 'slug' => 'USD-ZAR', 'base_currency' => 'USD', 'quote_currency' => 'ZAR', 'meta' => ['anchor' => 18.50]],
            ['symbol' => 'EUR/TRY', 'slug' => 'EUR-TRY', 'base_currency' => 'EUR', 'quote_currency' => 'TRY', 'meta' => ['anchor' => 33.00]],
            ['symbol' => 'USD/RUB', 'slug' => 'USD-RUB', 'base_currency' => 'USD', 'quote_currency' => 'RUB', 'meta' => ['anchor' => 95.00]],
            ['symbol' => 'USD/CNH', 'slug' => 'USD-CNH', 'base_currency' => 'USD', 'quote_currency' => 'CNH', 'meta' => ['anchor' => 7.25]],
        ];

        // Create LIVE pairs
        foreach ($liveMetals as $metal) {
            Pair::create(array_merge($metal, [
                'type' => 'LIVE',
                'is_active' => true,
                'trend_mode' => 'SIDEWAYS',
                'volatility' => 'MID',
                'price_precision' => 2,
            ]));
        }

        foreach ($liveForex as $forex) {
            Pair::create(array_merge($forex, [
                'type' => 'LIVE',
                'is_active' => true,
                'trend_mode' => 'SIDEWAYS',
                'volatility' => 'MID',
                'price_precision' => 5,
            ]));
        }

        // Create OTC pairs (same as LIVE but with " OTC" suffix)
        $otcPairs = array_merge($liveMetals, $liveForex);
        foreach ($otcPairs as $pair) {
            Pair::create([
                'symbol' => $pair['symbol'] . ' OTC',
                'slug' => $pair['slug'] . '-OTC',
                'type' => 'OTC',
                'is_active' => true,
                'base_currency' => $pair['base_currency'],
                'quote_currency' => $pair['quote_currency'],
                'trend_mode' => 'SIDEWAYS',
                'volatility' => 'MID',
                'price_precision' => $pair['symbol'] === 'XAU/USD' || $pair['symbol'] === 'XAG/USD' ? 2 : 5,
                'meta' => $pair['meta'],
            ]);
        }
    }
}
