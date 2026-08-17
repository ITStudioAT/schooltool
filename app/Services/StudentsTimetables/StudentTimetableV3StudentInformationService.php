<?php

namespace App\Services\StudentsTimetables;

use App\Enums\StudentTimetableStudyProgram;
use App\Models\Import116;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StudentTimetableV3StudentInformationService
{
    private const INFORMATION_KEYS = ['religion', 'language', 'branch', 'arts_subject'];

    private const MODULE_GROUPS = [
        ['key' => 'exempt', 'label' => 'Befreite Module'],
        ['key' => 'passed', 'label' => 'Bestandene Module'],
        ['key' => 'failed', 'label' => 'Nicht bestandene Module'],
    ];

    public function __construct(
        protected StudentTimetablesStudentOverviewService $studentOverviewService,
        protected StudentTimetableCompletedCourseHistoryService $completedCourseHistoryService,
        protected StudentTimetableCompactSubjectPlanService $compactSubjectPlanService,
    ) {}

    /** @return array<string, mixed> */
    public function informationForStudent(
        User $user,
        ?string $studentCode,
        array $selectionOverride = [],
        bool $seedCompactSubjectPlanIfMissing = true,
        bool $includeAllSelectableModules = false,
    ): array {
        $schoolyearId = (int) $user->schoolyear_id;
        $studyInformation = $this->completedCourseHistoryService->studyInformationForStudentCode(
            $user,
            $schoolyearId,
            $studentCode,
        );
        $studyProgram = $studyInformation['study_program'];

        if ($studyProgram === StudentTimetableStudyProgram::Kompaktstudium && $seedCompactSubjectPlanIfMissing) {
            $this->compactSubjectPlanService->seedIfMissing((int) $user->school_id, $schoolyearId);
        }

        $selectionSummary = $this->studentOverviewService->selectionSummaryForStudentCode(
            $user,
            $studentCode,
            $selectionOverride,
            strictSelectionOverride: $selectionOverride !== [],
            studyProgram: $studyProgram,
            includeAllSelectableModules: $includeAllSelectableModules,
        );
        $moduleSelectionGroups = $this->moduleSelectionGroups(
            (array) ($selectionSummary['module_selection_groups'] ?? []),
        );
        $mainModuleSelectionSummary = trim((string) $studentCode) !== '' && ! $includeAllSelectableModules
            ? $this->studentOverviewService->selectionSummaryForStudentCode(
                $user,
                null,
                (array) ($selectionSummary['selection'] ?? []),
                strictSelectionOverride: true,
                studyProgram: $studyProgram,
                includeAllSelectableModules: true,
            )
            : $selectionSummary;
        $mainModuleSelectionGroups = $this->mainModuleSelectionGroups($this->moduleSelectionGroups(
            (array) ($mainModuleSelectionSummary['module_selection_groups'] ?? []),
        ));

        return [
            'student_code' => (string) data_get($selectionSummary, 'student.student_code', ''),
            'study_program' => $studyProgram->value,
            'subject_plan' => $studyInformation['subject_plan'] ?? '',
            'subject_plan_mismatch' => (bool) ($studyInformation['subject_plan_mismatch'] ?? false),
            'religion' => (string) data_get($selectionSummary, 'student.religion', ''),
            'instruction_type' => (string) data_get($selectionSummary, 'student.instruction_type', ''),
            'school_level' => (string) data_get($selectionSummary, 'student.school_level', ''),
            'school_level_mismatch' => (bool) data_get($selectionSummary, 'student.school_level_mismatch', false),
            'original_school_level' => (string) data_get($selectionSummary, 'student.original_school_level', ''),
            'school_level_options' => trim((string) $studentCode) === ''
                ? []
                : $this->studentOverviewService->schoolLevelOptionsForStudyProgram($studyProgram),
            'semester' => data_get($selectionSummary, 'selection.semester'),
            'items' => collect($selectionSummary['selection_items'] ?? [])
                ->filter(fn (array $item): bool => in_array($item['key'] ?? null, self::INFORMATION_KEYS, true))
                ->map(fn (array $item): array => [
                    'key' => (string) ($item['key'] ?? ''),
                    'label' => (string) ($item['label'] ?? ''),
                    'value' => $item['value'] ?? null,
                ])
                ->values()
                ->all(),
            'selection_fields' => $this->selectionFields($selectionSummary),
            'module_groups' => $this->moduleGroups((array) ($selectionSummary['study_modules'] ?? [])),
            'module_selection_groups' => trim((string) $studentCode) === ''
                ? $this->mainModuleSelectionGroups($moduleSelectionGroups)
                : $moduleSelectionGroups,
            'main_module_selection_groups' => $mainModuleSelectionGroups,
        ];
    }

    /** @return array<string, mixed> */
    public function updateSchoolLevelForStudent(
        User $user,
        string $studentCode,
        string $schoolLevel,
        array $selectionOverride = [],
    ): array {
        $schoolyearId = (int) $user->schoolyear_id;
        $student = Import116::query()
            ->where('school_id', $user->school_id)
            ->where('schoolyear_id', $schoolyearId)
            ->where('student_code', $studentCode)
            ->firstOrFail();
        $studyProgram = $this->completedCourseHistoryService
            ->studyProgramForStudentCode($user, $schoolyearId, $studentCode);
        $validSchoolLevels = collect(
            $this->studentOverviewService->schoolLevelOptionsForStudyProgram($studyProgram),
        )->pluck('value');
        $hasStoredOriginalSchoolLevel = $student->original_school_level !== null;
        $originalSchoolLevel = $hasStoredOriginalSchoolLevel
            ? $student->original_school_level
            : $student->school_level;
        $originalAttendanceYear = $hasStoredOriginalSchoolLevel
            ? $student->original_attendance_year
            : $student->attendance_year;
        $originalSchoolLevelDisplay = $this->studentOverviewService->displaySchoolLevel(
            $originalSchoolLevel,
            $originalAttendanceYear,
        );

        if (
            ! $validSchoolLevels->containsStrict($schoolLevel)
            && $schoolLevel !== $originalSchoolLevelDisplay
        ) {
            throw ValidationException::withMessages([
                'school_level' => 'Diese Schulstufe ist für die Studienform des Studierenden nicht gültig.',
            ]);
        }

        $restoresOriginalSchoolLevel = $schoolLevel === $originalSchoolLevelDisplay;
        $student->forceFill([
            ...(! $hasStoredOriginalSchoolLevel ? [
                'original_school_level' => $student->school_level,
                'original_attendance_year' => $student->attendance_year,
            ] : []),
            'school_level' => $restoresOriginalSchoolLevel ? $originalSchoolLevel : $schoolLevel,
            'attendance_year' => $restoresOriginalSchoolLevel ? $originalAttendanceYear : null,
        ])->save();

        return $this->informationForStudent($user, $studentCode, $selectionOverride);
    }

    /**
     * @param  array<string, mixed>  $selectionSummary
     * @return list<array{
     *     key: string,
     *     label: string,
     *     selected_value: mixed,
     *     options: list<array{title: string, value: mixed}>
     * }>
     */
    private function selectionFields(array $selectionSummary): array
    {
        $items = collect($selectionSummary['selection_items'] ?? [])->keyBy('key');
        $selection = (array) ($selectionSummary['selection'] ?? []);
        $options = (array) ($selectionSummary['selection_options'] ?? []);

        return collect(self::INFORMATION_KEYS)
            ->map(fn (string $key): array => [
                'key' => $key,
                'label' => (string) data_get($items, "{$key}.label", ''),
                'selected_value' => $selection[$key] ?? null,
                'options' => collect($options[$key] ?? [])
                    ->map(fn (array $option): array => [
                        'title' => (string) ($option['title'] ?? ''),
                        'value' => $option['value'] ?? null,
                    ])
                    ->filter(fn (array $option): bool => $option['title'] !== '' && $option['value'] !== null)
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $studyModules
     * @return list<array{
     *     key: string,
     *     label: string,
     *     count: int,
     *     modules: list<array{
     *         code: string,
     *         name: string,
     *         grade: string,
     *         grades: list<array{value: string, status: string}>
     *     }>
     * }>
     */
    private function moduleGroups(array $studyModules): array
    {
        return collect(self::MODULE_GROUPS)
            ->map(function (array $group) use ($studyModules): array {
                $modules = collect($studyModules[$group['key']] ?? [])
                    ->map(function (array $module) use ($group): array {
                        $grade = mb_strtoupper(trim((string) ($module['grade'] ?? '')), 'UTF-8');
                        $attemptCount = max(1, (int) ($module['attempt_count'] ?? 1));
                        $grades = collect([
                            ...array_fill(0, $attemptCount, ['value' => $grade, 'status' => $group['key']]),
                            ...collect($module['previous_failed_grades'] ?? [])
                                ->map(fn (mixed $failedGrade): array => [
                                    'value' => mb_strtoupper(trim((string) $failedGrade), 'UTF-8'),
                                    'status' => 'failed',
                                ])
                                ->all(),
                        ])
                            ->filter(fn (array $gradeItem): bool => $gradeItem['value'] !== '')
                            ->values()
                            ->all();

                        return [
                            'code' => trim((string) ($module['code'] ?? '')),
                            'name' => trim((string) ($module['name'] ?? '')),
                            'grade' => $grade,
                            'grades' => $grades,
                        ];
                    })
                    ->filter(fn (array $module): bool => $module['code'] !== '' && $module['grades'] !== [])
                    ->values()
                    ->all();

                return [
                    'key' => $group['key'],
                    'label' => $group['label'],
                    'count' => count($modules),
                    'modules' => $modules,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $groups
     * @return list<array<string, mixed>>
     */
    private function moduleSelectionGroups(array $groups): array
    {
        $descriptions = [
            'finished' => 'Bereits befreit oder bestanden',
            'negative' => 'Noch einmal zu absolvieren',
            'previous' => 'Aus früheren Semestern',
            'current' => 'Für das aktuelle Semester',
            'additional' => 'Frei zusätzlich wählbar',
        ];

        return collect($groups)
            ->map(function (array $group) use ($descriptions): array {
                $groupKey = (string) ($group['key'] ?? '');
                $modules = collect($group['modules'] ?? [])
                    ->map(function (array $module) use ($groupKey): array {
                        $grade = mb_strtoupper(trim((string) ($module['grade'] ?? '')), 'UTF-8');
                        $semester = is_numeric($module['semester'] ?? null) ? (int) $module['semester'] : null;
                        $hours = $this->moduleRegularHours($module) ?? $this->moduleHours($module);
                        $hoursLabel = trim((string) ($module['hours_label'] ?? ''));

                        return [
                            'selection_key' => trim((string) ($module['selection_key'] ?? '')),
                            'code' => trim((string) ($module['code'] ?? '')),
                            'name' => trim((string) ($module['name'] ?? '')),
                            'semester' => $semester,
                            'semester_label' => $semester ? "{$semester}. Semester" : null,
                            'hours' => $hours,
                            'hours_label' => $hours !== null
                                ? $this->courseHoursLabel($hours)
                                : ($hoursLabel !== '' ? $hoursLabel : null),
                            'status_label' => match ($groupKey) {
                                'finished' => $grade === 'B' ? 'Befreit' : 'Bestanden',
                                'negative' => 'Negativ',
                                default => null,
                            },
                            'grades' => $this->moduleGradeValues($module),
                            'courses' => $this->moduleCourses($module),
                            'selected_by_default' => (bool) ($module['selected_by_default'] ?? false),
                            'is_intended_for_selection' => (bool) ($module['is_intended_for_selection'] ?? true),
                        ];
                    })
                    ->filter(fn (array $module): bool => $module['selection_key'] !== '' && $module['code'] !== '')
                    ->values()
                    ->all();

                return [
                    'key' => $groupKey,
                    'label' => (string) ($group['label'] ?? ''),
                    'description' => $descriptions[$groupKey] ?? '',
                    'count' => count($modules),
                    'modules' => $modules,
                ];
            })
            ->filter(fn (array $group): bool => $group['key'] !== '' && $group['label'] !== '')
            ->values()
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $groups
     * @return list<array<string, mixed>>
     */
    private function mainModuleSelectionGroups(array $groups): array
    {
        return collect($groups)
            ->flatMap(fn (array $group): array => (array) ($group['modules'] ?? []))
            ->filter(fn (array $module): bool => $this->mainModuleCode((string) ($module['code'] ?? '')) !== '')
            ->unique(fn (array $module): string => (string) ($module['selection_key'] ?? ''))
            ->groupBy(fn (array $module): string => $this->mainModuleCode((string) ($module['code'] ?? '')))
            ->map(function (Collection $modules, string $mainModuleCode): array {
                $sortedModules = $modules
                    ->sort(fn (array $firstModule, array $secondModule): int => strnatcasecmp(
                        (string) ($firstModule['code'] ?? ''),
                        (string) ($secondModule['code'] ?? ''),
                    ))
                    ->values();
                $mainModuleName = $this->mainModuleName((array) $sortedModules->first());
                $moduleCount = $sortedModules->count();

                return [
                    'key' => $mainModuleCode,
                    'code' => $mainModuleCode,
                    'name' => $mainModuleName,
                    'label' => trim("{$mainModuleCode} {$mainModuleName}"),
                    'description' => $moduleCount === 1 ? '1 Modul verfügbar' : "{$moduleCount} Module verfügbar",
                    'count' => $moduleCount,
                    'modules' => $sortedModules->all(),
                ];
            })
            ->sort(fn (array $firstGroup, array $secondGroup): int => strnatcasecmp(
                (string) $firstGroup['code'],
                (string) $secondGroup['code'],
            ))
            ->values()
            ->all();
    }

    private function mainModuleCode(string $moduleCode): string
    {
        return Str::of($moduleCode)
            ->trim()
            ->upper()
            ->replaceMatches('/\d+$/u', '')
            ->toString();
    }

    /** @param array<string, mixed> $module */
    private function mainModuleName(array $module): string
    {
        return Str::of((string) ($module['name'] ?? ''))
            ->trim()
            ->replaceMatches('/\s*\d+$/u', '')
            ->trim()
            ->toString();
    }

    /**
     * @param  array<string, mixed>  $module
     * @return list<array<string, mixed>>
     */
    private function moduleCourses(array $module): array
    {
        return collect($module['course_groups'] ?? [])
            ->filter(fn (array $course): bool => $this->courseKey($course) !== '' && $this->courseTitle($course) !== '')
            ->groupBy(fn (array $course): string => Str::of($this->courseTitle($course))->lower()->toString())
            ->map(function (Collection $courseGroups) use ($module): array {
                $course = $courseGroups->first();
                $scheduleLabels = $courseGroups
                    ->map(fn (array $courseGroup): string => $this->courseScheduleLabel($courseGroup))
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();
                $displayScheduleRows = $this->courseDisplayScheduleRows($courseGroups);
                $usualHours = $this->moduleHours($module);
                $regularHours = $this->moduleRegularHours($module) ?? $usualHours;
                $scheduledHours = $this->courseScheduledWeeklyHours($courseGroups);
                $isDistanceLearning = $this->courseIsDistanceLearning($courseGroups, $scheduledHours, $regularHours);
                $dates = $courseGroups
                    ->flatMap(fn (array $courseGroup): array => is_array($courseGroup['dates'] ?? null)
                        ? $courseGroup['dates']
                        : [])
                    ->map(fn (mixed $date): string => trim((string) $date))
                    ->filter()
                    ->unique()
                    ->sort()
                    ->values();

                return [
                    'key' => $this->courseKey($course),
                    'keys' => $courseGroups
                        ->map(fn (array $courseGroup): string => $this->courseKey($courseGroup))
                        ->filter()
                        ->unique()
                        ->values()
                        ->all(),
                    'title' => $this->courseTitle($course),
                    'course_title' => trim((string) ($course['title'] ?? '')),
                    'teacher' => $courseGroups
                        ->pluck('teacher')
                        ->map(fn (mixed $teacher): string => trim((string) $teacher))
                        ->filter()
                        ->unique()
                        ->implode(', '),
                    'rooms_label' => $courseGroups
                        ->flatMap(fn (array $courseGroup): array => $courseGroup['rooms'] ?? [])
                        ->map(fn (mixed $room): string => trim((string) $room))
                        ->filter()
                        ->unique()
                        ->implode(', '),
                    'schedule_label' => $scheduleLabels[0] ?? '',
                    'schedule_labels' => $scheduleLabels,
                    'display_schedule_labels' => collect($displayScheduleRows)->pluck('label')->all(),
                    'display_schedule_rows' => $displayScheduleRows,
                    'scheduled_hours' => $scheduledHours,
                    'usual_hours' => $usualHours,
                    'regular_hours' => $regularHours,
                    'hours_label' => $this->courseHoursLabel($regularHours),
                    'is_distance_learning' => $isDistanceLearning,
                    'instruction_label' => $isDistanceLearning ? 'Fernunterricht' : null,
                    'timetable_entries' => $courseGroups
                        ->map(fn (array $courseGroup): array => $this->courseTimetableEntry($courseGroup))
                        ->values()
                        ->all(),
                    'block_label' => $courseGroups
                        ->pluck('block_label')
                        ->map(fn (mixed $label): string => trim((string) $label))
                        ->filter()
                        ->unique()
                        ->implode(', '),
                    'dates_count' => $dates->count(),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $courseGroup
     * @return array<string, mixed>
     */
    private function courseTimetableEntry(array $courseGroup): array
    {
        return [
            'key' => trim((string) ($courseGroup['key'] ?? '')),
            'weekday' => (int) ($courseGroup['weekday'] ?? 0),
            'hour' => (int) ($courseGroup['hour'] ?? 0),
            'starts_at' => trim((string) ($courseGroup['starts_at'] ?? '')),
            'ends_at' => trim((string) ($courseGroup['ends_at'] ?? '')),
            'time_from' => trim((string) ($courseGroup['time_from'] ?? $courseGroup['starts_at'] ?? '')),
            'time_until' => trim((string) ($courseGroup['time_until'] ?? $courseGroup['ends_at'] ?? '')),
            'display_label' => trim((string) ($courseGroup['display_label'] ?? '')),
            'subject' => trim((string) ($courseGroup['subject'] ?? '')),
            'course' => trim((string) ($courseGroup['course'] ?? '')),
            'module_code' => trim((string) ($courseGroup['module_code'] ?? '')),
            'teacher' => trim((string) ($courseGroup['teacher'] ?? '')),
            'rooms' => collect(is_array($courseGroup['rooms'] ?? null) ? $courseGroup['rooms'] : [])
                ->map(fn (mixed $room): string => trim((string) $room))
                ->filter()
                ->unique()
                ->values()
                ->all(),
            'class_name' => trim((string) ($courseGroup['class_name'] ?? '')),
            'student_group' => trim((string) ($courseGroup['student_group'] ?? '')),
            'dates' => collect(is_array($courseGroup['dates'] ?? null) ? $courseGroup['dates'] : [])
                ->map(fn (mixed $date): string => trim((string) $date))
                ->filter()
                ->unique()
                ->sort()
                ->values()
                ->all(),
            'recurrence_interval' => is_numeric($courseGroup['recurrence_interval'] ?? null)
                ? (int) $courseGroup['recurrence_interval']
                : null,
            'recurrence_label' => trim((string) ($courseGroup['recurrence_label'] ?? '')),
            'is_block' => ($courseGroup['is_block'] ?? false) === true,
            'block_label' => trim((string) ($courseGroup['block_label'] ?? '')),
            'is_full_semester' => ($courseGroup['is_full_semester'] ?? false) === true,
            'is_kompaktunterricht' => ($courseGroup['is_kompaktunterricht'] ?? false) === true,
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $courseGroups
     * @return list<array{label: string, entry_keys: list<string>}>
     */
    private function courseDisplayScheduleRows(Collection $courseGroups): array
    {
        $scheduleRanges = [];

        $sortedCourseGroups = $courseGroups->sortBy(
            fn (array $courseGroup): string => $this->courseGroupScheduleSeriesKey($courseGroup)
                .'|'.$this->courseGroupScheduleSortValue($courseGroup),
        );

        foreach ($sortedCourseGroups as $courseGroup) {
            $lastRangeIndex = count($scheduleRanges) - 1;

            if ($lastRangeIndex >= 0
                && $this->courseGroupsHaveContinuousSchedule($scheduleRanges[$lastRangeIndex], $courseGroup)) {
                $scheduleRanges[$lastRangeIndex]['time_until'] = trim((string) ($courseGroup['time_until'] ?? $courseGroup['ends_at'] ?? ''));
                $scheduleRanges[$lastRangeIndex]['display_range_until_hour'] = $this->courseGroupDisplayRangeUntilHour($courseGroup);
                $scheduleRanges[$lastRangeIndex]['display_entry_keys'][] = $this->courseKey($courseGroup);

                continue;
            }

            $courseGroup['display_entry_keys'] = [$this->courseKey($courseGroup)];
            $scheduleRanges[] = $courseGroup;
        }

        return collect($scheduleRanges)
            ->sortBy(fn (array $courseGroup): string => $this->courseGroupScheduleSortValue($courseGroup))
            ->map(fn (array $courseGroup): array => [
                'label' => $this->courseScheduleLabel($courseGroup),
                'entry_keys' => collect($courseGroup['display_entry_keys'] ?? [])
                    ->map(fn (mixed $entryKey): string => trim((string) $entryKey))
                    ->filter()
                    ->unique()
                    ->values()
                    ->all(),
            ])
            ->filter(fn (array $scheduleRow): bool => $scheduleRow['label'] !== '')
            ->groupBy('label')
            ->map(fn (Collection $scheduleRows, string $label): array => [
                'label' => $label,
                'entry_keys' => $scheduleRows
                    ->flatMap(fn (array $scheduleRow): array => $scheduleRow['entry_keys'])
                    ->unique()
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();
    }

    /** @param array<string, mixed> $courseGroup */
    private function courseGroupScheduleSortValue(array $courseGroup): string
    {
        return implode('|', [
            str_pad((string) ($courseGroup['weekday'] ?? 0), 2, '0', STR_PAD_LEFT),
            trim((string) ($courseGroup['time_from'] ?? $courseGroup['starts_at'] ?? '')),
            str_pad((string) ($courseGroup['hour'] ?? 0), 3, '0', STR_PAD_LEFT),
            $this->courseGroupScheduleSeriesKey($courseGroup),
        ]);
    }

    /**
     * @param  array<string, mixed>  $firstCourseGroup
     * @param  array<string, mixed>  $secondCourseGroup
     */
    private function courseGroupsHaveContinuousSchedule(array $firstCourseGroup, array $secondCourseGroup): bool
    {
        if ($this->courseGroupScheduleSeriesKey($firstCourseGroup)
            !== $this->courseGroupScheduleSeriesKey($secondCourseGroup)) {
            return false;
        }

        $firstHour = $this->courseGroupDisplayRangeUntilHour($firstCourseGroup);
        $secondHour = $this->courseGroupDisplayRangeUntilHour($secondCourseGroup);

        if ($firstHour !== null && $secondHour !== null) {
            return $secondHour === $firstHour + 1;
        }

        $firstEndTime = trim((string) ($firstCourseGroup['time_until'] ?? $firstCourseGroup['ends_at'] ?? ''));
        $secondStartTime = trim((string) ($secondCourseGroup['time_from'] ?? $secondCourseGroup['starts_at'] ?? ''));

        return $firstEndTime !== ''
            && $firstEndTime === $secondStartTime;
    }

    /** @param array<string, mixed> $courseGroup */
    private function courseGroupDisplayRangeUntilHour(array $courseGroup): ?int
    {
        $hour = $courseGroup['display_range_until_hour'] ?? $courseGroup['hour'] ?? null;

        return is_numeric($hour) && (int) $hour > 0
            ? (int) $hour
            : null;
    }

    /** @param array<string, mixed> $courseGroup */
    private function courseGroupScheduleSeriesKey(array $courseGroup): string
    {
        $dates = collect(is_array($courseGroup['dates'] ?? null) ? $courseGroup['dates'] : [])
            ->map(fn (mixed $date): string => trim((string) $date))
            ->filter()
            ->sort()
            ->values()
            ->implode(',');

        return implode('|', [
            (string) ($courseGroup['weekday'] ?? ''),
            (string) ($courseGroup['recurrence_interval'] ?? ''),
            trim((string) ($courseGroup['recurrence_label'] ?? '')),
            $dates,
            trim((string) ($courseGroup['block_label'] ?? '')),
            ($courseGroup['is_kompaktunterricht'] ?? false) === true ? 'compact' : 'regular',
        ]);
    }

    private function courseHoursLabel(?float $usualHours): string
    {
        return $usualHours === null
            ? ''
            : $this->formatHours($usualHours).' Std.';
    }

    private function formatHours(float $hours): string
    {
        return rtrim(rtrim(number_format($hours, 2, ',', ''), '0'), ',');
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $courseGroups
     */
    private function courseIsDistanceLearning(
        Collection $courseGroups,
        ?float $scheduledHours,
        ?float $regularHours,
    ): bool {
        if ($courseGroups->contains(
            fn (array $courseGroup): bool => ($courseGroup['is_kompaktunterricht'] ?? false) === true,
        )) {
            return false;
        }

        if ($scheduledHours === null || $regularHours === null) {
            return false;
        }

        return abs(($scheduledHours * 2) - $regularHours) < 0.001;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $courseGroups
     */
    private function courseScheduledWeeklyHours(Collection $courseGroups): ?float
    {
        $recurringCourseGroups = $courseGroups
            ->reject(fn (array $courseGroup): bool => $this->courseGroupIsOccasional($courseGroup))
            ->unique(fn (array $courseGroup): string => implode('|', [
                (string) ($courseGroup['weekday'] ?? ''),
                (string) ($courseGroup['hour'] ?? ''),
            ]));

        if ($recurringCourseGroups->isEmpty()) {
            return null;
        }

        return $recurringCourseGroups
            ->sum(fn (array $courseGroup): float => 1 / $this->courseGroupWeekInterval($courseGroup));
    }

    /** @param array<string, mixed> $module */
    private function moduleHours(array $module): ?float
    {
        $hours = $module['hours'] ?? $module['hours_per_week'] ?? null;

        return is_numeric($hours) && (float) $hours > 0
            ? (float) $hours
            : null;
    }

    /** @param array<string, mixed> $module */
    private function moduleRegularHours(array $module): ?float
    {
        $hours = $module['regular_hours'] ?? null;

        return is_numeric($hours) && (float) $hours > 0
            ? (float) $hours
            : null;
    }

    /** @param array<string, mixed> $courseGroup */
    private function courseGroupIsOccasional(array $courseGroup): bool
    {
        $datesCount = is_numeric($courseGroup['dates_count'] ?? null)
            ? (int) $courseGroup['dates_count']
            : null;

        return $datesCount !== null && $datesCount > 0 && $datesCount <= 2;
    }

    /** @param array<string, mixed> $courseGroup */
    private function courseGroupWeekInterval(array $courseGroup): int
    {
        if (is_numeric($courseGroup['recurrence_interval'] ?? null)
            && (int) $courseGroup['recurrence_interval'] > 0) {
            return (int) $courseGroup['recurrence_interval'];
        }

        if (preg_match('/(\d+)\s*-\s*w/iu', (string) ($courseGroup['recurrence_label'] ?? ''), $matches) === 1) {
            return max(1, (int) $matches[1]);
        }

        return 1;
    }

    /** @param array<string, mixed> $course */
    private function courseKey(array $course): string
    {
        return trim((string) ($course['key'] ?? ''));
    }

    /** @param array<string, mixed> $course */
    private function courseTitle(array $course): string
    {
        return Str::of((string) ($course['display_label'] ?? $course['title'] ?? ''))
            ->squish()
            ->toString();
    }

    /** @param array<string, mixed> $course */
    private function courseScheduleLabel(array $course): string
    {
        $weekdayLabel = [
            1 => 'Montag',
            2 => 'Dienstag',
            3 => 'Mittwoch',
            4 => 'Donnerstag',
            5 => 'Freitag',
            6 => 'Samstag',
        ][(int) ($course['weekday'] ?? 0)] ?? '';
        $timeFrom = trim((string) ($course['time_from'] ?? $course['starts_at'] ?? ''));
        $timeUntil = trim((string) ($course['time_until'] ?? $course['ends_at'] ?? ''));
        $timeLabel = $timeFrom !== '' && $timeUntil !== ''
            ? "{$timeFrom}–{$timeUntil}"
            : '';
        $hour = (int) ($course['hour'] ?? 0);
        $recurrenceLabel = trim((string) ($course['recurrence_label'] ?? ''));
        $singleDateLabel = $this->courseSingleDateLabel($course);

        return collect([
            $weekdayLabel,
            $timeLabel !== '' ? $timeLabel : ($hour > 0 ? "{$hour}. Stunde" : ''),
            $recurrenceLabel,
            $singleDateLabel,
        ])->filter()->implode(' · ');
    }

    /** @param array<string, mixed> $course */
    private function courseSingleDateLabel(array $course): string
    {
        $dates = collect(is_array($course['dates'] ?? null) ? $course['dates'] : [])
            ->map(fn (mixed $date): string => trim((string) $date))
            ->filter()
            ->unique()
            ->values();

        if ($dates->count() !== 1) {
            return '';
        }

        $date = (string) $dates->first();

        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $matches) !== 1) {
            return $date;
        }

        return "{$matches[3]}.{$matches[2]}.{$matches[1]}";
    }

    /**
     * @param  array<string, mixed>  $module
     * @return list<string>
     */
    private function moduleGradeValues(array $module): array
    {
        $grade = mb_strtoupper(trim((string) ($module['grade'] ?? '')), 'UTF-8');
        $attemptCount = max(1, (int) ($module['attempt_count'] ?? 1));

        return collect([
            ...array_fill(0, $attemptCount, $grade),
            ...($module['previous_failed_grades'] ?? []),
        ])
            ->map(fn (mixed $gradeValue): string => mb_strtoupper(trim((string) $gradeValue), 'UTF-8'))
            ->filter()
            ->values()
            ->all();
    }
}
