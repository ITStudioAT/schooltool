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
        Schema::create('number_system_game_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('teaching_course_id')->constrained()->cascadeOnDelete();
            $table->string('title', 120);
            $table->string('join_code', 10)->unique();
            $table->string('seed', 64);
            $table->unsignedTinyInteger('engine_version')->default(1);
            $table->unsignedTinyInteger('planned_minutes');
            $table->unsignedTinyInteger('difficulty');
            $table->string('mode', 8)->default('solo');
            $table->string('status', 16)->default('waiting');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();
            $table->index(['school_id', 'teacher_id']);
        });
        Schema::create('number_system_game_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('session_id')->constrained('number_system_game_sessions')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->default(0);
            $table->unsignedInteger('revision')->default(0);
            $table->string('team_name', 60)->nullable();
            $table->string('team_code', 8)->nullable()->unique();
            $table->json('puzzles');
            $table->json('draft')->nullable();
            $table->json('history')->nullable();
            $table->json('feedback')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->unsignedTinyInteger('hints')->default(0);
            $table->boolean('solved')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['session_id', 'user_id']);
            $table->unique(['session_id', 'team_name']);
        });
        Schema::create('number_system_game_members', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('session_id')->constrained('number_system_game_sessions')->cascadeOnDelete();
            $table->foreignId('run_id')->constrained('number_system_game_runs')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unique(['session_id', 'user_id']);
            $table->unique(['run_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('number_system_game_members');
        Schema::dropIfExists('number_system_game_runs');
        Schema::dropIfExists('number_system_game_sessions');
    }
};
