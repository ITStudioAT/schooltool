<?php

namespace App\Services\StudentsTimetables;

use App\Models\Import116;
use App\Models\StudentTimetableRecognitionImport;
use App\Models\User;
use Closure;
use Illuminate\Support\Facades\DB;

class StudentTimetableStudySelectionRefreshService
{
    public function __construct(
        private StudentTimetablesStudentOverviewService $studentOverviewService,
        private StudentTimetableCompletedCourseHistoryService $completedCourseHistoryService,
    ) {}

    public function refreshForUser(User $user, int $schoolyearId): int
    {
        return $this->refreshStudentSnapshots($user, $schoolyearId)['processed_students'];
    }

    /**
     * @param  Closure(array{
     *     total_students: int,
     *     processed_students: int,
     *     study_selections_updated: int,
     *     course_results_updated: int
     * }): void  $onProgress
     * @return array{
     *     total_students: int,
     *     processed_students: int,
     *     study_selections_updated: int,
     *     course_results_updated: int
     * }
     */
    public function refreshForUserWithProgress(User $user, int $schoolyearId, Closure $onProgress): array
    {
        return $this->refreshStudentSnapshots($user, $schoolyearId, $onProgress);
    }

    /**
     * @param  (Closure(array{
     *     total_students: int,
     *     processed_students: int,
     *     study_selections_updated: int,
     *     course_results_updated: int
     * }): void)|null  $onProgress
     * @return array{
     *     total_students: int,
     *     processed_students: int,
     *     study_selections_updated: int,
     *     course_results_updated: int
     * }
     */
    private function refreshStudentSnapshots(User $user, int $schoolyearId, ?Closure $onProgress = null): array
    {
        if ($schoolyearId <= 0) {
            return $this->emptyRefreshSummary();
        }

        $students = Import116::query()
            ->where('school_id', $user->school_id)
            ->where('schoolyear_id', $schoolyearId)
            ->whereNotNull('exists_date')
            ->orderBy('id')
            ->get();

        if ($students->isEmpty()) {
            return $this->emptyRefreshSummary();
        }

        $hasCompletedRecognitionImport = StudentTimetableRecognitionImport::query()
            ->where('school_id', $user->school_id)
            ->where('schoolyear_id', $schoolyearId)
            ->where('import_status', 'completed')
            ->exists();
        $selectionsByStudentCode = $hasCompletedRecognitionImport
            ? $this->studentOverviewService->studySelectionsForStudents($user, $students, $schoolyearId)
            : [];
        $coursesByStudentCode = $hasCompletedRecognitionImport
            ? $this->completedCourseHistoryService->coursesForStudentCodes(
                $user,
                $schoolyearId,
                $students
                    ->pluck('student_code')
                    ->map(fn (mixed $studentCode): string => trim((string) $studentCode))
                    ->filter()
                    ->unique()
                    ->values()
                    ->all(),
            )
            : [];
        $updatedAt = now();

        $refreshStudents = function () use (
            $students,
            $selectionsByStudentCode,
            $coursesByStudentCode,
            $hasCompletedRecognitionImport,
            $onProgress,
            $updatedAt,
        ): array {
            $summary = [
                'total_students' => $students->count(),
                'processed_students' => 0,
                'study_selections_updated' => 0,
                'course_results_updated' => 0,
            ];

            foreach ($students as $student) {
                $studentCode = trim((string) $student->student_code);
                $studySelection = $selectionsByStudentCode[$studentCode] ?? null;
                $courseResults = $hasCompletedRecognitionImport
                    ? $this->courseResultSnapshot($coursesByStudentCode[$studentCode] ?? [])
                    : null;

                if ($student->study_selection !== $studySelection) {
                    $summary['study_selections_updated']++;
                }

                if ($student->course_results !== $courseResults) {
                    $summary['course_results_updated']++;
                }

                $encodedStudySelection = $studySelection === null
                    ? null
                    : json_encode($studySelection, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
                $encodedCourseResults = $courseResults === null
                    ? null
                    : json_encode($courseResults, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

                DB::table('import116')
                    ->where('id', $student->id)
                    ->where('school_id', $student->school_id)
                    ->where('schoolyear_id', $student->schoolyear_id)
                    ->update([
                        'study_selection' => $encodedStudySelection,
                        'course_results' => $encodedCourseResults,
                        'updated_at' => $updatedAt,
                    ]);

                $summary['processed_students']++;

                if (
                    $onProgress
                    && ($summary['processed_students'] % 10 === 0
                        || $summary['processed_students'] === $summary['total_students'])
                ) {
                    $onProgress($summary);
                }
            }

            return $summary;
        };

        if ($onProgress) {
            return $refreshStudents();
        }

        return DB::transaction($refreshStudents);
    }

    /**
     * @return array{
     *     total_students: int,
     *     processed_students: int,
     *     study_selections_updated: int,
     *     course_results_updated: int
     * }
     */
    private function emptyRefreshSummary(): array
    {
        return [
            'total_students' => 0,
            'processed_students' => 0,
            'study_selections_updated' => 0,
            'course_results_updated' => 0,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $courses
     * @return array{
     *     completed: list<array{code: string, grade: string, status: string}>,
     *     negative: list<array{code: string, grade: string, status: string}>
     * }
     */
    private function courseResultSnapshot(array $courses): array
    {
        $completed = [];
        $negative = [];

        foreach ($courses as $course) {
            $code = trim((string) ($course['code'] ?? ''));
            $grade = mb_strtoupper(trim((string) ($course['grade'] ?? '')), 'UTF-8');

            if ($code === '' || $grade === '') {
                continue;
            }

            if ($grade === 'B') {
                $completed[] = [
                    'code' => $code,
                    'grade' => $grade,
                    'status' => 'exempt',
                ];

                continue;
            }

            if (in_array($grade, ['1', '2', '3', '4'], true)) {
                $completed[] = [
                    'code' => $code,
                    'grade' => $grade,
                    'status' => 'passed',
                ];

                continue;
            }

            if (in_array($grade, ['5', 'N'], true)) {
                $negative[] = [
                    'code' => $code,
                    'grade' => $grade,
                    'status' => 'failed',
                ];
            }
        }

        return [
            'completed' => $this->sortedCourseResults($completed),
            'negative' => $this->sortedCourseResults($negative),
        ];
    }

    /**
     * @param  list<array{code: string, grade: string, status: string}>  $courseResults
     * @return list<array{code: string, grade: string, status: string}>
     */
    private function sortedCourseResults(array $courseResults): array
    {
        return collect($courseResults)
            ->unique(fn (array $courseResult): string => "{$courseResult['code']}|{$courseResult['grade']}|{$courseResult['status']}")
            ->sortBy(fn (array $courseResult): string => "{$courseResult['code']}|{$courseResult['grade']}", SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();
    }
}
