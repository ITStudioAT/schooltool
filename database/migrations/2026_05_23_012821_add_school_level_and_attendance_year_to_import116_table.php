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
            $table->string('school_level')->nullable()->after('class');
            $table->string('attendance_year')->nullable()->after('school_level');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('import116', function (Blueprint $table) {
            $table->dropColumn(['school_level', 'attendance_year']);
        });
    }
};
