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
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // e.g., "Binance", "PayPal", "Bank Transfer"
            $table->string('type'); // e.g., "crypto", "bank", "digital_wallet"
            $table->string('slug')->unique(); // e.g., "binance", "paypal"
            $table->json('details'); // Payment method specific details
            $table->boolean('is_active')->default(true);
            $table->decimal('min_amount', 10, 2)->default(10.00);
            $table->decimal('max_amount', 10, 2)->default(10000.00);
            $table->decimal('fee_percentage', 5, 2)->default(0.00);
            $table->decimal('fee_fixed', 10, 2)->default(0.00);
            $table->text('instructions')->nullable(); // Instructions for users
            $table->json('required_fields')->nullable(); // Fields required for this payment method
            $table->integer('processing_time_minutes')->default(15); // Expected processing time
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_methods');
    }
};