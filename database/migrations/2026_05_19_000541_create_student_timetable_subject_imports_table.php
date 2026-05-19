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
        Schema::create('student_timetable_subject_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('schoolyear_id')->constrained('schoolyears')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('original_filename');
            $table->string('stored_filename');
            $table->string('file_path');
            $table->unsignedInteger('file_size')->default(0);
            $table->json('analysis')->nullable();
            $table->unsignedInteger('subjects_total')->default(0);
            $table->unsignedInteger('subject_rows_total')->default(0);
            $table->unsignedInteger('semesters_total')->default(0);
            $table->unsignedInteger('branches_total')->default(0);
            $table->timestamp('imported_at')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'schoolyear_id', 'imported_at'], 'student_tt_subject_imports_schoolyear_imported_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_timetable_subject_imports');
    }
};
