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
        Schema::table('timetable_imports', function (Blueprint $table) {
            if (! Schema::hasColumn('timetable_imports', 'tt_skipped_invalid')) {
                $table->unsignedInteger('tt_skipped_invalid')->default(0)->after('tt_courses');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('timetable_imports', function (Blueprint $table) {
            if (Schema::hasColumn('timetable_imports', 'tt_skipped_invalid')) {
                $table->dropColumn('tt_skipped_invalid');
            }
        });
    }
};
