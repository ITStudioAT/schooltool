<?php

namespace App\Services\StudentsTimetables;

use App\Models\Import116;
use App\Models\SchoolTool;
use App\Models\StudentTimetableRecognitionRow;
use App\Models\StudentTimetableSubjectRow;
use App\Models\User;
use Illuminate\Support\Collection;

class StudentTimetablesStudentOverviewService
{
    /**
     * @return array<string, mixed>
     */
    public function summaryForUser(User $user): array
    {
        $schoolyearId = $this->schoolyearIdForUser($user);
        $student = $this->import116StudentForUser($user, $schoolyearId);
        $completedCourses = $this->completedCourses($user, $schoolyearId, $student?->student_code);
        $selection = $this->selectionForStudent($student, $completedCourses);
        $subjectCourses = $this->subjectCourses($user, $schoolyearId, $selection);
        $completedCourseCodes = $this->studentCompletedCourseCodes($completedCourses);
        $visitedCourseCodes = $this->studentVisitedCourseCodes($completedCourses);
        $proposedCourses = $this->plannedCoursesForSemester($subjectCourses, $selection, $completedCourseCodes);
        $missingCourses = $this->pendingCoursesBeforeSemester($subjectCourses, $selection, $completedCourseCodes, $visitedCourseCodes);
        $additionalCourses = $this->additionalCourses($subjectCourses, $selection, $completedCourseCodes, $visitedCourseCodes, $missingCourses, $proposedCourses);

        return [
            'student' => [
                'student_code' => $student?->student_code,
                'class' => $student?->class ?? $user->schoolclass,
                'first_name' => $student?->first_name ?? $user->first_name,
                'last_name' => $student?->last_name ?? $user->last_name,
            ],
            'selection' => $selection,
            'completed_courses' => $completedCourses,
            'missing_courses' => $missingCourses,
            'proposed_courses' => $proposedCourses,
            'additional_courses' => $additionalCourses,
            'counts' => [
                'completed_courses' => count($completedCourses),
                'missing_courses' => count($missingCourses),
                'proposed_courses' => count($proposedCourses),
                'additional_courses' => count($additionalCourses),
            ],
        ];
    }

    private function schoolyearIdForUser(User $user): int
    {
        $schoolyearId = $user->schoolyear_id
            ?? SchoolTool::query()
                ->where('school_id', $user->school_id)
                ->value('active_schoolyear_id');

        if (! $schoolyearId) {
            abort(422, 'Kein aktives Schuljahr gefunden.');
        }

        return (int) $schoolyearId;
    }

    private function import116StudentForUser(User $user, int $schoolyearId): ?Import116
    {
        if ($user->import116_id) {
            $student = Import116::query()
                ->where('school_id', $user->school_id)
                ->where('schoolyear_id', $schoolyearId)
                ->where('id', $user->import116_id)
                ->first();

            if ($student) {
                return $student;
            }
        }

        return Import116::query()
            ->where('school_id', $user->school_id)
            ->where('schoolyear_id', $schoolyearId)
            ->where('email', $user->email)
            ->first();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function completedCourses(User $user, int $schoolyearId, ?string $studentCode): array
    {
        if (! $studentCode) {
            return [];
        }

        return StudentTimetableRecognitionRow::query()
            ->where('school_id', $user->school_id)
            ->where('schoolyear_id', $schoolyearId)
            ->where('student_code', $studentCode)
            ->orderBy('subject')
            ->orderBy('row_number')
            ->get(['subject', 'grade', 'note', 'raw_data'])
            ->map(fn (StudentTimetableRecognitionRow $row): array => [
                'code' => $this->displayCourseCode((string) $row->subject),
                'name' => $this->displayCourseCode((string) $row->subject),
                'grade' => trim((string) ($row->note ?: $row->grade)),
                'semester' => $this->integerOrNull(data_get($row->raw_data, 'semester')),
            ])
            ->filter(fn (array $course): bool => $course['code'] !== '')
            ->unique(fn (array $course): string => $this->normalizedCourseCode((string) $course['code']))
            ->values()
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $completedCourses
     * @return array<string, mixed>
     */
    private function selectionForStudent(?Import116 $student, array $completedCourses): array
    {
        $completedCodes = collect($completedCourses)
            ->pluck('code')
            ->map(fn (mixed $code): string => $this->normalizedCourseCode((string) $code));
        $studentSemester = $this->studentSemester($student);

        return [
            'semester' => $studentSemester ?? $this->currentSemesterFromCompletedCourses($completedCourses),
            'religion' => $completedCodes->first(fn (string $code): bool => in_array($this->courseCodeWithoutModule($code), ['R', 'RK'], true)) ? 'R' : 'ETH',
            'language' => $completedCodes->first(fn (string $code): bool => in_array($this->courseCodeWithoutModule($code), ['F', 'S', 'SPA'], true))
                ? $this->languageFromCodes($completedCodes)
                : 'L',
            'branch' => $this->studentBranch($student),
            'arts_subject' => $completedCodes->first(fn (string $code): bool => $this->courseCodeWithoutModule($code) === 'BE') ? 'BE' : 'ME',
        ];
    }

    private function studentSemester(?Import116 $student): ?int
    {
        $schoolLevel = $this->studentSchoolLevelKey($student);
        $semesterBySchoolLevel = [
            '09_1' => 1,
            '09_2' => 2,
            '10_1' => 3,
            '10_2' => 4,
            '11_1' => 5,
            '11_2' => 6,
            '12_1' => 7,
            '12_2' => 8,
        ];

        return $semesterBySchoolLevel[$schoolLevel] ?? null;
    }

    private function studentBranch(?Import116 $student): ?string
    {
        $schoolLevel = trim((string) $student?->school_level);

        if ($this->studentSchoolLevelKey($student) !== '') {
            return null;
        }

        return $schoolLevel !== '' ? $schoolLevel : null;
    }

    private function studentSchoolLevelKey(?Import116 $student): string
    {
        if (! $student) {
            return '';
        }

        $importedSchoolLevel = $this->normalizedStudentSchoolLevel($student->school_level, $student->attendance_year);

        if ($importedSchoolLevel !== '') {
            return $importedSchoolLevel;
        }

        return $this->normalizedStudentSchoolLevel($student->class);
    }

    private function normalizedStudentSchoolLevel(?string $schoolLevel, ?string $attendanceYear = null): string
    {
        $normalizedAttendanceYear = trim((string) $attendanceYear);

        if ($normalizedAttendanceYear !== '') {
            $normalizedSchoolLevel = $this->normalizedStudentSchoolLevelToken($schoolLevel);

            if ($normalizedSchoolLevel !== '' && in_array($normalizedAttendanceYear, ['1', '2'], true)) {
                return "{$normalizedSchoolLevel}_{$normalizedAttendanceYear}";
            }
        }

        $value = trim((string) $schoolLevel);

        if ($value === '') {
            return '';
        }

        $normalizedValue = preg_replace('/[.\-\s]+/u', '_', $value) ?: '';

        if (preg_match('/(?:^|[^0-9])(0?9|1[0-2])_?([12])(?:$|[^0-9])/u', $normalizedValue, $match) !== 1) {
            return '';
        }

        return str_pad($match[1], 2, '0', STR_PAD_LEFT)."_{$match[2]}";
    }

    private function normalizedStudentSchoolLevelToken(?string $value): string
    {
        if (preg_match('/^(0?9|1[0-2])$/u', trim((string) $value), $match) !== 1) {
            return '';
        }

        return str_pad($match[1], 2, '0', STR_PAD_LEFT);
    }

    /**
     * @param  list<array<string, mixed>>  $completedCourses
     */
    private function currentSemesterFromCompletedCourses(array $completedCourses): ?int
    {
        return collect($completedCourses)
            ->pluck('semester')
            ->filter(fn (mixed $semester): bool => is_numeric($semester) && (int) $semester > 0)
            ->map(fn (mixed $semester): int => (int) $semester)
            ->max();
    }

    private function languageFromCodes(Collection $completedCodes): string
    {
        foreach (['L', 'F', 'S', 'SPA'] as $language) {
            if ($completedCodes->contains(fn (string $code): bool => $this->courseCodeWithoutModule($code) === $language)) {
                return $language === 'SPA' ? 'S' : $language;
            }
        }

        return 'L';
    }

    /**
     * @param  array<string, mixed>  $selection
     * @return list<array<string, mixed>>
     */
    private function subjectCourses(User $user, int $schoolyearId, array $selection): Collection
    {
        return StudentTimetableSubjectRow::query()
            ->where('school_id', $user->school_id)
            ->where('schoolyear_id', $schoolyearId)
            ->where('is_active', true)
            ->orderBy('semester')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->filter(fn (StudentTimetableSubjectRow $row): bool => $this->subjectMatchesSelection($row, $selection))
            ->flatMap(fn (StudentTimetableSubjectRow $row): array => $this->subjectRowCoursePayloads($row, $selection));
    }

    /**
     * @param  array<string, mixed>  $selection
     */
    private function subjectMatchesSelection(StudentTimetableSubjectRow $row, array $selection): bool
    {
        $branch = trim((string) $row->branch);
        $selectedBranch = trim((string) ($selection['branch'] ?? ''));

        if ($branch !== '' && $branch !== 'common' && $selectedBranch !== '' && $branch !== $selectedBranch) {
            return false;
        }

        if ($this->isArtsSubject($row)) {
            return $this->subjectBaseKey($row) === ($selection['arts_subject'] ?? 'ME');
        }

        if ($this->isLanguageSubject($row)) {
            $language = $this->languageSubjectCode($row);

            return $language === '' || $language === ($selection['language'] ?? 'L');
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $selection
     * @return list<array<string, mixed>>
     */
    private function subjectRowCoursePayloads(StudentTimetableSubjectRow $row, array $selection): array
    {
        $code = $this->selectedSubjectCode($row, $selection);
        $codes = $this->isReligionSubject($row) || $this->isLanguageSubject($row)
            ? [$code]
            : $this->courseCodeAliasParts($code);

        return collect($codes)
            ->map(fn (string $courseCode): array => [
                'code' => $courseCode,
                'name' => $row->name ?: $row->json_subject ?: $courseCode,
                'semester' => $row->semester,
                'branch' => $row->branch ?: 'common',
                'hours_per_week' => $row->hours_per_week !== null ? (float) $row->hours_per_week : null,
                'hours' => $row->hours_per_week !== null ? (float) $row->hours_per_week : null,
                'key' => implode('|', [
                    $row->id,
                    $row->semester,
                    $row->branch ?: 'common',
                    $row->json_code,
                    $row->json_subject,
                    $row->name,
                    $courseCode,
                ]),
            ])
            ->filter(fn (array $course): bool => trim((string) $course['code']) !== '')
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $selection
     */
    private function selectedSubjectCode(StudentTimetableSubjectRow $row, array $selection): string
    {
        if ($this->isReligionSubject($row)) {
            return (string) ($selection['religion'] ?? 'ETH').$this->subjectModuleNumber($row);
        }

        if ($this->isLanguageSubject($row)) {
            return (string) ($selection['language'] ?? 'L').$this->subjectModuleNumber($row);
        }

        return (string) ($row->json_code ?: $row->json_subject ?: $row->name ?: '');
    }

    /**
     * @param  list<array<string, mixed>>  $proposedCourses
     * @param  list<array<string, mixed>>  $completedCourses
     * @return list<array<string, mixed>>
     */
    private function plannedCoursesForSemester(Collection $subjectCourses, array $selection, array $completedCourseCodes): array
    {
        $semester = $this->integerOrNull($selection['semester'] ?? null);

        if (! $semester) {
            return [];
        }

        return $subjectCourses
            ->filter(fn (array $course): bool => (int) ($course['semester'] ?? 0) === $semester)
            ->reject(fn (array $course): bool => $this->courseCompletedForStudentPlanning($course, $completedCourseCodes))
            ->unique(fn (array $course): string => (string) $course['key'])
            ->sort(fn (array $firstCourse, array $secondCourse): int => strnatcasecmp((string) $firstCourse['code'], (string) $secondCourse['code']))
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function pendingCoursesBeforeSemester(Collection $subjectCourses, array $selection, array $completedCourseCodes, array $visitedCourseCodes): array
    {
        $semester = $this->integerOrNull($selection['semester'] ?? null);

        if (! $semester) {
            return [];
        }

        return $subjectCourses
            ->filter(fn (array $course): bool => (int) ($course['semester'] ?? 0) < $semester)
            ->reject(fn (array $course): bool => $this->courseCompletedForStudentPlanning($course, $completedCourseCodes))
            ->filter(fn (array $course): bool => $this->coursePossibleAsStudentMissing($course, $completedCourseCodes, $visitedCourseCodes))
            ->unique(fn (array $course): string => (string) $course['key'])
            ->sort(fn (array $firstCourse, array $secondCourse): int => strnatcasecmp((string) $firstCourse['code'], (string) $secondCourse['code']))
            ->values()
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $missingCourses
     * @param  list<array<string, mixed>>  $plannedCourses
     * @return list<array<string, mixed>>
     */
    private function additionalCourses(Collection $subjectCourses, array $selection, array $completedCourseCodes, array $visitedCourseCodes, array $missingCourses, array $plannedCourses): array
    {
        $semester = $this->integerOrNull($selection['semester'] ?? null);

        if (! $semester) {
            return [];
        }

        $plannedCourseCodes = $this->studentPlannedCourseCodes($plannedCourses);
        $regularCourseCodes = $this->studentPlannedCourseCodes([
            ...$missingCourses,
            ...$plannedCourses,
        ]);
        $unavailableCourseCodes = $this->studentUnavailableAdditionalCourseCodes($completedCourseCodes, [
            ...$missingCourses,
            ...$plannedCourses,
        ]);

        return $subjectCourses
            ->filter(fn (array $course): bool => (int) ($course['semester'] ?? 0) > $semester)
            ->reject(fn (array $course): bool => $this->courseCompletedForStudentPlanning($course, $unavailableCourseCodes))
            ->reject(fn (array $course): bool => $this->courseCompletedForStudentPlanning($course, $regularCourseCodes))
            ->filter(fn (array $course): bool => $this->coursePossibleAsStudentAdditional($course, $completedCourseCodes, $visitedCourseCodes, $plannedCourseCodes))
            ->unique(fn (array $course): string => (string) $course['key'])
            ->sort(fn (array $firstCourse, array $secondCourse): int => strnatcasecmp((string) $firstCourse['code'], (string) $secondCourse['code']))
            ->values()
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $completedCourses
     * @return list<string>
     */
    private function studentCompletedCourseCodes(array $completedCourses): array
    {
        return collect($completedCourses)
            ->filter(fn (array $course): bool => $this->completedCourseCountsAsDone((string) ($course['grade'] ?? '')))
            ->flatMap(fn (array $course): array => $this->courseCodeAliasParts((string) ($course['code'] ?? '')))
            ->map(fn (string $code): string => $this->normalizedCourseCode($code))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $completedCourses
     * @return list<string>
     */
    private function studentVisitedCourseCodes(array $completedCourses): array
    {
        return collect($completedCourses)
            ->flatMap(fn (array $course): array => $this->courseCodeAliasParts((string) ($course['code'] ?? '')))
            ->map(fn (string $code): string => $this->normalizedCourseCode($code))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function completedCourseCountsAsDone(string $grade): bool
    {
        $normalizedGrade = mb_strtoupper(trim($grade), 'UTF-8');

        return $normalizedGrade === 'B' || in_array($normalizedGrade, ['1', '2', '3', '4'], true);
    }

    /**
     * @param  list<string>  $completedCourseCodes
     */
    private function courseCompletedForStudentPlanning(array $course, array $completedCourseCodes): bool
    {
        if ($this->courseMatchesCourseCodeSet($course, $completedCourseCodes)) {
            return true;
        }

        return collect($this->courseModulePartsForStudentPlanning($course))
            ->contains(fn (array $parts): bool => $this->studentCourseCodesContainEquivalentModule($completedCourseCodes, $parts));
    }

    /**
     * @param  list<string>  $courseCodes
     */
    private function courseMatchesCourseCodeSet(array $course, array $courseCodes): bool
    {
        if ($courseCodes === []) {
            return false;
        }

        return collect($this->courseCodeAliases($course))
            ->contains(fn (string $courseCode): bool => in_array($courseCode, $courseCodes, true));
    }

    /**
     * @param  list<string>  $courseCodes
     */
    private function studentCourseCodesContainEquivalentModule(array $courseCodes, array $courseParts): bool
    {
        $moduleNumber = (string) ($courseParts['module'] ?? '');

        if ($courseCodes === [] || $moduleNumber === '') {
            return false;
        }

        $baseAliases = $this->studentCourseBaseAliases((string) ($courseParts['base'] ?? ''));

        return collect($courseCodes)
            ->map(fn (string $courseCode): array => $this->courseCodeModuleParts($courseCode))
            ->contains(fn (array $parts): bool => (string) ($parts['module'] ?? '') === $moduleNumber
                && in_array((string) ($parts['base'] ?? ''), $baseAliases, true));
    }

    /**
     * @param  list<string>  $completedCourseCodes
     * @param  list<string>  $visitedCourseCodes
     * @param  list<string>  $plannedCourseCodes
     */
    private function coursePossibleAsStudentAdditional(array $course, array $completedCourseCodes, array $visitedCourseCodes, array $plannedCourseCodes): bool
    {
        return collect($this->courseModulePartsForStudentPlanning($course))
            ->contains(fn (array $parts): bool => $this->courseModulePrerequisiteMet($parts, $completedCourseCodes, $visitedCourseCodes, [
                'planned_course_codes' => $plannedCourseCodes,
            ]));
    }

    /**
     * @param  list<string>  $completedCourseCodes
     * @param  list<string>  $visitedCourseCodes
     */
    private function coursePossibleAsStudentMissing(array $course, array $completedCourseCodes, array $visitedCourseCodes): bool
    {
        return collect($this->courseModulePartsForStudentPlanning($course))
            ->contains(fn (array $parts): bool => $this->courseModulePrerequisiteMet($parts, $completedCourseCodes, $visitedCourseCodes, [
                'allow_initial_modules' => true,
            ]));
    }

    /**
     * @return list<array{base: string, module: string}>
     */
    private function courseModulePartsForStudentPlanning(array $course): array
    {
        return collect($this->courseCodeAliases($course))
            ->map(fn (string $courseCode): array => $this->courseCodeModuleParts($courseCode))
            ->filter(fn (array $parts): bool => (string) ($parts['module'] ?? '') !== '')
            ->filter(fn (array $parts): bool => $this->courseBaseEligibleForStudentAdditional((string) ($parts['base'] ?? '')))
            ->unique(fn (array $parts): string => ($parts['base'] ?? '').'|'.($parts['module'] ?? ''))
            ->values()
            ->all();
    }

    private function courseBaseEligibleForStudentAdditional(string $base): bool
    {
        $baseAliases = $this->studentCourseBaseAliases($base);

        return collect([
            'BE',
            'BU',
            'CH',
            'D',
            'E',
            'ET',
            'ETH',
            'F',
            'GPB',
            'GS',
            'GW',
            'GWB',
            'INF',
            'L',
            'M',
            'ME',
            'MU',
            'PH',
            'PP',
            'R',
            'RK',
            'S',
            'SPA',
            'ÖKO',
        ])->intersect($baseAliases)->isNotEmpty();
    }

    /**
     * @param  list<string>  $completedCourseCodes
     * @param  list<string>  $visitedCourseCodes
     * @param  array<string, mixed>  $options
     */
    private function courseModulePrerequisiteMet(array $parts, array $completedCourseCodes, array $visitedCourseCodes, array $options = []): bool
    {
        $moduleNumber = $this->integerOrNull($parts['module'] ?? null);

        if (! $moduleNumber) {
            return false;
        }

        $baseAliases = $this->studentCourseBaseAliases((string) ($parts['base'] ?? ''));

        if ($moduleNumber === 1) {
            if (($options['allow_initial_modules'] ?? false) === true) {
                return ! $this->courseBaseHasVisitedLaterModule($baseAliases, $visitedCourseCodes, $moduleNumber);
            }

            return true;
        }

        if ($moduleNumber === 2) {
            $plannedCourseCodes = is_array($options['planned_course_codes'] ?? null)
                ? $options['planned_course_codes']
                : [];

            return collect($baseAliases)
                ->contains(fn (string $baseAlias): bool => in_array("{$baseAlias}1", $completedCourseCodes, true)
                    || in_array("{$baseAlias}1", $plannedCourseCodes, true));
        }

        $prerequisiteModuleNumber = $moduleNumber - 2;
        $hasPositivePrerequisite = collect($baseAliases)
            ->contains(fn (string $baseAlias): bool => in_array("{$baseAlias}{$prerequisiteModuleNumber}", $completedCourseCodes, true));

        if (! $hasPositivePrerequisite) {
            return false;
        }

        if (in_array($this->normalizedCourseCode((string) ($parts['base'] ?? '')), ['E', 'M'], true) && $moduleNumber === 8) {
            return collect($baseAliases)
                ->contains(fn (string $baseAlias): bool => in_array("{$baseAlias}7", $visitedCourseCodes, true));
        }

        return true;
    }

    /**
     * @param  list<string>  $baseAliases
     * @param  list<string>  $visitedCourseCodes
     */
    private function courseBaseHasVisitedLaterModule(array $baseAliases, array $visitedCourseCodes, int $moduleNumber): bool
    {
        if ($visitedCourseCodes === []) {
            return false;
        }

        return collect($visitedCourseCodes)
            ->map(fn (string $courseCode): array => $this->courseCodeModuleParts($courseCode))
            ->contains(function (array $parts) use ($baseAliases, $moduleNumber): bool {
                $visitedModuleNumber = $this->integerOrNull($parts['module'] ?? null);

                return in_array((string) ($parts['base'] ?? ''), $baseAliases, true)
                    && $visitedModuleNumber !== null
                    && $visitedModuleNumber > $moduleNumber;
            });
    }

    /**
     * @return list<string>
     */
    private function studentCourseBaseAliases(string $base): array
    {
        $normalizedBase = $this->normalizedCourseCode($base);
        $mappedAliases = [
            'GS' => ['GPB'],
            'GPB' => ['GS'],
            'GW' => ['GWB'],
            'GWB' => ['GW'],
            'ET' => ['ETH', 'R', 'RK'],
            'ETH' => ['ET', 'R', 'RK'],
            'ME' => ['MU'],
            'MU' => ['ME'],
            'R' => ['RK', 'ET', 'ETH'],
            'RK' => ['R', 'ET', 'ETH'],
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

    /**
     * @param  list<array<string, mixed>>  $plannedCourses
     * @return list<string>
     */
    private function studentPlannedCourseCodes(array $plannedCourses): array
    {
        return collect($plannedCourses)
            ->flatMap(fn (array $course): array => $this->courseCodeAliases($course))
            ->map(fn (string $courseCode): string => $this->normalizedCourseCode($courseCode))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $completedCourseCodes
     * @param  list<array<string, mixed>>  $plannedCourses
     * @return list<string>
     */
    private function studentUnavailableAdditionalCourseCodes(array $completedCourseCodes, array $plannedCourses): array
    {
        return collect($plannedCourses)
            ->flatMap(fn (array $course): array => $this->courseCodeAliases($course))
            ->merge($completedCourseCodes)
            ->map(fn (string $courseCode): string => $this->normalizedCourseCode($courseCode))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    private function courseCodeAliases(array $course): array
    {
        return collect([
            $course['code'] ?? null,
            $course['ttCode'] ?? null,
            ...($course['ttCodes'] ?? []),
        ])
            ->flatMap(fn (mixed $courseCode): array => $this->courseCodeAliasParts((string) $courseCode))
            ->map(fn (string $courseCode): string => $this->normalizedCourseCode($courseCode))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array{base: string, module: string}
     */
    private function courseCodeModuleParts(string $code): array
    {
        $normalizedCode = $this->normalizedCourseCode($code);

        return [
            'base' => $this->courseCodeWithoutModule($normalizedCode),
            'module' => $this->courseModuleNumber($normalizedCode),
        ];
    }

    private function isReligionSubject(StudentTimetableSubjectRow $row): bool
    {
        return $this->subjectBaseKey($row) === 'R/ET';
    }

    private function isLanguageSubject(StudentTimetableSubjectRow $row): bool
    {
        $baseKey = $this->normalizedCourseCode($this->subjectBaseKey($row));

        return $baseKey === 'L/F/S' || in_array($baseKey, ['L', 'F', 'S'], true);
    }

    private function isArtsSubject(StudentTimetableSubjectRow $row): bool
    {
        return in_array($this->subjectBaseKey($row), ['ME', 'BE'], true);
    }

    private function languageSubjectCode(StudentTimetableSubjectRow $row): string
    {
        $baseKey = $this->normalizedCourseCode($this->subjectBaseKey($row));

        if (in_array($baseKey, ['L', 'F', 'S'], true)) {
            return $baseKey;
        }

        $jsonCodeParts = collect($this->courseCodeAliasParts($this->courseCodeWithoutModule((string) $row->json_code)))
            ->map(fn (string $value): string => $this->normalizedCourseCode($value))
            ->all();

        return count($jsonCodeParts) === 1 && in_array($jsonCodeParts[0], ['L', 'F', 'S'], true)
            ? $jsonCodeParts[0]
            : '';
    }

    private function subjectBaseKey(StudentTimetableSubjectRow $row): string
    {
        $jsonSubject = trim((string) $row->json_subject);

        return $jsonSubject !== ''
            ? $jsonSubject
            : $this->courseCodeWithoutModule((string) $row->json_code);
    }

    private function subjectModuleNumber(StudentTimetableSubjectRow $row): string
    {
        return $this->courseModuleNumber((string) $row->json_code);
    }

    /**
     * @return list<string>
     */
    private function courseCodeAliasParts(string $value): array
    {
        return collect(explode('/', trim($value)))
            ->map(fn (string $part): string => trim($part))
            ->filter()
            ->values()
            ->all();
    }

    private function displayCourseCode(string $code): string
    {
        $code = $this->normalizedCourseCode($code);

        if (str_contains($code, '_')) {
            $code = collect(explode('_', $code))->filter()->last() ?: $code;
        }

        return $code;
    }

    private function normalizedCourseCode(string $code): string
    {
        return preg_replace('/\s+/u', '', mb_strtoupper(trim($code), 'UTF-8')) ?: '';
    }

    private function courseCodeWithoutModule(string $code): string
    {
        return preg_replace('/\d+$/u', '', $this->normalizedCourseCode($code)) ?: '';
    }

    private function courseModuleNumber(string $code): string
    {
        preg_match('/(\d+)$/u', $this->normalizedCourseCode($code), $match);

        return $match[1] ?? '';
    }

    private function integerOrNull(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }
}
