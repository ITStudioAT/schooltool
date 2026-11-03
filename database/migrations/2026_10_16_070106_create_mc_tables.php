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
        Schema::create('mc_catalogs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained();
            $table->foreignId('owner_id')->constrained('users');
            $table->string('title');
            $table->json('questions');
            $table->timestamps();
            $table->index(['school_id', 'owner_id']);
        });

        Schema::create('mc_tests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained();
            $table->foreignId('owner_id')->constrained('users');
            $table->foreignId('catalog_id')->constrained('mc_catalogs');
            $table->string('title');
            $table->string('status')->default('draft');
            $table->unsignedInteger('question_count');
            $table->decimal('total_points', 10, 2);
            $table->unsignedInteger('duration_seconds');
            $table->unsignedInteger('lead_seconds')->default(30);
            $table->boolean('balance_types')->default(true);
            $table->boolean('shuffle_questions')->default(true);
            $table->boolean('shuffle_options')->default(true);
            $table->json('snapshot')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('results_released_at')->nullable();
            $table->timestamp('solutions_released_at')->nullable();
            $table->timestamps();
            $table->index(['school_id', 'owner_id']);
            $table->index(['status', 'ends_at']);
        });

        Schema::create('mc_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('test_id')->constrained('mc_tests');
            $table->foreignId('student_id')->constrained('users');
            $table->string('student_name');
            $table->string('email');
            $table->json('questions');
            $table->json('answers');
            $table->timestamp('ready_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('completion_reason')->nullable();
            $table->unsignedInteger('correct_count')->nullable();
            $table->decimal('score', 10, 2)->nullable();
            $table->decimal('percentage', 6, 2)->nullable();
            $table->char('access_token_hash', 64)->unique();
            $table->text('access_token');
            $table->timestamp('invited_at')->nullable();
            $table->timestamps();
            $table->unique(['test_id', 'student_id']);
            $table->index(['test_id', 'completed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mc_attempts');
        Schema::dropIfExists('mc_tests');
        Schema::dropIfExists('mc_catalogs');
    }
};
