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
        Schema::create('student_timetable_data_refreshes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('schoolyear_id')->constrained('schoolyears')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 20)->default('queued');
            $table->unsignedInteger('total_students')->default(0);
            $table->unsignedInteger('processed_students')->default(0);
            $table->unsignedInteger('study_selections_updated')->default(0);
            $table->unsignedInteger('course_results_updated')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(
                ['school_id', 'schoolyear_id', 'created_at'],
                'student_data_refresh_scope_created_idx',
            );
            $table->index(
                ['school_id', 'schoolyear_id', 'status'],
                'student_data_refresh_scope_status_idx',
            );
        });
    }
};
