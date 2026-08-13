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
        Schema::create('student_timetable_v3_timetables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('schoolyear_id')->constrained('schoolyears')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('context_key', 80);
            $table->string('planning_mode', 30);
            $table->string('student_code')->nullable();
            $table->char('fingerprint', 64);
            $table->json('modules');
            $table->json('parameters');
            $table->json('summary');
            $table->json('timetables');
            $table->timestamp('generated_at');
            $table->timestamps();

            $table->unique(
                ['school_id', 'schoolyear_id', 'user_id', 'context_key'],
                'student_tt_v3_timetables_scope_unique',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_timetable_v3_timetables');
    }
};
