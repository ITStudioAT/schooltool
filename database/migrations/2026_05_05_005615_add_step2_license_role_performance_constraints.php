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
            $table->dropIndex('licences_name_index');
            $table->unique('name', 'licences_name_unique');
        });

        Schema::table('school_licences', function (Blueprint $table) {
            $table->dropIndex('school_licences_school_id_licence_id_index');
            $table->unique(['school_id', 'licence_id'], 'school_licences_school_id_licence_id_unique');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->index(['school_id', 'last_name', 'first_name'], 'users_school_id_last_name_first_name_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_school_id_last_name_first_name_index');
        });

        Schema::table('school_licences', function (Blueprint $table) {
            $table->dropUnique('school_licences_school_id_licence_id_unique');
            $table->index(['school_id', 'licence_id'], 'school_licences_school_id_licence_id_index');
        });

        Schema::table('licences', function (Blueprint $table) {
            $table->dropUnique('licences_name_unique');
            $table->index('name', 'licences_name_index');
        });
    }
};
