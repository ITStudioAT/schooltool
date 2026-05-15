<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('student_timetable_entries', function (Blueprint $table) {
            if (! Schema::hasColumn('student_timetable_entries', 'identity_hash')) {
                $table->char('identity_hash', 64)->nullable()->after('student_group');
                $table->index(['school_id', 'schoolyear_id', 'identity_hash'], 'student_tt_entries_identity_hash_idx');
            }
        });

        DB::table('student_timetable_entries')
            ->whereNull('identity_hash')
            ->update([
                'identity_hash' => DB::raw(
                    "SHA2(CONCAT_WS(CHAR(31), school_id, schoolyear_id, COALESCE(source_identifier, CHAR(0)), COALESCE(DATE_FORMAT(date, '%Y-%m-%d'), CHAR(0)), COALESCE(period, CHAR(0)), COALESCE(class_name, CHAR(0)), COALESCE(course, CHAR(0)), COALESCE(student_group, CHAR(0))), 256)"
                ),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('student_timetable_entries', function (Blueprint $table) {
            if (Schema::hasColumn('student_timetable_entries', 'identity_hash')) {
                $table->dropIndex('student_tt_entries_identity_hash_idx');
                $table->dropColumn('identity_hash');
            }
        });
    }
};
