<?php

namespace App\Services;

use App\Models\TeachingCourse;
use App\Models\TeachingCourseStudentEntry;
use App\Models\TeachingCourseWork;
use App\Models\TeachingCourseWorkGroupStudent;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TeachingCourseWorkEntrySyncService
{
    public const SOURCE_COURSE_WORK = 'course_work';

    private static ?bool $supportsGroupStudentIndexCache = null;

    public function __construct(private TeachingCourseService $courseService) {}

    public function syncWork(TeachingCourseWork $work): void
    {
        $normalizedGroups = null;
        $rows = $this->buildRowsFromWork($work, $normalizedGroups);

        DB::transaction(function () use ($work, $rows, $normalizedGroups) {
            if ($normalizedGroups !== null) {
                $work->groups = $normalizedGroups;
                $work->save();
            }

            TeachingCourseStudentEntry::where('teaching_course_work_id', $work->id)
                ->where('source', self::SOURCE_COURSE_WORK)
                ->delete();

            if (empty($rows)) {
                $this->syncGroupStudentIndex($work);

                return;
            }

            TeachingCourseStudentEntry::insert($rows);
            $this->syncGroupStudentIndex($work);
        });
    }

    public function deleteForWork(TeachingCourseWork $work): void
    {
        TeachingCourseStudentEntry::where('teaching_course_work_id', $work->id)
            ->where('source', self::SOURCE_COURSE_WORK)
            ->delete();

        if ($this->supportsGroupStudentIndex()) {
            TeachingCourseWorkGroupStudent::where('teaching_course_work_id', $work->id)->delete();
        }
    }

    public function syncGroupStudentIndex(TeachingCourseWork $work): int
    {
        if (! $this->supportsGroupStudentIndex() || ! $work->id) {
            return 0;
        }

        $rows = $this->groupStudentRowsFromGroups($work, is_array($work->groups) ? $work->groups : []);

        TeachingCourseWorkGroupStudent::where('teaching_course_work_id', $work->id)->delete();

        if (empty($rows)) {
            return 0;
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            TeachingCourseWorkGroupStudent::insert($chunk);
        }

        return count($rows);
    }

    public function serializeWork(TeachingCourseWork $work): array
    {
        $payload = $work->toArray();
        $payload['groups'] = $this->groupsForWork($work);

        return $payload;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function groupsForWork(TeachingCourseWork $work): array
    {
        if (! $this->supportsGroupStudentIndex()) {
            return is_array($work->groups) ? $work->groups : [];
        }

        $rows = $work->relationLoaded('teachingCourseWorkGroupStudents')
            ? $work->teachingCourseWorkGroupStudents
            : $work->teachingCourseWorkGroupStudents()->orderBy('group_index')->orderBy('id')->get();

        if ($rows->isEmpty()) {
            return is_array($work->groups) ? $work->groups : [];
        }

        return $this->groupsFromGroupStudentRows($rows);
    }

    /**
     * @param  Collection<int, TeachingCourseWorkGroupStudent>  $rows
     * @return array<int, array<string, mixed>>
     */
    public function groupsFromGroupStudentRows(Collection $rows): array
    {
        return $rows
            ->sortBy([['group_index', 'asc'], ['id', 'asc']])
            ->groupBy(fn (TeachingCourseWorkGroupStudent $row): int => (int) $row->group_index)
            ->map(function (Collection $groupRows): array {
                /** @var TeachingCourseWorkGroupStudent|null $first */
                $first = $groupRows->first();
                $usesIndividualGrades = $groupRows->contains(fn (TeachingCourseWorkGroupStudent $row): bool => (bool) $row->uses_individual_grades);

                $studentIds = $groupRows
                    ->pluck('user_id')
                    ->map(fn ($userId): int => (int) $userId)
                    ->filter(fn (int $userId): bool => $userId > 0)
                    ->unique()
                    ->values()
                    ->all();

                return [
                    'student_ids' => $studentIds,
                    'date' => $first?->group_date?->format('Y-m-d'),
                    'comment' => $first?->group_comment,
                    'grade' => $usesIndividualGrades ? null : $first?->group_grade,
                    'grades' => $usesIndividualGrades
                        ? $groupRows->map(fn (TeachingCourseWorkGroupStudent $row): array => [
                            'student_id' => (int) $row->user_id,
                            'grade' => $row->student_grade ?? '',
                        ])->values()->all()
                        : [],
                    'points' => $groupRows
                        ->filter(fn (TeachingCourseWorkGroupStudent $row): bool => $row->student_points !== null)
                        ->map(fn (TeachingCourseWorkGroupStudent $row): array => [
                            'student_id' => (int) $row->user_id,
                            'points' => (float) $row->student_points,
                        ])->values()->all(),
                    'comments' => $usesIndividualGrades
                        ? $groupRows->map(fn (TeachingCourseWorkGroupStudent $row): array => [
                            'student_id' => (int) $row->user_id,
                            'comment' => $row->student_comment ?? '',
                        ])->values()->all()
                        : [],
                    'name' => $first?->group_name,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $groups
     * @return array<int, array<string, mixed>>
     */
    public function groupStudentRowsFromGroups(TeachingCourseWork $work, array $groups): array
    {
        $rows = [];

        foreach ($groups as $groupIndex => $group) {
            if (! is_array($group)) {
                continue;
            }

            $gradesMap = $this->studentValueMap((array) ($group['grades'] ?? []), 'grade');
            $commentsMap = $this->studentValueMap((array) ($group['comments'] ?? []), 'comment');
            $pointsMap = $this->studentValueMap((array) ($group['points'] ?? []), 'points');

            $studentIds = array_values(array_unique(array_filter(array_map('intval', array_merge(
                (array) ($group['student_ids'] ?? []),
                array_keys($gradesMap),
                array_keys($commentsMap),
                array_keys($pointsMap),
            )), fn (int $id): bool => $id > 0)));

            if (empty($studentIds)) {
                continue;
            }

            $usesIndividualGrades = ! empty($gradesMap) || ! empty($commentsMap) || ! empty($pointsMap);
            $groupDate = $group['date'] ?? $work->date_for_all_groups;
            $groupDate = $groupDate ? date('Y-m-d', strtotime((string) $groupDate)) : null;

            foreach ($studentIds as $studentId) {
                $rawPoints = $pointsMap[$studentId] ?? null;

                $rows[] = [
                    'teaching_course_work_id' => $work->id,
                    'teaching_course_id' => $work->teaching_course_id,
                    'user_id' => $studentId,
                    'group_index' => (int) $groupIndex,
                    'group_name' => $this->toNullableString($group['name'] ?? null),
                    'group_date' => $groupDate,
                    'group_grade' => $usesIndividualGrades ? null : $this->toNullableString($group['grade'] ?? null),
                    'group_comment' => $this->toNullableString($group['comment'] ?? null),
                    'uses_individual_grades' => $usesIndividualGrades,
                    'student_grade' => $usesIndividualGrades ? $this->toNullableString($gradesMap[$studentId] ?? null) : null,
                    'student_points' => $rawPoints === null || $rawPoints === '' ? null : (float) $rawPoints,
                    'student_comment' => $usesIndividualGrades ? $this->toNullableString($commentsMap[$studentId] ?? null) : null,
                ];
            }
        }

        return $rows;
    }

    /**
     * @param  array<int, array<string, mixed>>  $groups
     * @return array<int, int>
     */
    public function groupStudentIdsFromGroups(array $groups): array
    {
        $studentIds = [];

        foreach ($groups as $group) {
            if (! is_array($group)) {
                continue;
            }

            foreach ((array) ($group['student_ids'] ?? []) as $studentId) {
                $id = (int) $studentId;
                if ($id > 0) {
                    $studentIds[$id] = true;
                }
            }

            foreach (['grades', 'points', 'comments'] as $field) {
                foreach ((array) ($group[$field] ?? []) as $item) {
                    if (! is_array($item)) {
                        continue;
                    }

                    $id = (int) ($item['student_id'] ?? 0);
                    if ($id > 0) {
                        $studentIds[$id] = true;
                    }
                }
            }
        }

        return array_values(array_map('intval', array_keys($studentIds)));
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, mixed>
     */
    private function studentValueMap(array $items, string $valueKey): array
    {
        $values = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $studentId = (int) ($item['student_id'] ?? 0);
            if ($studentId <= 0) {
                continue;
            }

            $values[$studentId] = $item[$valueKey] ?? null;
        }

        return $values;
    }

    public function supportsGroupStudentIndex(): bool
    {
        return self::$supportsGroupStudentIndexCache ??= Schema::hasTable('teaching_course_work_group_students');
    }

    /**
     * Re-sync all non-group works for a course so that students added after
     * the work was created receive their entries automatically.
     */
    public function syncNonGroupWorksForCourse(TeachingCourse $course): void
    {
        $works = TeachingCourseWork::where('teaching_course_id', $course->id)
            ->where('is_group_work', false)
            ->get();

        foreach ($works as $work) {
            $this->syncWork($work);
        }
    }

    /**
     * Build one derived student-entry row per student.
     *
     * For non-group works all current course students are included so that
     * students added after the work was created are not missed. Grades and
     * comments already stored in the groups array are preserved.
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildRowsFromWork(TeachingCourseWork $work, ?array &$normalizedGroups = null): array
    {
        $groups = is_array($work->groups) ? $work->groups : [];

        if (! $work->is_group_work) {
            return $this->buildRowsForNonGroupWork($work, $groups, $normalizedGroups);
        }

        return $this->buildRowsFromGroups($work, $groups);
    }

    /**
     * For non-group works: build rows for every active course student,
     * using stored group data for grades/comments when available.
     *
     * @param  array<int, array<string, mixed>>  $groups
     * @return array<int, array<string, mixed>>
     */
    private function buildRowsForNonGroupWork(TeachingCourseWork $work, array $groups, ?array &$normalizedGroups = null): array
    {
        $course = $work->teachingCourse;
        if (! $course) {
            return $this->buildRowsFromGroups($work, $groups);
        }

        // Build lookup maps from the stored groups so existing grades, points, and comments are preserved.
        $gradesByStudentId = [];
        $commentsByStudentId = [];
        $pointsByStudentId = [];
        $dateByStudentId = [];

        foreach ($groups as $group) {
            if (! is_array($group)) {
                continue;
            }

            $studentIds = array_values(array_unique(array_filter(
                array_map(fn ($id) => (int) $id, (array) ($group['student_ids'] ?? [])),
                fn ($id) => $id > 0
            )));

            if (empty($studentIds)) {
                continue;
            }

            $groupGrade = $this->toNullableString($group['grade'] ?? null);
            $groupComment = $this->toNullableString($group['comment'] ?? null);
            $groupDate = $group['date'] ?? null;

            $gradesMap = [];
            foreach ((array) ($group['grades'] ?? []) as $gradeItem) {
                if (! is_array($gradeItem)) {
                    continue;
                }
                $sid = (int) ($gradeItem['student_id'] ?? 0);
                if ($sid <= 0) {
                    continue;
                }
                $gradesMap[$sid] = $this->toNullableString($gradeItem['grade'] ?? null);
            }

            $commentsMap = [];
            foreach ((array) ($group['comments'] ?? []) as $commentItem) {
                if (! is_array($commentItem)) {
                    continue;
                }
                $sid = (int) ($commentItem['student_id'] ?? 0);
                if ($sid <= 0) {
                    continue;
                }
                $commentsMap[$sid] = $this->toNullableString($commentItem['comment'] ?? null);
            }

            $pointsMap = [];
            foreach ((array) ($group['points'] ?? []) as $pointsItem) {
                if (! is_array($pointsItem)) {
                    continue;
                }
                $sid = (int) ($pointsItem['student_id'] ?? 0);
                if ($sid <= 0) {
                    continue;
                }

                $rawPoints = $pointsItem['points'] ?? null;
                if ($rawPoints === null || $rawPoints === '') {
                    continue;
                }

                $pointsMap[$sid] = (float) $rawPoints;
            }

            foreach ($studentIds as $studentId) {
                $gradesByStudentId[$studentId] = $gradesMap[$studentId] ?? $groupGrade;
                $commentsByStudentId[$studentId] = $commentsMap[$studentId] ?? $groupComment;
                if (array_key_exists($studentId, $pointsMap)) {
                    $pointsByStudentId[$studentId] = $pointsMap[$studentId];
                }
                if ($groupDate) {
                    $dateByStudentId[$studentId] = date('Y-m-d', strtotime((string) $groupDate));
                }
            }
        }

        $defaultDate = $work->date_for_all_groups
            ? date('Y-m-d', strtotime((string) $work->date_for_all_groups))
            : null;

        $now = now();
        $byUserId = [];

        foreach ($course->teachingCourseStudents()->get() as $courseStudent) {
            $previousUserId = (int) ($courseStudent->user_id ?: 0);
            $importAliasId = (int) ($courseStudent->import116_id ?: 0);
            $studentId = $this->courseService->resolveCourseStudentUserId(
                $courseStudent,
                (int) $course->school_id,
                $course->schoolyear_id ? (int) $course->schoolyear_id : null
            ) ?? 0;

            if (! $studentId) {
                continue;
            }

            if ($previousUserId !== $studentId) {
                $courseStudent->update(['user_id' => $studentId]);
            }

            $studentAliases = array_values(array_unique(array_filter([
                $studentId,
                $previousUserId,
                $importAliasId,
            ], fn (int $id): bool => $id > 0)));

            $grade = $this->firstMappedValue($gradesByStudentId, $studentAliases);
            $comment = $this->firstMappedValue($commentsByStudentId, $studentAliases);
            $points = $this->firstMappedValue($pointsByStudentId, $studentAliases);
            $entryDate = $this->firstMappedValue($dateByStudentId, $studentAliases) ?? $defaultDate;
            $description = $comment ?: $this->toNullableString($work->description);

            $gradesByStudentId[$studentId] = $grade;
            $commentsByStudentId[$studentId] = $comment;
            if ($points !== null) {
                $pointsByStudentId[$studentId] = $points;
            }
            if ($entryDate !== null) {
                $dateByStudentId[$studentId] = $entryDate;
            }

            $byUserId[$studentId] = [
                'teaching_course_id' => $work->teaching_course_id,
                'user_id' => $studentId,
                'teaching_course_work_id' => $work->id,
                'date' => $entryDate,
                'description' => $description,
                'type' => $this->toNullableString($work->type),
                'grade' => $grade,
                'status' => null,
                'source' => self::SOURCE_COURSE_WORK,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $normalizedGroups = $this->buildNormalizedGroupsForNonGroupWork(
            $byUserId,
            $gradesByStudentId,
            $pointsByStudentId,
            $commentsByStudentId,
            $dateByStudentId,
            $defaultDate
        );

        return array_values($byUserId);
    }

    /**
     * @param  array<int, mixed>  $valuesByStudentId
     * @param  array<int, int>  $studentIds
     */
    private function firstMappedValue(array $valuesByStudentId, array $studentIds): mixed
    {
        foreach ($studentIds as $studentId) {
            if (array_key_exists($studentId, $valuesByStudentId)) {
                return $valuesByStudentId[$studentId];
            }
        }

        return null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rowsByUserId
     * @param  array<int, ?string>  $gradesByStudentId
     * @param  array<int, float|int>  $pointsByStudentId
     * @param  array<int, ?string>  $commentsByStudentId
     * @param  array<int, string>  $dateByStudentId
     * @return array<int, array<string, mixed>>
     */
    private function buildNormalizedGroupsForNonGroupWork(
        array $rowsByUserId,
        array $gradesByStudentId,
        array $pointsByStudentId,
        array $commentsByStudentId,
        array $dateByStudentId,
        ?string $defaultDate
    ): array {
        $groups = [];

        foreach ($rowsByUserId as $studentId => $row) {
            $resolvedStudentId = (int) ($row['user_id'] ?? $studentId);
            if ($resolvedStudentId <= 0) {
                continue;
            }

            $grade = $this->toNullableString($gradesByStudentId[$resolvedStudentId] ?? null);
            $comment = $this->toNullableString($commentsByStudentId[$resolvedStudentId] ?? null);

            $groups[] = [
                'student_ids' => [$resolvedStudentId],
                'date' => $dateByStudentId[$resolvedStudentId] ?? $defaultDate,
                'comment' => null,
                'grade' => null,
                'grades' => [[
                    'student_id' => $resolvedStudentId,
                    'grade' => $grade ?? '',
                ]],
                'points' => array_key_exists($resolvedStudentId, $pointsByStudentId) ? [[
                    'student_id' => $resolvedStudentId,
                    'points' => $pointsByStudentId[$resolvedStudentId],
                ]] : [],
                'comments' => [[
                    'student_id' => $resolvedStudentId,
                    'comment' => $comment ?? '',
                ]],
            ];
        }

        return $groups;
    }

    /**
     * Build rows from explicit group assignments (used for group works).
     *
     * @param  array<int, array<string, mixed>>  $groups
     * @return array<int, array<string, mixed>>
     */
    private function buildRowsFromGroups(TeachingCourseWork $work, array $groups): array
    {
        if (empty($groups)) {
            return [];
        }

        $byUserId = [];
        $now = now();

        foreach ($groups as $group) {
            if (! is_array($group)) {
                continue;
            }

            $studentIds = array_values(array_unique(array_filter(
                array_map(fn ($id) => (int) $id, (array) ($group['student_ids'] ?? [])),
                fn ($id) => $id > 0
            )));

            if (empty($studentIds)) {
                continue;
            }

            $groupGrade = $this->toNullableString($group['grade'] ?? null);
            $groupComment = $this->toNullableString($group['comment'] ?? null);
            $entryDate = $group['date'] ?? $work->date_for_all_groups;
            $entryDate = $entryDate ? date('Y-m-d', strtotime((string) $entryDate)) : null;

            $gradesMap = [];
            foreach ((array) ($group['grades'] ?? []) as $gradeItem) {
                if (! is_array($gradeItem)) {
                    continue;
                }
                $sid = (int) ($gradeItem['student_id'] ?? 0);
                if ($sid <= 0) {
                    continue;
                }
                $gradesMap[$sid] = $this->toNullableString($gradeItem['grade'] ?? null);
            }

            $commentsMap = [];
            foreach ((array) ($group['comments'] ?? []) as $commentItem) {
                if (! is_array($commentItem)) {
                    continue;
                }
                $sid = (int) ($commentItem['student_id'] ?? 0);
                if ($sid <= 0) {
                    continue;
                }
                $commentsMap[$sid] = $this->toNullableString($commentItem['comment'] ?? null);
            }

            foreach ($studentIds as $studentId) {
                $grade = $gradesMap[$studentId] ?? $groupGrade;
                $comment = $commentsMap[$studentId] ?? $groupComment;
                $description = $comment ?: $this->toNullableString($work->description);

                $byUserId[$studentId] = [
                    'teaching_course_id' => $work->teaching_course_id,
                    'user_id' => $studentId,
                    'teaching_course_work_id' => $work->id,
                    'date' => $entryDate,
                    'description' => $description,
                    'type' => $this->toNullableString($work->type),
                    'grade' => $grade,
                    'status' => null,
                    'source' => self::SOURCE_COURSE_WORK,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        return array_values($byUserId);
    }

    private function toNullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
