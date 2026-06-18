<?php

namespace App\Services\StudentsTimetables;

use App\Jobs\StudentsTimetables\ProcessRecognitionCsvImportJob;
use App\Models\StudentTimetableRecognitionImport;
use App\Models\StudentTimetableRecognitionRow;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;

class RecognitionImportService
{
    private const IDENTITY_SEPARATOR = "\x1F";

    public function createQueuedImport(
        User $user,
        string $storedFilename,
        string $originalFilename,
        string $filePath,
        string $storedPath,
    ): StudentTimetableRecognitionImport {
        $import = StudentTimetableRecognitionImport::create([
            'school_id' => $user->school_id,
            'schoolyear_id' => $user->schoolyear_id,
            'user_id' => $user->id,
            'original_filename' => $originalFilename,
            'stored_filename' => $storedFilename,
            'file_path' => $filePath,
            'file_size' => is_file($storedPath) ? (filesize($storedPath) ?: 0) : 0,
            'total_rows' => 0,
            'imported_rows' => 0,
            'skipped_rows' => 0,
            'import_status' => 'pending',
            'import_message' => 'Import wartet auf Verarbeitung.',
            'imported_at' => now(),
        ]);

        ProcessRecognitionCsvImportJob::dispatch($import->id);

        return $import;
    }

    public function processImport(StudentTimetableRecognitionImport $import): StudentTimetableRecognitionImport
    {
        $import->update([
            'import_status' => 'running',
            'import_message' => 'CSV-Datei wird verarbeitet.',
        ]);

        $storedPath = storage_path($import->file_path);

        if (! is_file($storedPath)) {
            $this->markFailed($import, 'Die CSV-Datei konnte nicht gelesen werden.');

            return $import->refresh();
        }

        $filterResult = $this->filterStoredCsv($storedPath);

        DB::transaction(function () use ($import, $filterResult, $storedPath): void {
            $import->rows()->delete();

            $rows = collect($filterResult['records'])
                ->map(fn (array $record): array => [
                    'school_id' => $import->school_id,
                    'schoolyear_id' => $import->schoolyear_id,
                    ...$record,
                    'identity_hash' => $this->recognitionRowIdentityHash(
                        (int) $import->school_id,
                        (int) $import->schoolyear_id,
                        $record['raw_data'],
                    ),
                ])
                ->keyBy(fn (array $row): string => $row['identity_hash'])
                ->values();

            if ($rows->isNotEmpty()) {
                $rows->pluck('identity_hash')->chunk(500)->each(
                    fn ($hashes) => StudentTimetableRecognitionRow::query()
                        ->where('school_id', $import->school_id)
                        ->where('schoolyear_id', $import->schoolyear_id)
                        ->whereIn('identity_hash', $hashes->all())
                        ->delete()
                );

                $now = now();

                $rows
                    ->map(fn (array $row): array => [
                        'student_timetable_recognition_import_id' => $import->id,
                        'school_id' => $row['school_id'],
                        'schoolyear_id' => $row['schoolyear_id'],
                        'row_number' => $row['row_number'],
                        'student_code' => $row['student_code'],
                        'student' => $row['student'],
                        'subject' => $row['subject'],
                        'grade' => $row['grade'],
                        'note' => $row['note'],
                        'colloquia' => $row['colloquia'],
                        'module_repetitions' => $row['module_repetitions'],
                        'teacher_code' => $row['teacher_code'],
                        'identity_hash' => $row['identity_hash'],
                        'raw_data' => json_encode($row['raw_data'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])
                    ->chunk(500)
                    ->each(fn ($chunk) => DB::table('student_timetable_recognition_rows')->insert($chunk->all()));
            }

            $import->update([
                'file_size' => is_file($storedPath) ? (filesize($storedPath) ?: 0) : 0,
                'total_rows' => $filterResult['total_rows'],
                'imported_rows' => $rows->count(),
                'skipped_rows' => $filterResult['total_rows'] - $rows->count(),
                'import_status' => 'completed',
                'import_message' => 'Import abgeschlossen.',
            ]);
        });

        return $import->refresh();
    }

    public function markFailed(StudentTimetableRecognitionImport $import, string $message): void
    {
        $import->update([
            'import_status' => 'failed',
            'import_message' => $message,
        ]);
    }

    public function importLegacyCsvFilesIfMissing(User $user): void
    {
        $uploadPath = "app/private/{$user->school_id}/recognition-imports/{$user->schoolyear_id}";
        $directory = storage_path($uploadPath);

        if (! File::isDirectory($directory)) {
            return;
        }

        collect(File::files($directory))
            ->filter(fn ($file): bool => Str::lower($file->getExtension()) === 'csv')
            ->reject(fn ($file): bool => StudentTimetableRecognitionImport::query()
                ->where('school_id', $user->school_id)
                ->where('schoolyear_id', $user->schoolyear_id)
                ->where('stored_filename', $file->getFilename())
                ->exists())
            ->each(function ($file) use ($user, $uploadPath): void {
                $import = StudentTimetableRecognitionImport::create([
                    'school_id' => $user->school_id,
                    'schoolyear_id' => $user->schoolyear_id,
                    'user_id' => $user->id,
                    'original_filename' => $file->getFilename(),
                    'stored_filename' => $file->getFilename(),
                    'file_path' => "{$uploadPath}/{$file->getFilename()}",
                    'file_size' => $file->getSize(),
                    'total_rows' => 0,
                    'imported_rows' => 0,
                    'skipped_rows' => 0,
                    'import_status' => 'pending',
                    'import_message' => 'Import wartet auf Verarbeitung.',
                    'imported_at' => now(),
                ]);

                ProcessRecognitionCsvImportJob::dispatch($import->id);
            });
    }

    /**
     * @return array{
     *     total_rows: int,
     *     imported_rows: int,
     *     skipped_rows: int,
     *     records: array<int, array{
     *         row_number: int,
     *         raw_data: array<string, string>,
     *         student_code: ?string,
     *         student: ?string,
     *         subject: ?string,
     *         grade: ?string,
     *         note: ?string,
     *         colloquia: ?string,
     *         module_repetitions: ?string,
     *         teacher_code: ?string,
     *     }>,
     * }
     */
    private function filterStoredCsv(string $path): array
    {
        $contents = File::get($path);
        $contents = preg_replace('/^\xEF\xBB\xBF/u', '', $contents) ?? $contents;
        $lines = preg_split('/\R/u', $contents, -1, PREG_SPLIT_NO_EMPTY);

        if (! $lines) {
            File::put($path, '');

            return [
                'total_rows' => 0,
                'imported_rows' => 0,
                'skipped_rows' => 0,
                'records' => [],
            ];
        }

        $delimiter = $this->detectDelimiter($lines[0]);
        $headers = str_getcsv($lines[0], $delimiter);
        $filteredRows = [$headers];
        $records = [];
        $totalRows = 0;

        foreach (array_slice($lines, 1) as $index => $line) {
            $row = str_getcsv($line, $delimiter);
            $record = $this->recordFromRow($headers, $row);
            $totalRows++;

            if (! $this->recognitionRecordShouldBeImported($record)) {
                continue;
            }

            $filteredRows[] = $row;
            $records[] = $this->recognitionRowPayload($record, $index + 2);
        }

        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            throw new RuntimeException('Die CSV-Datei konnte nicht gefiltert werden.');
        }

        foreach ($filteredRows as $row) {
            fputcsv($handle, $row, $delimiter);
        }

        rewind($handle);
        $filteredContents = stream_get_contents($handle);
        fclose($handle);

        File::put($path, $filteredContents === false ? '' : $filteredContents);

        return [
            'total_rows' => $totalRows,
            'imported_rows' => count($records),
            'skipped_rows' => $totalRows - count($records),
            'records' => $records,
        ];
    }

    private function detectDelimiter(string $headerLine): string
    {
        $delimiters = [';' => substr_count($headerLine, ';'), ',' => substr_count($headerLine, ','), "\t" => substr_count($headerLine, "\t")];
        arsort($delimiters);

        return array_key_first($delimiters) ?: ';';
    }

    /**
     * @param  array<int, string|null>  $headers
     * @param  array<int, string|null>  $row
     * @return array<string, string>
     */
    private function recordFromRow(array $headers, array $row): array
    {
        $record = [];

        foreach ($headers as $index => $header) {
            $record[$this->normalizeHeader($header)] = trim((string) ($row[$index] ?? ''));
        }

        return $record;
    }

    /**
     * @param  array<string, string>  $record
     */
    private function recognitionRecordShouldBeImported(array $record): bool
    {
        return $this->hasValue($record, ['note'])
            || $this->hasNonZeroValue($record, ['kolloquien'])
            || $this->hasModuleRepetitionValue($record)
            || $this->hasValue($record, ['lehrerkuerzel', 'lehrerkurzel', 'lehrerkürzel', 'lehrerkã¼rzel']);
    }

    private function normalizeHeader(mixed $header): string
    {
        $normalized = Str::lower(trim((string) $header));
        $normalized = str_replace([' ', '-', '_'], '', $normalized);
        $normalized = str_replace(['ä', ...$this->legacyMojibakeVariants('ä')], 'ae', $normalized);
        $normalized = str_replace(['ö', ...$this->legacyMojibakeVariants('ö')], 'oe', $normalized);
        $normalized = str_replace(['ü', ...$this->legacyMojibakeVariants('ü')], 'ue', $normalized);

        return $normalized;
    }

    /**
     * @return array<int, string>
     */
    private function legacyMojibakeVariants(string $character): array
    {
        $singleMojibake = mb_convert_encoding($character, 'UTF-8', 'Windows-1252');
        $doubleMojibake = mb_convert_encoding($singleMojibake, 'UTF-8', 'Windows-1252');

        return array_values(array_unique([
            $singleMojibake,
            Str::lower($singleMojibake),
            $doubleMojibake,
            Str::lower($doubleMojibake),
        ]));
    }

    /**
     * @param  array<string, string>  $record
     * @param  array<int, string>  $keys
     */
    private function hasValue(array $record, array $keys): bool
    {
        foreach ($keys as $key) {
            if (trim($record[$key] ?? '') !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, string>  $record
     * @param  array<int, string>  $keys
     */
    private function hasNonZeroValue(array $record, array $keys): bool
    {
        foreach ($keys as $key) {
            $value = trim($record[$key] ?? '');

            if ($value !== '' && $value !== '0') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, string>  $record
     */
    private function hasModuleRepetitionValue(array $record): bool
    {
        $value = trim($record['modulwiederholungen'] ?? '');

        return $value !== '' && $value !== '0' && $value !== '0/0';
    }

    /**
     * @param  array<string, string>  $record
     * @return array{
     *     row_number: int,
     *     raw_data: array<string, string>,
     *     student_code: ?string,
     *     student: ?string,
     *     subject: ?string,
     *     grade: ?string,
     *     note: ?string,
     *     colloquia: ?string,
     *     module_repetitions: ?string,
     *     teacher_code: ?string,
     * }
     */
    private function recognitionRowPayload(array $record, int $rowNumber): array
    {
        return [
            'row_number' => $rowNumber,
            'raw_data' => $record,
            'student_code' => $this->firstRecordValue($record, ['schuelerinnenkennzahl']),
            'student' => $this->firstRecordValue($record, ['studierende', 'schueler', 'schuelerin', 'student']),
            'subject' => $this->firstRecordValue($record, ['gegenstand', 'fach', 'faecher']),
            'grade' => $this->firstRecordValue($record, ['note']),
            'note' => $this->firstRecordValue($record, ['note']),
            'colloquia' => $this->firstRecordValue($record, ['kolloquien']),
            'module_repetitions' => $this->firstRecordValue($record, ['modulwiederholungen']),
            'teacher_code' => $this->firstRecordValue($record, ['lehrerkuerzel', 'lehrerkurzel', 'lehrerkürzel', 'lehrerkã¼rzel']),
        ];
    }

    /**
     * @param  array<string, string>  $rawData
     */
    private function recognitionRowIdentityHash(int $schoolId, int $schoolyearId, array $rawData): string
    {
        ksort($rawData);

        return hash('sha256', implode(self::IDENTITY_SEPARATOR, [
            $schoolId,
            $schoolyearId,
            json_encode($rawData, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
        ]));
    }

    /**
     * @param  array<string, string>  $record
     * @param  array<int, string>  $keys
     */
    private function firstRecordValue(array $record, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = trim($record[$key] ?? '');

            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }
}
