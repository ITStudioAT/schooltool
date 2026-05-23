<?php

namespace App\Http\Controllers\Admin\Teaching;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\Teaching\Import116Resource;
use App\Models\Import116;
use App\Models\Import116Run;
use App\Models\Import116RunChange;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class Import116Controller extends Controller
{
    public function loadClassStudents(Request $request)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'teaching_admin', 'teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $validated = $request->validate([
            'schoolclass' => 'nullable|string|max:255',
            'schoolclasses' => 'nullable|array',
            'schoolclasses.*' => 'string|max:255',
        ]);

        $schoolclass = $validated['schoolclass'] ?? null;
        $schoolclasses = $validated['schoolclasses'] ?? null;

        $studentsQuery = Import116::query()
            ->where('school_id', $auth_user->school_id)
            ->where('schoolyear_id', $auth_user->schoolyear_id)
            ->whereNotNull('class')
            ->where('class', '!=', '');

        if ($schoolclasses && count($schoolclasses) > 0) {
            $studentsQuery->whereIn('class', $schoolclasses);
        } elseif ($schoolclass) {
            $studentsQuery->where('class', $schoolclass);
        }

        $students = $studentsQuery
            ->orderBy('class')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        $classes = Import116::query()
            ->where('school_id', $auth_user->school_id)
            ->where('schoolyear_id', $auth_user->schoolyear_id)
            ->whereNotNull('class')
            ->where('class', '!=', '')
            ->distinct()
            ->orderBy('class')
            ->pluck('class');

        return response()->json([
            'data' => Import116Resource::collection($students),
            'classes' => $classes,
        ]);
    }

    public function runs(Request $request)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'teaching_admin', 'teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $this->ensureRunTrackingTables();

        $validated = $request->validate([
            'limit' => 'nullable|integer|min:1|max:50',
        ]);

        $historyLimit = (int) config('schooltool.import116_runs_history_limit', 10);
        $limit = min((int) ($validated['limit'] ?? $historyLimit), max(1, $historyLimit));

        $runs = Import116Run::query()
            ->where('school_id', $auth_user->school_id)
            ->where('schoolyear_id', $auth_user->schoolyear_id)
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        $availableResetRuns = Import116Run::query()
            ->where('school_id', $auth_user->school_id)
            ->where('schoolyear_id', $auth_user->schoolyear_id)
            ->where('status', 'completed')
            ->whereNull('undone_at')
            ->count();

        return response()->json([
            'data' => $runs->map(fn (Import116Run $run) => $this->serializeRun($run))->values(),
            'meta' => [
                'history_limit' => $historyLimit,
                'reset_max_runs' => $this->resetMaxRuns(),
                'available_reset_runs' => (int) $availableResetRuns,
            ],
        ]);
    }

    public function runDetails(Request $request, Import116Run $import116_run)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'teaching_admin', 'teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $this->ensureRunTrackingTables();
        $this->assertRunInCurrentSchoolyear($import116_run, (int) $auth_user->school_id, $auth_user->schoolyear_id);

        $changes = Import116RunChange::query()
            ->where('import116_run_id', $import116_run->id)
            ->orderBy('change_type')
            ->orderBy('student_code')
            ->get();

        $grouped = [
            'inserted' => [],
            'updated' => [],
            'deleted' => [],
        ];

        foreach ($changes as $change) {
            $type = (string) $change->change_type;
            if (! array_key_exists($type, $grouped)) {
                $grouped[$type] = [];
            }
            $grouped[$type][] = $this->serializeRunChange($change);
        }

        return response()->json([
            'run' => $this->serializeRun($import116_run, true),
            'changes' => $grouped,
        ]);
    }

    public function resetRuns(Request $request)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'teaching_admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $this->ensureRunTrackingTables();

        $validated = $request->validate([
            'count' => ['nullable', 'integer', 'min:1'],
            'target_import_id' => ['nullable', 'integer', 'min:1'],
        ]);

        $targetImportId = isset($validated['target_import_id']) ? (int) $validated['target_import_id'] : null;
        $maxResetRuns = $this->resetMaxRuns();
        $activeRuns = Import116Run::query()
            ->where('school_id', $auth_user->school_id)
            ->where('schoolyear_id', $auth_user->schoolyear_id)
            ->where('status', 'completed')
            ->whereNull('undone_at')
            ->orderByDesc('id')
            ->get();

        if ($activeRuns->isEmpty()) {
            abort(422, 'Es sind keine abgeschlossenen Importe zum Zurücksetzen vorhanden.');
        }

        if ($targetImportId) {
            $targetIndex = $activeRuns->search(fn (Import116Run $run) => (int) $run->id === $targetImportId);
            if ($targetIndex === false) {
                abort(422, 'Der ausgewählte Import kann nicht zurückgesetzt werden.');
            }
            $requestedCount = (int) $targetIndex + 1;
        } else {
            $requestedCount = (int) ($validated['count'] ?? 0);
        }

        if ($requestedCount <= 0) {
            abort(422, 'Bitte einen Import auswählen oder eine Anzahl angeben.');
        }

        if ($requestedCount > $maxResetRuns) {
            abort(422, "Es dürfen maximal {$maxResetRuns} Importe gleichzeitig zurückgesetzt werden.");
        }

        $runs = $activeRuns->take($requestedCount)->values();
        if ($runs->count() < $requestedCount) {
            abort(422, 'Es sind nicht genügend abgeschlossene Importe zum Zurücksetzen vorhanden.');
        }

        $result = [
            'requested_count' => $requestedCount,
            'reset_count' => 0,
            'runs' => [],
        ];

        foreach ($runs as $run) {
            DB::transaction(function () use ($run, $auth_user, &$result) {
                $changes = Import116RunChange::query()
                    ->where('import116_run_id', $run->id)
                    ->orderByDesc('id')
                    ->get();

                foreach ($changes as $change) {
                    $before = is_array($change->before_snapshot) ? $change->before_snapshot : null;
                    $after = is_array($change->after_snapshot) ? $change->after_snapshot : null;

                    if ($before === null) {
                        $this->rollbackInsertedStudent($change, $after);

                        continue;
                    }

                    $this->restoreImport116Snapshot($before);
                }

                $run->status = 'undone';
                $run->undone_at = now();
                $run->undone_by_user_id = $auth_user->id;
                $run->save();

                $result['reset_count']++;
                $result['runs'][] = [
                    'id' => $run->id,
                    'started_at' => optional($run->started_at)->toISOString(),
                    'finished_at' => optional($run->finished_at)->toISOString(),
                    'counts' => $run->counts ?? [],
                ];
            });
        }

        return response()->json([
            'message' => $result['reset_count'] === 1
                ? '1 Import wurde zurückgesetzt.'
                : "{$result['reset_count']} Importe wurden zurückgesetzt.",
            'result' => $result,
        ]);
    }

    public function destroyRun(Request $request, Import116Run $import116_run)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'teaching_admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $this->ensureRunTrackingTables();
        $this->assertRunInCurrentSchoolyear($import116_run, (int) $auth_user->school_id, $auth_user->schoolyear_id);

        $importId = (int) $import116_run->id;
        $import116_run->delete(); // cascades changes

        return response()->json([
            'message' => "Import #{$importId} wurde gelöscht.",
        ], 200);
    }

    private function ensureRunTrackingTables(): void
    {
        if (! Schema::hasTable('import116_runs') || ! Schema::hasTable('import116_run_changes')) {
            abort(409, 'Import-Protokolle sind noch nicht verfügbar. Bitte Migration ausführen.');
        }
    }

    private function resetMaxRuns(): int
    {
        return max(1, (int) config('schooltool.import116_reset_max_runs', 3));
    }

    private function assertRunInCurrentSchoolyear(Import116Run $run, int $schoolId, ?int $schoolyearId): void
    {
        if ((int) $run->school_id !== $schoolId || (int) ($run->schoolyear_id ?? 0) !== (int) ($schoolyearId ?? 0)) {
            abort(404, 'Import nicht gefunden.');
        }
    }

    private function serializeRun(Import116Run $run, bool $withSummary = false): array
    {
        $counts = is_array($run->counts) ? $run->counts : [];
        $summary = is_array($run->report_summary) ? $run->report_summary : [];

        $payload = [
            'id' => (int) $run->id,
            'status' => (string) $run->status,
            'started_at' => optional($run->started_at)->toISOString(),
            'finished_at' => optional($run->finished_at)->toISOString(),
            'undone_at' => optional($run->undone_at)->toISOString(),
            'source_name' => $this->displayImportSourceName($run->source_path),
            'counts' => [
                'inserted' => (int) ($counts['inserted'] ?? 0),
                'updated' => (int) ($counts['updated'] ?? 0),
                'deleted' => (int) ($counts['deleted'] ?? 0),
                'unchanged' => (int) ($counts['unchanged'] ?? 0),
                'changes_total' => (int) ($counts['changes_total'] ?? 0),
                'processed_rows' => (int) ($counts['processed_rows'] ?? 0),
                'seen_students' => (int) ($counts['seen_students'] ?? 0),
            ],
            'error_message' => $run->error_message,
        ];

        if ($withSummary) {
            $payload['report_summary'] = $summary;
        } else {
            $payload['report_summary_preview'] = [
                'inserted' => array_slice(is_array($summary['inserted'] ?? null) ? $summary['inserted'] : [], 0, 5),
                'updated' => array_slice(is_array($summary['updated'] ?? null) ? $summary['updated'] : [], 0, 5),
                'deleted' => array_slice(is_array($summary['deleted'] ?? null) ? $summary['deleted'] : [], 0, 5),
            ];
        }

        return $payload;
    }

    private function displayImportSourceName(?string $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $normalized = str_replace('\\', '/', trim($value));
        $basename = basename($normalized);

        return $basename !== '' ? $basename : $normalized;
    }

    private function serializeRunChange(Import116RunChange $change): array
    {
        $summary = is_array($change->summary) ? $change->summary : [];
        $before = is_array($change->before_snapshot) ? $change->before_snapshot : [];
        $after = is_array($change->after_snapshot) ? $change->after_snapshot : [];
        $source = ! empty($after) ? $after : $before;

        return [
            'id' => (int) $change->id,
            'change_type' => (string) $change->change_type,
            'student_code' => (string) ($change->student_code ?? ($source['student_code'] ?? '')),
            'class' => $summary['class'] ?? ($source['class'] ?? null),
            'name' => $summary['name'] ?? trim(((string) ($source['last_name'] ?? '')).' '.((string) ($source['first_name'] ?? ''))),
            'changed_fields' => array_values(array_filter((array) ($summary['changed_fields'] ?? []))),
            'before_snapshot' => $before,
            'after_snapshot' => $after,
        ];
    }

    private function rollbackInsertedStudent(Import116RunChange $change, ?array $afterSnapshot): void
    {
        $studentCode = trim((string) ($change->student_code ?? ($afterSnapshot['student_code'] ?? '')));
        if ($studentCode === '') {
            return;
        }

        $row = Import116::query()
            ->where('school_id', $change->school_id)
            ->where('schoolyear_id', $change->schoolyear_id)
            ->where('student_code', $studentCode)
            ->first();

        if (! $row) {
            return;
        }

        $hasTeachingReferences = Schema::hasTable('teaching_course_students')
            && DB::table('teaching_course_students')
                ->where('import116_id', $row->id)
                ->whereNull('deleted_at')
                ->exists();

        if ($hasTeachingReferences) {
            $label = trim(((string) ($afterSnapshot['last_name'] ?? '')).' '.((string) ($afterSnapshot['first_name'] ?? '')));
            abort(409, "Import kann nicht zurückgesetzt werden: Schüler {$label} ({$studentCode}) wird noch in Kursen verwendet.");
        }

        if (Schema::hasTable('users')) {
            DB::table('users')->where('import116_id', $row->id)->update(['import116_id' => null]);
        }

        $row->delete();
    }

    private function restoreImport116Snapshot(array $snapshot): void
    {
        $studentCode = trim((string) ($snapshot['student_code'] ?? ''));
        $schoolId = isset($snapshot['school_id']) ? (int) $snapshot['school_id'] : 0;
        if ($studentCode === '' || $schoolId <= 0) {
            return;
        }

        $payload = [
            'school_id' => $schoolId,
            'schoolyear_id' => isset($snapshot['schoolyear_id']) && $snapshot['schoolyear_id'] !== null ? (int) $snapshot['schoolyear_id'] : null,
            'class' => (string) ($snapshot['class'] ?? ''),
            'school_level' => $snapshot['school_level'] ?? null,
            'attendance_year' => $snapshot['attendance_year'] ?? null,
            'student_code' => $studentCode,
            'last_name' => (string) ($snapshot['last_name'] ?? ''),
            'first_name' => (string) ($snapshot['first_name'] ?? ''),
            'email' => $snapshot['email'] ?? null,
            'phone_1' => $snapshot['phone_1'] ?? null,
            'phone_2' => $snapshot['phone_2'] ?? null,
            'sex' => $snapshot['sex'] ?? null,
            'birth_date' => $snapshot['birth_date'] ?? null,
            'mother_name' => $snapshot['mother_name'] ?? null,
            'mother_email' => $snapshot['mother_email'] ?? null,
            'mother_phone_1' => $snapshot['mother_phone_1'] ?? null,
            'mother_phone_2' => $snapshot['mother_phone_2'] ?? null,
            'father_name' => $snapshot['father_name'] ?? null,
            'father_email' => $snapshot['father_email'] ?? null,
            'father_phone_1' => $snapshot['father_phone_1'] ?? null,
            'father_phone_2' => $snapshot['father_phone_2'] ?? null,
            'import_date' => $snapshot['import_date'] ?? now(),
            'exists_date' => $snapshot['exists_date'] ?? null,
            'import_user_id' => isset($snapshot['import_user_id']) && $snapshot['import_user_id'] !== null ? (int) $snapshot['import_user_id'] : null,
            'user_id' => isset($snapshot['user_id']) && $snapshot['user_id'] !== null ? (int) $snapshot['user_id'] : null,
        ];

        $row = Import116::query()->updateOrCreate(
            [
                'school_id' => $schoolId,
                'schoolyear_id' => isset($snapshot['schoolyear_id']) && $snapshot['schoolyear_id'] !== null ? (int) $snapshot['schoolyear_id'] : null,
                'student_code' => $studentCode,
            ],
            $payload
        );

        if (
            $row
            && Schema::hasTable('users')
            && isset($payload['user_id'])
            && is_int($payload['user_id'])
            && $payload['user_id'] > 0
        ) {
            DB::table('users')
                ->where('id', $payload['user_id'])
                ->update(['import116_id' => $row->id]);
        }
    }
}
