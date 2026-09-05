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
        Schema::table('teaching_course_behaviour_entries', function (Blueprint $table) {
            $table->boolean('remind_student_by_email')->default(false);
            $table->boolean('remind_teacher_by_email')->default(true);
            $table->timestamp('student_reminder_email_sent_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('teaching_course_behaviour_entries', function (Blueprint $table) {
            $table->dropColumn(['remind_student_by_email', 'remind_teacher_by_email', 'student_reminder_email_sent_at']);
        });
    }
};
