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
        Schema::table('mc_attempts', function (Blueprint $table) {
            $table->timestamp('reopened_until')->nullable();
            $table->json('reopen_history')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mc_attempts', function (Blueprint $table) {
            $table->dropColumn(['reopened_until', 'reopen_history']);
        });
    }
};
