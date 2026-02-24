<?php

namespace App\Jobs\Teaching;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Events\Import116FinishedEvent;
use App\Models\Import116;
use App\Models\Import116Run;
use App\Models\Import116RunChange;
use App\Models\SchoolTool;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\SimpleExcel\SimpleExcelReader;

class Import116Job implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public $user, public string $path, public ?int $schoolyearId = null, public ?string $originalFilename = null)
    {
        // placeholder for future payload
    }

    public function handle(): void
    {
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
                ['path' => $this->path]
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
                []
            ));
            return;
        }

        $report = ['counts' => ['inserted' => 0, 'updated' => 0, 'deleted' => 0]];
        try {
            DB::transaction(function () use ($reader, $headerMapping, $schoolId, $schoolyearId, $run, &$report) {
                $now = now();
                $seenCodes = [];
                $baselineSnapshots = $this->loadSnapshotsByStudentCode($schoolId, $schoolyearId);

                $reader->getRows()->each(function (array $row) use ($headerMapping, $schoolId, $schoolyearId, $now, &$seenCodes) {
                    $mapped = [];
                    foreach ($headerMapping as $originalHeader => $field) {
                        $mapped[$field] = isset($row[$originalHeader]) ? $this->normalizeCell($row[$originalHeader]) : null;
                    }

                    $studentCode = $mapped['student_code'] ?? null;
                    if (! $studentCode) {
                        return;
                    }

                    $seenCodes[] = $studentCode;

                    $data = [
                        'school_id' => $schoolId,
                        'schoolyear_id' => $schoolyearId,
                        'class' => $mapped['class'] ?? '',
                        'student_code' => $studentCode,
                        'last_name' => $mapped['last_name'] ?? '',
                        'first_name' => $mapped['first_name'] ?? '',
                        'sex' => $mapped['sex'] ?? null,
                        'birth_date' => $this->parseDate($mapped['birth_date'] ?? null),
                        'import_date' => $now,
                        'exists_date' => $now,
                        'import_user_id' => $this->user->id,
                    ];

                    $addressType = strtolower((string) ($mapped['address_type'] ?? ''));
                    if (in_array($addressType, ['eigen', 'schüler', 'schueler'], true)) {
                        $this->setIfPresent($data, 'email', $mapped['email'] ?? null);
                        $this->setIfPresent($data, 'phone_1', $mapped['phone_1'] ?? null);
                        $this->setIfPresent($data, 'phone_2', $mapped['phone_2'] ?? null);
                    } elseif ($addressType === 'vater') {
                        $this->setIfPresent($data, 'father_name', $mapped['address_name'] ?? null);
                        $this->setIfPresent($data, 'father_email', $mapped['email'] ?? null);
                        $this->setIfPresent($data, 'father_phone_1', $mapped['phone_1'] ?? null);
                        $this->setIfPresent($data, 'father_phone_2', $mapped['phone_2'] ?? null);
                    } elseif ($addressType === 'mutter') {
                        $this->setIfPresent($data, 'mother_name', $mapped['address_name'] ?? null);
                        $this->setIfPresent($data, 'mother_email', $mapped['email'] ?? null);
                        $this->setIfPresent($data, 'mother_phone_1', $mapped['phone_1'] ?? null);
                        $this->setIfPresent($data, 'mother_phone_2', $mapped['phone_2'] ?? null);
                    }

                    $record = Import116::updateOrCreate(
                        [
                            'school_id' => $schoolId,
                            'schoolyear_id' => $schoolyearId,
                            'student_code' => $studentCode,
                        ],
                        $data
                    );

                    // Link Import116 record with existing User by email, school_id and schoolyear_id
                    if ($record->email && $schoolyearId) {
                        $matchingUser = User::where('email', $record->email)
                            ->where('school_id', $schoolId)
                            ->where('schoolyear_id', $schoolyearId)
                            ->first();

                        if ($matchingUser) {
                            $record->user_id = $matchingUser->id;
                            $record->save();

                            $matchingUser->import116_id = $record->id;
                            $matchingUser->save();
                        }
                    }
                });

                $this->deleteMissingImport116Rows($schoolId, $schoolyearId, $seenCodes);

                $uniqueSeenCodes = array_values(array_unique($seenCodes));

                $finalSnapshots = $this->loadSnapshotsByStudentCode(
                    $schoolId,
                    $schoolyearId,
                    array_values(array_unique(array_merge(array_keys($baselineSnapshots), $uniqueSeenCodes)))
                );

                $report = $this->buildRunReport($baselineSnapshots, $finalSnapshots, count($seenCodes), count($uniqueSeenCodes));
                $this->storeRunReport($run, $report, $schoolId, $schoolyearId);

                $schoolTool = SchoolTool::firstOrCreate(['school_id' => $schoolId]);
                $schoolTool->import_166_at = $now;
                $schoolTool->save();
            });
        } catch (\Throwable $e) {
            $this->markRunFailed($run, 'Import 116 fehlgeschlagen: '.$e->getMessage());

            broadcast(new Import116FinishedEvent(
                500,
                $this->user->id,
                'Import 116 fehlgeschlagen.',
                ['error' => $e->getMessage()]
            ));

            throw $e;
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
                'source_path' => $this->normalizedSourceName(),
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

    private function normalizedSourceName(): ?string
    {
        $value = is_string($this->originalFilename) && trim($this->originalFilename) !== ''
            ? trim($this->originalFilename)
            : (is_string($this->path) ? trim($this->path) : '');

        if ($value === '') {
            return null;
        }

        $value = str_replace('\\', '/', $value);
        return basename($value);
    }

    private function storeRunReport(?Import116Run $run, array $report, int $schoolId, ?int $schoolyearId): void
    {
        if (! $run) {
            return;
        }

        if (! empty($report['changes']) && $this->isRunTrackingAvailable()) {
            $rows = [];
            $timestamp = now();
            foreach ($report['changes'] as $change) {
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

            foreach (array_chunk($rows, 500) as $chunk) {
                Import116RunChange::query()->insert($chunk);
            }
        }

        $run->status = 'completed';
        $run->finished_at = now();
        $run->counts = $report['counts'] ?? [];
        $run->report_summary = $report['summary'] ?? [];
        $run->error_message = null;
        $run->save();
    }

    private function isRunTrackingAvailable(): bool
    {
        try {
            return Schema::hasTable('import116_runs') && Schema::hasTable('import116_run_changes');
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function loadSnapshotsByStudentCode(int $schoolId, ?int $schoolyearId, ?array $studentCodes = null): array
    {
        $query = Import116::query()
            ->where('school_id', $schoolId)
            ->where('schoolyear_id', $schoolyearId);

        if (is_array($studentCodes)) {
            $studentCodes = array_values(array_filter(array_map(fn($code) => is_scalar($code) ? trim((string) $code) : '', $studentCodes)));
            if (empty($studentCodes)) {
                return [];
            }
            $query->whereIn('student_code', $studentCodes);
        }

        return $query
            ->get()
            ->mapWithKeys(fn(Import116 $record) => [(string) $record->student_code => $this->snapshotImport116($record)])
            ->all();
    }

    private function deleteMissingImport116Rows(int $schoolId, ?int $schoolyearId, array $seenCodes): void
    {
        $query = Import116::query()
            ->where('school_id', $schoolId)
            ->where('schoolyear_id', $schoolyearId);

        if (! empty($seenCodes)) {
            $query->whereNotIn('student_code', $seenCodes);
        }

        $rowsToDelete = $query->get(['id']);
        if ($rowsToDelete->isEmpty()) {
            return;
        }

        $ids = $rowsToDelete
            ->pluck('id')
            ->map(fn($id) => (int) $id)
            ->filter(fn($id) => $id > 0)
            ->values()
            ->all();

        if (empty($ids)) {
            return;
        }

        if (Schema::hasTable('users')) {
            User::query()->whereIn('import116_id', $ids)->update(['import116_id' => null]);
        }

        Import116::query()->whereIn('id', $ids)->delete();
    }

    private function snapshotImport116(Import116 $record): array
    {
        return [
            'id' => (int) $record->id,
            'school_id' => (int) $record->school_id,
            'schoolyear_id' => $record->schoolyear_id !== null ? (int) $record->schoolyear_id : null,
            'class' => (string) ($record->class ?? ''),
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
            'name' => trim(((string) ($source['last_name'] ?? '')) . ' ' . ((string) ($source['first_name'] ?? ''))),
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
            'email' => ['mailadresse', 'e-mail', 'email'],
            'phone_1' => ['mobiltelefon', 'handy', 'telefonnummer', 'telefonnummer 1', 'tel1'],
            'phone_2' => ['telefonnummer 2', 'tel2', 'telefon 2'],
            'sex' => ['geschlecht', 'sex'],
            'birth_date' => ['geburtsdatum', 'birth_date', 'geburtstag'],
            'address_type' => ['adressart', 'adress-art', 'adresse', 'adresseart'],
            'address_name' => ['name (anschrift)', 'anschrift (name)', 'name', 'anschrift'],
        ];

        $normalized = array_map(fn($h) => trim(mb_strtolower($h)), $headers);
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
}
