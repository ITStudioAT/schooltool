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
            $table->string('audience', 16)->default('course');
            $table->unsignedBigInteger('teaching_course_id')->nullable()->change();
        });
        Schema::table('number_system_game_runs', function (Blueprint $table): void {
            $table->unsignedBigInteger('user_id')->nullable()->change();
        });
        Schema::create('number_system_game_guests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('session_id')->constrained('number_system_game_sessions')->cascadeOnDelete();
            $table->foreignId('run_id')->nullable()->constrained('number_system_game_runs')->cascadeOnDelete();
            $table->string('first_name', 60);
            $table->string('token_hash', 64);
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
            $table->unique(['session_id', 'token_hash']);
            $table->index('token_hash');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        throw new RuntimeException('Gastspielstände können nicht verlustfrei zurückgerollt werden. Verwende eine vorwärtsgerichtete Migration.');
    }
};
