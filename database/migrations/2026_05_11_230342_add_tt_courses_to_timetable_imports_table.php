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
        if (Schema::hasColumn('timetable_imports', 'tt_courses')) {
            return;
        }

        Schema::table('timetable_imports', function (Blueprint $table) {
            $table->unsignedInteger('tt_courses')->default(0)->after('total_lines');
        });
    }

    public function down(): void
    {
        Schema::table('timetable_imports', function (Blueprint $table) {
            $table->dropColumn('tt_courses');
        });
    }
};
