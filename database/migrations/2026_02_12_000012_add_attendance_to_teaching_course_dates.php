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
        Schema::table('teaching_course_dates', function (Blueprint $table) {
            $table->json('attendance')->nullable()->after('status');
            $table->boolean('attendance_checked')->default(false)->after('attendance');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('teaching_course_dates', function (Blueprint $table) {
            $table->dropColumn(['attendance', 'attendance_checked']);
        });
    }
};
