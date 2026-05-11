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
        if (Schema::hasColumn('timetable_imports', 'schoolyear_id')) {
            return;
        }

        Schema::table('timetable_imports', function (Blueprint $table) {
            $table->unsignedBigInteger('schoolyear_id')->nullable()->index()->after('school_id');
        });
    }

    public function down(): void
    {
        Schema::table('timetable_imports', function (Blueprint $table) {
            $table->dropColumn('schoolyear_id');
        });
    }
};
