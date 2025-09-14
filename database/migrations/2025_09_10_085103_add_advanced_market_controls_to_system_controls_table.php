<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('system_controls', function (Blueprint $table) {
            // Advanced Market Simulation
            $table->boolean('support_resistance_enabled')->default(true)->after('trend_strength');
            $table->boolean('order_blocks_enabled')->default(true)->after('support_resistance_enabled');
            $table->boolean('fair_value_gaps_enabled')->default(true)->after('order_blocks_enabled');
            $table->decimal('fakeout_probability', 3, 2)->default(0.30)->after('fair_value_gaps_enabled');
            
            // Multi-timeframe settings
            $table->json('active_timeframes')->default('["5s","30s","1m","5m","15m","30m","1h","2h","4h"]')->after('fakeout_probability');
            
            // Advanced win rate controls
            $table->decimal('base_win_rate', 3, 2)->default(0.60)->after('active_timeframes');
            $table->decimal('stake_penalty_factor', 3, 2)->default(0.05)->after('base_win_rate');
            $table->decimal('daily_adjustment_factor', 3, 2)->default(0.20)->after('stake_penalty_factor');
            $table->boolean('manual_override_enabled')->default(true)->after('daily_adjustment_factor');
            
            // Chart behavior
            $table->boolean('smooth_candle_updates')->default(true)->after('manual_override_enabled');
            $table->integer('candle_animation_speed')->default(1000)->after('smooth_candle_updates');
        });
    }

    /**
     * Reverse the migrations.
     */
 
            // Chart behavior
   

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('system_controls', function (Blueprint $table) {
            $table->dropColumn([
                'support_resistance_enabled',
                'order_blocks_enabled',
                'fair_value_gaps_enabled',
                'fakeout_probability',
                'active_timeframes',
                'base_win_rate',
                'stake_penalty_factor',
                'daily_adjustment_factor',
                'manual_override_enabled',
                'smooth_candle_updates',
                'candle_animation_speed'
            ]);
        });
    }
};
   
 
