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
        Schema::table('number_system_game_sessions', function (Blueprint $table): void {
            $table->string('timer_mode', 12)->default('none');
            $table->unsignedInteger('countdown_seconds')->nullable();
            $table->boolean('ranking_enabled')->default(false);
            $table->unsignedInteger('active_seconds')->default(0);
            $table->timestamp('active_since')->nullable();
        });
        Schema::table('number_system_game_runs', function (Blueprint $table): void {
            $table->unsignedInteger('finished_seconds')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('number_system_game_runs', fn (Blueprint $table) => $table->dropColumn('finished_seconds'));
        Schema::table('number_system_game_sessions', fn (Blueprint $table) => $table->dropColumn([
            'timer_mode', 'countdown_seconds', 'ranking_enabled', 'active_seconds', 'active_since',
        ]));
    }
};
