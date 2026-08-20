<?php

namespace App\Services\StudentsTimetables;

use App\Enums\StudentTimetableStudyProgram;
use App\Models\Import116;
use App\Models\SchoolTool;
use App\Models\StudentTimetablePersonalTimetable;
use App\Models\StudentTimetableProfileSelection;
use App\Models\StudentTimetablePublishedTimetable;
use App\Models\StudentTimetableSubjectRow;
use App\Models\User;
use App\Services\SchoolHourService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class StudentTimetablesStudentOverviewService
{
    private const COURSE_SECTION_COLOR_MAP = [
        'completed' => '#00897B',
        'missing' => '#FB8C00',
        'proposed' => '#3949AB',
        'additional' => '#0288D1',
    ];

    private const DEFAULT_BRANCH = 'wirtschaftskundlich';

    private const COMPACT_SEMESTER_BY_SCHOOL_LEVEL = [
        '09_1' => 1,
        '09_2' => 2,
        '11_1' => 3,
        '11_2' => 4,
        '12_2' => 5,
    ];

    private const NORMAL_SEMESTER_BY_SCHOOL_LEVEL = [
        '09_1' => 1,
        '09_2' => 2,
        '10_1' => 3,
        '10_2' => 4,
        '11_1' => 5,
        '11_2' => 6,
        '12_1' => 7,
        '12_2' => 8,
    ];

    public function __construct(
        protected StudentTimetableCompletedCourseHistoryService $completedCourseHistoryService,
        protected StudentTimetableOverviewService $overviewService,
        protected SchoolHourService $schoolHourService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function summaryForUser(User $user, array $selectionOverride = []): array
    {
        $schoolyearId = $this->schoolyearIdForUser($user);
        $student = $this->import116StudentForUser($user, $schoolyearId);

        $savedSelectionOverride = $this->profileSelectionForUserAndSchoolyear($user, $schoolyearId);
        $selectionOverride = $this->normalizedSelectionOverride([
            ...$savedSelectionOverride,
            ...$selectionOverride,
        ]);

        return $this->summaryForStudent($user, $schoolyearId, $student, $selectionOverride);
    }

    /**
     * @return array<string, mixed>
     */
    public function updateProfileSelectionForUser(User $user, array $selection): array
    {
        $schoolyearId = $this->schoolyearIdForUser($user);
        $selection = $this->normalizedSelectionOverride($selection);

        DB::transaction(function () use ($user, $schoolyearId, $selection): void {
            $query = StudentTimetableProfileSelection::query()
                ->where('school_id', $user->school_id)
                ->where('schoolyear_id', $schoolyearId)
                ->where('user_id', $user->id);

            if ($selection === []) {
                $query->delete();

                return;
            }

            StudentTimetableProfileSelection::query()->updateOrCreate(
                [
                    'school_id' => $user->school_id,
                    'schoolyear_id' => $schoolyearId,
                    'user_id' => $user->id,
                ],
                [
                    'selection' => $selection,
                ],
            );
        });

        return $selection;
    }

    public function restoreProfileSelectionForUser(User $user): void
    {
        $schoolyearId = $this->schoolyearIdForUser($user);

        StudentTimetableProfileSelection::query()
            ->where('school_id', $user->school_id)
            ->where('schoolyear_id', $schoolyearId)
            ->where('user_id', $user->id)
            ->delete();
    }

    /**
     * @return array<string, mixed>
     */
    public function adoptPublishedTimetableForUser(User $user): array
    {
        $schoolyearId = $this->schoolyearIdForUser($user);
        $student = $this->import116StudentForUser($user, $schoolyearId);
        $studentCode = $this->nonEmptyString($student?->student_code);

        if (! $studentCode) {
            abort(404, 'Schülerdatensatz nicht gefunden.');
        }

        $publishedTimetable = StudentTimetablePublishedTimetable::query()
            ->where('school_id', $user->school_id)
            ->where('schoolyear_id', $schoolyearId)
            ->where('student_code', $studentCode)
            ->first();

        if (! $publishedTimetable) {
            abort(404, 'Gespeicherter Stundenplan nicht gefunden.');
        }

        StudentTimetablePersonalTimetable::query()->updateOrCreate(
            [
                'school_id' => $user->school_id,
                'schoolyear_id' => $schoolyearId,
                'user_id' => $user->id,
                'student_code' => $studentCode,
            ],
            [
                'student_label' => $publishedTimetable->student_label,
                'timetable' => is_array($publishedTimetable->timetable) ? $publishedTimetable->timetable : [],
                'state' => is_array($publishedTimetable->state) ? $publishedTimetable->state : [],
                'adopted_at' => now(),
            ],
        );

        return $this->summaryForUser($user);
    }

    /**
     * @param  array<string, mixed>  $timetable
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    public function savePersonalTimetableForUser(User $user, array $timetable, array $state = []): array
    {
        $schoolyearId = $this->schoolyearIdForUser($user);
        $student = $this->import116StudentForUser($user, $schoolyearId);
        $studentCode = $this->nonEmptyString($student?->student_code);

        if (! $studentCode) {
            abort(404, 'Schülerdatensatz nicht gefunden.');
        }

        StudentTimetablePersonalTimetable::query()->updateOrCreate(
            [
                'school_id' => $user->school_id,
                'schoolyear_id' => $schoolyearId,
                'user_id' => $user->id,
                'student_code' => $studentCode,
            ],
            [
                'student_label' => $this->studentLabel($student, $user),
                'timetable' => $timetable,
                'state' => $state,
                'adopted_at' => now(),
            ],
        );

        return $this->summaryForUser($user);
    }

    /**
     * @return array<string, mixed>
     */
    public function deletePersonalTimetableForUser(User $user): array
    {
        $schoolyearId = $this->schoolyearIdForUser($user);
        $student = $this->import116StudentForUser($user, $schoolyearId);
        $studentCode = $this->nonEmptyString($student?->student_code);

        if (! $studentCode) {
            abort(404, 'Schülerdatensatz nicht gefunden.');
        }

        StudentTimetablePersonalTimetable::query()
            ->where('school_id', $user->school_id)
            ->where('schoolyear_id', $schoolyearId)
            ->where('user_id', $user->id)
            ->where('student_code', $studentCode)
            ->delete();

        return $this->summaryForUser($user);
    }

    /**
     * @param  array<string, mixed>  $selectionOverride
     * @return array<string, mixed>
     */
    public function summaryForStudentCode(User $user, string $studentCode, array $selectionOverride = [], bool $strictSelectionOverride = false): array
    {
        $schoolyearId = $this->schoolyearIdForUser($user);
        $student = $this->studentByCode($user, $schoolyearId, $studentCode);

        return $this->summaryForStudent($user, $schoolyearId, $student, $selectionOverride, $strictSelectionOverride);
    }

    /**
     * @param  array<string, mixed>  $selectionOverride
     * @return array<string, mixed>
     */
    public function courseHistoryForStudentCode(User $user, string $studentCode, array $selectionOverride = [], bool $strictSelectionOverride = false): array
    {
        $schoolyearId = $this->schoolyearIdForUser($user);
        $student = $this->studentByCode($user, $schoolyearId, $studentCode);

        return $this->courseHistoryForStudent($user, $schoolyearId, $student, $selectionOverride, $strictSelectionOverride);
    }

    /**
     * @param  array<string, mixed>  $selectionOverride
     * @return array<string, mixed>
     */
    public function selectionSummaryForStudentCode(
        User $user,
        ?string $studentCode,
        array $selectionOverride = [],
        bool $strictSelectionOverride = false,
        StudentTimetableStudyProgram $studyProgram = StudentTimetableStudyProgram::Normalstudium,
        bool $includeAllSelectableModules = false,
    ): array {
        $schoolyearId = $this->schoolyearIdForUser($user);

        if (! $this->nonEmptyString($studentCode)) {
            $selection = [
                'semester' => null,
                'religion' => null,
                'language' => null,
                'branch' => null,
                'arts_subject' => null,
            ];
            $selection = $this->selectionWithOverrides($selection, $selectionOverride, $strictSelectionOverride);

            return [
                'student' => [
                    'student_code' => null,
                    'class' => null,
                    'first_name' => null,
                    'last_name' => null,
                    'religion' => null,
                    'instruction_type' => null,
                    'school_level' => null,
                    'school_level_mismatch' => false,
                    'original_school_level' => null,
                ],
                'selection' => $selection,
                'selection_options' => $this->selectionOptions(null),
                'selection_items' => $this->selectionItems($selection, null),
                'study_modules' => $this->studyModules([], []),
                'module_selection_groups' => $this->moduleSelectionGroups(
                    $user,
                    $schoolyearId,
                    $selection,
                    [],
                    [],
                    $studyProgram,
                    applySelectionEligibility: ! $includeAllSelectableModules,
                ),
            ];
        }

        $student = $this->studentByCode($user, $schoolyearId, $studentCode);
        $recognizedCourses = $this->recognizedCoursesWithResolvedGenericReligion(
            $this->completedCourses($user, $schoolyearId, $student->student_code),
            $student,
        );
        $completedCourses = $this->positiveCourses($recognizedCourses);
        $missingCourses = $this->negativeCourses($recognizedCourses);
        $completedReligionSelection = $this->completedReligionSelection($completedCourses);
        $selection = $this->selectionForStudent($user, $schoolyearId, $student, $completedCourses, $studyProgram);
        $selection = $this->selectionWithOverrides($selection, $selectionOverride, $strictSelectionOverride);

        if (
            $completedReligionSelection !== null
            && ! array_key_exists('religion', $selectionOverride)
        ) {
            $selection['religion'] = $completedReligionSelection;
        }

        return [
            'student' => [
                'student_code' => $student->student_code,
                'class' => $student->class,
                'first_name' => $student->first_name,
                'last_name' => $student->last_name,
                'religion' => $student->religion,
                'instruction_type' => $this->instructionTypeForStudent($student),
                'school_level' => $this->importedSchoolLevelForStudent($student),
                'school_level_mismatch' => $this->schoolLevelMismatchForStudent($student, $studyProgram),
                'original_school_level' => $this->originalSchoolLevelForStudent($student),
            ],
            'selection' => $selection,
            'selection_options' => $this->selectionOptions($student->religion),
            'selection_items' => $this->selectionItems($selection, $student->religion),
            'study_modules' => $this->studyModules($completedCourses, $missingCourses),
            'module_selection_groups' => $this->moduleSelectionGroups(
                $user,
                $schoolyearId,
                $selection,
                $completedCourses,
                $missingCourses,
                studyProgram: $studyProgram,
                limitToStudentProgression: ! $includeAllSelectableModules,
                applySelectionEligibility: ! $includeAllSelectableModules,
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $selectionOverride
     * @return array<string, mixed>
     */
    private function summaryForStudent(User $user, int $schoolyearId, ?Import116 $student, array $selectionOverride = [], bool $strictSelectionOverride = false): array
    {
        $courseHistory = $this->courseHistoryForStudent($user, $schoolyearId, $student, $selectionOverride, $strictSelectionOverride);

        return [
            ...$courseHistory,
            'selection_options' => $this->selectionOptions($student?->religion),
            'selection_items' => $this->selectionItems($courseHistory['selection'], $student?->religion),
            'manual_timetable' => $this->manualTimetableSelection($user, $courseHistory['course_sections']),
            'personal_timetable' => $this->personalTimetableForStudent($user, $schoolyearId, $student),
            'published_timetable' => $this->publishedTimetableForStudent($user, $schoolyearId, $student),
            'school_hours' => $this->schoolHoursForUser($user),
        ];
    }

    /**
     * @param  array<string, mixed>  $selectionOverride
     * @return array<string, mixed>
     */
    private function courseHistoryForStudent(User $user, int $schoolyearId, ?Import116 $student, array $selectionOverride = [], bool $strictSelectionOverride = false): array
    {
        $recognizedCourses = $this->completedCourses($user, $schoolyearId, $student?->student_code);
        $completedCourses = $this->positiveCourses($recognizedCourses);
        $selection = $this->selectionForStudent($user, $schoolyearId, $student, $completedCourses);
        $selection = $this->selectionWithOverrides($selection, $selectionOverride, $strictSelectionOverride);
        $subjectCourses = $this->subjectCourses($user, $schoolyearId, $selection);
        $completedCourseCodes = $this->studentCompletedCourseCodes($completedCourses);
        $visitedCourseCodes = $this->studentVisitedCourseCodes($recognizedCourses);
        $missingCourses = $this->negativeCourses($recognizedCourses);
        $proposedCourses = $this->plannedCoursesForProgression($subjectCourses, $completedCourseCodes, $visitedCourseCodes, $missingCourses);
        $additionalCourses = $this->additionalCoursesForProgression($subjectCourses, $completedCourseCodes, $visitedCourseCodes, $missingCourses, $proposedCourses);
        $courseSections = $this->courseSections($completedCourses, $missingCourses, $proposedCourses, $additionalCourses);
        $automaticCourseSelection = $this->automaticCourseSelection($courseSections);

        return [
            'student' => [
                'student_code' => $student?->student_code,
                'class' => $student?->class ?? $user->schoolclass,
                'first_name' => $student?->first_name ?? $user->first_name,
                'last_name' => $student?->last_name ?? $user->last_name,
                'religion' => $student?->religion,
            ],
            'selection' => $selection,
            'selection_override' => $selectionOverride,
            'completed_courses' => $completedCourses,
            'missing_courses' => $missingCourses,
            'proposed_courses' => $proposedCourses,
            'additional_courses' => $additionalCourses,
            'course_sections' => $courseSections,
            'automatic_course_selection' => $automaticCourseSelection,
            'counts' => [
                'completed_courses' => count($completedCourses),
                'missing_courses' => count($missingCourses),
                'proposed_courses' => count($proposedCourses),
                'additional_courses' => count($additionalCourses),
            ],
        ];
    }

    private function studentByCode(User $user, int $schoolyearId, string $studentCode): Import116
    {
        $student = Import116::query()
            ->where('school_id', $user->school_id)
            ->where('schoolyear_id', $schoolyearId)
            ->where('student_code', $studentCode)
            ->first();

        if (! $student) {
            abort(404, 'Student nicht gefunden.');
        }

        return $student;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function publishedTimetableForStudent(User $user, int $schoolyearId, ?Import116 $student): ?array
    {
        $studentCode = $this->nonEmptyString($student?->student_code);

        if (! $studentCode) {
            return null;
        }

        $publishedTimetable = StudentTimetablePublishedTimetable::query()
            ->where('school_id', $user->school_id)
            ->where('schoolyear_id', $schoolyearId)
            ->where('student_code', $studentCode)
            ->first();

        if (! $publishedTimetable) {
            return null;
        }

        $state = is_array($publishedTimetable->state) ? $publishedTimetable->state : [];

        return [
            'id' => (int) $publishedTimetable->id,
            'student_code' => (string) $publishedTimetable->student_code,
            'student_label' => $publishedTimetable->student_label,
            'name' => $publishedTimetable->name,
            'timetable' => is_array($publishedTimetable->timetable) ? $publishedTimetable->timetable : [],
            'state' => $state,
            'active_course_group_keys' => $this->publishedTimetableCourseGroupKeys($state),
            'published_at' => optional($publishedTimetable->published_at)->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function personalTimetableForStudent(User $user, int $schoolyearId, ?Import116 $student): ?array
    {
        $studentCode = $this->nonEmptyString($student?->student_code);

        if (! $studentCode) {
            return null;
        }

        $personalTimetable = StudentTimetablePersonalTimetable::query()
            ->where('school_id', $user->school_id)
            ->where('schoolyear_id', $schoolyearId)
            ->where('user_id', $user->id)
            ->where('student_code', $studentCode)
            ->first();

        if (! $personalTimetable) {
            return null;
        }

        $state = is_array($personalTimetable->state) ? $personalTimetable->state : [];

        return [
            'id' => (int) $personalTimetable->id,
            'student_code' => (string) $personalTimetable->student_code,
            'student_label' => $personalTimetable->student_label,
            'timetable' => is_array($personalTimetable->timetable) ? $personalTimetable->timetable : [],
            'state' => $state,
            'active_course_group_keys' => $this->publishedTimetableCourseGroupKeys($state),
            'adopted_at' => optional($personalTimetable->adopted_at)->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $state
     * @return list<string>
     */
    private function publishedTimetableCourseGroupKeys(array $state): array
    {
        return collect($state['activeCourseGroupFilterKeys'] ?? [])
            ->map(fn (mixed $courseGroupKey): string => (string) $courseGroupKey)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $selection
     * @param  array<string, mixed>  $selectionOverride
     * @return array<string, mixed>
     */
    private function selectionWithOverrides(array $selection, array $selectionOverride, bool $strictSelectionOverride = false): array
    {
        if ($selectionOverride === []) {
            return $selection;
        }

        if ($strictSelectionOverride) {
            return [
                'semester' => $this->integerOrNull($selectionOverride['semester'] ?? null) ?? $selection['semester'],
                'religion' => $this->strictSelectionOverrideValue($selectionOverride, 'religion'),
                'language' => $this->strictSelectionOverrideValue($selectionOverride, 'language'),
                'branch' => $this->strictSelectionOverrideValue($selectionOverride, 'branch'),
                'arts_subject' => $this->strictSelectionOverrideValue($selectionOverride, 'arts_subject', 'artsSubject'),
            ];
        }

        return [
            'semester' => $this->integerOrNull($selectionOverride['semester'] ?? null) ?? $selection['semester'],
            'religion' => array_key_exists('religion', $selectionOverride)
                ? $this->nonEmptyString($selectionOverride['religion'])
                : $selection['religion'],
            'language' => array_key_exists('language', $selectionOverride)
                ? $this->nonEmptyString($selectionOverride['language'])
                : $selection['language'],
            'branch' => array_key_exists('branch', $selectionOverride)
                ? $this->nonEmptyString($selectionOverride['branch'])
                : $selection['branch'],
            'arts_subject' => array_key_exists('arts_subject', $selectionOverride) || array_key_exists('artsSubject', $selectionOverride)
                ? $this->nonEmptyString($selectionOverride['arts_subject'] ?? $selectionOverride['artsSubject'])
                : $selection['arts_subject'],
        ];
    }

    /**
     * @param  array<string, mixed>  $selectionOverride
     */
    private function strictSelectionOverrideValue(array $selectionOverride, string $key, ?string $alternateKey = null): ?string
    {
        if (array_key_exists($key, $selectionOverride)) {
            return $this->nonEmptyString($selectionOverride[$key]);
        }

        if ($alternateKey && array_key_exists($alternateKey, $selectionOverride)) {
            return $this->nonEmptyString($selectionOverride[$alternateKey]);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $selection
     * @return array<string, ?string>
     */
    private function normalizedSelectionOverride(array $selection): array
    {
        return collect([
            'religion' => $this->religionOptions(),
            'language' => $this->languageOptions(),
            'branch' => $this->branchOptions(),
            'arts_subject' => $this->artsSubjectOptions(),
        ])
            ->mapWithKeys(function (array $options, string $key) use ($selection): array {
                $selectionKey = $key === 'arts_subject' && array_key_exists('artsSubject', $selection)
                    ? 'artsSubject'
                    : $key;

                if (! array_key_exists($selectionKey, $selection)) {
                    return [];
                }

                $value = $this->nonEmptyString($selection[$key] ?? ($key === 'arts_subject' ? $selection['artsSubject'] ?? null : null));

                if (! $value) {
                    return [$key => null];
                }

                $validValues = collect($options)
                    ->pluck('value')
                    ->map(fn (mixed $optionValue): string => (string) $optionValue)
                    ->all();

                return in_array($value, $validValues, true)
                    ? [$key => $value]
                    : [];
            })
            ->all();
    }

    /**
     * @return array<string, ?string>
     */
    private function profileSelectionForUserAndSchoolyear(User $user, int $schoolyearId): array
    {
        $profileSelection = StudentTimetableProfileSelection::query()
            ->where('school_id', $user->school_id)
            ->where('schoolyear_id', $schoolyearId)
            ->where('user_id', $user->id)
            ->first();

        return is_array($profileSelection?->selection) ? $this->normalizedSelectionOverride($profileSelection->selection) : [];
    }

    private function nonEmptyString(mixed $value): ?string
    {
        $stringValue = trim((string) $value);

        return $stringValue !== '' ? $stringValue : null;
    }

    private function studentLabel(?Import116 $student, User $user): string
    {
        return collect([
            $student?->class ?? $user->schoolclass,
            trim(implode(' ', array_filter([
                $student?->last_name ?? $user->last_name,
                $student?->first_name ?? $user->first_name,
            ]))),
        ])
            ->map(fn (mixed $value): string => trim((string) $value))
            ->filter()
            ->join(' · ');
    }

    /**
     * @param  array<string, mixed>  $selection
     * @return list<array<string, mixed>>
     */
    private function selectionItems(array $selection, ?string $studentReligion): array
    {
        return [
            [
                'key' => 'semester',
                'label' => 'Semester',
                'value' => $this->selectionOptionTitle($this->semesterOptions(), $selection['semester'] ?? null),
            ],
            [
                'key' => 'religion',
                'label' => 'Ethik / Religion',
                'value' => $this->selectionOptionTitle($this->religionOptions(), $selection['religion'] ?? null),
                'meta' => $this->studentReligionMeta($studentReligion),
            ],
            [
                'key' => 'language',
                'label' => 'Sprache',
                'value' => $this->selectionOptionTitle($this->languageOptions(), $selection['language'] ?? null),
            ],
            [
                'key' => 'branch',
                'label' => 'Zweig',
                'value' => $this->selectionOptionTitle($this->branchOptions(), $selection['branch'] ?? null),
            ],
            [
                'key' => 'arts_subject',
                'label' => 'ME / BE',
                'value' => $this->selectionOptionTitle($this->artsSubjectOptions(), $selection['arts_subject'] ?? null),
            ],
        ];
    }

    /**
     * @return array<string, list<array{title: string, value: mixed}>>
     */
    private function selectionOptions(?string $studentReligion): array
    {
        return [
            'religion' => $this->religionOptionsForStudent($studentReligion),
            'language' => $this->languageOptions(),
            'branch' => $this->branchOptions(),
            'arts_subject' => $this->artsSubjectOptions(),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $completedCourses
     * @param  list<array<string, mixed>>  $missingCourses
     * @param  list<array<string, mixed>>  $proposedCourses
     * @param  list<array<string, mixed>>  $additionalCourses
     * @return list<array<string, mixed>>
     */
    private function courseSections(array $completedCourses, array $missingCourses, array $proposedCourses, array $additionalCourses): array
    {
        return [
            [
                'key' => 'completed',
                'title' => 'Abgeschlossene Module',
                'icon' => 'mdi-check-circle-outline',
                'color' => self::COURSE_SECTION_COLOR_MAP['completed'],
                'items' => $completedCourses,
                'empty' => 'Keine abgeschlossenen Module gefunden.',
            ],
            [
                'key' => 'missing',
                'title' => 'Negative Module',
                'icon' => 'mdi-alert-circle-outline',
                'color' => self::COURSE_SECTION_COLOR_MAP['missing'],
                'items' => $missingCourses,
                'empty' => 'Keine negativen Module erkannt.',
            ],
            [
                'key' => 'proposed',
                'title' => 'Vorgesehene Module',
                'icon' => 'mdi-format-list-checks',
                'color' => self::COURSE_SECTION_COLOR_MAP['proposed'],
                'items' => $proposedCourses,
                'empty' => 'Keine vorgesehenen Module importiert.',
            ],
            [
                'key' => 'additional',
                'title' => 'Zusätzliche Module',
                'icon' => 'mdi-plus-circle-outline',
                'color' => self::COURSE_SECTION_COLOR_MAP['additional'],
                'items' => $additionalCourses,
                'empty' => 'Keine zusätzlichen Module erkannt.',
            ],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $courseSections
     * @return array<string, mixed>
     */
    private function automaticCourseSelection(array $courseSections): array
    {
        $sections = collect($courseSections)
            ->filter(fn (array $section): bool => in_array((string) ($section['key'] ?? ''), ['missing', 'proposed'], true))
            ->values()
            ->all();

        $courses = collect($sections)
            ->flatMap(fn (array $section): array => is_array($section['items'] ?? null) ? $section['items'] : [])
            ->values();
        $hours = $this->courseHoursTotal($courses->all());

        return [
            'title' => 'Negative Module + Vorgesehene Module',
            'sections' => $sections,
            'courses' => $courses->all(),
            'total' => $courses->count(),
            'hours' => $hours,
            'hours_label' => $this->courseHoursLabel($hours),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $courseSections
     * @return array<string, mixed>
     */
    private function manualTimetableSelection(User $user, array $courseSections): array
    {
        $courseGroups = $this->manualTimetableCourseGroupsForUser($user);
        $sections = collect($courseSections)
            ->filter(fn (array $section): bool => in_array((string) ($section['key'] ?? ''), ['missing', 'proposed', 'additional'], true))
            ->map(fn (array $section): array => [
                ...$section,
                'items' => collect(is_array($section['items'] ?? null) ? $section['items'] : [])
                    ->map(fn (array $course): array => $this->courseWithManualTimetableGroups($course, $courseGroups))
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();

        $regularCourses = collect($sections)
            ->filter(fn (array $section): bool => in_array((string) ($section['key'] ?? ''), ['missing', 'proposed'], true))
            ->flatMap(fn (array $section): array => is_array($section['items'] ?? null) ? $section['items'] : [])
            ->values();
        $additionalCourses = collect($sections)
            ->firstWhere('key', 'additional')['items'] ?? [];

        return [
            'title' => 'Manueller Stundenplan',
            'sections' => $sections,
            'courses' => $regularCourses->all(),
            'additional_courses' => is_array($additionalCourses) ? $additionalCourses : [],
            'total' => $regularCourses->count(),
            'course_group_count' => $regularCourses
                ->flatMap(fn (array $course): array => is_array($course['course_groups'] ?? null) ? $course['course_groups'] : [])
                ->pluck('key')
                ->unique()
                ->count(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function manualTimetableCourseGroupsForUser(User $user): array
    {
        $schoolHours = collect($this->schoolHoursForUser($user))->keyBy('hour');

        return collect($this->overviewService->courseGroupsForUser($user))
            ->map(function (array $courseGroup) use ($schoolHours): array {
                $schoolHour = $schoolHours->get((int) ($courseGroup['hour'] ?? 0), []);

                return [
                    ...$courseGroup,
                    'time_from' => $this->nonEmptyString($courseGroup['time_from'] ?? $courseGroup['from'] ?? $schoolHour['from'] ?? null),
                    'time_until' => $this->nonEmptyString($courseGroup['time_until'] ?? $courseGroup['until'] ?? $schoolHour['until'] ?? null),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $course
     * @param  list<array<string, mixed>>  $courseGroups
     * @return array<string, mixed>
     */
    private function courseWithManualTimetableGroups(array $course, array $courseGroups): array
    {
        $matchingGroups = collect($courseGroups)
            ->filter(fn (array $courseGroup): bool => $this->courseGroupMatchesCourse($courseGroup, $course))
            ->sortBy(fn (array $courseGroup): string => $this->manualCourseGroupSortValue($courseGroup))
            ->values()
            ->all();

        return [
            ...$course,
            'course_groups' => $matchingGroups,
        ];
    }

    /**
     * @param  array<string, mixed>  $courseGroup
     * @param  array<string, mixed>  $course
     */
    private function courseGroupMatchesCourse(array $courseGroup, array $course): bool
    {
        return collect($this->courseAliases($course))
            ->intersect($this->courseGroupCodes($courseGroup))
            ->isNotEmpty();
    }

    /**
     * @param  array<string, mixed>  $course
     * @return list<string>
     */
    private function courseAliases(array $course): array
    {
        return collect([
            $course['code'] ?? '',
            $course['ttCode'] ?? '',
            ...(is_array($course['ttCodes'] ?? null) ? $course['ttCodes'] : []),
        ])
            ->flatMap(fn (mixed $value): array => [
                (string) $value,
                $this->defaultTimetableCodeAlias((string) $value),
            ])
            ->flatMap(fn (string $value): array => $this->courseCodeAliasParts($value))
            ->map(fn (string $value): string => $this->normalizedCourseCode($value))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $courseGroup
     * @return list<string>
     */
    private function courseGroupCodes(array $courseGroup): array
    {
        return collect([
            $courseGroup['course'] ?? '',
            $courseGroup['subject'] ?? '',
            $courseGroup['module_code'] ?? '',
            ...$this->courseCodeTokensFromValue((string) ($courseGroup['title'] ?? '')),
            ...$this->courseCodeTokensFromValue((string) ($courseGroup['display_label'] ?? '')),
        ])
            ->map(fn (mixed $value): string => $this->normalizedCourseCode((string) $value))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    private function courseCodeTokensFromValue(string $value): array
    {
        return collect(preg_split('/[\s,;|()\\[\\]{}]+/u', $value) ?: [])
            ->flatMap(fn (string $part): array => preg_split('/[-–—]+/u', $part) ?: [])
            ->map(fn (string $part): string => trim($part))
            ->filter()
            ->values()
            ->all();
    }

    private function defaultTimetableCodeAlias(string $value): string
    {
        $normalizedValue = $this->normalizedCourseCode($value);

        if (! preg_match('/^([A-ZÄÖÜ]+)(\d*)$/u', $normalizedValue, $match)) {
            return '';
        }

        $aliases = [
            'ET' => 'ETH',
            'ETH' => 'ET',
            'GS' => 'GPB',
            'GPB' => 'GS',
            'GW' => 'GWB',
            'GWB' => 'GW',
            'ME' => 'MU',
            'MU' => 'ME',
            'R' => 'RK',
            'RK' => 'R',
            'S' => 'SPA',
            'SPA' => 'S',
            'LPT' => 'LET',
            'LET' => 'LPT',
        ];

        return isset($aliases[$match[1]]) ? $aliases[$match[1]].($match[2] ?? '') : '';
    }

    /**
     * @param  array<string, mixed>  $courseGroup
     */
    private function manualCourseGroupSortValue(array $courseGroup): string
    {
        return implode('|', [
            str_pad((string) ($courseGroup['semester'] ?? 99), 2, '0', STR_PAD_LEFT),
            str_pad((string) ($courseGroup['weekday'] ?? 99), 2, '0', STR_PAD_LEFT),
            str_pad((string) ($courseGroup['hour'] ?? 99), 2, '0', STR_PAD_LEFT),
            (string) ($courseGroup['display_label'] ?? $courseGroup['title'] ?? ''),
        ]);
    }

    /**
     * @return list<array{hour: int, from: ?string, until: ?string}>
     */
    private function schoolHoursForUser(User $user): array
    {
        return $this->schoolHourService->listForUser($user)
            ->map(fn (mixed $schoolHour): array => [
                'hour' => (int) $schoolHour->hour,
                'from' => $this->formatTimeValue($schoolHour->from),
                'until' => $this->formatTimeValue($schoolHour->until),
            ])
            ->values()
            ->all();
    }

    private function formatTimeValue(mixed $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        return mb_substr($value, 0, 5);
    }

    /**
     * @return list<array{title: string, value: int}>
     */
    private function semesterOptions(): array
    {
        return collect(range(1, 8))
            ->map(fn (int $semester): array => [
                'title' => "Semester {$semester}",
                'value' => $semester,
            ])
            ->all();
    }

    /**
     * @return list<array{title: string, value: string}>
     */
    private function religionOptions(): array
    {
        return [
            ['title' => 'ETH - Ethik', 'value' => 'ETH'],
            ['title' => 'Rev - Religion evangelisch', 'value' => 'Rev'],
            ['title' => 'Ris - Religion Islam', 'value' => 'Ris'],
            ['title' => 'Rk - Religion katholisch', 'value' => 'Rk'],
            ['title' => 'Ror - Religion orthodox', 'value' => 'Ror'],
        ];
    }

    /**
     * @return list<array{title: string, value: string}>
     */
    private function religionOptionsForStudent(?string $studentReligion): array
    {
        $religion = $this->nonEmptyString($studentReligion);

        if (! $religion || $this->studentReligionMatchesNoConfession($religion)) {
            return $this->religionOptions();
        }

        $allowedValues = collect(['ETH', $this->studentReligionOptionValue($religion)])
            ->filter()
            ->values()
            ->all();

        return collect($this->religionOptions())
            ->filter(fn (array $option): bool => in_array($option['value'], $allowedValues, true))
            ->values()
            ->all();
    }

    private function studentReligionMatchesNoConfession(string $religion): bool
    {
        return in_array($this->normalizedStudentReligion($religion), [
            'ob',
            'ohnebekenntnis',
            'ohnebekenntniss',
            'keinbekenntnis',
            'keinbekenntniss',
            'konfessionslos',
        ], true);
    }

    private function studentReligionOptionValue(string $religion): ?string
    {
        $normalizedReligion = $this->normalizedStudentReligion($religion);

        foreach ($this->studentReligionOptionAliases() as $optionValue => $aliases) {
            foreach ($aliases as $alias) {
                if (
                    $normalizedReligion === $alias
                    || (mb_strlen($alias) >= 4 && str_contains($normalizedReligion, $alias))
                    || (mb_strlen($normalizedReligion) >= 4 && str_contains($alias, $normalizedReligion))
                ) {
                    return $optionValue;
                }
            }
        }

        return null;
    }

    private function normalizedStudentReligion(string $religion): string
    {
        $normalizedReligion = mb_strtolower(trim($religion), 'UTF-8');
        $normalizedReligion = strtr($normalizedReligion, [
            'ä' => 'a',
            'ö' => 'o',
            'ü' => 'u',
            'ß' => 'ss',
        ]);

        return preg_replace('/[^a-z0-9]/u', '', $normalizedReligion) ?: '';
    }

    /**
     * @return array<string, list<string>>
     */
    private function studentReligionOptionAliases(): array
    {
        return [
            'Rev' => ['rev', 'ev', 'evang', 'evangelisch', 'evangab', 'evangelischab'],
            'Ris' => ['ris', 'islam', 'islamisch', 'muslim', 'moslem'],
            'Rk' => ['rk', 'kath', 'katholisch', 'romkath', 'roemkath', 'roemischkatholisch'],
            'Ror' => ['ror', 'orth', 'orthodox', 'griechorth', 'griechischorthodox'],
        ];
    }

    /**
     * @return list<array{title: string, value: string}>
     */
    private function languageOptions(): array
    {
        return [
            ['title' => 'L - Latein', 'value' => 'L'],
            ['title' => 'F - Französisch', 'value' => 'F'],
            ['title' => 'S - Spanisch', 'value' => 'S'],
        ];
    }

    /**
     * @return list<array{title: string, value: string}>
     */
    private function branchOptions(): array
    {
        return [
            ['title' => 'Wirtschaftskundlicher Zweig', 'value' => 'wirtschaftskundlich'],
            ['title' => 'Gymnasialer Zweig', 'value' => 'gymnasial'],
        ];
    }

    /**
     * @return list<array{title: string, value: string}>
     */
    private function artsSubjectOptions(): array
    {
        return [
            ['title' => 'ME - Musikerziehung', 'value' => 'ME'],
            ['title' => 'BE - Bildnerische Erziehung', 'value' => 'BE'],
        ];
    }

    /**
     * @param  list<array{title: string, value: mixed}>  $options
     */
    private function selectionOptionTitle(array $options, mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return collect($options)
            ->firstWhere('value', $value)['title'] ?? (string) $value;
    }

    private function studentReligionMeta(?string $religion): ?string
    {
        $value = trim((string) $religion);

        return $value !== '' ? "Religion: {$value}" : null;
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
        return $this->completedCourseHistoryService->coursesForStudentCode($user, $schoolyearId, $studentCode);
    }

    /**
     * @param  list<array<string, mixed>>  $completedCourses
     * @return array<string, mixed>
     */
    private function selectionForStudent(
        User $user,
        int $schoolyearId,
        ?Import116 $student,
        array $completedCourses,
        ?StudentTimetableStudyProgram $studyProgram = null,
    ): array {
        $completedCodes = collect($completedCourses)
            ->pluck('code')
            ->map(fn (mixed $code): string => $this->normalizedCourseCode((string) $code));
        $studentSemester = $this->semesterForStudent($student, $studyProgram);

        return [
            'semester' => $studentSemester ?? $this->currentSemesterFromCompletedCourses($completedCourses),
            'religion' => $this->selectionOptionFromCompletedCodes($completedCodes, $this->religionSelectionCourseAliases())
                ?? $this->studentReligionOptionValue((string) $student?->religion),
            'language' => $this->selectionOptionFromCompletedCodes($completedCodes, $this->languageSelectionCourseAliases()),
            'branch' => $this->branchFromCompletedCourses(
                $user,
                $schoolyearId,
                $completedCodes,
                $student,
                $studyProgram ?? StudentTimetableStudyProgram::Normalstudium,
            ),
            'arts_subject' => $this->selectionOptionFromCompletedCodes($completedCodes, $this->artsSelectionCourseAliases()),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $recognizedCourses
     * @return list<array<string, mixed>>
     */
    private function recognizedCoursesWithResolvedGenericReligion(array $recognizedCourses, Import116 $student): array
    {
        $religion = $this->studentReligionOptionValue((string) $student->religion);

        if (! $religion) {
            return $recognizedCourses;
        }

        return collect($recognizedCourses)
            ->map(function (array $course) use ($religion): array {
                $parts = $this->courseCodeModuleParts((string) ($course['code'] ?? ''));

                if (($parts['base'] ?? '') !== 'R') {
                    return $course;
                }

                $resolvedCode = $religion.($parts['module'] ?? '');
                $moduleNumber = (string) ($parts['module'] ?? '');

                return [
                    ...$course,
                    'code' => $resolvedCode,
                    'subject' => $resolvedCode,
                    'planning_codes' => [
                        $resolvedCode,
                        "R{$moduleNumber}",
                        "ETH{$moduleNumber}",
                    ],
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $completedCourses
     */
    private function completedReligionSelection(array $completedCourses): ?string
    {
        $completedCodes = collect($completedCourses)
            ->pluck('code')
            ->map(fn (mixed $code): string => $this->normalizedCourseCode((string) $code));

        $ethicsSelection = $this->selectionOptionFromCompletedCodes($completedCodes, [
            'ETH' => ['ETH', 'ET'],
        ]);

        if ($ethicsSelection !== null) {
            return $ethicsSelection;
        }

        return $this->selectionOptionFromCompletedCodes($completedCodes, [
            'Rev' => ['REV'],
            'Ris' => ['RIS'],
            'Rk' => ['RK'],
            'Ror' => ['ROR'],
        ]);
    }

    public function semesterForStudent(
        ?Import116 $student,
        ?StudentTimetableStudyProgram $studyProgram = null,
    ): ?int {
        $schoolLevel = $this->studentSchoolLevelKey($student);
        $usesCompactProgression = $studyProgram
            ? $studyProgram === StudentTimetableStudyProgram::Kompaktstudium
            : $this->overviewService->isKompaktunterrichtClass((string) $student?->class);

        return $this->semesterForSchoolLevel($schoolLevel, $usesCompactProgression);
    }

    /** @return list<array{value: string, semester: int}> */
    public function schoolLevelOptionsForStudyProgram(StudentTimetableStudyProgram $studyProgram): array
    {
        $semesterBySchoolLevel = $studyProgram === StudentTimetableStudyProgram::Kompaktstudium
            ? self::COMPACT_SEMESTER_BY_SCHOOL_LEVEL
            : self::NORMAL_SEMESTER_BY_SCHOOL_LEVEL;

        return collect($semesterBySchoolLevel)
            ->map(fn (int $semester, string $schoolLevel): array => [
                'value' => $schoolLevel,
                'semester' => $semester,
            ])
            ->values()
            ->all();
    }

    private function schoolLevelMismatchForStudent(
        ?Import116 $student,
        StudentTimetableStudyProgram $studyProgram,
    ): bool {
        if (! $student || trim((string) $student->school_level) === '') {
            return false;
        }

        $schoolLevel = $this->normalizedStudentSchoolLevel($student->school_level, $student->attendance_year);

        return $schoolLevel === ''
            || $this->semesterForSchoolLevel(
                $schoolLevel,
                $studyProgram === StudentTimetableStudyProgram::Kompaktstudium,
            ) === null;
    }

    private function semesterForSchoolLevel(string $schoolLevel, bool $usesCompactProgression): ?int
    {
        $semesterBySchoolLevel = $usesCompactProgression
            ? self::COMPACT_SEMESTER_BY_SCHOOL_LEVEL
            : self::NORMAL_SEMESTER_BY_SCHOOL_LEVEL;

        return $semesterBySchoolLevel[$schoolLevel] ?? null;
    }

    public function instructionTypeForStudent(?Import116 $student): string
    {
        return $this->overviewService->isKompaktunterrichtClass((string) $student?->class)
            ? 'Kompaktunterricht'
            : 'Normalunterricht';
    }

    private function studentBranch(?Import116 $student): ?string
    {
        $schoolLevel = mb_strtolower(trim((string) $student?->school_level), 'UTF-8');

        if (str_contains($schoolLevel, 'gymnasial')) {
            return 'gymnasial';
        }

        if (str_contains($schoolLevel, 'wirtschaft')) {
            return self::DEFAULT_BRANCH;
        }

        return null;
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

    private function importedSchoolLevelForStudent(Import116 $student): string
    {
        return $this->displaySchoolLevel($student->school_level, $student->attendance_year);
    }

    private function originalSchoolLevelForStudent(Import116 $student): string
    {
        if ($student->original_school_level === null) {
            return '';
        }

        return $this->displaySchoolLevel(
            $student->original_school_level,
            $student->original_attendance_year,
        );
    }

    public function displaySchoolLevel(?string $schoolLevel, ?string $attendanceYear = null): string
    {
        $normalizedSchoolLevel = $this->normalizedStudentSchoolLevel(
            $schoolLevel,
            $attendanceYear,
        );

        return $normalizedSchoolLevel !== ''
            ? $normalizedSchoolLevel
            : trim((string) $schoolLevel);
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

    /**
     * @param  Collection<int, string>  $completedCodes
     * @param  array<string, list<string>>  $optionAliases
     */
    private function selectionOptionFromCompletedCodes(Collection $completedCodes, array $optionAliases): ?string
    {
        $bestMatch = null;
        $optionIndex = 0;

        foreach ($optionAliases as $option => $aliases) {
            $match = $this->bestCompletedCourseMatch($completedCodes, $aliases, $optionIndex, (string) $option);
            $optionIndex++;

            if (! $match) {
                continue;
            }

            if (! $bestMatch || $this->courseMatchSortValue($match) > $this->courseMatchSortValue($bestMatch)) {
                $bestMatch = $match;
            }
        }

        return $bestMatch['option'] ?? null;
    }

    /**
     * @return array<string, list<string>>
     */
    private function religionSelectionCourseAliases(): array
    {
        return [
            'ETH' => ['ETH', 'ET'],
            'Rev' => ['REV'],
            'Ris' => ['RIS'],
            'Rk' => ['RK'],
            'Ror' => ['ROR'],
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    private function languageSelectionCourseAliases(): array
    {
        return [
            'L' => ['L', 'LET', 'LPT'],
            'F' => ['F'],
            'S' => ['S', 'SPA'],
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    private function artsSelectionCourseAliases(): array
    {
        return [
            'ME' => ['ME', 'MU'],
            'BE' => ['BE'],
        ];
    }

    /**
     * @param  Collection<int, string>  $completedCodes
     * @param  list<string>  $aliases
     * @return array{option: string, module: int, semester: int, course_index: int, option_index: int}|null
     */
    private function bestCompletedCourseMatch(Collection $completedCodes, array $aliases, int $optionIndex, string $option): ?array
    {
        $optionAliases = collect([$option, ...$aliases])
            ->map(fn (?string $alias): string => $this->courseCodeWithoutModule((string) $alias))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($optionAliases === []) {
            return null;
        }

        return $completedCodes
            ->values()
            ->map(fn (string $code, int $courseIndex): array => [
                ...$this->courseCodeModuleParts($code),
                'course_index' => $courseIndex,
            ])
            ->filter(fn (array $parts): bool => in_array((string) ($parts['base'] ?? ''), $optionAliases, true))
            ->map(fn (array $parts): array => [
                'option' => $option,
                'module' => $this->integerOrNull($parts['module'] ?? null) ?? 0,
                'semester' => 0,
                'course_index' => (int) $parts['course_index'],
                'option_index' => $optionIndex,
            ])
            ->sortByDesc(fn (array $match): int => $this->courseMatchSortValue($match))
            ->first();
    }

    /**
     * @param  Collection<int, string>  $completedCodes
     */
    private function branchFromCompletedCourses(
        User $user,
        int $schoolyearId,
        Collection $completedCodes,
        ?Import116 $student,
        StudentTimetableStudyProgram $studyProgram = StudentTimetableStudyProgram::Normalstudium,
    ): ?string {
        $branchOptions = ['wirtschaftskundlich', 'gymnasial'];
        $bestMatch = null;

        StudentTimetableSubjectRow::query()
            ->forStudyProgram($studyProgram)
            ->where('school_id', $user->school_id)
            ->where('schoolyear_id', $schoolyearId)
            ->where('is_active', true)
            ->whereIn('branch', $branchOptions)
            ->orderBy('semester')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->each(function (StudentTimetableSubjectRow $row, int $subjectIndex) use ($completedCodes, &$bestMatch): void {
                $match = $this->bestSubjectRowCompletedCourseMatch($row, $completedCodes, $subjectIndex);

                if (! $match) {
                    return;
                }

                if (! $bestMatch || $this->courseMatchSortValue($match) > $this->courseMatchSortValue($bestMatch)) {
                    $bestMatch = $match;
                }
            });

        return $bestMatch['branch'] ?? $this->studentBranch($student);
    }

    /**
     * @param  Collection<int, string>  $completedCodes
     * @return array{branch: string, module: int, semester: int, course_index: int, option_index: int}|null
     */
    private function bestSubjectRowCompletedCourseMatch(StudentTimetableSubjectRow $row, Collection $completedCodes, int $subjectIndex): ?array
    {
        $subjectParts = collect($this->subjectRowCourseCodesForBranchInference($row))
            ->flatMap(fn (string $code): array => $this->courseCodeAliasParts($code))
            ->map(fn (string $code): array => $this->courseCodeModuleParts($code))
            ->filter(fn (array $parts): bool => (string) ($parts['base'] ?? '') !== '')
            ->unique(fn (array $parts): string => ($parts['base'] ?? '').'|'.($parts['module'] ?? ''))
            ->values();

        if ($subjectParts->isEmpty()) {
            return null;
        }

        return $completedCodes
            ->values()
            ->flatMap(function (string $code, int $courseIndex) use ($row, $subjectParts, $subjectIndex): array {
                $completedParts = $this->courseCodeModuleParts($code);

                return $subjectParts
                    ->filter(fn (array $subjectPart): bool => $this->subjectCoursePartMatchesCompletedCoursePart($subjectPart, $completedParts))
                    ->map(fn (): array => [
                        'branch' => (string) $row->branch,
                        'module' => $this->integerOrNull($completedParts['module'] ?? null) ?? 0,
                        'semester' => (int) $row->semester,
                        'course_index' => $courseIndex,
                        'option_index' => $subjectIndex,
                    ])
                    ->all();
            })
            ->sortByDesc(fn (array $match): int => $this->courseMatchSortValue($match))
            ->first();
    }

    /**
     * @return list<string>
     */
    private function subjectRowCourseCodesForBranchInference(StudentTimetableSubjectRow $row): array
    {
        return $this->courseCodeAliasParts((string) ($row->json_code ?: $row->json_subject ?: $row->name ?: ''));
    }

    private function subjectCoursePartMatchesCompletedCoursePart(array $subjectPart, array $completedPart): bool
    {
        $completedBase = (string) ($completedPart['base'] ?? '');

        if (! in_array($completedBase, $this->studentCourseBaseAliases((string) ($subjectPart['base'] ?? '')), true)) {
            return false;
        }

        $subjectModule = (string) ($subjectPart['module'] ?? '');
        $completedModule = (string) ($completedPart['module'] ?? '');

        return $subjectModule === '' || $completedModule === '' || $subjectModule === $completedModule;
    }

    /**
     * @param  array<string, int|string>  $match
     */
    private function courseMatchSortValue(array $match): int
    {
        return ((int) ($match['module'] ?? 0) * 1_000_000_000)
            + ((int) ($match['semester'] ?? 0) * 1_000_000)
            + ((int) ($match['course_index'] ?? 0) * 1_000)
            - (int) ($match['option_index'] ?? 0);
    }

    /**
     * @param  array<string, mixed>  $selection
     * @return list<array<string, mixed>>
     */
    private function subjectCourses(
        User $user,
        int $schoolyearId,
        array $selection,
        StudentTimetableStudyProgram $studyProgram = StudentTimetableStudyProgram::Normalstudium,
        bool $applySelectionEligibility = true,
    ): Collection {
        return StudentTimetableSubjectRow::query()
            ->forStudyProgram($studyProgram)
            ->where('school_id', $user->school_id)
            ->where('schoolyear_id', $schoolyearId)
            ->where('is_active', true)
            ->orderBy('semester')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->when(
                $applySelectionEligibility,
                fn (Collection $rows): Collection => $rows
                    ->filter(fn (StudentTimetableSubjectRow $row): bool => $this->subjectMatchesSelection($row, $selection)),
            )
            ->flatMap(fn (StudentTimetableSubjectRow $row): array => $this->subjectRowCoursePayloads(
                $row,
                $selection,
                $applySelectionEligibility,
            ));
    }

    /**
     * @param  array<string, mixed>  $selection
     */
    private function subjectMatchesSelection(StudentTimetableSubjectRow $row, array $selection): bool
    {
        if (! $this->subjectMatchesSelectedBranch($row, $selection)) {
            return false;
        }

        if ($this->isReligionSubject($row)) {
            $selectedReligion = $this->nonEmptyString($selection['religion'] ?? null);

            if ($selectedReligion === null) {
                return false;
            }

            $subjectBase = $this->normalizedCourseCode($this->subjectBaseKey($row));

            if ($subjectBase === 'R/ET') {
                return true;
            }

            return $selectedReligion === 'ETH'
                ? in_array($subjectBase, ['ET', 'ETH'], true)
                : $subjectBase === 'R';
        }

        if ($this->isArtsSubject($row)) {
            $selectedArtsSubject = $this->nonEmptyString($selection['arts_subject'] ?? null);

            return $selectedArtsSubject !== null && $this->subjectBaseKey($row) === $selectedArtsSubject;
        }

        if ($this->isLanguageSubject($row)) {
            $language = $this->languageSubjectCode($row);
            $selectedLanguage = $this->nonEmptyString($selection['language'] ?? null);

            if ($selectedLanguage === null) {
                return false;
            }

            return $language === '' || $language === $selectedLanguage;
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $selection
     */
    private function subjectMatchesSelectedBranch(StudentTimetableSubjectRow $row, array $selection): bool
    {
        $branch = trim((string) $row->branch);
        $selectedBranch = trim((string) ($selection['branch'] ?? ''));

        if ($this->isArtsSubject($row)) {
            return true;
        }

        return $branch === '' || $branch === 'common' || $branch === $selectedBranch;
    }

    /**
     * @param  array<string, mixed>  $selection
     * @return list<array<string, mixed>>
     */
    private function subjectRowCoursePayloads(
        StudentTimetableSubjectRow $row,
        array $selection,
        bool $applySelectionEligibility = true,
    ): array {
        $codes = $applySelectionEligibility
            ? $this->selectedSubjectCodes($row, $selection)
            : $this->allSelectableSubjectCodes($row);
        $intendedCourseCodes = $this->subjectMatchesSelection($row, $selection)
            ? $this->selectedSubjectCodes($row, $selection)
            : [];

        return collect($codes)
            ->map(function (string $courseCode) use ($intendedCourseCodes, $row): array {
                $branch = $row->branch ?: 'common';
                $hours = $row->hours_per_week !== null ? (float) $row->hours_per_week : null;

                return [
                    'code' => $courseCode,
                    'name' => $row->name ?: $row->json_subject ?: $courseCode,
                    'semester' => $row->semester,
                    'branch' => $branch,
                    'branch_label' => $this->courseBranchLabel($branch),
                    'hours_per_week' => $hours,
                    'hours' => $hours,
                    'hours_value' => $this->courseHoursValue($hours),
                    'hours_label' => $this->courseHoursLabel($hours),
                    'is_intended_for_selection' => in_array($courseCode, $intendedCourseCodes, true),
                    'key' => implode('|', [
                        $row->id,
                        $row->semester,
                        $branch,
                        $row->json_code,
                        $row->json_subject,
                        $row->name,
                        $courseCode,
                    ]),
                ];
            })
            ->filter(fn (array $course): bool => trim((string) $course['code']) !== '')
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $selection
     * @return list<string>
     */
    private function selectedSubjectCodes(StudentTimetableSubjectRow $row, array $selection): array
    {
        $code = $this->selectedSubjectCode($row, $selection);

        return $this->isReligionSubject($row) || $this->isLanguageSubject($row)
            ? [$code]
            : $this->courseCodeAliasParts($code);
    }

    /** @return list<string> */
    private function allSelectableSubjectCodes(StudentTimetableSubjectRow $row): array
    {
        $moduleNumber = $this->subjectModuleNumber($row);
        $subjectBase = $this->normalizedCourseCode($this->subjectBaseKey($row));

        if ($this->isReligionSubject($row)) {
            $religionCodes = match ($subjectBase) {
                'R/ET' => collect($this->religionOptions())->pluck('value'),
                'ET', 'ETH' => collect(['ETH']),
                default => collect($this->religionOptions())
                    ->pluck('value')
                    ->reject(fn (string $religion): bool => $religion === 'ETH'),
            };

            return $religionCodes
                ->map(fn (string $religion): string => $religion.$moduleNumber)
                ->values()
                ->all();
        }

        if ($this->isLanguageSubject($row)) {
            $languageCodes = $subjectBase === 'L/F/S'
                ? collect($this->languageOptions())->pluck('value')
                : collect([$subjectBase]);

            return $languageCodes
                ->map(fn (string $language): string => $language.$moduleNumber)
                ->values()
                ->all();
        }

        return $this->courseCodeAliasParts((string) ($row->json_code ?: $row->json_subject ?: $row->name ?: ''));
    }

    private function courseBranchLabel(?string $branch): string
    {
        $branch = trim((string) $branch);

        return $branch === '' || $branch === 'common' ? 'alle' : $branch;
    }

    /**
     * @param  list<array<string, mixed>>  $courses
     */
    private function courseHoursTotal(array $courses): float
    {
        return (float) collect($courses)
            ->sum(fn (array $course): float => is_numeric($course['hours'] ?? null) ? (float) $course['hours'] : 0.0);
    }

    private function courseHoursValue(mixed $hours): ?string
    {
        if (! is_numeric($hours)) {
            return null;
        }

        $numericHours = (float) $hours;

        if ($numericHours <= 0) {
            return null;
        }

        return rtrim(rtrim(number_format($numericHours, 2, ',', ''), '0'), ',');
    }

    private function courseHoursLabel(mixed $hours): ?string
    {
        $hoursValue = $this->courseHoursValue($hours);

        return $hoursValue ? "{$hoursValue} Std." : null;
    }

    /**
     * @param  array<string, mixed>  $selection
     */
    private function selectedSubjectCode(StudentTimetableSubjectRow $row, array $selection): string
    {
        if ($this->isReligionSubject($row)) {
            $religion = $this->nonEmptyString($selection['religion'] ?? null);

            return $religion ? $religion.$this->subjectModuleNumber($row) : '';
        }

        if ($this->isLanguageSubject($row)) {
            $language = $this->nonEmptyString($selection['language'] ?? null);

            return $language ? $language.$this->subjectModuleNumber($row) : '';
        }

        return (string) ($row->json_code ?: $row->json_subject ?: $row->name ?: '');
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $subjectCourses
     * @param  array<string, mixed>  $selection
     * @param  list<string>  $completedCourseCodes
     * @param  list<string>  $visitedCourseCodes
     * @param  list<array<string, mixed>>  $missingCourses
     * @return list<array<string, mixed>>
     */
    private function plannedCoursesForProgression(Collection $subjectCourses, array $completedCourseCodes, array $visitedCourseCodes, array $missingCourses): array
    {
        $missingCourseCodes = $this->studentPlannedCourseCodes($missingCourses);
        $candidates = $subjectCourses
            ->reject(fn (array $course): bool => $this->courseCompletedForStudentPlanning($course, $completedCourseCodes))
            ->reject(fn (array $course): bool => $this->courseCompletedForStudentPlanning($course, $missingCourseCodes))
            ->reject(fn (array $course): bool => $this->isArtsSelectionCourse($course))
            ->unique(fn (array $course): string => $this->studentPlanningCourseUniqueKey($course));
        $firstAvailableCourseKeys = $candidates
            ->groupBy(fn (array $course): string => $this->studentProgressionCourseGroupKey($course))
            ->map(fn (Collection $courses): ?array => $this->firstStudentProgressionCourse($courses))
            ->filter()
            ->map(fn (array $course): string => $this->studentPlanningCourseUniqueKey($course))
            ->values()
            ->all();

        return $candidates
            ->filter(fn (array $course): bool => in_array($this->studentPlanningCourseUniqueKey($course), $firstAvailableCourseKeys, true)
                || $this->coursePossibleAsStudentMissing($course, $completedCourseCodes, $visitedCourseCodes))
            ->groupBy(fn (array $course): string => $this->studentProgressionCourseGroupKey($course))
            ->map(fn (Collection $courses): ?array => $this->firstStudentProgressionCourse($courses))
            ->filter()
            ->sort(fn (array $firstCourse, array $secondCourse): int => $this->studentProgressionCourseSort($firstCourse, $secondCourse))
            ->values()
            ->all();
    }

    /**
     * @param  array<string,mixed>  $course
     */
    private function isArtsSelectionCourse(array $course): bool
    {
        return collect($this->courseCodeAliases($course))
            ->map(fn (string $courseCode): string => (string) ($this->courseCodeModuleParts($courseCode)['base'] ?? ''))
            ->contains(fn (string $base): bool => in_array($base, ['BE', 'ME', 'MU'], true));
    }

    /**
     * @param  list<array<string, mixed>>  $recognizedCourses
     * @return list<array<string, mixed>>
     */
    private function positiveCourses(array $recognizedCourses): array
    {
        return collect($recognizedCourses)
            ->filter(fn (array $course): bool => $this->completedCourseCountsAsDone((string) ($course['grade'] ?? '')))
            ->values()
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $recognizedCourses
     * @return list<array<string, mixed>>
     */
    private function negativeCourses(array $recognizedCourses): array
    {
        return collect($recognizedCourses)
            ->filter(fn (array $course): bool => $this->courseCountsAsNegative((string) ($course['grade'] ?? '')))
            ->sort(fn (array $firstCourse, array $secondCourse): int => strnatcasecmp((string) $firstCourse['code'], (string) $secondCourse['code']))
            ->values()
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $completedCourses
     * @param  list<array<string, mixed>>  $missingCourses
     * @return array{
     *     exempt: list<array<string, mixed>>,
     *     passed: list<array<string, mixed>>,
     *     failed: list<array<string, mixed>>
     * }
     */
    private function studyModules(array $completedCourses, array $missingCourses): array
    {
        $completedModules = collect($completedCourses);
        $passedModules = $completedModules
            ->reject(fn (array $course): bool => mb_strtoupper(trim((string) ($course['grade'] ?? '')), 'UTF-8') === 'B')
            ->values();
        $passedModuleCodes = $passedModules
            ->map(fn (array $course): string => $this->normalizedCourseCode((string) ($course['code'] ?? '')))
            ->filter()
            ->unique()
            ->values();
        $failedModulesByCode = collect($missingCourses)
            ->groupBy(fn (array $course): string => $this->normalizedCourseCode((string) ($course['code'] ?? '')));

        return [
            'exempt' => $completedModules
                ->filter(fn (array $course): bool => mb_strtoupper(trim((string) ($course['grade'] ?? '')), 'UTF-8') === 'B')
                ->values()
                ->all(),
            'passed' => $passedModules
                ->map(function (array $course) use ($failedModulesByCode): array {
                    $moduleCode = $this->normalizedCourseCode((string) ($course['code'] ?? ''));
                    $previousFailedGrades = collect($failedModulesByCode->get($moduleCode, []))
                        ->flatMap(function (array $failedCourse): array {
                            $grade = mb_strtoupper(trim((string) ($failedCourse['grade'] ?? '')), 'UTF-8');
                            $attemptCount = max(1, (int) ($failedCourse['attempt_count'] ?? 1));

                            return array_fill(0, $attemptCount, $grade);
                        })
                        ->filter()
                        ->values()
                        ->all();

                    return [
                        ...$course,
                        'previous_failed_grades' => $previousFailedGrades,
                    ];
                })
                ->all(),
            'failed' => collect($missingCourses)
                ->reject(fn (array $course): bool => $passedModuleCodes->contains(
                    $this->normalizedCourseCode((string) ($course['code'] ?? '')),
                ))
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $selection
     * @param  list<array<string, mixed>>  $completedCourses
     * @param  list<array<string, mixed>>  $missingCourses
     * @return list<array<string, mixed>>
     */
    private function moduleSelectionGroups(
        User $user,
        int $schoolyearId,
        array $selection,
        array $completedCourses,
        array $missingCourses,
        StudentTimetableStudyProgram $studyProgram = StudentTimetableStudyProgram::Normalstudium,
        bool $limitToStudentProgression = false,
        bool $applySelectionEligibility = true,
    ): array {
        $studyModules = $this->studyModules($completedCourses, $missingCourses);
        $subjectCourses = $this->subjectCourses(
            $user,
            $schoolyearId,
            $selection,
            $studyProgram,
            $applySelectionEligibility,
        );
        $regularSubjectCourses = $studyProgram === StudentTimetableStudyProgram::Normalstudium
            ? $subjectCourses
            : $this->subjectCourses(
                $user,
                $schoolyearId,
                $selection,
                StudentTimetableStudyProgram::Normalstudium,
                $applySelectionEligibility,
            );
        $historicalSubjectCourses = $studyProgram === StudentTimetableStudyProgram::Normalstudium
            ? $subjectCourses
            : $subjectCourses->concat($regularSubjectCourses);
        $finishedModules = collect([
            ...$studyModules['exempt'],
            ...$studyModules['passed'],
        ])
            ->unique(fn (array $course): string => $this->normalizedCourseCode((string) ($course['code'] ?? '')))
            ->map(fn (array $course): array => $this->moduleWithSubjectPlanHours($course, $historicalSubjectCourses))
            ->values();
        $negativeModules = collect($studyModules['failed'])
            ->map(fn (array $course): array => $this->moduleWithSubjectPlanHours($course, $historicalSubjectCourses));
        $courseGroups = $this->manualTimetableCourseGroupsForUser($user);
        $unavailableCourseCodes = $this->studentPlannedCourseCodes([
            ...$completedCourses,
            ...$missingCourses,
        ]);

        if ($limitToStudentProgression) {
            $completedCourseCodes = $this->studentCompletedCourseCodes($completedCourses);
            $visitedCourseCodes = $this->studentVisitedCourseCodes([
                ...$completedCourses,
                ...$missingCourses,
            ]);
            $plannedModules = $this->plannedCoursesForProgression(
                $subjectCourses,
                $completedCourseCodes,
                $visitedCourseCodes,
                $missingCourses,
            );
            $progressionAdditionalModules = $this->additionalCoursesForProgression(
                $subjectCourses,
                $completedCourseCodes,
                $visitedCourseCodes,
                $missingCourses,
                $plannedModules,
            );
            $subjectCourses = collect([
                ...$plannedModules,
                ...$progressionAdditionalModules,
            ]);
        }

        $availableModules = $subjectCourses
            ->reject(fn (array $course): bool => $this->courseCompletedForStudentPlanning($course, $unavailableCourseCodes))
            ->unique(fn (array $course): string => $this->studentPlanningCourseUniqueKey($course))
            ->sort(fn (array $firstCourse, array $secondCourse): int => $this->studentProgressionCourseSort($firstCourse, $secondCourse))
            ->values();
        $currentSemester = $this->integerOrNull($selection['semester'] ?? null);
        $previousModules = $currentSemester === null
            ? collect()
            : $availableModules->filter(fn (array $course): bool => (int) ($course['semester'] ?? 0) < $currentSemester);
        $currentModules = $currentSemester === null
            ? collect()
            : $availableModules->filter(fn (array $course): bool => (int) ($course['semester'] ?? 0) === $currentSemester);
        $additionalModules = $currentSemester === null
            ? $availableModules
            : $availableModules->filter(fn (array $course): bool => (int) ($course['semester'] ?? 0) > $currentSemester);

        return [
            $this->moduleSelectionGroup('finished', 'Abgeschlossene', $finishedModules, false, $regularSubjectCourses, courseGroups: $courseGroups),
            $this->moduleSelectionGroup('negative', 'Negative', $negativeModules, false, $regularSubjectCourses, $currentSemester, $courseGroups),
            $this->moduleSelectionGroup('previous', 'Frühere', $previousModules, false, $regularSubjectCourses, courseGroups: $courseGroups),
            $this->moduleSelectionGroup('current', 'Aktuelle', $currentModules, true, $regularSubjectCourses, courseGroups: $courseGroups),
            $this->moduleSelectionGroup('additional', 'Zusätzliche', $additionalModules, false, $regularSubjectCourses, courseGroups: $courseGroups),
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $modules
     * @return array<string, mixed>
     */
    private function moduleSelectionGroup(
        string $key,
        string $label,
        Collection $modules,
        bool $selectedByDefault,
        Collection $regularSubjectCourses,
        ?int $selectedSemester = null,
        array $courseGroups = [],
    ): array {
        return [
            'key' => $key,
            'label' => $label,
            'modules' => $modules
                ->map(function (array $module) use ($key, $selectedByDefault, $selectedSemester, $courseGroups, $regularSubjectCourses): array {
                    $semester = $this->integerOrNull($module['semester'] ?? null);
                    $module = $this->moduleWithRegularSubjectPlanHours($module, $regularSubjectCourses);

                    return [
                        ...$this->courseWithManualTimetableGroups($module, $courseGroups),
                        'selection_key' => $key.':'.$this->normalizedCourseCode((string) ($module['code'] ?? '')),
                        'selected_by_default' => $selectedByDefault
                            || ($key === 'negative' && $semester !== null && $semester === $selectedSemester),
                    ];
                })
                ->filter(fn (array $module): bool => trim((string) ($module['code'] ?? '')) !== '')
                ->sort(fn (array $firstModule, array $secondModule): int => strnatcasecmp(
                    (string) ($firstModule['code'] ?? ''),
                    (string) ($secondModule['code'] ?? ''),
                ))
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $regularSubjectCourses
     */
    private function moduleWithRegularSubjectPlanHours(array $module, Collection $regularSubjectCourses): array
    {
        $moduleCourseCodes = $this->studentPlannedCourseCodes([$module]);
        $regularSubjectCourse = $regularSubjectCourses->first(
            fn (array $course): bool => $this->courseCompletedForStudentPlanning($course, $moduleCourseCodes),
        );

        if (! is_array($regularSubjectCourse)) {
            return $module;
        }

        return [
            ...$module,
            'regular_hours' => $regularSubjectCourse['hours']
                ?? $regularSubjectCourse['hours_per_week']
                ?? $module['regular_hours']
                ?? null,
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $subjectCourses
     */
    private function moduleWithSubjectPlanHours(array $module, Collection $subjectCourses): array
    {
        $moduleCourseCodes = $this->studentPlannedCourseCodes([$module]);
        $subjectCourse = $subjectCourses->first(
            fn (array $course): bool => $this->courseCompletedForStudentPlanning($course, $moduleCourseCodes),
        );

        if (! is_array($subjectCourse)) {
            return $module;
        }

        return [
            ...$module,
            'hours_per_week' => $subjectCourse['hours_per_week'] ?? $module['hours_per_week'] ?? null,
            'hours' => $subjectCourse['hours'] ?? $module['hours'] ?? null,
            'hours_value' => $subjectCourse['hours_value'] ?? $module['hours_value'] ?? null,
            'hours_label' => $subjectCourse['hours_label'] ?? $module['hours_label'] ?? null,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $missingCourses
     * @param  list<array<string, mixed>>  $plannedCourses
     * @return list<array<string, mixed>>
     */
    private function additionalCoursesForProgression(Collection $subjectCourses, array $completedCourseCodes, array $visitedCourseCodes, array $missingCourses, array $plannedCourses): array
    {
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
            ->reject(fn (array $course): bool => $this->courseCompletedForStudentPlanning($course, $unavailableCourseCodes))
            ->reject(fn (array $course): bool => $this->courseCompletedForStudentPlanning($course, $regularCourseCodes))
            ->filter(fn (array $course): bool => $this->coursePossibleAsStudentAdditional($course, $completedCourseCodes, $visitedCourseCodes, $plannedCourseCodes))
            ->unique(fn (array $course): string => $this->studentPlanningCourseUniqueKey($course))
            ->groupBy(fn (array $course): string => $this->studentProgressionCourseGroupKey($course))
            ->map(fn (Collection $courses): ?array => $this->firstStudentProgressionCourse($courses))
            ->filter()
            ->sort(fn (array $firstCourse, array $secondCourse): int => $this->studentProgressionCourseSort($firstCourse, $secondCourse))
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $courses
     * @return array<string, mixed>|null
     */
    private function firstStudentProgressionCourse(Collection $courses): ?array
    {
        return $courses
            ->sort(fn (array $firstCourse, array $secondCourse): int => $this->studentProgressionCourseSort($firstCourse, $secondCourse))
            ->first();
    }

    /**
     * @param  array<string, mixed>  $course
     */
    private function studentProgressionCourseGroupKey(array $course): string
    {
        $baseAliases = collect($this->courseModulePartsForStudentPlanning($course))
            ->flatMap(fn (array $parts): array => $this->studentCourseBaseAliases((string) ($parts['base'] ?? '')))
            ->filter()
            ->sort()
            ->unique()
            ->values()
            ->implode('|');

        return $baseAliases !== '' ? $baseAliases : $this->studentPlanningCourseUniqueKey($course);
    }

    /**
     * @param  array<string, mixed>  $firstCourse
     * @param  array<string, mixed>  $secondCourse
     */
    private function studentProgressionCourseSort(array $firstCourse, array $secondCourse): int
    {
        $firstModule = $this->studentProgressionCourseModuleNumber($firstCourse);
        $secondModule = $this->studentProgressionCourseModuleNumber($secondCourse);
        $moduleComparison = $firstModule <=> $secondModule;

        if ($moduleComparison !== 0) {
            return $moduleComparison;
        }

        $semesterComparison = (int) ($firstCourse['semester'] ?? 0) <=> (int) ($secondCourse['semester'] ?? 0);

        return $semesterComparison !== 0
            ? $semesterComparison
            : strnatcasecmp((string) $firstCourse['code'], (string) $secondCourse['code']);
    }

    /**
     * @param  array<string, mixed>  $course
     */
    private function studentProgressionCourseModuleNumber(array $course): int
    {
        return collect($this->courseModulePartsForStudentPlanning($course))
            ->map(fn (array $parts): ?int => $this->integerOrNull($parts['module'] ?? null))
            ->filter(fn (?int $module): bool => $module !== null)
            ->min() ?? PHP_INT_MAX;
    }

    /**
     * @param  array<string, mixed>  $course
     */
    private function studentPlanningCourseUniqueKey(array $course): string
    {
        $code = $this->normalizedCourseCode((string) ($course['code'] ?? ''));

        return $code !== '' ? $code : (string) ($course['key'] ?? '');
    }

    /**
     * @param  list<array<string, mixed>>  $completedCourses
     * @return list<string>
     */
    private function studentCompletedCourseCodes(array $completedCourses): array
    {
        return collect($completedCourses)
            ->filter(fn (array $course): bool => $this->completedCourseCountsAsDone((string) ($course['grade'] ?? '')))
            ->flatMap(fn (array $course): array => $this->studentPlanningCodes($course))
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
            ->flatMap(fn (array $course): array => $this->studentPlanningCodes($course))
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

    private function courseCountsAsNegative(string $grade): bool
    {
        return in_array(mb_strtoupper(trim($grade), 'UTF-8'), ['5', 'N'], true);
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
            ->flatMap(fn (array $course): array => $this->studentPlanningCodes($course))
            ->map(fn (string $courseCode): string => $this->normalizedCourseCode($courseCode))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $course
     * @return list<string>
     */
    private function studentPlanningCodes(array $course): array
    {
        return collect([
            ...$this->courseCodeAliases($course),
            ...(is_array($course['planning_codes'] ?? null) ? $course['planning_codes'] : []),
        ])
            ->map(fn (mixed $courseCode): string => trim((string) $courseCode))
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
        $subjectBase = $this->normalizedCourseCode($this->subjectBaseKey($row));

        if ($subjectBase === 'R/ET') {
            return true;
        }

        return $row->study_program === StudentTimetableStudyProgram::Kompaktstudium
            && in_array($subjectBase, ['R', 'ET', 'ETH'], true);
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
