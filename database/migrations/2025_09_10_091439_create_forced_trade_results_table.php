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
        Schema::create('forced_trade_results', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('trade_id');
            $table->enum('forced_result', ['WIN', 'LOSS']);
            $table->text('reason')->nullable(); // Admin reason for forcing
            $table->unsignedBigInteger('admin_id'); // Who forced this result
            $table->boolean('is_applied')->default(false); // Whether it's been applied
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();

            $table->foreign('trade_id')->references('id')->on('trades')->onDelete('cascade');
            $table->foreign('admin_id')->references('id')->on('users')->onDelete('cascade');
            $table->unique('trade_id'); // One forced result per trade
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('forced_trade_results');
    }
};

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('forced_trade_results');
    }
};

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('forced_trade_results');
    }
};