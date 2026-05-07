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
        Schema::table('teaching_courses', function (Blueprint $table) {
            $table->index(['school_id', 'schoolyear_id', 'title'], 'teaching_courses_schoolyear_title_idx');
            $table->index(['school_id', 'schoolyear_id', 'user_id', 'title'], 'teaching_courses_teacher_title_idx');
        });

        Schema::table('teaching_curricula', function (Blueprint $table) {
            $table->index(['school_id', 'user_id', 'updated_at'], 'teaching_curricula_owner_updated_idx');
        });

        Schema::table('import116', function (Blueprint $table) {
            $table->index(['school_id', 'schoolyear_id', 'class'], 'import116_schoolyear_class_idx');
        });

        Schema::table('teaching_course_student_entries', function (Blueprint $table) {
            $table->index(['teaching_course_id', 'user_id'], 'tc_student_entries_course_user_idx');
        });

        Schema::table('teaching_course_behaviour_entries', function (Blueprint $table) {
            $table->index(['teaching_course_id', 'user_id'], 'tc_behaviour_entries_course_user_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('teaching_course_behaviour_entries', function (Blueprint $table) {
            $table->dropIndex('tc_behaviour_entries_course_user_idx');
        });

        Schema::table('teaching_course_student_entries', function (Blueprint $table) {
            $table->dropIndex('tc_student_entries_course_user_idx');
        });

        Schema::table('import116', function (Blueprint $table) {
            $table->dropIndex('import116_schoolyear_class_idx');
        });

        Schema::table('teaching_curricula', function (Blueprint $table) {
            $table->dropIndex('teaching_curricula_owner_updated_idx');
        });

        Schema::table('teaching_courses', function (Blueprint $table) {
            $table->dropIndex('teaching_courses_teacher_title_idx');
            $table->dropIndex('teaching_courses_schoolyear_title_idx');
        });
    }
};
