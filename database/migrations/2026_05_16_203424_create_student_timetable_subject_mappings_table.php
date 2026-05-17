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
        Schema::create('student_timetable_subject_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('schoolyear_id')->constrained('schoolyears')->cascadeOnDelete();
            $table->string('json_subject');
            $table->string('tt_subject');
            $table->string('note')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('source')->default('manual');
            $table->timestamps();

            $table->unique(['school_id', 'schoolyear_id', 'json_subject', 'tt_subject'], 'student_tt_subject_mappings_unique');
            $table->index(['school_id', 'schoolyear_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_timetable_subject_mappings');
    }
};
