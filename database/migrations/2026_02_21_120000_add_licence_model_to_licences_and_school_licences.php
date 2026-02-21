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
            $table->json('licence_model')->nullable()->after('is_selectable');
        });

        Schema::table('school_licences', function (Blueprint $table) {
            $table->json('licence_model')->nullable()->after('valid_until');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('school_licences', function (Blueprint $table) {
            $table->dropColumn('licence_model');
        });

        Schema::table('licences', function (Blueprint $table) {
            $table->dropColumn('licence_model');
        });
    }
};

