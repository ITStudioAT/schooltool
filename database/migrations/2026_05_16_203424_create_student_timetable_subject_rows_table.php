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
        Schema::create('student_timetable_subject_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('schoolyear_id')->constrained('schoolyears')->cascadeOnDelete();
            $table->unsignedTinyInteger('semester')->nullable();
            $table->string('branch')->nullable();
            $table->string('json_code')->nullable();
            $table->string('json_subject')->nullable();
            $table->string('name')->nullable();
            $table->decimal('hours_per_week', 5, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('source')->default('manual');
            $table->timestamps();

            $table->index(['school_id', 'schoolyear_id']);
            $table->index(['semester', 'branch']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_timetable_subject_rows');
    }
};
