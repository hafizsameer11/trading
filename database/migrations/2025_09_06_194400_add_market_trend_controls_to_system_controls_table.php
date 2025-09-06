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
            $table->enum('morning_trend', ['UP', 'DOWN', 'SIDEWAYS'])->default('SIDEWAYS')->after('otc_tick_ms');
            $table->enum('afternoon_trend', ['UP', 'DOWN', 'SIDEWAYS'])->default('SIDEWAYS')->after('morning_trend');
            $table->enum('evening_trend', ['UP', 'DOWN', 'SIDEWAYS'])->default('SIDEWAYS')->after('afternoon_trend');
            $table->time('morning_start')->default('09:00:00')->after('evening_trend');
            $table->time('morning_end')->default('12:00:00')->after('morning_start');
            $table->time('afternoon_start')->default('12:00:00')->after('morning_end');
            $table->time('afternoon_end')->default('17:00:00')->after('afternoon_start');
            $table->time('evening_start')->default('17:00:00')->after('afternoon_end');
            $table->time('evening_end')->default('21:00:00')->after('evening_start');
            $table->decimal('trend_strength', 3, 1)->default(5.0)->after('evening_end')->comment('Trend strength from 1.0 to 10.0');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('system_controls', function (Blueprint $table) {
            $table->dropColumn([
                'morning_trend',
                'afternoon_trend', 
                'evening_trend',
                'morning_start',
                'morning_end',
                'afternoon_start',
                'afternoon_end',
                'evening_start',
                'evening_end',
                'trend_strength'
            ]);
        });
    }
};