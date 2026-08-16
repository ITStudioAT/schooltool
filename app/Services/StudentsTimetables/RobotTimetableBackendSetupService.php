<?php

namespace App\Services\StudentsTimetables;

use App\Enums\StudentTimetableStudyProgram;
use App\Models\StudentTimetableSubjectMapping;
use App\Models\StudentTimetableSubjectRow;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use SplPriorityQueue;

class RobotTimetableBackendSetupService
{
    private const MAX_BACKEND_TIMETABLE_VARIATIONS = 200000;

    private const V3_CHECKING_PROGRESS_PERCENT = 80;

    private const V3_MATERIALIZING_PROGRESS_PERCENT = 85;

    private const V3_COMPACTING_PROGRESS_PERCENT = 90;

    private const TIMETABLE_VARIATION_CACHE_VERSION = 5;

    private const TIMETABLE_VARIATION_CACHE_TTL_MINUTES = 20;

    /**
     * @var array<string, mixed>
     */
    private array $runtimeCache = [];

    public function __construct(
        private StudentTimetableRememberedTtEntryService $rememberedTtEntryService,
        private TimetableDateSlotOverlapService $dateSlotOverlapService,
    ) {}

    /**
     * @param  array<string, mixed>  $settings
     * @param  list<array<string, mixed>>  $evaluationCriteria
     * @param  list<string>  $selectedQualityCriterionKeys
     * @return array<string, mixed>
     */
    public function createInitialBackendTimetable(
        User $authUser,
        array $settings,
        StudentTimetableOverviewService $overviewService,
        array $evaluationCriteria = [],
        array $selectedQualityCriterionKeys = [],
    ): array {
        $this->resetRuntimeCache();

        $subjectRows = StudentTimetableSubjectRow::query()
            ->forStudyProgram(StudentTimetableStudyProgram::Normalstudium)
            ->where('school_id', $authUser->school_id)
            ->where('schoolyear_id', $authUser->schoolyear_id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (StudentTimetableSubjectRow $row): array => $row->toArray())
            ->all();

        $subjectMappings = StudentTimetableSubjectMapping::query()
            ->where('school_id', $authUser->school_id)
            ->where('schoolyear_id', $authUser->schoolyear_id)
            ->orderBy('json_subject')
            ->orderBy('tt_subject')
            ->get()
            ->map(fn (StudentTimetableSubjectMapping $mapping): array => $mapping->toArray())
            ->all();

        $courseGroups = $this->rememberedTtEntryService->activeCourseGroupsForUser(
            $authUser,
            $overviewService->courseGroupsForUser($authUser),
        );

        $variationResult = $this->calculateCachedTimetableVariationsForUser(
            authUser: $authUser,
            subjectRows: $subjectRows,
            subjectMappings: $subjectMappings,
            courseGroups: $courseGroups,
            settings: $settings,
            evaluationCriteria: $evaluationCriteria,
            selectedQualityCriterionKeys: $selectedQualityCriterionKeys,
        );

        return [
            'algorithm' => [
                'key' => 'backend-v2',
                'name' => 'Neue Backend-Stundenplanlogik',
                'status' => 'step-1-counts-ready',
                'school_id' => $authUser->school_id,
                'schoolyear_id' => $authUser->schoolyear_id,
            ],
            'timetable_variation_count' => $variationResult['timetable_variation_count'],
            'full_green_timetable_count' => $variationResult['full_green_timetable_count'],
            'green_timetable_count' => $variationResult['green_timetable_count'],
            'red_timetable_count' => $variationResult['red_timetable_count'],
            'conflict_timetable_count' => $variationResult['red_timetable_count'],
            'selected_course_count' => $variationResult['selected_course_count'],
            'selected_additional_course_count' => count($this->stringList($settings['selected_additional_course_keys'] ?? [])),
            'additional_course_timetable_count' => $variationResult['additional_course_timetable_count'],
            'no_saturday_timetable_count' => $variationResult['no_saturday_timetable_count'],
            'selected_timetable' => $variationResult['selected_timetable'],
            'problem_courses' => $variationResult['problem_courses'],
            'quality_counters' => $variationResult['quality_counters'],
            'all_quality_criteria_count' => $variationResult['all_quality_criteria_count'],
            'selected_quality_criteria_count' => $variationResult['selected_quality_criteria_count'],
            'conflicting_additional_course_keys' => $this->conflictingAdditionalCourseKeysFromInput(
                $subjectRows,
                $subjectMappings,
                $courseGroups,
                $settings,
                $variationResult['selected_timetable'],
            ),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $subjectRows
     * @param  list<array<string, mixed>>  $subjectMappings
     * @param  list<array<string, mixed>>  $courseGroups
     * @param  array<string, mixed>  $settings
     * @return list<string>
     */
    public function conflictingAdditionalCourseKeys(
        array $subjectRows,
        array $subjectMappings,
        array $courseGroups,
        array $settings,
        ?array $selectedTimetable,
    ): array {
        $this->resetRuntimeCache();

        return $this->conflictingAdditionalCourseKeysFromInput(
            $subjectRows,
            $subjectMappings,
            $courseGroups,
            $settings,
            $selectedTimetable,
        );
    }

    /**
     * @param  list<array<string, mixed>>  $subjectRows
     * @param  list<array<string, mixed>>  $subjectMappings
     * @param  list<array<string, mixed>>  $courseGroups
     * @param  array<string, mixed>  $settings
     * @return list<string>
     */
    private function conflictingAdditionalCourseKeysFromInput(
        array $subjectRows,
        array $subjectMappings,
        array $courseGroups,
        array $settings,
        ?array $selectedTimetable,
    ): array {
        if (! $selectedTimetable) {
            return [];
        }

        $availableAdditionalCourseKeys = $this->stringList($settings['available_additional_course_keys'] ?? []);
        if ($availableAdditionalCourseKeys === []) {
            return [];
        }

        $additionalCourseSettings = [
            ...$settings,
            'selected_course_keys' => $availableAdditionalCourseKeys,
            'selected_additional_course_keys' => [],
        ];
        $selectedCourseKeys = $this->stringList($settings['selected_course_keys'] ?? []);
        $selectedCourseKeySet = array_flip($selectedCourseKeys);
        $occupiedCourseGroups = $this->selectedTimetableOccupiedCourseGroups($selectedTimetable);

        return collect($this->selectedCourses($subjectRows, $subjectMappings, $courseGroups, $additionalCourseSettings))
            ->reject(fn (array $course): bool => isset($selectedCourseKeySet[(string) ($course['key'] ?? '')]))
            ->filter(function (array $course) use ($courseGroups, $subjectMappings, $settings, $occupiedCourseGroups): bool {
                $options = $this->courseOptions($course, $courseGroups, $subjectMappings, $settings);

                return $options !== []
                    && collect($options)->every(fn (array $option): bool => $this->courseOptionConflictsWithSelectedTimetable(
                        $option,
                        $occupiedCourseGroups,
                    ));
            })
            ->pluck('key')
            ->map(fn (mixed $courseKey): string => (string) $courseKey)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $option
     * @param  list<array<string, mixed>>  $occupiedCourseGroups
     */
    private function courseOptionConflictsWithSelectedTimetable(array $option, array $occupiedCourseGroups): bool
    {
        if (($option['all_date_summary']['has_overlap'] ?? false) === true) {
            return true;
        }

        return collect($option['course_groups'] ?? [])
            ->contains(fn (array $courseGroup): bool => collect($occupiedCourseGroups)
                ->contains(fn (array $occupiedCourseGroup): bool => $this->courseGroupsBlockTimetableSlot(
                    $occupiedCourseGroup,
                    $courseGroup,
                )));
    }

    /**
     * @param  array<string, mixed>  $selectedTimetable
     * @return list<array<string, mixed>>
     */
    private function selectedTimetableOccupiedCourseGroups(array $selectedTimetable): array
    {
        return collect($selectedTimetable['slots'] ?? [])
            ->flatMap(function (array $slot): array {
                return [
                    $slot['courseGroup'] ?? null,
                    ...collect($slot['sameSlotEntries'] ?? [])
                        ->pluck('courseGroup')
                        ->all(),
                    ...collect($slot['conflicts'] ?? [])
                        ->pluck('courseGroup')
                        ->all(),
                ];
            })
            ->filter(fn (mixed $courseGroup): bool => is_array($courseGroup))
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $settings
     * @param  list<array<string, mixed>>  $evaluationCriteria
     * @param  list<string>  $selectedQualityCriterionKeys
     * @return array<string, mixed>
     */
    public function qualityCountersForUser(
        User $authUser,
        array $settings,
        StudentTimetableOverviewService $overviewService,
        array $evaluationCriteria,
        array $selectedQualityCriterionKeys = [],
    ): array {
        $this->resetRuntimeCache();

        $subjectRows = StudentTimetableSubjectRow::query()
            ->forStudyProgram(StudentTimetableStudyProgram::Normalstudium)
            ->where('school_id', $authUser->school_id)
            ->where('schoolyear_id', $authUser->schoolyear_id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (StudentTimetableSubjectRow $row): array => $row->toArray())
            ->all();

        $subjectMappings = StudentTimetableSubjectMapping::query()
            ->where('school_id', $authUser->school_id)
            ->where('schoolyear_id', $authUser->schoolyear_id)
            ->orderBy('json_subject')
            ->orderBy('tt_subject')
            ->get()
            ->map(fn (StudentTimetableSubjectMapping $mapping): array => $mapping->toArray())
            ->all();

        return $this->qualityCountersForTimetableVariations(
            subjectRows: $subjectRows,
            subjectMappings: $subjectMappings,
            courseGroups: $this->rememberedTtEntryService->activeCourseGroupsForUser(
                $authUser,
                $overviewService->courseGroupsForUser($authUser),
            ),
            settings: $settings,
            evaluationCriteria: $evaluationCriteria,
            selectedQualityCriterionKeys: $selectedQualityCriterionKeys,
        );
    }

    /**
     * @param  array<string, mixed>  $settings
     * @param  list<array<string, mixed>>  $candidateCourses
     * @param  list<array<string, mixed>>  $evaluationCriteria
     * @param  list<string>  $selectedQualityCriterionKeys
     * @return array<string, array{available: bool, valid_timetable_count: int}>
     */
    public function courseAvailabilityForUser(
        User $authUser,
        array $settings,
        array $candidateCourses,
        StudentTimetableOverviewService $overviewService,
        array $evaluationCriteria = [],
        array $selectedQualityCriterionKeys = [],
        bool $availabilityOnly = false,
    ): array {
        $this->resetRuntimeCache();

        $subjectRows = StudentTimetableSubjectRow::query()
            ->forStudyProgram(StudentTimetableStudyProgram::Normalstudium)
            ->where('school_id', $authUser->school_id)
            ->where('schoolyear_id', $authUser->schoolyear_id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (StudentTimetableSubjectRow $row): array => $row->toArray())
            ->all();

        $subjectMappings = StudentTimetableSubjectMapping::query()
            ->where('school_id', $authUser->school_id)
            ->where('schoolyear_id', $authUser->schoolyear_id)
            ->orderBy('json_subject')
            ->orderBy('tt_subject')
            ->get()
            ->map(fn (StudentTimetableSubjectMapping $mapping): array => $mapping->toArray())
            ->all();

        $courseGroups = $this->rememberedTtEntryService->activeCourseGroupsForUser(
            $authUser,
            $overviewService->courseGroupsForUser($authUser),
        );
        $selectedQualityCriterionKeys = $this->selectedQualityCriterionKeys(
            $selectedQualityCriterionKeys,
            $evaluationCriteria,
        );

        if ($availabilityOnly) {
            $selectedTimetable = null;
            $baseInput = null;

            if ($selectedQualityCriterionKeys === []) {
                $baseCacheKey = $this->timetableVariationBaseCacheKey(
                    $authUser,
                    $subjectRows,
                    $subjectMappings,
                    $courseGroups,
                    $settings,
                    $evaluationCriteria,
                    $selectedQualityCriterionKeys,
                );
                $base = $this->rememberedTimetableVariationBase(
                    $baseCacheKey,
                    $subjectRows,
                    $subjectMappings,
                    $courseGroups,
                    $settings,
                    $evaluationCriteria,
                    $selectedQualityCriterionKeys,
                );
                $selectedTimetable = $this->rememberedSelectedTimetable(
                    $baseCacheKey,
                    $base,
                    $settings,
                    $evaluationCriteria,
                );
                $baseInput = $this->timetableVariationInputFromBase($base);
            }

            return $this->courseAvailabilityFromSharedInput(
                $subjectRows,
                $subjectMappings,
                $courseGroups,
                $settings,
                $candidateCourses,
                $selectedTimetable,
                $baseInput,
                $evaluationCriteria,
                $selectedQualityCriterionKeys,
            );
        }

        $availability = [];

        foreach ($candidateCourses as $candidateCourse) {
            $availabilityKey = (string) $candidateCourse['availability_key'];
            $validTimetableCount = $this->validTimetableCountForAvailability(
                $subjectRows,
                $subjectMappings,
                $courseGroups,
                $this->settingsWithAvailabilityCandidate($settings, $candidateCourse),
                $evaluationCriteria,
                $selectedQualityCriterionKeys,
                $availabilityOnly,
            );

            $availability[$availabilityKey] = [
                'available' => $validTimetableCount > 0,
                'valid_timetable_count' => $validTimetableCount,
            ];
        }

        return $availability;
    }

    /**
     * @param  list<array<string, mixed>>  $subjectRows
     * @param  list<array<string, mixed>>  $subjectMappings
     * @param  list<array<string, mixed>>  $courseGroups
     * @param  array<string, mixed>  $settings
     * @param  list<array<string, mixed>>  $candidateCourses
     * @return array<string, array{available: bool, valid_timetable_count: int}>
     */
    private function courseAvailabilityFromSharedInput(
        array $subjectRows,
        array $subjectMappings,
        array $courseGroups,
        array $settings,
        array $candidateCourses,
        ?array $selectedTimetable = null,
        ?array $baseInput = null,
        array $evaluationCriteria = [],
        array $selectedQualityCriterionKeys = [],
    ): array {
        $baseInput ??= $this->timetableVariationInput($subjectRows, $subjectMappings, $courseGroups, $settings);
        $availableCoursesByKey = $this->availableCoursesByKey($subjectRows, $subjectMappings, $courseGroups, $settings);
        $selectedTimetableOccupiedCourseGroups = $selectedTimetable === null
            ? null
            : $this->selectedTimetableOccupiedCourseGroups($selectedTimetable);
        $availability = [];

        foreach ($candidateCourses as $candidateCourse) {
            $availabilityKey = (string) $candidateCourse['availability_key'];
            $courseKey = trim((string) $candidateCourse['course_key']);

            if ($courseKey === '' || ! array_key_exists($courseKey, $availableCoursesByKey)) {
                $availability[$availabilityKey] = [
                    'available' => false,
                    'valid_timetable_count' => 0,
                ];

                continue;
            }

            if (
                $selectedTimetableOccupiedCourseGroups !== null
                && ! $this->availabilityCandidateAlreadySelected($candidateCourse, $settings, $courseKey)
            ) {
                $candidateSettings = $this->settingsWithAvailabilityCandidate($settings, $candidateCourse);
                $courseOptions = $this->courseOptions(
                    $availableCoursesByKey[$courseKey],
                    $courseGroups,
                    $subjectMappings,
                    $candidateSettings,
                );
                $candidateAvailable = $courseOptions !== []
                    && collect($courseOptions)->contains(fn (array $option): bool => ! $this->courseOptionConflictsWithSelectedTimetable(
                        $option,
                        $selectedTimetableOccupiedCourseGroups,
                    ));

                if ($candidateAvailable) {
                    $availability[$availabilityKey] = [
                        'available' => true,
                        'valid_timetable_count' => 1,
                    ];

                    continue;
                }
            }

            [$candidateInput, $candidateSettings] = $this->timetableVariationInputWithAvailabilityCandidate(
                $baseInput,
                $settings,
                $candidateCourse,
                $availableCoursesByKey,
                $courseGroups,
                $subjectMappings,
            );
            $validTimetableCount = $this->validTimetableCountForAvailabilityInput(
                $candidateInput,
                $candidateSettings,
                $evaluationCriteria,
                $selectedQualityCriterionKeys,
                true,
            );

            $availability[$availabilityKey] = [
                'available' => $validTimetableCount > 0,
                'valid_timetable_count' => $validTimetableCount,
            ];
        }

        return $availability;
    }

    /**
     * @param  array<string, mixed>  $candidateCourse
     * @param  array<string, mixed>  $settings
     */
    private function availabilityCandidateAlreadySelected(array $candidateCourse, array $settings, string $courseKey): bool
    {
        $selectedCourseKey = ($candidateCourse['course_group'] ?? null) === 'additional'
            ? 'selected_additional_course_keys'
            : 'selected_course_keys';

        return in_array($courseKey, $this->stringList($settings[$selectedCourseKey] ?? []), true);
    }

    /**
     * @param  list<array<string, mixed>>  $subjectRows
     * @param  list<array<string, mixed>>  $subjectMappings
     * @param  list<array<string, mixed>>  $courseGroups
     * @param  array<string, mixed>  $settings
     * @return array<string, array<string, mixed>>
     */
    private function availableCoursesByKey(
        array $subjectRows,
        array $subjectMappings,
        array $courseGroups,
        array $settings,
    ): array {
        return collect($subjectRows)
            ->filter(fn (array $subject): bool => ($subject['is_active'] ?? true) !== false)
            ->filter(fn (array $subject): bool => $this->selectedModulesAreAuthoritative($settings)
                || $this->subjectMatchesSelectedBranch($subject, $settings))
            ->filter(fn (array $subject): bool => $this->selectedModulesAreAuthoritative($settings)
                || $this->subjectMatchesSelectedChoices($subject, $settings))
            ->flatMap(fn (array $subject): array => $this->courseVariantsFromSubject(
                $subject,
                $subjectMappings,
                $courseGroups,
                $settings,
                'selected_course_keys',
            ))
            ->reduce(function (array $coursesByKey, array $course) use ($settings): array {
                foreach ($this->availabilityCourseLookupKeys($course, $settings) as $courseKey) {
                    $coursesByKey[$courseKey] ??= $course;
                }

                return $coursesByKey;
            }, []);
    }

    /**
     * @param  array<string, mixed>  $course
     * @param  array<string, mixed>  $settings
     * @return list<string>
     */
    private function availabilityCourseLookupKeys(array $course, array $settings): array
    {
        return collect([
            $course['key'] ?? '',
            $this->genericSelectedReligionCourseKey($course, $settings),
            ...$this->courseAliases($course),
        ])
            ->map(fn (string $value): string => trim($value))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $course
     * @param  array<string, mixed>  $settings
     */
    private function genericSelectedReligionCourseKey(array $course, array $settings): string
    {
        $selectedReligion = $this->normalizedCourseCode(data_get($settings, 'selection.religion', ''));
        if (! in_array($selectedReligion, ['REV', 'RIS', 'RK', 'ROR'], true)) {
            return '';
        }

        $courseCode = $this->normalizedCourseCode($course['code'] ?? '');
        if (preg_match('/^([A-Z]+)(\d+)$/u', $courseCode, $matches) !== 1) {
            return '';
        }

        if (($matches[1] ?? '') !== $selectedReligion) {
            return '';
        }

        return "R{$matches[2]}";
    }

    /**
     * @param  array{selected_courses: list<array<string, mixed>>, course_options: list<list<array<string, mixed>>>, additional_courses: list<array<string, mixed>>, additional_course_options: list<list<array<string, mixed>>>, has_missing_options: bool}  $baseInput
     * @param  array<string, mixed>  $settings
     * @param  array<string, mixed>  $candidateCourse
     * @param  array<string, array<string, mixed>>  $availableCoursesByKey
     * @param  list<array<string, mixed>>  $courseGroups
     * @param  list<array<string, mixed>>  $subjectMappings
     * @return array{0: array{selected_courses: list<array<string, mixed>>, course_options: list<list<array<string, mixed>>>, additional_courses: list<array<string, mixed>>, additional_course_options: list<list<array<string, mixed>>>, has_missing_options: bool}, 1: array<string, mixed>}
     */
    private function timetableVariationInputWithAvailabilityCandidate(
        array $baseInput,
        array $settings,
        array $candidateCourse,
        array $availableCoursesByKey,
        array $courseGroups,
        array $subjectMappings,
    ): array {
        $candidateSettings = $this->settingsWithAvailabilityCandidate($settings, $candidateCourse);
        $courseKey = trim((string) $candidateCourse['course_key']);
        $course = $availableCoursesByKey[$courseKey] ?? null;

        if ($courseKey === '' || $course === null) {
            return [$baseInput, $candidateSettings];
        }

        if ($candidateCourse['course_group'] === 'additional') {
            if (in_array($courseKey, $this->stringList($settings['selected_additional_course_keys'] ?? []), true)) {
                if ($this->stringList($candidateCourse['deselected_course_group_keys'] ?? []) === []) {
                    return [$baseInput, $candidateSettings];
                }

                $additionalCourses = $baseInput['additional_courses'];
                $additionalCourseOptions = $baseInput['additional_course_options'];
                foreach ($additionalCourses as $courseIndex => $additionalCourse) {
                    if (! $this->courseMatchesSelectedCourseKeys($additionalCourse, [$courseKey])) {
                        continue;
                    }

                    $additionalCourseOptions[$courseIndex] = $this->courseOptions($course, $courseGroups, $subjectMappings, $candidateSettings);

                    return [[
                        ...$baseInput,
                        'additional_course_options' => $additionalCourseOptions,
                    ], $candidateSettings];
                }

                return [$baseInput, $candidateSettings];
            }

            return [[
                ...$baseInput,
                'additional_courses' => [
                    ...$baseInput['additional_courses'],
                    $course,
                ],
                'additional_course_options' => [
                    ...$baseInput['additional_course_options'],
                    $this->courseOptions($course, $courseGroups, $subjectMappings, $candidateSettings),
                ],
            ], $candidateSettings];
        }

        if (in_array($courseKey, $this->stringList($settings['selected_course_keys'] ?? []), true)) {
            if ($this->stringList($candidateCourse['deselected_course_group_keys'] ?? []) === []) {
                return [$baseInput, $candidateSettings];
            }

            $selectedCourses = $baseInput['selected_courses'];
            $courseOptions = $baseInput['course_options'];
            foreach ($selectedCourses as $courseIndex => $selectedCourse) {
                if (! $this->courseMatchesSelectedCourseKeys($selectedCourse, [$courseKey])) {
                    continue;
                }

                $courseOptions[$courseIndex] = $this->courseOptions($course, $courseGroups, $subjectMappings, $candidateSettings);

                return [[
                    ...$baseInput,
                    'course_options' => $courseOptions,
                    'has_missing_options' => collect($courseOptions)->contains(fn (array $options): bool => $options === []),
                ], $candidateSettings];
            }

            return [$baseInput, $candidateSettings];
        }

        $courseOptions = [
            ...$baseInput['course_options'],
            $this->courseOptions($course, $courseGroups, $subjectMappings, $candidateSettings),
        ];

        return [[
            ...$baseInput,
            'selected_courses' => [
                ...$baseInput['selected_courses'],
                $course,
            ],
            'course_options' => $courseOptions,
            'has_missing_options' => collect($courseOptions)->contains(fn (array $options): bool => $options === []),
        ], $candidateSettings];
    }

    /**
     * @param  array<string, mixed>  $settings
     * @param  array<string, mixed>  $candidateCourse
     * @return array<string, mixed>
     */
    private function settingsWithAvailabilityCandidate(array $settings, array $candidateCourse): array
    {
        $courseKey = trim((string) $candidateCourse['course_key']);
        if ($courseKey === '') {
            return $settings;
        }

        $settings = [
            ...$settings,
            'deselected_course_group_keys' => $this->uniqueStrings([
                ...$this->stringList($settings['deselected_course_group_keys'] ?? []),
                ...$this->stringList($candidateCourse['deselected_course_group_keys'] ?? []),
            ]),
        ];

        if ($candidateCourse['course_group'] === 'additional') {
            return [
                ...$settings,
                'selected_additional_course_keys' => $this->uniqueStrings([
                    ...$this->stringList($settings['selected_additional_course_keys'] ?? []),
                    $courseKey,
                ]),
                'selected_additional_courses_required' => true,
            ];
        }

        return [
            ...$settings,
            'selected_course_keys' => $this->uniqueStrings([
                ...$this->stringList($settings['selected_course_keys'] ?? []),
                $courseKey,
            ]),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $subjectRows
     * @param  list<array<string, mixed>>  $subjectMappings
     * @param  list<array<string, mixed>>  $courseGroups
     * @param  array<string, mixed>  $settings
     * @param  list<array<string, mixed>>  $evaluationCriteria
     * @param  list<string>  $selectedQualityCriterionKeys
     */
    private function validTimetableCountForAvailability(
        array $subjectRows,
        array $subjectMappings,
        array $courseGroups,
        array $settings,
        array $evaluationCriteria,
        array $selectedQualityCriterionKeys,
        bool $availabilityOnly = false,
    ): int {
        $input = $this->timetableVariationInput($subjectRows, $subjectMappings, $courseGroups, $settings);

        return $this->validTimetableCountForAvailabilityInput(
            $input,
            $settings,
            $evaluationCriteria,
            $selectedQualityCriterionKeys,
            $availabilityOnly,
        );
    }

    /**
     * @param  array{selected_courses: list<array<string, mixed>>, course_options: list<list<array<string, mixed>>>, additional_courses: list<array<string, mixed>>, additional_course_options: list<list<array<string, mixed>>>, has_missing_options: bool}  $input
     * @param  array<string, mixed>  $settings
     * @param  list<array<string, mixed>>  $evaluationCriteria
     * @param  list<string>  $selectedQualityCriterionKeys
     */
    private function validTimetableCountForAvailabilityInput(
        array $input,
        array $settings,
        array $evaluationCriteria,
        array $selectedQualityCriterionKeys,
        bool $availabilityOnly = false,
    ): int {

        if ($this->timetableVariationLimitExceeded($input['selected_courses'], $input['course_options'], $input['has_missing_options'])) {
            throw ValidationException::withMessages([
                'selected_course_keys' => sprintf(
                    'Diese Auswahl erzeugt zu viele Stundenplan-Variationen. Bitte weniger Kurse auswählen oder die Auswahl einschränken. Maximum: %s Variationen.',
                    number_format(self::MAX_BACKEND_TIMETABLE_VARIATIONS, 0, ',', '.'),
                ),
            ]);
        }

        $selectedQualityCriterionKeys = $this->selectedQualityCriterionKeys(
            $selectedQualityCriterionKeys,
            $evaluationCriteria,
        );

        if ($availabilityOnly && $selectedQualityCriterionKeys !== []) {
            $qualityResult = $this->qualityResultForSelectedTimetableType(
                $input['course_options'],
                $input['additional_course_options'],
                $settings,
                $evaluationCriteria,
            );
            $selectedQualitySubset = $this->selectedQualityCriteriaSubset(
                $qualityResult['combination_counts'],
                $qualityResult['summary'],
                $evaluationCriteria,
                $selectedQualityCriterionKeys,
            );

            return $selectedQualitySubset['total'] > 0 ? 1 : 0;
        }

        if ($availabilityOnly) {
            return $this->validTimetableExistsForAvailability(
                $input['course_options'],
                $input['additional_course_options'],
                $settings,
            ) ? 1 : 0;
        }

        if ($selectedQualityCriterionKeys !== []) {
            $qualityResult = $this->qualityResultForSelectedTimetableType(
                $input['course_options'],
                $input['additional_course_options'],
                $settings,
                $evaluationCriteria,
            );
            $selectedQualitySubset = $this->selectedQualityCriteriaSubset(
                $qualityResult['combination_counts'],
                $qualityResult['summary'],
                $evaluationCriteria,
                $selectedQualityCriterionKeys,
            );

            return $selectedQualitySubset['total'];
        }

        $counts = $this->timetableVariationCounts(
            $input['selected_courses'],
            $input['course_options'],
            $input['has_missing_options'],
            $input['additional_course_options'],
            $this->selectedBackendTimetableType($settings['selected_timetable_type'] ?? 'full_green') ?? 'full_green',
            ($settings['selected_additional_courses_required'] ?? false) === true,
        );

        $validTimetableCount = $counts['full_green_timetable_count'] + $counts['green_timetable_count'];

        return $validTimetableCount;
    }

    /**
     * @param  list<list<array<string, mixed>>>  $courseOptions
     * @param  list<list<array<string, mixed>>>  $additionalCourseOptions
     * @param  array<string, mixed>  $settings
     */
    private function validTimetableExistsForAvailability(
        array $courseOptions,
        array $additionalCourseOptions,
        array $settings,
        ?string $selectedType = null,
    ): bool {
        if ($courseOptions === [] || collect($courseOptions)->contains(fn (array $options): bool => $options === [])) {
            return false;
        }

        $courseOptions = $this->availabilityCourseOptions($courseOptions);
        $additionalCourseOptions = $this->availabilityCourseOptions($additionalCourseOptions);
        $additionalCoursesRequired = ($settings['selected_additional_courses_required'] ?? false) === true
            && $this->selectedAdditionalCoursesAvailable($additionalCourseOptions);
        $memo = [];

        if ($additionalCoursesRequired) {
            return $this->validTimetableWithAdditionalCoursesExists(
                $courseOptions,
                $additionalCourseOptions,
                memo: $memo,
                selectedType: $selectedType,
            );
        }

        return $this->validTimetableExists($courseOptions, memo: $memo, selectedType: $selectedType);
    }

    /**
     * @param  list<list<array<string, mixed>>>  $courseOptions
     * @return list<list<array<string, mixed>>>
     */
    private function availabilityCourseOptions(array $courseOptions): array
    {
        return collect($courseOptions)
            ->sortBy(fn (array $options): int => count($options))
            ->values()
            ->all();
    }

    /**
     * @param  list<list<array<string, mixed>>>  $courseOptions
     * @param  array<string, bool>  $memo
     * @param  array{weekly: array<string, true>, dated: array<string, true>, dated_weekly: array<string, true>, has_overlap: bool}|null  $usedAllSummary
     * @param  array{weekly: array<string, true>, dated: array<string, true>, dated_weekly: array<string, true>, has_overlap: bool}|null  $usedRegularDateSummary
     * @param  array{weekly: array<string, true>, dated: array<string, true>, dated_weekly: array<string, true>, has_overlap: bool}|null  $usedRegularWeeklySlotSummary
     */
    private function validTimetableExists(
        array $courseOptions,
        int $courseIndex = 0,
        ?array $usedAllSummary = null,
        ?array $usedRegularDateSummary = null,
        ?array $usedRegularWeeklySlotSummary = null,
        bool $isFullGreenCandidate = true,
        bool $hasRegularConflict = false,
        array &$memo = [],
        ?string $selectedType = null,
    ): bool {
        if ($hasRegularConflict && $selectedType !== 'conflict') {
            return false;
        }

        if ($courseIndex >= count($courseOptions)) {
            if ($selectedType === null) {
                return ! $hasRegularConflict;
            }

            $combinationType = $hasRegularConflict
                ? 'conflict'
                : ($isFullGreenCandidate ? 'full_green' : 'green');

            return $combinationType === $selectedType;
        }

        $usedAllSummary ??= $this->emptyDateKeySummary();
        $usedRegularDateSummary ??= $this->emptyDateKeySummary();
        $usedRegularWeeklySlotSummary ??= $this->emptyDateKeySummary();
        $memoKey = $this->availabilityStateKey(
            $courseIndex,
            $usedAllSummary,
            $usedRegularDateSummary,
            $usedRegularWeeklySlotSummary,
            $isFullGreenCandidate,
            $hasRegularConflict,
            $selectedType,
        );

        if (array_key_exists($memoKey, $memo)) {
            return $memo[$memoKey];
        }

        foreach ($courseOptions[$courseIndex] as $option) {
            $nextState = $this->nextTimetableTypeState(
                $option,
                $usedAllSummary,
                $usedRegularDateSummary,
                $usedRegularWeeklySlotSummary,
                $isFullGreenCandidate,
                $hasRegularConflict,
            );

            if ($this->validTimetableExists(
                $courseOptions,
                $courseIndex + 1,
                $nextState['used_all_summary'],
                $nextState['used_regular_date_summary'],
                $nextState['used_regular_weekly_slot_summary'],
                $nextState['is_full_green_candidate'],
                $nextState['has_regular_conflict'],
                $memo,
                $selectedType,
            )) {
                return $memo[$memoKey] = true;
            }
        }

        return $memo[$memoKey] = false;
    }

    /**
     * @param  list<list<array<string, mixed>>>  $courseOptions
     * @param  list<list<array<string, mixed>>>  $additionalCourseOptions
     * @param  array<string, bool>  $memo
     * @param  array{weekly: array<string, true>, dated: array<string, true>, dated_weekly: array<string, true>, has_overlap: bool}|null  $usedAllSummary
     * @param  array{weekly: array<string, true>, dated: array<string, true>, dated_weekly: array<string, true>, has_overlap: bool}|null  $usedRegularDateSummary
     * @param  array{weekly: array<string, true>, dated: array<string, true>, dated_weekly: array<string, true>, has_overlap: bool}|null  $usedRegularWeeklySlotSummary
     */
    private function validTimetableWithAdditionalCoursesExists(
        array $courseOptions,
        array $additionalCourseOptions,
        int $courseIndex = 0,
        ?array $usedAllSummary = null,
        ?array $usedRegularDateSummary = null,
        ?array $usedRegularWeeklySlotSummary = null,
        bool $isFullGreenCandidate = true,
        bool $hasRegularConflict = false,
        array &$memo = [],
        ?string $selectedType = null,
    ): bool {
        if ($hasRegularConflict && $selectedType !== 'conflict') {
            return false;
        }

        if ($courseIndex >= count($courseOptions)) {
            if ($selectedType !== null) {
                $combinationType = $hasRegularConflict
                    ? 'conflict'
                    : ($isFullGreenCandidate ? 'full_green' : 'green');

                if ($combinationType !== $selectedType) {
                    return false;
                }
            }

            return count($this->additionalOptionsForTimetable(
                $additionalCourseOptions,
                $usedAllSummary ?? $this->emptyDateKeySummary(),
            )) === count($additionalCourseOptions);
        }

        $usedAllSummary ??= $this->emptyDateKeySummary();
        $usedRegularDateSummary ??= $this->emptyDateKeySummary();
        $usedRegularWeeklySlotSummary ??= $this->emptyDateKeySummary();
        $memoKey = $this->availabilityStateKey(
            $courseIndex,
            $usedAllSummary,
            $usedRegularDateSummary,
            $usedRegularWeeklySlotSummary,
            $isFullGreenCandidate,
            $hasRegularConflict,
            $selectedType,
        );

        if (array_key_exists($memoKey, $memo)) {
            return $memo[$memoKey];
        }

        if (count($this->additionalOptionsForTimetable($additionalCourseOptions, $usedAllSummary)) !== count($additionalCourseOptions)) {
            return $memo[$memoKey] = false;
        }

        foreach ($courseOptions[$courseIndex] as $option) {
            $nextState = $this->nextTimetableTypeState(
                $option,
                $usedAllSummary,
                $usedRegularDateSummary,
                $usedRegularWeeklySlotSummary,
                $isFullGreenCandidate,
                $hasRegularConflict,
            );

            if ($this->validTimetableWithAdditionalCoursesExists(
                $courseOptions,
                $additionalCourseOptions,
                $courseIndex + 1,
                $nextState['used_all_summary'],
                $nextState['used_regular_date_summary'],
                $nextState['used_regular_weekly_slot_summary'],
                $nextState['is_full_green_candidate'],
                $nextState['has_regular_conflict'],
                $memo,
                $selectedType,
            )) {
                return $memo[$memoKey] = true;
            }
        }

        return $memo[$memoKey] = false;
    }

    /**
     * @param  array{weekly: array<string, true>, dated: array<string, true>, dated_weekly: array<string, true>, has_overlap: bool}  $usedAllSummary
     * @param  array{weekly: array<string, true>, dated: array<string, true>, dated_weekly: array<string, true>, has_overlap: bool}  $usedRegularDateSummary
     * @param  array{weekly: array<string, true>, dated: array<string, true>, dated_weekly: array<string, true>, has_overlap: bool}  $usedRegularWeeklySlotSummary
     */
    private function availabilityStateKey(
        int $courseIndex,
        array $usedAllSummary,
        array $usedRegularDateSummary,
        array $usedRegularWeeklySlotSummary,
        bool $isFullGreenCandidate,
        bool $hasRegularConflict,
        ?string $selectedType,
    ): string {
        return implode(';', [
            $courseIndex,
            (int) $isFullGreenCandidate,
            (int) $hasRegularConflict,
            $selectedType ?? '*',
            $this->dateSummaryStateKey($usedAllSummary),
            $this->dateSummaryStateKey($usedRegularDateSummary),
            $this->dateSummaryStateKey($usedRegularWeeklySlotSummary),
        ]);
    }

    /**
     * @param  array{weekly: array<string, true>, dated: array<string, true>, dated_weekly: array<string, true>, has_overlap: bool}  $summary
     */
    private function dateSummaryStateKey(array $summary): string
    {
        $parts = ['overlap:'.(int) ($summary['has_overlap'] ?? false)];

        foreach (['weekly', 'dated', 'dated_weekly'] as $key) {
            $values = array_keys($summary[$key] ?? []);
            sort($values, SORT_STRING);
            $parts[] = $key.':'.implode(',', $values);
        }

        return implode('|', $parts);
    }

    /**
     * @param  list<array<string, mixed>>  $subjectRows
     * @param  list<array<string, mixed>>  $subjectMappings
     * @param  list<array<string, mixed>>  $courseGroups
     * @param  array<string, mixed>  $settings
     * @param  list<array<string, mixed>>  $evaluationCriteria
     * @param  list<string>  $selectedQualityCriterionKeys
     * @return array{quality_counters: array<int, array<string, mixed>>, all_quality_criteria_count: int, selected_quality_criteria_count: int}
     */
    public function qualityCountersForTimetableVariations(
        array $subjectRows,
        array $subjectMappings,
        array $courseGroups,
        array $settings,
        array $evaluationCriteria,
        array $selectedQualityCriterionKeys = [],
    ): array {
        $this->resetRuntimeCache();

        if ($evaluationCriteria === []) {
            return [
                'quality_counters' => [],
                'all_quality_criteria_count' => 0,
                'selected_quality_criteria_count' => 0,
            ];
        }

        $input = $this->timetableVariationInput($subjectRows, $subjectMappings, $courseGroups, $settings);

        if ($this->timetableVariationLimitExceeded($input['selected_courses'], $input['course_options'], $input['has_missing_options'])) {
            throw ValidationException::withMessages([
                'selected_course_keys' => sprintf(
                    'Diese Auswahl erzeugt zu viele Stundenplan-Variationen. Bitte weniger Kurse auswählen oder die Auswahl einschränken. Maximum: %s Variationen.',
                    number_format(self::MAX_BACKEND_TIMETABLE_VARIATIONS, 0, ',', '.'),
                ),
            ]);
        }

        $qualityResult = $this->qualityResultForSelectedTimetableType(
            $input['course_options'],
            $input['additional_course_options'],
            $settings,
            $evaluationCriteria,
        );
        $selectedQualityCriterionKeys = $this->selectedQualityCriterionKeys(
            $selectedQualityCriterionKeys,
            $evaluationCriteria,
        );
        $selectedQualitySubset = $this->selectedQualityCriteriaSubset(
            $qualityResult['combination_counts'],
            $qualityResult['summary'],
            $evaluationCriteria,
            $selectedQualityCriterionKeys,
        );
        $selectedQualityCriteriaCount = $selectedQualityCriterionKeys === []
            ? 0
            : $selectedQualitySubset['total'];

        return [
            'quality_counters' => $this->qualityCountersFromSummary(
                $qualityResult['summary'],
                null,
                $qualityResult['combination_counts'],
                $evaluationCriteria,
                $selectedQualityCriterionKeys,
                $selectedQualitySubset,
            ),
            'all_quality_criteria_count' => $this->allQualityCriteriaCount(
                $qualityResult['combination_counts'],
                $qualityResult['summary'],
            ),
            'selected_quality_criteria_count' => $selectedQualityCriteriaCount,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $subjectRows
     * @param  list<array<string, mixed>>  $subjectMappings
     * @param  list<array<string, mixed>>  $courseGroups
     * @param  array<string, mixed>  $settings
     * @return array{timetable_variation_count: int, full_green_timetable_count: int, green_timetable_count: int, red_timetable_count: int, selected_course_count: int}
     */
    public function countTimetableVariations(
        array $subjectRows,
        array $subjectMappings,
        array $courseGroups,
        array $settings,
    ): array {
        $result = $this->calculateTimetableVariations($subjectRows, $subjectMappings, $courseGroups, $settings);

        return [
            'timetable_variation_count' => $result['timetable_variation_count'],
            'full_green_timetable_count' => $result['full_green_timetable_count'],
            'green_timetable_count' => $result['green_timetable_count'],
            'red_timetable_count' => $result['red_timetable_count'],
            'selected_course_count' => $result['selected_course_count'],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $subjectRows
     * @param  list<array<string, mixed>>  $subjectMappings
     * @param  list<array<string, mixed>>  $courseGroups
     * @param  array<string, mixed>  $settings
     * @param  list<array<string, mixed>>  $evaluationCriteria
     * @param  list<string>  $selectedQualityCriterionKeys
     * @return array{timetable_variation_count: int, full_green_timetable_count: int, green_timetable_count: int, red_timetable_count: int, selected_course_count: int, selected_additional_course_count: int, additional_course_timetable_count: int, no_saturday_timetable_count: int, selected_timetable: ?array<string, mixed>, quality_counters: list<array<string, mixed>>, all_quality_criteria_count: int, selected_quality_criteria_count: int}
     */
    public function calculateTimetableVariations(
        array $subjectRows,
        array $subjectMappings,
        array $courseGroups,
        array $settings,
        array $evaluationCriteria = [],
        array $selectedQualityCriterionKeys = [],
    ): array {
        $this->resetRuntimeCache();

        return $this->calculateTimetableVariationsFromBase(
            $this->timetableVariationBase(
                $subjectRows,
                $subjectMappings,
                $courseGroups,
                $settings,
                $evaluationCriteria,
                $selectedQualityCriterionKeys,
            ),
            $settings,
            $evaluationCriteria,
        );
    }

    /**
     * @param  list<array<string, mixed>>  $subjectRows
     * @param  list<array<string, mixed>>  $subjectMappings
     * @param  list<array<string, mixed>>  $courseGroups
     * @param  array<string, mixed>  $settings
     * @param  list<array<string, mixed>>  $evaluationCriteria
     * @param  list<string>  $selectedQualityCriterionKeys
     * @param  array<string, list<string>>  $requiredCourseGroupsByModule
     * @return array<string, mixed>
     */
    public function calculateAllTimetableVariations(
        array $subjectRows,
        array $subjectMappings,
        array $courseGroups,
        array $settings,
        int $maximumTimetables,
        array $evaluationCriteria = [],
        array $selectedQualityCriterionKeys = [],
        array $requiredCourseGroupsByModule = [],
    ): array {
        $this->resetRuntimeCache();
        $settings['require_complete_course_group_options'] = true;

        if (
            ($settings['selected_additional_courses_required'] ?? false) === true
            || $this->stringList($settings['selected_additional_course_keys'] ?? []) !== []
        ) {
            throw ValidationException::withMessages([
                'selected_additional_course_keys' => 'Zusatzkurse werden bei der vollständigen V3-Ausgabe als reguläre Module übergeben.',
            ]);
        }

        $base = $this->timetableVariationBase(
            $subjectRows,
            $subjectMappings,
            $courseGroups,
            $settings,
            $evaluationCriteria,
            $selectedQualityCriterionKeys,
            maximumMaterializedTimetables: max(1, $maximumTimetables),
            requireAllSelectedCourses: true,
            requiredCourseGroupsByModule: $requiredCourseGroupsByModule,
        );
        $timetableCount = (int) $base['counts']['timetable_variation_count'];

        if ($timetableCount > max(1, $maximumTimetables)) {
            throw ValidationException::withMessages([
                'modules' => sprintf(
                    'Diese Auswahl erzeugt %s Stundenpläne. Für eine vollständige Ausgabe sind höchstens %s Stundenpläne erlaubt.',
                    number_format($timetableCount, 0, ',', '.'),
                    number_format(max(1, $maximumTimetables), 0, ',', '.'),
                ),
            ]);
        }

        return [
            ...$this->calculateTimetableVariationsFromBase(
                $base,
                $settings,
                $evaluationCriteria,
                selectedTimetable: null,
                selectedTimetableResolved: true,
            ),
            'timetables' => $this->allTimetables($base['course_options']),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $subjectRows
     * @param  list<array<string, mixed>>  $subjectMappings
     * @param  list<array<string, mixed>>  $courseGroups
     * @param  array<string, mixed>  $settings
     * @param  list<array<string, mixed>>  $evaluationCriteria
     * @param  list<string>  $selectedQualityCriterionKeys
     * @param  array<string, list<string>>  $requiredCourseGroupsByModule
     * @param  (callable(int, int, string, int): void)|null  $progressCallback
     * @return array<string, mixed>
     */
    public function calculateAllPossibleTimetableVariations(
        array $subjectRows,
        array $subjectMappings,
        array $courseGroups,
        array $settings,
        int $maximumTimetables,
        array $evaluationCriteria = [],
        array $selectedQualityCriterionKeys = [],
        array $requiredCourseGroupsByModule = [],
        bool $includeOneCourseRemovalCountsWhenNoPossible = false,
        bool $calculateNoSaturdayTimetableCount = true,
        ?callable $progressCallback = null,
    ): array {
        $this->resetRuntimeCache();
        $settings['require_complete_course_group_options'] = true;

        if (
            ($settings['selected_additional_courses_required'] ?? false) === true
            || $this->stringList($settings['selected_additional_course_keys'] ?? []) !== []
        ) {
            throw ValidationException::withMessages([
                'selected_additional_course_keys' => 'Zusatzkurse werden bei der vollständigen V3-Ausgabe als reguläre Module übergeben.',
            ]);
        }

        $base = $this->timetableVariationBase(
            $subjectRows,
            $subjectMappings,
            $courseGroups,
            $settings,
            $evaluationCriteria,
            $selectedQualityCriterionKeys,
            requireAllSelectedCourses: true,
            requiredCourseGroupsByModule: $requiredCourseGroupsByModule,
            calculateNoSaturdayTimetableCount: $calculateNoSaturdayTimetableCount,
            progressCallback: $progressCallback,
        );
        $maximumTimetables = max(1, $maximumTimetables);
        $combinationCount = (int) $base['counts']['timetable_variation_count'];
        $possibleTimetableCount = (int) $base['counts']['full_green_timetable_count']
            + (int) $base['counts']['green_timetable_count'];
        $materializationPhase = $includeOneCourseRemovalCountsWhenNoPossible && $possibleTimetableCount === 0
            ? 'analyzing_solutions'
            : 'materializing';
        if ($progressCallback !== null) {
            $progressCallback(
                self::V3_MATERIALIZING_PROGRESS_PERCENT,
                $combinationCount,
                $materializationPhase,
                $combinationCount,
            );
        }
        $timetables = $this->allPossibleTimetables(
            $base['course_options'],
            $maximumTimetables,
            (int) $base['counts']['full_green_timetable_count'],
        );
        $oneCourseRemovalCounts = $includeOneCourseRemovalCountsWhenNoPossible && $possibleTimetableCount === 0
            ? $this->oneCourseRemovalCounts($base)
            : [];

        $result = [
            ...$this->calculateTimetableVariationsFromBase(
                $base,
                $settings,
                $evaluationCriteria,
                selectedTimetable: null,
                selectedTimetableResolved: true,
            ),
            'timetables' => $timetables,
        ];

        if ($includeOneCourseRemovalCountsWhenNoPossible) {
            $result['one_course_removal_counts'] = $oneCourseRemovalCounts;
        }

        if ($progressCallback !== null) {
            $progressCallback(
                self::V3_COMPACTING_PROGRESS_PERCENT,
                $combinationCount,
                'compacting',
                $combinationCount,
            );
        }

        return $result;
    }

    /**
     * @param  array{selected_courses: list<array<string, mixed>>, course_options: list<list<array<string, mixed>>>}  $base
     * @return list<array{removed_course_code: string, possible_timetable_count: ?int, full_green_timetable_count: ?int, green_timetable_count: ?int, status: 'calculated'|'combination_limit_exceeded'}>
     */
    private function oneCourseRemovalCounts(array $base): array
    {
        return collect($base['selected_courses'])
            ->values()
            ->map(function (array $removedCourse, int $removedCourseIndex) use ($base): array {
                $remainingCourses = collect($base['selected_courses'])
                    ->except($removedCourseIndex)
                    ->values()
                    ->all();
                $remainingCourseOptions = collect($base['course_options'])
                    ->except($removedCourseIndex)
                    ->values()
                    ->all();
                $hasMissingOptions = collect($remainingCourseOptions)
                    ->contains(fn (array $options): bool => $options === []);

                if ($this->timetableVariationLimitExceeded(
                    $remainingCourses,
                    $remainingCourseOptions,
                    $hasMissingOptions,
                )) {
                    return [
                        'removed_course_code' => (string) ($removedCourse['code'] ?? ''),
                        'possible_timetable_count' => null,
                        'full_green_timetable_count' => null,
                        'green_timetable_count' => null,
                        'status' => 'combination_limit_exceeded',
                    ];
                }

                $typeCounts = $remainingCourses === [] || $hasMissingOptions
                    ? ['full_green' => 0, 'green' => 0]
                    : $this->timetableTypeVariationCounts($remainingCourseOptions);
                $fullGreenTimetableCount = (int) $typeCounts['full_green'];
                $greenTimetableCount = (int) $typeCounts['green'];

                return [
                    'removed_course_code' => (string) ($removedCourse['code'] ?? ''),
                    'possible_timetable_count' => $fullGreenTimetableCount + $greenTimetableCount,
                    'full_green_timetable_count' => $fullGreenTimetableCount,
                    'green_timetable_count' => $greenTimetableCount,
                    'status' => 'calculated',
                ];
            })
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $subjectRows
     * @param  list<array<string, mixed>>  $subjectMappings
     * @param  list<array<string, mixed>>  $courseGroups
     * @param  array<string, mixed>  $settings
     * @param  list<array<string, mixed>>  $evaluationCriteria
     * @param  list<string>  $selectedQualityCriterionKeys
     * @return array{timetable_variation_count: int, full_green_timetable_count: int, green_timetable_count: int, red_timetable_count: int, selected_course_count: int, selected_additional_course_count: int, additional_course_timetable_count: int, no_saturday_timetable_count: int, selected_timetable: ?array<string, mixed>, quality_counters: list<array<string, mixed>>, all_quality_criteria_count: int, selected_quality_criteria_count: int}
     */
    public function calculateCachedTimetableVariationsForUser(
        User $authUser,
        array $subjectRows,
        array $subjectMappings,
        array $courseGroups,
        array $settings,
        array $evaluationCriteria = [],
        array $selectedQualityCriterionKeys = [],
    ): array {
        $this->resetRuntimeCache();

        $baseCacheKey = $this->timetableVariationBaseCacheKey(
            $authUser,
            $subjectRows,
            $subjectMappings,
            $courseGroups,
            $settings,
            $evaluationCriteria,
            $selectedQualityCriterionKeys,
        );

        $base = $this->rememberedTimetableVariationBase(
            $baseCacheKey,
            $subjectRows,
            $subjectMappings,
            $courseGroups,
            $settings,
            $evaluationCriteria,
            $selectedQualityCriterionKeys,
        );
        $selectedTimetable = $this->rememberedSelectedTimetable(
            $baseCacheKey,
            $base,
            $settings,
            $evaluationCriteria,
        );

        return $this->calculateTimetableVariationsFromBase(
            $base,
            $settings,
            $evaluationCriteria,
            $selectedTimetable,
            true,
        );
    }

    /**
     * @param  list<array<string, mixed>>  $subjectRows
     * @param  list<array<string, mixed>>  $subjectMappings
     * @param  list<array<string, mixed>>  $courseGroups
     * @param  array<string, mixed>  $settings
     * @param  list<array<string, mixed>>  $evaluationCriteria
     * @param  list<string>  $selectedQualityCriterionKeys
     * @return array<string, mixed>
     */
    private function rememberedTimetableVariationBase(
        string $baseCacheKey,
        array $subjectRows,
        array $subjectMappings,
        array $courseGroups,
        array $settings,
        array $evaluationCriteria,
        array $selectedQualityCriterionKeys,
    ): array {
        return Cache::remember(
            $baseCacheKey,
            now()->addMinutes(self::TIMETABLE_VARIATION_CACHE_TTL_MINUTES),
            fn (): array => $this->timetableVariationBase(
                $subjectRows,
                $subjectMappings,
                $courseGroups,
                $settings,
                $evaluationCriteria,
                $selectedQualityCriterionKeys,
            ),
        );
    }

    /**
     * @param  array<string, mixed>  $base
     * @param  array<string, mixed>  $settings
     * @param  list<array<string, mixed>>  $evaluationCriteria
     * @return array<string, mixed>|null
     */
    private function rememberedSelectedTimetable(
        string $baseCacheKey,
        array $base,
        array $settings,
        array $evaluationCriteria,
    ): ?array {
        return Cache::remember(
            $this->selectedTimetableCacheKey($baseCacheKey, $settings),
            now()->addMinutes(self::TIMETABLE_VARIATION_CACHE_TTL_MINUTES),
            fn (): ?array => $this->selectedTimetableFromBase($base, $settings, $evaluationCriteria),
        );
    }

    /**
     * @param  list<array<string, mixed>>  $subjectRows
     * @param  list<array<string, mixed>>  $subjectMappings
     * @param  list<array<string, mixed>>  $courseGroups
     * @param  array<string, mixed>  $settings
     * @param  list<array<string, mixed>>  $evaluationCriteria
     * @param  list<string>  $selectedQualityCriterionKeys
     * @param  array<string, list<string>>  $requiredCourseGroupsByModule
     * @param  (callable(int, int, string, int): void)|null  $progressCallback
     * @return array{course_options: list<list<array<string, mixed>>>, additional_course_options: list<list<array<string, mixed>>>, selected_additional_course_count: int, counts: array{timetable_variation_count: int, full_green_timetable_count: int, green_timetable_count: int, red_timetable_count: int, selected_course_count: int, additional_course_timetable_count: int, no_saturday_timetable_count: int}, quality_summary: array<string, mixed>, quality_combination_counts: array<string, int>, selected_quality_criterion_keys: list<string>, selected_quality_subset: array{steps: array<string, array<string, mixed>>, counts: array<string, int>, total: int}, all_quality_criteria_count: int, selected_quality_criteria_count: int}
     */
    private function timetableVariationBase(
        array $subjectRows,
        array $subjectMappings,
        array $courseGroups,
        array $settings,
        array $evaluationCriteria = [],
        array $selectedQualityCriterionKeys = [],
        ?int $maximumMaterializedTimetables = null,
        bool $requireAllSelectedCourses = false,
        array $requiredCourseGroupsByModule = [],
        bool $calculateNoSaturdayTimetableCount = true,
        ?callable $progressCallback = null,
    ): array {
        $input = $this->timetableVariationInput($subjectRows, $subjectMappings, $courseGroups, $settings);

        if ($requireAllSelectedCourses) {
            $this->ensureEverySelectedCourseWasResolved($settings, $input['selected_courses']);
            $this->ensureEveryRequiredCourseGroupWasResolvedForItsModule(
                $requiredCourseGroupsByModule,
                $courseGroups,
                $input['selected_courses'],
                $subjectMappings,
            );
        }

        if ($this->timetableVariationLimitExceeded($input['selected_courses'], $input['course_options'], $input['has_missing_options'])) {
            throw ValidationException::withMessages([
                'selected_course_keys' => sprintf(
                    'Diese Auswahl erzeugt zu viele Stundenplan-Variationen. Bitte weniger Kurse auswählen oder die Auswahl einschränken. Maximum: %s Variationen.',
                    number_format(self::MAX_BACKEND_TIMETABLE_VARIATIONS, 0, ',', '.'),
                ),
            ]);
        }

        if (
            $maximumMaterializedTimetables !== null
            && $this->timetableVariationLimitExceeded(
                $input['selected_courses'],
                $input['course_options'],
                $input['has_missing_options'],
                $maximumMaterializedTimetables,
            )
        ) {
            throw ValidationException::withMessages([
                'modules' => sprintf(
                    'Diese Auswahl erzeugt zu viele Stundenpläne für eine vollständige Ausgabe. Maximum: %s Stundenpläne.',
                    number_format($maximumMaterializedTimetables, 0, ',', '.'),
                ),
            ]);
        }

        $combinationCount = $input['selected_courses'] === [] || $input['has_missing_options']
            ? 0
            : $this->totalVariationCount($input['course_options']);
        if ($progressCallback !== null) {
            $progressCallback(0, $combinationCount, 'checking', 0);
        }
        $checkedCombinationCount = 0;
        $nextProgressPercent = 5;
        $combinationCheckedCallback = $progressCallback === null
            ? null
            : function () use (
                $combinationCount,
                $progressCallback,
                &$checkedCombinationCount,
                &$nextProgressPercent,
            ): void {
                $checkedCombinationCount++;

                while (
                    $nextProgressPercent <= self::V3_CHECKING_PROGRESS_PERCENT
                    && ($checkedCombinationCount * self::V3_CHECKING_PROGRESS_PERCENT)
                        >= ($combinationCount * $nextProgressPercent)
                ) {
                    $progressCallback(
                        $nextProgressPercent,
                        $combinationCount,
                        'checking',
                        $checkedCombinationCount,
                    );
                    $nextProgressPercent += 5;
                }
            };
        $counts = $this->timetableVariationCounts(
            $input['selected_courses'],
            $input['course_options'],
            $input['has_missing_options'],
            $input['additional_course_options'],
            $this->selectedBackendTimetableType($settings['selected_timetable_type'] ?? 'full_green') ?? 'full_green',
            ($settings['selected_additional_courses_required'] ?? false) === true,
            $combinationCheckedCallback,
            $calculateNoSaturdayTimetableCount,
        );
        $qualityResult = $this->qualityResultForSelectedTimetableType(
            $input['course_options'],
            $input['additional_course_options'],
            $settings,
            $evaluationCriteria,
        );
        $selectedQualityCriterionKeys = $this->selectedQualityCriterionKeys(
            $selectedQualityCriterionKeys,
            $evaluationCriteria,
        );
        $selectedQualitySubset = $this->selectedQualityCriteriaSubset(
            $qualityResult['combination_counts'],
            $qualityResult['summary'],
            $evaluationCriteria,
            $selectedQualityCriterionKeys,
        );

        return [
            'selected_courses' => $input['selected_courses'],
            'course_options' => $input['course_options'],
            'additional_courses' => $input['additional_courses'],
            'additional_course_options' => $input['additional_course_options'],
            'selected_additional_course_count' => count($input['additional_courses']),
            'has_missing_options' => $input['has_missing_options'],
            'problem_courses' => $input['problem_courses'],
            'counts' => $counts,
            'quality_summary' => $qualityResult['summary'],
            'quality_combination_counts' => $qualityResult['combination_counts'],
            'selected_quality_criterion_keys' => $selectedQualityCriterionKeys,
            'selected_quality_subset' => $selectedQualitySubset,
            'all_quality_criteria_count' => $this->allQualityCriteriaCount(
                $qualityResult['combination_counts'],
                $qualityResult['summary'],
            ),
            'selected_quality_criteria_count' => $selectedQualityCriterionKeys === []
                ? 0
                : $selectedQualitySubset['total'],
        ];
    }

    /**
     * @param  array<string, mixed>  $base
     * @return array{selected_courses: list<array<string, mixed>>, course_options: list<list<array<string, mixed>>>, additional_courses: list<array<string, mixed>>, additional_course_options: list<list<array<string, mixed>>>, has_missing_options: bool, problem_courses: list<array<string, mixed>>}
     */
    private function timetableVariationInputFromBase(array $base): array
    {
        return [
            'selected_courses' => $base['selected_courses'],
            'course_options' => $base['course_options'],
            'additional_courses' => $base['additional_courses'],
            'additional_course_options' => $base['additional_course_options'],
            'has_missing_options' => $base['has_missing_options'],
            'problem_courses' => $base['problem_courses'],
        ];
    }

    /**
     * @param  array{course_options: list<list<array<string, mixed>>>, additional_course_options: list<list<array<string, mixed>>>, selected_additional_course_count: int, counts: array{timetable_variation_count: int, full_green_timetable_count: int, green_timetable_count: int, red_timetable_count: int, selected_course_count: int, additional_course_timetable_count: int, no_saturday_timetable_count: int}, quality_summary: array<string, mixed>, quality_combination_counts: array<string, int>, selected_quality_criterion_keys: list<string>, selected_quality_subset: array{steps: array<string, array<string, mixed>>, counts: array<string, int>, total: int}, all_quality_criteria_count: int, selected_quality_criteria_count: int}  $base
     * @param  array<string, mixed>  $settings
     * @param  list<array<string, mixed>>  $evaluationCriteria
     * @return array{timetable_variation_count: int, full_green_timetable_count: int, green_timetable_count: int, red_timetable_count: int, selected_course_count: int, selected_additional_course_count: int, additional_course_timetable_count: int, no_saturday_timetable_count: int, selected_timetable: ?array<string, mixed>, quality_counters: list<array<string, mixed>>, all_quality_criteria_count: int, selected_quality_criteria_count: int}
     */
    private function calculateTimetableVariationsFromBase(
        array $base,
        array $settings,
        array $evaluationCriteria = [],
        ?array $selectedTimetable = null,
        bool $selectedTimetableResolved = false,
    ): array {
        if (! $selectedTimetableResolved) {
            $selectedTimetable = $this->selectedTimetableFromBase($base, $settings, $evaluationCriteria);
        }

        return [
            ...$base['counts'],
            'selected_additional_course_count' => $base['selected_additional_course_count'],
            'selected_timetable' => $selectedTimetable,
            'problem_courses' => $base['problem_courses'],
            'quality_counters' => $this->qualityCountersFromSummary(
                $base['quality_summary'],
                $selectedTimetable['metrics'] ?? null,
                $base['quality_combination_counts'],
                $evaluationCriteria,
                $base['selected_quality_criterion_keys'],
                $base['selected_quality_subset'],
            ),
            'all_quality_criteria_count' => $base['all_quality_criteria_count'],
            'selected_quality_criteria_count' => $base['selected_quality_criteria_count'],
        ];
    }

    /**
     * @param  array{course_options: list<list<array<string, mixed>>>, additional_course_options: list<list<array<string, mixed>>>, selected_additional_course_count: int, counts: array{timetable_variation_count: int, full_green_timetable_count: int, green_timetable_count: int, red_timetable_count: int, selected_course_count: int, additional_course_timetable_count: int, no_saturday_timetable_count: int}, selected_quality_criterion_keys: list<string>, selected_quality_subset: array{steps: array<string, array<string, mixed>>, counts: array<string, int>, total: int}}  $base
     * @param  array<string, mixed>  $settings
     * @param  list<array<string, mixed>>  $evaluationCriteria
     * @return ?array<string, mixed>
     */
    private function selectedTimetableFromBase(
        array $base,
        array $settings,
        array $evaluationCriteria = [],
    ): ?array {
        return $this->selectedTimetable(
            $base['course_options'],
            $base['additional_course_options'],
            $settings,
            $base['counts'],
            $evaluationCriteria,
            $base['selected_quality_criterion_keys'],
            $base['selected_quality_subset'],
        );
    }

    /**
     * @param  list<array<string, mixed>>  $subjectRows
     * @param  list<array<string, mixed>>  $subjectMappings
     * @param  list<array<string, mixed>>  $courseGroups
     * @param  array<string, mixed>  $settings
     * @param  list<array<string, mixed>>  $evaluationCriteria
     * @param  list<string>  $selectedQualityCriterionKeys
     */
    private function timetableVariationBaseCacheKey(
        User $authUser,
        array $subjectRows,
        array $subjectMappings,
        array $courseGroups,
        array $settings,
        array $evaluationCriteria,
        array $selectedQualityCriterionKeys,
    ): string {
        $cacheSettings = $settings;
        unset(
            $cacheSettings['availability_only'],
            $cacheSettings['candidate_courses'],
            $cacheSettings['include_quality_counters'],
            $cacheSettings['selected_timetable_number'],
        );

        return 'students-timetables:timetable-v2:base:'.hash('sha256', json_encode($this->canonicalCacheValue([
            'version' => self::TIMETABLE_VARIATION_CACHE_VERSION,
            'user_id' => $authUser->id,
            'school_id' => $authUser->school_id,
            'schoolyear_id' => $authUser->schoolyear_id,
            'subject_rows' => $subjectRows,
            'subject_mappings' => $subjectMappings,
            'course_groups' => $courseGroups,
            'settings' => $cacheSettings,
            'evaluation_criteria' => $evaluationCriteria,
            'selected_quality_criterion_keys' => $selectedQualityCriterionKeys,
        ]), JSON_THROW_ON_ERROR));
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function selectedTimetableCacheKey(string $baseCacheKey, array $settings): string
    {
        return 'students-timetables:timetable-v2:selected:'.hash('sha256', json_encode($this->canonicalCacheValue([
            'base' => $baseCacheKey,
            'selected_timetable_type' => $settings['selected_timetable_type'] ?? 'full_green',
            'selected_timetable_number' => (int) ($settings['selected_timetable_number'] ?? 1),
            'selected_additional_courses_required' => ($settings['selected_additional_courses_required'] ?? false) === true,
            'selected_conflict_ranking' => $settings['selected_conflict_ranking'] ?? null,
        ]), JSON_THROW_ON_ERROR));
    }

    private function canonicalCacheValue(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(fn (mixed $item): mixed => $this->canonicalCacheValue($item), $value);
        }

        ksort($value);

        return array_map(fn (mixed $item): mixed => $this->canonicalCacheValue($item), $value);
    }

    /**
     * @param  list<array<string, mixed>>  $selectedCourses
     * @param  list<list<array<string, mixed>>>  $courseOptions
     */
    private function timetableVariationLimitExceeded(
        array $selectedCourses,
        array $courseOptions,
        bool $hasMissingOptions,
        int $maximumTimetables = self::MAX_BACKEND_TIMETABLE_VARIATIONS,
    ): bool {
        if ($selectedCourses === [] || $hasMissingOptions) {
            return false;
        }

        $variationCount = 1;

        foreach ($courseOptions as $options) {
            $variationCount *= count($options);

            if ($variationCount > $maximumTimetables) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<array<string, mixed>>  $subjectRows
     * @param  list<array<string, mixed>>  $subjectMappings
     * @param  list<array<string, mixed>>  $courseGroups
     * @param  array<string, mixed>  $settings
     * @return array{selected_courses: list<array<string, mixed>>, course_options: list<list<array<string, mixed>>>, additional_courses: list<array<string, mixed>>, additional_course_options: list<list<array<string, mixed>>>, has_missing_options: bool, problem_courses: list<array<string, mixed>>}
     */
    private function timetableVariationInput(
        array $subjectRows,
        array $subjectMappings,
        array $courseGroups,
        array $settings,
    ): array {
        $selectedCourses = $this->selectedCourses($subjectRows, $subjectMappings, $courseGroups, $settings);
        $courseOptions = collect($selectedCourses)
            ->map(fn (array $course): array => $this->courseOptions($course, $courseGroups, $subjectMappings, $settings))
            ->all();
        $selectedAdditionalCourses = $this->selectedCourses(
            $subjectRows,
            $subjectMappings,
            $courseGroups,
            $settings,
            'selected_additional_course_keys',
        );
        $additionalCourseOptions = collect($selectedAdditionalCourses)
            ->map(fn (array $course): array => $this->courseOptions($course, $courseGroups, $subjectMappings, $settings))
            ->all();

        return [
            'selected_courses' => $selectedCourses,
            'course_options' => $courseOptions,
            'additional_courses' => $selectedAdditionalCourses,
            'additional_course_options' => $additionalCourseOptions,
            'has_missing_options' => collect($courseOptions)->contains(fn (array $options): bool => $options === []),
            'problem_courses' => $this->unavailableCourseDiagnostics(
                $selectedCourses,
                $courseOptions,
                $courseGroups,
                $subjectMappings,
                $settings,
            ),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $selectedCourses
     * @param  list<list<array<string, mixed>>>  $courseOptions
     * @param  list<array<string, mixed>>  $courseGroups
     * @param  list<array<string, mixed>>  $subjectMappings
     * @param  array<string, mixed>  $settings
     * @return list<array<string, mixed>>
     */
    private function unavailableCourseDiagnostics(
        array $selectedCourses,
        array $courseOptions,
        array $courseGroups,
        array $subjectMappings,
        array $settings,
    ): array {
        return collect($selectedCourses)
            ->values()
            ->map(function (array $course, int $index) use ($courseOptions, $courseGroups, $subjectMappings, $settings): ?array {
                if (($courseOptions[$index] ?? []) !== []) {
                    return null;
                }

                $reason = $this->unavailableCourseReason($course, $courseGroups, $subjectMappings, $settings);

                return [
                    'key' => (string) ($course['key'] ?? ''),
                    'code' => (string) ($course['code'] ?? ''),
                    'name' => (string) ($course['name'] ?? ''),
                    'label' => $this->courseProblemLabel($course),
                    'reason' => $reason,
                    'reason_label' => $this->unavailableCourseReasonLabel($reason),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $course
     * @param  list<array<string, mixed>>  $courseGroups
     * @param  list<array<string, mixed>>  $subjectMappings
     * @param  array<string, mixed>  $settings
     */
    private function unavailableCourseReason(
        array $course,
        array $courseGroups,
        array $subjectMappings,
        array $settings,
    ): string {
        $settingsWithoutDeselectedGroups = [
            ...$settings,
            'deselected_course_group_keys' => [],
        ];

        if ($this->courseOptions($course, $courseGroups, $subjectMappings, $settingsWithoutDeselectedGroups) !== []) {
            return 'course_groups_deselected';
        }

        $settingsWithoutTimeConstraints = [
            ...$settingsWithoutDeselectedGroups,
            'constraints' => [
                ...($this->arrayValue($settings['constraints'] ?? [])),
                'availableWeekdays' => [1, 2, 3, 4, 5, 6],
                'availableTimes' => range(1, 30),
                'excludedWeekdayTimes' => [],
            ],
        ];

        if ($this->courseOptions($course, $courseGroups, $subjectMappings, $settingsWithoutTimeConstraints) !== []) {
            return 'time_constraints';
        }

        return 'no_course_groups';
    }

    private function unavailableCourseReasonLabel(string $reason): string
    {
        return match ($reason) {
            'course_groups_deselected' => 'Alle passenden Modulgruppen wurden abgewählt.',
            'time_constraints' => 'Die Zeitvorgaben schließen alle passenden Modulgruppen aus.',
            default => 'Es wurde keine passende Modulgruppe im importierten Stundenplan gefunden.',
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function arrayValue(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }

    /**
     * @param  list<array<string, mixed>>  $selectedCourses
     * @param  list<list<array<string, mixed>>>  $courseOptions
     * @param  list<list<array<string, mixed>>>  $additionalCourseOptions
     * @param  (callable(): void)|null  $combinationCheckedCallback
     * @return array{timetable_variation_count: int, full_green_timetable_count: int, green_timetable_count: int, red_timetable_count: int, selected_course_count: int, additional_course_timetable_count: int, no_saturday_timetable_count: int}
     */
    private function timetableVariationCounts(
        array $selectedCourses,
        array $courseOptions,
        bool $hasMissingOptions,
        array $additionalCourseOptions,
        string $selectedTimetableType,
        bool $selectedAdditionalCoursesRequired,
        ?callable $combinationCheckedCallback = null,
        bool $calculateNoSaturdayTimetableCount = true,
    ): array {
        if ($selectedCourses === []) {
            return [
                'timetable_variation_count' => 0,
                'full_green_timetable_count' => 0,
                'green_timetable_count' => 0,
                'red_timetable_count' => 0,
                'selected_course_count' => 0,
                'additional_course_timetable_count' => 0,
                'no_saturday_timetable_count' => 0,
            ];
        }

        if ($hasMissingOptions) {
            return [
                'timetable_variation_count' => 0,
                'full_green_timetable_count' => 0,
                'green_timetable_count' => 0,
                'red_timetable_count' => 0,
                'selected_course_count' => count($selectedCourses),
                'additional_course_timetable_count' => 0,
                'no_saturday_timetable_count' => 0,
            ];
        }

        $timetableVariationCount = $this->totalVariationCount($courseOptions);
        $typeCounts = $this->timetableTypeVariationCounts(
            $courseOptions,
            combinationCheckedCallback: $combinationCheckedCallback,
        );
        $hasMissingAdditionalOptions = $additionalCourseOptions === []
            || collect($additionalCourseOptions)->contains(fn (array $options): bool => $options === []);
        $additionalTypeCounts = $hasMissingAdditionalOptions
            ? ['full_green' => 0, 'green' => 0, 'conflict' => 0]
            : $this->requiredAdditionalCourseTimetableCounts($courseOptions, $additionalCourseOptions);
        $additionalCourseTimetableCount = $additionalTypeCounts[$selectedTimetableType] ?? 0;

        if ($selectedAdditionalCoursesRequired && ! $hasMissingAdditionalOptions) {
            $typeCounts = $additionalTypeCounts;
            $timetableVariationCount = array_sum($additionalTypeCounts);
        }
        $noSaturdayTimetableCount = $calculateNoSaturdayTimetableCount
            ? $this->noSaturdayTimetableCount(
                $courseOptions,
                $additionalCourseOptions,
                $selectedAdditionalCoursesRequired && ! $hasMissingAdditionalOptions,
            )
            : 0;

        return [
            'timetable_variation_count' => $timetableVariationCount,
            'full_green_timetable_count' => $typeCounts['full_green'],
            'green_timetable_count' => $typeCounts['green'],
            'red_timetable_count' => $typeCounts['conflict'],
            'selected_course_count' => count($selectedCourses),
            'additional_course_timetable_count' => $additionalCourseTimetableCount,
            'no_saturday_timetable_count' => $noSaturdayTimetableCount,
        ];
    }

    /**
     * @param  list<list<array<string, mixed>>>  $courseOptions
     */
    private function totalVariationCount(array $courseOptions): int
    {
        return collect($courseOptions)
            ->reduce(fn (int $total, array $options): int => $total * count($options), 1);
    }

    /**
     * @param  list<list<array<string, mixed>>>  $courseOptions
     * @param  list<list<array<string, mixed>>>  $additionalCourseOptions
     * @param  array{weekly: array<string, true>, dated: array<string, true>, dated_weekly: array<string, true>, has_overlap: bool}|null  $usedAllSummary
     * @param  array{weekly: array<string, true>, dated: array<string, true>, dated_weekly: array<string, true>, has_overlap: bool}|null  $usedRegularDateSummary
     * @param  array{weekly: array<string, true>, dated: array<string, true>, dated_weekly: array<string, true>, has_overlap: bool}|null  $usedRegularWeeklySlotSummary
     * @param  array<string, mixed>|null  $qualityState
     */
    private function noSaturdayTimetableCount(
        array $courseOptions,
        array $additionalCourseOptions = [],
        bool $selectedAdditionalCoursesRequired = false,
        int $courseIndex = 0,
        ?array $usedAllSummary = null,
        ?array $usedRegularDateSummary = null,
        ?array $usedRegularWeeklySlotSummary = null,
        ?array $qualityState = null,
        bool $hasRegularConflict = false,
    ): int {
        $qualityState ??= $this->emptyQualityState();

        if (($qualityState['all_uses_saturday'] ?? false) === true) {
            return 0;
        }

        if ($courseIndex >= count($courseOptions)) {
            if ($hasRegularConflict) {
                return 0;
            }

            if (! $selectedAdditionalCoursesRequired) {
                return 1;
            }

            return $this->acceptedNoSaturdayAdditionalCourseSelectionCount(
                $additionalCourseOptions,
                $usedAllSummary ?? $this->emptyDateKeySummary(),
                $usedRegularDateSummary ?? $this->emptyDateKeySummary(),
                $usedRegularWeeklySlotSummary ?? $this->emptyDateKeySummary(),
                $qualityState,
            );
        }

        $count = 0;
        $usedAllSummary ??= $this->emptyDateKeySummary();
        $usedRegularDateSummary ??= $this->emptyDateKeySummary();
        $usedRegularWeeklySlotSummary ??= $this->emptyDateKeySummary();

        foreach ($courseOptions[$courseIndex] as $option) {
            $nextState = $this->nextTimetableTypeState(
                $option,
                $usedAllSummary,
                $usedRegularDateSummary,
                $usedRegularWeeklySlotSummary,
                true,
                $hasRegularConflict,
            );

            $count += $this->noSaturdayTimetableCount(
                $courseOptions,
                $additionalCourseOptions,
                $selectedAdditionalCoursesRequired,
                $courseIndex + 1,
                $nextState['used_all_summary'],
                $nextState['used_regular_date_summary'],
                $nextState['used_regular_weekly_slot_summary'],
                $this->mergeQualityStates($qualityState, $option['quality_state'] ?? $this->qualityStateForOption($option)),
                $nextState['has_regular_conflict'],
            );
        }

        return $count;
    }

    /**
     * @param  list<list<array<string, mixed>>>  $additionalCourseOptions
     * @param  array{weekly: array<string, true>, dated: array<string, true>, dated_weekly: array<string, true>, has_overlap: bool}  $usedAllSummary
     * @param  array{weekly: array<string, true>, dated: array<string, true>, dated_weekly: array<string, true>, has_overlap: bool}  $usedRegularDateSummary
     * @param  array{weekly: array<string, true>, dated: array<string, true>, dated_weekly: array<string, true>, has_overlap: bool}  $usedRegularWeeklySlotSummary
     * @param  array<string, mixed>  $qualityState
     */
    private function acceptedNoSaturdayAdditionalCourseSelectionCount(
        array $additionalCourseOptions,
        array $usedAllSummary,
        array $usedRegularDateSummary,
        array $usedRegularWeeklySlotSummary,
        array $qualityState,
        int $additionalCourseIndex = 0,
    ): int {
        if (($qualityState['all_uses_saturday'] ?? false) === true) {
            return 0;
        }

        if ($additionalCourseIndex >= count($additionalCourseOptions)) {
            return 1;
        }

        $count = 0;

        foreach ($additionalCourseOptions[$additionalCourseIndex] as $option) {
            $allSummary = $option['all_date_summary'] ?? $this->dateKeySummary($option['date_keys'] ?? []);

            if ($allSummary['has_overlap'] || $this->dateKeySummariesOverlap($usedAllSummary, $allSummary)) {
                continue;
            }

            $nextState = $this->nextTimetableTypeState(
                $option,
                $usedAllSummary,
                $usedRegularDateSummary,
                $usedRegularWeeklySlotSummary,
                true,
                false,
            );

            if ($nextState['has_regular_conflict']) {
                continue;
            }

            $count += $this->acceptedNoSaturdayAdditionalCourseSelectionCount(
                $additionalCourseOptions,
                $nextState['used_all_summary'],
                $nextState['used_regular_date_summary'],
                $nextState['used_regular_weekly_slot_summary'],
                $this->mergeQualityStates($qualityState, $option['quality_state'] ?? $this->qualityStateForOption($option)),
                $additionalCourseIndex + 1,
            );
        }

        return $count;
    }

    /**
     * @param  list<list<array<string, mixed>>>  $courseOptions
     * @param  array{weekly: array<string, true>, dated: array<string, true>, dated_weekly: array<string, true>, has_overlap: bool}|null  $usedAllSummary
     * @param  array{weekly: array<string, true>, dated: array<string, true>, dated_weekly: array<string, true>, has_overlap: bool}|null  $usedRegularDateSummary
     * @param  array{weekly: array<string, true>, dated: array<string, true>, dated_weekly: array<string, true>, has_overlap: bool}|null  $usedRegularWeeklySlotSummary
     * @param  (callable(): void)|null  $combinationCheckedCallback
     * @return array{full_green: int, green: int, conflict: int}
     */
    private function timetableTypeVariationCounts(
        array $courseOptions,
        int $courseIndex = 0,
        ?array $usedAllSummary = null,
        ?array $usedRegularDateSummary = null,
        ?array $usedRegularWeeklySlotSummary = null,
        bool $isFullGreenCandidate = true,
        bool $hasRegularConflict = false,
        ?callable $combinationCheckedCallback = null,
    ): array {
        if ($courseIndex >= count($courseOptions)) {
            if ($combinationCheckedCallback !== null) {
                $combinationCheckedCallback();
            }

            $type = $hasRegularConflict
                ? 'conflict'
                : ($isFullGreenCandidate ? 'full_green' : 'green');

            return [
                'full_green' => $type === 'full_green' ? 1 : 0,
                'green' => $type === 'green' ? 1 : 0,
                'conflict' => $type === 'conflict' ? 1 : 0,
            ];
        }

        $counts = [
            'full_green' => 0,
            'green' => 0,
            'conflict' => 0,
        ];
        $usedAllSummary ??= $this->emptyDateKeySummary();
        $usedRegularDateSummary ??= $this->emptyDateKeySummary();
        $usedRegularWeeklySlotSummary ??= $this->emptyDateKeySummary();

        foreach ($courseOptions[$courseIndex] as $option) {
            $nextState = $this->nextTimetableTypeState(
                $option,
                $usedAllSummary,
                $usedRegularDateSummary,
                $usedRegularWeeklySlotSummary,
                $isFullGreenCandidate,
                $hasRegularConflict,
            );
            $nextCounts = $this->timetableTypeVariationCounts(
                $courseOptions,
                $courseIndex + 1,
                $nextState['used_all_summary'],
                $nextState['used_regular_date_summary'],
                $nextState['used_regular_weekly_slot_summary'],
                $nextState['is_full_green_candidate'],
                $nextState['has_regular_conflict'],
                $combinationCheckedCallback,
            );

            $counts['full_green'] += $nextCounts['full_green'];
            $counts['green'] += $nextCounts['green'];
            $counts['conflict'] += $nextCounts['conflict'];
        }

        return $counts;
    }

    /**
     * @param  list<list<array<string, mixed>>>  $courseOptions
     * @param  list<list<array<string, mixed>>>  $additionalCourseOptions
     * @param  array{weekly: array<string, true>, dated: array<string, true>, dated_weekly: array<string, true>, has_overlap: bool}|null  $usedAllSummary
     * @param  array{weekly: array<string, true>, dated: array<string, true>, dated_weekly: array<string, true>, has_overlap: bool}|null  $usedRegularDateSummary
     * @param  array{weekly: array<string, true>, dated: array<string, true>, dated_weekly: array<string, true>, has_overlap: bool}|null  $usedRegularWeeklySlotSummary
     * @return array{full_green: int, green: int, conflict: int}
     */
    private function requiredAdditionalCourseTimetableCounts(
        array $courseOptions,
        array $additionalCourseOptions,
        int $courseIndex = 0,
        ?array $usedAllSummary = null,
        ?array $usedRegularDateSummary = null,
        ?array $usedRegularWeeklySlotSummary = null,
        bool $isFullGreenCandidate = true,
        bool $hasRegularConflict = false,
    ): array {
        if ($courseIndex >= count($courseOptions)) {
            $combinationType = $hasRegularConflict
                ? 'conflict'
                : ($isFullGreenCandidate ? 'full_green' : 'green');
            $acceptedSelectionCount = $this->acceptedAdditionalCourseSelectionCount(
                $additionalCourseOptions,
                $usedAllSummary ?? $this->emptyDateKeySummary(),
            );

            return [
                'full_green' => $combinationType === 'full_green' ? $acceptedSelectionCount : 0,
                'green' => $combinationType === 'green' ? $acceptedSelectionCount : 0,
                'conflict' => $combinationType === 'conflict' ? $acceptedSelectionCount : 0,
            ];
        }

        $counts = [
            'full_green' => 0,
            'green' => 0,
            'conflict' => 0,
        ];
        $usedAllSummary ??= $this->emptyDateKeySummary();
        $usedRegularDateSummary ??= $this->emptyDateKeySummary();
        $usedRegularWeeklySlotSummary ??= $this->emptyDateKeySummary();

        foreach ($courseOptions[$courseIndex] as $option) {
            $nextState = $this->nextTimetableTypeState(
                $option,
                $usedAllSummary,
                $usedRegularDateSummary,
                $usedRegularWeeklySlotSummary,
                $isFullGreenCandidate,
                $hasRegularConflict,
            );

            $nextCounts = $this->requiredAdditionalCourseTimetableCounts(
                $courseOptions,
                $additionalCourseOptions,
                $courseIndex + 1,
                $nextState['used_all_summary'],
                $nextState['used_regular_date_summary'],
                $nextState['used_regular_weekly_slot_summary'],
                $nextState['is_full_green_candidate'],
                $nextState['has_regular_conflict'],
            );

            $counts['full_green'] += $nextCounts['full_green'];
            $counts['green'] += $nextCounts['green'];
            $counts['conflict'] += $nextCounts['conflict'];
        }

        return $counts;
    }

    /**
     * @param  list<list<array<string, mixed>>>  $additionalCourseOptions
     * @param  array{weekly: array<string, true>, dated: array<string, true>, dated_weekly: array<string, true>, has_overlap: bool}  $usedAllSummary
     */
    private function acceptedAdditionalCourseSelectionCount(
        array $additionalCourseOptions,
        array $usedAllSummary,
        int $additionalCourseIndex = 0,
    ): int {
        if ($additionalCourseIndex >= count($additionalCourseOptions)) {
            return 1;
        }

        $count = 0;

        foreach ($additionalCourseOptions[$additionalCourseIndex] as $option) {
            $allSummary = $option['all_date_summary'] ?? $this->dateKeySummary($option['date_keys'] ?? []);

            if ($allSummary['has_overlap'] || $this->dateKeySummariesOverlap($usedAllSummary, $allSummary)) {
                continue;
            }

            $count += $this->acceptedAdditionalCourseSelectionCount(
                $additionalCourseOptions,
                $this->mergeDateKeySummaries($usedAllSummary, $allSummary),
                $additionalCourseIndex + 1,
            );
        }

        return $count;
    }

    /**
     * @param  list<list<array<string, mixed>>>  $additionalCourseOptions
     */
    private function selectedAdditionalCoursesAvailable(array $additionalCourseOptions): bool
    {
        return $additionalCourseOptions !== []
            && ! collect($additionalCourseOptions)->contains(fn (array $options): bool => $options === []);
    }

    /**
     * @param  list<list<array<string, mixed>>>  $courseOptions
     * @param  list<list<array<string, mixed>>>  $additionalCourseOptions
     * @param  array<string, mixed>  $settings
     * @param  list<array<string, mixed>>  $evaluationCriteria
     * @return array{summary: array<string, array<string, mixed>>, combination_counts: array<string, int>}
     */
    private function qualityResultForSelectedTimetableType(
        array $courseOptions,
        array $additionalCourseOptions,
        array $settings,
        array $evaluationCriteria,
    ): array {
        $summary = $this->emptyQualitySummary($evaluationCriteria);
        $combinationCounts = [];
        $selectedType = $this->selectedBackendTimetableType($settings['selected_timetable_type'] ?? 'full_green');

        if ($summary === [] || $selectedType === null || $courseOptions === [] || collect($courseOptions)->contains(fn (array $options): bool => $options === [])) {
            return [
                'summary' => $summary,
                'combination_counts' => $combinationCounts,
            ];
        }

        $additionalCoursesRequired = $this->selectedAdditionalCoursesAvailable($additionalCourseOptions);

        $this->recordQualityResultForSelectedTimetableType(
            $courseOptions,
            $additionalCourseOptions,
            $selectedType,
            $additionalCoursesRequired,
            $evaluationCriteria,
            $summary,
            $combinationCounts,
        );

        return [
            'summary' => $summary,
            'combination_counts' => $combinationCounts,
        ];
    }

    /**
     * @param  list<list<array<string, mixed>>>  $courseOptions
     * @param  list<list<array<string, mixed>>>  $additionalCourseOptions
     * @param  list<array<string, mixed>>  $evaluationCriteria
     * @param  array<string, array<string, mixed>>  $qualitySummary
     * @param  array<string, int>  $qualityMetricCombinationCounts
     * @param  array<string, mixed>|null  $qualityState
     * @param  array{weekly: array<string, true>, dated: array<string, true>, dated_weekly: array<string, true>, has_overlap: bool}|null  $usedAllSummary
     * @param  array{weekly: array<string, true>, dated: array<string, true>, dated_weekly: array<string, true>, has_overlap: bool}|null  $usedRegularDateSummary
     * @param  array{weekly: array<string, true>, dated: array<string, true>, dated_weekly: array<string, true>, has_overlap: bool}|null  $usedRegularWeeklySlotSummary
     */
    private function recordQualityResultForSelectedTimetableType(
        array $courseOptions,
        array $additionalCourseOptions,
        string $selectedType,
        bool $additionalCoursesRequired,
        array $evaluationCriteria,
        array &$qualitySummary,
        array &$qualityMetricCombinationCounts,
        int $courseIndex = 0,
        ?array $qualityState = null,
        ?array $usedAllSummary = null,
        ?array $usedRegularDateSummary = null,
        ?array $usedRegularWeeklySlotSummary = null,
        bool $isFullGreenCandidate = true,
        bool $hasRegularConflict = false,
    ): void {
        if ($courseIndex >= count($courseOptions)) {
            $combinationType = $hasRegularConflict
                ? 'conflict'
                : ($isFullGreenCandidate ? 'full_green' : 'green');

            if ($combinationType !== $selectedType) {
                return;
            }

            $additionalOptions = [];

            if ($additionalCoursesRequired) {
                $additionalOptions = $this->additionalOptionsForTimetable(
                    $additionalCourseOptions,
                    $usedAllSummary ?? $this->emptyDateKeySummary(),
                );

                if (count($additionalOptions) !== count($additionalCourseOptions)) {
                    return;
                }
            }

            $metrics = $this->qualityMetricsFromState($this->qualityStateWithOptions(
                $qualityState ?? $this->emptyQualityState(),
                $additionalOptions,
            ));
            $this->recordQualityMetrics($qualitySummary, $metrics);
            $signature = $this->qualityMetricCombinationSignatureForMetrics($evaluationCriteria, $metrics);
            $qualityMetricCombinationCounts[$signature] = (int) ($qualityMetricCombinationCounts[$signature] ?? 0) + 1;

            return;
        }

        $qualityState ??= $this->emptyQualityState();
        $usedAllSummary ??= $this->emptyDateKeySummary();
        $usedRegularDateSummary ??= $this->emptyDateKeySummary();
        $usedRegularWeeklySlotSummary ??= $this->emptyDateKeySummary();

        foreach ($courseOptions[$courseIndex] as $option) {
            $nextState = $this->nextTimetableTypeState(
                $option,
                $usedAllSummary,
                $usedRegularDateSummary,
                $usedRegularWeeklySlotSummary,
                $isFullGreenCandidate,
                $hasRegularConflict,
            );

            $this->recordQualityResultForSelectedTimetableType(
                $courseOptions,
                $additionalCourseOptions,
                $selectedType,
                $additionalCoursesRequired,
                $evaluationCriteria,
                $qualitySummary,
                $qualityMetricCombinationCounts,
                $courseIndex + 1,
                $this->mergeQualityStates($qualityState, $option['quality_state'] ?? $this->qualityStateForOption($option)),
                $nextState['used_all_summary'],
                $nextState['used_regular_date_summary'],
                $nextState['used_regular_weekly_slot_summary'],
                $nextState['is_full_green_candidate'],
                $nextState['has_regular_conflict'],
            );
        }
    }

    /**
     * @param  list<array<string, mixed>>  $evaluationCriteria
     * @return array<string, array<string, mixed>>
     */
    private function emptyQualitySummary(array $evaluationCriteria): array
    {
        return collect($evaluationCriteria)
            ->keyBy(fn (array $criterion): string => (string) ($criterion['key'] ?? ''))
            ->map(fn (array $criterion): array => [
                'key' => $criterion['key'] ?? '',
                'label' => $criterion['label'] ?? '',
                'description' => $criterion['description'] ?? '',
                'option' => $criterion['option'] ?? null,
                'total' => 0,
                'count' => 0,
                'best_value' => null,
                'best_label' => '-',
            ])
            ->all();
    }

    /**
     * @param  array<string, array<string, mixed>>  $qualitySummary
     * @param  array<string, int|bool>  $metrics
     */
    private function recordQualityMetrics(array &$qualitySummary, array $metrics): void
    {
        foreach ($qualitySummary as $key => $summary) {
            $value = $this->qualityMetricValue($key, $summary['option'] ?? null, $metrics);
            $qualitySummary[$key]['total'] = (int) ($summary['total'] ?? 0) + 1;

            if (is_bool($value)) {
                $qualitySummary[$key]['count'] = (int) ($summary['count'] ?? 0) + ($value ? 1 : 0);
                $qualitySummary[$key]['best_value'] = true;
                $qualitySummary[$key]['best_label'] = 'erfüllt';

                continue;
            }

            if (($summary['best_value'] ?? null) === null || $this->qualityMetricIsBetter($key, $value, (int) $summary['best_value'])) {
                $qualitySummary[$key]['best_value'] = $value;
                $qualitySummary[$key]['best_label'] = $this->qualityMetricLabel($key, $value);
                $qualitySummary[$key]['count'] = 1;

                continue;
            }

            if ((int) $summary['best_value'] === $value) {
                $qualitySummary[$key]['count'] = (int) ($summary['count'] ?? 0) + 1;
            }
        }
    }

    /**
     * @param  array<string, array<string, mixed>>  $qualitySummary
     * @param  ?array<string, int|bool>  $selectedMetrics
     * @param  array<string, int>  $qualityMetricCombinationCounts
     * @param  list<array<string, mixed>>  $evaluationCriteria
     * @param  list<string>  $selectedQualityCriterionKeys
     * @param  array{steps: array<string, array<string, mixed>>, counts: array<string, int>, total: int}|null  $selectedQualitySubset
     * @return list<array<string, mixed>>
     */
    private function qualityCountersFromSummary(
        array $qualitySummary,
        ?array $selectedMetrics = null,
        array $qualityMetricCombinationCounts = [],
        array $evaluationCriteria = [],
        array $selectedQualityCriterionKeys = [],
        ?array $selectedQualitySubset = null,
    ): array {
        if ($selectedQualityCriterionKeys === [] || $qualityMetricCombinationCounts === [] || $evaluationCriteria === []) {
            return collect($qualitySummary)
                ->values()
                ->map(fn (array $summary): array => [
                    ...$this->qualityCounterPayload($summary),
                    ...$this->selectedQualityCounterPayload($summary, $selectedMetrics),
                ])
                ->all();
        }

        $selectedQualitySubset ??= $this->selectedQualityCriteriaSubset(
            $qualityMetricCombinationCounts,
            $qualitySummary,
            $evaluationCriteria,
            $selectedQualityCriterionKeys,
        );

        return collect($qualitySummary)
            ->values()
            ->map(function (array $summary) use ($selectedMetrics, $evaluationCriteria, $selectedQualityCriterionKeys, $selectedQualitySubset): array {
                $key = (string) ($summary['key'] ?? '');
                $counterPayload = in_array($key, $selectedQualityCriterionKeys, true)
                    ? ($selectedQualitySubset['steps'][$key] ?? $this->qualityCounterPayload($summary))
                    : $this->qualityCounterPayloadForCombinationSubset(
                        $summary,
                        $selectedQualitySubset['counts'],
                        $evaluationCriteria,
                    );

                return [
                    ...$counterPayload,
                    ...$this->selectedQualityCounterPayload($summary, $selectedMetrics),
                ];
            })
            ->all();
    }

    /**
     * @param  array<string, int>  $qualityMetricCombinationCounts
     * @param  array<string, array<string, mixed>>  $qualitySummary
     * @param  list<array<string, mixed>>  $evaluationCriteria
     * @param  list<string>  $selectedQualityCriterionKeys
     * @return array{steps: array<string, array<string, mixed>>, counts: array<string, int>, total: int}
     */
    private function selectedQualityCriteriaSubset(
        array $qualityMetricCombinationCounts,
        array $qualitySummary,
        array $evaluationCriteria,
        array $selectedQualityCriterionKeys,
    ): array {
        $subsetCounts = $qualityMetricCombinationCounts;
        $steps = [];

        foreach ($selectedQualityCriterionKeys as $criterionKey) {
            if (! array_key_exists($criterionKey, $qualitySummary)) {
                $subsetCounts = [];

                continue;
            }

            $stepPayload = $this->qualityCounterPayloadForCombinationSubset(
                $qualitySummary[$criterionKey],
                $subsetCounts,
                $evaluationCriteria,
            );
            $steps[$criterionKey] = $stepPayload;
            $bestValue = $stepPayload['best_value'] ?? null;

            if ($bestValue === null) {
                $subsetCounts = [];

                continue;
            }

            $subsetCounts = $this->qualityMetricCombinationCountsMatchingCriterion(
                $subsetCounts,
                $evaluationCriteria,
                $criterionKey,
                $bestValue,
            );
        }

        return [
            'steps' => $steps,
            'counts' => $subsetCounts,
            'total' => (int) array_sum($subsetCounts),
        ];
    }

    /**
     * @param  array<string, mixed>  $summary
     * @param  array<string, int>  $qualityMetricCombinationCounts
     * @param  list<array<string, mixed>>  $evaluationCriteria
     * @return array<string, mixed>
     */
    private function qualityCounterPayloadForCombinationSubset(
        array $summary,
        array $qualityMetricCombinationCounts,
        array $evaluationCriteria,
    ): array {
        $payload = $this->qualityCounterPayload($summary);
        $key = (string) ($summary['key'] ?? '');
        $criterionIndex = $this->qualityCriterionIndex($evaluationCriteria, $key);
        $total = (int) array_sum($qualityMetricCombinationCounts);

        $payload['total'] = $total;
        $payload['count'] = 0;

        if ($criterionIndex === null || $total === 0) {
            $payload['best_value'] = null;
            $payload['best_label'] = '-';

            return $payload;
        }

        if (is_bool($summary['best_value'] ?? null)) {
            $payload['best_value'] = true;
            $payload['best_label'] = 'erfüllt';
            $payload['count'] = $this->qualityMetricCombinationCountMatchingPart(
                $qualityMetricCombinationCounts,
                $criterionIndex,
                $this->qualityMetricSignaturePart(true),
            );

            return $payload;
        }

        $bestValue = null;
        $bestCount = 0;

        foreach ($qualityMetricCombinationCounts as $signature => $count) {
            $value = $this->qualityMetricSignaturePartValue(explode('|', $signature)[$criterionIndex] ?? null);

            if (! is_int($value)) {
                continue;
            }

            if ($bestValue === null || $this->qualityMetricIsBetter($key, $value, $bestValue)) {
                $bestValue = $value;
                $bestCount = (int) $count;

                continue;
            }

            if ($value === $bestValue) {
                $bestCount += (int) $count;
            }
        }

        $payload['best_value'] = $bestValue;
        $payload['best_label'] = $bestValue === null ? '-' : $this->qualityMetricLabel($key, $bestValue);
        $payload['count'] = $bestCount;

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $summary
     * @return array<string, mixed>
     */
    private function qualityCounterPayload(array $summary): array
    {
        return [
            'key' => $summary['key'] ?? '',
            'label' => $summary['label'] ?? '',
            'description' => $summary['description'] ?? '',
            'option' => $summary['option'] ?? null,
            'count' => (int) ($summary['count'] ?? 0),
            'total' => (int) ($summary['total'] ?? 0),
            'best_value' => $summary['best_value'] ?? null,
            'best_label' => $summary['best_label'] ?? '-',
        ];
    }

    /**
     * @param  array<string, mixed>  $summary
     * @param  ?array<string, int|bool>  $selectedMetrics
     * @return array<string, mixed>
     */
    private function selectedQualityCounterPayload(array $summary, ?array $selectedMetrics): array
    {
        if ($selectedMetrics === null) {
            return [
                'selected_value' => null,
                'selected_label' => '-',
                'selected_reached' => false,
            ];
        }

        $key = (string) ($summary['key'] ?? '');
        $selectedValue = $this->qualityMetricValue($key, $summary['option'] ?? null, $selectedMetrics);

        return [
            'selected_value' => $selectedValue,
            'selected_label' => is_bool($selectedValue) ? ($selectedValue ? 'erfüllt' : 'nicht erfüllt') : $this->qualityMetricLabel($key, $selectedValue),
            'selected_reached' => $this->qualityMetricValueReachedBest($key, $selectedValue, $summary['best_value'] ?? null),
        ];
    }

    /**
     * @param  array<string, int>  $qualityMetricCombinationCounts
     * @param  array<string, array<string, mixed>>  $qualitySummary
     */
    private function allQualityCriteriaCount(array $qualityMetricCombinationCounts, array $qualitySummary): int
    {
        if ($qualitySummary === []) {
            return 0;
        }

        $signature = $this->allQualityCriteriaSignature($qualitySummary);

        if ($signature === null) {
            return 0;
        }

        return (int) ($qualityMetricCombinationCounts[$signature] ?? 0);
    }

    /**
     * @param  list<string>  $selectedQualityCriterionKeys
     * @param  list<array<string, mixed>>  $evaluationCriteria
     * @return list<string>
     */
    private function selectedQualityCriterionKeys(array $selectedQualityCriterionKeys, array $evaluationCriteria): array
    {
        $availableKeys = collect($evaluationCriteria)
            ->pluck('key')
            ->map(fn (mixed $key): string => (string) $key)
            ->flip();

        return collect($selectedQualityCriterionKeys)
            ->map(fn (mixed $key): string => (string) $key)
            ->filter(fn (string $key): bool => $key !== '' && $availableKeys->has($key))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $evaluationCriteria
     */
    private function qualityCriterionIndex(array $evaluationCriteria, string $criterionKey): ?int
    {
        foreach (array_values($evaluationCriteria) as $index => $criterion) {
            if ((string) ($criterion['key'] ?? '') === $criterionKey) {
                return $index;
            }
        }

        return null;
    }

    /**
     * @param  array<string, int>  $qualityMetricCombinationCounts
     * @param  list<array<string, mixed>>  $evaluationCriteria
     * @return array<string, int>
     */
    private function qualityMetricCombinationCountsMatchingCriterion(
        array $qualityMetricCombinationCounts,
        array $evaluationCriteria,
        string $criterionKey,
        int|bool $value,
    ): array {
        $criterionIndex = $this->qualityCriterionIndex($evaluationCriteria, $criterionKey);

        if ($criterionIndex === null) {
            return [];
        }

        $requiredPart = $this->qualityMetricSignaturePart($value);

        return collect($qualityMetricCombinationCounts)
            ->filter(function (int $count, string $signature) use ($criterionIndex, $requiredPart): bool {
                return (explode('|', $signature)[$criterionIndex] ?? null) === $requiredPart;
            })
            ->all();
    }

    /**
     * @param  array<string, int>  $qualityMetricCombinationCounts
     */
    private function qualityMetricCombinationCountMatchingPart(
        array $qualityMetricCombinationCounts,
        int $criterionIndex,
        string $requiredPart,
    ): int {
        return collect($qualityMetricCombinationCounts)
            ->reduce(function (int $total, int $count, string $signature) use ($criterionIndex, $requiredPart): int {
                if ((explode('|', $signature)[$criterionIndex] ?? null) !== $requiredPart) {
                    return $total;
                }

                return $total + $count;
            }, 0);
    }

    /**
     * @param  list<array<string, mixed>>  $evaluationCriteria
     * @param  array<string, int|bool>  $metrics
     */
    private function qualityMetricCombinationSignatureForMetrics(array $evaluationCriteria, array $metrics): string
    {
        return $this->qualityMetricCombinationSignature(collect($evaluationCriteria)
            ->map(fn (array $criterion): int|bool => $this->qualityMetricValue(
                (string) ($criterion['key'] ?? ''),
                $criterion['option'] ?? null,
                $metrics,
            ))
            ->all());
    }

    /**
     * @param  array<string, array<string, mixed>>  $qualitySummary
     */
    private function allQualityCriteriaSignature(array $qualitySummary): ?string
    {
        if ($qualitySummary === []) {
            return null;
        }

        $bestValues = collect($qualitySummary)
            ->pluck('best_value')
            ->all();

        if (collect($bestValues)->contains(fn (mixed $value): bool => $value === null)) {
            return null;
        }

        return $this->qualityMetricCombinationSignature($bestValues);
    }

    /**
     * @param  list<int|bool|null>  $values
     */
    private function qualityMetricCombinationSignature(array $values): string
    {
        return collect($values)
            ->map(fn (int|bool|null $value): string => $this->qualityMetricSignaturePart($value))
            ->implode('|');
    }

    private function qualityMetricSignaturePart(int|bool|null $value): string
    {
        if (is_bool($value)) {
            return 'bool:'.(int) $value;
        }

        return 'int:'.(int) $value;
    }

    private function qualityMetricSignaturePartValue(?string $value): int|bool|null
    {
        if ($value === null) {
            return null;
        }

        if (str_starts_with($value, 'bool:')) {
            return (bool) (int) substr($value, 5);
        }

        if (str_starts_with($value, 'int:')) {
            return (int) substr($value, 4);
        }

        return null;
    }

    /**
     * @param  list<array<string, mixed>>  $options
     * @param  list<array<string, mixed>>  $additionalOptions
     * @return array<string, int|bool>
     */
    private function qualityMetricsFromOptions(array $options, array $additionalOptions = []): array
    {
        $allWeekdays = [];
        $regularWeekdayHours = [];
        $occasionalWeekdayHours = [];
        $regularCourseGroups = [];
        $allUsesSaturday = false;
        $regularUsesSaturday = false;
        $startsFromPeriod10 = true;
        $endsByPeriod13 = true;
        $distanceLearningCount = 0;

        foreach ([...$options, ...$additionalOptions] as $option) {
            $course = is_array($option['course'] ?? null) ? $option['course'] : [];
            if ($course !== [] && $this->optionIsDistanceLearningCourse($course, $option)) {
                $distanceLearningCount++;
            }

            foreach ($option['course_groups'] ?? [] as $courseGroup) {
                $isOccasional = $this->isOccasionalCourseGroup($courseGroup);
                $weekday = (int) ($courseGroup['weekday'] ?? 0);
                $hour = (int) ($courseGroup['hour'] ?? 0);

                if ($weekday >= 1 && $weekday <= 6) {
                    $allWeekdays[$weekday] = true;

                    if ($weekday === 6) {
                        $allUsesSaturday = true;
                        $regularUsesSaturday = $regularUsesSaturday || ! $isOccasional;
                    }
                }

                if ($hour > 0 && $hour < 10) {
                    $startsFromPeriod10 = false;
                }

                if ($hour > 13) {
                    $endsByPeriod13 = false;
                }

                if ($hour <= 0) {
                    continue;
                }

                if ($isOccasional) {
                    $occasionalWeekdayHours[$weekday][$hour] = true;

                    continue;
                }

                $regularWeekdayHours[$weekday][$hour] = true;
                $regularCourseGroups[] = $courseGroup;
            }
        }

        return [
            'saturday_free_all_appointments' => ! $allUsesSaturday,
            'saturday_free_ignore_single_date_appointments' => ! $regularUsesSaturday,
            'free_days' => max(0, 6 - count($allWeekdays)),
            'gap_count' => $this->gapCountFromWeekdayHours($regularWeekdayHours, $occasionalWeekdayHours),
            'starts_from_period_10' => $startsFromPeriod10,
            'ends_by_period_13' => $endsByPeriod13,
            'regular_conflict_count' => $this->regularConflictCountForCourseGroups($regularCourseGroups),
            'distance_learning_count' => $distanceLearningCount,
        ];
    }

    /**
     * @return array{
     *     all_weekdays: array<int, true>,
     *     regular_weekday_hours: array<int, array<int, true>>,
     *     occasional_weekday_hours: array<int, array<int, true>>,
     *     all_uses_saturday: bool,
     *     regular_uses_saturday: bool,
     *     starts_from_period_10: bool,
     *     ends_by_period_13: bool,
     *     distance_learning_count: int
     * }
     */
    private function emptyQualityState(): array
    {
        return [
            'all_weekdays' => [],
            'regular_weekday_hours' => [],
            'occasional_weekday_hours' => [],
            'all_uses_saturday' => false,
            'regular_uses_saturday' => false,
            'starts_from_period_10' => true,
            'ends_by_period_13' => true,
            'distance_learning_count' => 0,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $courseGroups
     * @return array<string, mixed>
     */
    private function qualityStateForCourseGroups(array $courseGroups): array
    {
        $state = $this->emptyQualityState();

        foreach ($courseGroups as $courseGroup) {
            $isOccasional = $this->isOccasionalCourseGroup($courseGroup);
            $weekday = (int) ($courseGroup['weekday'] ?? 0);
            $hour = (int) ($courseGroup['hour'] ?? 0);

            if ($weekday >= 1 && $weekday <= 6) {
                $state['all_weekdays'][$weekday] = true;

                if ($weekday === 6) {
                    $state['all_uses_saturday'] = true;
                    $state['regular_uses_saturday'] = $state['regular_uses_saturday'] || ! $isOccasional;
                }
            }

            if ($hour > 0 && $hour < 10) {
                $state['starts_from_period_10'] = false;
            }

            if ($hour > 13) {
                $state['ends_by_period_13'] = false;
            }

            if ($hour <= 0) {
                continue;
            }

            if ($isOccasional) {
                $state['occasional_weekday_hours'][$weekday][$hour] = true;

                continue;
            }

            $state['regular_weekday_hours'][$weekday][$hour] = true;
        }

        return $state;
    }

    /**
     * @param  array<string, mixed>  $option
     * @return array<string, mixed>
     */
    private function qualityStateForOption(array $option): array
    {
        $state = $this->qualityStateForCourseGroups($option['course_groups'] ?? []);
        $course = is_array($option['course'] ?? null) ? $option['course'] : [];

        if ($course !== [] && $this->optionIsDistanceLearningCourse($course, $option)) {
            $state['distance_learning_count'] = 1;
        }

        return $state;
    }

    /**
     * @param  array<string, mixed>  $state
     * @param  list<array<string, mixed>>  $options
     * @return array<string, mixed>
     */
    private function qualityStateWithOptions(array $state, array $options): array
    {
        foreach ($options as $option) {
            $state = $this->mergeQualityStates(
                $state,
                $option['quality_state'] ?? $this->qualityStateForOption($option),
            );
        }

        return $state;
    }

    /**
     * @param  array<string, mixed>  $firstState
     * @param  array<string, mixed>  $secondState
     * @return array<string, mixed>
     */
    private function mergeQualityStates(array $firstState, array $secondState): array
    {
        return [
            'all_weekdays' => ($firstState['all_weekdays'] ?? []) + ($secondState['all_weekdays'] ?? []),
            'regular_weekday_hours' => $this->mergeWeekdayHourStates(
                $firstState['regular_weekday_hours'] ?? [],
                $secondState['regular_weekday_hours'] ?? [],
            ),
            'occasional_weekday_hours' => $this->mergeWeekdayHourStates(
                $firstState['occasional_weekday_hours'] ?? [],
                $secondState['occasional_weekday_hours'] ?? [],
            ),
            'all_uses_saturday' => ($firstState['all_uses_saturday'] ?? false) || ($secondState['all_uses_saturday'] ?? false),
            'regular_uses_saturday' => ($firstState['regular_uses_saturday'] ?? false) || ($secondState['regular_uses_saturday'] ?? false),
            'starts_from_period_10' => ($firstState['starts_from_period_10'] ?? true) && ($secondState['starts_from_period_10'] ?? true),
            'ends_by_period_13' => ($firstState['ends_by_period_13'] ?? true) && ($secondState['ends_by_period_13'] ?? true),
            'distance_learning_count' => (int) ($firstState['distance_learning_count'] ?? 0)
                + (int) ($secondState['distance_learning_count'] ?? 0),
        ];
    }

    /**
     * @param  array<int, array<int, true>>  $firstHours
     * @param  array<int, array<int, true>>  $secondHours
     * @return array<int, array<int, true>>
     */
    private function mergeWeekdayHourStates(array $firstHours, array $secondHours): array
    {
        foreach ($secondHours as $weekday => $hours) {
            $firstHours[(int) $weekday] = ($firstHours[(int) $weekday] ?? []) + $hours;
        }

        return $firstHours;
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, int|bool>
     */
    private function qualityMetricsFromState(array $state): array
    {
        return [
            'saturday_free_all_appointments' => ! ($state['all_uses_saturday'] ?? false),
            'saturday_free_ignore_single_date_appointments' => ! ($state['regular_uses_saturday'] ?? false),
            'free_days' => max(0, 6 - count($state['all_weekdays'] ?? [])),
            'gap_count' => $this->gapCountFromWeekdayHours(
                $state['regular_weekday_hours'] ?? [],
                $state['occasional_weekday_hours'] ?? [],
            ),
            'starts_from_period_10' => (bool) ($state['starts_from_period_10'] ?? true),
            'ends_by_period_13' => (bool) ($state['ends_by_period_13'] ?? true),
            'regular_conflict_count' => 0,
            'distance_learning_count' => (int) ($state['distance_learning_count'] ?? 0),
        ];
    }

    /**
     * @param  array<int, array<int, true>>  $regularWeekdayHours
     * @param  array<int, array<int, true>>  $occasionalWeekdayHours
     */
    private function gapCountFromWeekdayHours(array $regularWeekdayHours, array $occasionalWeekdayHours): int
    {
        $gapCount = 0;

        foreach ($regularWeekdayHours as $weekday => $regularHourSet) {
            $hours = array_map('intval', array_keys($regularHourSet));
            sort($hours);

            if (count($hours) <= 1) {
                continue;
            }

            $firstHour = $hours[0];
            $lastHour = $hours[count($hours) - 1];
            $regularGapCount = max(0, $lastHour - $firstHour + 1 - count($hours));
            $occasionalGapCount = 0;

            foreach (array_keys($occasionalWeekdayHours[$weekday] ?? []) as $occasionalHour) {
                $occasionalHour = (int) $occasionalHour;

                if ($occasionalHour > $firstHour && $occasionalHour < $lastHour && ! isset($regularHourSet[$occasionalHour])) {
                    $occasionalGapCount++;
                }
            }

            $gapCount += max($regularGapCount, $occasionalGapCount);
        }

        return $gapCount;
    }

    /**
     * @param  list<array<string, mixed>>  $courseGroups
     */
    private function regularConflictCountForCourseGroups(array $courseGroups): int
    {
        $conflictCount = 0;

        foreach ($courseGroups as $firstIndex => $firstCourseGroup) {
            foreach (array_slice($courseGroups, $firstIndex + 1) as $secondCourseGroup) {
                if ($this->courseGroupsDateSlotOverlap($firstCourseGroup, $secondCourseGroup)) {
                    $conflictCount++;
                }
            }
        }

        return $conflictCount;
    }

    /**
     * @param  array<string, int|bool>  $metrics
     */
    private function qualityMetricValue(string $key, mixed $option, array $metrics): int|bool
    {
        return match ($key) {
            'saturday_free' => ($option === 'ignore_single_date_appointments')
                ? (bool) ($metrics['saturday_free_ignore_single_date_appointments'] ?? false)
                : (bool) ($metrics['saturday_free_all_appointments'] ?? false),
            'free_days' => (int) ($metrics['free_days'] ?? 0),
            'few_gaps' => (int) ($metrics['gap_count'] ?? 0),
            'starts_from_period_10' => (bool) ($metrics['starts_from_period_10'] ?? false),
            'ends_by_period_13' => (bool) ($metrics['ends_by_period_13'] ?? false),
            'prefer_distance_learning' => (int) ($metrics['distance_learning_count'] ?? 0),
            'avoid_distance_learning' => $option === 'none'
                ? (int) ($metrics['distance_learning_count'] ?? 0) === 0
                : (int) ($metrics['distance_learning_count'] ?? 0),
            default => false,
        };
    }

    private function qualityMetricIsBetter(string $key, int $value, int $currentBest): bool
    {
        return match ($key) {
            'few_gaps', 'avoid_distance_learning' => $value < $currentBest,
            default => $value > $currentBest,
        };
    }

    private function qualityMetricLabel(string $key, int $value): string
    {
        return match ($key) {
            'free_days' => "{$value} freie Tage",
            'few_gaps' => "{$value} Lücken",
            'prefer_distance_learning' => "{$value} FU-Kurse",
            'avoid_distance_learning' => "{$value} FU-Kurse",
            default => (string) $value,
        };
    }

    private function qualityMetricValueReachedBest(string $key, int|bool $value, mixed $bestValue): bool
    {
        if (is_bool($value)) {
            return $value === true && $bestValue === true;
        }

        return is_numeric($bestValue) && (int) $bestValue === $value;
    }

    /**
     * @param  array<string, mixed>  $option
     * @param  array{weekly: array<string, true>, dated: array<string, true>, dated_weekly: array<string, true>, has_overlap: bool}  $usedAllSummary
     * @param  array{weekly: array<string, true>, dated: array<string, true>, dated_weekly: array<string, true>, has_overlap: bool}  $usedRegularDateSummary
     * @param  array{weekly: array<string, true>, dated: array<string, true>, dated_weekly: array<string, true>, has_overlap: bool}  $usedRegularWeeklySlotSummary
     * @return array{
     *     used_all_summary: array{weekly: array<string, true>, dated: array<string, true>, dated_weekly: array<string, true>, has_overlap: bool},
     *     used_regular_date_summary: array{weekly: array<string, true>, dated: array<string, true>, dated_weekly: array<string, true>, has_overlap: bool},
     *     used_regular_weekly_slot_summary: array{weekly: array<string, true>, dated: array<string, true>, dated_weekly: array<string, true>, has_overlap: bool},
     *     is_full_green_candidate: bool,
     *     has_regular_conflict: bool
     * }
     */
    private function nextTimetableTypeState(
        array $option,
        array $usedAllSummary,
        array $usedRegularDateSummary,
        array $usedRegularWeeklySlotSummary,
        bool $isFullGreenCandidate,
        bool $hasRegularConflict,
    ): array {
        $allSummary = $option['all_date_summary'] ?? $this->dateKeySummary($option['date_keys'] ?? []);
        $regularDateSummary = $option['regular_date_summary'] ?? $this->emptyDateKeySummary();
        $regularWeeklySlotSummary = $option['regular_weekly_slot_summary'] ?? $this->emptyDateKeySummary();
        $nextHasRegularConflict = $hasRegularConflict
            || $regularDateSummary['has_overlap']
            || $this->dateKeySummariesOverlap($usedRegularDateSummary, $regularDateSummary)
            || $regularWeeklySlotSummary['has_overlap']
            || $this->dateKeySummariesOverlap($usedRegularWeeklySlotSummary, $regularWeeklySlotSummary);

        return [
            'used_all_summary' => $this->mergeDateKeySummaries($usedAllSummary, $allSummary),
            'used_regular_date_summary' => $this->mergeDateKeySummaries($usedRegularDateSummary, $regularDateSummary),
            'used_regular_weekly_slot_summary' => $this->mergeDateKeySummaries($usedRegularWeeklySlotSummary, $regularWeeklySlotSummary),
            'is_full_green_candidate' => $isFullGreenCandidate
                && ! $nextHasRegularConflict
                && ! $allSummary['has_overlap']
                && ! $this->dateKeySummariesOverlap($usedAllSummary, $allSummary),
            'has_regular_conflict' => $nextHasRegularConflict,
        ];
    }

    /**
     * @param  list<list<array<string, mixed>>>  $courseOptions
     * @param  array<string, mixed>  $settings
     * @param  list<list<array<string, mixed>>>  $additionalCourseOptions
     * @param  array{timetable_variation_count: int, full_green_timetable_count: int, green_timetable_count: int, red_timetable_count: int, selected_course_count: int, additional_course_timetable_count: int, no_saturday_timetable_count: int}  $counts
     * @param  list<array<string, mixed>>  $evaluationCriteria
     * @param  list<string>  $selectedQualityCriterionKeys
     * @param  array{steps: array<string, array<string, mixed>>, counts: array<string, int>, total: int}|null  $selectedQualitySubset
     * @return ?array<string, mixed>
     */
    private function selectedTimetable(
        array $courseOptions,
        array $additionalCourseOptions,
        array $settings,
        array $counts,
        array $evaluationCriteria = [],
        array $selectedQualityCriterionKeys = [],
        ?array $selectedQualitySubset = null,
    ): ?array {
        if ($courseOptions === [] || collect($courseOptions)->contains(fn (array $options): bool => $options === [])) {
            return null;
        }

        $selectedType = $this->selectedBackendTimetableType($settings['selected_timetable_type'] ?? 'full_green');
        if ($selectedType === null) {
            return null;
        }

        $availableCount = match ($selectedType) {
            'conflict' => $counts['red_timetable_count'],
            'green' => $counts['green_timetable_count'],
            default => $counts['full_green_timetable_count'],
        };
        $additionalCoursesRequired = ($settings['selected_additional_courses_required'] ?? false) === true
            && $this->selectedAdditionalCoursesAvailable($additionalCourseOptions);
        $additionalCoursesRequiredForQuality = $this->selectedAdditionalCoursesAvailable($additionalCourseOptions);
        $qualitySignatureCounts = $this->selectedQualitySignatureCounts(
            $settings,
            $selectedQualityCriterionKeys,
            $selectedQualitySubset,
        );

        if ($additionalCoursesRequired) {
            $availableCount = $counts['additional_course_timetable_count'];
        }

        if ($qualitySignatureCounts !== []) {
            $availableCount = (int) array_sum($qualitySignatureCounts);
        }

        if ($availableCount <= 0) {
            return null;
        }

        $selectedNumber = min(
            max(1, (int) ($settings['selected_timetable_number'] ?? 1)),
            $availableCount,
        );
        $remainingNumber = $selectedNumber;

        if ($qualitySignatureCounts !== []) {
            $combination = $this->findSelectedCombinationMatchingQualityCriteria(
                $courseOptions,
                $additionalCourseOptions,
                $selectedType,
                $evaluationCriteria,
                $qualitySignatureCounts,
                $additionalCoursesRequiredForQuality,
                $remainingNumber,
            );

            return $combination === null
                ? null
                : $this->timetableFromOptions(
                    $combination['options'],
                    $selectedType,
                    $selectedNumber,
                    $combination['additional_options'],
                    $additionalCoursesRequiredForQuality,
                    count($combination['additional_options']),
                );
        }

        if ($additionalCoursesRequired) {
            $combination = $this->findSelectedCombinationWithAdditionalCourses(
                $courseOptions,
                $additionalCourseOptions,
                $selectedType,
                $remainingNumber,
            );

            return $combination === null
                ? null
                : $this->timetableFromOptions(
                    $combination['options'],
                    $selectedType,
                    $selectedNumber,
                    $combination['additional_options'],
                    true,
                    count($additionalCourseOptions),
                );
        }

        if (
            $selectedType === 'conflict'
            && ($settings['selected_conflict_ranking'] ?? null) === 'fewest_regular_conflicts'
        ) {
            $combination = $this->findSelectedConflictCombinationRanked(
                $courseOptions,
                $selectedNumber,
            );

            return $combination === null
                ? null
                : $this->timetableFromOptions(
                    $combination,
                    $selectedType,
                    $selectedNumber,
                    [],
                    false,
                    0,
                );
        }

        $combination = $this->findSelectedCombination($courseOptions, $selectedType, $remainingNumber);

        return $combination === null
            ? null
            : $this->timetableFromOptions(
                $combination,
                $selectedType,
                $selectedNumber,
                [],
                false,
                0,
            );
    }

    /**
     * @param  list<list<array<string, mixed>>>  $courseOptions
     * @return ?list<array<string, mixed>>
     */
    private function findSelectedConflictCombinationRanked(
        array $courseOptions,
        int $selectedNumber,
    ): ?array {
        $rankedCandidates = new SplPriorityQueue;
        $enumerationOrder = 0;

        $this->collectRankedConflictCombinations(
            $courseOptions,
            max(1, $selectedNumber),
            $rankedCandidates,
            $enumerationOrder,
        );

        $rankedCandidates->setExtractFlags(SplPriorityQueue::EXTR_DATA);
        $candidates = [];
        while (! $rankedCandidates->isEmpty()) {
            $candidates[] = $rankedCandidates->extract();
        }
        usort(
            $candidates,
            fn (array $first, array $second): int => [$first['conflict_count'], $first['order']]
                <=> [$second['conflict_count'], $second['order']],
        );

        return $candidates[min(max(1, $selectedNumber), count($candidates)) - 1]['options'] ?? null;
    }

    /**
     * @param  list<list<array<string, mixed>>>  $courseOptions
     * @param  array{weekly: array<string, true>, dated: array<string, true>, dated_weekly: array<string, true>, has_overlap: bool}|null  $usedAllSummary
     * @param  array{weekly: array<string, true>, dated: array<string, true>, dated_weekly: array<string, true>, has_overlap: bool}|null  $usedRegularDateSummary
     * @param  array{weekly: array<string, true>, dated: array<string, true>, dated_weekly: array<string, true>, has_overlap: bool}|null  $usedRegularWeeklySlotSummary
     * @param  list<array<string, mixed>>  $selectedOptions
     */
    private function collectRankedConflictCombinations(
        array $courseOptions,
        int $limit,
        SplPriorityQueue $rankedCandidates,
        int &$enumerationOrder,
        int $courseIndex = 0,
        ?array $usedAllSummary = null,
        ?array $usedRegularDateSummary = null,
        ?array $usedRegularWeeklySlotSummary = null,
        bool $isFullGreenCandidate = true,
        bool $hasRegularConflict = false,
        array $selectedOptions = [],
    ): void {
        if ($courseIndex >= count($courseOptions)) {
            if (! $hasRegularConflict) {
                return;
            }

            $candidate = [
                'conflict_count' => (int) ($this->qualityMetricsFromOptions($selectedOptions)['regular_conflict_count'] ?? 0),
                'order' => $enumerationOrder++,
                'options' => $selectedOptions,
            ];
            $rankedCandidates->insert($candidate, [$candidate['conflict_count'], $candidate['order']]);

            if (count($rankedCandidates) > $limit) {
                $rankedCandidates->extract();
            }

            return;
        }

        $usedAllSummary ??= $this->emptyDateKeySummary();
        $usedRegularDateSummary ??= $this->emptyDateKeySummary();
        $usedRegularWeeklySlotSummary ??= $this->emptyDateKeySummary();

        foreach ($courseOptions[$courseIndex] as $option) {
            $nextState = $this->nextTimetableTypeState(
                $option,
                $usedAllSummary,
                $usedRegularDateSummary,
                $usedRegularWeeklySlotSummary,
                $isFullGreenCandidate,
                $hasRegularConflict,
            );

            $this->collectRankedConflictCombinations(
                $courseOptions,
                $limit,
                $rankedCandidates,
                $enumerationOrder,
                $courseIndex + 1,
                $nextState['used_all_summary'],
                $nextState['used_regular_date_summary'],
                $nextState['used_regular_weekly_slot_summary'],
                $nextState['is_full_green_candidate'],
                $nextState['has_regular_conflict'],
                [...$selectedOptions, $option],
            );
        }
    }

    /**
     * @param  array<string, mixed>  $settings
     * @param  list<string>  $selectedQualityCriterionKeys
     * @param  array{steps: array<string, array<string, mixed>>, counts: array<string, int>, total: int}|null  $selectedQualitySubset
     * @return array<string, int>
     */
    private function selectedQualitySignatureCounts(
        array $settings,
        array $selectedQualityCriterionKeys,
        ?array $selectedQualitySubset,
    ): array {
        if (($settings['selected_quality_criteria_required'] ?? false) !== true) {
            return [];
        }

        if ($selectedQualityCriterionKeys === []) {
            return [];
        }

        $counts = [];

        foreach (($selectedQualitySubset['counts'] ?? []) as $signature => $count) {
            if ((int) $count <= 0) {
                continue;
            }

            $counts[(string) $signature] = (int) $count;
        }

        return $counts;
    }

    private function selectedBackendTimetableType(mixed $value): ?string
    {
        return match ((string) $value) {
            'full_green', 'green', 'conflict' => (string) $value,
            default => null,
        };
    }

    /**
     * @param  list<list<array<string, mixed>>>  $courseOptions
     * @param  list<list<array<string, mixed>>>  $additionalCourseOptions
     * @param  list<array<string, mixed>>  $evaluationCriteria
     * @param  array<string, int>  $qualitySignatureCounts
     * @param  array<string, mixed>|null  $qualityState
     * @param  array{weekly: array<string, true>, dated: array<string, true>, dated_weekly: array<string, true>, has_overlap: bool}|null  $usedAllSummary
     * @param  array{weekly: array<string, true>, dated: array<string, true>, dated_weekly: array<string, true>, has_overlap: bool}|null  $usedRegularDateSummary
     * @param  array{weekly: array<string, true>, dated: array<string, true>, dated_weekly: array<string, true>, has_overlap: bool}|null  $usedRegularWeeklySlotSummary
     * @param  list<array<string, mixed>>  $selectedOptions
     * @return ?array{options: list<array<string, mixed>>, additional_options: list<array<string, mixed>>}
     */
    private function findSelectedCombinationMatchingQualityCriteria(
        array $courseOptions,
        array $additionalCourseOptions,
        string $selectedType,
        array $evaluationCriteria,
        array $qualitySignatureCounts,
        bool $additionalCoursesRequired,
        int &$remainingNumber,
        int $courseIndex = 0,
        ?array $qualityState = null,
        ?array $usedAllSummary = null,
        ?array $usedRegularDateSummary = null,
        ?array $usedRegularWeeklySlotSummary = null,
        bool $isFullGreenCandidate = true,
        bool $hasRegularConflict = false,
        array $selectedOptions = [],
    ): ?array {
        if ($courseIndex >= count($courseOptions)) {
            $combinationType = $hasRegularConflict
                ? 'conflict'
                : ($isFullGreenCandidate ? 'full_green' : 'green');

            if ($combinationType !== $selectedType) {
                return null;
            }

            $additionalOptions = [];

            if ($additionalCoursesRequired) {
                $additionalOptions = $this->additionalOptionsForTimetable(
                    $additionalCourseOptions,
                    $usedAllSummary ?? $this->emptyDateKeySummary(),
                );

                if (count($additionalOptions) !== count($additionalCourseOptions)) {
                    return null;
                }
            }

            $metrics = $this->qualityMetricsFromState($this->qualityStateWithOptions(
                $qualityState ?? $this->emptyQualityState(),
                $additionalOptions,
            ));
            $signature = $this->qualityMetricCombinationSignatureForMetrics($evaluationCriteria, $metrics);

            if (! array_key_exists($signature, $qualitySignatureCounts)) {
                return null;
            }

            $remainingNumber--;

            return $remainingNumber === 0
                ? [
                    'options' => $selectedOptions,
                    'additional_options' => $additionalOptions,
                ]
                : null;
        }

        $qualityState ??= $this->emptyQualityState();
        $usedAllSummary ??= $this->emptyDateKeySummary();
        $usedRegularDateSummary ??= $this->emptyDateKeySummary();
        $usedRegularWeeklySlotSummary ??= $this->emptyDateKeySummary();

        foreach ($courseOptions[$courseIndex] as $option) {
            $nextState = $this->nextTimetableTypeState(
                $option,
                $usedAllSummary,
                $usedRegularDateSummary,
                $usedRegularWeeklySlotSummary,
                $isFullGreenCandidate,
                $hasRegularConflict,
            );
            $combination = $this->findSelectedCombinationMatchingQualityCriteria(
                $courseOptions,
                $additionalCourseOptions,
                $selectedType,
                $evaluationCriteria,
                $qualitySignatureCounts,
                $additionalCoursesRequired,
                $remainingNumber,
                $courseIndex + 1,
                $this->mergeQualityStates($qualityState, $option['quality_state'] ?? $this->qualityStateForOption($option)),
                $nextState['used_all_summary'],
                $nextState['used_regular_date_summary'],
                $nextState['used_regular_weekly_slot_summary'],
                $nextState['is_full_green_candidate'],
                $nextState['has_regular_conflict'],
                [...$selectedOptions, $option],
            );

            if ($combination !== null) {
                return $combination;
            }
        }

        return null;
    }

    /**
     * @param  list<list<array<string, mixed>>>  $courseOptions
     * @param  array{weekly: array<string, true>, dated: array<string, true>, dated_weekly: array<string, true>, has_overlap: bool}|null  $usedAllSummary
     * @param  array{weekly: array<string, true>, dated: array<string, true>, dated_weekly: array<string, true>, has_overlap: bool}|null  $usedRegularDateSummary
     * @param  array{weekly: array<string, true>, dated: array<string, true>, dated_weekly: array<string, true>, has_overlap: bool}|null  $usedRegularWeeklySlotSummary
     * @param  list<array<string, mixed>>  $selectedOptions
     * @return ?list<array<string, mixed>>
     */
    private function findSelectedCombination(
        array $courseOptions,
        string $selectedType,
        int &$remainingNumber,
        int $courseIndex = 0,
        ?array $usedAllSummary = null,
        ?array $usedRegularDateSummary = null,
        ?array $usedRegularWeeklySlotSummary = null,
        bool $isFullGreenCandidate = true,
        bool $hasRegularConflict = false,
        array $selectedOptions = [],
    ): ?array {
        if ($courseIndex >= count($courseOptions)) {
            $combinationType = $hasRegularConflict
                ? 'conflict'
                : ($isFullGreenCandidate ? 'full_green' : 'green');

            if ($combinationType !== $selectedType) {
                return null;
            }

            $remainingNumber--;

            return $remainingNumber === 0 ? $selectedOptions : null;
        }

        $usedAllSummary ??= $this->emptyDateKeySummary();
        $usedRegularDateSummary ??= $this->emptyDateKeySummary();
        $usedRegularWeeklySlotSummary ??= $this->emptyDateKeySummary();

        foreach ($courseOptions[$courseIndex] as $option) {
            $nextState = $this->nextTimetableTypeState(
                $option,
                $usedAllSummary,
                $usedRegularDateSummary,
                $usedRegularWeeklySlotSummary,
                $isFullGreenCandidate,
                $hasRegularConflict,
            );
            $remainingCounts = $this->timetableTypeVariationCounts(
                $courseOptions,
                $courseIndex + 1,
                $nextState['used_all_summary'],
                $nextState['used_regular_date_summary'],
                $nextState['used_regular_weekly_slot_summary'],
                $nextState['is_full_green_candidate'],
                $nextState['has_regular_conflict'],
            );
            $matchingCount = $remainingCounts[$selectedType] ?? 0;

            if ($matchingCount <= 0) {
                continue;
            }

            if ($remainingNumber > $matchingCount) {
                $remainingNumber -= $matchingCount;

                continue;
            }

            return $this->findSelectedCombination(
                $courseOptions,
                $selectedType,
                $remainingNumber,
                $courseIndex + 1,
                $nextState['used_all_summary'],
                $nextState['used_regular_date_summary'],
                $nextState['used_regular_weekly_slot_summary'],
                $nextState['is_full_green_candidate'],
                $nextState['has_regular_conflict'],
                [...$selectedOptions, $option],
            );
        }

        return null;
    }

    /**
     * @param  list<list<array<string, mixed>>>  $courseOptions
     * @return list<array<string, mixed>>
     */
    private function allTimetables(array $courseOptions): array
    {
        if ($courseOptions === [] || collect($courseOptions)->contains(fn (array $options): bool => $options === [])) {
            return [];
        }

        $timetablesByType = [
            'full_green' => [],
            'green' => [],
            'conflict' => [],
        ];
        $numbersByType = [
            'full_green' => 0,
            'green' => 0,
            'conflict' => 0,
        ];

        $this->collectAllTimetables(
            $courseOptions,
            $timetablesByType,
            $numbersByType,
        );

        return [
            ...$timetablesByType['full_green'],
            ...$timetablesByType['green'],
            ...$timetablesByType['conflict'],
        ];
    }

    /**
     * @param  list<list<array<string, mixed>>>  $courseOptions
     * @return list<array<string, mixed>>
     */
    private function allPossibleTimetables(
        array $courseOptions,
        int $maximumTimetables,
        int $fullGreenTimetableCount,
    ): array {
        if ($courseOptions === [] || collect($courseOptions)->contains(fn (array $options): bool => $options === [])) {
            return [];
        }

        $timetablesByType = [
            'full_green' => [],
            'green' => [],
        ];
        $numbersByType = [
            'full_green' => 0,
            'green' => 0,
        ];
        $fullGreenLimit = min(max(1, $maximumTimetables), max(0, $fullGreenTimetableCount));
        $limitsByType = [
            'full_green' => $fullGreenLimit,
            'green' => max(0, max(1, $maximumTimetables) - $fullGreenLimit),
        ];

        $this->collectAllPossibleTimetables(
            $courseOptions,
            $timetablesByType,
            $numbersByType,
            $limitsByType,
        );

        return [
            ...$timetablesByType['full_green'],
            ...$timetablesByType['green'],
        ];
    }

    /**
     * @param  list<list<array<string, mixed>>>  $courseOptions
     * @param  array{full_green: list<array<string, mixed>>, green: list<array<string, mixed>>}  $timetablesByType
     * @param  array{full_green: int, green: int}  $numbersByType
     * @param  array{full_green: int, green: int}  $limitsByType
     * @param  array{weekly: array<string, true>, dated: array<string, true>, dated_weekly: array<string, true>, has_overlap: bool}|null  $usedAllSummary
     * @param  array{weekly: array<string, true>, dated: array<string, true>, dated_weekly: array<string, true>, has_overlap: bool}|null  $usedRegularDateSummary
     * @param  array{weekly: array<string, true>, dated: array<string, true>, dated_weekly: array<string, true>, has_overlap: bool}|null  $usedRegularWeeklySlotSummary
     * @param  list<array<string, mixed>>  $selectedOptions
     */
    private function collectAllPossibleTimetables(
        array $courseOptions,
        array &$timetablesByType,
        array &$numbersByType,
        array $limitsByType,
        int $courseIndex = 0,
        ?array $usedAllSummary = null,
        ?array $usedRegularDateSummary = null,
        ?array $usedRegularWeeklySlotSummary = null,
        bool $isFullGreenCandidate = true,
        bool $hasRegularConflict = false,
        array $selectedOptions = [],
    ): void {
        if (
            count($timetablesByType['full_green']) >= $limitsByType['full_green']
            && count($timetablesByType['green']) >= $limitsByType['green']
        ) {
            return;
        }

        if ($hasRegularConflict) {
            return;
        }

        if ($courseIndex >= count($courseOptions)) {
            $type = $isFullGreenCandidate ? 'full_green' : 'green';

            if (count($timetablesByType[$type]) >= $limitsByType[$type]) {
                return;
            }

            $number = ++$numbersByType[$type];
            $timetablesByType[$type][] = $this->timetableFromOptions($selectedOptions, $type, $number);

            return;
        }

        $usedAllSummary ??= $this->emptyDateKeySummary();
        $usedRegularDateSummary ??= $this->emptyDateKeySummary();
        $usedRegularWeeklySlotSummary ??= $this->emptyDateKeySummary();

        foreach ($courseOptions[$courseIndex] as $option) {
            $nextState = $this->nextTimetableTypeState(
                $option,
                $usedAllSummary,
                $usedRegularDateSummary,
                $usedRegularWeeklySlotSummary,
                $isFullGreenCandidate,
                $hasRegularConflict,
            );

            $this->collectAllPossibleTimetables(
                $courseOptions,
                $timetablesByType,
                $numbersByType,
                $limitsByType,
                $courseIndex + 1,
                $nextState['used_all_summary'],
                $nextState['used_regular_date_summary'],
                $nextState['used_regular_weekly_slot_summary'],
                $nextState['is_full_green_candidate'],
                $nextState['has_regular_conflict'],
                [...$selectedOptions, $option],
            );
        }
    }

    /**
     * @param  list<list<array<string, mixed>>>  $courseOptions
     * @param  array{full_green: list<array<string, mixed>>, green: list<array<string, mixed>>, conflict: list<array<string, mixed>>}  $timetablesByType
     * @param  array{full_green: int, green: int, conflict: int}  $numbersByType
     * @param  array{weekly: array<string, true>, dated: array<string, true>, dated_weekly: array<string, true>, has_overlap: bool}|null  $usedAllSummary
     * @param  array{weekly: array<string, true>, dated: array<string, true>, dated_weekly: array<string, true>, has_overlap: bool}|null  $usedRegularDateSummary
     * @param  array{weekly: array<string, true>, dated: array<string, true>, dated_weekly: array<string, true>, has_overlap: bool}|null  $usedRegularWeeklySlotSummary
     * @param  list<array<string, mixed>>  $selectedOptions
     */
    private function collectAllTimetables(
        array $courseOptions,
        array &$timetablesByType,
        array &$numbersByType,
        int $courseIndex = 0,
        ?array $usedAllSummary = null,
        ?array $usedRegularDateSummary = null,
        ?array $usedRegularWeeklySlotSummary = null,
        bool $isFullGreenCandidate = true,
        bool $hasRegularConflict = false,
        array $selectedOptions = [],
    ): void {
        if ($courseIndex >= count($courseOptions)) {
            $type = $hasRegularConflict
                ? 'conflict'
                : ($isFullGreenCandidate ? 'full_green' : 'green');
            $number = ++$numbersByType[$type];
            $timetablesByType[$type][] = $this->timetableFromOptions($selectedOptions, $type, $number);

            return;
        }

        $usedAllSummary ??= $this->emptyDateKeySummary();
        $usedRegularDateSummary ??= $this->emptyDateKeySummary();
        $usedRegularWeeklySlotSummary ??= $this->emptyDateKeySummary();

        foreach ($courseOptions[$courseIndex] as $option) {
            $nextState = $this->nextTimetableTypeState(
                $option,
                $usedAllSummary,
                $usedRegularDateSummary,
                $usedRegularWeeklySlotSummary,
                $isFullGreenCandidate,
                $hasRegularConflict,
            );

            $this->collectAllTimetables(
                $courseOptions,
                $timetablesByType,
                $numbersByType,
                $courseIndex + 1,
                $nextState['used_all_summary'],
                $nextState['used_regular_date_summary'],
                $nextState['used_regular_weekly_slot_summary'],
                $nextState['is_full_green_candidate'],
                $nextState['has_regular_conflict'],
                [...$selectedOptions, $option],
            );
        }
    }

    /**
     * @param  list<list<array<string, mixed>>>  $courseOptions
     * @param  list<list<array<string, mixed>>>  $additionalCourseOptions
     * @param  array{weekly: array<string, true>, dated: array<string, true>, dated_weekly: array<string, true>, has_overlap: bool}|null  $usedAllSummary
     * @param  array{weekly: array<string, true>, dated: array<string, true>, dated_weekly: array<string, true>, has_overlap: bool}|null  $usedRegularDateSummary
     * @param  array{weekly: array<string, true>, dated: array<string, true>, dated_weekly: array<string, true>, has_overlap: bool}|null  $usedRegularWeeklySlotSummary
     * @param  list<array<string, mixed>>  $selectedOptions
     * @return ?array{options: list<array<string, mixed>>, additional_options: list<array<string, mixed>>}
     */
    private function findSelectedCombinationWithAdditionalCourses(
        array $courseOptions,
        array $additionalCourseOptions,
        string $selectedType,
        int &$remainingNumber,
        int $courseIndex = 0,
        ?array $usedAllSummary = null,
        ?array $usedRegularDateSummary = null,
        ?array $usedRegularWeeklySlotSummary = null,
        bool $isFullGreenCandidate = true,
        bool $hasRegularConflict = false,
        array $selectedOptions = [],
    ): ?array {
        if ($courseIndex >= count($courseOptions)) {
            $combinationType = $hasRegularConflict
                ? 'conflict'
                : ($isFullGreenCandidate ? 'full_green' : 'green');

            if ($combinationType !== $selectedType) {
                return null;
            }

            $additionalOptions = $this->findSelectedAdditionalOptionsForTimetable(
                $additionalCourseOptions,
                $usedAllSummary ?? $this->emptyDateKeySummary(),
                $remainingNumber,
            );

            if ($additionalOptions === null) {
                return null;
            }

            return [
                'options' => $selectedOptions,
                'additional_options' => $additionalOptions,
            ];
        }

        $usedAllSummary ??= $this->emptyDateKeySummary();
        $usedRegularDateSummary ??= $this->emptyDateKeySummary();
        $usedRegularWeeklySlotSummary ??= $this->emptyDateKeySummary();

        foreach ($courseOptions[$courseIndex] as $option) {
            $nextState = $this->nextTimetableTypeState(
                $option,
                $usedAllSummary,
                $usedRegularDateSummary,
                $usedRegularWeeklySlotSummary,
                $isFullGreenCandidate,
                $hasRegularConflict,
            );
            $remainingCounts = $this->requiredAdditionalCourseTimetableCounts(
                $courseOptions,
                $additionalCourseOptions,
                $courseIndex + 1,
                $nextState['used_all_summary'],
                $nextState['used_regular_date_summary'],
                $nextState['used_regular_weekly_slot_summary'],
                $nextState['is_full_green_candidate'],
                $nextState['has_regular_conflict'],
            );
            $matchingCount = $remainingCounts[$selectedType] ?? 0;

            if ($matchingCount <= 0) {
                continue;
            }

            if ($remainingNumber > $matchingCount) {
                $remainingNumber -= $matchingCount;

                continue;
            }

            $combination = $this->findSelectedCombinationWithAdditionalCourses(
                $courseOptions,
                $additionalCourseOptions,
                $selectedType,
                $remainingNumber,
                $courseIndex + 1,
                $nextState['used_all_summary'],
                $nextState['used_regular_date_summary'],
                $nextState['used_regular_weekly_slot_summary'],
                $nextState['is_full_green_candidate'],
                $nextState['has_regular_conflict'],
                [...$selectedOptions, $option],
            );

            if ($combination !== null) {
                return $combination;
            }
        }

        return null;
    }

    /**
     * @param  list<list<array<string, mixed>>>  $additionalCourseOptions
     * @param  array{weekly: array<string, true>, dated: array<string, true>, dated_weekly: array<string, true>, has_overlap: bool}  $usedAllSummary
     * @return ?list<array<string, mixed>>
     */
    private function findSelectedAdditionalOptionsForTimetable(
        array $additionalCourseOptions,
        array $usedAllSummary,
        int &$remainingNumber,
        int $additionalCourseIndex = 0,
        array $selectedAdditionalOptions = [],
    ): ?array {
        if ($additionalCourseIndex >= count($additionalCourseOptions)) {
            $remainingNumber--;

            return $remainingNumber === 0 ? $selectedAdditionalOptions : null;
        }

        foreach ($additionalCourseOptions[$additionalCourseIndex] as $option) {
            $allSummary = $option['all_date_summary'] ?? $this->dateKeySummary($option['date_keys'] ?? []);

            if ($allSummary['has_overlap'] || $this->dateKeySummariesOverlap($usedAllSummary, $allSummary)) {
                continue;
            }

            $selectedOptions = $this->findSelectedAdditionalOptionsForTimetable(
                $additionalCourseOptions,
                $this->mergeDateKeySummaries($usedAllSummary, $allSummary),
                $remainingNumber,
                $additionalCourseIndex + 1,
                [
                    ...$selectedAdditionalOptions,
                    $this->additionalTimetableOption($option),
                ],
            );

            if ($selectedOptions !== null) {
                return $selectedOptions;
            }
        }

        return null;
    }

    /**
     * @param  list<array<string, mixed>>  $options
     * @return array{weekly: array<string, true>, dated: array<string, true>, dated_weekly: array<string, true>, has_overlap: bool}
     */
    private function dateSummaryForOptions(array $options): array
    {
        return collect($options)
            ->map(fn (array $option): array => $option['all_date_summary'] ?? $this->dateKeySummary($option['date_keys'] ?? []))
            ->reduce(
                fn (array $summary, array $optionSummary): array => $this->mergeDateKeySummaries($summary, $optionSummary),
                $this->emptyDateKeySummary(),
            );
    }

    /**
     * @param  list<list<array<string, mixed>>>  $additionalCourseOptions
     * @param  array{weekly: array<string, true>, dated: array<string, true>, dated_weekly: array<string, true>, has_overlap: bool}  $usedAllSummary
     * @return list<array<string, mixed>>
     */
    private function additionalOptionsForTimetable(
        array $additionalCourseOptions,
        array $usedAllSummary,
        int $additionalCourseIndex = 0,
    ): array {
        if ($additionalCourseIndex >= count($additionalCourseOptions)) {
            return [];
        }

        foreach ($additionalCourseOptions[$additionalCourseIndex] as $option) {
            $allSummary = $option['all_date_summary'] ?? $this->dateKeySummary($option['date_keys'] ?? []);

            if ($allSummary['has_overlap'] || $this->dateKeySummariesOverlap($usedAllSummary, $allSummary)) {
                continue;
            }

            $followingOptions = $this->additionalOptionsForTimetable(
                $additionalCourseOptions,
                $this->mergeDateKeySummaries($usedAllSummary, $allSummary),
                $additionalCourseIndex + 1,
            );

            if (count($followingOptions) === count($additionalCourseOptions) - $additionalCourseIndex - 1) {
                return [
                    $this->additionalTimetableOption($option),
                    ...$followingOptions,
                ];
            }
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $option
     * @return array<string, mixed>
     */
    private function additionalTimetableOption(array $option): array
    {
        return [
            ...$option,
            'isAdditionalCourse' => true,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $options
     * @return array<string, mixed>
     */
    private function timetableFromOptions(
        array $options,
        string $type,
        int $number,
        array $additionalOptions = [],
        bool $additionalCoursesAccepted = false,
        int $acceptedAdditionalCourseCount = 0,
    ): array {
        $slotEntries = [];

        foreach ([...$options, ...$additionalOptions] as $option) {
            $course = is_array($option['course'] ?? null) ? $option['course'] : [];

            foreach ($option['course_groups'] ?? [] as $courseGroup) {
                $slotEntries[$this->slotKey($courseGroup['weekday'] ?? '', $courseGroup['hour'] ?? '')][] = $this->timetableSlot(
                    $course,
                    $option,
                    $courseGroup,
                );
            }
        }

        $regularProblems = [];
        $slots = collect($slotEntries)
            ->map(function (array $entries) use (&$regularProblems): array {
                return $this->timetableSlotWithRegularConflicts($entries, $regularProblems);
            })
            ->all();

        return [
            'key' => "backend-{$type}-{$number}",
            'number' => $number,
            'type' => $type,
            'metrics' => [
                ...$this->qualityMetricsFromOptions($options, $additionalOptions),
                'regular_conflict_count' => count($this->uniqueStrings($regularProblems)),
            ],
            'statusMessage' => match ($type) {
                'conflict' => 'Roter Stundenplan mit Überschneidung',
                'green' => 'Grüner Stundenplan mit Einzeltermin-Überschneidung',
                default => 'Voller grüner Stundenplan',
            },
            'additionalCoursesAccepted' => $additionalCoursesAccepted,
            'acceptedAdditionalCourseCount' => $acceptedAdditionalCourseCount,
            'missingAdditionalCourses' => [],
            'qualityCriteria' => [],
            'slots' => $slots,
            'occasionalAppointments' => [],
            'problems' => $this->uniqueStrings($regularProblems),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $entries
     * @param  list<string>  $regularProblems
     * @return array<string, mixed>
     */
    private function timetableSlotWithRegularConflicts(array $entries, array &$regularProblems): array
    {
        $slot = $entries[0] ?? [];
        $conflictingEntryIndexes = [];
        $sameSlotEntryIndexes = [];

        foreach ($entries as $firstIndex => $firstEntry) {
            foreach (array_slice($entries, $firstIndex + 1, null, true) as $secondIndex => $secondEntry) {
                if (! $this->courseGroupsDateSlotOverlap($firstEntry['courseGroup'] ?? [], $secondEntry['courseGroup'] ?? [])) {
                    $sameSlotEntryIndexes[$secondIndex] = true;

                    continue;
                }

                $conflictingEntryIndexes[$firstIndex] = true;
                $conflictingEntryIndexes[$secondIndex] = true;

                if (! $this->timetableSlotIsOccasional($firstEntry) && ! $this->timetableSlotIsOccasional($secondEntry)) {
                    $regularProblems[] = $this->regularConflictProblem($firstEntry, $secondEntry);
                }
            }
        }

        foreach (array_keys($conflictingEntryIndexes) as $entryIndex) {
            if ($entryIndex === 0) {
                continue;
            }

            $this->addSlotConflict($slot, $entries[$entryIndex]);
        }

        foreach (array_keys($sameSlotEntryIndexes) as $entryIndex) {
            if (isset($conflictingEntryIndexes[$entryIndex])) {
                continue;
            }

            $this->addSlotSameSlotEntry($slot, $entries[$entryIndex]);
        }

        return $slot;
    }

    /**
     * @param  array<string, mixed>  $course
     * @param  array<string, mixed>  $option
     * @param  array<string, mixed>  $courseGroup
     * @return array<string, mixed>
     */
    private function timetableSlot(array $course, array $option, array $courseGroup): array
    {
        return [
            'key' => $course['key'] ?? $course['code'] ?? '',
            'code' => $course['code'] ?? '',
            'name' => $course['name'] ?? '',
            'sourceLabel' => $option['label'] ?? $this->courseGroupOptionLabel($courseGroup),
            'alternativeLabels' => [$option['label'] ?? $this->courseGroupOptionLabel($courseGroup)],
            'courseGroup' => $courseGroup,
            'dateRangeLabel' => $this->courseGroupDateRangeLabel($courseGroup),
            'conflicts' => [],
            'isOccasional' => $this->isOccasionalCourseGroup($courseGroup),
            'isAdditionalCourse' => ($option['isAdditionalCourse'] ?? false) === true,
            'isDistanceLearningCourse' => $this->optionIsDistanceLearningCourse($course, $option),
        ];
    }

    /**
     * @param  array<string, mixed>  $course
     * @param  array<string, mixed>  $option
     */
    private function optionIsDistanceLearningCourse(array $course, array $option): bool
    {
        $courseGroups = collect($option['course_groups'] ?? []);

        if ($courseGroups->contains(
            fn (array $courseGroup): bool => ($courseGroup['is_kompaktunterricht'] ?? false) === true,
        )) {
            return false;
        }

        $requiredSlotCount = $this->requiredSlotCountForCourse($course);
        $scheduledWeeklyLoad = $this->courseGroupsScheduledWeeklyLoad(
            $courseGroups
                ->reject(fn (array $courseGroup): bool => $this->isOccasionalCourseGroup($courseGroup))
                ->values()
                ->all(),
        );

        return $requiredSlotCount >= 2
            && $scheduledWeeklyLoad > 0
            && abs(($scheduledWeeklyLoad * 2) - $requiredSlotCount) < 0.001;
    }

    /**
     * @param  array<string, mixed>  $course
     */
    private function requiredSlotCountForCourse(array $course): int
    {
        return max(1, (int) round((float) ($course['regular_hours'] ?? $course['hours'] ?? 0)));
    }

    /**
     * @param  list<array<string, mixed>>  $courseGroups
     */
    private function courseGroupsScheduledWeeklyLoad(array $courseGroups): float
    {
        return collect($this->uniqueCourseGroupsBySlot($courseGroups))
            ->sum(fn (array $courseGroup): float => $this->courseGroupWeeklySlotLoad($courseGroup));
    }

    /**
     * @param  list<array<string, mixed>>  $courseGroups
     * @return list<array<string, mixed>>
     */
    private function uniqueCourseGroupsBySlot(array $courseGroups): array
    {
        return collect($courseGroups)
            ->unique(fn (array $courseGroup): string => $this->slotKey(
                $courseGroup['weekday'] ?? '',
                $courseGroup['hour'] ?? '',
            ))
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $courseGroup
     */
    private function courseGroupWeeklySlotLoad(array $courseGroup): float
    {
        $interval = $this->courseGroupWeekInterval($courseGroup);

        return $interval > 0 ? 1 / $interval : 1.0;
    }

    /**
     * @param  array<string, mixed>  $courseGroup
     */
    private function courseGroupWeekInterval(array $courseGroup): int
    {
        if (is_numeric($courseGroup['recurrence_interval'] ?? null) && (int) $courseGroup['recurrence_interval'] > 0) {
            return (int) $courseGroup['recurrence_interval'];
        }

        if (preg_match('/(\d+)\s*-\s*w/iu', (string) ($courseGroup['recurrence_label'] ?? ''), $matches) === 1) {
            return (int) $matches[1];
        }

        return 0;
    }

    /**
     * @param  array<string, mixed>  $slot
     */
    private function timetableSlotIsOccasional(array $slot): bool
    {
        return ($slot['isOccasional'] ?? false) === true
            || $this->isOccasionalCourseGroup($slot['courseGroup'] ?? []);
    }

    /**
     * @param  array<string, mixed>  $slot
     * @param  array<string, mixed>  $sameSlotEntry
     */
    private function addSlotSameSlotEntry(array &$slot, array $sameSlotEntry): void
    {
        if (($sameSlotEntry['key'] ?? null) === ($slot['key'] ?? null)) {
            return;
        }

        $slot['sameSlotEntries'] ??= [];

        if (collect($slot['sameSlotEntries'])->contains(fn (array $entry): bool => ($entry['key'] ?? '') === ($sameSlotEntry['key'] ?? ''))) {
            return;
        }

        $slot['sameSlotEntries'][] = $sameSlotEntry;
    }

    /**
     * @param  array<string, mixed>  $slot
     * @param  array<string, mixed>  $conflictSlot
     */
    private function addSlotConflict(array &$slot, array $conflictSlot): void
    {
        $label = collect([
            $this->courseProblemLabel($conflictSlot),
            $this->courseGroupDateTimeLabel($conflictSlot['courseGroup'] ?? []),
        ])
            ->filter()
            ->implode(' ');

        if ($label === '') {
            return;
        }

        $slot['conflicts'] ??= [];

        if (collect($slot['conflicts'])->contains(fn (array $conflict): bool => ($conflict['label'] ?? '') === $label)) {
            return;
        }

        $slot['conflicts'][] = [
            'key' => $conflictSlot['key'] ?? $label,
            'label' => $label,
            'sortValue' => implode('|', [
                $conflictSlot['courseGroup']['weekday'] ?? '',
                str_pad((string) ($conflictSlot['courseGroup']['hour'] ?? ''), 2, '0', STR_PAD_LEFT),
                $conflictSlot['code'] ?? '',
            ]),
            'code' => $conflictSlot['code'] ?? '',
            'name' => $conflictSlot['name'] ?? '',
            'sourceLabel' => $conflictSlot['sourceLabel'] ?? '',
            'alternativeLabels' => $conflictSlot['alternativeLabels'] ?? [],
            'courseGroup' => $conflictSlot['courseGroup'] ?? [],
            'dateRangeLabel' => $conflictSlot['dateRangeLabel'] ?? '',
            'isOccasional' => $this->timetableSlotIsOccasional($conflictSlot),
            'isAdditionalCourse' => ($conflictSlot['isAdditionalCourse'] ?? false) === true,
            'isDistanceLearningCourse' => ($conflictSlot['isDistanceLearningCourse'] ?? false) === true,
        ];
    }

    /**
     * @param  array<string, mixed>  $firstSlot
     * @param  array<string, mixed>  $secondSlot
     */
    private function regularConflictProblem(array $firstSlot, array $secondSlot): string
    {
        $details = collect($this->overlappingDateSlotLabels($firstSlot['courseGroup'] ?? [], $secondSlot['courseGroup'] ?? []))
            ->filter()
            ->take(5)
            ->values()
            ->all();
        $detailLabel = $details === [] ? '' : ' ('.implode(', ', $details).')';

        return "{$this->courseProblemLabel($firstSlot)} überschneidet sich mit {$this->courseProblemLabel($secondSlot)}{$detailLabel}.";
    }

    /**
     * @param  array<string, mixed>  $course
     */
    private function courseProblemLabel(array $course): string
    {
        return collect([
            $course['code'] ?? '',
            $course['name'] ?? '',
            $course['sourceLabel'] ?? '',
        ])
            ->map(fn (mixed $value): string => trim((string) $value))
            ->filter()
            ->unique()
            ->implode(' ');
    }

    /**
     * @param  array<string, mixed>  $firstCourseGroup
     * @param  array<string, mixed>  $secondCourseGroup
     * @return list<string>
     */
    private function overlappingDateSlotLabels(array $firstCourseGroup, array $secondCourseGroup): array
    {
        $firstDates = $this->courseGroupDates($firstCourseGroup);
        $secondDates = $this->courseGroupDates($secondCourseGroup);
        $firstSlot = $this->slotKey($firstCourseGroup['weekday'] ?? '', $firstCourseGroup['hour'] ?? '');
        $secondSlot = $this->slotKey($secondCourseGroup['weekday'] ?? '', $secondCourseGroup['hour'] ?? '');

        if ($firstSlot !== $secondSlot) {
            return [];
        }

        if ($firstDates === [] || $secondDates === []) {
            return [$this->courseGroupDateTimeLabel($firstCourseGroup)];
        }

        return collect($firstDates)
            ->intersect($secondDates)
            ->map(fn (string $date): string => collect([
                $date,
                $this->courseGroupTimeLabel($firstCourseGroup),
            ])->filter()->implode(', '))
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $courseGroup
     */
    private function courseGroupDateTimeLabel(array $courseGroup): string
    {
        return collect([
            $this->courseGroupDateLabel($courseGroup),
            $this->courseGroupTimeLabel($courseGroup),
        ])
            ->filter()
            ->implode(', ');
    }

    /**
     * @param  array<string, mixed>  $courseGroup
     */
    private function courseGroupDateLabel(array $courseGroup): string
    {
        $dates = $this->courseGroupDates($courseGroup);

        return $dates === [] ? '' : implode(', ', $dates);
    }

    /**
     * @param  array<string, mixed>  $courseGroup
     */
    private function courseGroupDateRangeLabel(array $courseGroup): string
    {
        $dates = $this->courseGroupDates($courseGroup);

        if ($dates === []) {
            return '';
        }

        if ($dates[0] === $dates[count($dates) - 1]) {
            return $this->shortCourseGroupDateLabel($dates[0], true);
        }

        $firstDate = $this->shortCourseGroupDateLabel($dates[0], true);
        $lastDate = $this->shortCourseGroupDateLabel($dates[count($dates) - 1]);

        return "{$firstDate}-{$lastDate}";
    }

    private function shortCourseGroupDateLabel(string $date, bool $preserveEarlyMonthPadding = false): string
    {
        $dateTime = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);

        if ($dateTime === false) {
            return $date;
        }

        if ($preserveEarlyMonthPadding && (int) $dateTime->format('n') <= 4) {
            return $dateTime->format('j.m.');
        }

        return $dateTime->format('j.n.');
    }

    /**
     * @param  array<string, mixed>  $courseGroup
     */
    private function courseGroupTimeLabel(array $courseGroup): string
    {
        return collect([
            $courseGroup['weekday_label'] ?? null,
            $courseGroup['hour_label'] ?? null,
            $courseGroup['time_label'] ?? null,
        ])
            ->map(fn (mixed $value): string => trim((string) $value))
            ->filter()
            ->implode(' ');
    }

    /**
     * @param  array<string, mixed>  $firstCourseGroup
     * @param  array<string, mixed>  $secondCourseGroup
     */
    private function courseGroupsDateSlotOverlap(array $firstCourseGroup, array $secondCourseGroup): bool
    {
        if ($this->courseGroupsRegularDateRangeOverlap($firstCourseGroup, $secondCourseGroup)) {
            return true;
        }

        $firstSummary = $this->dateKeySummary($this->courseGroupDateSlotKeysForGroup($firstCourseGroup));
        $secondSummary = $this->dateKeySummary($this->courseGroupDateSlotKeysForGroup($secondCourseGroup));

        return $firstSummary['has_overlap']
            || $secondSummary['has_overlap']
            || $this->dateKeySummariesOverlap($firstSummary, $secondSummary);
    }

    /**
     * @param  array<string, mixed>  $firstCourseGroup
     * @param  array<string, mixed>  $secondCourseGroup
     */
    private function courseGroupsRegularDateRangeOverlap(array $firstCourseGroup, array $secondCourseGroup): bool
    {
        if (! $this->courseGroupsShareWeekdayTime($firstCourseGroup, $secondCourseGroup)) {
            return false;
        }

        if ($this->isOccasionalCourseGroup($firstCourseGroup) || $this->isOccasionalCourseGroup($secondCourseGroup)) {
            return false;
        }

        if (
            ! $this->usesContinuousRegularDateRange($firstCourseGroup)
            || ! $this->usesContinuousRegularDateRange($secondCourseGroup)
        ) {
            return false;
        }

        $firstDates = $this->courseGroupDates($firstCourseGroup);
        $secondDates = $this->courseGroupDates($secondCourseGroup);

        if ($firstDates === [] || $secondDates === []) {
            return false;
        }

        return max($firstDates[0], $secondDates[0]) <= min($firstDates[count($firstDates) - 1], $secondDates[count($secondDates) - 1]);
    }

    /**
     * @param  array<string, mixed>  $firstCourseGroup
     * @param  array<string, mixed>  $secondCourseGroup
     */
    private function courseGroupsBlockTimetableSlot(array $firstCourseGroup, array $secondCourseGroup): bool
    {
        if (! $this->courseGroupsShareWeekdayTime($firstCourseGroup, $secondCourseGroup)) {
            return false;
        }

        return ! $this->courseGroupsMixRegularAndOccasional($firstCourseGroup, $secondCourseGroup)
            && $this->courseGroupsDateSlotOverlap($firstCourseGroup, $secondCourseGroup);
    }

    /**
     * @param  array<string, mixed>  $firstCourseGroup
     * @param  array<string, mixed>  $secondCourseGroup
     */
    private function courseGroupsShareWeekdayTime(array $firstCourseGroup, array $secondCourseGroup): bool
    {
        return (int) ($firstCourseGroup['weekday'] ?? 0) === (int) ($secondCourseGroup['weekday'] ?? 0)
            && (int) ($firstCourseGroup['hour'] ?? 0) === (int) ($secondCourseGroup['hour'] ?? 0);
    }

    /**
     * @param  array<string, mixed>  $firstCourseGroup
     * @param  array<string, mixed>  $secondCourseGroup
     */
    private function courseGroupsMixRegularAndOccasional(array $firstCourseGroup, array $secondCourseGroup): bool
    {
        return $this->isOccasionalCourseGroup($firstCourseGroup) !== $this->isOccasionalCourseGroup($secondCourseGroup);
    }

    /**
     * @param  array<string, mixed>  $courseGroup
     * @return list<string>
     */
    private function courseGroupDates(array $courseGroup): array
    {
        $cacheKey = $this->courseGroupCacheKey($courseGroup);
        if (array_key_exists($cacheKey, $this->runtimeCache['courseGroupDates'] ?? [])) {
            return $this->runtimeCache['courseGroupDates'][$cacheKey];
        }

        if (! is_array($courseGroup['dates'] ?? null)) {
            return $this->runtimeCache['courseGroupDates'][$cacheKey] = [];
        }

        return $this->runtimeCache['courseGroupDates'][$cacheKey] = collect($courseGroup['dates'])
            ->map(fn (mixed $date): string => trim((string) $date))
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    private function slotKey(int|string $weekday, int|string $time): string
    {
        return "{$weekday}-{$time}";
    }

    /**
     * @param  list<array<string, mixed>>  $subjectRows
     * @param  list<array<string, mixed>>  $subjectMappings
     * @param  list<array<string, mixed>>  $courseGroups
     * @param  array<string, mixed>  $settings
     * @return list<array<string, mixed>>
     */
    private function selectedCourses(
        array $subjectRows,
        array $subjectMappings,
        array $courseGroups,
        array $settings,
        string $selectedCourseSettingsKey = 'selected_course_keys',
    ): array {
        $selectedCourseKeys = collect($settings[$selectedCourseSettingsKey] ?? [])
            ->map(fn (mixed $courseKey): string => (string) $courseKey)
            ->filter()
            ->unique()
            ->values();
        $selectedCourseKeyValues = $selectedCourseKeys->all();
        $deselectedCourseKeys = $this->stringList($settings['deselected_course_keys'] ?? []);

        if ($selectedCourseSettingsKey === 'selected_additional_course_keys' && $selectedCourseKeys->isEmpty()) {
            return [];
        }

        return collect($subjectRows)
            ->filter(fn (array $subject): bool => ($subject['is_active'] ?? true) !== false)
            ->filter(fn (array $subject): bool => $this->selectedModulesAreAuthoritative($settings)
                || $this->subjectMatchesSelectedBranch($subject, $settings))
            ->filter(fn (array $subject): bool => $this->selectedModulesAreAuthoritative($settings)
                || $this->subjectMatchesSelectedChoices($subject, $settings))
            ->flatMap(fn (array $subject): array => $this->courseVariantsFromSubject(
                $subject,
                $subjectMappings,
                $courseGroups,
                $settings,
                $selectedCourseSettingsKey,
            ))
            ->when(
                $selectedCourseKeys->isNotEmpty(),
                fn (Collection $courses): Collection => $courses
                    ->filter(fn (array $course): bool => $this->courseMatchesSelectedCourseKeys($course, $selectedCourseKeyValues)),
            )
            ->when(
                $selectedCourseSettingsKey === 'selected_course_keys',
                fn (Collection $courses): Collection => $courses
                    ->reject(fn (array $course): bool => $this->courseMatchesSelectedCourseKeys($course, $deselectedCourseKeys)),
            )
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $settings
     * @param  list<array<string, mixed>>  $selectedCourses
     */
    private function ensureEverySelectedCourseWasResolved(array $settings, array $selectedCourses): void
    {
        $requestedCourseKeys = $this->stringList($settings['selected_course_keys'] ?? []);
        $unresolvedCourseKeys = collect($requestedCourseKeys)
            ->filter(fn (string $courseKey): bool => collect($selectedCourses)
                ->filter(fn (array $course): bool => $this->courseMatchesSelectedCourseKeys($course, [$courseKey]))
                ->count() !== 1)
            ->map(fn (string $courseKey): string => $this->selectedCourseCodeFromKey($courseKey) ?: trim($courseKey))
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();
        $hasOneToOneCourseResolution = $requestedCourseKeys === []
            || count($requestedCourseKeys) === count($selectedCourses);

        if ($unresolvedCourseKeys === [] && $hasOneToOneCourseResolution) {
            return;
        }

        if ($unresolvedCourseKeys === []) {
            $unresolvedCourseKeys = collect($requestedCourseKeys)
                ->map(fn (string $courseKey): string => $this->selectedCourseCodeFromKey($courseKey) ?: trim($courseKey))
                ->filter()
                ->unique()
                ->sort()
                ->values()
                ->all();
        }

        throw ValidationException::withMessages([
            'modules' => sprintf(
                'Die ausgewählten Module konnten nicht eindeutig und vollständig aufgelöst werden: %s. Die Berechnung wurde abgebrochen.',
                implode(', ', $unresolvedCourseKeys),
            ),
        ]);
    }

    /**
     * @param  array<string, list<string>>  $requiredCourseGroupsByModule
     * @param  list<array<string, mixed>>  $courseGroups
     * @param  list<array<string, mixed>>  $selectedCourses
     * @param  list<array<string, mixed>>  $subjectMappings
     */
    private function ensureEveryRequiredCourseGroupWasResolvedForItsModule(
        array $requiredCourseGroupsByModule,
        array $courseGroups,
        array $selectedCourses,
        array $subjectMappings,
    ): void {
        if ($requiredCourseGroupsByModule === []) {
            return;
        }

        $courseGroupsByKey = collect($courseGroups)
            ->filter(fn (array $courseGroup): bool => trim((string) ($courseGroup['key'] ?? '')) !== '')
            ->keyBy(fn (array $courseGroup): string => (string) $courseGroup['key']);
        $hasUnresolvedCourseGroup = collect($requiredCourseGroupsByModule)
            ->contains(function (array $courseGroupKeys, int|string $moduleCode) use ($courseGroupsByKey, $selectedCourses, $subjectMappings): bool {
                $moduleCode = (string) $moduleCode;
                $coursesForModule = collect($selectedCourses)
                    ->filter(fn (array $course): bool => $this->courseMatchesSelectedCourseKeys($course, [$moduleCode]))
                    ->values();

                if ($coursesForModule->isEmpty()) {
                    return true;
                }

                return collect($courseGroupKeys)->contains(function (string $courseGroupKey) use ($courseGroupsByKey, $coursesForModule, $subjectMappings): bool {
                    $courseGroup = $courseGroupsByKey->get($courseGroupKey);

                    if (! is_array($courseGroup) || $this->courseGroupOptionLabel($courseGroup) === '') {
                        return true;
                    }

                    return ! $coursesForModule->contains(fn (array $course): bool => $this->courseGroupMatchesCourse(
                        $courseGroup,
                        $course,
                        $subjectMappings,
                    ));
                });
            });

        if (! $hasUnresolvedCourseGroup) {
            return;
        }

        throw ValidationException::withMessages([
            'selected_course_keys' => 'Mindestens ein ausgewählter Unterricht konnte nicht vollständig für die Berechnung aufgelöst werden. Die Berechnung wurde abgebrochen.',
        ]);
    }

    /**
     * @param  list<string>  $selectedCourseKeys
     */
    private function courseMatchesSelectedCourseKeys(array $course, array $selectedCourseKeys): bool
    {
        if ($selectedCourseKeys === []) {
            return false;
        }

        $courseKey = (string) ($course['key'] ?? '');
        if ($courseKey !== '' && in_array($courseKey, $selectedCourseKeys, true)) {
            return true;
        }

        $courseAliases = $this->courseAliases($course);

        return collect($selectedCourseKeys)
            ->map(fn (string $selectedCourseKey): string => $this->selectedCourseCodeFromKey($selectedCourseKey))
            ->filter()
            ->contains(fn (string $selectedCourseCode): bool => in_array($selectedCourseCode, $courseAliases, true));
    }

    private function selectedCourseCodeFromKey(string $selectedCourseKey): string
    {
        $selectedCourseKey = trim($selectedCourseKey);
        if ($selectedCourseKey === '' || str_contains($selectedCourseKey, '|')) {
            return '';
        }

        if (preg_match('/^(.+)-\d+$/u', $selectedCourseKey, $matches) === 1) {
            return $this->normalizedCourseCode($matches[1]);
        }

        return $this->normalizedCourseCode($selectedCourseKey);
    }

    /**
     * @param  list<array<string, mixed>>  $subjectMappings
     * @param  array<string, mixed>  $settings
     */
    private function authoritativeSelectedCourseCode(
        array $subject,
        array $subjectMappings,
        array $settings,
        string $selectedCourseSettingsKey,
    ): ?string {
        $matchingCourseCodes = collect($this->stringList($settings[$selectedCourseSettingsKey] ?? []))
            ->map(fn (string $selectedCourseCode): string => trim($selectedCourseCode))
            ->filter()
            ->filter(fn (string $selectedCourseCode): bool => $this->authoritativeCourseCodeMatchesSubject(
                $selectedCourseCode,
                $subject,
                $subjectMappings,
            ))
            ->unique(fn (string $selectedCourseCode): string => $this->normalizedCourseCode($selectedCourseCode))
            ->values();

        return $matchingCourseCodes->count() === 1
            ? $matchingCourseCodes->first()
            : null;
    }

    /**
     * @param  array<string, mixed>  $subject
     * @param  list<array<string, mixed>>  $subjectMappings
     */
    private function authoritativeCourseCodeMatchesSubject(
        string $selectedCourseCode,
        array $subject,
        array $subjectMappings,
    ): bool {
        if (preg_match(
            '/^([A-ZÄÖÜ]+)(\d*)$/u',
            $this->normalizedCourseCode($selectedCourseCode),
            $selectedCourseParts,
        ) !== 1 || $selectedCourseParts[2] !== $this->subjectModuleNumber($subject)) {
            return false;
        }

        $selectedCourseBase = $selectedCourseParts[1];

        if (
            $this->isReligionSubject($subject)
            && in_array($selectedCourseBase, ['REV', 'RIS', 'ROR'], true)
        ) {
            return true;
        }

        if ($this->isLanguageSubject($subject)) {
            $languageSubjectCode = $this->languageSubjectCode($subject);

            return in_array($selectedCourseBase, ['L', 'F', 'S', 'SPA'], true)
                && ($languageSubjectCode === '' || in_array($selectedCourseBase, [
                    $languageSubjectCode,
                    $this->defaultTimetableCodeAlias($languageSubjectCode),
                ], true));
        }

        $subjectAliases = collect([
            $this->subjectBaseKey($subject),
            $this->courseCodeWithoutModule((string) ($subject['json_code'] ?? '')),
        ])
            ->flatMap(fn (string $value): array => $this->courseCodeAliasParts($value))
            ->flatMap(fn (string $value): array => [$value, $this->defaultTimetableCodeAlias($value)])
            ->map(fn (string $value): string => $this->normalizedCourseCode($value))
            ->filter()
            ->unique()
            ->values();
        $mappedAliases = collect($this->activeSubjectMappings($subjectMappings))
            ->filter(fn (array $mapping): bool => $subjectAliases->contains(
                $this->normalizedCourseCode($mapping['json_subject'] ?? ''),
            ) || $subjectAliases->contains(
                $this->normalizedCourseCode($mapping['tt_subject'] ?? ''),
            ))
            ->flatMap(fn (array $mapping): array => [
                (string) ($mapping['json_subject'] ?? ''),
                (string) ($mapping['tt_subject'] ?? ''),
            ])
            ->flatMap(fn (string $value): array => $this->courseCodeAliasParts($this->courseCodeWithoutModule($value)))
            ->flatMap(fn (string $value): array => [$value, $this->defaultTimetableCodeAlias($value)])
            ->map(fn (string $value): string => $this->normalizedCourseCode($value))
            ->filter();

        return $subjectAliases
            ->merge($mappedAliases)
            ->contains($selectedCourseBase);
    }

    /** @param  array<string, mixed>  $settings */
    private function selectedModulesAreAuthoritative(array $settings): bool
    {
        return ($settings['selected_modules_are_authoritative'] ?? false) === true;
    }

    /**
     * @param  array<string, mixed>  $course
     * @param  list<array<string, mixed>>  $courseGroups
     * @param  list<array<string, mixed>>  $subjectMappings
     * @param  array<string, mixed>  $settings
     * @return list<array{label: string, course: array<string, mixed>, course_groups: list<array<string, mixed>>, date_keys: list<string>}>
     */
    private function courseOptions(array $course, array $courseGroups, array $subjectMappings, array $settings): array
    {
        $deselectedCourseGroupKeys = $this->stringList($settings['deselected_course_group_keys'] ?? []);

        return collect($this->courseOptionsBeforeDeselection($course, $courseGroups, $subjectMappings, $settings))
            ->reject(fn (array $option): bool => $this->courseGroupDeselected(
                $course,
                (string) ($option['label'] ?? ''),
                $deselectedCourseGroupKeys,
            ))
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $course
     * @param  list<array<string, mixed>>  $courseGroups
     * @param  list<array<string, mixed>>  $subjectMappings
     * @param  array<string, mixed>  $settings
     * @return list<array{label: string, course: array<string, mixed>, course_groups: list<array<string, mixed>>, date_keys: list<string>}>
     */
    private function courseOptionsBeforeDeselection(
        array $course,
        array $courseGroups,
        array $subjectMappings,
        array $settings,
    ): array {
        $selectedCourseGroupKeys = $this->stringList($settings['selected_course_group_keys'] ?? []);
        $cacheSelectedCourseGroupKeys = $selectedCourseGroupKeys;
        sort($cacheSelectedCourseGroupKeys, SORT_STRING);
        $cacheKey = hash('sha256', json_encode($this->canonicalCacheValue([
            'constraints' => $settings['constraints'] ?? [],
            'course' => $this->courseCacheKey($course),
            'require_complete_course_group_options' => ($settings['require_complete_course_group_options'] ?? false) === true,
            'selected_course_group_keys' => $cacheSelectedCourseGroupKeys,
        ]), JSON_THROW_ON_ERROR));

        if (array_key_exists($cacheKey, $this->runtimeCache['courseOptionsBeforeDeselection'] ?? [])) {
            return $this->runtimeCache['courseOptionsBeforeDeselection'][$cacheKey];
        }

        $hasSelectedCourseGroupKeys = $this->courseHasSelectedCourseGroupKeys($course, $selectedCourseGroupKeys);

        $courseGroupsByOption = collect($courseGroups)
            ->filter(fn (array $courseGroup): bool => $this->courseGroupMatchesCourse($courseGroup, $course, $subjectMappings))
            ->groupBy(fn (array $courseGroup): string => $this->courseGroupOptionLabel($courseGroup))
            ->reject(fn (Collection $groups, string $label): bool => $label === '');

        if (($settings['require_complete_course_group_options'] ?? false) === true) {
            $courseGroupsByOption = $courseGroupsByOption
                ->filter(fn (Collection $groups): bool => $groups
                    ->every(fn (array $courseGroup): bool => $this->courseGroupAvailable($courseGroup, $settings)));
        } else {
            $courseGroupsByOption = $courseGroupsByOption
                ->map(fn (Collection $groups): Collection => $groups
                    ->filter(fn (array $courseGroup): bool => $this->courseGroupAvailable($courseGroup, $settings)))
                ->filter(fn (Collection $groups): bool => $groups->isNotEmpty());
        }

        $options = $courseGroupsByOption
            ->when(
                $hasSelectedCourseGroupKeys,
                fn (Collection $groups): Collection => $groups
                    ->filter(fn (Collection $groups, string $label): bool => $this->courseGroupSelected($course, $label, $selectedCourseGroupKeys)),
            )
            ->map(function (Collection $groups, string $label) use ($course): array {
                $courseGroups = $groups->values()->all();
                $regularCourseGroups = collect($courseGroups)
                    ->reject(fn (array $courseGroup): bool => $this->isOccasionalCourseGroup($courseGroup))
                    ->values()
                    ->all();
                $dateKeys = $this->courseGroupDateSlotKeys($courseGroups);
                $regularDateKeys = $this->courseGroupDateSlotKeys($regularCourseGroups);
                $regularWeeklySlotKeys = $this->weeklyCourseGroupSlotKeys($regularCourseGroups);

                $option = [
                    'label' => $label,
                    'course' => $course,
                    'course_groups' => $courseGroups,
                    'date_keys' => $dateKeys,
                    'regular_date_keys' => $regularDateKeys,
                    'regular_weekly_slot_keys' => $regularWeeklySlotKeys,
                    'all_date_summary' => $this->dateKeySummary($dateKeys),
                    'regular_date_summary' => $this->dateKeySummary($regularDateKeys),
                    'regular_weekly_slot_summary' => $this->dateKeySummary($regularWeeklySlotKeys),
                ];

                return [
                    ...$option,
                    'quality_state' => $this->qualityStateForOption($option),
                ];
            })
            ->sortBy(fn (array $option): string => $this->optionSortValue($option))
            ->values()
            ->all();

        return $this->runtimeCache['courseOptionsBeforeDeselection'][$cacheKey] = $options;
    }

    /**
     * @param  array<string, mixed>  $courseGroup
     * @param  array<string, mixed>  $settings
     */
    private function courseGroupAvailable(array $courseGroup, array $settings): bool
    {
        $weekday = (int) ($courseGroup['weekday'] ?? 0);
        $hour = (int) ($courseGroup['hour'] ?? 0);

        return $this->constraintValueSelected($settings, 'availableWeekdays', $weekday)
            && $this->constraintValueSelected($settings, 'availableTimes', $hour)
            && ! $this->constraintValueSelected($settings, 'excludedWeekdayTimes', "{$weekday}-{$hour}");
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function constraintValueSelected(array $settings, string $key, int|string $value): bool
    {
        $values = data_get($settings, "constraints.{$key}", []);

        if (! is_array($values)) {
            return false;
        }

        return collect($values)
            ->contains(fn (mixed $item): bool => (string) $item === (string) $value);
    }

    /**
     * @param  array<string, mixed>  $subject
     * @param  list<array<string, mixed>>  $subjectMappings
     * @param  list<array<string, mixed>>  $courseGroups
     * @param  array<string, mixed>  $settings
     * @return list<array<string, mixed>>
     */
    private function courseVariantsFromSubject(
        array $subject,
        array $subjectMappings,
        array $courseGroups,
        array $settings,
        string $selectedCourseSettingsKey,
    ): array {
        if ($this->selectedModulesAreAuthoritative($settings)) {
            return collect($this->subjectCourseVariants($subject))
                ->map(function (array $variant) use ($subjectMappings, $courseGroups, $settings, $selectedCourseSettingsKey): ?array {
                    $authoritativeCourseCode = $this->authoritativeSelectedCourseCode(
                        $variant,
                        $subjectMappings,
                        $settings,
                        $selectedCourseSettingsKey,
                    );

                    return $authoritativeCourseCode === null
                        ? null
                        : $this->courseFromSubject(
                            $variant,
                            $subjectMappings,
                            $courseGroups,
                            $settings,
                            $authoritativeCourseCode,
                        );
                })
                ->filter(fn (mixed $course): bool => is_array($course))
                ->values()
                ->all();
        }

        return collect($this->subjectCourseVariants($subject))
            ->map(fn (array $variant): array => $this->courseFromSubject($variant, $subjectMappings, $courseGroups, $settings))
            ->all();
    }

    /**
     * @param  array<string, mixed>  $subject
     * @return list<array<string, mixed>>
     */
    private function subjectCourseVariants(array $subject): array
    {
        if ($this->isReligionSubject($subject) || $this->isLanguageSubject($subject)) {
            return [$subject];
        }

        $courseCodes = $this->courseCodeAliasParts($subject['json_code'] ?? '');
        if (count($courseCodes) <= 1) {
            return [$subject];
        }

        $splitHours = (float) ($subject['hours_per_week'] ?? 0) / count($courseCodes);

        return collect($courseCodes)
            ->map(fn (string $courseCode): array => [
                ...$subject,
                'json_code' => $courseCode,
                'hours_per_week' => is_finite($splitHours) ? $splitHours : ($subject['hours_per_week'] ?? 0),
            ])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $subject
     * @param  list<array<string, mixed>>  $subjectMappings
     * @param  list<array<string, mixed>>  $courseGroups
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    private function courseFromSubject(
        array $subject,
        array $subjectMappings,
        array $courseGroups,
        array $settings,
        ?string $authoritativeCourseCode = null,
    ): array {
        $courseCode = $this->selectedCourseCode($subject, $settings, $authoritativeCourseCode);
        $regularCourseHours = $settings['regular_course_hours'] ?? [];
        $regularHours = is_array($regularCourseHours)
            ? $regularCourseHours[$this->normalizedCourseCode($courseCode)] ?? null
            : null;

        return [
            'key' => implode('|', [
                $subject['id'] ?? $subject['local_id'] ?? '',
                $subject['semester'] ?? '',
                $subject['branch'] ?? 'common',
                $subject['json_code'] ?? '',
                $subject['json_subject'] ?? '',
                $subject['name'] ?? '',
                $courseCode,
            ]),
            'code' => $courseCode,
            'name' => $subject['name'] ?? $subject['json_subject'] ?? $subject['json_code'] ?? '',
            'ttCodes' => $this->selectedCourseTimetableCodes(
                $subject,
                $subjectMappings,
                $courseGroups,
                $settings,
                $authoritativeCourseCode,
            ),
            'hours' => (float) ($subject['hours_per_week'] ?? 0),
            'regular_hours' => is_numeric($regularHours) && (float) $regularHours > 0
                ? (float) $regularHours
                : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $subject
     * @param  list<array<string, mixed>>  $subjectMappings
     * @param  list<array<string, mixed>>  $courseGroups
     * @param  array<string, mixed>  $settings
     * @return list<string>
     */
    private function selectedCourseTimetableCodes(
        array $subject,
        array $subjectMappings,
        array $courseGroups,
        array $settings,
        ?string $authoritativeCourseCode = null,
    ): array {
        $moduleNumber = $this->subjectModuleNumber($subject);
        $courseCode = $this->selectedCourseCode($subject, $settings, $authoritativeCourseCode);
        $mappingCodes = collect($this->activeSubjectMappings($subjectMappings))
            ->filter(fn (array $mapping): bool => in_array(
                $this->normalizedCourseCode($mapping['json_subject'] ?? ''),
                $this->subjectMappingJsonAliases($subject, $settings, $authoritativeCourseCode),
                true,
            ))
            ->flatMap(fn (array $mapping): array => $this->timetableCodesForMappedSubject(
                (string) ($mapping['tt_subject'] ?? ''),
                $moduleNumber,
                $courseGroups,
                $subjectMappings,
            ))
            ->all();

        $fallbackCodes = $authoritativeCourseCode !== null || $this->isLanguageSubject($subject)
            ? [$courseCode]
            : $this->timetableCodesForMappedSubject(
                (string) ($subject['tt_subject'] ?? ''),
                $moduleNumber,
                $courseGroups,
                $subjectMappings,
            );

        return collect([
            $courseCode,
            ...$mappingCodes,
            ...$fallbackCodes,
        ])
            ->map(fn (string $value): string => $this->normalizedCourseCode($value))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $courseGroups
     * @param  list<array<string, mixed>>  $subjectMappings
     * @return list<string>
     */
    private function timetableCodesForMappedSubject(
        string $value,
        string $moduleNumber,
        array $courseGroups,
        array $subjectMappings,
    ): array {
        $exactCode = $this->normalizedCourseCode($value);
        $moduleCode = $this->normalizedCourseCode($this->timetableCodeWithModule($value, $moduleNumber));

        if ($exactCode === '') {
            return [];
        }

        return collect([
            $moduleCode,
            $exactCode !== $moduleCode && $this->timetableCourseCodeExists($exactCode, $courseGroups, $subjectMappings) ? $exactCode : '',
        ])
            ->filter()
            ->values()
            ->all();
    }

    private function timetableCodeWithModule(string $value, string $moduleNumber): string
    {
        if (trim($value) === '') {
            return '';
        }

        if ($moduleNumber !== '' && ! preg_match('/\d/u', $value)) {
            return "{$value}{$moduleNumber}";
        }

        return $value;
    }

    /**
     * @param  list<array<string, mixed>>  $courseGroups
     * @param  list<array<string, mixed>>  $subjectMappings
     */
    private function timetableCourseCodeExists(string $code, array $courseGroups, array $subjectMappings): bool
    {
        return collect($courseGroups)
            ->contains(fn (array $courseGroup): bool => in_array(
                $this->normalizedCourseCode($code),
                $this->courseGroupCodes($courseGroup, $subjectMappings),
                true,
            ));
    }

    /**
     * @param  array<string, mixed>  $courseGroup
     * @param  array<string, mixed>  $course
     * @param  list<array<string, mixed>>  $subjectMappings
     */
    private function courseGroupMatchesCourse(array $courseGroup, array $course, array $subjectMappings): bool
    {
        $cacheKey = $this->courseGroupCacheKey($courseGroup).'|'.$this->courseCacheKey($course);
        if (array_key_exists($cacheKey, $this->runtimeCache['courseGroupMatchesCourse'] ?? [])) {
            return $this->runtimeCache['courseGroupMatchesCourse'][$cacheKey];
        }

        $courseAliases = $this->courseAliases($course);
        if ($courseAliases === []) {
            return $this->runtimeCache['courseGroupMatchesCourse'][$cacheKey] = false;
        }

        $courseGroupCodes = $this->courseGroupCodes($courseGroup, $subjectMappings);

        if (! collect($courseAliases)->contains(fn (string $alias): bool => in_array($alias, $courseGroupCodes, true))) {
            return $this->runtimeCache['courseGroupMatchesCourse'][$cacheKey] = false;
        }

        return $this->runtimeCache['courseGroupMatchesCourse'][$cacheKey] = ! $this->courseGroupHasConflictingModule(
            $courseGroup,
            $courseAliases,
            $subjectMappings,
        );
    }

    /**
     * @param  list<string>  $courseAliases
     * @param  list<array<string, mixed>>  $subjectMappings
     */
    private function courseGroupHasConflictingModule(array $courseGroup, array $courseAliases, array $subjectMappings): bool
    {
        $aliasParts = collect($courseAliases)
            ->map(fn (string $alias): array => $this->courseCodeModuleParts($alias, $subjectMappings))
            ->filter(fn (array $parts): bool => $parts['module'] !== '')
            ->values();

        if ($aliasParts->isEmpty()) {
            return false;
        }

        return collect($this->courseGroupLeadingCodes($courseGroup))
            ->map(fn (string $code): array => $this->courseCodeModuleParts($code, $subjectMappings))
            ->filter(fn (array $parts): bool => $parts['module'] !== '')
            ->contains(function (array $parts) use ($aliasParts): bool {
                $sameBaseAliases = $aliasParts->filter(fn (array $alias): bool => $alias['base'] === $parts['base']);

                return $sameBaseAliases->isNotEmpty()
                    && ! $sameBaseAliases->contains(fn (array $alias): bool => $alias['module'] === $parts['module']);
            });
    }

    /**
     * @param  array<string, mixed>  $course
     * @return list<string>
     */
    private function courseAliases(array $course): array
    {
        $cacheKey = $this->courseCacheKey($course);

        return $this->runtimeCache['courseAliases'][$cacheKey] ??= collect([
            $course['code'] ?? '',
            ...(is_array($course['ttCodes'] ?? null) ? $course['ttCodes'] : []),
        ])
            ->flatMap(fn (string $value): array => [
                $value,
                $this->defaultTimetableCodeAlias($value),
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
     * @param  list<array<string, mixed>>  $subjectMappings
     * @return list<string>
     */
    private function courseGroupCodes(array $courseGroup, array $subjectMappings): array
    {
        $cacheKey = $this->courseGroupCacheKey($courseGroup);

        return $this->runtimeCache['courseGroupCodes'][$cacheKey] ??= collect([
            ...$this->courseGroupLeadingCodes($courseGroup),
            ...collect([
                $courseGroup['course'] ?? '',
                $courseGroup['subject'] ?? '',
            ])->flatMap(fn (string $value): array => $this->courseCodeTokensFromValue($value))->all(),
        ])
            ->map(fn (string $value): string => $this->normalizedCourseCode($value))
            ->map(fn (string $value): string => $this->normalizedCourseModuleBase($value, $subjectMappings) === $value ? $value : $this->normalizedCourseCode($value))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $courseGroup
     * @return list<string>
     */
    private function courseGroupLeadingCodes(array $courseGroup): array
    {
        $cacheKey = $this->courseGroupCacheKey($courseGroup);

        return $this->runtimeCache['courseGroupLeadingCodes'][$cacheKey] ??= collect([
            $courseGroup['class_name'] ?? '',
            $courseGroup['display_label'] ?? '',
            $courseGroup['title'] ?? '',
        ])
            ->flatMap(fn (string $value): array => $this->leadingCourseCodesFromValue($value))
            ->map(fn (string $value): string => $this->normalizedCourseCode($value))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    private function leadingCourseCodesFromValue(string $value): array
    {
        $firstSegment = trim(preg_split('/\s+-\s+|[-\s]/u', $value)[0] ?? '');

        return $this->courseCodeTokensFromValue($firstSegment);
    }

    /**
     * @return list<string>
     */
    private function courseCodeTokensFromValue(string $value): array
    {
        preg_match_all('/[A-Za-zÄÖÜäöüß]+[0-9]*/u', $value, $matches);

        return $matches[0] ?? [];
    }

    /**
     * @param  list<array<string, mixed>>  $subjectMappings
     * @return array{base: string, module: string}
     */
    private function courseCodeModuleParts(string $value, array $subjectMappings): array
    {
        $normalizedValue = $this->normalizedCourseCode($value);

        if (! preg_match('/^([A-ZÄÖÜ]+)(\d+)$/u', $normalizedValue, $match)) {
            return [
                'base' => $this->normalizedCourseModuleBase($normalizedValue, $subjectMappings),
                'module' => '',
            ];
        }

        return [
            'base' => $this->normalizedCourseModuleBase($match[1], $subjectMappings),
            'module' => $match[2],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $subjectMappings
     */
    private function normalizedCourseModuleBase(string $value, array $subjectMappings): string
    {
        $normalizedValue = $this->normalizedCourseCode($value);
        if (array_key_exists($normalizedValue, $this->runtimeCache['normalizedCourseModuleBase'] ?? [])) {
            return $this->runtimeCache['normalizedCourseModuleBase'][$normalizedValue];
        }

        $mapping = collect($this->activeSubjectMappings($subjectMappings))
            ->first(fn (array $subjectMapping): bool => in_array($normalizedValue, [
                $this->normalizedCourseCode($subjectMapping['json_subject'] ?? ''),
                $this->normalizedCourseCode($subjectMapping['tt_subject'] ?? ''),
            ], true));

        return $this->runtimeCache['normalizedCourseModuleBase'][$normalizedValue] = $this->normalizedCourseCode($mapping['json_subject'] ?? '') ?: $normalizedValue;
    }

    /**
     * @param  list<array<string, mixed>>  $subjectMappings
     * @return list<array<string, mixed>>
     */
    private function activeSubjectMappings(array $subjectMappings): array
    {
        return $this->runtimeCache['activeSubjectMappings'] ??= collect($subjectMappings)
            ->filter(fn (array $mapping): bool => ($mapping['is_active'] ?? true) !== false)
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $subject
     * @param  array<string, mixed>  $settings
     * @return list<string>
     */
    private function subjectMappingJsonAliases(
        array $subject,
        array $settings,
        ?string $authoritativeCourseCode = null,
    ): array {
        if ($authoritativeCourseCode !== null) {
            $selectedCourseBase = $this->courseCodeWithoutModule($authoritativeCourseCode);

            return collect([
                $selectedCourseBase,
                $this->defaultTimetableCodeAlias($selectedCourseBase),
            ])
                ->flatMap(fn (string $value): array => $this->courseCodeAliasParts($value))
                ->map(fn (string $value): string => $this->normalizedCourseCode($value))
                ->filter()
                ->unique()
                ->values()
                ->all();
        }

        return collect([
            $this->subjectBaseKey($subject),
            $subject['json_subject'] ?? '',
            $this->courseCodeWithoutModule($subject['json_code'] ?? ''),
            $this->courseCodeWithoutModule($this->selectedCourseCode($subject, $settings)),
        ])
            ->flatMap(fn (string $value): array => $this->courseCodeAliasParts($value))
            ->map(fn (string $value): string => $this->normalizedCourseCode($value))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $subject
     * @param  array<string, mixed>  $settings
     */
    private function selectedCourseCode(
        array $subject,
        array $settings,
        ?string $authoritativeCourseCode = null,
    ): string {
        if ($authoritativeCourseCode !== null) {
            return $authoritativeCourseCode;
        }

        if ($this->isReligionSubject($subject)) {
            return (string) data_get($settings, 'selection.religion', 'ETH').$this->subjectModuleNumber($subject);
        }

        if ($this->isLanguageSubject($subject)) {
            return (string) data_get($settings, 'selection.language', 'L').$this->subjectModuleNumber($subject);
        }

        return $this->alternativeDisplay($subject['json_code'] ?? $subject['json_subject'] ?? $subject['name'] ?? '');
    }

    /**
     * @param  array<string, mixed>  $subject
     * @param  array<string, mixed>  $settings
     */
    private function subjectMatchesSelectedBranch(array $subject, array $settings): bool
    {
        $branch = (string) ($subject['branch'] ?? '');

        return $branch === '' || $branch === 'common' || $branch === (string) data_get($settings, 'selection.branch', '');
    }

    /**
     * @param  array<string, mixed>  $subject
     * @param  array<string, mixed>  $settings
     */
    private function subjectMatchesSelectedChoices(array $subject, array $settings): bool
    {
        if ($this->isArtsSubject($subject)) {
            return $this->subjectBaseKey($subject) === (string) data_get($settings, 'selection.artsSubject', 'ME');
        }

        if ($this->isLanguageSubject($subject)) {
            $languageCode = $this->languageSubjectCode($subject);

            return $languageCode === '' || $languageCode === (string) data_get($settings, 'selection.language', 'L');
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $subject
     */
    private function languageSubjectCode(array $subject): string
    {
        $baseKey = $this->normalizedCourseCode($this->subjectBaseKey($subject));

        if (in_array($baseKey, ['L', 'F', 'S'], true)) {
            return $baseKey;
        }

        if ($baseKey !== 'L/F/S') {
            return '';
        }

        $jsonCodeParts = collect($this->courseCodeAliasParts($this->courseCodeWithoutModule($subject['json_code'] ?? '')))
            ->map(fn (string $value): string => $this->normalizedCourseCode($value))
            ->all();

        return count($jsonCodeParts) === 1 && in_array($jsonCodeParts[0], ['L', 'F', 'S'], true)
            ? $jsonCodeParts[0]
            : '';
    }

    /**
     * @param  array<string, mixed>  $subject
     */
    private function isReligionSubject(array $subject): bool
    {
        return in_array(
            $this->normalizedCourseCode($this->subjectBaseKey($subject)),
            ['R', 'R/ET'],
            true,
        );
    }

    /**
     * @param  array<string, mixed>  $subject
     */
    private function isLanguageSubject(array $subject): bool
    {
        $baseKey = $this->normalizedCourseCode($this->subjectBaseKey($subject));

        return $baseKey === 'L/F/S' || in_array($baseKey, ['L', 'F', 'S'], true);
    }

    /**
     * @param  array<string, mixed>  $subject
     */
    private function isArtsSubject(array $subject): bool
    {
        return in_array($this->subjectBaseKey($subject), ['ME', 'BE'], true);
    }

    /**
     * @param  array<string, mixed>  $subject
     */
    private function subjectBaseKey(array $subject): string
    {
        $jsonSubject = trim((string) ($subject['json_subject'] ?? ''));

        return $jsonSubject !== ''
            ? $jsonSubject
            : $this->courseCodeWithoutModule((string) ($subject['json_code'] ?? ''));
    }

    /**
     * @param  array<string, mixed>  $subject
     */
    private function subjectModuleNumber(array $subject): string
    {
        preg_match('/(\d+)$/u', (string) ($subject['json_code'] ?? ''), $match);

        return $match[1] ?? '';
    }

    private function alternativeDisplay(string $value): string
    {
        $normalizedValue = trim($value);

        if (! str_contains($normalizedValue, '/')) {
            return $normalizedValue !== '' ? $normalizedValue : '-';
        }

        return collect(explode('/', $normalizedValue))
            ->map(fn (string $part): string => trim($part))
            ->filter()
            ->implode(' / ');
    }

    /**
     * @param  array<string, mixed>  $courseGroup
     */
    private function courseGroupOptionLabel(array $courseGroup): string
    {
        return trim((string) (
            $courseGroup['class_name']
            ?? $courseGroup['display_label']
            ?? $courseGroup['title']
            ?? $courseGroup['course']
            ?? $courseGroup['subject']
            ?? ''
        ));
    }

    /**
     * @param  array<string, mixed>  $course
     * @return list<string>
     */
    private function courseGroupSelectionKeys(array $course, string $label): array
    {
        return collect([
            $course['key'] ?? '',
            $course['code'] ?? '',
        ])
            ->map(fn (mixed $courseKey): string => trim((string) $courseKey))
            ->filter()
            ->unique()
            ->map(fn (string $courseKey): string => collect([$courseKey, $label])->filter()->implode('|'))
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $selectedCourseGroupKeys
     */
    private function courseHasSelectedCourseGroupKeys(array $course, array $selectedCourseGroupKeys): bool
    {
        return collect($selectedCourseGroupKeys)
            ->contains(fn (string $selectedCourseGroupKey): bool => $this->courseGroupKeyBelongsToCourse($course, $selectedCourseGroupKey));
    }

    /**
     * @param  list<string>  $selectedCourseGroupKeys
     */
    private function courseGroupSelected(array $course, string $label, array $selectedCourseGroupKeys): bool
    {
        return collect($selectedCourseGroupKeys)
            ->contains(fn (string $selectedCourseGroupKey): bool => $this->courseGroupKeyMatchesCourseLabel($course, $label, $selectedCourseGroupKey));
    }

    /**
     * @param  array<string, mixed>  $course
     */
    private function courseGroupKeyBelongsToCourse(array $course, string $courseGroupKey): bool
    {
        [$courseKey, $label] = array_pad(explode('|', $courseGroupKey, 2), 2, '');

        if (trim($label) === '') {
            return false;
        }

        return $this->courseKeyMatchesCourse($course, $courseKey);
    }

    /**
     * @param  array<string, mixed>  $course
     */
    private function courseGroupKeyMatchesCourseLabel(array $course, string $label, string $courseGroupKey): bool
    {
        [$courseKey, $courseGroupLabel] = array_pad(explode('|', $courseGroupKey, 2), 2, '');

        return trim($courseGroupLabel) === $label
            && $this->courseKeyMatchesCourse($course, $courseKey);
    }

    /**
     * @param  array<string, mixed>  $course
     */
    private function courseKeyMatchesCourse(array $course, string $courseKey): bool
    {
        $courseKey = trim($courseKey);

        if ($courseKey === '') {
            return false;
        }

        if (in_array($courseKey, [
            (string) ($course['key'] ?? ''),
            (string) ($course['code'] ?? ''),
        ], true)) {
            return true;
        }

        $courseCode = $this->selectedCourseCodeFromKey($courseKey);

        return $courseCode !== '' && in_array($courseCode, $this->courseAliases($course), true);
    }

    /**
     * @param  array<string, mixed>  $course
     * @param  list<string>  $deselectedCourseGroupKeys
     */
    private function courseGroupDeselected(array $course, string $label, array $deselectedCourseGroupKeys): bool
    {
        $selectionKeySet = array_flip($this->courseGroupSelectionKeys($course, $label));

        return collect($deselectedCourseGroupKeys)
            ->contains(function (string $deselectedCourseGroupKey) use ($course, $label, $selectionKeySet): bool {
                if (isset($selectionKeySet[$deselectedCourseGroupKey])) {
                    return true;
                }

                return $this->courseGroupDeselectedByEquivalentCourseKey($course, $label, $deselectedCourseGroupKey);
            });
    }

    /**
     * @param  array<string, mixed>  $course
     */
    private function courseGroupDeselectedByEquivalentCourseKey(array $course, string $label, string $deselectedCourseGroupKey): bool
    {
        $suffix = "|{$label}";
        if (! str_ends_with($deselectedCourseGroupKey, $suffix)) {
            return false;
        }

        $deselectedCourseKey = substr($deselectedCourseGroupKey, 0, -strlen($suffix));
        $deselectedCourseCode = $this->selectedCourseCodeFromKey($deselectedCourseKey);
        if ($deselectedCourseCode === '') {
            return false;
        }

        return in_array($deselectedCourseCode, $this->courseAliases($course), true);
    }

    /**
     * @param  list<array<string, mixed>>  $courseGroups
     * @return list<string>
     */
    private function courseGroupDateSlotKeys(array $courseGroups): array
    {
        return collect($courseGroups)
            ->flatMap(fn (array $courseGroup): array => $this->courseGroupDateSlotKeysForGroup($courseGroup))
            ->values()
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $courseGroups
     * @return list<string>
     */
    private function weeklyCourseGroupSlotKeys(array $courseGroups): array
    {
        return collect($courseGroups)
            ->filter(fn (array $courseGroup): bool => $this->courseGroupDates($courseGroup) === [])
            ->map(fn (array $courseGroup): string => 'weekly|'.($courseGroup['weekday'] ?? '').'|'.($courseGroup['hour'] ?? ''))
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $courseGroup
     * @return list<string>
     */
    private function courseGroupDateSlotKeysForGroup(array $courseGroup): array
    {
        $cacheKey = $this->courseGroupCacheKey($courseGroup);
        if (array_key_exists($cacheKey, $this->runtimeCache['courseGroupDateSlotKeysForGroup'] ?? [])) {
            return $this->runtimeCache['courseGroupDateSlotKeysForGroup'][$cacheKey];
        }

        $weekday = (string) ($courseGroup['weekday'] ?? '');
        $hour = (string) ($courseGroup['hour'] ?? '');
        $dates = $this->courseGroupDates($courseGroup);

        if ($dates === []) {
            return $this->runtimeCache['courseGroupDateSlotKeysForGroup'][$cacheKey] = ["weekly|{$weekday}|{$hour}"];
        }

        $dateKeys = collect($dates)
            ->map(fn (string $date): string => "date|{$date}|{$weekday}|{$hour}")
            ->all();

        if ($this->isOccasionalCourseGroup($courseGroup)) {
            return $this->runtimeCache['courseGroupDateSlotKeysForGroup'][$cacheKey] = $dateKeys;
        }

        if (! $this->usesContinuousRegularDateRange($courseGroup)) {
            return $this->runtimeCache['courseGroupDateSlotKeysForGroup'][$cacheKey] = $dateKeys;
        }

        $rangeStart = $this->dateMonthDayOrdinal($dates[0] ?? '');
        $rangeEnd = $this->dateMonthDayOrdinal($dates[count($dates) - 1] ?? '');

        if ($rangeStart === null || $rangeEnd === null) {
            return $this->runtimeCache['courseGroupDateSlotKeysForGroup'][$cacheKey] = $dateKeys;
        }

        return $this->runtimeCache['courseGroupDateSlotKeysForGroup'][$cacheKey] = [
            ...$dateKeys,
            "range|{$rangeStart}|{$rangeEnd}|{$weekday}|{$hour}",
        ];
    }

    /**
     * @param  array<string, mixed>  $courseGroup
     */
    private function isOccasionalCourseGroup(array $courseGroup): bool
    {
        $datesCount = $this->courseGroupDatesCount($courseGroup);

        return $datesCount !== null && $datesCount > 0 && $datesCount <= 2;
    }

    /**
     * @param  array<string, mixed>  $courseGroup
     */
    private function hasInactiveRememberedDates(array $courseGroup): bool
    {
        return ($courseGroup['has_inactive_remembered_dates'] ?? false) === true;
    }

    /**
     * @param  array<string, mixed>  $courseGroup
     */
    private function usesContinuousRegularDateRange(array $courseGroup): bool
    {
        if ($this->hasInactiveRememberedDates($courseGroup)) {
            return false;
        }

        if (is_numeric($courseGroup['recurrence_interval'] ?? null)) {
            return (int) $courseGroup['recurrence_interval'] <= 1;
        }

        $recurrenceType = mb_strtolower(trim((string) ($courseGroup['recurrence_type'] ?? '')));

        return $recurrenceType === '' || $recurrenceType === 'weekly';
    }

    /**
     * @param  array<string, mixed>  $courseGroup
     */
    private function courseGroupDatesCount(array $courseGroup): ?int
    {
        if (is_numeric($courseGroup['dates_count'] ?? null)) {
            return (int) $courseGroup['dates_count'];
        }

        if (is_array($courseGroup['dates'] ?? null)) {
            return collect($courseGroup['dates'])->filter()->count();
        }

        return null;
    }

    /**
     * @param  list<string>  $dateKeys
     * @return array{weekly: array<string, true>, dated: array<string, true>, dated_weekly: array<string, true>, has_overlap: bool}
     */
    private function dateKeySummary(array $dateKeys): array
    {
        $signature = implode("\n", $dateKeys);
        if (array_key_exists($signature, $this->runtimeCache['dateKeySummary'] ?? [])) {
            return $this->runtimeCache['dateKeySummary'][$signature];
        }

        return $this->runtimeCache['dateKeySummary'][$signature] = $this->dateSlotOverlapService->summarize($dateKeys);
    }

    /**
     * @return array{
     *     weekly: array<string, true>,
     *     dated: array<string, true>,
     *     dated_weekly: array<string, true>,
     *     ranges: array<string, list<array{start: int, end: int}>>,
     *     has_overlap: bool
     * }
     */
    private function emptyDateKeySummary(): array
    {
        return $this->dateSlotOverlapService->emptySummary();
    }

    /**
     * @param  array{weekly: array<string, true>, dated: array<string, true>, dated_weekly: array<string, true>, has_overlap: bool}  $firstSummary
     * @param  array{weekly: array<string, true>, dated: array<string, true>, dated_weekly: array<string, true>, has_overlap: bool}  $secondSummary
     */
    private function dateKeySummariesOverlap(array $firstSummary, array $secondSummary): bool
    {
        return $this->dateSlotOverlapService->overlaps($firstSummary, $secondSummary);
    }

    /**
     * @param  array{weekly: array<string, true>, dated: array<string, true>, dated_weekly: array<string, true>, has_overlap: bool}  $firstSummary
     * @param  array{weekly: array<string, true>, dated: array<string, true>, dated_weekly: array<string, true>, has_overlap: bool}  $secondSummary
     * @return array{weekly: array<string, true>, dated: array<string, true>, dated_weekly: array<string, true>, has_overlap: bool}
     */
    private function mergeDateKeySummaries(array $firstSummary, array $secondSummary): array
    {
        return $this->dateSlotOverlapService->merge($firstSummary, $secondSummary);
    }

    private function dateMonthDayOrdinal(string $date): ?int
    {
        if (preg_match('/^(?:\d{4}-)?(?<month>\d{1,2})-(?<day>\d{1,2})$/u', $date, $match) === 1) {
            return ((int) $match['month'] * 31) + (int) $match['day'];
        }

        if (preg_match('/^(?<day>\d{1,2})\.(?<month>\d{1,2})\.?$/u', $date, $match) === 1) {
            return ((int) $match['month'] * 31) + (int) $match['day'];
        }

        return null;
    }

    /**
     * @param  array{label: string, course_groups: list<array<string, mixed>>, date_keys: list<string>}  $option
     */
    private function optionSortValue(array $option): string
    {
        $firstCourseGroup = $option['course_groups'][0] ?? [];

        return implode('|', [
            str_pad((string) ($firstCourseGroup['weekday'] ?? 99), 2, '0', STR_PAD_LEFT),
            str_pad((string) ($firstCourseGroup['hour'] ?? 99), 2, '0', STR_PAD_LEFT),
            $option['label'],
        ]);
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

    private function normalizedCourseCode(string $value): string
    {
        return $this->runtimeCache['normalizedCourseCode'][$value] ??= (preg_replace('/\s+/u', '', mb_strtoupper(trim($value), 'UTF-8')) ?: '');
    }

    private function resetRuntimeCache(): void
    {
        $this->runtimeCache = [];
    }

    /**
     * @param  array<string, mixed>  $courseGroup
     */
    private function courseGroupCacheKey(array $courseGroup): string
    {
        return hash('sha256', serialize([
            'key' => $courseGroup['key'] ?? '',
            'semester' => $courseGroup['semester'] ?? '',
            'weekday' => $courseGroup['weekday'] ?? '',
            'hour' => $courseGroup['hour'] ?? '',
            'dates_count' => $courseGroup['dates_count'] ?? '',
            'recurrence_type' => $courseGroup['recurrence_type'] ?? '',
            'recurrence_interval' => $courseGroup['recurrence_interval'] ?? '',
            'has_inactive_remembered_dates' => (bool) ($courseGroup['has_inactive_remembered_dates'] ?? false),
            'class_name' => $courseGroup['class_name'] ?? '',
            'display_label' => $courseGroup['display_label'] ?? '',
            'title' => $courseGroup['title'] ?? '',
            'course' => $courseGroup['course'] ?? '',
            'module_code' => $courseGroup['module_code'] ?? '',
            'subject' => $courseGroup['subject'] ?? '',
            'dates' => is_array($courseGroup['dates'] ?? null) ? $courseGroup['dates'] : [],
        ]));
    }

    /**
     * @param  array<string, mixed>  $course
     */
    private function courseCacheKey(array $course): string
    {
        return hash('sha256', serialize([
            'key' => $course['key'] ?? '',
            'code' => $course['code'] ?? '',
            'ttCode' => $course['ttCode'] ?? '',
            'ttCodes' => is_array($course['ttCodes'] ?? null) ? $course['ttCodes'] : [],
        ]));
    }

    private function courseCodeWithoutModule(string $value): string
    {
        return preg_replace('/\d+$/u', '', $value) ?: '';
    }

    /**
     * @param  list<string>  $values
     * @return list<string>
     */
    private function uniqueStrings(array $values): array
    {
        return collect($values)
            ->map(fn (string $value): string => trim($value))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    private function stringList(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }

        return collect($values)
            ->map(fn (mixed $value): string => trim((string) $value))
            ->filter()
            ->values()
            ->all();
    }
}
