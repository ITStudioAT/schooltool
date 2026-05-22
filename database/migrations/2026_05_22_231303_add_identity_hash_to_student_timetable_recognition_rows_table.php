<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const IDENTITY_SEPARATOR = "\x1F";

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('student_timetable_recognition_rows', 'identity_hash')) {
            Schema::table('student_timetable_recognition_rows', function (Blueprint $table) {
                $table->char('identity_hash', 64)->nullable()->after('teacher_code');
            });
        }

        DB::table('student_timetable_recognition_rows')
            ->select(['id', 'school_id', 'schoolyear_id', 'raw_data'])
            ->orderBy('id')
            ->chunkById(500, function ($rows): void {
                foreach ($rows as $row) {
                    DB::table('student_timetable_recognition_rows')
                        ->where('id', $row->id)
                        ->update([
                            'identity_hash' => $this->recognitionRowIdentityHash(
                                (int) $row->school_id,
                                (int) $row->schoolyear_id,
                                $row->raw_data,
                            ),
                        ]);
                }
            });

        $duplicateIds = DB::table('student_timetable_recognition_rows')
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
            DB::table('student_timetable_recognition_rows')
                ->whereIn('id', $duplicateIds->all())
                ->delete();
        }

        Schema::table('student_timetable_recognition_rows', function (Blueprint $table) {
            $table->unique(['school_id', 'schoolyear_id', 'identity_hash'], 'student_tt_recognition_rows_identity_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('student_timetable_recognition_rows', function (Blueprint $table) {
            $table->dropUnique('student_tt_recognition_rows_identity_unique');
        });

        Schema::table('student_timetable_recognition_rows', function (Blueprint $table) {
            if (Schema::hasColumn('student_timetable_recognition_rows', 'identity_hash')) {
                $table->dropColumn('identity_hash');
            }
        });
    }

    private function recognitionRowIdentityHash(int $schoolId, int $schoolyearId, mixed $rawData): string
    {
        return hash('sha256', implode(self::IDENTITY_SEPARATOR, [
            $schoolId,
            $schoolyearId,
            json_encode($this->normalizedRawData($rawData), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
        ]));
    }

    private function normalizedRawData(mixed $rawData): mixed
    {
        if (is_string($rawData)) {
            $decoded = json_decode($rawData, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                return $this->normalizedRawData($decoded);
            }

            return $rawData;
        }

        if (! is_array($rawData)) {
            return $rawData;
        }

        ksort($rawData);

        foreach ($rawData as $key => $value) {
            $rawData[$key] = $this->normalizedRawData($value);
        }

        return $rawData;
    }
};
