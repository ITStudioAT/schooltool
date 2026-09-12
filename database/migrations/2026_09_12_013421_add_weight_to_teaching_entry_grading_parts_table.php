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
        Schema::table('teaching_entry_grading_parts', function (Blueprint $table) {
            $table->decimal('weight', 10, 3)->default(1);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('teaching_entry_grading_parts', function (Blueprint $table) {
            $table->dropColumn('weight');
        });
    }
};
