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
        Schema::create('teaching_course_student_category_evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teaching_course_id')->index('tc_sce_course_idx');
            $table->foreignId('user_id')->index('tc_sce_user_idx');
            $table->unsignedTinyInteger('semester')->default(1);
            $table->string('category_name', 100);
            $table->string('value', 50);
            $table->timestamps();

            $table->unique(
                ['teaching_course_id', 'user_id', 'semester', 'category_name'],
                'teaching_course_student_category_eval_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('teaching_course_student_category_evaluations');
    }
};
