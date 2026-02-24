<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import116_runs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('school_id');
            $table->unsignedBigInteger('schoolyear_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('source_path')->nullable();
            $table->string('status')->default('running');
            $table->dateTime('started_at');
            $table->dateTime('finished_at')->nullable();
            $table->dateTime('undone_at')->nullable();
            $table->unsignedBigInteger('undone_by_user_id')->nullable();
            $table->json('counts')->nullable();
            $table->json('report_summary')->nullable();
            $table->json('report_paths')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'schoolyear_id']);
            $table->index(['school_id', 'schoolyear_id', 'status']);
            $table->index(['school_id', 'schoolyear_id', 'finished_at']);
        });

        Schema::create('import116_run_changes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('import116_run_id');
            $table->unsignedBigInteger('school_id');
            $table->unsignedBigInteger('schoolyear_id')->nullable();
            $table->string('student_code');
            $table->string('change_type');
            $table->json('before_snapshot')->nullable();
            $table->json('after_snapshot')->nullable();
            $table->json('summary')->nullable();
            $table->timestamps();

            $table->index(['import116_run_id', 'change_type']);
            $table->index(['school_id', 'schoolyear_id', 'student_code']);
            $table->foreign('import116_run_id')
                ->references('id')
                ->on('import116_runs')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import116_run_changes');
        Schema::dropIfExists('import116_runs');
    }
};
