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
            $table->unsignedTinyInteger('semester_count')->default(1);
            $table->unsignedTinyInteger('semester_1_weight')->default(100);
            $table->unsignedTinyInteger('semester_2_weight')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('teaching_entry_areas', function (Blueprint $table) {
            $table->dropColumn(['semester_count', 'semester_1_weight', 'semester_2_weight']);
        });
    }
};
