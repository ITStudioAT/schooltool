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
            ->whereNull('starts_at')
            ->orWhereNull('ends_at')
            ->delete();

        Schema::table('student_timetable_entries', function (Blueprint $table) {
            $table->char('identity_hash_v2', 64)->nullable()->after('identity_hash');
        });

        DB::table('student_timetable_entries')
            ->select([
                'id',
                'school_id',
                'schoolyear_id',
                'source_identifier',
                'date',
                'period',
                'class_name',
                'course',
            ])
            ->orderBy('id')
            ->chunkById(500, function ($entries): void {
                foreach ($entries as $entry) {
                    DB::table('student_timetable_entries')
                        ->where('id', $entry->id)
                        ->update([
                            'identity_hash_v2' => hash('sha256', implode("\x1F", [
                                $entry->school_id,
                                $entry->schoolyear_id,
                                $entry->source_identifier ?? "\x00",
                                $entry->date ?? "\x00",
                                $entry->period ?? "\x00",
                                $entry->class_name ?? "\x00",
                                $entry->course ?? "\x00",
                            ])),
                        ]);
                }
            });

        $duplicateIds = DB::table('student_timetable_entries')
            ->select(['id', 'school_id', 'schoolyear_id', 'identity_hash_v2'])
            ->orderBy('id')
            ->get()
            ->groupBy(fn ($entry): string => "{$entry->school_id}|{$entry->schoolyear_id}|{$entry->identity_hash_v2}")
            ->flatMap(fn ($entries) => $entries->count() > 1
                ? $entries->sortByDesc('id')->skip(1)->pluck('id')
                : [])
            ->values();

        if ($duplicateIds->isNotEmpty()) {
            DB::table('student_timetable_entries')
                ->whereIn('id', $duplicateIds->all())
                ->delete();
        }

        Schema::table('student_timetable_entries', function (Blueprint $table) {
            $table->dropUnique('student_tt_entries_identity_unique');
        });

        DB::table('student_timetable_entries')->update([
            'identity_hash' => DB::raw('identity_hash_v2'),
        ]);

        Schema::table('student_timetable_entries', function (Blueprint $table) {
            $table->dropColumn(['teacher', 'room', 'student_group', 'identity_hash_v2']);
        });

        Schema::table('student_timetable_entries', function (Blueprint $table) {
            $table->unique(['school_id', 'schoolyear_id', 'identity_hash'], 'student_tt_entries_identity_unique');
        });
    }
};
