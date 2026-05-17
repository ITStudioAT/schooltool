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
        Schema::table('student_timetable_entries', function (Blueprint $table) {
            if (! Schema::hasColumn('student_timetable_entries', 'starts_at')) {
                $table->string('starts_at')->nullable()->after('period');
            }

            if (! Schema::hasColumn('student_timetable_entries', 'ends_at')) {
                $table->string('ends_at')->nullable()->after('starts_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('student_timetable_entries', function (Blueprint $table) {
            if (Schema::hasColumn('student_timetable_entries', 'ends_at')) {
                $table->dropColumn('ends_at');
            }

            if (Schema::hasColumn('student_timetable_entries', 'starts_at')) {
                $table->dropColumn('starts_at');
            }
        });
    }
};
