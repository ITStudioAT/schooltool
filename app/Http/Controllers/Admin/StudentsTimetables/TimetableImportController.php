<?php

namespace App\Http\Controllers\Admin\StudentsTimetables;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CompareTimetableImportRequest;
use App\Http\Requests\Admin\ConfirmTimetableImportRequest;
use App\Models\StudentTimetableEntry;
use App\Models\TimetableImport;
use App\Models\User;
use App\Services\StudentsTimetables\StudentTimetableOverviewService;
use App\Services\StudentsTimetables\TimetableImportService;
use App\Support\PrivateImportSourceFile;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class TimetableImportController extends Controller
{
    private const SINGLE_DATE_MAXIMUM_DATES = 2;

    private const ADMIN_ROLES = ['super_admin', 'admin', 'studentstimetables_admin'];

    public function index(Request $request, TimetableImportService $service): JsonResponse
    {
        if (! $authUser = $this->userHasRole(self::ADMIN_ROLES)) {
            abort(403, 'Sie haben keine Berechtigung.');
        }

        $this->ensurePersonalSchoolyear($authUser);

        $preview = TimetableImport::query()
            ->where('school_id', $authUser->school_id)
            ->where('schoolyear_id', $authUser->schoolyear_id)
            ->where('import_status', 'preview')
            ->orderByDesc('imported_at')
            ->orderByDesc('id')
            ->first();

        if ($preview) {
            $preview->setAttribute('date_plausibility', $service->datePlausibilityFor($preview));
            $preview->setAttribute('tt_diagnostics', $service->previewDiagnosticsFor($preview));
            $preview->setAttribute('replacement_scopes', $service->replacementScopesFor($preview));
        }

        if ($request->boolean('summary')) {
            $import = TimetableImport::query()
                ->where('school_id', $authUser->school_id)
                ->where('schoolyear_id', $authUser->schoolyear_id)
                ->where('import_status', '!=', 'preview')
                ->orderByDesc('imported_at')
                ->orderByDesc('id')
                ->first([
                    'id',
                    'original_filename',
                    'stored_filename',
                    'sections',
                    'tt_skipped_invalid',
                    'import_mode',
                    'import_operation',
                    'replacement_from',
                    'replacement_until',
                    'change_summary',
                    'tt_imported_rows',
                    'import_status',
                    'progress_current',
                    'progress_total',
                    'import_message',
                    'import_error',
                    'started_at',
                    'finished_at',
                    'imported_at',
                ]);

            return response()->json([
                'data' => $import ? [$import] : [],
                'total' => $import ? 1 : 0,
                'main_dataset' => null,
                'preview' => $preview,
            ]);
        }

        $perPage = min(max($request->integer('per_page', 25), 1), 100);

        $imports = TimetableImport::where('school_id', $authUser->school_id)
            ->where('schoolyear_id', $authUser->schoolyear_id)
            ->where('import_status', '!=', 'preview')
            ->orderByDesc('imported_at')
            ->orderByDesc('id')
            ->paginate($perPage);

        $imports->getCollection()->each(function (TimetableImport $import): void {
            $import->setAttribute('source_available', $this->timetableImportSourcePath($import) !== null);
        });

        return response()->json([
            ...$imports->toArray(),
            'preview' => $preview,
            'main_dataset' => $this->mainDatasetMetadata(
                (int) $authUser->school_id,
                (int) $authUser->schoolyear_id,
                $request->boolean('include_single_date_courses', true),
            ),
        ]);
    }

    public function show(TimetableImport $timetableImport, TimetableImportService $service): JsonResponse
    {
        if (! $authUser = $this->userHasRole(self::ADMIN_ROLES)) {
            abort(403, 'Sie haben keine Berechtigung.');
        }

        $this->ensurePersonalSchoolyear($authUser);

        if ($timetableImport->school_id !== $authUser->school_id || $timetableImport->schoolyear_id !== $authUser->schoolyear_id) {
            abort(403, 'Kein Zugriff auf diesen Import.');
        }

        $timetableImport->load('user:id,first_name,last_name');
        $timetableImport->setAttribute('tt_diagnostics', $service->previewDiagnosticsFor($timetableImport));

        return response()->json(['data' => $timetableImport]);
    }

    public function destroy(TimetableImport $timetableImport, TimetableImportService $service): JsonResponse
    {
        if (! $authUser = $this->userHasRole(self::ADMIN_ROLES)) {
            abort(403, 'Sie haben keine Berechtigung.');
        }

        $this->ensurePersonalSchoolyear($authUser);

        if ($timetableImport->school_id !== $authUser->school_id || $timetableImport->schoolyear_id !== $authUser->schoolyear_id) {
            abort(403, 'Kein Zugriff auf diesen Import.');
        }

        if ($timetableImport->import_status === 'preview') {
            $service->deletePreview($timetableImport);

            return response()->json([
                'message' => 'Vorimport und Quelldatei wurden gelöscht.',
            ]);
        }

        if (in_array($timetableImport->import_status, ['pending', 'running', 'deleting'], true)) {
            abort(409, 'Import wird bereits verarbeitet.');
        }

        $queuedImport = $service->queueUnimport($timetableImport);

        return response()->json([
            'message' => 'Import-Löschung wurde in die Warteschlange gestellt.',
            'data' => $queuedImport,
        ], 202);
    }

    public function comparison(CompareTimetableImportRequest $request, TimetableImport $timetableImport, TimetableImportService $service): JsonResponse
    {
        if (! $authUser = $this->userHasRole(self::ADMIN_ROLES)) {
            abort(403, 'Sie haben keine Berechtigung.');
        }

        $this->ensurePersonalSchoolyear($authUser);
        if ($timetableImport->school_id !== $authUser->school_id || $timetableImport->schoolyear_id !== $authUser->schoolyear_id) {
            abort(403, 'Kein Zugriff auf diesen Import.');
        }

        if ($timetableImport->import_status !== 'preview') {
            abort(409, 'Dieser Import ist keine offene Vorschau mehr.');
        }

        return response()->json(['data' => $service->comparisonFor(
            $timetableImport,
            $request->validated('operation'),
            $request->validated('scope'),
        )]);
    }

    public function confirm(ConfirmTimetableImportRequest $request, TimetableImport $timetableImport, TimetableImportService $service): JsonResponse
    {
        if (! $authUser = $this->userHasRole(self::ADMIN_ROLES)) {
            abort(403, 'Sie haben keine Berechtigung.');
        }

        $this->ensurePersonalSchoolyear($authUser);

        if ($timetableImport->school_id !== $authUser->school_id || $timetableImport->schoolyear_id !== $authUser->schoolyear_id) {
            abort(403, 'Kein Zugriff auf diesen Import.');
        }

        $queuedImport = $service->confirmPreview(
            $timetableImport,
            $request->validated('mode', 'strict'),
            $request->validated('operation', 'merge'),
            $request->validated('scope'),
            $request->validated('fingerprint'),
        );

        return response()->json([
            'message' => $queuedImport->isPartialImport()
                ? 'Teilimport wurde bestätigt und in die Warteschlange gestellt.'
                : 'Import wurde bestätigt und in die Warteschlange gestellt.',
            'data' => $queuedImport,
        ], 202);
    }

    public function downloadSource(TimetableImport $timetableImport): BinaryFileResponse
    {
        if (! $authUser = $this->userHasRole(self::ADMIN_ROLES)) {
            abort(403, 'Sie haben keine Berechtigung.');
        }

        $this->ensurePersonalSchoolyear($authUser);

        if ($timetableImport->school_id !== $authUser->school_id || $timetableImport->schoolyear_id !== $authUser->schoolyear_id) {
            abort(404, 'Import nicht gefunden.');
        }

        $sourcePath = $this->timetableImportSourcePath($timetableImport);
        if ($sourcePath === null) {
            abort(404, 'Die importierte Quelldatei ist nicht mehr verfügbar.');
        }

        return response()->download(
            $sourcePath,
            PrivateImportSourceFile::downloadName($timetableImport->original_filename, 'stundenplan', 'txt'),
            ['Content-Type' => 'text/plain; charset=UTF-8'],
        );
    }

    public function updateSingleDateAppointments(Request $request): JsonResponse
    {
        if (! $authUser = $this->userHasRole(self::ADMIN_ROLES)) {
            abort(403, 'Sie haben keine Berechtigung.');
        }

        $this->ensurePersonalSchoolyear($authUser);

        $validated = $request->validate([
            'appointments' => ['array'],
            'appointments.*.entry_ids' => ['required', 'array'],
            'appointments.*.entry_ids.*' => ['integer'],
            'appointments.*.active' => ['required', 'boolean'],
        ]);

        $schoolId = (int) $authUser->school_id;
        $schoolyearId = (int) $authUser->schoolyear_id;
        $allowedEntryIds = $this->singleDateEntryIds($schoolId, $schoolyearId);
        $requestedAppointments = collect($validated['appointments'] ?? []);

        foreach ([true, false] as $isActive) {
            $entryIds = $requestedAppointments
                ->filter(fn (array $appointment): bool => (bool) $appointment['active'] === $isActive)
                ->flatMap(fn (array $appointment): array => $appointment['entry_ids'])
                ->map(fn (int|string $entryId): int => (int) $entryId)
                ->filter(fn (int $entryId): bool => in_array($entryId, $allowedEntryIds, true))
                ->unique()
                ->values();

            if ($entryIds->isEmpty()) {
                continue;
            }

            StudentTimetableEntry::current()
                ->where('school_id', $schoolId)
                ->where('schoolyear_id', $schoolyearId)
                ->whereIn('id', $entryIds)
                ->update(['is_active' => $isActive]);
        }

        StudentTimetableOverviewService::forgetCacheFor($schoolId, $schoolyearId);

        return response()->json([
            'message' => 'Einzeltermine wurden gespeichert.',
            'main_dataset' => $this->mainDatasetMetadata($schoolId, $schoolyearId),
        ]);
    }

    private function mainDatasetMetadata(int $schoolId, int $schoolyearId, bool $includeSingleDateCourses = true): array
    {
        $baseQuery = StudentTimetableEntry::current()->where('school_id', $schoolId)
            ->where('schoolyear_id', $schoolyearId)
            ->where('is_active', true);
        $courses = $this->mainDatasetCourses($schoolId, $schoolyearId);

        return [
            'name' => 'Aktiver Stundenplan',
            'table' => 'student_timetable_entries',
            'entries_count' => (clone $baseQuery)->count(),
            'courses_count' => count($courses),
            'first_date' => (clone $baseQuery)->min('date'),
            'last_date' => (clone $baseQuery)->max('date'),
            'updated_at' => (clone $baseQuery)->max('updated_at'),
            'courses' => $courses,
            'single_date_courses' => $includeSingleDateCourses
                ? $this->mainDatasetSingleDateCourses($schoolId, $schoolyearId)
                : [],
        ];
    }

    /**
     * @return list<array{name: string, entries_count: int, first_date: ?string, last_date: ?string, weekly_hours: ?int}>
     */
    private function mainDatasetCourses(int $schoolId, int $schoolyearId): array
    {
        $weeklyHoursByCourse = $this->weeklyHoursByCourse($schoolId, $schoolyearId);

        return StudentTimetableEntry::current()
            ->where('school_id', $schoolId)
            ->where('schoolyear_id', $schoolyearId)
            ->where('is_active', true)
            ->whereNotNull('class_name')
            ->selectRaw('class_name as name, COUNT(*) as entries_count, MIN(date) as first_date, MAX(date) as last_date')
            ->groupBy('class_name')
            ->orderBy('class_name')
            ->get()
            ->map(fn (StudentTimetableEntry $entry): array => [
                'name' => (string) $entry->getAttribute('name'),
                'entries_count' => (int) $entry->getAttribute('entries_count'),
                'first_date' => $entry->getAttribute('first_date'),
                'last_date' => $entry->getAttribute('last_date'),
                'weekly_hours' => $weeklyHoursByCourse[(string) $entry->getAttribute('name')] ?? null,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, int>
     */
    private function weeklyHoursByCourse(int $schoolId, int $schoolyearId): array
    {
        return StudentTimetableEntry::current()
            ->where('school_id', $schoolId)
            ->where('schoolyear_id', $schoolyearId)
            ->where('is_active', true)
            ->whereNotNull('class_name')
            ->whereNotNull('date')
            ->get(['class_name', 'date', 'period', 'starts_at'])
            ->groupBy('class_name')
            ->map(fn ($entries): ?int => $this->typicalWeeklyHours($entries))
            ->filter(fn (?int $weeklyHours): bool => $weeklyHours !== null)
            ->all();
    }

    /**
     * @return list<array{
     *     name: string,
     *     appointments_count: int,
     *     appointments: list<array{
     *         date: string,
     *         weekday: int,
     *         period: ?string,
     *         starts_at: ?string,
     *         ends_at: ?string,
     *         subject: ?string,
     *         course: ?string,
     *         active: bool,
     *         entry_ids: list<int>
     *     }>
     * }>
     */
    private function mainDatasetSingleDateCourses(int $schoolId, int $schoolyearId): array
    {
        return StudentTimetableEntry::current()
            ->where('school_id', $schoolId)
            ->where('schoolyear_id', $schoolyearId)
            ->whereNotNull('class_name')
            ->whereNotNull('date')
            ->get([
                'date',
                'id',
                'semester',
                'period',
                'starts_at',
                'ends_at',
                'subject',
                'class_name',
                'course',
                'is_active',
            ])
            ->groupBy(fn (StudentTimetableEntry $entry): string => $this->singleDateGroupKey($entry))
            ->map(fn (Collection $entries): ?array => $this->singleDateGroupPayload($entries))
            ->filter()
            ->groupBy('course_name')
            ->map(fn (Collection $groups, string $courseName): array => [
                'name' => $courseName,
                'appointments_count' => $groups->sum(fn (array $group): int => count($group['appointments'])),
                'appointments' => $groups
                    ->flatMap(fn (array $group): array => $group['appointments'])
                    ->sortBy([
                        ['date', 'asc'],
                        ['period_sort', 'asc'],
                        ['starts_at', 'asc'],
                    ])
                    ->map(fn (array $appointment): array => collect($appointment)
                        ->except('period_sort')
                        ->all())
                    ->values()
                    ->all(),
            ])
            ->filter(fn (array $course): bool => $course['appointments_count'] > 0)
            ->sortBy('name')
            ->values()
            ->all();
    }

    private function singleDateGroupKey(StudentTimetableEntry $entry): string
    {
        $date = $entry->date instanceof CarbonInterface
            ? CarbonImmutable::instance($entry->date)
            : CarbonImmutable::parse($entry->date);

        return implode('|', [
            $entry->semester ?? '',
            $date->dayOfWeekIso,
            $this->hourFromPeriod($entry->period) ?? '',
            mb_strtolower((string) $entry->class_name),
            mb_strtolower((string) $entry->course),
            mb_strtolower((string) $entry->subject),
            '',
            '',
        ]);
    }

    /**
     * @param  Collection<int, StudentTimetableEntry>  $entries
     * @return array<string, mixed>|null
     */
    private function singleDateGroupPayload(Collection $entries): ?array
    {
        $firstEntry = $entries->first();
        if (! $firstEntry instanceof StudentTimetableEntry) {
            return null;
        }

        $dates = $entries
            ->map(fn (StudentTimetableEntry $entry): ?string => $entry->date?->toDateString())
            ->filter()
            ->unique()
            ->sort()
            ->values();

        if ($dates->isEmpty() || $dates->count() > self::SINGLE_DATE_MAXIMUM_DATES) {
            return null;
        }

        return [
            'course_name' => (string) $firstEntry->class_name,
            'appointments' => $dates
                ->map(fn (string $date): array => [
                    'date' => $date,
                    'weekday' => CarbonImmutable::parse($date)->dayOfWeekIso,
                    'period' => $firstEntry->period,
                    'period_sort' => $this->hourFromPeriod($firstEntry->period) ?? 0,
                    'starts_at' => $this->shortTime($firstEntry->starts_at),
                    'ends_at' => $this->shortTime($firstEntry->ends_at),
                    'subject' => $firstEntry->subject,
                    'course' => $firstEntry->course,
                    'active' => $entries
                        ->filter(fn (StudentTimetableEntry $entry): bool => $entry->date?->toDateString() === $date)
                        ->every(fn (StudentTimetableEntry $entry): bool => (bool) $entry->is_active),
                    'entry_ids' => $entries
                        ->filter(fn (StudentTimetableEntry $entry): bool => $entry->date?->toDateString() === $date)
                        ->pluck('id')
                        ->map(fn (int|string $entryId): int => (int) $entryId)
                        ->values()
                        ->all(),
                ])
                ->all(),
        ];
    }

    /**
     * @return list<int>
     */
    private function singleDateEntryIds(int $schoolId, int $schoolyearId): array
    {
        return collect($this->mainDatasetSingleDateCourses($schoolId, $schoolyearId))
            ->flatMap(fn (array $course): array => $course['appointments'])
            ->flatMap(fn (array $appointment): array => $appointment['entry_ids'])
            ->map(fn (int|string $entryId): int => (int) $entryId)
            ->unique()
            ->values()
            ->all();
    }

    private function ensurePersonalSchoolyear(User $authUser): void
    {
        if (! $authUser->schoolyear_id) {
            abort(422, 'Kein persönliches Schuljahr ausgewählt.');
        }
    }

    private function timetableImportSourcePath(TimetableImport $import): ?string
    {
        return PrivateImportSourceFile::resolve(
            $import->file_path,
            "app/private/{$import->school_id}/timetable-imports/{$import->schoolyear_id}",
        );
    }

    private function typicalWeeklyHours($entries): ?int
    {
        $weeklyCounts = collect($entries)
            ->groupBy(fn (StudentTimetableEntry $entry): string => $this->weekKey($entry))
            ->map(fn ($weekEntries): int => collect($weekEntries)
                ->map(fn (StudentTimetableEntry $entry): string => implode('|', [
                    $entry->date?->toDateString() ?? '',
                    $entry->period ?? '',
                    $entry->starts_at ?? '',
                ]))
                ->unique()
                ->count())
            ->filter(fn (int $count): bool => $count > 0)
            ->values();

        if ($weeklyCounts->isEmpty()) {
            return null;
        }

        return (int) $weeklyCounts
            ->countBy()
            ->sortKeysDesc()
            ->sortDesc()
            ->keys()
            ->first();
    }

    private function hourFromPeriod(?string $period): ?int
    {
        if (! preg_match('/\d+/', (string) $period, $match)) {
            return null;
        }

        return (int) $match[0];
    }

    private function shortTime(?string $time): ?string
    {
        $time = trim((string) $time);

        return $time !== ''
            ? substr($time, 0, 5)
            : null;
    }

    private function weekKey(StudentTimetableEntry $entry): string
    {
        /** @var CarbonInterface $date */
        $date = $entry->date;

        return "{$date->isoWeekYear()}-{$date->isoWeek()}";
    }
}
