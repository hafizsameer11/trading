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
        Schema::create('candles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pair_id');
            $table->string('timeframe'); // 5s, 10s, 15s, 30s, 1m, 2m, 5m, 10m, 15m, 30m, 1h, 2h, 4h
            $table->decimal('open', 20, 8);
            $table->decimal('high', 20, 8);
            $table->decimal('low', 20, 8);
            $table->decimal('close', 20, 8);
            $table->decimal('volume', 20, 8);
            $table->timestamp('timestamp');
            $table->timestamps();

            // Indexes for performance
            $table->index(['pair_id', 'timeframe', 'timestamp']);
            $table->index(['pair_id', 'timestamp']);
            $table->index('timestamp');
            
            // Foreign key
            $table->foreign('pair_id')->references('id')->on('pairs')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('candles');
    }
};