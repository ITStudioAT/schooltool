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
        Schema::table('student_timetable_subject_imports', function (Blueprint $table) {
            $table->string('study_program', 32)->default('normalstudium')->after('schoolyear_id');
            $table->index(
                ['school_id', 'schoolyear_id', 'study_program', 'imported_at'],
                'student_tt_subject_imports_study_program_imported_at',
            );
        });

        Schema::table('student_timetable_subject_rows', function (Blueprint $table) {
            $table->string('study_program', 32)->default('normalstudium')->after('schoolyear_id');
            $table->index(
                ['school_id', 'schoolyear_id', 'study_program'],
                'student_tt_subject_rows_study_program',
            );
        });
    }
};
