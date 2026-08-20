<?php

namespace App\Services\StudentsTimetables;

use App\Enums\StudentTimetableStudyProgram;
use App\Jobs\StudentsTimetables\ProcessRecognitionCsvImportJob;
use App\Models\Import116;
use App\Models\StudentTimetableRecognitionImport;
use App\Models\StudentTimetableRecognitionRow;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;

class RecognitionImportService
{
    private const BATCH_SIZE = 500;

    public function __construct(private StudentTimetableRecognitionIdentityService $identityService) {}

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
            if ($filterResult['importable_rows'] === 0) {
                $import->update([
                    'total_rows' => $filterResult['total_rows'],
                    'imported_rows' => 0,
                    'skipped_rows' => $filterResult['total_rows'],
                ]);
                $this->markFailed(
                    $import,
                    'Die CSV-Datei enthält keine gültigen Anrechnungsdaten. Bestehende Daten wurden nicht verändert.',
                );

                return $import->refresh();
            }

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

    /** @return array{total_rows: int, importable_rows: int} */
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
        $importableRows = 0;

        try {
            $headerLine = fgets($input);
            if ($headerLine === false) {
                return ['total_rows' => 0, 'importable_rows' => 0];
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
                    $importableRows++;
                    fputcsv($output, $row, $delimiter, '"', '');
                }
            }
        } finally {
            fclose($input);
            fclose($output);
        }

        return [
            'total_rows' => $totalRows,
            'importable_rows' => $importableRows,
        ];
    }

    private function persistOriginalCsv(string $path, StudentTimetableRecognitionImport $import): int
    {
        $input = fopen($path, 'rb');
        if ($input === false) {
            throw new RuntimeException('Die ursprüngliche CSV-Datei konnte nicht gelesen werden.');
        }

        $batch = [];
        $studyProgramsByStudentCode = [];

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
                    $this->collectStudentStudyProgram($studyProgramsByStudentCode, $record);

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

            $this->persistStudentStudyPrograms($import, $studyProgramsByStudentCode);
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
            'identity_hash' => $this->identityService->hash(
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
     * @param  array<string, array<string, StudentTimetableStudyProgram>>  $studyProgramsByStudentCode
     * @param  array<string, string>  $record
     */
    private function collectStudentStudyProgram(array &$studyProgramsByStudentCode, array $record): void
    {
        $studentCode = $this->firstRecordValue($record, ['schuelerinnenkennzahl']);
        $studyProgram = StudentTimetableStudyProgram::fromSubjectPlan($record['stundentafel'] ?? null);

        if (! $studentCode || ! $studyProgram) {
            return;
        }

        $studyProgramsByStudentCode[$studentCode][$studyProgram->value] = $studyProgram;
    }

    /**
     * @param  array<string, array<string, StudentTimetableStudyProgram>>  $studyProgramsByStudentCode
     */
    private function persistStudentStudyPrograms(
        StudentTimetableRecognitionImport $import,
        array $studyProgramsByStudentCode,
    ): void {
        $studentCodesByStudyProgram = collect($studyProgramsByStudentCode)
            ->filter(fn (array $studyPrograms): bool => count($studyPrograms) === 1)
            ->mapWithKeys(fn (array $studyPrograms, string $studentCode): array => [
                $studentCode => array_key_first($studyPrograms),
            ])
            ->groupBy(fn (string $studyProgram): string => $studyProgram, preserveKeys: true);

        foreach ($studentCodesByStudyProgram as $studyProgram => $studentCodes) {
            Import116::query()
                ->where('school_id', $import->school_id)
                ->where('schoolyear_id', $import->schoolyear_id)
                ->whereIn('student_code', $studentCodes->keys())
                ->update(['study_program' => $studyProgram]);
        }

        $ambiguousStudentCodes = collect($studyProgramsByStudentCode)
            ->filter(fn (array $studyPrograms): bool => count($studyPrograms) > 1)
            ->keys();

        if ($ambiguousStudentCodes->isEmpty()) {
            return;
        }

        Import116::query()
            ->where('school_id', $import->school_id)
            ->where('schoolyear_id', $import->schoolyear_id)
            ->whereIn('student_code', $ambiguousStudentCodes)
            ->update(['study_program' => null]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    protected function upsertRecognitionRows(array $rows): void
    {
        $this->adoptExistingIdentityHashes($rows);

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

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function adoptExistingIdentityHashes(array $rows): void
    {
        collect($rows)
            ->groupBy(fn (array $row): string => "{$row['school_id']}|{$row['schoolyear_id']}")
            ->each(function (Collection $importRows): void {
                $schoolId = (int) $importRows->first()['school_id'];
                $schoolyearId = (int) $importRows->first()['schoolyear_id'];
                $identityHashes = $importRows->pluck('identity_hash')->filter()->unique()->values();
                $importRowsWithModuleIds = $importRows->map(fn (array $row): array => [
                    'student_code' => $row['student_code'] ?? null,
                    'module_id' => trim((string) data_get(
                        json_decode((string) ($row['raw_data'] ?? '[]'), true),
                        'modulid',
                    )),
                ]);
                $moduleIds = $importRowsWithModuleIds
                    ->pluck('module_id')
                    ->filter()
                    ->unique()
                    ->values();
                $fallbackStudentCodes = $importRowsWithModuleIds
                    ->where('module_id', '')
                    ->pluck('student_code')
                    ->filter()
                    ->unique()
                    ->values();

                $existingRows = StudentTimetableRecognitionRow::query()
                    ->where('school_id', $schoolId)
                    ->where('schoolyear_id', $schoolyearId)
                    ->where(function ($query) use ($identityHashes, $fallbackStudentCodes, $moduleIds): void {
                        $query->whereIn('identity_hash', $identityHashes);

                        if ($fallbackStudentCodes->isNotEmpty()) {
                            $query->orWhereIn('student_code', $fallbackStudentCodes);
                        }

                        if ($moduleIds->isNotEmpty()) {
                            $moduleIdExpression = DB::connection()->getDriverName() === 'sqlite'
                                ? "json_extract(raw_data, '$.modulid')"
                                : "JSON_UNQUOTE(JSON_EXTRACT(raw_data, '$.modulid'))";

                            $query->orWhereIn(DB::raw($moduleIdExpression), $moduleIds);
                        }
                    })
                    ->latest('id')
                    ->get(['id', 'school_id', 'schoolyear_id', 'identity_hash', 'raw_data']);
                $existingRowsByIdentityHash = $existingRows->groupBy(
                    fn (StudentTimetableRecognitionRow $row): string => $this->identityService->hash(
                        (int) $row->school_id,
                        (int) $row->schoolyear_id,
                        $row->raw_data ?? [],
                    ),
                );
                $identityHashUpdates = [];

                foreach ($identityHashes as $identityHash) {
                    $matchingRows = $existingRowsByIdentityHash->get($identityHash, collect());
                    if ($matchingRows->isEmpty()) {
                        continue;
                    }

                    $identityOwner = $matchingRows->first(
                        fn (StudentTimetableRecognitionRow $row): bool => $row->identity_hash === $identityHash,
                    ) ?? $matchingRows->first();

                    if ($identityOwner->identity_hash !== $identityHash) {
                        $identityHashUpdates[(int) $identityOwner->id] = $identityHash;
                    }
                }

                $this->updateIdentityHashes($identityHashUpdates);
            });
    }

    /**
     * @param  array<int, string>  $identityHashUpdates
     */
    private function updateIdentityHashes(array $identityHashUpdates): void
    {
        collect($identityHashUpdates)
            ->chunk(self::BATCH_SIZE)
            ->each(function (Collection $identityHashChunk): void {
                $cases = [];
                $bindings = [];

                foreach ($identityHashChunk as $id => $identityHash) {
                    $cases[] = 'WHEN ? THEN ?';
                    $bindings[] = $id;
                    $bindings[] = $identityHash;
                }

                $ids = $identityHashChunk->keys()->map(fn (mixed $id): int => (int) $id)->values();
                $placeholders = $ids->map(fn (): string => '?')->implode(', ');
                $bindings = [...$bindings, ...$ids];

                DB::update(
                    'UPDATE student_timetable_recognition_rows SET identity_hash = CASE id '
                    .implode(' ', $cases)
                    .' ELSE identity_hash END WHERE id IN ('.$placeholders.')',
                    $bindings,
                );
            });
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
