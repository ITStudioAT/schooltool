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
        if (
            Schema::hasTable('teaching_course_date_materials')
            && ! Schema::hasColumn('teaching_course_date_materials', 'material_title')
        ) {
            Schema::table('teaching_course_date_materials', function (Blueprint $table): void {
                $table->string('material_title')->nullable()->after('title');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (
            Schema::hasTable('teaching_course_date_materials')
            && Schema::hasColumn('teaching_course_date_materials', 'material_title')
        ) {
            Schema::table('teaching_course_date_materials', function (Blueprint $table): void {
                $table->dropColumn('material_title');
            });
        }
    }
};
