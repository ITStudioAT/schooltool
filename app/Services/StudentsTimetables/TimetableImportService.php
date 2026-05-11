<?php

namespace App\Services\StudentsTimetables;

use App\Jobs\StudentsTimetables\ProcessTimetableImportJob;
use App\Models\Schoolyear;
use App\Models\StudentTimetableEntry;
use App\Models\TimetableImport;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TimetableImportService
{
    /**
     * @return array{sections: array<string, int>, total_lines: int, tt_courses: int, tt_first_date: ?string, tt_last_date: ?string}
     */
    public function analyzeFile(string $filePath): array
    {
        $lines = $this->readNormalizedLines($filePath);
        if ($lines === null) {
            return ['sections' => [], 'total_lines' => 0, 'tt_courses' => 0, 'tt_first_date' => null, 'tt_last_date' => null];
        }

        $sections = [];
        $ttCourses = [];
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
                $course = trim($parts[8] ?? '');
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

        StudentTimetableEntry::where('school_id', $import->school_id)
            ->where('schoolyear_id', $import->schoolyear_id)
            ->delete();

        $sections = [];
        $ttCourses = [];
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
                $course = trim($parts[8] ?? '');
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
                StudentTimetableEntry::insert($rows);
                $rows = [];
            }

            if ($processedLines % 500 === 0) {
                $this->markProgress($import, $processedLines, $totalLines);
            }
        }

        if ($rows !== []) {
            StudentTimetableEntry::insert($rows);
        }

        ksort($sections);

        $import->update([
            'sections' => $sections,
            'total_lines' => $totalLines,
            'tt_courses' => count($ttCourses),
            'tt_first_date' => $ttFirstDate,
            'tt_last_date' => $ttLastDate,
            'import_status' => 'completed',
            'progress_current' => $totalLines,
            'progress_total' => $totalLines,
            'import_message' => 'Import abgeschlossen.',
            'import_error' => null,
            'finished_at' => now(),
        ]);

        return $import->refresh();
    }

    public function ensureSemesterTwoStart(User $user, ?int $schoolyearId): void
    {
        $this->schoolyearWithSemesterTwoStart($user, $schoolyearId);
    }

    private function createImportRecord(User $user, string $storedFilename, string $originalFilename, string $filePath, Schoolyear $schoolyear): TimetableImport
    {
        $existing = TimetableImport::where('school_id', $user->school_id)
            ->where('schoolyear_id', $schoolyear->id)
            ->first();

        if ($existing) {
            $oldFile = storage_path($existing->file_path);
            if (is_file($oldFile)) {
                @unlink($oldFile);
            }

            StudentTimetableEntry::where('school_id', $user->school_id)
                ->where('schoolyear_id', $schoolyear->id)
                ->delete();

            $existing->delete();
        }

        return DB::transaction(function () use ($user, $storedFilename, $originalFilename, $filePath, $schoolyear): TimetableImport {
            return TimetableImport::create([
                'school_id' => $user->school_id,
                'schoolyear_id' => $schoolyear->id,
                'user_id' => $user->id,
                'original_filename' => $originalFilename,
                'stored_filename' => $storedFilename,
                'file_path' => $filePath,
                'sections' => [],
                'total_lines' => 0,
                'tt_courses' => 0,
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
            ]);
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
        $now = now();

        return [
            'school_id' => $import->school_id,
            'schoolyear_id' => $schoolyear->id,
            'timetable_import_id' => $import->id,
            'line_number' => $lineNumber,
            'date' => $date,
            'semester' => $date ? $this->semesterForDate($date, $schoolyear->sem_2_start) : null,
            'source_identifier' => $this->nullableColumn($parts[1] ?? null),
            'period' => $this->nullableColumn($parts[3] ?? null),
            'subject' => $this->nullableColumn($parts[4] ?? null),
            'teacher' => $this->nullableColumn($parts[5] ?? null),
            'room' => $this->nullableColumn($parts[6] ?? null),
            'class_name' => $this->nullableColumn($parts[7] ?? null),
            'course' => $this->nullableColumn($parts[8] ?? null),
            'student_group' => $this->nullableColumn($parts[9] ?? null),
            'raw_columns' => json_encode($parts, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?: '[]',
            'raw_line' => $line,
            'created_at' => $now,
            'updated_at' => $now,
        ];
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
