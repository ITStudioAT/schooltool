<?php

namespace App\Services\StudentsTimetables;

use App\Enums\StudentTimetableStudyProgram;
use App\Models\Import116;
use App\Models\StudentTimetableRecognitionRow;
use App\Models\StudentTimetableSubjectRow;
use App\Models\User;
use Illuminate\Support\Collection;

class StudentTimetableCompletedCourseHistoryService
{
    public function __construct(
        private StudentTimetableRecognitionIdentityService $identityService,
        private StudentTimetableOverviewService $overviewService,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function coursesForStudentCode(User $user, int $schoolyearId, ?string $studentCode): array
    {
        if (! $studentCode) {
            return [];
        }

        $subjectRows = StudentTimetableSubjectRow::query()
            ->forStudyProgram(StudentTimetableStudyProgram::Normalstudium)
            ->where('school_id', $user->school_id)
            ->where('schoolyear_id', $schoolyearId)
            ->where('is_active', true)
            ->get(['semester', 'json_code', 'json_subject', 'name']);

        $recognitionRows = StudentTimetableRecognitionRow::query()
            ->where('school_id', $user->school_id)
            ->where('schoolyear_id', $schoolyearId)
            ->where('student_code', $studentCode)
            ->orderByDesc('student_timetable_recognition_import_id')
            ->orderByDesc('id')
            ->get(['id', 'row_number', 'student_code', 'subject', 'grade', 'note', 'raw_data'])
            ->unique(fn (StudentTimetableRecognitionRow $row): string => $this->identityService->sourceIdentity(
                [
                    'student_code' => $row->student_code,
                    'subject' => $row->subject,
                    'grade' => $row->grade,
                    'note' => $row->note,
                    ...($row->raw_data ?? []),
                ],
            ))
            ->sortBy(fn (StudentTimetableRecognitionRow $row): string => sprintf(
                '%s|%010d',
                (string) $row->subject,
                (int) $row->row_number,
            ), SORT_NATURAL)
            ->values();

        $sequentialSubjectLabels = $this->sequentialRecognitionSubjectLabels($recognitionRows, $subjectRows);

        return $recognitionRows
            ->map(fn (StudentTimetableRecognitionRow $row): array => [
                'code' => $sequentialSubjectLabels[$row->id]
                    ?? $this->recognitionCompletedCourseSubjectLabel($row, $subjectRows),
                'grade' => $this->recognitionCompletedCourseGrade($row),
                'raw_data' => $row->raw_data,
            ])
            ->filter(fn (array $course): bool => $course['code'] !== '' && $course['grade'] !== '')
            ->groupBy(fn (array $course): string => "{$course['code']}|{$course['grade']}")
            ->map(function (Collection $attempts): array {
                $course = $attempts->first();

                return [
                    'code' => (string) ($course['code'] ?? ''),
                    'grade' => (string) ($course['grade'] ?? ''),
                    'raw_data' => $course['raw_data'] ?? [],
                    'attempt_count' => $attempts->count(),
                ];
            })
            ->sortBy([
                ['code', 'asc'],
                ['grade', 'asc'],
            ], SORT_NATURAL)
            ->map(fn (array $course): array => [
                'code' => (string) $course['code'],
                'name' => $this->recognitionCompletedCourseName((string) $course['code'], $subjectRows),
                'subject' => (string) $course['code'],
                'grade' => (string) $course['grade'],
                'semester' => $this->integerOrNull(data_get($course, 'raw_data.semester')),
                'attempt_count' => (int) ($course['attempt_count'] ?? 1),
            ])
            ->values()
            ->all();
    }

    public function studyProgramForStudentCode(
        User $user,
        int $schoolyearId,
        ?string $studentCode,
    ): StudentTimetableStudyProgram {
        return $this->studyInformationForStudentCode($user, $schoolyearId, $studentCode)['study_program'];
    }

    /** @return array{study_program: StudentTimetableStudyProgram, subject_plan: ?string, subject_plan_mismatch: bool} */
    public function studyInformationForStudentCode(
        User $user,
        int $schoolyearId,
        ?string $studentCode,
    ): array {
        if (! $studentCode) {
            return [
                'study_program' => StudentTimetableStudyProgram::Normalstudium,
                'subject_plan' => null,
                'subject_plan_mismatch' => false,
            ];
        }

        $student = Import116::query()
            ->where('school_id', $user->school_id)
            ->where('schoolyear_id', $schoolyearId)
            ->where('student_code', $studentCode)
            ->first(['class']);
        $studyProgram = $this->overviewService->isKompaktunterrichtClass((string) $student?->class)
            ? StudentTimetableStudyProgram::Kompaktstudium
            : StudentTimetableStudyProgram::Normalstudium;
        $subjectPlan = null;
        $subjectPlanMismatch = false;

        $rowsByImport = StudentTimetableRecognitionRow::query()
            ->where('school_id', $user->school_id)
            ->where('schoolyear_id', $schoolyearId)
            ->where('student_code', $studentCode)
            ->orderByDesc('student_timetable_recognition_import_id')
            ->orderByDesc('id')
            ->get(['student_timetable_recognition_import_id', 'raw_data'])
            ->groupBy('student_timetable_recognition_import_id');

        foreach ($rowsByImport as $recognitionRows) {
            $subjectPlans = $recognitionRows
                ->map(fn (StudentTimetableRecognitionRow $row): string => trim((string) data_get(
                    $row->raw_data,
                    'stundentafel',
                    '',
                )))
                ->filter()
                ->unique(fn (string $value): string => mb_strtoupper($value, 'UTF-8'))
                ->values();

            if ($subjectPlan === null && $subjectPlans->isNotEmpty()) {
                $subjectPlan = $subjectPlans->implode(' · ');
                $subjectPlanMismatch = $subjectPlans->contains(
                    fn (string $value): bool => ($subjectPlanStudyProgram = StudentTimetableStudyProgram::fromSubjectPlan($value)) !== null
                        && $subjectPlanStudyProgram !== $studyProgram,
                );
                break;
            }
        }

        return [
            'study_program' => $studyProgram,
            'subject_plan' => $subjectPlan,
            'subject_plan_mismatch' => $subjectPlanMismatch,
        ];
    }

    /**
     * @param  Collection<int, StudentTimetableSubjectRow>  $subjectRows
     */
    private function recognitionCompletedCourseName(string $code, Collection $subjectRows): string
    {
        $courseCodeAliases = $this->timetableCourseCodeAliases($code);
        $subjectRow = $subjectRows->first(function (StudentTimetableSubjectRow $subjectRow) use ($courseCodeAliases): bool {
            $jsonCode = $this->normalizedTimetableCourseCode((string) $subjectRow->json_code);

            return $jsonCode !== ''
                && array_intersect($courseCodeAliases, $this->timetableCourseCodeAliases($jsonCode)) !== [];
        });
        $name = trim((string) $subjectRow?->name);

        return $name !== '' ? $name : $code;
    }

    /**
     * @param  Collection<int, StudentTimetableRecognitionRow>  $recognitionRows
     * @param  Collection<int, StudentTimetableSubjectRow>  $subjectRows
     * @return array<int, string>
     */
    private function sequentialRecognitionSubjectLabels(Collection $recognitionRows, Collection $subjectRows): array
    {
        $labels = [];

        $recognitionRows
            ->groupBy(fn (StudentTimetableRecognitionRow $row): string => $this->normalizedTimetableCourseCode(
                $this->recognitionTimetableCourseCode((string) $row->subject),
            ))
            ->each(function (Collection $rows, string $subject) use ($subjectRows, &$labels): void {
                if ($subject === '' || $this->timetableCourseModuleNumber($subject) !== '') {
                    return;
                }

                $exactLabels = $rows
                    ->map(fn (StudentTimetableRecognitionRow $row): string => $this->resolvedRecognitionSubjectFromSubjectRows(
                        $subject,
                        trim((string) data_get($row->raw_data, 'semester', '')),
                        $subjectRows,
                    ));

                if ($exactLabels->every(fn (string $label): bool => $label !== '')) {
                    return;
                }

                $candidateLabels = $this->subjectPlanModuleLabelsForRecognitionSubject($subject, $subjectRows);
                if ($candidateLabels->count() < $rows->count()) {
                    return;
                }

                $rows
                    ->values()
                    ->each(function (StudentTimetableRecognitionRow $row, int $index) use ($candidateLabels, &$labels): void {
                        $labels[$row->id] = $candidateLabels[$index];
                    });
            });

        return $labels;
    }

    /**
     * @param  Collection<int, StudentTimetableSubjectRow>  $subjectRows
     */
    private function recognitionCompletedCourseSubjectLabel(StudentTimetableRecognitionRow $row, Collection $subjectRows): string
    {
        $subject = $this->recognitionTimetableCourseCode((string) $row->subject);
        $semester = trim((string) data_get($row->raw_data, 'semester', ''));
        $normalizedSubject = $this->normalizedTimetableCourseCode($subject);

        if ($normalizedSubject !== '') {
            $resolvedSubject = $this->resolvedRecognitionSubjectFromSubjectRows($normalizedSubject, $semester, $subjectRows);

            if ($resolvedSubject !== '') {
                return $resolvedSubject;
            }
        }

        if ($subject === '' || $semester === '' || preg_match('/\d+$/u', $subject) === 1) {
            return $subject;
        }

        return "{$subject}{$semester}";
    }

    /**
     * @param  Collection<int, StudentTimetableSubjectRow>  $subjectRows
     */
    private function resolvedRecognitionSubjectFromSubjectRows(string $subject, string $semester, Collection $subjectRows): string
    {
        $subjectCodeAliases = $this->timetableCourseCodeAliases($subject);

        foreach ($subjectRows as $subjectRow) {
            $jsonCode = $this->normalizedTimetableCourseCode((string) $subjectRow->json_code);
            if ($jsonCode === '') {
                continue;
            }

            if (array_intersect($subjectCodeAliases, $this->timetableCourseCodeAliases($jsonCode)) !== []) {
                return $jsonCode;
            }
        }

        $subjectBaseAliases = $this->timetableCourseBaseAliases($this->timetableCourseCodeWithoutModule($subject));
        $semesterCandidates = collect([
            $semester,
            $this->timetableCourseModuleNumber($subject),
        ])
            ->map(fn (mixed $value): int => (int) $value)
            ->filter(fn (int $value): bool => $value > 0)
            ->unique()
            ->values();

        if ($semesterCandidates->isEmpty()) {
            return '';
        }

        foreach ($subjectRows as $subjectRow) {
            if (! $semesterCandidates->contains((int) $subjectRow->semester)) {
                continue;
            }

            $jsonCode = $this->normalizedTimetableCourseCode((string) $subjectRow->json_code);
            $jsonSubject = $this->normalizedTimetableCourseCode((string) $subjectRow->json_subject);
            $rowBaseAliases = array_values(array_unique([
                ...$this->timetableCourseBaseAliases($this->timetableCourseCodeWithoutModule($jsonCode)),
                ...$this->timetableCourseBaseAliases($jsonSubject),
            ]));

            if (array_intersect($subjectBaseAliases, $rowBaseAliases) !== []) {
                return $jsonCode;
            }
        }

        return '';
    }

    /**
     * @param  Collection<int, StudentTimetableSubjectRow>  $subjectRows
     * @return Collection<int, string>
     */
    private function subjectPlanModuleLabelsForRecognitionSubject(string $subject, Collection $subjectRows): Collection
    {
        $subjectBaseAliases = $this->timetableCourseBaseAliases($this->timetableCourseCodeWithoutModule($subject));

        return $subjectRows
            ->map(function (StudentTimetableSubjectRow $subjectRow) use ($subjectBaseAliases): ?array {
                $jsonCode = $this->normalizedTimetableCourseCode((string) $subjectRow->json_code);
                if ($jsonCode === '' || $this->timetableCourseModuleNumber($jsonCode) === '') {
                    return null;
                }

                $jsonSubject = $this->normalizedTimetableCourseCode((string) $subjectRow->json_subject);
                $rowBaseAliases = array_values(array_unique([
                    ...$this->timetableCourseBaseAliases($this->timetableCourseCodeWithoutModule($jsonCode)),
                    ...$this->timetableCourseBaseAliases($jsonSubject),
                ]));

                if (array_intersect($subjectBaseAliases, $rowBaseAliases) === []) {
                    return null;
                }

                return [
                    'semester' => (int) $subjectRow->semester,
                    'module' => (int) $this->timetableCourseModuleNumber($jsonCode),
                    'json_code' => $jsonCode,
                ];
            })
            ->filter()
            ->sortBy([
                ['semester', 'asc'],
                ['module', 'asc'],
                ['json_code', 'asc'],
            ], SORT_NATURAL)
            ->pluck('json_code')
            ->unique()
            ->values();
    }

    /**
     * @return list<string>
     */
    private function timetableCourseCodeAliases(string $code): array
    {
        $normalizedCode = $this->normalizedTimetableCourseCode($code);
        $moduleNumber = $this->timetableCourseModuleNumber($normalizedCode);

        return collect($this->timetableCourseBaseAliases($this->timetableCourseCodeWithoutModule($normalizedCode)))
            ->map(fn (string $base): string => "{$base}{$moduleNumber}")
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    private function timetableCourseBaseAliases(string $base): array
    {
        $normalizedBase = $this->normalizedTimetableCourseCode($base);
        $mappedAliases = [
            'GS' => ['GPB'],
            'GPB' => ['GS'],
            'GW' => ['GWB'],
            'GWB' => ['GW'],
            'ME' => ['MU'],
            'MU' => ['ME'],
            'S' => ['SPA'],
            'SPA' => ['S'],
            'LPT' => ['LET'],
            'LET' => ['LPT'],
        ];

        return collect([
            $normalizedBase,
            ...($mappedAliases[$normalizedBase] ?? []),
        ])
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function timetableCourseCodeWithoutModule(string $code): string
    {
        return preg_replace('/\d+$/u', '', $code) ?: $code;
    }

    private function timetableCourseModuleNumber(string $code): string
    {
        preg_match('/(\d+)$/u', $code, $matches);

        return $matches[1] ?? '';
    }

    private function normalizedTimetableCourseCode(string $code): string
    {
        return preg_replace('/\s+/u', '', mb_strtoupper(trim($code), 'UTF-8')) ?: '';
    }

    private function recognitionTimetableCourseCode(string $subject): string
    {
        $code = $this->normalizedTimetableCourseCode($subject);

        if (! str_contains($code, '_')) {
            return $code;
        }

        return collect(explode('_', $code))
            ->map(fn (string $part): string => trim($part))
            ->filter()
            ->last() ?: $code;
    }

    private function recognitionCompletedCourseGrade(StudentTimetableRecognitionRow $row): string
    {
        $note = trim((string) $row->note);

        if ($note !== '') {
            return $note;
        }

        return trim((string) $row->grade);
    }

    private function integerOrNull(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (int) $value : null;
    }
}
