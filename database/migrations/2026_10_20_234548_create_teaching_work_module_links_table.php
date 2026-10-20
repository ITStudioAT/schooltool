<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teaching_work_module_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('dropbox_connection_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teaching_course_work_id')->unique()->constrained()->cascadeOnDelete();
            $table->uuid('module_id')->index();
            $table->unsignedTinyInteger('schema_version');
            $table->string('account_id');
            $table->string('namespace_id')->nullable();
            $table->string('folder_id');
            $table->string('folder_name');
            $table->text('folder_path');
            $table->string('handshake_file_id');
            $table->string('handshake_revision');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teaching_work_module_links');
    }
};
