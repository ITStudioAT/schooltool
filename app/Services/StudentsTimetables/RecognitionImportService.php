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
    private const BATCH_SIZE = 500;

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
        $storedPath = storage_path($import->file_path);
        $backupPath = $this->backupPath($storedPath, (int) $import->id);
        $temporaryPath = $this->temporaryFilteredPath($storedPath, (int) $import->id);
        $this->recoverInterruptedReplacement($storedPath, $backupPath, $temporaryPath, (string) $import->import_status);

        $import->update([
            'import_status' => 'running',
            'import_message' => 'CSV-Datei wird verarbeitet.',
        ]);

        if (! is_file($storedPath)) {
            $this->markFailed($import, 'Die CSV-Datei konnte nicht gelesen werden.');

            return $import->refresh();
        }

        $replacementActivated = false;
        try {
            $filterResult = $this->prepareFilteredCsv($storedPath, $temporaryPath);
            $this->activateFilteredCsv($storedPath, $temporaryPath, $backupPath);
            $replacementActivated = true;

            DB::transaction(function () use ($import, $storedPath, $backupPath, $filterResult): void {
                $import->rows()->delete();
                $importedRows = $this->persistOriginalCsv($backupPath, $import);

                $import->update([
                    'file_size' => is_file($storedPath) ? (filesize($storedPath) ?: 0) : 0,
                    'total_rows' => $filterResult['total_rows'],
                    'imported_rows' => $importedRows,
                    'skipped_rows' => $filterResult['total_rows'] - $importedRows,
                    'import_status' => 'completed',
                    'import_message' => 'Import abgeschlossen.',
                ]);
            });
        } catch (\Throwable $exception) {
            if ($replacementActivated) {
                $this->restoreOriginalCsv($storedPath, $backupPath);
            }

            throw $exception;
        } finally {
            @unlink($temporaryPath);
        }

        @unlink($backupPath);

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

    /** @return array{total_rows: int} */
    private function prepareFilteredCsv(string $path, string $temporaryPath): array
    {
        $input = fopen($path, 'rb');
        if ($input === false) {
            throw new RuntimeException('Die CSV-Datei konnte nicht gelesen werden.');
        }

        @unlink($temporaryPath);
        $output = fopen($temporaryPath, 'xb');
        if ($output === false) {
            fclose($input);

            throw new RuntimeException('Die CSV-Datei konnte nicht gefiltert werden.');
        }

        $totalRows = 0;

        try {
            $headerLine = fgets($input);
            if ($headerLine === false) {
                return ['total_rows' => 0];
            }

            $headerLine = preg_replace('/^\xEF\xBB\xBF/u', '', $headerLine) ?? $headerLine;
            $delimiter = $this->detectDelimiter($headerLine);
            rewind($input);

            $headers = fgetcsv($input, null, $delimiter, '"', '') ?: [];
            if (isset($headers[0])) {
                $headers[0] = preg_replace('/^\xEF\xBB\xBF/u', '', (string) $headers[0]) ?? $headers[0];
            }

            fputcsv($output, $headers, $delimiter, '"', '');

            while (($row = fgetcsv($input, null, $delimiter, '"', '')) !== false) {
                if ($this->isEmptyCsvRow($row)) {
                    continue;
                }

                $totalRows++;
                $record = $this->recordFromRow($headers, $row);

                if ($this->recognitionRecordShouldBeImported($record)) {
                    fputcsv($output, $row, $delimiter, '"', '');
                }
            }
        } finally {
            fclose($input);
            fclose($output);
        }

        return ['total_rows' => $totalRows];
    }

    private function persistOriginalCsv(string $path, StudentTimetableRecognitionImport $import): int
    {
        $input = fopen($path, 'rb');
        if ($input === false) {
            throw new RuntimeException('Die ursprüngliche CSV-Datei konnte nicht gelesen werden.');
        }

        $batch = [];

        try {
            $headerLine = fgets($input);
            if ($headerLine !== false) {
                $delimiter = $this->detectDelimiter($headerLine);
                rewind($input);
                $headers = fgetcsv($input, null, $delimiter, '"', '') ?: [];
                if (isset($headers[0])) {
                    $headers[0] = preg_replace('/^\xEF\xBB\xBF/u', '', (string) $headers[0]) ?? $headers[0];
                }
                $rowNumber = 1;

                while (($row = fgetcsv($input, null, $delimiter, '"', '')) !== false) {
                    if ($this->isEmptyCsvRow($row)) {
                        continue;
                    }

                    $rowNumber++;
                    $record = $this->recordFromRow($headers, $row);
                    if (! $this->recognitionRecordShouldBeImported($record)) {
                        continue;
                    }

                    $batch[] = $this->recognitionDatabaseRow($import, $record, $rowNumber);

                    if (count($batch) >= self::BATCH_SIZE) {
                        $this->upsertRecognitionRows($batch);
                        $batch = [];
                    }
                }
            }

            if ($batch !== []) {
                $this->upsertRecognitionRows($batch);
            }
        } finally {
            fclose($input);
        }

        return StudentTimetableRecognitionRow::query()
            ->where('student_timetable_recognition_import_id', $import->id)
            ->count();
    }

    private function activateFilteredCsv(string $path, string $temporaryPath, string $backupPath): void
    {
        @unlink($backupPath);

        if (! @rename($path, $backupPath)) {
            throw new RuntimeException('Die ursprüngliche CSV-Datei konnte nicht gesichert werden.');
        }

        if (@rename($temporaryPath, $path)) {
            return;
        }

        @rename($backupPath, $path);

        throw new RuntimeException('Die gefilterte CSV-Datei konnte nicht aktiviert werden.');
    }

    private function restoreOriginalCsv(string $path, string $backupPath): void
    {
        if (! is_file($backupPath)) {
            return;
        }

        @unlink($path);

        if (! @rename($backupPath, $path)) {
            throw new RuntimeException('Die ursprüngliche CSV-Datei konnte nicht wiederhergestellt werden.');
        }
    }

    private function recoverInterruptedReplacement(
        string $path,
        string $backupPath,
        string $temporaryPath,
        string $status,
    ): void {
        if (is_file($backupPath)) {
            if ($status === 'completed') {
                @unlink($backupPath);
            } else {
                $this->restoreOriginalCsv($path, $backupPath);
            }
        }

        @unlink($temporaryPath);
    }

    private function backupPath(string $path, int $importId): string
    {
        return "{$path}.import-{$importId}.original.bak";
    }

    private function temporaryFilteredPath(string $path, int $importId): string
    {
        return "{$path}.import-{$importId}.filtered.tmp";
    }

    /**
     * @param  array<int, string|null>  $row
     */
    private function isEmptyCsvRow(array $row): bool
    {
        return count($row) === 1 && trim((string) ($row[0] ?? '')) === '';
    }

    /**
     * @param  array<string, string>  $record
     * @return array<string, mixed>
     */
    private function recognitionDatabaseRow(
        StudentTimetableRecognitionImport $import,
        array $record,
        int $rowNumber,
    ): array {
        $payload = $this->recognitionRowPayload($record, $rowNumber);
        $now = now();

        return [
            'student_timetable_recognition_import_id' => $import->id,
            'school_id' => $import->school_id,
            'schoolyear_id' => $import->schoolyear_id,
            'row_number' => $payload['row_number'],
            'student_code' => $payload['student_code'],
            'student' => $payload['student'],
            'subject' => $payload['subject'],
            'grade' => $payload['grade'],
            'note' => $payload['note'],
            'colloquia' => $payload['colloquia'],
            'module_repetitions' => $payload['module_repetitions'],
            'teacher_code' => $payload['teacher_code'],
            'identity_hash' => $this->recognitionRowIdentityHash(
                (int) $import->school_id,
                (int) $import->schoolyear_id,
                $payload['raw_data'],
            ),
            'raw_data' => json_encode($payload['raw_data'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    protected function upsertRecognitionRows(array $rows): void
    {
        DB::table('student_timetable_recognition_rows')->upsert(
            $rows,
            ['school_id', 'schoolyear_id', 'identity_hash'],
            [
                'student_timetable_recognition_import_id',
                'row_number',
                'student_code',
                'student',
                'subject',
                'grade',
                'note',
                'colloquia',
                'module_repetitions',
                'teacher_code',
                'raw_data',
                'updated_at',
            ],
        );
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
