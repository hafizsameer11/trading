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
        Schema::create('market_data', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pair_id')->constrained('pairs')->onDelete('cascade');
            $table->string('pair_symbol');
            $table->integer('timeframe_sec'); // 60, 300, 900, etc.
            $table->bigInteger('timestamp'); // Unix timestamp
            $table->decimal('open', 20, 8);
            $table->decimal('high', 20, 8);
            $table->decimal('low', 20, 8);
            $table->decimal('close', 20, 8);
            $table->decimal('volume', 20, 8)->default(0);
            $table->timestamps();
            
            // Indexes for fast queries
            $table->index(['pair_id', 'timeframe_sec', 'timestamp']);
            $table->unique(['pair_id', 'timeframe_sec', 'timestamp']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('market_data');
    }
};
