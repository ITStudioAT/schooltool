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
        Schema::table('teaching_entry_definitions', function (Blueprint $table) {
            $table->string('free_grading_mode', 20)->nullable();
            $table->json('free_missing_grade_thresholds')->nullable();
            $table->json('free_points_grade_thresholds')->nullable();
        });
    }
};
