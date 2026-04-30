<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('teaching_course_date_materials')) {
            Schema::create('teaching_course_date_materials', function (Blueprint $table) {
                $table->id();
                $table->foreignId('teaching_course_date_id')->index();
                $table->string('title');
                $table->string('type')->nullable();
                $table->string('status')->nullable();
                $table->string('subject')->nullable();
                $table->string('area')->nullable();
                $table->string('unit')->nullable();
                $table->unsignedBigInteger('source_material_card_id')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('teaching_course_date_material_attachments')) {
            Schema::create('teaching_course_date_material_attachments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('teaching_course_date_material_id')->index('tcdma_material_id_index');
                $table->string('name');
                $table->string('file_path')->nullable();
                $table->string('mime_type')->nullable();
                $table->unsignedBigInteger('size_bytes')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('teaching_course_date_material_attachments');
        Schema::dropIfExists('teaching_course_date_materials');
    }
};
