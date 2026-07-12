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
            $table->foreignId('teaching_entry_area_id')
                ->nullable()
                ->after('user_id')
                ->constrained('teaching_entry_areas')
                ->restrictOnDelete();
            $table->unique(
                ['user_id', 'schoolyear_id', 'teaching_entry_area_id', 'short_name'],
                'teaching_entry_definitions_user_year_area_short_unique'
            );
            $table->dropUnique('teaching_entry_definitions_user_year_schema_short_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('teaching_entry_definitions', function (Blueprint $table) {
            $table->unique(
                ['user_id', 'schoolyear_id', 'teaching_schema_id', 'short_name'],
                'teaching_entry_definitions_user_year_schema_short_unique'
            );
            $table->dropUnique('teaching_entry_definitions_user_year_area_short_unique');
            $table->dropConstrainedForeignId('teaching_entry_area_id');
        });
    }
};
