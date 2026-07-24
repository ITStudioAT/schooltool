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
        Schema::create('material_v2_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_v2_item_id')->constrained()->cascadeOnDelete();
            $table->string('disk', 64);
            $table->string('path', 1024);
            $table->string('original_name');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->longText('extracted_text')->nullable();
            $table->string('extraction_status', 24)->default('pending');
            $table->text('extraction_error')->nullable();
            $table->timestamp('extracted_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['material_v2_item_id', 'extraction_status'], 'material_v2_attachments_item_status_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('material_v2_attachments');
    }
};
