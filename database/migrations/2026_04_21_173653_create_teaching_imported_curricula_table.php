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
        Schema::create('teaching_imported_curricula', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->char('curriculum_key', 36);
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedTinyInteger('semester_count')->default(2);
            $table->json('free_weeks')->nullable();
            $table->json('topics')->nullable();
            $table->unsignedTinyInteger('source_schema_version')->default(1);
            $table->timestamp('source_exported_at')->nullable();
            $table->timestamp('imported_at')->nullable();
            $table->timestamps();

            $table->unique(['school_id', 'user_id', 'curriculum_key'], 'teaching_imported_curricula_user_key_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('teaching_imported_curricula');
    }
};
