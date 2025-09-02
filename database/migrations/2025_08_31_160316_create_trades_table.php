<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('pair_id')->constrained()->onDelete('cascade');
            $table->string('pair_symbol');
            $table->integer('timeframe_sec');
            $table->enum('direction', ['UP', 'DOWN']);
            $table->decimal('amount', 16, 2);
            $table->decimal('entry_price', 20, 8);
            $table->datetime('expiry_at');
            $table->enum('result', ['PENDING', 'WIN', 'LOSE', 'TIE'])->default('PENDING');
            $table->datetime('settled_at')->nullable();
            $table->decimal('payout_rate', 5, 2)->default(70.00);
            $table->enum('account_type', ['DEMO', 'LIVE'])->default('DEMO');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trades');
    }
};
