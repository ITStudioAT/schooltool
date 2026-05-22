<?php

namespace App\Services\StudentsTimetables;

use App\Jobs\StudentsTimetables\ProcessTimetableImportJob;
use App\Jobs\StudentsTimetables\ProcessTimetableUnimportJob;
use App\Models\Schoolyear;
use App\Models\StudentTimetableEntry;
use App\Models\TimetableImport;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class TimetableImportService
{
    private const IDENTITY_SEPARATOR = "\x1F";

    private const IDENTITY_NULL_VALUE = "\x00";

    /**
     * @return array{sections: array<string, int>, total_lines: int, tt_courses: int, tt_skipped_invalid: int, tt_first_date: ?string, tt_last_date: ?string}
     */
    public function analyzeFile(string $filePath): array
    {
        $lines = $this->readNormalizedLines($filePath);
        if ($lines === null) {
            return [
                'sections' => [],
                'total_lines' => 0,
                'tt_courses' => 0,
                'tt_skipped_invalid' => 0,
                'tt_first_date' => null,
                'tt_last_date' => null,
            ];
        }

        $sections = [];
        $ttCourses = [];
        $ttSkippedInvalid = 0;
        $ttFirstDate = null;
        $ttLastDate = null;

        foreach ($lines as $line) {
            $parts = explode("\t", $line);
            $code = trim($parts[0] ?? '');
            if ($code === '') {
                continue;
            }
            $sections[$code] = ($sections[$code] ?? 0) + 1;

            if ($code === 'TT') {
                if (! $this->isImportableTimetableRecord($parts)) {
                    $ttSkippedInvalid++;

                    continue;
                }

                $course = $this->timetableCourseName($parts);
                if ($course !== '') {
                    $ttCourses[$course] = true;
                }

                $rawDate = trim($parts[2] ?? '');
                if (preg_match('/^\d{8}$/', $rawDate)) {
                    $date = substr($rawDate, 0, 4).'-'.substr($rawDate, 4, 2).'-'.substr($rawDate, 6, 2);
                    if ($ttFirstDate === null || $date < $ttFirstDate) {
                        $ttFirstDate = $date;
                    }
                    if ($ttLastDate === null || $date > $ttLastDate) {
                        $ttLastDate = $date;
                    }
                }
            }
        }

        ksort($sections);

        return [
            'sections' => $sections,
            'total_lines' => count($lines),
            'tt_courses' => count($ttCourses),
            'tt_skipped_invalid' => $ttSkippedInvalid,
            'tt_first_date' => $ttFirstDate,
            'tt_last_date' => $ttLastDate,
        ];
    }

    public function createImport(User $user, string $storedFilename, string $originalFilename, string $filePath, ?int $schoolyearId = null): TimetableImport
    {
        $schoolyear = $this->schoolyearWithSemesterTwoStart($user, $schoolyearId);
        $import = $this->createImportRecord($user, $storedFilename, $originalFilename, $filePath, $schoolyear);

        return $this->processImport($import);
    }

    public function createQueuedImport(User $user, string $storedFilename, string $originalFilename, string $filePath, ?int $schoolyearId = null): TimetableImport
    {
        $schoolyear = $this->schoolyearWithSemesterTwoStart($user, $schoolyearId);
        $import = $this->createImportRecord($user, $storedFilename, $originalFilename, $filePath, $schoolyear);

        ProcessTimetableImportJob::dispatch($import->id);

        return $import;
    }

    public function processImport(TimetableImport $import): TimetableImport
    {
        $schoolyear = Schoolyear::where('school_id', $import->school_id)->find($import->schoolyear_id);

        if (! $schoolyear || ! $schoolyear->sem_2_start) {
            $this->markFailed($import, 'Semester 2 beginnt am muss gesetzt sein, bevor Sie eine TXT-Datei importieren.');

            return $import->refresh();
        }

        $lines = $this->readNormalizedLines(storage_path($import->file_path));

        if ($lines === null) {
            $this->markFailed($import, 'Die TXT-Datei konnte nicht gelesen werden.');

            return $import->refresh();
        }

        $totalLines = count($lines);
        $this->markRunning($import, $totalLines);

        $sections = [];
        $ttCourses = [];
        $ttSkippedInvalid = 0;
        $ttFirstDate = null;
        $ttLastDate = null;
        $rows = [];
        $processedLines = 0;

        foreach ($lines as $index => $line) {
            $processedLines++;
            $parts = explode("\t", $line);
            $code = trim($parts[0] ?? '');

            if ($code !== '') {
                $sections[$code] = ($sections[$code] ?? 0) + 1;
            }

            if ($code === 'TT') {
                if (! $this->isImportableTimetableRecord($parts)) {
                    $ttSkippedInvalid++;

                    continue;
                }

                $course = $this->timetableCourseName($parts);
                if ($course !== '') {
                    $ttCourses[$course] = true;
                }

                $date = $this->normalizeDate($parts[2] ?? null);
                if ($date) {
                    if ($ttFirstDate === null || $date < $ttFirstDate) {
                        $ttFirstDate = $date;
                    }
                    if ($ttLastDate === null || $date > $ttLastDate) {
                        $ttLastDate = $date;
                    }
                }

                $rows[] = $this->timetableEntryPayload($import, $schoolyear, $parts, $line, $index + 1);
            }

            if (count($rows) >= 500) {
                $this->updateOrCreateTimetableEntries($rows);
                $rows = [];
            }

            if ($processedLines % 500 === 0) {
                $this->markProgress($import, $processedLines, $totalLines);
            }
        }

        if ($rows !== []) {
            $this->updateOrCreateTimetableEntries($rows);
        }

        ksort($sections);

        $import->update($this->importCompletionPayload([
            'sections' => $sections,
            'total_lines' => $totalLines,
            'tt_courses' => count($ttCourses),
            'tt_skipped_invalid' => $ttSkippedInvalid,
            'tt_first_date' => $ttFirstDate,
            'tt_last_date' => $ttLastDate,
            'import_status' => 'completed',
            'progress_current' => $totalLines,
            'progress_total' => $totalLines,
            'import_message' => 'Import abgeschlossen.',
            'import_error' => null,
            'finished_at' => now(),
        ]));

        StudentTimetableOverviewService::forgetCacheFor((int) $import->school_id, (int) $import->schoolyear_id);

        return $import->refresh();
    }

    public function queueUnimport(TimetableImport $import): TimetableImport
    {
        $import->update([
            'import_status' => 'deleting',
            'progress_current' => 0,
            'progress_total' => 0,
            'import_message' => 'Import wird gelöscht. Der aktive Stundenplan wird anschließend neu aufgebaut.',
            'import_error' => null,
            'started_at' => now(),
            'finished_at' => null,
        ]);

        ProcessTimetableUnimportJob::dispatch((int) $import->id);

        return $import->refresh();
    }

    /**
     * @return array{message: string, removed_import_id: int, removed_import_entries: int, replayed_imports: int, active_entries: int}
     */
    public function unimport(TimetableImport $import): array
    {
        $schoolId = (int) $import->school_id;
        $schoolyearId = (int) $import->schoolyear_id;
        $importId = (int) $import->id;
        $filePath = storage_path($import->file_path);
        $removedImportEntries = StudentTimetableEntry::where('timetable_import_id', $importId)->count();

        $remainingImports = TimetableImport::where('school_id', $schoolId)
            ->where('schoolyear_id', $schoolyearId)
            ->whereKeyNot($importId)
            ->orderBy('imported_at')
            ->orderBy('id')
            ->get();

        DB::transaction(function () use ($import, $remainingImports, $schoolId, $schoolyearId): void {
            StudentTimetableEntry::where('school_id', $schoolId)
                ->where('schoolyear_id', $schoolyearId)
                ->delete();

            $import->delete();

            $remainingImports->each(function (TimetableImport $remainingImport): void {
                $this->processImport($remainingImport);
            });
        });

        StudentTimetableOverviewService::forgetCacheFor($schoolId, $schoolyearId);

        if (is_file($filePath)) {
            @unlink($filePath);
        }

        return [
            'message' => 'Import wurde gelöscht und der aktive Stundenplan wurde neu aufgebaut.',
            'removed_import_id' => $importId,
            'removed_import_entries' => $removedImportEntries,
            'replayed_imports' => $remainingImports->count(),
            'active_entries' => StudentTimetableEntry::where('school_id', $schoolId)
                ->where('schoolyear_id', $schoolyearId)
                ->count(),
        ];
    }

    public function ensureSemesterTwoStart(User $user, ?int $schoolyearId): void
    {
        $this->schoolyearWithSemesterTwoStart($user, $schoolyearId);
    }

    private function createImportRecord(User $user, string $storedFilename, string $originalFilename, string $filePath, Schoolyear $schoolyear): TimetableImport
    {
        return DB::transaction(function () use ($user, $storedFilename, $originalFilename, $filePath, $schoolyear): TimetableImport {
            return TimetableImport::create($this->importCreationPayload([
                'school_id' => $user->school_id,
                'schoolyear_id' => $schoolyear->id,
                'user_id' => $user->id,
                'original_filename' => $originalFilename,
                'stored_filename' => $storedFilename,
                'file_path' => $filePath,
                'sections' => [],
                'total_lines' => 0,
                'tt_courses' => 0,
                'tt_skipped_invalid' => 0,
                'tt_first_date' => null,
                'tt_last_date' => null,
                'import_status' => 'pending',
                'progress_current' => 0,
                'progress_total' => 0,
                'import_message' => 'Import wartet auf Verarbeitung.',
                'import_error' => null,
                'imported_at' => now(),
                'started_at' => null,
                'finished_at' => null,
            ]));
        });
    }

    private function schoolyearWithSemesterTwoStart(User $user, ?int $schoolyearId): Schoolyear
    {
        if (! $schoolyearId) {
            throw ValidationException::withMessages([
                'schoolyear_id' => 'Bitte wählen Sie ein Schuljahr aus, bevor Sie eine TXT-Datei importieren.',
            ]);
        }

        $schoolyear = Schoolyear::where('school_id', $user->school_id)->find($schoolyearId);

        if (! $schoolyear) {
            throw ValidationException::withMessages([
                'schoolyear_id' => 'Das gewählte Schuljahr wurde nicht gefunden.',
            ]);
        }

        if (! $schoolyear->sem_2_start) {
            throw ValidationException::withMessages([
                'sem_2_start' => 'Semester 2 beginnt am muss gesetzt sein, bevor Sie eine TXT-Datei importieren.',
            ]);
        }

        return $schoolyear;
    }

    /**
     * @param  list<string>  $parts
     * @return array<string, mixed>
     */
    private function timetableEntryPayload(
        TimetableImport $import,
        Schoolyear $schoolyear,
        array $parts,
        string $line,
        int $lineNumber,
    ): array {
        $date = $this->normalizeDate($parts[2] ?? null);
        $usesUntisTimeColumns = $this->usesUntisTimeColumns($parts);
        $className = $this->nullableColumn($parts[7] ?? null);

        $row = [
            'school_id' => $import->school_id,
            'schoolyear_id' => $schoolyear->id,
            'timetable_import_id' => $import->id,
            'line_number' => $lineNumber,
            'date' => $date,
            'semester' => $date ? $this->semesterForDate($date, $schoolyear->sem_2_start) : null,
            'source_identifier' => $this->nullableColumn($parts[1] ?? null),
            'period' => $this->nullableColumn($parts[3] ?? null),
            'starts_at' => $usesUntisTimeColumns ? $this->nullableColumn($parts[4] ?? null) : null,
            'ends_at' => $usesUntisTimeColumns ? $this->nullableColumn($parts[5] ?? null) : null,
            'subject' => $usesUntisTimeColumns ? $this->nullableColumn($parts[8] ?? null) : $this->nullableColumn($parts[4] ?? null),
            'teacher' => $usesUntisTimeColumns ? null : $this->nullableColumn($parts[5] ?? null),
            'room' => $usesUntisTimeColumns ? null : $this->nullableColumn($parts[6] ?? null),
            'class_name' => $className,
            'course' => $this->nullableColumn($parts[8] ?? null),
            'module_code' => $this->moduleCodeFromClassName($className),
            'student_group' => $usesUntisTimeColumns ? null : $this->nullableColumn($parts[9] ?? null),
            'is_active' => true,
            'raw_columns' => $parts,
            'raw_line' => $line,
        ];

        $row['identity_hash'] = $this->timetableEntryIdentityHash($row);

        return $row;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function updateOrCreateTimetableEntries(array $rows): void
    {
        $rows = collect($rows)
            ->keyBy(fn (array $row): string => $row['identity_hash'])
            ->values();

        if ($rows->isEmpty()) {
            return;
        }

        $now = now();

        DB::table('student_timetable_entries')->upsert(
            $rows
                ->map(fn (array $row): array => [
                    'school_id' => $row['school_id'],
                    'schoolyear_id' => $row['schoolyear_id'],
                    'timetable_import_id' => $row['timetable_import_id'],
                    'line_number' => $row['line_number'],
                    'date' => $row['date'],
                    'semester' => $row['semester'],
                    'source_identifier' => $row['source_identifier'],
                    'period' => $row['period'],
                    'starts_at' => $row['starts_at'],
                    'ends_at' => $row['ends_at'],
                    'subject' => $row['subject'],
                    'teacher' => $row['teacher'],
                    'room' => $row['room'],
                    'class_name' => $row['class_name'],
                    'course' => $row['course'],
                    'module_code' => $row['module_code'],
                    'student_group' => $row['student_group'],
                    'is_active' => $row['is_active'],
                    'identity_hash' => $row['identity_hash'],
                    'raw_columns' => json_encode($row['raw_columns'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                    'raw_line' => $row['raw_line'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ])
                ->all(),
            ['school_id', 'schoolyear_id', 'identity_hash'],
            [
                'timetable_import_id',
                'line_number',
                'date',
                'semester',
                'source_identifier',
                'period',
                'starts_at',
                'ends_at',
                'subject',
                'teacher',
                'room',
                'class_name',
                'course',
                'module_code',
                'student_group',
                'is_active',
                'raw_columns',
                'raw_line',
                'updated_at',
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function timetableEntryIdentityHash(array $row): string
    {
        return hash('sha256', implode(self::IDENTITY_SEPARATOR, [
            'school_id' => $row['school_id'],
            'schoolyear_id' => $row['schoolyear_id'],
            'source_identifier' => $row['source_identifier'] ?? self::IDENTITY_NULL_VALUE,
            'date' => $row['date'] ?? self::IDENTITY_NULL_VALUE,
            'period' => $row['period'] ?? self::IDENTITY_NULL_VALUE,
            'class_name' => $row['class_name'] ?? self::IDENTITY_NULL_VALUE,
            'course' => $row['course'] ?? self::IDENTITY_NULL_VALUE,
            'student_group' => $row['student_group'] ?? self::IDENTITY_NULL_VALUE,
        ]));
    }

    private function normalizeDate(?string $rawDate): ?string
    {
        $rawDate = trim((string) $rawDate);

        if (preg_match('/^\d{8}$/', $rawDate)) {
            return substr($rawDate, 0, 4).'-'.substr($rawDate, 4, 2).'-'.substr($rawDate, 6, 2);
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $rawDate)) {
            return $rawDate;
        }

        return null;
    }

    /**
     * @param  list<string>  $parts
     */
    private function usesUntisTimeColumns(array $parts): bool
    {
        return $this->isTimeColumn($parts[4] ?? null)
            && $this->isTimeColumn($parts[5] ?? null);
    }

    private function isTimeColumn(?string $value): bool
    {
        return preg_match('/^\d{1,2}:\d{2}$/', trim((string) $value)) === 1;
    }

    private function moduleCodeFromClassName(?string $className): ?string
    {
        if (! $className) {
            return null;
        }

        if (preg_match('/^\s*([A-Za-zÄÖÜäöüß]+[0-9]+)(?=$|[-\s])/u', $className, $matches) !== 1) {
            return null;
        }

        return $matches[1];
    }

    /**
     * @param  list<string>  $parts
     */
    private function timetableCourseName(array $parts): string
    {
        return trim($parts[7] ?? '');
    }

    /**
     * @param  list<string>  $parts
     */
    private function isImportableTimetableRecord(array $parts): bool
    {
        return trim($parts[1] ?? '') !== '0'
            && $this->timetableCourseName($parts) !== '';
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function importCreationPayload(array $payload): array
    {
        if ($this->hasSkippedInvalidColumn()) {
            return $payload;
        }

        unset($payload['tt_skipped_invalid']);

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function importCompletionPayload(array $payload): array
    {
        if ($this->hasSkippedInvalidColumn()) {
            return $payload;
        }

        unset($payload['tt_skipped_invalid']);

        return $payload;
    }

    private function hasSkippedInvalidColumn(): bool
    {
        return Schema::hasColumn('timetable_imports', 'tt_skipped_invalid');
    }

    private function semesterForDate(string $date, string $semesterTwoStart): int
    {
        return $date >= $semesterTwoStart ? 2 : 1;
    }

    private function nullableColumn(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    /**
     * @return list<string>|null
     */
    private function readNormalizedLines(string $filePath): ?array
    {
        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return null;
        }

        return array_values(array_map(
            fn (string $line): string => $this->toUtf8($line),
            $lines,
        ));
    }

    private function toUtf8(string $value): string
    {
        if (mb_check_encoding($value, 'UTF-8')) {
            return $value;
        }

        return mb_convert_encoding($value, 'UTF-8', 'Windows-1252,ISO-8859-1,UTF-8');
    }

    private function markRunning(TimetableImport $import, int $totalLines): void
    {
        $import->update([
            'import_status' => 'running',
            'progress_current' => 0,
            'progress_total' => $totalLines,
            'import_message' => $totalLines > 0
                ? "Import verarbeitet 0 von {$totalLines} Zeilen."
                : 'Import verarbeitet die Datei.',
            'import_error' => null,
            'started_at' => now(),
            'finished_at' => null,
        ]);
    }

    private function markProgress(TimetableImport $import, int $processedLines, int $totalLines): void
    {
        $import->update([
            'progress_current' => $processedLines,
            'progress_total' => $totalLines,
            'import_message' => "Import verarbeitet {$processedLines} von {$totalLines} Zeilen.",
        ]);
    }

    private function markFailed(TimetableImport $import, string $message): void
    {
        $import->update([
            'import_status' => 'failed',
            'import_message' => $message,
            'import_error' => $message,
            'finished_at' => now(),
        ]);
    }
}
