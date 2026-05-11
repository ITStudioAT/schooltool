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
        if (Schema::hasTable('student_timetable_entries')) {
            return;
        }

        Schema::create('student_timetable_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('schoolyear_id')->constrained('schoolyears')->cascadeOnDelete();
            $table->foreignId('timetable_import_id')->constrained('timetable_imports')->cascadeOnDelete();
            $table->unsignedInteger('line_number');
            $table->date('date')->nullable();
            $table->unsignedTinyInteger('semester')->nullable();
            $table->string('source_identifier')->nullable();
            $table->string('period')->nullable();
            $table->string('subject')->nullable();
            $table->string('teacher')->nullable();
            $table->string('room')->nullable();
            $table->string('class_name')->nullable();
            $table->string('course')->nullable();
            $table->string('student_group')->nullable();
            $table->json('raw_columns');
            $table->text('raw_line');
            $table->timestamps();

            $table->index(['school_id', 'schoolyear_id', 'semester'], 'student_tt_entries_school_year_sem_idx');
            $table->index(['school_id', 'schoolyear_id', 'date'], 'student_tt_entries_school_year_date_idx');
            $table->index(['timetable_import_id', 'line_number'], 'student_tt_entries_import_line_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_timetable_entries');
    }
};
