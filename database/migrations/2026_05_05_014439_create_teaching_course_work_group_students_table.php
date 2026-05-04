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
        Schema::create('teaching_course_work_group_students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teaching_course_work_id')->nullable()->index('tcwgs_work_idx');
            $table->foreignId('teaching_course_id')->nullable()->index('tcwgs_course_idx');
            $table->foreignId('user_id')->nullable()->index('tcwgs_user_idx');

            $table->unique(['teaching_course_work_id', 'user_id'], 'tcwgs_work_user_unique');
            $table->index(['teaching_course_id', 'user_id'], 'tcwgs_course_user_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('teaching_course_work_group_students');
    }
};
