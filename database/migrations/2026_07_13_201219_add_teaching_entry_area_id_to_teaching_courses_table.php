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
        Schema::table('teaching_courses', function (Blueprint $table) {
            $table->foreignId('teaching_entry_area_id')
                ->nullable()
                ->after('teaching_schema_id')
                ->constrained('teaching_entry_areas')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('teaching_courses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('teaching_entry_area_id');
        });
    }
};
