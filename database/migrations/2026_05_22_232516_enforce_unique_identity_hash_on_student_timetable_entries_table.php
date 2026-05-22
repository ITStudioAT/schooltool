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
        DB::table('student_timetable_entries')
            ->whereNull('identity_hash')
            ->update([
                'identity_hash' => DB::raw(
                    "SHA2(CONCAT_WS(CHAR(31), school_id, schoolyear_id, COALESCE(source_identifier, CHAR(0)), COALESCE(DATE_FORMAT(date, '%Y-%m-%d'), CHAR(0)), COALESCE(period, CHAR(0)), COALESCE(class_name, CHAR(0)), COALESCE(course, CHAR(0)), COALESCE(student_group, CHAR(0))), 256)"
                ),
            ]);

        $duplicateIds = DB::table('student_timetable_entries')
            ->select(['id', 'school_id', 'schoolyear_id', 'identity_hash'])
            ->whereNotNull('identity_hash')
            ->orderBy('id')
            ->get()
            ->groupBy(fn ($row): string => "{$row->school_id}|{$row->schoolyear_id}|{$row->identity_hash}")
            ->flatMap(fn ($rows) => $rows->count() > 1
                ? $rows->sortByDesc('id')->skip(1)->pluck('id')
                : [])
            ->values();

        if ($duplicateIds->isNotEmpty()) {
            DB::table('student_timetable_entries')
                ->whereIn('id', $duplicateIds->all())
                ->delete();
        }

        Schema::table('student_timetable_entries', function (Blueprint $table) {
            $table->dropIndex('student_tt_entries_identity_hash_idx');
            $table->unique(['school_id', 'schoolyear_id', 'identity_hash'], 'student_tt_entries_identity_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('student_timetable_entries', function (Blueprint $table) {
            $table->dropUnique('student_tt_entries_identity_unique');
            $table->index(['school_id', 'schoolyear_id', 'identity_hash'], 'student_tt_entries_identity_hash_idx');
        });
    }
};
