<?php

namespace App\Console\Commands;

use App\Models\Import116;
use App\Models\TeachingCourseBehaviourEntry;
use App\Models\TeachingCourseDate;
use App\Models\TeachingCourseStudent;
use App\Models\TeachingCourseStudentEntry;
use App\Models\TeachingCourseWork;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class TeachingCleanupCourseStudentCollisions extends Command
{
    protected $signature = 'teaching:cleanup-course-student-collisions
        {--apply : Persist changes (default is dry-run)}
        {--course-id=* : Limit cleanup to one or more course ids}
        {--force-row-id=* : Allow fixing specific row ids even when dependencies exist}';

    protected $description = 'Fix wrong TeachingCourseStudent rows caused by numeric ID collisions between users and import116.';

    /** @var array<int, array<int, bool>> */
    private array $attendanceDependencyCache = [];

    /** @var array<int, array<int, bool>> */
    private array $workGroupDependencyCache = [];

    /** @var array<int, bool> */
    private array $forceRowIdSet = [];

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $courseIds = array_values(array_unique(array_filter(array_map('intval', (array) $this->option('course-id')), fn (int $id) => $id > 0)));
        $forceRowIds = array_values(array_unique(array_filter(array_map('intval', (array) $this->option('force-row-id')), fn (int $id) => $id > 0)));
        $this->forceRowIdSet = array_fill_keys($forceRowIds, true);

        $rowsQuery = TeachingCourseStudent::query()
            ->withTrashed()
            ->whereNotNull('user_id')
            ->whereNull('import116_id')
            ->with([
                'teachingCourse:id,title,school_id,schoolyear_id,classes',
                'user:id,first_name,last_name,school_id,schoolclass',
            ]);

        if (! empty($courseIds)) {
            $rowsQuery->whereIn('teaching_course_id', $courseIds);
        }

        $rows = $rowsQuery->get();

        if ($rows->isEmpty()) {
            $this->info('No rows found that can be checked.');

            return self::SUCCESS;
        }

        $importsById = Import116::query()
            ->whereIn('id', $rows->pluck('user_id')->filter()->unique()->values())
            ->get(['id', 'school_id', 'schoolyear_id', 'class', 'first_name', 'last_name', 'email'])
            ->keyBy('id');

        $candidates = [];
        $skipped = [];
        $skippedDetails = [];

        foreach ($rows as $row) {
            [$isCandidate, $reason, $context] = $this->evaluateRow($row, $importsById->get((int) $row->user_id));

            if ($isCandidate) {
                $candidates[] = $context;
            } else {
                $skipped[] = $reason;
                $skippedDetails[] = [
                    'reason' => $reason,
                    'context' => $context,
                ];
            }
        }

        $this->line('--- Cleanup Summary ---');
        $this->line('Mode: '.($apply ? 'APPLY' : 'DRY-RUN'));
        $this->line('Checked rows: '.$rows->count());
        $this->line('Candidate fixes: '.count($candidates));
        $this->line('Skipped rows: '.count($skipped));
        if (! empty($forceRowIds)) {
            $this->line('Force row ids: '.implode(', ', $forceRowIds));
        }

        if (! empty($skipped)) {
            $reasonCounts = array_count_values($skipped);
            ksort($reasonCounts);
            foreach ($reasonCounts as $reason => $count) {
                $this->line("  - {$reason}: {$count}");
            }
        }

        $dependentRows = array_values(array_filter($skippedDetails, function (array $item): bool {
            return $item['reason'] === 'dependent_records_exist' && is_array($item['context']) && ! empty($item['context']);
        }));

        if (! empty($dependentRows)) {
            $this->line('Blocked by dependencies:');
            $this->table(
                ['row_id', 'course_id', 'course', 'old_user', 'new_import', 'dependencies', 'state'],
                array_map(function (array $item) {
                    $ctx = $item['context'];

                    return [
                        'row_id' => $ctx['row_id'] ?? '',
                        'course_id' => $ctx['course_id'] ?? '',
                        'course' => $ctx['course_title'] ?? '',
                        'old_user' => $ctx['old_user'] ?? '',
                        'new_import' => $ctx['new_import'] ?? '',
                        'dependencies' => $ctx['dependencies'] ?? '',
                        'state' => $ctx['row_state'] ?? '',
                    ];
                }, $dependentRows)
            );
        }

        if (empty($candidates)) {
            $this->info('No safe candidate rows found.');

            return self::SUCCESS;
        }

        $previewRows = array_map(function (array $candidate) {
            return [
                'row_id' => $candidate['row_id'],
                'course_id' => $candidate['course_id'],
                'course' => $candidate['course_title'],
                'old_user' => $candidate['old_user'],
                'new_import' => $candidate['new_import'],
                'dependencies' => $candidate['dependencies'] ?? '',
                'state' => $candidate['row_state'],
            ];
        }, $candidates);

        $this->table(
            ['row_id', 'course_id', 'course', 'old_user', 'new_import', 'dependencies', 'state'],
            $previewRows
        );

        if (! $apply) {
            $this->info('Dry-run only. Re-run with --apply to persist.');

            return self::SUCCESS;
        }

        $updated = 0;
        DB::transaction(function () use ($candidates, &$updated): void {
            foreach ($candidates as $candidate) {
                /** @var TeachingCourseStudent|null $row */
                $row = TeachingCourseStudent::query()->withTrashed()->lockForUpdate()->find($candidate['row_id']);
                if (! $row) {
                    continue;
                }

                $duplicateImportRowExists = TeachingCourseStudent::query()
                    ->withTrashed()
                    ->where('teaching_course_id', $row->teaching_course_id)
                    ->where('import116_id', $candidate['import_id'])
                    ->where('id', '!=', $row->id)
                    ->exists();

                if ($duplicateImportRowExists) {
                    continue;
                }

                $row->user_id = null;
                $row->import116_id = $candidate['import_id'];
                $row->save();
                $updated++;
            }
        });

        $this->info("Updated rows: {$updated}");

        return self::SUCCESS;
    }

    /**
     * @return array{0: bool, 1: string, 2: array<string, mixed>}
     */
    private function evaluateRow(TeachingCourseStudent $row, ?Import116 $import): array
    {
        $course = $row->teachingCourse;
        $user = $row->user;

        if (! $course || ! $user) {
            return [false, 'missing_course_or_user', []];
        }

        if (! $import) {
            return [false, 'no_import_with_same_id', []];
        }

        $baseContext = [
            'row_id' => (int) $row->id,
            'course_id' => (int) $course->id,
            'course_title' => (string) ($course->title ?? ''),
            'import_id' => (int) $import->id,
            'old_user' => $this->formatPerson((int) $user->id, $user->first_name, $user->last_name, $user->schoolclass),
            'new_import' => $this->formatPerson((int) $import->id, $import->first_name, $import->last_name, $import->class),
            'row_state' => $row->deleted_at ? 'soft-deleted' : 'active',
        ];

        if ((int) $import->school_id !== (int) $course->school_id) {
            return [false, 'import_school_mismatch', []];
        }

        if ($course->schoolyear_id && $import->schoolyear_id && (int) $import->schoolyear_id !== (int) $course->schoolyear_id) {
            return [false, 'import_schoolyear_mismatch', []];
        }

        $classes = is_array($course->classes) ? $course->classes : [];
        $importClass = trim((string) ($import->class ?? ''));
        if ($importClass === '' || ! in_array($importClass, $classes, true)) {
            return [false, 'import_class_not_in_course', []];
        }

        $userClass = trim((string) ($user->schoolclass ?? ''));
        if ($userClass !== '' && in_array($userClass, $classes, true)) {
            return [false, 'user_class_matches_course', []];
        }

        if (! $this->namesDiffer($user->first_name, $user->last_name, $import->first_name, $import->last_name)) {
            return [false, 'names_match', []];
        }

        if ($this->hasProtectedCourseStudentData($row)) {
            return [false, 'row_has_course_student_data', $baseContext];
        }

        $dependencyKinds = $this->dependentRecordKinds((int) $row->teaching_course_id, (int) $row->user_id);
        if (! empty($dependencyKinds)) {
            $baseContext['dependencies'] = implode(', ', $dependencyKinds);

            if (! isset($this->forceRowIdSet[(int) $row->id])) {
                return [false, 'dependent_records_exist', $baseContext];
            }

            $baseContext['forced'] = true;
            $baseContext['row_state'] = ($row->deleted_at ? 'soft-deleted' : 'active').' (forced)';
        }

        $duplicateImportRowExists = TeachingCourseStudent::query()
            ->withTrashed()
            ->where('teaching_course_id', $row->teaching_course_id)
            ->where('import116_id', $import->id)
            ->where('id', '!=', $row->id)
            ->exists();

        if ($duplicateImportRowExists) {
            return [false, 'duplicate_import_row_exists', $baseContext];
        }

        return [true, 'candidate', $baseContext];
    }

    private function formatPerson(int $id, ?string $firstName, ?string $lastName, ?string $class): string
    {
        $firstName = trim((string) $firstName);
        $lastName = trim((string) $lastName);
        $class = trim((string) $class);

        return sprintf(
            '%d: %s, %s (%s)',
            $id,
            $lastName !== '' ? $lastName : '-',
            $firstName !== '' ? $firstName : '-',
            $class !== '' ? $class : '-'
        );
    }

    private function namesDiffer(?string $userFirst, ?string $userLast, ?string $importFirst, ?string $importLast): bool
    {
        $uFirst = $this->normalizeNamePart($userFirst);
        $uLast = $this->normalizeNamePart($userLast);
        $iFirst = $this->normalizeNamePart($importFirst);
        $iLast = $this->normalizeNamePart($importLast);

        return $uFirst !== $iFirst || $uLast !== $iLast;
    }

    private function normalizeNamePart(?string $value): string
    {
        return mb_strtolower(trim((string) $value));
    }

    private function hasProtectedCourseStudentData(TeachingCourseStudent $row): bool
    {
        $fields = [
            $row->comment,
            $row->sem_1_grade,
            $row->sem_2_grade,
            $row->sem_grade,
            $row->behaviour_1_grade,
            $row->behaviour_2_grade,
            $row->behaviour_grade,
        ];

        foreach ($fields as $value) {
            if ($value !== null && trim((string) $value) !== '') {
                return true;
            }
        }

        return is_array($row->stars) && ! empty($row->stars);
    }

    private function hasDependentRecords(int $courseId, int $userId): bool
    {
        return ! empty($this->dependentRecordKinds($courseId, $userId));
    }

    /**
     * @return array<int, string>
     */
    private function dependentRecordKinds(int $courseId, int $userId): array
    {
        if ($courseId <= 0 || $userId <= 0) {
            return [];
        }

        $kinds = [];

        if (TeachingCourseStudentEntry::query()
            ->where('teaching_course_id', $courseId)
            ->where('user_id', $userId)
            ->exists()) {
            $kinds[] = 'student_entries';
        }

        if (TeachingCourseBehaviourEntry::query()
            ->where('teaching_course_id', $courseId)
            ->where('user_id', $userId)
            ->exists()) {
            $kinds[] = 'behaviour_entries';
        }

        if ($this->courseAttendanceDependsOnUser($courseId, $userId)) {
            $kinds[] = 'attendance';
        }

        if ($this->courseWorkGroupsDependOnUser($courseId, $userId)) {
            $kinds[] = 'work_groups';
        }

        return $kinds;
    }

    private function courseAttendanceDependsOnUser(int $courseId, int $userId): bool
    {
        if (! isset($this->attendanceDependencyCache[$courseId])) {
            $dependentUserSet = [];
            $dates = TeachingCourseDate::query()
                ->where('teaching_course_id', $courseId)
                ->get(['attendance', 'status']);

            foreach ($dates as $date) {
                $attendance = is_array($date->attendance) ? $date->attendance : [];
                foreach (array_keys($attendance) as $studentKey) {
                    $studentId = $this->normalizeAttendanceStudentId($studentKey);
                    if ($studentId !== null) {
                        $dependentUserSet[$studentId] = true;
                    }
                }

                $status = is_array($date->status) ? $date->status : [];
                foreach ($status as $statusItem) {
                    if (! is_string($statusItem) || ! str_starts_with($statusItem, 'att:')) {
                        continue;
                    }

                    $parts = explode(':', $statusItem);
                    if (count($parts) < 3) {
                        continue;
                    }

                    $studentId = $this->normalizeAttendanceStudentId($parts[1] ?? null);
                    if ($studentId !== null) {
                        $dependentUserSet[$studentId] = true;
                    }
                }
            }

            $this->attendanceDependencyCache[$courseId] = $dependentUserSet;
        }

        return isset($this->attendanceDependencyCache[$courseId][$userId]);
    }

    private function courseWorkGroupsDependOnUser(int $courseId, int $userId): bool
    {
        if (! isset($this->workGroupDependencyCache[$courseId])) {
            $dependentUserSet = [];
            $works = TeachingCourseWork::query()
                ->where('teaching_course_id', $courseId)
                ->get(['groups']);

            foreach ($works as $work) {
                $groups = is_array($work->groups) ? $work->groups : [];
                foreach ($groups as $group) {
                    if (! is_array($group)) {
                        continue;
                    }

                    foreach ((array) ($group['student_ids'] ?? []) as $studentId) {
                        $id = (int) $studentId;
                        if ($id > 0) {
                            $dependentUserSet[$id] = true;
                        }
                    }

                    foreach ((array) ($group['grades'] ?? []) as $gradeItem) {
                        if (! is_array($gradeItem)) {
                            continue;
                        }

                        $id = (int) ($gradeItem['student_id'] ?? 0);
                        if ($id > 0) {
                            $dependentUserSet[$id] = true;
                        }
                    }

                    foreach ((array) ($group['comments'] ?? []) as $commentItem) {
                        if (! is_array($commentItem)) {
                            continue;
                        }

                        $id = (int) ($commentItem['student_id'] ?? 0);
                        if ($id > 0) {
                            $dependentUserSet[$id] = true;
                        }
                    }
                }
            }

            $this->workGroupDependencyCache[$courseId] = $dependentUserSet;
        }

        return isset($this->workGroupDependencyCache[$courseId][$userId]);
    }

    private function normalizeAttendanceStudentId(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }

        $studentKey = trim((string) $value);
        if ($studentKey === '') {
            return null;
        }

        if (str_starts_with($studentKey, 's_')) {
            $studentKey = substr($studentKey, 2);
        }

        if (! ctype_digit($studentKey)) {
            return null;
        }

        $id = (int) $studentKey;

        return $id > 0 ? $id : null;
    }
}
