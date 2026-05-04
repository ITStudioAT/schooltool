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
        Schema::table('licences', function (Blueprint $table) {
            $table->index('name', 'licences_name_index');
        });

        Schema::table('school_tools', function (Blueprint $table) {
            $table->index('school_id', 'school_tools_school_id_index');
        });

        Schema::table('school_licences', function (Blueprint $table) {
            $table->index(['school_id', 'licence_id'], 'school_licences_school_id_licence_id_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('school_licences', function (Blueprint $table) {
            $table->dropIndex('school_licences_school_id_licence_id_index');
        });

        Schema::table('school_tools', function (Blueprint $table) {
            $table->dropIndex('school_tools_school_id_index');
        });

        Schema::table('licences', function (Blueprint $table) {
            $table->dropIndex('licences_name_index');
        });
    }
};
