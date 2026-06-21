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
        Schema::table('student_timetable_entries', function (Blueprint $table) {
            $table->index(
                ['school_id', 'schoolyear_id', 'is_active', 'date', 'period'],
                'student_tt_entries_course_group_lookup_idx',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('student_timetable_entries', function (Blueprint $table) {
            $table->dropIndex('student_tt_entries_course_group_lookup_idx');
        });
    }
};
