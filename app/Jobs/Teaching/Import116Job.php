<?php

namespace App\Jobs\Teaching;

use App\Events\Import116FinishedEvent;
use App\Models\Import116;
use App\Models\Import116Run;
use App\Models\Import116RunChange;
use App\Models\SchoolTool;
use App\Models\User;
use App\Models\UserGroupMember;
use App\Services\StudentsTimetables\StudentTimetableStudySelectionRefreshService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PDO;
use RuntimeException;
use Spatie\SimpleExcel\SimpleExcelReader;

class Import116Job implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;

    public bool $failOnTimeout = true;

    /** @var array<string, bool> */
    private array $availableTables = [];

    private const BATCH_SIZE = 500;

    private const PLACEHOLDER_PASSWORD_ROUNDS = 4;

    private const OPTIONAL_CONTACT_FIELDS = [
        'email',
        'phone_1',
        'phone_2',
        'mother_name',
        'mother_email',
        'mother_phone_1',
        'mother_phone_2',
        'father_name',
        'father_email',
        'father_phone_1',
        'father_phone_2',
    ];

    public function __construct(public $user, public string $path, public ?int $schoolyearId = null, public ?string $originalFilename = null)
    {
        $this->onQueue('imports');
    }

    public function handle(?StudentTimetableStudySelectionRefreshService $studySelectionRefreshService = null): void
    {
        $studySelectionRefreshService ??= app(StudentTimetableStudySelectionRefreshService::class);
        $schoolId = (int) $this->user->school_id;
        $schoolyearId = $this->schoolyearId ?? $this->user->schoolyear_id;
        $run = $this->createRun($schoolId, $schoolyearId);
        $fullPath = storage_path($this->path);

        if (! is_file($fullPath)) {
            $this->markRunFailed($run, 'Import 116 fehlgeschlagen: Datei nicht gefunden.');
            broadcast(new Import116FinishedEvent(
                404,
                $this->user->id,
                'Import 116 fehlgeschlagen: Datei nicht gefunden.',
                ['path' => $this->path, 'run_id' => $run?->id]
            ));

            return;
        }

        $reader = SimpleExcelReader::create($fullPath);
        $headers = $reader->getHeaders();
        $headerMapping = $this->mapHeaders($headers);

        if ($headerMapping === false) {
            $this->markRunFailed($run, 'Import 116 fehlgeschlagen: Spaltenüberschriften nicht erkannt.');
            broadcast(new Import116FinishedEvent(
                422,
                $this->user->id,
                'Import 116 fehlgeschlagen: Spaltenüberschriften nicht erkannt.',
                ['run_id' => $run?->id]
            ));

            return;
        }

        $report = ['counts' => $this->emptyReportCounts()];
        $stagingTable = $this->newStagingTableName();
        $stagingTableCreated = false;

        try {
            $now = now();
            $this->createStagingTable($stagingTable);
            $stagingTableCreated = true;
            $stagingCounts = $this->stageStudentRows(
                $reader->getRows(),
                $headerMapping,
                $schoolId,
                $schoolyearId,
                $now,
                $stagingTable,
            );
            $report['counts']['processed_rows'] = $stagingCounts['processed_rows'];
            $report['counts']['seen_students'] = $stagingCounts['seen_students'];

            if ($stagingCounts['seen_students'] === 0) {
                $message = 'Import 116 fehlgeschlagen: Die Datei enthält keine gültigen Schülerdaten. Bestehende Daten wurden nicht verändert.';
                $this->markRunFailed($run, $message);
                broadcast(new Import116FinishedEvent(
                    422,
                    $this->user->id,
                    $message,
                    ['run_id' => $run?->id, 'counts' => $report['counts']],
                ));

                return;
            }

            $initialReportCounts = $report['counts'];

            DB::transaction(function () use (
                $stagingTable,
                $schoolId,
                $schoolyearId,
                $run,
                $now,
                $initialReportCounts,
                $studySelectionRefreshService,
                &$report,
            ): void {
                $report['counts'] = $initialReportCounts;

                if ($run) {
                    $run->changes()->delete();
                }

                foreach ($this->aggregatedStagedStudentBatches($stagingTable) as $students) {
                    $studentCodes = array_keys($students);
                    $baselineSnapshots = $this->loadSnapshotsByStudentCode($schoolId, $schoolyearId, $studentCodes);
                    $this->persistAggregatedStudents(
                        $students,
                        $baselineSnapshots,
                        $schoolId,
                        $schoolyearId,
                        $now,
                    );

                    $recordsByStudentCode = $this->loadCurrentRecordsByStudentCode($schoolId, $schoolyearId, $studentCodes);
                    $this->synchronizeImportedRecords(
                        $students,
                        $recordsByStudentCode,
                        $schoolId,
                        $schoolyearId,
                    );

                    $finalSnapshots = $this->loadSnapshotsByStudentCode($schoolId, $schoolyearId, $studentCodes);
                    $batchReport = $this->buildRunReport($baselineSnapshots, $finalSnapshots, 0, 0);
                    $this->mergeReportCounts($report['counts'], $batchReport['counts']);
                    $this->storeRunChanges($run, $batchReport['changes'], $schoolId, $schoolyearId);
                }

                $this->deleteMissingImport116Rows(
                    $schoolId,
                    $schoolyearId,
                    $stagingTable,
                    $run,
                    $report['counts'],
                );

                $this->completeRun($run, $report['counts']);

                $schoolTool = SchoolTool::firstOrCreate(['school_id' => $schoolId]);
                $schoolTool->import_166_at = $now;
                $schoolTool->save();

                if ($schoolyearId) {
                    $studySelectionRefreshService->refreshForUser($this->user, (int) $schoolyearId);
                }
            }, attempts: 3);
        } catch (\Throwable $e) {
            $this->markRunFailed($run, 'Import 116 fehlgeschlagen: '.$e->getMessage());

            broadcast(new Import116FinishedEvent(
                500,
                $this->user->id,
                'Import 116 fehlgeschlagen.',
                ['error' => $e->getMessage(), 'run_id' => $run?->id]
            ));

            throw $e;
        } finally {
            if ($stagingTableCreated) {
                Schema::dropIfExists($stagingTable);
            }
        }

        broadcast(new Import116FinishedEvent(
            200,
            $this->user->id,
            'Import 116 wurde abgeschlossen.',
            [
                'run_id' => $run?->id,
                'created' => (int) ($report['counts']['inserted'] ?? 0),
                'updated' => (int) ($report['counts']['updated'] ?? 0),
                'deleted' => (int) ($report['counts']['deleted'] ?? 0),
                'counts' => $report['counts'] ?? [],
            ]
        ));
    }

    /**
     * @param  iterable<int, array<string, mixed>>  $rows
     * @param  array<string, string>  $headerMapping
     * @return array{processed_rows: int, seen_students: int}
     */
    private function stageStudentRows(
        iterable $rows,
        array $headerMapping,
        int $schoolId,
        ?int $schoolyearId,
        Carbon $now,
        string $stagingTable,
    ): array {
        $batch = [];
        $processedRows = 0;

        foreach ($rows as $row) {
            $data = $this->mappedStudentRow($row, $headerMapping, $schoolId, $schoolyearId, $now);
            if ($data === null) {
                continue;
            }

            $processedRows++;
            $batch[] = [
                'student_code' => $data['student_code'],
                'row_sequence' => $processedRows,
                'payload' => json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            ];

            if (count($batch) >= self::BATCH_SIZE) {
                DB::table($stagingTable)->insert($batch);
                $batch = [];
            }
        }

        if ($batch !== []) {
            DB::table($stagingTable)->insert($batch);
        }

        return [
            'processed_rows' => $processedRows,
            'seen_students' => DB::table($stagingTable)->distinct()->count('student_code'),
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, string>  $headerMapping
     * @return array<string, mixed>|null
     */
    private function mappedStudentRow(
        array $row,
        array $headerMapping,
        int $schoolId,
        ?int $schoolyearId,
        Carbon $now,
    ): ?array {
        $mapped = [];
        foreach ($headerMapping as $originalHeader => $field) {
            $mapped[$field] = isset($row[$originalHeader]) ? $this->normalizeCell($row[$originalHeader]) : null;
        }

        $studentCode = $mapped['student_code'] ?? null;
        $class = $mapped['class'] ?? null;
        $lastName = $mapped['last_name'] ?? null;
        $firstName = $mapped['first_name'] ?? null;

        if (! $studentCode || ! $class || ! $lastName || ! $firstName) {
            return null;
        }

        $data = [
            'school_id' => $schoolId,
            'schoolyear_id' => $schoolyearId,
            'class' => $class,
            'school_level' => $mapped['school_level'] ?? null,
            'attendance_year' => $mapped['attendance_year'] ?? null,
            'religion' => $mapped['religion'] ?? null,
            'student_code' => $studentCode,
            'last_name' => $lastName,
            'first_name' => $firstName,
            'sex' => $mapped['sex'] ?? null,
            'birth_date' => $this->parseDate($mapped['birth_date'] ?? null),
            'import_date' => $now->toDateTimeString(),
            'exists_date' => $now->toDateTimeString(),
            'import_user_id' => $this->user->id,
        ];

        $addressType = $this->normalizeAddressType($mapped['address_type'] ?? null);
        if ($this->isStudentAddressType($addressType)) {
            $this->setIfPresent($data, 'email', $mapped['email'] ?? null);
            $this->setIfPresent($data, 'phone_1', $mapped['phone_1'] ?? null);
            $this->setIfPresent($data, 'phone_2', $mapped['phone_2'] ?? null);
        } elseif (str_starts_with($addressType, 'vater')) {
            $this->setIfPresent($data, 'father_name', $mapped['address_name'] ?? null);
            $this->setIfPresent($data, 'father_email', $mapped['email'] ?? null);
            $this->setIfPresent($data, 'father_phone_1', $mapped['phone_1'] ?? null);
            $this->setIfPresent($data, 'father_phone_2', $mapped['phone_2'] ?? null);
        } elseif (str_starts_with($addressType, 'mutter')) {
            $this->setIfPresent($data, 'mother_name', $mapped['address_name'] ?? null);
            $this->setIfPresent($data, 'mother_email', $mapped['email'] ?? null);
            $this->setIfPresent($data, 'mother_phone_1', $mapped['phone_1'] ?? null);
            $this->setIfPresent($data, 'mother_phone_2', $mapped['phone_2'] ?? null);
        }

        return $data;
    }

    protected function newStagingTableName(): string
    {
        return 'import116_stage_'.Str::lower(Str::random(20));
    }

    private function createStagingTable(string $stagingTable): void
    {
        Schema::create($stagingTable, function (Blueprint $table): void {
            $table->temporary();
            $table->id();
            $table->string('student_code');
            $table->unsignedBigInteger('row_sequence');
            $table->longText('payload');
        });
    }

    /**
     * @return \Generator<int, array<string, array<string, mixed>>>
     */
    private function aggregatedStagedStudentBatches(string $stagingTable): \Generator
    {
        $batch = [];
        $currentStudentCode = null;
        $currentStudent = [];

        foreach (DB::table($stagingTable)
            ->select(['student_code', 'payload'])
            ->orderBy('student_code')
            ->orderBy('row_sequence')
            ->lazy(self::BATCH_SIZE) as $stagedRow) {
            $studentCode = (string) $stagedRow->student_code;
            $data = json_decode((string) $stagedRow->payload, true, flags: JSON_THROW_ON_ERROR);

            if ($currentStudentCode !== null && $studentCode !== $currentStudentCode) {
                $batch[$currentStudentCode] = $currentStudent;

                if (count($batch) >= self::BATCH_SIZE) {
                    yield $batch;
                    $batch = [];
                }

                $currentStudent = [];
            }

            $currentStudentCode = $studentCode;
            $currentStudent = array_replace($currentStudent, $data);
        }

        if ($currentStudentCode !== null) {
            $batch[$currentStudentCode] = $currentStudent;
        }

        if ($batch !== []) {
            yield $batch;
        }
    }

    /**
     * @param  array<string, array<string, mixed>>  $students
     * @param  array<string, Import116>  $recordsByStudentCode
     */
    protected function synchronizeImportedRecords(
        array $students,
        array $recordsByStudentCode,
        int $schoolId,
        ?int $schoolyearId,
    ): void {
        [$usersByEmail, $usersById] = $this->prefetchUsersForRecords($schoolId, $recordsByStudentCode);

        foreach ($students as $studentCode => $data) {
            $record = $recordsByStudentCode[$studentCode] ?? null;
            if (! $record) {
                continue;
            }

            if ($record->email) {
                $matchingUser = $usersByEmail[$this->normalizedEmail($record->email)] ?? null;

                if ($matchingUser) {
                    $record->user_id = $matchingUser->id;
                    $record->save();

                    $matchingUser->import116_id = $record->id;
                    $matchingUser->schoolyear_id = $schoolyearId;
                    $matchingUser->save();
                    $usersById[(int) $matchingUser->id] = $matchingUser;
                }
            }

            if ($record->email && $record->user_id) {
                $linkedUser = $usersById[(int) $record->user_id] ?? null;
                if ($linkedUser && $this->isPlaceholderEmail($linkedUser->email)) {
                    $emailOwner = $usersByEmail[$this->normalizedEmail($record->email)] ?? null;
                    $emailTaken = $emailOwner && (int) $emailOwner->id !== (int) $linkedUser->id;
                    if (! $emailTaken) {
                        unset($usersByEmail[$this->normalizedEmail($linkedUser->email)]);
                        $linkedUser->email = $record->email;
                        $linkedUser->save();
                        $usersByEmail[$this->normalizedEmail($linkedUser->email)] = $linkedUser;
                    }
                }
            }

            if (! $record->email && ! $record->user_id) {
                $placeholderEmail = $this->buildPlaceholderEmail($studentCode, $schoolId);
                $placeholderUser = $usersByEmail[$this->normalizedEmail($placeholderEmail)] ?? null;

                if (! $placeholderUser) {
                    $placeholderUser = new User([
                        'school_id' => $schoolId,
                        'schoolyear_id' => $schoolyearId,
                        'email' => $placeholderEmail,
                        'first_name' => $data['first_name'] ?? '',
                        'last_name' => $data['last_name'] ?? '',
                        'schoolclass' => $data['class'] ?? null,
                        'sex' => $data['sex'] ?? null,
                        'password' => Hash::make(str()->random(64), ['rounds' => self::PLACEHOLDER_PASSWORD_ROUNDS]),
                        'import116_id' => $record->id,
                    ]);
                    $placeholderUser->is_active = false;
                    $placeholderUser->save();
                    $usersByEmail[$this->normalizedEmail($placeholderUser->email)] = $placeholderUser;
                    $usersById[(int) $placeholderUser->id] = $placeholderUser;
                }

                $record->user_id = $placeholderUser->id;
                $record->save();

                if ($placeholderUser->import116_id !== $record->id) {
                    $placeholderUser->import116_id = $record->id;
                    $placeholderUser->save();
                }
            }

            $this->syncImport116ReferencesToCurrentRecord($schoolId, $record);
            $this->syncLinkedUsersToCurrentRecord($schoolId, $record);
            $this->syncTeachingCourseStudentsToCurrentRecord($record);
        }
    }

    /**
     * @param  array<string, array<string, mixed>>  $students
     * @param  array<string, array<string, mixed>>  $baselineSnapshots
     */
    private function persistAggregatedStudents(
        array $students,
        array $baselineSnapshots,
        int $schoolId,
        ?int $schoolyearId,
        Carbon $now,
    ): void {
        if ($schoolyearId === null) {
            foreach ($students as $studentCode => $data) {
                Import116::query()->updateOrCreate(
                    [
                        'school_id' => $schoolId,
                        'schoolyear_id' => null,
                        'student_code' => $studentCode,
                    ],
                    $data,
                );
            }

            return;
        }

        $updateColumns = [
            'class',
            'school_level',
            'attendance_year',
            'religion',
            'last_name',
            'first_name',
            'email',
            'phone_1',
            'phone_2',
            'sex',
            'birth_date',
            'mother_name',
            'mother_email',
            'mother_phone_1',
            'mother_phone_2',
            'father_name',
            'father_email',
            'father_phone_1',
            'father_phone_2',
            'import_date',
            'exists_date',
            'import_user_id',
            'updated_at',
        ];
        $rows = [];

        foreach ($students as $studentCode => $data) {
            $baseline = $baselineSnapshots[$studentCode] ?? [];

            foreach (self::OPTIONAL_CONTACT_FIELDS as $field) {
                if (! array_key_exists($field, $data)) {
                    $data[$field] = $baseline[$field] ?? null;
                }
            }

            $rows[] = [
                ...$data,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($rows) >= self::BATCH_SIZE) {
                Import116::query()->upsert(
                    $rows,
                    ['school_id', 'schoolyear_id', 'student_code'],
                    $updateColumns,
                );
                $rows = [];
            }
        }

        if ($rows !== []) {
            Import116::query()->upsert(
                $rows,
                ['school_id', 'schoolyear_id', 'student_code'],
                $updateColumns,
            );
        }
    }

    /**
     * @param  array<int, string>  $studentCodes
     * @return array<string, Import116>
     */
    private function loadCurrentRecordsByStudentCode(
        int $schoolId,
        ?int $schoolyearId,
        array $studentCodes,
    ): array {
        $records = [];

        foreach (array_chunk($studentCodes, self::BATCH_SIZE) as $chunk) {
            Import116::query()
                ->where('school_id', $schoolId)
                ->where('schoolyear_id', $schoolyearId)
                ->whereIn('student_code', $chunk)
                ->get()
                ->each(function (Import116 $record) use (&$records): void {
                    $records[(string) $record->student_code] = $record;
                });
        }

        return $records;
    }

    /**
     * @param  array<string, Import116>  $recordsByStudentCode
     * @return array{0: array<string, User>, 1: array<int, User>}
     */
    private function prefetchUsersForRecords(int $schoolId, array $recordsByStudentCode): array
    {
        $emails = [];
        $userIds = [];

        foreach ($recordsByStudentCode as $studentCode => $record) {
            $email = $record->email ?: $this->buildPlaceholderEmail($studentCode, $schoolId);
            $emails[$this->normalizedEmail($email)] = $email;

            if ((int) ($record->user_id ?? 0) > 0) {
                $userIds[] = (int) $record->user_id;
            }
        }

        $usersByEmail = [];
        $usersById = [];

        foreach (array_chunk(array_values($emails), self::BATCH_SIZE) as $emailChunk) {
            User::query()
                ->where('school_id', $schoolId)
                ->whereIn('email', $emailChunk)
                ->get()
                ->each(function (User $user) use (&$usersByEmail, &$usersById): void {
                    $usersByEmail[$this->normalizedEmail($user->email)] = $user;
                    $usersById[(int) $user->id] = $user;
                });
        }

        foreach (array_chunk(array_values(array_unique($userIds)), self::BATCH_SIZE) as $userIdChunk) {
            $missingUserIds = array_values(array_filter(
                $userIdChunk,
                fn (int $userId): bool => ! isset($usersById[$userId]),
            ));

            if ($missingUserIds === []) {
                continue;
            }

            User::query()
                ->where('school_id', $schoolId)
                ->whereIn('id', $missingUserIds)
                ->get()
                ->each(function (User $user) use (&$usersByEmail, &$usersById): void {
                    $usersByEmail[$this->normalizedEmail($user->email)] = $user;
                    $usersById[(int) $user->id] = $user;
                });
        }

        return [$usersByEmail, $usersById];
    }

    private function normalizedEmail(?string $email): string
    {
        return mb_strtolower(trim((string) $email));
    }

    private function createRun(int $schoolId, ?int $schoolyearId): ?Import116Run
    {
        if (! $this->isRunTrackingAvailable()) {
            return null;
        }

        try {
            return Import116Run::create([
                'school_id' => $schoolId,
                'schoolyear_id' => $schoolyearId,
                'user_id' => $this->user->id ?? null,
                'source_path' => $this->normalizedSourcePath(),
                'status' => 'running',
                'started_at' => now(),
            ]);
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function markRunFailed(?Import116Run $run, string $message): void
    {
        if (! $run) {
            return;
        }

        $run->status = 'failed';
        $run->error_message = $message;
        $run->finished_at = now();
        $run->save();
    }

    private function normalizedSourcePath(): ?string
    {
        $value = trim($this->path);

        if ($value === '') {
            return null;
        }

        $value = str_replace('\\', '/', $value);

        return $value;
    }

    /**
     * @param  array<int, array<string, mixed>>  $changes
     */
    private function storeRunChanges(
        ?Import116Run $run,
        array $changes,
        int $schoolId,
        ?int $schoolyearId,
    ): void {
        if (! $run || $changes === [] || ! $this->isRunTrackingAvailable()) {
            return;
        }

        $rows = [];
        $timestamp = now();
        foreach ($changes as $change) {
            $rows[] = [
                'import116_run_id' => $run->id,
                'school_id' => $schoolId,
                'schoolyear_id' => $schoolyearId,
                'student_code' => (string) ($change['student_code'] ?? ''),
                'change_type' => (string) ($change['change_type'] ?? 'updated'),
                'before_snapshot' => isset($change['before_snapshot']) ? json_encode($change['before_snapshot'], JSON_UNESCAPED_UNICODE) : null,
                'after_snapshot' => isset($change['after_snapshot']) ? json_encode($change['after_snapshot'], JSON_UNESCAPED_UNICODE) : null,
                'summary' => isset($change['summary']) ? json_encode($change['summary'], JSON_UNESCAPED_UNICODE) : null,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ];
        }

        Import116RunChange::query()->insert($rows);
    }

    /**
     * @param  array<string, int>  $counts
     */
    private function completeRun(?Import116Run $run, array $counts): void
    {
        if (! $run) {
            return;
        }

        $run->status = 'completed';
        $run->finished_at = now();
        $run->counts = $counts;
        $run->error_message = null;
        $run->save();

        $this->streamRunSummaryToDatabase($run);
    }

    private function streamRunSummaryToDatabase(Import116Run $run): void
    {
        $types = ['inserted', 'updated', 'deleted'];
        $streams = [];
        $hasEntries = array_fill_keys($types, false);
        $summaryStream = null;

        try {
            foreach ($types as $type) {
                $streams[$type] = tmpfile();
                if ($streams[$type] === false) {
                    throw new RuntimeException('Temporäre Import-116-Berichtsdatei konnte nicht erstellt werden.');
                }
            }

            foreach ($run->changes()->select(['id', 'student_code', 'change_type', 'summary'])->lazyById(self::BATCH_SIZE) as $change) {
                $type = (string) $change->change_type;
                if (! isset($streams[$type])) {
                    continue;
                }

                $summary = is_array($change->summary) ? $change->summary : [];
                $entry = [
                    'student_code' => (string) $change->student_code,
                    'class' => $summary['class'] ?? null,
                    'name' => $summary['name'] ?? null,
                    'changed_fields' => $summary['changed_fields'] ?? [],
                ];

                if ($hasEntries[$type]) {
                    fwrite($streams[$type], ',');
                }

                fwrite($streams[$type], json_encode($entry, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
                $hasEntries[$type] = true;
            }

            $summaryStream = tmpfile();
            if ($summaryStream === false) {
                throw new RuntimeException('Temporäre Import-116-Zusammenfassung konnte nicht erstellt werden.');
            }

            fwrite($summaryStream, '{');
            foreach ($types as $index => $type) {
                if ($index > 0) {
                    fwrite($summaryStream, ',');
                }

                fwrite($summaryStream, json_encode($type, JSON_THROW_ON_ERROR).':[');
                rewind($streams[$type]);
                stream_copy_to_stream($streams[$type], $summaryStream);
                fwrite($summaryStream, ']');
            }
            fwrite($summaryStream, '}');
            rewind($summaryStream);

            $connection = DB::connection();
            $grammar = $connection->getQueryGrammar();
            $statement = $connection->getPdo()->prepare(sprintf(
                'update %s set %s = ? where %s = ?',
                $grammar->wrapTable('import116_runs'),
                $grammar->wrap('report_summary'),
                $grammar->wrap('id'),
            ));
            $statement->bindParam(1, $summaryStream, PDO::PARAM_LOB);
            $statement->bindValue(2, (int) $run->id, PDO::PARAM_INT);
            $statement->execute();
        } finally {
            foreach ($streams as $stream) {
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }

            if (is_resource($summaryStream)) {
                fclose($summaryStream);
            }
        }
    }

    /**
     * @return array<string, int>
     */
    private function emptyReportCounts(): array
    {
        return [
            'processed_rows' => 0,
            'seen_students' => 0,
            'inserted' => 0,
            'updated' => 0,
            'deleted' => 0,
            'unchanged' => 0,
            'changes_total' => 0,
        ];
    }

    /**
     * @param  array<string, int>  $total
     * @param  array<string, int>  $batch
     */
    private function mergeReportCounts(array &$total, array $batch): void
    {
        foreach (['inserted', 'updated', 'deleted', 'unchanged', 'changes_total'] as $key) {
            $total[$key] += (int) ($batch[$key] ?? 0);
        }
    }

    private function isRunTrackingAvailable(): bool
    {
        return $this->tableExists('import116_runs') && $this->tableExists('import116_run_changes');
    }

    private function tableExists(string $table): bool
    {
        if (array_key_exists($table, $this->availableTables)) {
            return $this->availableTables[$table];
        }

        try {
            return $this->availableTables[$table] = Schema::hasTable($table);
        } catch (\Throwable $e) {
            return $this->availableTables[$table] = false;
        }
    }

    private function loadSnapshotsByStudentCode(int $schoolId, ?int $schoolyearId, array $studentCodes): array
    {
        $studentCodes = array_values(array_filter(array_map(fn ($code) => is_scalar($code) ? trim((string) $code) : '', $studentCodes)));
        if ($studentCodes === []) {
            return [];
        }

        return Import116::query()
            ->where('school_id', $schoolId)
            ->where('schoolyear_id', $schoolyearId)
            ->whereIn('student_code', $studentCodes)
            ->get()
            ->mapWithKeys(fn (Import116 $record) => [(string) $record->student_code => $this->snapshotImport116($record)])
            ->all();
    }

    /**
     * @param  array<string, int>  $counts
     */
    private function deleteMissingImport116Rows(
        int $schoolId,
        ?int $schoolyearId,
        string $stagingTable,
        ?Import116Run $run,
        array &$counts,
    ): void {
        Import116::query()
            ->where('school_id', $schoolId)
            ->where('schoolyear_id', $schoolyearId)
            ->whereNotExists(function ($query) use ($stagingTable): void {
                $query
                    ->selectRaw('1')
                    ->from($stagingTable)
                    ->whereColumn($stagingTable.'.student_code', 'import116.student_code');
            })
            ->chunkById(self::BATCH_SIZE, function ($records) use ($schoolId, $schoolyearId, $run, &$counts): void {
                $beforeSnapshots = $records
                    ->mapWithKeys(fn (Import116 $record): array => [
                        (string) $record->student_code => $this->snapshotImport116($record),
                    ])
                    ->all();
                $ids = $records
                    ->pluck('id')
                    ->map(fn ($id): int => (int) $id)
                    ->all();

                if ($this->tableExists('users')) {
                    User::query()->whereIn('import116_id', $ids)->update(['import116_id' => null]);
                }

                Import116::query()->whereIn('id', $ids)->delete();

                $batchReport = $this->buildRunReport($beforeSnapshots, [], 0, 0);
                $this->mergeReportCounts($counts, $batchReport['counts']);
                $this->storeRunChanges($run, $batchReport['changes'], $schoolId, $schoolyearId);
            });
    }

    private function syncImport116ReferencesToCurrentRecord(int $schoolId, Import116 $record): void
    {
        $studentCode = trim((string) ($record->student_code ?? ''));
        if ($studentCode === '') {
            return;
        }

        $staleImportIds = Import116::query()
            ->where('school_id', $schoolId)
            ->where('student_code', $studentCode)
            ->where('id', '!=', (int) $record->id)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->values();

        if ($staleImportIds->isEmpty()) {
            return;
        }

        if ($this->tableExists('users')) {
            User::query()
                ->where('school_id', $schoolId)
                ->whereIn('import116_id', $staleImportIds->all())
                ->update(['import116_id' => (int) $record->id]);
        }

        if (! $this->tableExists('user_group_members')) {
            return;
        }

        $targetRef = 'import116.student:'.(int) $record->id;
        $staleRefs = $staleImportIds
            ->map(fn (int $id) => 'import116.student:'.$id)
            ->values()
            ->all();

        $members = UserGroupMember::query()
            ->where('school_id', $schoolId)
            ->where('member_provider', UserGroupMember::PROVIDER_IMPORT116_STUDENT)
            ->whereIn('member_ref', $staleRefs)
            ->get();

        foreach ($members as $member) {
            $duplicate = UserGroupMember::query()
                ->where('user_group_id', (int) $member->user_group_id)
                ->where('member_provider', UserGroupMember::PROVIDER_IMPORT116_STUDENT)
                ->where('member_ref', $targetRef)
                ->where('id', '!=', (int) $member->id)
                ->exists();

            if ($duplicate) {
                $member->delete();

                continue;
            }

            $member->member_ref = $targetRef;
            $member->source_schoolyear_id = $record->schoolyear_id ? (int) $record->schoolyear_id : null;
            $member->save();
        }
    }

    private function syncLinkedUsersToCurrentRecord(int $schoolId, Import116 $record): void
    {
        if (! $this->tableExists('users')) {
            return;
        }

        $linkedUsers = User::query()
            ->where('school_id', $schoolId)
            ->where(function ($query) use ($record): void {
                $query->where('import116_id', (int) $record->id);

                if ((int) ($record->user_id ?? 0) > 0) {
                    $query->orWhere('id', (int) $record->user_id);
                }
            })
            ->get();

        foreach ($linkedUsers as $linkedUser) {
            $linkedUser->first_name = $record->first_name;
            $linkedUser->last_name = $record->last_name;
            $linkedUser->schoolclass = $record->class;
            $linkedUser->sex = $record->sex;
            $linkedUser->import116_id = (int) $record->id;
            $linkedUser->save();
        }
    }

    private function syncTeachingCourseStudentsToCurrentRecord(Import116 $record): void
    {
        if (! $record->user_id || ! $this->tableExists('teaching_course_students')) {
            return;
        }

        DB::table('teaching_course_students')
            ->where('import116_id', (int) $record->id)
            ->whereNull('user_id')
            ->orderBy('id')
            ->get()
            ->each(function (object $sourceRow) use ($record): void {
                $targetRow = DB::table('teaching_course_students')
                    ->where('teaching_course_id', $sourceRow->teaching_course_id)
                    ->where('user_id', (int) $record->user_id)
                    ->where('id', '!=', (int) $sourceRow->id)
                    ->orderByRaw('deleted_at IS NULL DESC')
                    ->orderBy('id')
                    ->first();

                if (! $targetRow) {
                    DB::table('teaching_course_students')
                        ->where('id', (int) $sourceRow->id)
                        ->update([
                            'user_id' => (int) $record->user_id,
                            'updated_at' => now(),
                        ]);

                    return;
                }

                $sourceIsActive = $sourceRow->deleted_at === null;
                $targetIsActive = $targetRow->deleted_at === null;

                if ($sourceIsActive && ! $targetIsActive) {
                    DB::table('teaching_course_students')
                        ->where('id', (int) $targetRow->id)
                        ->delete();

                    DB::table('teaching_course_students')
                        ->where('id', (int) $sourceRow->id)
                        ->update($this->mergedTeachingCourseStudentPayload($sourceRow, $targetRow, [
                            'user_id' => (int) $record->user_id,
                            'import116_id' => (int) $record->id,
                            'deleted_at' => null,
                        ]));

                    return;
                }

                DB::table('teaching_course_students')
                    ->where('id', (int) $sourceRow->id)
                    ->delete();

                DB::table('teaching_course_students')
                    ->where('id', (int) $targetRow->id)
                    ->update($this->mergedTeachingCourseStudentPayload($targetRow, $sourceRow, [
                        'import116_id' => (int) $record->id,
                        'deleted_at' => ($sourceIsActive || $targetIsActive) ? null : $targetRow->deleted_at,
                    ]));
            });
    }

    private function mergedTeachingCourseStudentPayload(object $primaryRow, object $secondaryRow, array $overrides): array
    {
        $payload = [
            'comment' => $primaryRow->comment ?: $secondaryRow->comment,
            'sem_1_grade' => $primaryRow->sem_1_grade ?: $secondaryRow->sem_1_grade,
            'sem_2_grade' => $primaryRow->sem_2_grade ?: $secondaryRow->sem_2_grade,
            'sem_grade' => $primaryRow->sem_grade ?: $secondaryRow->sem_grade,
            'behaviour_1_grade' => $primaryRow->behaviour_1_grade ?: $secondaryRow->behaviour_1_grade,
            'behaviour_2_grade' => $primaryRow->behaviour_2_grade ?: $secondaryRow->behaviour_2_grade,
            'behaviour_grade' => $primaryRow->behaviour_grade ?: $secondaryRow->behaviour_grade,
            'stars' => $primaryRow->stars ?: $secondaryRow->stars,
            'canceled_at' => $primaryRow->canceled_at ?: $secondaryRow->canceled_at,
            'updated_at' => now(),
        ];

        return array_merge($payload, $overrides);
    }

    private function snapshotImport116(Import116 $record): array
    {
        return [
            'id' => (int) $record->id,
            'school_id' => (int) $record->school_id,
            'schoolyear_id' => $record->schoolyear_id !== null ? (int) $record->schoolyear_id : null,
            'class' => (string) ($record->class ?? ''),
            'school_level' => $record->school_level,
            'attendance_year' => $record->attendance_year,
            'religion' => $record->religion,
            'student_code' => (string) ($record->student_code ?? ''),
            'last_name' => (string) ($record->last_name ?? ''),
            'first_name' => (string) ($record->first_name ?? ''),
            'email' => $record->email,
            'phone_1' => $record->phone_1,
            'phone_2' => $record->phone_2,
            'sex' => $record->sex,
            'birth_date' => $record->birth_date?->format('Y-m-d'),
            'mother_name' => $record->mother_name,
            'mother_email' => $record->mother_email,
            'mother_phone_1' => $record->mother_phone_1,
            'mother_phone_2' => $record->mother_phone_2,
            'father_name' => $record->father_name,
            'father_email' => $record->father_email,
            'father_phone_1' => $record->father_phone_1,
            'father_phone_2' => $record->father_phone_2,
            'import_date' => $record->import_date?->format('Y-m-d H:i:s'),
            'exists_date' => $record->exists_date?->format('Y-m-d H:i:s'),
            'import_user_id' => $record->import_user_id !== null ? (int) $record->import_user_id : null,
            'user_id' => $record->user_id !== null ? (int) $record->user_id : null,
        ];
    }

    private function buildRunReport(array $beforeSnapshots, array $afterSnapshots, int $processedRows, int $seenStudents): array
    {
        $studentCodes = array_values(array_unique(array_merge(array_keys($beforeSnapshots), array_keys($afterSnapshots))));
        sort($studentCodes);

        $changes = [];
        $counts = [
            'processed_rows' => $processedRows,
            'seen_students' => $seenStudents,
            'inserted' => 0,
            'updated' => 0,
            'deleted' => 0,
            'unchanged' => 0,
            'changes_total' => 0,
        ];

        $summaryLists = [
            'inserted' => [],
            'updated' => [],
            'deleted' => [],
        ];

        foreach ($studentCodes as $studentCode) {
            $before = $beforeSnapshots[$studentCode] ?? null;
            $after = $afterSnapshots[$studentCode] ?? null;
            if (! $before && ! $after) {
                continue;
            }

            $beforeExists = ! empty($before['exists_date'] ?? null);
            $afterExists = ! empty($after['exists_date'] ?? null);

            $changeType = null;
            if (! $beforeExists && $afterExists) {
                $changeType = 'inserted';
            } elseif ($beforeExists && ! $afterExists) {
                $changeType = 'deleted';
            } elseif ($this->hasRealContentChanges($before, $after)) {
                $changeType = 'updated';
            }

            if (! $changeType) {
                $counts['unchanged']++;

                continue;
            }

            $summary = $this->buildChangeSummary($changeType, $before, $after);
            $changes[] = [
                'student_code' => $studentCode,
                'change_type' => $changeType,
                'before_snapshot' => $before,
                'after_snapshot' => $after,
                'summary' => $summary,
            ];

            $counts[$changeType]++;
            $counts['changes_total']++;

            $summaryLists[$changeType][] = [
                'student_code' => $studentCode,
                'class' => $summary['class'] ?? null,
                'name' => $summary['name'] ?? null,
                'changed_fields' => $summary['changed_fields'] ?? [],
            ];
        }

        return [
            'counts' => $counts,
            'changes' => $changes,
            'summary' => $summaryLists,
        ];
    }

    private function hasRealContentChanges(?array $before, ?array $after): bool
    {
        $beforeContent = $this->contentSnapshot($before);
        $afterContent = $this->contentSnapshot($after);

        return $beforeContent !== $afterContent;
    }

    private function contentSnapshot(?array $snapshot): array
    {
        $snapshot = is_array($snapshot) ? $snapshot : [];
        $content = [];
        foreach ($this->contentFields() as $field) {
            $content[$field] = $snapshot[$field] ?? null;
        }

        return $content;
    }

    private function contentFields(): array
    {
        return [
            'schoolyear_id',
            'class',
            'school_level',
            'attendance_year',
            'religion',
            'student_code',
            'last_name',
            'first_name',
            'email',
            'phone_1',
            'phone_2',
            'sex',
            'birth_date',
            'mother_name',
            'mother_email',
            'mother_phone_1',
            'mother_phone_2',
            'father_name',
            'father_email',
            'father_phone_1',
            'father_phone_2',
        ];
    }

    private function buildChangeSummary(string $changeType, ?array $before, ?array $after): array
    {
        $source = $after ?: $before ?: [];
        $summary = [
            'change_type' => $changeType,
            'student_code' => $source['student_code'] ?? null,
            'class' => $source['class'] ?? null,
            'name' => trim(((string) ($source['last_name'] ?? '')).' '.((string) ($source['first_name'] ?? ''))),
            'changed_fields' => [],
        ];

        if ($changeType === 'updated') {
            foreach ($this->contentFields() as $field) {
                $beforeValue = $before[$field] ?? null;
                $afterValue = $after[$field] ?? null;
                if ($beforeValue !== $afterValue) {
                    $summary['changed_fields'][] = $field;
                }
            }
        }

        return $summary;
    }

    private function mapHeaders(array $headers): array|false
    {
        $required = [
            'class' => ['klasse', 'class', 'klasse/bezeichnung'],
            'student_code' => ['schülerkennzahl', 'schuelerkennzahl', 'student_code', 'schueler_kennzahl'],
            'last_name' => ['familienname', 'nachname', 'last_name'],
            'first_name' => ['vorname', 'first_name'],
        ];

        $optional = [
            'school_level' => ['schulstufe', 'school_level', 'school level'],
            'attendance_year' => ['besuchsjahr', 'attendance_year', 'attendance year'],
            'religion' => ['religionsbekenntnis', 'religion'],
            'email' => ['mailadresse', 'e-mail', 'email'],
            'phone_1' => ['mobiltelefon', 'handy', 'telefonnummer', 'telefonnummer 1', 'tel1'],
            'phone_2' => ['telefonnummer 2', 'tel2', 'telefon 2'],
            'sex' => ['geschlecht', 'sex'],
            'birth_date' => ['geburtsdatum', 'birth_date', 'geburtstag'],
            'address_type' => ['adressart', 'adress-art', 'adresse', 'adresseart'],
            'address_name' => ['name (anschrift)', 'anschrift (name)', 'name', 'anschrift'],
        ];

        $normalized = array_map(fn ($h) => trim(mb_strtolower($h)), $headers);
        $mapping = [];

        foreach ($required as $field => $variants) {
            $found = false;
            foreach ($normalized as $index => $name) {
                if (in_array($name, $variants, true)) {
                    $mapping[$headers[$index]] = $field;
                    $found = true;
                    break;
                }
            }
            if (! $found) {
                return false;
            }
        }

        foreach ($optional as $field => $variants) {
            foreach ($normalized as $index => $name) {
                if (in_array($name, $variants, true)) {
                    $mapping[$headers[$index]] = $field;
                    break;
                }
            }
        }

        return $mapping;
    }

    private function parseDate(?string $value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (! $value) {
            return null;
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        if (is_numeric($trimmed)) {
            $seconds = ((int) $trimmed - 25569) * 86400;
            if ($seconds > 0) {
                return Carbon::createFromTimestamp($seconds)->toDateString();
            }
        }

        try {
            return Carbon::parse($trimmed)->toDateString();
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function normalizeCell(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return trim((string) $value);
    }

    private function normalizeAddressType(?string $value): string
    {
        $normalized = trim(mb_strtolower((string) $value));
        if ($normalized === '') {
            return '';
        }

        $normalized = str_replace(
            ['ä', 'ö', 'ü', 'ß'],
            ['ae', 'oe', 'ue', 'ss'],
            $normalized
        );

        return preg_replace('/\s+/', ' ', $normalized) ?? $normalized;
    }

    private function isStudentAddressType(string $addressType): bool
    {
        if ($addressType === '') {
            return false;
        }

        if (str_starts_with($addressType, 'eigen') || str_contains($addressType, 'schueler')) {
            return true;
        }

        $collapsed = preg_replace('/[^a-z0-9]+/', '', $addressType) ?? '';
        if ($collapsed === '') {
            return false;
        }

        foreach (['eigen', 'eigenberechtigt', 'schueler', 'schuelerselbst'] as $keyword) {
            if ($this->isNearMatch($collapsed, $keyword)) {
                return true;
            }

            $keywordLength = strlen($keyword);
            if (strlen($collapsed) > $keywordLength) {
                $prefix = substr($collapsed, 0, $keywordLength);
                if ($this->isNearMatch($prefix, $keyword)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function isNearMatch(string $value, string $target): bool
    {
        if ($value === '' || $target === '') {
            return false;
        }

        if ($value === $target) {
            return true;
        }

        if ($value[0] !== $target[0]) {
            return false;
        }

        $lengthDifference = abs(strlen($value) - strlen($target));
        if ($lengthDifference > 2) {
            return false;
        }

        $maxDistance = strlen($target) <= 6 ? 1 : 2;

        return levenshtein($value, $target) <= $maxDistance;
    }

    private function setIfPresent(array &$data, string $key, ?string $value): void
    {
        if ($value === null) {
            return;
        }

        $trimmed = trim((string) $value);
        if ($trimmed === '') {
            return;
        }

        $data[$key] = $trimmed;
    }

    private function buildPlaceholderEmail(string $studentCode, int $schoolId): string
    {
        $slug = preg_replace('/[^a-z0-9_]/', '_', strtolower($studentCode));

        return 'noemail.'.$slug.'@schooltool.noemail';
    }

    public static function isPlaceholderEmail(?string $email): bool
    {
        return is_string($email) && str_ends_with($email, '@schooltool.noemail');
    }
}
