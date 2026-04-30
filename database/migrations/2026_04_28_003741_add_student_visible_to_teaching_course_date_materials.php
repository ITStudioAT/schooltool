<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('teaching_course_date_materials') && ! Schema::hasColumn('teaching_course_date_materials', 'student_visible')) {
            Schema::table('teaching_course_date_materials', function (Blueprint $table) {
                $table->boolean('student_visible')->default(false)->after('source_material_card_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('teaching_course_date_materials', 'student_visible')) {
            Schema::table('teaching_course_date_materials', function (Blueprint $table) {
                $table->dropColumn('student_visible');
            });
        }
    }
};
