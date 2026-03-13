<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aba_analysis_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('aba_id')->constrained('abas')->cascadeOnDelete();
            $table->foreignId('aba_analysis_run_id')->constrained('aba_analysis_runs')->cascadeOnDelete();
            $table->foreignId('aba_attachment_id')->nullable()->constrained('aba_attachments')->nullOnDelete();
            $table->foreignId('parent_result_id')->nullable()->constrained('aba_analysis_results')->nullOnDelete();
            $table->string('section_type', 64);
            $table->string('section_title')->nullable();
            $table->longText('extracted_text')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedTinyInteger('hierarchy_level')->nullable();
            $table->unsignedInteger('start_line')->nullable();
            $table->unsignedInteger('end_line')->nullable();
            $table->unsignedInteger('start_page')->nullable();
            $table->unsignedInteger('end_page')->nullable();
            $table->json('anchor')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['aba_analysis_run_id', 'sort_order'], 'aba_analysis_results_run_sort_idx');
            $table->index(['aba_id', 'section_type'], 'aba_analysis_results_aba_type_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aba_analysis_results');
    }
};
