<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_controls', function (Blueprint $table) {
            $table->id();
            $table->decimal('daily_win_percent', 5, 2)->default(50.00);
            $table->integer('otc_tick_ms')->default(1000);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_controls');
    }
};
