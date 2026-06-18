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
        Schema::table('teaching_courses', function (Blueprint $table) {
            $table->json('teaching_student_grade_columns')
                ->nullable()
                ->after('teaching_curriculum_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('teaching_courses', function (Blueprint $table) {
            $table->dropColumn('teaching_student_grade_columns');
        });
    }
};
