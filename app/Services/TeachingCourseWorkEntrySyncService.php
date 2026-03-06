<?php

namespace App\Services;

use App\Models\TeachingCourse;
use App\Models\TeachingCourseStudentEntry;
use App\Models\TeachingCourseWork;
use Illuminate\Support\Facades\DB;

class TeachingCourseWorkEntrySyncService
{
    public const SOURCE_COURSE_WORK = 'course_work';

    public function __construct(private TeachingCourseService $courseService) {}

    public function syncWork(TeachingCourseWork $work): void
    {
        $rows = $this->buildRowsFromWork($work);

        DB::transaction(function () use ($work, $rows) {
            TeachingCourseStudentEntry::where('teaching_course_work_id', $work->id)
                ->where('source', self::SOURCE_COURSE_WORK)
                ->delete();

            if (empty($rows)) {
                return;
            }

            TeachingCourseStudentEntry::insert($rows);
        });
    }

    public function deleteForWork(TeachingCourseWork $work): void
    {
        TeachingCourseStudentEntry::where('teaching_course_work_id', $work->id)
            ->where('source', self::SOURCE_COURSE_WORK)
            ->delete();
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
    private function buildRowsFromWork(TeachingCourseWork $work): array
    {
        $groups = is_array($work->groups) ? $work->groups : [];

        if (! $work->is_group_work) {
            return $this->buildRowsForNonGroupWork($work, $groups);
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
    private function buildRowsForNonGroupWork(TeachingCourseWork $work, array $groups): array
    {
        $course = $work->teachingCourse;
        if (! $course) {
            return $this->buildRowsFromGroups($work, $groups);
        }

        // Build lookup maps from the stored groups so existing grades/comments are preserved.
        $gradesByStudentId = [];
        $commentsByStudentId = [];
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

            foreach ($studentIds as $studentId) {
                $gradesByStudentId[$studentId] = $gradesMap[$studentId] ?? $groupGrade;
                $commentsByStudentId[$studentId] = $commentsMap[$studentId] ?? $groupComment;
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
            $studentId = (int) ($courseStudent->user_id ?: 0);

            if (! $studentId && $courseStudent->import116_id) {
                $studentId = $this->courseService->resolveStudentIdFromNumeric(
                    (int) $courseStudent->import116_id,
                    (int) $course->school_id
                ) ?? 0;

                // Normalize the course student record so that future serializations
                // use the resolved user_id and the student ID matches entry user_ids.
                if ($studentId) {
                    $courseStudent->update(['user_id' => $studentId]);
                }
            }

            if (! $studentId) {
                continue;
            }

            $grade = $gradesByStudentId[$studentId] ?? null;
            $comment = $commentsByStudentId[$studentId] ?? null;
            $entryDate = $dateByStudentId[$studentId] ?? $defaultDate;
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

        return array_values($byUserId);
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
