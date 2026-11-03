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
        Schema::table('teaching_entry_areas', function (Blueprint $table) {
            $table->json('grading_part_groups')->nullable();
        });
        Schema::table('teaching_entry_grading_parts', function (Blueprint $table) {
            $table->uuid('grading_group_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('teaching_entry_areas', function (Blueprint $table) {
            $table->dropColumn('grading_part_groups');
        });
        Schema::table('teaching_entry_grading_parts', function (Blueprint $table) {
            $table->dropColumn('grading_group_id');
        });
    }
};
