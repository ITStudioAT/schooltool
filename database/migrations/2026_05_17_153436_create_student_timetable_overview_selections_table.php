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
        Schema::create('student_timetable_overview_selections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('schoolyear_id')->constrained('schoolyears')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('course_group_key');
            $table->string('course_label')->nullable();
            $table->string('course_title')->nullable();
            $table->timestamps();

            $table->unique(
                ['school_id', 'schoolyear_id', 'user_id', 'course_group_key'],
                'student_tt_overview_selections_unique',
            );
            $table->index(['school_id', 'schoolyear_id', 'user_id'], 'student_tt_overview_selections_scope_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_timetable_overview_selections');
    }
};
