<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('material_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('source_type', 20); // upload|link|note
            $table->text('source_url')->nullable();
            $table->longText('source_text')->nullable();
            $table->string('subject')->nullable();
            $table->string('area')->nullable();
            $table->string('unit')->nullable();
            $table->string('type')->nullable();
            $table->string('status', 20)->default('inbox'); // inbox|in_progress|done
            $table->text('notes')->nullable();
            $table->json('keywords')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'subject']);
            $table->index(['user_id', 'area']);
            $table->index(['user_id', 'unit']);
            $table->index(['user_id', 'type']);
        });

        Schema::create('material_card_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_card_id')->constrained()->cascadeOnDelete();
            $table->string('attachment_type', 20); // file|link
            $table->string('name')->nullable();
            $table->text('url')->nullable();
            $table->string('file_path')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->timestamps();

            $table->index(['material_card_id', 'attachment_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('material_card_attachments');
        Schema::dropIfExists('material_cards');
    }
};
