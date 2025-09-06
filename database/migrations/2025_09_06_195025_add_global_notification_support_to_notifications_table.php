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
        Schema::table('notifications', function (Blueprint $table) {
            $table->boolean('is_global')->default(false)->after('user_id');
            $table->unsignedBigInteger('created_by')->nullable()->after('is_global');
            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium')->after('created_by');
            $table->timestamp('expires_at')->nullable()->after('priority');
            
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropColumn(['is_global', 'created_by', 'priority', 'expires_at']);
        });
    }
};