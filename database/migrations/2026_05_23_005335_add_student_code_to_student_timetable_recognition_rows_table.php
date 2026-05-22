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
        if (! Schema::hasColumn('student_timetable_recognition_rows', 'student_code')) {
            Schema::table('student_timetable_recognition_rows', function (Blueprint $table) {
                $table->string('student_code')->nullable()->after('row_number');
            });
        }

        DB::table('student_timetable_recognition_rows')
            ->select(['id', 'raw_data'])
            ->whereNull('student_code')
            ->orderBy('id')
            ->chunkById(500, function ($rows): void {
                foreach ($rows as $row) {
                    DB::table('student_timetable_recognition_rows')
                        ->where('id', $row->id)
                        ->update([
                            'student_code' => $this->studentCodeFromRawData($row->raw_data),
                        ]);
                }
            });

        Schema::table('student_timetable_recognition_rows', function (Blueprint $table) {
            $table->index(['school_id', 'schoolyear_id', 'student_code'], 'student_tt_recognition_rows_student_code_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('student_timetable_recognition_rows', function (Blueprint $table) {
            $table->dropIndex('student_tt_recognition_rows_student_code_idx');
        });

        Schema::table('student_timetable_recognition_rows', function (Blueprint $table) {
            if (Schema::hasColumn('student_timetable_recognition_rows', 'student_code')) {
                $table->dropColumn('student_code');
            }
        });
    }

    private function studentCodeFromRawData(mixed $rawData): ?string
    {
        if (is_string($rawData)) {
            $decoded = json_decode($rawData, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return null;
            }

            $rawData = $decoded;
        }

        if (! is_array($rawData)) {
            return null;
        }

        foreach ($rawData as $key => $value) {
            if ($this->normalizeHeader($key) !== 'schuelerinnenkennzahl') {
                continue;
            }

            $studentCode = trim((string) $value);

            return $studentCode === '' ? null : $studentCode;
        }

        return null;
    }

    private function normalizeHeader(mixed $header): string
    {
        $normalized = mb_strtolower(trim((string) $header));
        $normalized = str_replace([' ', '-', '_'], '', $normalized);
        $normalized = str_replace(['ä', 'Ã¤', 'ã¤'], 'ae', $normalized);
        $normalized = str_replace(['ö', 'Ã¶', 'ã¶'], 'oe', $normalized);
        $normalized = str_replace(['ü', 'Ã¼', 'ã¼'], 'ue', $normalized);

        return $normalized;
    }
};
