<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Demo user
        User::create([
            'name' => 'Demo User',
            'email' => 'demo@profittrade.com',
            'password' => Hash::make('password'),
            'is_admin' => false,
            'demo_balance' => 10000.00,
            'live_balance' => 0.00,
        ]);

        // Admin user
        User::create([
            'name' => 'Admin User',
            'email' => 'admin@profittrade.com',
            'password' => Hash::make('password'),
            'is_admin' => true,
            'demo_balance' => 10000.00,
            'live_balance' => 0.00,
        ]);
    }
}
