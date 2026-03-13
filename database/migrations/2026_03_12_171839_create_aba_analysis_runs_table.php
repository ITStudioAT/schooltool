<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aba_analysis_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('aba_id')->constrained('abas')->cascadeOnDelete();
            $table->foreignId('aba_attachment_id')->nullable()->constrained('aba_attachments')->nullOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 32)->default('started');
            $table->string('source_original_name')->nullable();
            $table->string('source_path')->nullable();
            $table->string('source_mime_type', 191)->nullable();
            $table->text('status_message')->nullable();
            $table->text('error_message')->nullable();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('running_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->dateTime('aborted_at')->nullable();
            $table->dateTime('failed_at')->nullable();
            $table->unsignedInteger('extracted_sections_count')->default(0);
            $table->unsignedInteger('extracted_figures_count')->default(0);
            $table->json('summary')->nullable();
            $table->timestamps();

            $table->index(['aba_id', 'status'], 'aba_analysis_runs_aba_status_idx');
            $table->index(['aba_id', 'created_at'], 'aba_analysis_runs_aba_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aba_analysis_runs');
    }
};
