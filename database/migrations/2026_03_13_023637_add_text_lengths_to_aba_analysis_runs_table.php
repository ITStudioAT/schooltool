<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aba_analysis_runs', function (Blueprint $table) {
            $table->unsignedInteger('text_length')->nullable()->after('extracted_figures_count');
            $table->unsignedInteger('text_length_without_spaces')->nullable()->after('text_length');
        });
    }

    public function down(): void
    {
        Schema::table('aba_analysis_runs', function (Blueprint $table) {
            $table->dropColumn(['text_length', 'text_length_without_spaces']);
        });
    }
};
