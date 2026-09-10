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
        Schema::table('teaching_course_date_material_attachments', function (Blueprint $table): void {
            $table->foreignId('source_teaching_curriculum_document_id')
                ->nullable()
                ->index('tcdma_source_document_index')
                ->constrained('teaching_curriculum_documents', indexName: 'tcdma_source_document_foreign')
                ->nullOnDelete();
        });
    }
};
