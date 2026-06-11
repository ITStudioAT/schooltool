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
        Schema::create('student_timetable_published_timetables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('schoolyear_id')->constrained('schoolyears')->cascadeOnDelete();
            $table->foreignId('published_by_user_id')->nullable();
            $table->string('student_code');
            $table->string('student_label')->nullable();
            $table->json('timetable');
            $table->json('state')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['school_id', 'schoolyear_id', 'student_code'],
                'student_tt_published_timetables_student_unique',
            );
            $table->index(
                ['school_id', 'schoolyear_id', 'published_by_user_id'],
                'student_tt_published_timetables_publisher_idx',
            );
            $table
                ->foreign('published_by_user_id', 'student_tt_published_by_user_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_timetable_published_timetables');
    }
};
