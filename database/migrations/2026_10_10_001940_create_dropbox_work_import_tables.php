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
        Schema::create('dropbox_connections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('account_id');
            $table->text('credentials');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });
        Schema::create('teaching_work_dropbox_folders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('dropbox_connection_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teaching_course_work_id')->constrained()->cascadeOnDelete();
            $table->string('folder_id');
            $table->string('folder_name');
            $table->timestamps();
            $table->unique(['dropbox_connection_id', 'teaching_course_work_id'], 'dropbox_work_folder_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('teaching_work_dropbox_folders');
        Schema::dropIfExists('dropbox_connections');
    }
};
