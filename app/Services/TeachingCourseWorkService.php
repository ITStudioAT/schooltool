<?php

namespace App\Services;

use App\Models\TeachingCourse;

class TeachingCourseWorkService
{
    public function __construct(
        private TeachingCourseService $courseService
    ) {}

    /**
     * Build individual (non-group) work groups: one group per student in the course.
     *
     * @return array<int, array{student_ids: int[], comment: null, grade: null}>
     */
    public function buildIndividualGroups(TeachingCourse $course): array
    {
        $students = $course->relationLoaded('teachingCourseStudents')
            ? $course->teachingCourseStudents
            : $course->teachingCourseStudents()->get(['user_id', 'import116_id']);

        $entries = $students->map(function ($student) {
            return ['id' => $student->user_id ?: $student->import116_id];
        })->all();

        $studentIds = [];
        foreach ($entries as $entry) {
            $resolvedId = $this->courseService->resolveStudentIdFromNumeric((int) ($entry['id'] ?? 0), (int) $course->school_id);
            if ($resolvedId) {
                $studentIds[] = $resolvedId;
            }
        }
        $studentIds = array_values(array_unique($studentIds));

        return array_values(array_map(function ($id) {
            return [
                'student_ids' => [(int) $id],
                'comment' => null,
                'grade' => null,
            ];
        }, $studentIds));
    }

    /**
     * Normalize all student_ids, grades, points, and comments within each work group
     * by resolving import/numeric IDs to real user IDs within the school.
     *
     * @return array<int, array>
     */
    public function normalizeWorkGroupsStudentIds(array $groups, int $schoolId): array
    {
        $normalized = [];

        foreach ($groups as $group) {
            if (! is_array($group)) {
                continue;
            }

            $studentIds = [];
            foreach (($group['student_ids'] ?? []) as $studentId) {
                $resolvedId = $this->courseService->resolveStudentIdFromNumeric((int) $studentId, $schoolId);
                if ($resolvedId) {
                    $studentIds[] = $resolvedId;
                }
            }
            $studentIds = array_values(array_unique($studentIds));

            $grades = [];
            foreach (($group['grades'] ?? []) as $gradeItem) {
                if (! is_array($gradeItem)) {
                    continue;
                }
                $resolvedId = $this->courseService->resolveStudentIdFromNumeric((int) ($gradeItem['student_id'] ?? 0), $schoolId);
                if (! $resolvedId) {
                    continue;
                }
                $grades[] = [
                    'student_id' => $resolvedId,
                    'grade' => $gradeItem['grade'] ?? '',
                ];
            }

            $comments = [];
            foreach (($group['comments'] ?? []) as $commentItem) {
                if (! is_array($commentItem)) {
                    continue;
                }
                $resolvedId = $this->courseService->resolveStudentIdFromNumeric((int) ($commentItem['student_id'] ?? 0), $schoolId);
                if (! $resolvedId) {
                    continue;
                }
                $comments[] = [
                    'student_id' => $resolvedId,
                    'comment' => $commentItem['comment'] ?? '',
                ];
            }

            $points = [];
            foreach (($group['points'] ?? []) as $pointsItem) {
                if (! is_array($pointsItem)) {
                    continue;
                }
                $resolvedId = $this->courseService->resolveStudentIdFromNumeric((int) ($pointsItem['student_id'] ?? 0), $schoolId);
                if (! $resolvedId) {
                    continue;
                }

                $rawPoints = $pointsItem['points'] ?? null;
                if ($rawPoints === null || $rawPoints === '') {
                    continue;
                }

                $points[] = [
                    'student_id' => $resolvedId,
                    'points' => (float) $rawPoints,
                ];
            }

            // Defensive cleanup:
            // If primary student_ids are empty, salvage ids from resolved grades/comments.
            // If still empty, drop the group entirely to avoid persisting blank student rows.
            if (empty($studentIds)) {
                $studentIds = array_values(array_unique(array_merge(
                    array_map(fn (array $item) => (int) ($item['student_id'] ?? 0), $grades),
                    array_map(fn (array $item) => (int) ($item['student_id'] ?? 0), $comments),
                    array_map(fn (array $item) => (int) ($item['student_id'] ?? 0), $points)
                )));
                $studentIds = array_values(array_filter($studentIds, fn (int $id) => $id > 0));
            }

            if (empty($studentIds)) {
                continue;
            }

            $normalized[] = array_merge($group, [
                'student_ids' => $studentIds,
                'grades' => $grades,
                'points' => $points,
                'comments' => $comments,
            ]);
        }

        return $normalized;
    }

    /**
     * Prepare validated data for storing or updating a course work,
     * handling individual vs. group work normalization.
     */
    public function prepareWorkData(array $validated, TeachingCourse $course, int $schoolId, ?bool $existingIsGroupWork = null): array
    {
        $isGroupWork = (bool) ($validated['is_group_work'] ?? $existingIsGroupWork ?? false);

        if (! $isGroupWork) {
            $validated['is_group_work'] = false;
            $validated['is_random_groups'] = false;
            $validated['group_size'] = null;
            if (empty($validated['groups'])) {
                $validated['groups'] = $this->buildIndividualGroups($course);
            }
        }

        $validated['groups'] = $this->normalizeWorkGroupsStudentIds(
            $validated['groups'] ?? [],
            $schoolId
        );

        return $validated;
    }
}
