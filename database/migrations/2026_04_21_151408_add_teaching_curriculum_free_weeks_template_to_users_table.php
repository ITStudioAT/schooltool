<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('teaching_curriculum_free_weeks_template')
                ->nullable()
                ->after('teaching_grade_columns_by_schoolyear');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('teaching_curriculum_free_weeks_template');
        });
    }
};
