<?php

namespace App\Services;

use App\Models\TeachingCourseStudentEntry;
use App\Models\TeachingCourseWork;
use Illuminate\Support\Facades\DB;

class TeachingCourseWorkEntrySyncService
{
    public const SOURCE_COURSE_WORK = 'course_work';

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
     * Build one derived student-entry row per student in work.groups.
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildRowsFromWork(TeachingCourseWork $work): array
    {
        $groups = is_array($work->groups) ? $work->groups : [];
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

