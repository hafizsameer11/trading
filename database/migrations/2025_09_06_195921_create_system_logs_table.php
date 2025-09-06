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
        Schema::create('system_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('action'); // e.g., 'user_created', 'deposit_approved', 'system_settings_updated'
            $table->string('model_type')->nullable(); // e.g., 'User', 'Deposit', 'SystemControl'
            $table->unsignedBigInteger('model_id')->nullable(); // ID of the affected model
            $table->text('description');
            $table->json('old_data')->nullable(); // Previous state of the data
            $table->json('new_data')->nullable(); // New state of the data
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->enum('level', ['info', 'warning', 'error', 'critical'])->default('info');
            $table->json('meta')->nullable(); // Additional metadata
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            $table->index(['action', 'created_at']);
            $table->index(['model_type', 'model_id']);
            $table->index(['user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_logs');
    }
};