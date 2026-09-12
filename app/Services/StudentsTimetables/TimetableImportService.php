<?php

namespace App\Services\StudentsTimetables;

use App\Jobs\StudentsTimetables\ProcessTimetableImportJob;
use App\Jobs\StudentsTimetables\ProcessTimetableUnimportJob;
use App\Models\Schoolyear;
use App\Models\StudentTimetableEntry;
use App\Models\TimetableImport;
use App\Models\User;
use App\Support\PrivateImportSourceFile;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

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
        $totalLines = 0;

        foreach ($lines as $line) {
            $totalLines++;
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
            'total_lines' => $totalLines,
            'tt_courses' => count($ttCourses),
            'tt_skipped_invalid' => $ttSkippedInvalid,
            'tt_first_date' => $ttFirstDate,
            'tt_last_date' => $ttLastDate,
        ];
    }

    /**
     * @return array{
     *     is_plausible: bool,
     *     status: string,
     *     schoolyear_label: ?string,
     *     schoolyear_from: ?string,
     *     schoolyear_until: ?string,
     *     data_from: ?string,
     *     data_until: ?string,
     *     message: string
     * }
     */
    public function datePlausibilityFor(TimetableImport $import): array
    {
        $schoolyear = Schoolyear::query()
            ->where('school_id', $import->school_id)
            ->find($import->schoolyear_id);

        return $this->datePlausibility(
            $schoolyear,
            $this->dateString($import->tt_first_date),
            $this->dateString($import->tt_last_date),
        );
    }

    /**
     * @return array{source_available: bool, records: list<array<string, mixed>>}
     */
    public function previewDiagnosticsFor(TimetableImport $import): array
    {
        $sourcePath = PrivateImportSourceFile::resolve(
            $import->file_path,
            "app/private/{$import->school_id}/timetable-imports/{$import->schoolyear_id}",
        );
        $lines = $sourcePath !== null ? $this->readNormalizedLines($sourcePath, true) : null;

        if ($lines === null) {
            return ['source_available' => false, 'records' => []];
        }

        $records = [];
        foreach ($lines as $index => $line) {
            $parts = explode("\t", $line);
            if (trim($parts[0] ?? '') !== 'TT') {
                continue;
            }

            $errors = $this->timetableRecordErrors($parts);
            if ($errors === []) {
                continue;
            }

            $records[] = [
                'line_number' => $index + 1,
                'source_identifier' => $parts[1] ?? null,
                'date' => $parts[2] ?? null,
                'period' => $parts[3] ?? null,
                'starts_at' => $parts[4] ?? null,
                'ends_at' => $parts[5] ?? null,
                'course' => $parts[7] ?? null,
                'errors' => $errors,
            ];
        }

        return ['source_available' => true, 'records' => $records];
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

    public function createPreview(User $user, string $storedFilename, string $originalFilename, string $filePath, ?int $schoolyearId = null): TimetableImport
    {
        $schoolyear = $this->schoolyearWithSemesterTwoStart($user, $schoolyearId);

        if (TimetableImport::query()
            ->where('school_id', $user->school_id)
            ->where('schoolyear_id', $schoolyear->id)
            ->where('import_status', 'preview')
            ->exists()) {
            $this->deleteSourceFile($user->school_id, $schoolyear->id, $filePath);

            throw ValidationException::withMessages([
                'file' => 'Für dieses Schuljahr ist bereits ein Vorimport offen. Bitte importieren oder löschen Sie zuerst diese Vorschau.',
            ]);
        }

        $analysis = $this->analyzeFile(storage_path($filePath));

        if ($this->importableTimetableRows($analysis) === 0) {
            $this->deleteSourceFile($user->school_id, $schoolyear->id, $filePath);

            throw ValidationException::withMessages([
                'file' => 'Die TXT-Datei enthält keine gültigen Stundenplan-Einträge. Bestehende Daten wurden nicht verändert.',
            ]);
        }

        return $this->createImportRecord(
            $user,
            $storedFilename,
            $originalFilename,
            $filePath,
            $schoolyear,
            $analysis,
            'preview',
        );
    }

    /** @return list<array{key: string, label: string, from: string, until: string}> */
    public function replacementScopesFor(TimetableImport $import): array
    {
        $schoolyear = Schoolyear::where('school_id', $import->school_id)->find($import->schoolyear_id);

        return $schoolyear ? app(TimetableImportComparisonService::class)->replacementScopes($schoolyear) : [];
    }

    /** @return array<string, mixed> */
    public function comparisonFor(TimetableImport $import, string $operation = 'merge', ?string $scope = null): array
    {
        if (! in_array($operation, ['merge', 'replace'], true)) {
            throw ValidationException::withMessages(['operation' => 'Bitte wählen Sie Plan ersetzen oder Daten ergänzen.']);
        }

        $schoolyear = Schoolyear::where('school_id', $import->school_id)->findOrFail($import->schoolyear_id);
        $scopes = $this->replacementScopesFor($import);
        $range = $operation === 'replace' ? collect($scopes)->firstWhere('key', $scope) : null;
        $comparison = $this->compareSource($import, $schoolyear, $operation, $range);
        $plausibility = $this->datePlausibilityFor($import);

        if (! $plausibility['is_plausible']) {
            $comparison['can_confirm'] = false;
            $comparison['message'] = $plausibility['message'];
        }

        return [...$comparison, 'replacement_scopes' => $scopes];
    }

    /**
     * @param  array{key: string, label: string, from: string, until: string}|null  $range
     * @return array<string, mixed>
     */
    private function compareSource(TimetableImport $import, Schoolyear $schoolyear, string $operation, ?array $range): array
    {
        $path = storage_path($import->file_path);
        $rows = [];
        foreach ($this->readNormalizedLines($path) ?? [] as $index => $line) {
            $parts = explode("\t", $line);
            if (trim($parts[0] ?? '') === 'TT' && $this->isImportableTimetableRecord($parts)) {
                $rows[] = $this->timetableEntryPayload($import, $schoolyear, $parts, $line, $index + 1);
            }
        }

        return app(TimetableImportComparisonService::class)->compare($import, $rows, $this->analyzeFile($path), $operation, $range);
    }

    public function confirmPreview(TimetableImport $import, string $mode = 'strict', string $operation = 'merge', ?string $scope = null, ?string $fingerprint = null): TimetableImport
    {
        return DB::transaction(function () use ($import, $mode, $operation, $scope, $fingerprint): TimetableImport {
            $this->lockSchoolyear($import);
            $this->assertNoPendingImport($import);

            return $this->confirmLockedPreview($import, $mode, $operation, $scope, $fingerprint);
        });
    }

    private function confirmLockedPreview(TimetableImport $import, string $mode, string $operation, ?string $scope, ?string $fingerprint): TimetableImport
    {
        if (! in_array($mode, ['strict', 'partial'], true)) {
            throw ValidationException::withMessages(['mode' => 'Bitte wählen Sie Vollimport oder Teilimport.']);
        }

        $analysis = $this->analyzeFile(storage_path($import->file_path));

        if ($this->importableTimetableRows($analysis) === 0) {
            throw ValidationException::withMessages([
                'file' => 'Die Vorschau kann nicht importiert werden: Die Quelldatei fehlt, ist unlesbar oder enthält keine gültigen Stundenplan-Einträge.',
            ]);
        }

        if ($analysis['tt_skipped_invalid'] > 0 && $mode !== 'partial') {
            throw ValidationException::withMessages([
                'file' => 'Semantische Prüfung fehlgeschlagen: '.$analysis['tt_skipped_invalid'].' TT-Datensätze entsprechen nicht dem erwarteten Format. Korrigieren Sie die Quelldatei; bestehende Daten wurden nicht verändert.',
            ]);
        }

        $schoolyear = Schoolyear::query()
            ->where('school_id', $import->school_id)
            ->find($import->schoolyear_id);
        $datePlausibility = $this->datePlausibility(
            $schoolyear,
            $analysis['tt_first_date'],
            $analysis['tt_last_date'],
        );

        if (! $datePlausibility['is_plausible']) {
            throw ValidationException::withMessages([
                'file' => $datePlausibility['message'],
            ]);
        }

        $comparison = $this->comparisonFor($import, $operation, $scope);
        if (! $comparison['can_confirm']) {
            throw ValidationException::withMessages(['operation' => $comparison['message']]);
        }

        if (($operation === 'replace' || $fingerprint !== null)
            && (! $fingerprint || ! hash_equals($comparison['fingerprint'], $fingerprint))) {
            throw ValidationException::withMessages(['fingerprint' => 'Der Plan oder die Datei hat sich geändert. Bitte prüfen Sie die aktualisierte Vorschau erneut.']);
        }

        $updated = TimetableImport::query()
            ->whereKey($import->id)
            ->where('import_status', 'preview')
            ->update([
                ...$analysis,
                'import_mode' => $mode,
                'import_operation' => $operation,
                'replacement_scope' => $comparison['scope']['key'] ?? null,
                'replacement_from' => $comparison['scope']['from'] ?? null,
                'replacement_until' => $comparison['scope']['until'] ?? null,
                'comparison_fingerprint' => $fingerprint !== null ? $comparison['fingerprint'] : null,
                'change_summary' => $this->changeSummary($comparison),
                'tt_imported_rows' => 0,
                'import_status' => 'pending',
                'progress_current' => 0,
                'progress_total' => 0,
                'import_message' => $mode === 'partial'
                    ? 'Import wartet auf Verarbeitung. Fehlerhafte TT-Einträge werden übersprungen.'
                    : 'Import wartet auf Verarbeitung.',
                'import_error' => null,
                'started_at' => null,
                'finished_at' => null,
            ]);

        if ($updated !== 1) {
            throw ValidationException::withMessages([
                'import' => 'Diese Vorschau wurde bereits bestätigt oder gelöscht.',
            ]);
        }

        ProcessTimetableImportJob::dispatch((int) $import->id)->afterCommit();

        return $import->refresh();
    }

    public function deletePreview(TimetableImport $import): void
    {
        $sourcePath = PrivateImportSourceFile::resolve(
            $import->file_path,
            "app/private/{$import->school_id}/timetable-imports/{$import->schoolyear_id}",
        );

        $deleted = TimetableImport::query()
            ->whereKey($import->id)
            ->where('import_status', 'preview')
            ->delete();

        if ($deleted !== 1) {
            throw ValidationException::withMessages([
                'import' => 'Nur eine noch nicht bestätigte Vorschau kann direkt gelöscht werden.',
            ]);
        }

        if ($sourcePath !== null) {
            @unlink($sourcePath);
        }
    }

    public function processImport(TimetableImport $import): TimetableImport
    {
        try {
            $result = DB::transaction(function () use ($import): TimetableImport {
                $this->lockSchoolyear($import);
                $current = $import->fresh();
                if (! $current || ! in_array($current->import_status, ['pending', 'running', 'failed'], true)) {
                    return $current ?? $import;
                }

                return $this->processImportRun($current);
            });

        } catch (Throwable $exception) {
            if ($import->fresh()) {
                $this->markFailed($import, 'Import fehlgeschlagen. Änderungen dieses Laufs wurden zurückgerollt: '.mb_substr($exception->getMessage(), 0, 150));
            }

            throw $exception;
        }

        StudentTimetableOverviewService::forgetCacheFor((int) $import->school_id, (int) $import->schoolyear_id);

        return $result;
    }

    private function processImportRun(TimetableImport $import, bool $replaying = false): TimetableImport
    {
        $schoolyear = Schoolyear::where('school_id', $import->school_id)->find($import->schoolyear_id);

        if (! $schoolyear || ! $schoolyear->sem_2_start) {
            $this->markFailed($import, 'Semester 2 beginnt am muss gesetzt sein, bevor Sie eine TXT-Datei importieren.');

            return $import->refresh();
        }

        $filePath = storage_path($import->file_path);
        $totalLines = $this->countNormalizedLines($filePath);
        if ($totalLines === null) {
            $this->markFailed($import, 'Die TXT-Datei konnte nicht gelesen werden.');

            return $import->refresh();
        }

        $lines = $this->readNormalizedLines($filePath);
        if ($lines === null) {
            $this->markFailed($import, 'Die TXT-Datei konnte nicht gelesen werden.');

            return $import->refresh();
        }

        $analysis = $this->analyzeFile($filePath);
        if ($this->importableTimetableRows($analysis) === 0) {
            $this->markFailed(
                $import,
                'Die TXT-Datei enthält keine gültigen Stundenplan-Einträge. Bestehende Daten wurden nicht verändert.',
                $analysis,
            );

            return $import->refresh();
        }

        if ($analysis['tt_skipped_invalid'] > 0 && ! $import->isPartialImport()) {
            $this->markFailed(
                $import,
                'Semantische Prüfung fehlgeschlagen: '.$analysis['tt_skipped_invalid'].' TT-Datensätze entsprechen nicht dem erwarteten Format. Bestehende Daten wurden nicht verändert.',
                $analysis,
            );

            return $import->refresh();
        }

        $datePlausibility = $this->datePlausibility(
            $schoolyear,
            $analysis['tt_first_date'],
            $analysis['tt_last_date'],
        );

        if (! $datePlausibility['is_plausible']) {
            $this->markFailed($import, $datePlausibility['message'], $analysis);

            return $import->refresh();
        }

        $this->markRunning($import, $totalLines);

        try {
            $result = DB::transaction(function () use ($import, $schoolyear, $lines, $totalLines, $replaying): TimetableImport {
                $this->lockSchoolyear($import);
                $range = $import->import_operation === 'replace' ? [
                    'key' => $import->replacement_scope,
                    'label' => match ($import->replacement_scope) {
                        'semester1' => '1. Semester',
                        'semester2' => '2. Semester',
                        default => 'Ganzes Schuljahr',
                    },
                    'from' => $import->replacement_from?->toDateString(),
                    'until' => $import->replacement_until?->toDateString(),
                ] : null;

                if ($range && (! $range['from'] || ! $range['until'])) {
                    throw ValidationException::withMessages(['import' => 'Plan ersetzen benötigt einen bestätigten Zeitraum.']);
                }

                $comparison = ! $replaying && $import->comparison_fingerprint
                    ? $this->comparisonFor($import, $import->import_operation, $import->replacement_scope)
                    : $this->compareSource($import, $schoolyear, $import->import_operation, $range);
                if (! $comparison['can_confirm']) {
                    throw ValidationException::withMessages(['import' => $comparison['message']]);
                }

                if (! $replaying && $import->comparison_fingerprint
                    && ! hash_equals($import->comparison_fingerprint, $comparison['fingerprint'])) {
                    throw ValidationException::withMessages(['import' => 'Der Plan oder die Datei hat sich seit der Vorschau geändert. Bitte laden Sie die Datei erneut hoch und prüfen Sie die Änderungen.']);
                }

                if (! $replaying && $range && ! $import->comparison_fingerprint) {
                    throw ValidationException::withMessages(['import' => 'Plan ersetzen benötigt eine bestätigte Änderungsvorschau.']);
                }

                $result = $this->persistTimetableRows($import, $schoolyear, $lines, $totalLines);
                if ($range) {
                    StudentTimetableEntry::current()
                        ->where('school_id', $import->school_id)
                        ->where('schoolyear_id', $import->schoolyear_id)
                        ->whereBetween('date', [$range['from'], $range['until']])
                        ->where('timetable_import_id', '!=', $import->id)
                        ->update(['superseded_by_import_id' => $import->id]);
                }

                $result->update(['change_summary' => $this->changeSummary($comparison)]);

                return $result;
            });
        } catch (ValidationException $exception) {
            $this->markFailed($import, $exception->getMessage());

            return $import->refresh();
        } catch (Throwable $exception) {
            $this->markFailed($import, 'Import fehlgeschlagen. Änderungen dieses Laufs wurden zurückgerollt: '.mb_substr($exception->getMessage(), 0, 150));

            throw $exception;
        }

        return $result;
    }

    /** @param iterable<int, string> $lines */
    private function persistTimetableRows(TimetableImport $import, Schoolyear $schoolyear, iterable $lines, int $totalLines): TimetableImport
    {

        $sections = [];
        $ttCourses = [];
        $ttSkippedInvalid = 0;
        $ttImportedRows = 0;
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
                $ttImportedRows++;
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

        if ($ttImportedRows === 0 || ($ttSkippedInvalid > 0 && ! $import->isPartialImport())) {
            throw new RuntimeException('Die gelesenen TT-Datensätze erfüllen die gewählte Importart nicht.');
        }

        ksort($sections);

        $import->update($this->importCompletionPayload([
            'sections' => $sections,
            'total_lines' => $totalLines,
            'tt_courses' => count($ttCourses),
            'tt_skipped_invalid' => $ttSkippedInvalid,
            'tt_imported_rows' => $ttImportedRows,
            'tt_first_date' => $ttFirstDate,
            'tt_last_date' => $ttLastDate,
            'import_status' => 'completed',
            'progress_current' => $totalLines,
            'progress_total' => $totalLines,
            'import_message' => match (true) {
                $import->import_operation === 'replace' => "Plan ersetzt: {$ttImportedRows} TT-Einträge verarbeitet, {$ttSkippedInvalid} fehlerhafte TT-Einträge übersprungen.",
                $import->isPartialImport() => "Teilimport abgeschlossen: {$ttImportedRows} TT-Datensätze verarbeitet, {$ttSkippedInvalid} nach aktuellen Prüfregeln ausgelassen. Übriger Bestand beibehalten.",
                default => 'Import abgeschlossen.',
            },
            'import_error' => null,
            'finished_at' => now(),
        ]));

        return $import->refresh();
    }

    public function queueUnimport(TimetableImport $import): TimetableImport
    {
        return DB::transaction(function () use ($import): TimetableImport {
            $this->lockSchoolyear($import);
            $this->assertNoPendingImport($import);

            return $this->queueLockedUnimport($import);
        });
    }

    private function queueLockedUnimport(TimetableImport $import): TimetableImport
    {
        $import->refresh();
        if (! in_array($import->import_status, ['completed', 'failed'], true)) {
            throw ValidationException::withMessages(['import' => 'Dieser Import wird bereits verarbeitet oder wurde bereits zurückgenommen.']);
        }

        $this->assertRemainingImportsCanBeReplayed($import);

        $import->update([
            'import_status' => 'deleting',
            'progress_current' => 0,
            'progress_total' => 0,
            'import_message' => 'Import wird gelöscht. Der aktive Stundenplan wird anschließend neu aufgebaut.',
            'import_error' => null,
            'started_at' => now(),
            'finished_at' => null,
        ]);

        ProcessTimetableUnimportJob::dispatch((int) $import->id)->afterCommit();

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

        $this->assertRemainingImportsCanBeReplayed($import);

        $remainingImports = TimetableImport::where('school_id', $schoolId)
            ->where('schoolyear_id', $schoolyearId)
            ->whereKeyNot($importId)
            ->where('import_status', 'completed')
            ->orderBy('imported_at')
            ->orderBy('id')
            ->get();

        $this->assertImportsCanBeReplayed($remainingImports);

        DB::transaction(function () use ($import, $remainingImports, $schoolId, $schoolyearId): void {
            $this->lockSchoolyear($import);
            $this->assertRemainingImportsCanBeReplayed($import);
            $remainingImports = TimetableImport::where('school_id', $schoolId)
                ->where('schoolyear_id', $schoolyearId)
                ->whereKeyNot($import->id)
                ->where('import_status', 'completed')
                ->orderBy('imported_at')
                ->orderBy('id')
                ->get();
            $this->assertImportsCanBeReplayed($remainingImports);
            $inactiveIdentities = StudentTimetableEntry::where('school_id', $schoolId)
                ->where('schoolyear_id', $schoolyearId)
                ->where('is_active', false)
                ->pluck('identity_hash');
            StudentTimetableEntry::where('school_id', $schoolId)
                ->where('schoolyear_id', $schoolyearId)
                ->delete();

            $import->delete();

            $remainingImports->each(function (TimetableImport $remainingImport): void {
                $replayedImport = $this->processImportRun($remainingImport, true);

                if ($replayedImport->import_status !== 'completed') {
                    throw new RuntimeException(
                        $replayedImport->import_error
                            ?: "Der verbleibende Import {$replayedImport->original_filename} konnte nicht wiederhergestellt werden.",
                    );
                }
            });

            foreach ($inactiveIdentities->chunk(500) as $identities) {
                StudentTimetableEntry::where('school_id', $schoolId)
                    ->where('schoolyear_id', $schoolyearId)
                    ->whereIn('identity_hash', $identities)
                    ->update(['is_active' => false]);
            }
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
            'active_entries' => StudentTimetableEntry::current()->where('school_id', $schoolId)
                ->where('schoolyear_id', $schoolyearId)
                ->count(),
        ];
    }

    public function ensureSemesterTwoStart(User $user, ?int $schoolyearId): void
    {
        $this->schoolyearWithSemesterTwoStart($user, $schoolyearId);
    }

    /**
     * @param  array{sections: array<string, int>, total_lines: int, tt_courses: int, tt_skipped_invalid: int, tt_first_date: ?string, tt_last_date: ?string}|null  $analysis
     */
    private function createImportRecord(
        User $user,
        string $storedFilename,
        string $originalFilename,
        string $filePath,
        Schoolyear $schoolyear,
        ?array $analysis = null,
        string $status = 'pending',
    ): TimetableImport {
        return DB::transaction(function () use ($user, $storedFilename, $originalFilename, $filePath, $schoolyear, $analysis, $status): TimetableImport {
            $analysis ??= [
                'sections' => [],
                'total_lines' => 0,
                'tt_courses' => 0,
                'tt_skipped_invalid' => 0,
                'tt_first_date' => null,
                'tt_last_date' => null,
            ];

            return TimetableImport::create($this->importCreationPayload([
                'school_id' => $user->school_id,
                'schoolyear_id' => $schoolyear->id,
                'user_id' => $user->id,
                'original_filename' => $originalFilename,
                'stored_filename' => $storedFilename,
                'file_path' => $filePath,
                ...$analysis,
                'import_status' => $status,
                'progress_current' => 0,
                'progress_total' => 0,
                'import_message' => $status === 'preview'
                    ? 'Vorimport geprüft. Die Daten wurden noch nicht übernommen.'
                    : 'Import wartet auf Verarbeitung.',
                'import_error' => null,
                'imported_at' => now(),
                'started_at' => null,
                'finished_at' => null,
            ]));
        });
    }

    private function lockSchoolyear(TimetableImport $import): void
    {
        Schoolyear::where('school_id', $import->school_id)
            ->whereKey($import->schoolyear_id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function assertNoPendingImport(TimetableImport $import): void
    {
        if (TimetableImport::where('school_id', $import->school_id)
            ->where('schoolyear_id', $import->schoolyear_id)
            ->whereKeyNot($import->id)
            ->whereIn('import_status', ['pending', 'running', 'deleting'])
            ->exists()) {
            throw ValidationException::withMessages(['import' => 'Für dieses Schuljahr läuft bereits ein Import oder eine Rücknahme. Bitte warten Sie auf den Abschluss.']);
        }
    }

    /** @param array<string, mixed> $comparison
     * @return array<string, int>
     */
    private function changeSummary(array $comparison): array
    {
        return collect($comparison)->only([
            'new_entries', 'updated_entries', 'unchanged_entries', 'removed_entries', 'removed_appointment_count',
        ])->all();
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
            'starts_at' => $this->nullableColumn($parts[4] ?? null),
            'ends_at' => $this->nullableColumn($parts[5] ?? null),
            'subject' => $this->nullableColumn($parts[8] ?? null),
            'class_name' => $className,
            'course' => $this->nullableColumn($parts[8] ?? null),
            'module_code' => $this->moduleCodeFromClassName($className),
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
                    'class_name' => $row['class_name'],
                    'course' => $row['course'],
                    'module_code' => $row['module_code'],
                    'is_active' => $row['is_active'],
                    'superseded_by_import_id' => null,
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
                'class_name',
                'course',
                'module_code',
                'superseded_by_import_id',
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
        ]));
    }

    /**
     * @return array{
     *     is_plausible: bool,
     *     status: string,
     *     schoolyear_label: ?string,
     *     schoolyear_from: ?string,
     *     schoolyear_until: ?string,
     *     data_from: ?string,
     *     data_until: ?string,
     *     message: string
     * }
     */
    private function datePlausibility(?Schoolyear $schoolyear, ?string $dataFrom, ?string $dataUntil): array
    {
        $schoolyearLabel = $schoolyear
            ? trim((string) ($schoolyear->concerns ?: $schoolyear->name))
            : null;
        $schoolyearFrom = $schoolyear ? $this->dateString($schoolyear->from) : null;
        $schoolyearUntil = $schoolyear ? $this->dateString($schoolyear->until) : null;

        $payload = [
            'is_plausible' => false,
            'status' => 'invalid',
            'schoolyear_label' => $schoolyearLabel ?: null,
            'schoolyear_from' => $schoolyearFrom,
            'schoolyear_until' => $schoolyearUntil,
            'data_from' => $dataFrom,
            'data_until' => $dataUntil,
        ];

        if (! $schoolyear) {
            return [
                ...$payload,
                'status' => 'schoolyear_missing',
                'message' => 'Das gewählte Schuljahr wurde nicht gefunden. Der Import ist gesperrt.',
            ];
        }

        if (! $schoolyearFrom || ! $schoolyearUntil || $schoolyearFrom > $schoolyearUntil) {
            return [
                ...$payload,
                'status' => 'schoolyear_dates_missing',
                'message' => 'Für das gewählte Schuljahr sind Beginn und Ende nicht vollständig oder nicht plausibel hinterlegt. Der Import ist gesperrt.',
            ];
        }

        if (! $dataFrom || ! $dataUntil || $dataFrom > $dataUntil) {
            return [
                ...$payload,
                'status' => 'data_dates_missing',
                'message' => 'Aus den gültigen TT-Einträgen konnte kein plausibler Datenzeitraum ermittelt werden. Der Import ist gesperrt.',
            ];
        }

        $formattedDataRange = $this->formattedDateRange($dataFrom, $dataUntil);
        $formattedSchoolyearRange = $this->formattedDateRange($schoolyearFrom, $schoolyearUntil);
        $formattedSchoolyearLabel = $schoolyearLabel !== '' ? " {$schoolyearLabel}" : '';

        if ($dataFrom < $schoolyearFrom || $dataUntil > $schoolyearUntil) {
            return [
                ...$payload,
                'status' => 'outside_schoolyear',
                'message' => "Der Datenzeitraum {$formattedDataRange} liegt nicht vollständig im ausgewählten Schuljahr{$formattedSchoolyearLabel} ({$formattedSchoolyearRange}). Der Import ist gesperrt.",
            ];
        }

        return [
            ...$payload,
            'is_plausible' => true,
            'status' => 'plausible',
            'message' => "Der Datenzeitraum {$formattedDataRange} liegt vollständig im ausgewählten Schuljahr{$formattedSchoolyearLabel} ({$formattedSchoolyearRange}).",
        ];
    }

    private function dateString(mixed $date): ?string
    {
        if ($date instanceof CarbonInterface) {
            return $date->toDateString();
        }

        if (! is_string($date)) {
            return null;
        }

        return $this->normalizeDate($date);
    }

    private function formattedDateRange(string $from, string $until): string
    {
        return $this->formattedDate($from).' – '.$this->formattedDate($until);
    }

    private function formattedDate(string $date): string
    {
        return substr($date, 8, 2).'.'.substr($date, 5, 2).'.'.substr($date, 0, 4);
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
        return $this->timetableRecordErrors($parts) === [];
    }

    /**
     * @param  list<string>  $parts
     * @return list<array{column: int, field: string, value: ?string, reason: string, expected: string}>
     */
    private function timetableRecordErrors(array $parts): array
    {
        $errors = [];

        if (trim($parts[1] ?? '') === '0') {
            $errors[] = [
                'column' => 2,
                'field' => 'Quellkennung',
                'value' => $parts[1],
                'reason' => 'Der Importer lässt Datensätze mit dem Wert 0 in Feld 2 nach den aktuellen Prüfregeln aus.',
                'expected' => 'Für die aktuelle Zuordnung: ein Wert ungleich 0 in Feld 2.',
            ];
        }

        if ($this->normalizeDate($parts[2] ?? null) === null) {
            $errors[] = [
                'column' => 3,
                'field' => 'Datum',
                'value' => $parts[2] ?? null,
                'reason' => 'Das Datum fehlt oder entspricht keinem unterstützten Datumsformat.',
                'expected' => 'JJJJMMTT oder JJJJ-MM-TT, z. B. 20260914 oder 2026-09-14.',
            ];
        }

        foreach ([4 => 'Beginn', 5 => 'Ende'] as $index => $field) {
            if ($this->isTimeColumn($parts[$index] ?? null)) {
                continue;
            }

            $errors[] = [
                'column' => $index + 1,
                'field' => $field,
                'value' => $parts[$index] ?? null,
                'reason' => 'Die Zeit fehlt oder entspricht keinem unterstützten Zeitformat.',
                'expected' => 'H:MM oder HH:MM mit Doppelpunkt, z. B. 8:00 oder 08:00.',
            ];
        }

        if ($this->timetableCourseName($parts) === '') {
            $errors[] = [
                'column' => 8,
                'field' => 'Kurs-/Klassenbezeichnung',
                'value' => $parts[7] ?? null,
                'reason' => 'Die Kurs-/Klassenbezeichnung fehlt oder enthält nur Leerzeichen; der TT-Datensatz kann keinem Kurs zugeordnet werden.',
                'expected' => 'Eine nicht leere Kurs-/Klassenbezeichnung in Feld 8.',
            ];
        }

        return $errors;
    }

    /**
     * @param  array{sections: array<string, int>, total_lines: int, tt_courses: int, tt_skipped_invalid: int, tt_first_date: ?string, tt_last_date: ?string}  $analysis
     */
    private function importableTimetableRows(array $analysis): int
    {
        return max(0, (int) ($analysis['sections']['TT'] ?? 0) - $analysis['tt_skipped_invalid']);
    }

    private function deleteSourceFile(int $schoolId, int $schoolyearId, string $filePath): void
    {
        $sourcePath = PrivateImportSourceFile::resolve(
            $filePath,
            "app/private/{$schoolId}/timetable-imports/{$schoolyearId}",
        );

        if ($sourcePath !== null) {
            @unlink($sourcePath);
        }
    }

    private function assertRemainingImportsCanBeReplayed(TimetableImport $import): void
    {
        $remainingImports = TimetableImport::query()
            ->where('school_id', $import->school_id)
            ->where('schoolyear_id', $import->schoolyear_id)
            ->whereKeyNot($import->id)
            ->orderBy('imported_at')
            ->orderBy('id')
            ->get();

        $this->assertImportsCanBeReplayed($remainingImports);
    }

    /**
     * @param  iterable<int, TimetableImport>  $imports
     */
    private function assertImportsCanBeReplayed(iterable $imports): void
    {
        foreach ($imports as $import) {
            if (in_array($import->import_status, ['pending', 'running', 'deleting'], true)) {
                throw ValidationException::withMessages([
                    'imports' => "Der Import {$import->original_filename} wird gerade verarbeitet. Es wurde nichts gelöscht.",
                ]);
            }

            if ($import->import_status !== 'completed') {
                continue;
            }

            $filePath = storage_path($import->file_path);
            $analysis = $this->analyzeFile($filePath);

            if (! is_file($filePath) || $this->importableTimetableRows($analysis) === 0) {
                throw ValidationException::withMessages([
                    'imports' => "Der verbleibende Import {$import->original_filename} kann nicht wiederhergestellt werden: Die Quelldatei fehlt, ist unlesbar oder enthält keine gültigen Stundenplan-Einträge. Es wurde nichts gelöscht.",
                ]);
            }

            if ($analysis['tt_skipped_invalid'] > 0 && ! $import->isPartialImport()) {
                throw ValidationException::withMessages([
                    'imports' => "Der verbleibende Vollimport {$import->original_filename} enthält nicht zuordenbare TT-Datensätze. Es wurde nichts gelöscht.",
                ]);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function importCreationPayload(array $payload): array
    {
        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function importCompletionPayload(array $payload): array
    {
        return $payload;
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
     * @return iterable<int, string>|null
     */
    private function readNormalizedLines(string $filePath, bool $preserveSourceLineNumbers = false): ?iterable
    {
        $handle = @fopen($filePath, 'rb');
        if ($handle === false) {
            return null;
        }

        return (function () use ($handle, $preserveSourceLineNumbers): iterable {
            $sourceIndex = -1;
            $recordIndex = 0;
            try {
                while (($line = fgets($handle)) !== false) {
                    $sourceIndex++;
                    $line = rtrim($line, "\r\n");
                    if ($line === '') {
                        continue;
                    }

                    yield ($preserveSourceLineNumbers ? $sourceIndex : $recordIndex++) => $this->toUtf8($line);
                }
            } finally {
                fclose($handle);
            }
        })();
    }

    private function countNormalizedLines(string $filePath): ?int
    {
        $lines = $this->readNormalizedLines($filePath);
        if ($lines === null) {
            return null;
        }

        $count = 0;
        foreach ($lines as $line) {
            $count++;
        }

        return $count;
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
            'tt_imported_rows' => 0,
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

    /** @param  array<string, mixed>  $context */
    private function markFailed(TimetableImport $import, string $message, array $context = []): void
    {
        $import->update([
            ...$context,
            'tt_imported_rows' => 0,
            'import_status' => 'failed',
            'import_message' => $message,
            'import_error' => $message,
            'finished_at' => now(),
        ]);
    }
}
