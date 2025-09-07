<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pairs', function (Blueprint $table) {
            // Add new columns for market data generation
            $table->decimal('volatility_decimal', 8, 5)->default(0.35)->after('volatility'); // baseline seconds stdev
            $table->tinyInteger('trend_strength')->default(0)->after('trend_mode'); // 0-10
        });
    }

    public function down(): void
    {
        Schema::table('pairs', function (Blueprint $table) {
            $table->dropColumn(['volatility_decimal', 'trend_strength']);
        });
    }
};