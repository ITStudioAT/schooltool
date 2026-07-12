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
            $table->unsignedBigInteger('teaching_entry_area_id')->nullable(false)->change();
            $table->dropIndex('teaching_entry_definitions_user_year_schema_index');
            $table->dropColumn('teaching_schema_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('teaching_entry_definitions', function (Blueprint $table) {
            $table->string('teaching_schema_id', 36)->nullable()->after('user_id');
            $table->index(
                ['user_id', 'schoolyear_id', 'teaching_schema_id'],
                'teaching_entry_definitions_user_year_schema_index'
            );
        });
    }
};
