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
        Schema::create('student_timetable_personal_timetables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('schoolyear_id')->constrained('schoolyears')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('student_code');
            $table->string('student_label')->nullable();
            $table->json('timetable');
            $table->json('state')->nullable();
            $table->timestamp('adopted_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['school_id', 'schoolyear_id', 'user_id', 'student_code'],
                'student_tt_personal_timetables_student_unique',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_timetable_personal_timetables');
    }
};
