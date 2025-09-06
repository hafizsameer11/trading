<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\PaymentMethod;

class PaymentMethodSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        PaymentMethod::create([
            'name' => 'Binance',
            'type' => 'crypto',
            'slug' => 'binance',
            'details' => [
                'binance_id' => '1116347904',
                'name' => 'ProfitTrade',
                'network' => 'TRC20',
                'wallet_address' => 'TExample1234567890abcdefghijklmnopqrstuvwxyz',
                'qr_code' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='
            ],
            'is_active' => true,
            'min_amount' => 10.00,
            'max_amount' => 10000.00,
            'fee_percentage' => 0.00,
            'fee_fixed' => 0.00,
            'instructions' => 'Send USDT (TRC20) to the provided wallet address. Make sure to use TRC20 network only. Include your transaction ID in the deposit form.',
            'required_fields' => ['transaction_id'],
            'processing_time_minutes' => 15,
        ]);
    }
}