<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop existing candles table if it exists
        Schema::dropIfExists('candles');
        
        Schema::create('candles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pair_id');
            $table->unsignedInteger('timeframe_sec'); // 60, 120, 300, etc.
            $table->unsignedInteger('timestamp');     // seconds UTC, start of bucket
            $table->decimal('open', 18, 8);
            $table->decimal('high', 18, 8);
            $table->decimal('low', 18, 8);
            $table->decimal('close', 18, 8);
            $table->decimal('volume', 24, 8)->default(0);
            $table->timestamps();
            
            // Unique constraint to prevent duplicates
            $table->unique(['pair_id', 'timeframe_sec', 'timestamp']);
            
            // Index for fast queries
            $table->index(['pair_id', 'timeframe_sec', 'timestamp']);
            
            // Foreign key constraint
            $table->foreign('pair_id')->references('id')->on('pairs')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candles');
    }
};