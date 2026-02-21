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
        Schema::table('school_licences', function (Blueprint $table) {
            $table->json('user_licence_assignments')->nullable()->after('licence_model');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('school_licences', function (Blueprint $table) {
            $table->dropColumn('user_licence_assignments');
        });
    }
};
