<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('abas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('schoolyear_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('student_name');
            $table->date('created_on');
            $table->date('evaluated_on')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['school_id', 'schoolyear_id']);
            $table->index(['user_id', 'schoolyear_id']);
        });

        Schema::create('aba_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('aba_id')->constrained('abas')->cascadeOnDelete();
            $table->string('original_name');
            $table->string('path');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('aba_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aba_attachments');
        Schema::dropIfExists('abas');
    }
};
