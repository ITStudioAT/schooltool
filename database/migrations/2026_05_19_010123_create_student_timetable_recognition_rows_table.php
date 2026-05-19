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
        Schema::create('student_timetable_recognition_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_timetable_recognition_import_id');
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('schoolyear_id')->constrained('schoolyears')->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->string('student')->nullable();
            $table->string('subject')->nullable();
            $table->string('grade')->nullable();
            $table->string('note')->nullable();
            $table->string('colloquia')->nullable();
            $table->string('module_repetitions')->nullable();
            $table->string('teacher_code')->nullable();
            $table->json('raw_data');
            $table->timestamps();

            $table->foreign('student_timetable_recognition_import_id', 'student_tt_recognition_rows_import_fk')
                ->references('id')
                ->on('student_timetable_recognition_imports')
                ->cascadeOnDelete();
            $table->index(['school_id', 'schoolyear_id'], 'student_tt_recognition_rows_schoolyear');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_timetable_recognition_rows');
    }
};
