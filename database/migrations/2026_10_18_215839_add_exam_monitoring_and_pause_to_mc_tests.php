<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mc_tests', function (Blueprint $table): void {
            $table->boolean('exam_mode')->default(false);
            $table->timestamp('paused_at')->nullable();
            $table->json('pause_history')->nullable();
        });
        Schema::table('mc_attempts', function (Blueprint $table): void {
            $table->unsignedInteger('pause_seconds')->default(0);
            $table->json('activity_state')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('mc_attempts', fn (Blueprint $table) => $table->dropColumn(['pause_seconds', 'activity_state']));
        Schema::table('mc_tests', fn (Blueprint $table) => $table->dropColumn(['exam_mode', 'paused_at', 'pause_history']));
    }
};
