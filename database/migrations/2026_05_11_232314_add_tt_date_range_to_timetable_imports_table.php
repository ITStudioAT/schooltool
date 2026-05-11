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
            if (! Schema::hasColumn('timetable_imports', 'tt_first_date')) {
                $table->date('tt_first_date')->nullable()->after('tt_courses');
            }
            if (! Schema::hasColumn('timetable_imports', 'tt_last_date')) {
                $table->date('tt_last_date')->nullable()->after('tt_first_date');
            }
        });
    }

    public function down(): void
    {
        Schema::table('timetable_imports', function (Blueprint $table) {
            $table->dropColumn(['tt_first_date', 'tt_last_date']);
        });
    }
};
