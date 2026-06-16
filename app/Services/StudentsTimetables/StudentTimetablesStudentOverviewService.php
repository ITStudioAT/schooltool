<?php

namespace App\Services\StudentsTimetables;

use App\Models\Import116;
use App\Models\SchoolTool;
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
     * @param  array<string, mixed>  $selectionOverride
     * @return array<string, mixed>
     */
    public function summaryForStudentCode(User $user, string $studentCode, array $selectionOverride = []): array
    {
        $schoolyearId = $this->schoolyearIdForUser($user);
        $student = Import116::query()
            ->where('school_id', $user->school_id)
            ->where('schoolyear_id', $schoolyearId)
            ->where('student_code', $studentCode)
            ->first();

        if (! $student) {
            abort(404, 'Student nicht gefunden.');
        }

        return $this->summaryForStudent($user, $schoolyearId, $student, $selectionOverride);
    }

    /**
     * @param  array<string, mixed>  $selectionOverride
     * @return array<string, mixed>
     */
    private function summaryForStudent(User $user, int $schoolyearId, ?Import116 $student, array $selectionOverride = []): array
    {
        $completedCourses = $this->completedCourses($user, $schoolyearId, $student?->student_code);
        $selection = $this->selectionForStudent($user, $schoolyearId, $student, $completedCourses);
        $selection = $this->selectionWithOverrides($selection, $selectionOverride);
        $subjectCourses = $this->subjectCourses($user, $schoolyearId, $selection);
        $completedCourseCodes = $this->studentCompletedCourseCodes($completedCourses);
        $visitedCourseCodes = $this->studentVisitedCourseCodes($completedCourses);
        $proposedCourses = $this->plannedCoursesForSemester($subjectCourses, $selection, $completedCourseCodes);
        $missingCourses = $this->pendingCoursesBeforeSemester($subjectCourses, $selection, $completedCourseCodes, $visitedCourseCodes);
        $additionalCourses = $this->additionalCourses($subjectCourses, $selection, $completedCourseCodes, $visitedCourseCodes, $missingCourses, $proposedCourses);
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
            'selection_options' => $this->selectionOptions(),
            'selection_items' => $this->selectionItems($selection, $student?->religion),
            'completed_courses' => $completedCourses,
            'missing_courses' => $missingCourses,
            'proposed_courses' => $proposedCourses,
            'additional_courses' => $additionalCourses,
            'course_sections' => $courseSections,
            'automatic_course_selection' => $automaticCourseSelection,
            'manual_timetable' => $this->manualTimetableSelection($user, $courseSections),
            'published_timetable' => $this->publishedTimetableForStudent($user, $schoolyearId, $student),
            'school_hours' => $this->schoolHoursForUser($user),
            'counts' => [
                'completed_courses' => count($completedCourses),
                'missing_courses' => count($missingCourses),
                'proposed_courses' => count($proposedCourses),
                'additional_courses' => count($additionalCourses),
            ],
        ];
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
            'timetable' => is_array($publishedTimetable->timetable) ? $publishedTimetable->timetable : [],
            'state' => $state,
            'active_course_group_keys' => $this->publishedTimetableCourseGroupKeys($state),
            'published_at' => optional($publishedTimetable->published_at)->toIso8601String(),
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
    private function selectionWithOverrides(array $selection, array $selectionOverride): array
    {
        if ($selectionOverride === []) {
            return $selection;
        }

        return [
            'semester' => $this->integerOrNull($selectionOverride['semester'] ?? null) ?? $selection['semester'],
            'religion' => $this->nonEmptyString($selectionOverride['religion'] ?? null) ?? $selection['religion'],
            'language' => $this->nonEmptyString($selectionOverride['language'] ?? null) ?? $selection['language'],
            'branch' => $this->nonEmptyString($selectionOverride['branch'] ?? null) ?? $selection['branch'],
            'arts_subject' => $this->nonEmptyString($selectionOverride['arts_subject'] ?? $selectionOverride['artsSubject'] ?? null)
                ?? $selection['arts_subject'],
        ];
    }

    /**
     * @param  array<string, mixed>  $selection
     * @return array<string, string>
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
                $value = $this->nonEmptyString($selection[$key] ?? ($key === 'arts_subject' ? $selection['artsSubject'] ?? null : null));

                if (! $value) {
                    return [];
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
     * @return array<string, string>
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
    private function selectionOptions(): array
    {
        return [
            'religion' => $this->religionOptions(),
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
                'title' => 'Abgeschlossene Kurse',
                'icon' => 'mdi-check-circle-outline',
                'color' => self::COURSE_SECTION_COLOR_MAP['completed'],
                'items' => $completedCourses,
                'empty' => 'Keine abgeschlossenen Kurse gefunden.',
            ],
            [
                'key' => 'missing',
                'title' => 'Fehlende Kurse',
                'icon' => 'mdi-alert-circle-outline',
                'color' => self::COURSE_SECTION_COLOR_MAP['missing'],
                'items' => $missingCourses,
                'empty' => 'Keine fehlenden Kurse erkannt.',
            ],
            [
                'key' => 'proposed',
                'title' => 'Vorgesehene Kurse',
                'icon' => 'mdi-format-list-checks',
                'color' => self::COURSE_SECTION_COLOR_MAP['proposed'],
                'items' => $proposedCourses,
                'empty' => 'Keine vorgesehenen Kurse importiert.',
            ],
            [
                'key' => 'additional',
                'title' => 'Zusätzliche Kurse',
                'icon' => 'mdi-plus-circle-outline',
                'color' => self::COURSE_SECTION_COLOR_MAP['additional'],
                'items' => $additionalCourses,
                'empty' => 'Keine zusätzlichen Kurse erkannt.',
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
            'title' => 'Fehlende Kurse + Vorgesehene Kurse',
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
    private function selectionForStudent(User $user, int $schoolyearId, ?Import116 $student, array $completedCourses): array
    {
        $completedCodes = collect($completedCourses)
            ->pluck('code')
            ->map(fn (mixed $code): string => $this->normalizedCourseCode((string) $code));
        $studentSemester = $this->studentSemester($student);

        return [
            'semester' => $studentSemester ?? $this->currentSemesterFromCompletedCourses($completedCourses),
            'religion' => $this->selectionOptionFromCompletedCodes($completedCodes, $this->religionSelectionCourseAliases()) ?? 'ETH',
            'language' => $this->selectionOptionFromCompletedCodes($completedCodes, $this->languageSelectionCourseAliases()) ?? 'L',
            'branch' => $this->branchFromCompletedCourses($user, $schoolyearId, $completedCodes, $student),
            'arts_subject' => $this->selectionOptionFromCompletedCodes($completedCodes, $this->artsSelectionCourseAliases()) ?? 'ME',
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

    private function studentBranch(?Import116 $student): string
    {
        $schoolLevel = mb_strtolower(trim((string) $student?->school_level), 'UTF-8');

        if (str_contains($schoolLevel, 'gymnasial')) {
            return 'gymnasial';
        }

        if (str_contains($schoolLevel, 'wirtschaft')) {
            return self::DEFAULT_BRANCH;
        }

        return self::DEFAULT_BRANCH;
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
            'Rk' => ['RK', 'R'],
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
    private function branchFromCompletedCourses(User $user, int $schoolyearId, Collection $completedCodes, ?Import116 $student): ?string
    {
        $branchOptions = ['wirtschaftskundlich', 'gymnasial'];
        $bestMatch = null;

        StudentTimetableSubjectRow::query()
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
        if (! $this->subjectMatchesSelectedBranch($row, $selection)) {
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
     */
    private function subjectMatchesSelectedBranch(StudentTimetableSubjectRow $row, array $selection): bool
    {
        $branch = trim((string) $row->branch);
        $selectedBranch = trim((string) ($selection['branch'] ?? ''));

        if ($this->isArtsSubject($row)) {
            return $branch === 'gymnasial' && $selectedBranch === 'gymnasial';
        }

        return $branch === '' || $branch === 'common' || $branch === $selectedBranch;
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
            ->map(function (string $courseCode) use ($row): array {
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
            ->sort(function (array $firstCourse, array $secondCourse): int {
                $semesterComparison = (int) ($firstCourse['semester'] ?? 0) <=> (int) ($secondCourse['semester'] ?? 0);

                return $semesterComparison !== 0
                    ? $semesterComparison
                    : strnatcasecmp((string) $firstCourse['code'], (string) $secondCourse['code']);
            })
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
