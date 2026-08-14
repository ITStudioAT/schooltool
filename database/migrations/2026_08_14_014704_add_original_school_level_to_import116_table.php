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
        Schema::table('import116', function (Blueprint $table) {
            $table->string('original_school_level')->nullable()->after('attendance_year');
            $table->string('original_attendance_year')->nullable()->after('original_school_level');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('import116', function (Blueprint $table) {
            $table->dropColumn(['original_school_level', 'original_attendance_year']);
        });
    }
};
