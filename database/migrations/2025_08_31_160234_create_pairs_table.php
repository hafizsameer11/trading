<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pairs', function (Blueprint $table) {
            $table->id();
            $table->string('symbol');
            $table->string('slug')->unique();
            $table->enum('type', ['LIVE', 'OTC']);
            $table->boolean('is_active')->default(true);
            $table->string('base_currency')->nullable();
            $table->string('quote_currency')->nullable();
            $table->enum('trend_mode', ['UP', 'DOWN', 'SIDEWAYS'])->default('SIDEWAYS');
            $table->enum('volatility', ['LOW', 'MID', 'HIGH'])->default('MID');
            $table->decimal('min_price', 20, 8)->nullable();
            $table->decimal('max_price', 20, 8)->nullable();
            $table->tinyInteger('price_precision')->default(5);
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pairs');
    }
};
