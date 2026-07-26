<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('material_v2_attachments', function (Blueprint $table) {
            $table->string('keyword_extraction_status', 24)
                ->default('pending')
                ->after('extracted_at');
            $table->text('keyword_extraction_error')
                ->nullable()
                ->after('keyword_extraction_status');
            $table->timestamp('keywords_extracted_at')
                ->nullable()
                ->after('keyword_extraction_error');
            $table->char('keyword_source_hash', 64)
                ->nullable()
                ->after('keywords_extracted_at');
            $table->index(
                ['material_v2_item_id', 'keyword_extraction_status'],
                'material_v2_attachments_item_keyword_status_index',
            );
        });

        Schema::create('material_v2_tag_suggestions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_v2_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('material_v2_attachment_id')->constrained()->cascadeOnDelete();
            $table->string('tag_name', 80);
            $table->string('normalized_name', 80);
            $table->decimal('base_score', 10, 4);
            $table->decimal('final_score', 10, 4);
            $table->unsignedSmallInteger('rank');
            $table->string('algorithm', 64);
            $table->string('language', 8);
            $table->json('source_locations');
            $table->char('source_hash', 64);
            $table->timestamp('dismissed_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['material_v2_attachment_id', 'normalized_name'],
                'material_v2_tag_suggestions_attachment_name_unique',
            );
            $table->index(
                ['material_v2_item_id', 'dismissed_at', 'rank'],
                'material_v2_tag_suggestions_item_rank_index',
            );
            $table->index('normalized_name');
        });
    }
};
