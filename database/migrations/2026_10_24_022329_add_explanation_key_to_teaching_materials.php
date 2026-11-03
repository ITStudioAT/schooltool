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
        foreach (['teaching_curriculum_documents', 'teaching_course_date_material_attachments'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->string('explanation_key', 80)->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['teaching_curriculum_documents', 'teaching_course_date_material_attachments'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropColumn('explanation_key');
            });
        }
    }
};
