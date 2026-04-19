<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('teaching_curriculum_documents')) {
            return;
        }

        Schema::create('teaching_curriculum_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teaching_curriculum_id')->constrained('teaching_curricula')->cascadeOnDelete();
            $table->string('source_type')->default('upload'); // 'upload' or 'material'
            $table->string('name');
            $table->string('file_path')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->foreignId('material_card_id')->nullable()->constrained('material_cards')->nullOnDelete();
            $table->timestamps();

            $table->index('teaching_curriculum_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teaching_curriculum_documents');
    }
};
