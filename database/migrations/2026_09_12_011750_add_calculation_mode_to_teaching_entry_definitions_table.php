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
            $table->string('calculation_mode', 20)->default('individual');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('teaching_entry_definitions', function (Blueprint $table) {
            $table->dropColumn('calculation_mode');
        });
    }
};
