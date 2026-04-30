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
            Schema::hasTable('teaching_course_date_material_attachments')
            && ! Schema::hasColumn('teaching_course_date_material_attachments', 'source_material_card_attachment_id')
        ) {
            Schema::table('teaching_course_date_material_attachments', function (Blueprint $table): void {
                $table->unsignedBigInteger('source_material_card_attachment_id')
                    ->nullable()
                    ->after('teaching_course_date_material_id')
                    ->index('tcdma_source_attachment_id_index');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (
            Schema::hasTable('teaching_course_date_material_attachments')
            && Schema::hasColumn('teaching_course_date_material_attachments', 'source_material_card_attachment_id')
        ) {
            Schema::table('teaching_course_date_material_attachments', function (Blueprint $table): void {
                $table->dropIndex('tcdma_source_attachment_id_index');
                $table->dropColumn('source_material_card_attachment_id');
            });
        }
    }
};
