<?php

namespace Database\Seeders;

use App\Models\SystemControl;
use Illuminate\Database\Seeder;

class SystemControlSeeder extends Seeder
{
    public function run(): void
    {
        SystemControl::create([
            'id' => 1,
            'daily_win_percent' => 50.00,
            'otc_tick_ms' => 1000,
        ]);
    }
}
