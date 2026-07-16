<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teaching_courses', function (Blueprint $table) {
            $table->boolean('teaching_show_student_age')
                ->default(false)
                ->after('teaching_student_grade_columns');
            $table->boolean('teaching_show_student_last_login')
                ->default(false)
                ->after('teaching_show_student_age');
        });
    }
};
