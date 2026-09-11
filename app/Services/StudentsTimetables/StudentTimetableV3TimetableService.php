<?php

namespace App\Services\StudentsTimetables;

use App\Enums\StudentTimetableStudyProgram;
use App\Models\Schoolyear;
use App\Models\StudentTimetableSubjectMapping;
use App\Models\StudentTimetableSubjectRow;
use App\Models\StudentTimetableV3Timetable;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StudentTimetableV3TimetableService
{
    private const MAX_MODULES = 10;

    private const MAX_MODULE_HOURS = 30;

    private const MAX_MATERIALIZED_TIMETABLES = 2000;

    private const MAX_PERSISTED_TIMETABLE_BYTES = 8388608;

    private const ALGORITHM_VERSION = 10;

    private const PREPARING_STARTED_PROGRESS_PERCENT = 5;

    private const STUDENT_INFORMATION_PROGRESS_PERCENT = 10;

    private const COURSE_SELECTION_PROGRESS_PERCENT = 15;

    private const PREPARATION_COMPLETE_PROGRESS_PERCENT = 20;

    private const BACKEND_CHECKING_PROGRESS_PERCENT = 80;

    private const PROGRESS_STEP_PERCENT = 5;

    public const TIMETABLES_PER_PAGE = 100;

    public const MAX_TIMETABLE_PAGE = 20;

    public const CALCULATION_WEEKDAYS = [1, 2, 3, 4, 5, 6];

    public function __construct(
        private StudentTimetableV3StudentInformationService $studentInformationService,
        private StudentTimetableOverviewService $overviewService,
        private StudentTimetableRememberedTtEntryService $rememberedTtEntryService,
        private StudentTimetableCalculationSettingsService $calculationSettingsService,
        private RobotTimetableBackendSetupService $backendSetupService,
        private StudentTimetableV3TimetableStorage $timetableStorage,
        private StudentTimetableV3TimetableFilterService $timetableFilterService,
        private StudentTimetableV3SessionScope $sessionScope,
        private ?StudentTimetableSubjectRuleService $subjectRuleService = null,
    ) {}

    /**
     * @param  array<string, mixed>  $parameters
     * @return array<string, mixed>|null
     */
    public function resultForUser(
        User $authUser,
        array $parameters,
        int $page = 1,
        ?string $expectedFingerprint = null,
        array $filters = [],
    ): ?array {
        $this->validateResultPage($page);
        $this->validateExpectedFingerprint($expectedFingerprint, $page);
        $schoolyearId = $this->schoolyearIdForUser($authUser);
        $workspaceId = $this->workspaceId($parameters);
        $studentCode = $this->studentCode($parameters);
        $planningMode = $this->planningMode($parameters, $studentCode);
        $sessionIdHash = $this->sessionScope->currentHash();
        $timetable = $this->timetableForContext(
            $authUser,
            $schoolyearId,
            $this->contextKey($planningMode, $studentCode, $workspaceId),
            $sessionIdHash,
        );

        if (! $timetable) {
            return null;
        }

        if (
            $expectedFingerprint !== null
            && ! hash_equals((string) $timetable->fingerprint, $expectedFingerprint)
        ) {
            return null;
        }

        $summary = $timetable->summary;

        if (! is_array($summary) || ($summary['algorithm_version'] ?? null) !== self::ALGORITHM_VERSION) {
            return null;
        }

        $modules = $timetable->modules;
        $storedParameters = $timetable->parameters;

        if (! is_array($modules) || ! is_array($storedParameters)) {
            return null;
        }

        try {
            $generationContext = $this->resolvedGenerationContext(
                $authUser,
                $modules,
                $storedParameters,
                $schoolyearId,
                false,
            );
        } catch (ValidationException) {
            return null;
        }

        if ($generationContext['planning_mode'] !== $planningMode) {
            return null;
        }

        if ($generationContext['student_code'] !== $studentCode) {
            return null;
        }

        if (! hash_equals((string) $timetable->fingerprint, $generationContext['fingerprint'])) {
            return null;
        }

        $storedTimetables = $timetable->timetables;

        if (! is_array($storedTimetables)) {
            return null;
        }

        $normalizedFilters = $this->timetableFilterService->normalize($filters);
        $timetablePage = $this->timetableStorage->expandFilteredPage(
            $storedTimetables,
            $page,
            self::TIMETABLES_PER_PAGE,
            $this->timetableFilterService->matcher($normalizedFilters),
        );
        $summaryTimetableCount = $summary['timetable_count'] ?? null;

        if (
            $timetablePage === null
            || $timetablePage['unfiltered_total'] > self::MAX_MATERIALIZED_TIMETABLES
            || ! is_int($summaryTimetableCount)
            || $summaryTimetableCount !== $timetablePage['unfiltered_total']
        ) {
            return null;
        }

        $optionCounts = $this->timetableFilterOptionCountsForStoredTimetables(
            $storedTimetables,
            $normalizedFilters,
        );

        if ($optionCounts === null) {
            return null;
        }

        if ($page > max(1, (int) ceil($timetablePage['total'] / self::TIMETABLES_PER_PAGE))) {
            return null;
        }

        return $this->result(
            $timetable,
            'reused',
            true,
            $timetablePage,
            $page,
            $optionCounts,
            $normalizedFilters,
            $timetablePage['unfiltered_total'],
        );
    }

    /**
     * @param  list<string|array<string, mixed>>  $modules
     * @param  array<string, mixed>  $parameters
     * @param  (callable(int, int, string, int): void)|null  $progressCallback
     * @return array<string, mixed>
     */
    public function createOrUpdateForUser(
        User $authUser,
        array $modules,
        array $parameters = [],
        ?callable $progressCallback = null,
    ): array {
        if ($progressCallback !== null) {
            $progressCallback(0, 0, 'preparing', 0);
        }
        $schoolyearId = $this->schoolyearIdForUser($authUser);
        $sessionIdHash = $this->sessionScope->currentHash();

        if ($progressCallback !== null) {
            $progressCallback(self::PREPARING_STARTED_PROGRESS_PERCENT, 0, 'preparing', 0);
        }

        $generationContext = $this->resolvedGenerationContext(
            $authUser,
            $modules,
            $parameters,
            $schoolyearId,
            progressCallback: $progressCallback,
        );

        return Cache::lock(
            $this->generationLockKey(
                $authUser,
                $schoolyearId,
                $sessionIdHash,
                $generationContext['context_key'],
            ),
            120,
        )->block(10, function () use (
            $authUser,
            $schoolyearId,
            $sessionIdHash,
            $generationContext,
            $progressCallback,
        ): array {
            $existingTimetable = $this->timetableForContext(
                $authUser,
                $schoolyearId,
                $generationContext['context_key'],
                $sessionIdHash,
            );

            if ($existingTimetable?->fingerprint === $generationContext['fingerprint']) {
                $storedTimetables = $existingTimetable->timetables;
                $existingSummary = is_array($existingTimetable->summary)
                    ? $existingTimetable->summary
                    : [];
                $existingSummaryTimetableCount = $existingSummary['timetable_count'] ?? null;
                $combinationCount = (int) ($existingSummary['timetable_variation_count'] ?? 0);
                $timetablePage = is_array($storedTimetables)
                    ? $this->timetableStorage->expandPage(
                        $storedTimetables,
                        1,
                        self::TIMETABLES_PER_PAGE,
                    )
                    : null;
                $optionCounts = is_array($storedTimetables)
                    ? $this->timetableFilterOptionCountsForStoredTimetables(
                        $storedTimetables,
                        $this->timetableFilterService->defaults(),
                    )
                    : null;

                if (
                    $timetablePage !== null
                    && $optionCounts !== null
                    && $timetablePage['total'] <= self::MAX_MATERIALIZED_TIMETABLES
                    && is_int($existingSummaryTimetableCount)
                    && $existingSummaryTimetableCount === $timetablePage['total']
                ) {
                    if ($progressCallback !== null) {
                        $progressCallback(85, $combinationCount, 'materializing', $combinationCount);
                        $progressCallback(90, $combinationCount, 'compacting', $combinationCount);
                    }

                    $updates = ['expires_at' => $this->sessionScope->expiresAt()];

                    if (! $this->timetableStorage->isCompact($storedTimetables)) {
                        $expandedTimetables = $this->timetableStorage->expand($storedTimetables);

                        if ($expandedTimetables !== null) {
                            $compactTimetables = $this->timetableStorage->compact($expandedTimetables);
                            $this->ensurePersistableTimetableSize($compactTimetables);
                            $updates['timetables'] = $compactTimetables;
                        }
                    }

                    if ($progressCallback !== null) {
                        $progressCallback(95, $combinationCount, 'persisting', $combinationCount);
                    }

                    $existingTimetable->update($updates);

                    $result = $this->result(
                        $existingTimetable,
                        'reused',
                        true,
                        $timetablePage,
                        1,
                        $optionCounts,
                    );

                    if ($progressCallback !== null) {
                        $progressCallback(100, $combinationCount, 'complete', $combinationCount);
                    }

                    return $result;
                }
            }

            $calculation = $this->backendSetupService->calculateAllPossibleTimetableVariations(
                subjectRows: $generationContext['subject_rows'],
                subjectMappings: $generationContext['subject_mappings'],
                courseGroups: $generationContext['selected_course_groups'],
                settings: $generationContext['settings'],
                maximumTimetables: self::MAX_MATERIALIZED_TIMETABLES,
                requiredCourseGroupsByModule: $generationContext['required_course_groups_by_module'],
                includeOneCourseRemovalCountsWhenNoPossible: true,
                calculateNoSaturdayTimetableCount: false,
                progressCallback: $this->backendProgressCallback($progressCallback),
            );
            $timetables = is_array($calculation['timetables'] ?? null)
                ? array_slice(array_values($calculation['timetables']), 0, self::MAX_MATERIALIZED_TIMETABLES)
                : [];
            $compactTimetables = $this->timetableStorage->compact($timetables);
            $this->ensurePersistableTimetableSize($compactTimetables);
            $possibleTimetableCount = (int) ($calculation['full_green_timetable_count'] ?? 0)
                + (int) ($calculation['green_timetable_count'] ?? 0);
            $summary = [
                'algorithm_version' => self::ALGORITHM_VERSION,
                'timetable_count' => count($timetables),
                'possible_timetable_count' => $possibleTimetableCount,
                'timetables_truncated' => $possibleTimetableCount > count($timetables),
                'timetable_variation_count' => (int) ($calculation['timetable_variation_count'] ?? 0),
                'full_green_timetable_count' => (int) ($calculation['full_green_timetable_count'] ?? 0),
                'green_timetable_count' => (int) ($calculation['green_timetable_count'] ?? 0),
                'conflict_timetable_count' => (int) ($calculation['red_timetable_count'] ?? 0),
                'selected_module_count' => count($generationContext['selected_modules']),
                'selected_course_group_count' => count($generationContext['selected_course_groups']),
                'problem_modules' => is_array($calculation['problem_courses'] ?? null)
                    ? array_values($calculation['problem_courses'])
                    : [],
            ];

            if ($summary['timetable_count'] === 0) {
                $summary['solution_plan'] = $this->solutionPlan(
                    $generationContext['module_payload'],
                    is_array($calculation['one_course_removal_counts'] ?? null)
                        ? $calculation['one_course_removal_counts']
                        : [],
                );
            }

            if ($progressCallback !== null) {
                $progressCallback(
                    95,
                    $summary['timetable_variation_count'],
                    'persisting',
                    $summary['timetable_variation_count'],
                );
            }
            $timetable = StudentTimetableV3Timetable::query()->updateOrCreate(
                [
                    'school_id' => $authUser->school_id,
                    'schoolyear_id' => $schoolyearId,
                    'user_id' => $authUser->id,
                    'session_id_hash' => $sessionIdHash,
                    'context_key' => $generationContext['context_key'],
                ],
                [
                    'planning_mode' => $generationContext['planning_mode'],
                    'student_code' => $generationContext['student_code'],
                    'fingerprint' => $generationContext['fingerprint'],
                    'modules' => $generationContext['module_payload'],
                    'parameters' => $generationContext['canonical_parameters'],
                    'summary' => $summary,
                    'timetables' => $compactTimetables,
                    'generated_at' => now(),
                    'expires_at' => $this->sessionScope->expiresAt(),
                ],
            );

            $result = $this->result(
                $timetable,
                $timetable->wasRecentlyCreated ? 'created' : 'updated',
                false,
                $this->pageFromExpandedTimetables($timetables, 1),
                1,
                $this->timetableFilterOptionCountsForExpandedTimetables(
                    $timetables,
                    $this->timetableFilterService->defaults(),
                ),
            );
            if ($progressCallback !== null) {
                $progressCallback(
                    100,
                    $summary['timetable_variation_count'],
                    'complete',
                    $summary['timetable_variation_count'],
                );
            }

            return $result;
        });
    }

    /**
     * @param  list<string|array<string, mixed>>  $modules
     * @param  array<string, mixed>  $parameters
     * @param  (callable(int, int, string, int): void)|null  $progressCallback
     * @return array<string, mixed>
     */
    private function resolvedGenerationContext(
        User $authUser,
        array $modules,
        array $parameters,
        int $schoolyearId,
        bool $seedCompactSubjectPlanIfMissing = false,
        ?callable $progressCallback = null,
    ): array {
        $moduleInput = $this->normalizedModuleInput($modules);
        $workspaceId = $this->workspaceId($parameters);
        $studentCode = $this->studentCode($parameters);
        $planningMode = $this->planningMode($parameters, $studentCode);
        $selectionOverride = $this->selectionOverride($parameters);
        $information = $this->studentInformationService->informationForStudent(
            $authUser,
            $studentCode,
            $selectionOverride,
            $seedCompactSubjectPlanIfMissing,
            includeAllSelectableModules: true,
        );

        if ($progressCallback !== null) {
            $progressCallback(self::STUDENT_INFORMATION_PROGRESS_PERCENT, 0, 'preparing', 0);
        }

        if ($studentCode !== null && trim((string) ($information['student_code'] ?? '')) !== $studentCode) {
            $this->invalid('student_code', 'Der ausgewählte Studierende ist nicht mehr verfügbar.');
        }

        $selectedModules = $this->selectedModules($information, $moduleInput['module_keys']);
        $selectedCourseKeys = $this->inputStringList([
            ...$moduleInput['course_keys'],
            ...$this->parameterCourseKeys($parameters),
        ], 'selected_course_keys', 500, 255);
        $resolvedCourses = $this->resolvedCourseSelection($selectedModules, $selectedCourseKeys);
        $activeCourseGroups = $this->rememberedTtEntryService->activeCourseGroupsForUser(
            $authUser,
            $this->overviewService->courseGroupsForUser($authUser),
        );
        $selectedCourseGroups = $this->selectedCourseGroups($activeCourseGroups, $resolvedCourses['keys']);

        if ($progressCallback !== null) {
            $progressCallback(self::COURSE_SELECTION_PROGRESS_PERCENT, 0, 'preparing', 0);
        }

        $studyProgram = $this->studyProgram($information);
        $subjectRows = $this->subjectRows($authUser, $schoolyearId, $studyProgram);
        $subjectRuleSetVersion = $this->subjectRuleService()
            ->ruleSet((int) $authUser->school_id, $schoolyearId, $studyProgram)?->version;
        $subjectMappings = $this->subjectMappings($authUser, $schoolyearId);
        $constraints = $this->constraints(
            $parameters,
            $this->calculationSettingsService->availableTimes($activeCourseGroups),
        );
        $selection = $this->resolvedSelection($information, $selectionOverride);
        $modulePayload = $this->modulePayload($selectedModules, $resolvedCourses['keys_by_module']);
        $requiredCourseGroupsByModule = collect($modulePayload)
            ->mapWithKeys(fn (array $module): array => [
                (string) $module['code'] => $module['selected_course_keys'],
            ])
            ->all();
        $canonicalParameters = [
            ...($workspaceId !== null ? ['workspace_id' => $workspaceId] : []),
            'planning_mode' => $planningMode,
            'student_code' => $studentCode,
            'study_program' => $studyProgram->value,
            'selection' => $selection,
            'constraints' => $constraints,
            'selected_course_keys' => $resolvedCourses['keys'],
        ];
        $settings = $this->generatorSettings(
            $selectedModules,
            $studentCode,
            $selection,
            $constraints,
        );
        $contextKey = $this->contextKey($planningMode, $studentCode, $workspaceId);
        $fingerprint = $this->fingerprint([
            'algorithm_version' => self::ALGORITHM_VERSION,
            'scope' => [
                'school_id' => (int) $authUser->school_id,
                'schoolyear_id' => $schoolyearId,
                'user_id' => (int) $authUser->id,
                'context_key' => $contextKey,
            ],
            'modules' => $modulePayload,
            'parameters' => $canonicalParameters,
            'subject_rows' => $subjectRows,
            'subject_rule_set_version' => $subjectRuleSetVersion,
            'subject_mappings' => $subjectMappings,
            'course_groups' => $selectedCourseGroups,
        ]);

        if ($progressCallback !== null) {
            $progressCallback(self::PREPARATION_COMPLETE_PROGRESS_PERCENT, 0, 'preparing', 0);
        }

        return [
            'workspace_id' => $workspaceId,
            'student_code' => $studentCode,
            'planning_mode' => $planningMode,
            'context_key' => $contextKey,
            'selected_modules' => $selectedModules,
            'selected_course_groups' => $selectedCourseGroups,
            'subject_rows' => $subjectRows,
            'subject_mappings' => $subjectMappings,
            'module_payload' => $modulePayload,
            'required_course_groups_by_module' => $requiredCourseGroupsByModule,
            'canonical_parameters' => $canonicalParameters,
            'settings' => $settings,
            'fingerprint' => $fingerprint,
        ];
    }

    /**
     * @param  (callable(int, int, string, int): void)|null  $progressCallback
     * @return (callable(int, int, string, int): void)|null
     */
    private function backendProgressCallback(?callable $progressCallback): ?callable
    {
        if ($progressCallback === null) {
            return null;
        }

        return function (
            int $progressPercent,
            int $combinationCount,
            string $phase,
            int $checkedCombinationCount,
        ) use ($progressCallback): void {
            $mappedProgressPercent = $phase === 'checking'
                ? $this->checkingProgressPercent($progressPercent)
                : $progressPercent;

            $progressCallback(
                $mappedProgressPercent,
                $combinationCount,
                $phase,
                $checkedCombinationCount,
            );
        };
    }

    private function checkingProgressPercent(int $progressPercent): int
    {
        $boundedProgressPercent = min(
            self::BACKEND_CHECKING_PROGRESS_PERCENT,
            max(0, $progressPercent),
        );
        $checkingProgressRange = self::BACKEND_CHECKING_PROGRESS_PERCENT
            - self::PREPARATION_COMPLETE_PROGRESS_PERCENT;
        $scaledProgressPercent = self::PREPARATION_COMPLETE_PROGRESS_PERCENT
            + intdiv(
                $boundedProgressPercent * $checkingProgressRange,
                self::BACKEND_CHECKING_PROGRESS_PERCENT,
            );

        return intdiv($scaledProgressPercent, self::PROGRESS_STEP_PERCENT)
            * self::PROGRESS_STEP_PERCENT;
    }

    /**
     * @param  list<string|array<string, mixed>>  $modules
     * @return array{module_keys: list<string>, course_keys: list<string>}
     */
    private function normalizedModuleInput(array $modules): array
    {
        if (count($modules) > 100) {
            $this->invalid('modules', 'Die Modulliste enthält zu viele Werte.');
        }

        $moduleKeys = [];
        $courseKeys = [];

        foreach ($modules as $module) {
            if (is_string($module)) {
                $moduleKeys[] = $module;

                continue;
            }

            if (! is_array($module)) {
                $this->invalid('modules', 'Die Module müssen als Auswahlschlüssel oder Modulobjekte übergeben werden.');
            }

            $moduleKey = $module['selection_key'] ?? $module['key'] ?? '';

            if (! is_scalar($moduleKey)) {
                $this->invalid('modules', 'Ein Modulauswahlschlüssel ist ungültig.');
            }

            $moduleKeys[] = (string) $moduleKey;
            $embeddedCourseKeys = $module['selected_course_keys'] ?? $module['course_keys'] ?? [];
            $courseKeys = [
                ...$courseKeys,
                ...$this->inputStringList($embeddedCourseKeys, 'modules', 500, 255),
            ];
        }

        $moduleKeys = $this->inputStringList($moduleKeys, 'modules', self::MAX_MODULES, 255);
        sort($moduleKeys, SORT_STRING);

        if ($moduleKeys === []) {
            $this->invalid('modules', 'Bitte wählen Sie mindestens ein Modul aus.');
        }

        return [
            'module_keys' => $moduleKeys,
            'course_keys' => $this->inputStringList($courseKeys, 'selected_course_keys', 500, 255),
        ];
    }

    /**
     * @param  array<string, mixed>  $parameters
     * @return list<string>
     */
    private function parameterCourseKeys(array $parameters): array
    {
        return $this->inputStringList(
            $parameters['selected_course_keys'] ?? [],
            'selected_course_keys',
            500,
            255,
        );
    }

    /**
     * @param  array<string, mixed>  $information
     * @param  list<string>  $selectedModuleKeys
     * @return list<array<string, mixed>>
     */
    private function selectedModules(array $information, array $selectedModuleKeys): array
    {
        $modulesByKey = collect($information['module_selection_groups'] ?? [])
            ->flatMap(fn (array $group): array => is_array($group['modules'] ?? null) ? $group['modules'] : [])
            ->filter(fn (mixed $module): bool => is_array($module))
            ->unique(fn (array $module): string => trim((string) ($module['selection_key'] ?? '')))
            ->keyBy(fn (array $module): string => trim((string) ($module['selection_key'] ?? '')));
        $unknownModuleKeys = collect($selectedModuleKeys)
            ->reject(fn (string $moduleKey): bool => $modulesByKey->has($moduleKey))
            ->values()
            ->all();

        if ($unknownModuleKeys !== []) {
            $this->invalid('modules', 'Mindestens ein ausgewähltes Modul ist nicht mehr verfügbar.');
        }

        $selectedModules = collect($selectedModuleKeys)
            ->map(fn (string $moduleKey): array => $modulesByKey->get($moduleKey))
            ->values()
            ->all();
        $hasUnknownHours = collect($selectedModules)->contains(
            fn (array $module): bool => ! is_numeric($module['hours'] ?? null) || (float) $module['hours'] <= 0,
        );

        if ($hasUnknownHours) {
            $this->invalid('modules', 'Für mindestens ein ausgewähltes Modul fehlen gültige Wochenstunden.');
        }

        $selectedHours = collect($selectedModules)
            ->sum(fn (array $module): float => (float) $module['hours']);

        if ($selectedHours > self::MAX_MODULE_HOURS) {
            $this->invalid('modules', 'Es können höchstens 30 Stunden gleichzeitig ausgewählt werden.');
        }

        return $selectedModules;
    }

    /**
     * @param  list<array<string, mixed>>  $selectedModules
     * @param  list<string>  $selectedCourseKeys
     * @return array{keys: list<string>, keys_by_module: array<string, list<string>>}
     */
    private function resolvedCourseSelection(array $selectedModules, array $selectedCourseKeys): array
    {
        if ($selectedCourseKeys === []) {
            $this->invalid('selected_course_keys', 'Bitte wählen Sie für jedes Modul mindestens einen Unterricht aus.');
        }

        $selectedCourseKeySet = array_flip($selectedCourseKeys);
        $offersByModule = [];
        $availableCourseKeys = [];

        foreach ($selectedModules as $module) {
            $moduleKey = (string) $module['selection_key'];
            $offersByModule[$moduleKey] = collect($module['courses'] ?? [])
                ->map(fn (array $course): array => $this->stringList(
                    is_array($course['keys'] ?? null) ? $course['keys'] : [$course['key'] ?? ''],
                ))
                ->filter(fn (array $courseKeys): bool => $courseKeys !== [])
                ->values()
                ->all();
            $availableCourseKeys = [
                ...$availableCourseKeys,
                ...collect($offersByModule[$moduleKey])->flatten()->all(),
            ];
        }

        $availableCourseKeySet = array_flip($this->stringList($availableCourseKeys));

        if (collect($selectedCourseKeys)->contains(fn (string $courseKey): bool => ! isset($availableCourseKeySet[$courseKey]))) {
            $this->invalid('selected_course_keys', 'Mindestens ein ausgewählter Unterricht gehört nicht zu den gewählten Modulen.');
        }

        $resolvedCourseKeys = [];
        $keysByModule = [];

        foreach ($offersByModule as $moduleKey => $offers) {
            $keysByModule[$moduleKey] = collect($offers)
                ->filter(fn (array $offerKeys): bool => collect($offerKeys)
                    ->every(fn (string $courseKey): bool => isset($selectedCourseKeySet[$courseKey])))
                ->flatten()
                ->map(fn (mixed $courseKey): string => (string) $courseKey)
                ->unique()
                ->sort()
                ->values()
                ->all();

            if ($keysByModule[$moduleKey] === []) {
                $this->invalid('selected_course_keys', 'Bitte wählen Sie für jedes Modul mindestens einen vollständigen Unterricht aus.');
            }

            $resolvedCourseKeys = [...$resolvedCourseKeys, ...$keysByModule[$moduleKey]];
        }

        $resolvedCourseKeys = $this->stringList($resolvedCourseKeys);
        sort($resolvedCourseKeys, SORT_STRING);

        if ($resolvedCourseKeys !== $selectedCourseKeys) {
            $sortedSelectedCourseKeys = $selectedCourseKeys;
            sort($sortedSelectedCourseKeys, SORT_STRING);

            if ($resolvedCourseKeys !== $sortedSelectedCourseKeys) {
                $this->invalid('selected_course_keys', 'Die Unterrichtsauswahl ist unvollständig oder nicht mehr aktuell.');
            }
        }

        return [
            'keys' => $resolvedCourseKeys,
            'keys_by_module' => $keysByModule,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $courseGroups
     * @param  list<string>  $selectedCourseKeys
     * @return list<array<string, mixed>>
     */
    private function selectedCourseGroups(array $courseGroups, array $selectedCourseKeys): array
    {
        $courseGroupsByKey = collect($courseGroups)
            ->filter(fn (array $courseGroup): bool => trim((string) ($courseGroup['key'] ?? '')) !== '')
            ->keyBy(fn (array $courseGroup): string => (string) $courseGroup['key']);

        if (collect($selectedCourseKeys)->contains(fn (string $courseKey): bool => ! $courseGroupsByKey->has($courseKey))) {
            $this->invalid('selected_course_keys', 'Mindestens ein ausgewählter Unterricht ist nicht mehr aktiv.');
        }

        return collect($selectedCourseKeys)
            ->map(fn (string $courseKey): array => $courseGroupsByKey->get($courseKey))
            ->sortBy(fn (array $courseGroup): string => (string) ($courseGroup['key'] ?? ''))
            ->values()
            ->all();
    }

    /** @param array<string, mixed> $information */
    private function studyProgram(array $information): StudentTimetableStudyProgram
    {
        $studyProgram = StudentTimetableStudyProgram::tryFrom((string) ($information['study_program'] ?? ''));

        if (! $studyProgram) {
            $this->invalid('student_code', 'Die Studienform konnte nicht ermittelt werden.');
        }

        return $studyProgram;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function subjectRows(User $authUser, int $schoolyearId, StudentTimetableStudyProgram $studyProgram): array
    {
        return StudentTimetableSubjectRow::query()
            ->forStudyProgram($studyProgram)
            ->where('school_id', $authUser->school_id)
            ->where('schoolyear_id', $schoolyearId)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (StudentTimetableSubjectRow $row): array => $row->toArray())
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function subjectMappings(User $authUser, int $schoolyearId): array
    {
        return StudentTimetableSubjectMapping::query()
            ->where('school_id', $authUser->school_id)
            ->where('schoolyear_id', $schoolyearId)
            ->orderBy('json_subject')
            ->orderBy('tt_subject')
            ->orderBy('id')
            ->get()
            ->map(fn (StudentTimetableSubjectMapping $mapping): array => $mapping->toArray())
            ->all();
    }

    /**
     * @param  array<string, mixed>  $parameters
     * @param  list<int>  $defaultAvailableTimes
     * @return array{availableWeekdays: list<int>, availableTimes: list<int>, excludedWeekdayTimes: list<string>}
     */
    private function constraints(array $parameters, array $defaultAvailableTimes): array
    {
        $constraints = $parameters['constraints'] ?? [];

        if (! is_array($constraints)) {
            $this->invalid('constraints', 'Die Zeitvorgaben müssen als Objekt übergeben werden.');
        }

        if (array_key_exists('availableWeekdays', $constraints)) {
            $this->integerList($constraints['availableWeekdays'], 1, 7, 'constraints.availableWeekdays');
        }

        $availableWeekdays = self::CALCULATION_WEEKDAYS;
        $availableTimes = array_key_exists('availableTimes', $constraints)
            ? $this->integerList($constraints['availableTimes'], 1, 20, 'constraints.availableTimes')
            : $defaultAvailableTimes;
        $excludedWeekdayTimes = $constraints['excludedWeekdayTimes'] ?? [];

        if (! is_array($excludedWeekdayTimes)) {
            $this->invalid('constraints.excludedWeekdayTimes', 'Die ausgeschlossenen Stunden müssen als Liste übergeben werden.');
        }

        $excludedWeekdayTimes = $this->inputStringList(
            $excludedWeekdayTimes,
            'constraints.excludedWeekdayTimes',
            140,
            20,
        );

        if (collect($excludedWeekdayTimes)->contains(
            fn (string $slot): bool => preg_match('/^[1-7]-(?:[1-9]|1\d|20)$/', $slot) !== 1,
        )) {
            $this->invalid('constraints.excludedWeekdayTimes', 'Eine ausgeschlossene Stunde hat ein ungültiges Format.');
        }

        sort($excludedWeekdayTimes, SORT_STRING);

        return [
            'availableWeekdays' => $availableWeekdays,
            'availableTimes' => $availableTimes,
            'excludedWeekdayTimes' => $excludedWeekdayTimes,
        ];
    }

    /**
     * @param  array<string, mixed>  $information
     * @param  array<string, mixed>  $selectionOverride
     * @return array{semester: int, religion: ?string, branch: ?string, arts_subject: ?string, language: ?string}
     */
    private function resolvedSelection(array $information, array $selectionOverride): array
    {
        $informationSelection = collect($information['selection_fields'] ?? [])
            ->filter(fn (mixed $field): bool => is_array($field))
            ->mapWithKeys(fn (array $field): array => [
                (string) ($field['key'] ?? '') => $field['selected_value'] ?? null,
            ]);
        $semester = (int) ($information['semester'] ?? 1);

        return [
            'semester' => min(20, max(1, $semester)),
            'religion' => $this->selectionValue($selectionOverride, $informationSelection, 'religion'),
            'branch' => $this->selectionValue($selectionOverride, $informationSelection, 'branch'),
            'arts_subject' => $this->selectionValue($selectionOverride, $informationSelection, 'arts_subject'),
            'language' => $this->selectionValue($selectionOverride, $informationSelection, 'language'),
        ];
    }

    /**
     * @param  Collection<string, mixed>  $informationSelection
     */
    private function selectionValue(array $selectionOverride, Collection $informationSelection, string $key): ?string
    {
        $value = array_key_exists($key, $selectionOverride)
            ? $selectionOverride[$key]
            : $informationSelection->get($key);

        return $value === null ? null : trim((string) $value);
    }

    /**
     * @param  list<array<string, mixed>>  $selectedModules
     * @param  array{semester: int, religion: ?string, branch: ?string, arts_subject: ?string, language: ?string}  $selection
     * @param  array{availableWeekdays: list<int>, availableTimes: list<int>, excludedWeekdayTimes: list<string>}  $constraints
     * @return array<string, mixed>
     */
    private function generatorSettings(
        array $selectedModules,
        ?string $studentCode,
        array $selection,
        array $constraints,
    ): array {
        $moduleCodes = collect($selectedModules)
            ->pluck('code')
            ->map(fn (mixed $moduleCode): string => trim((string) $moduleCode))
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();
        $regularCourseHours = collect($selectedModules)
            ->filter(fn (array $module): bool => is_numeric($module['hours'] ?? null) && (float) $module['hours'] > 0)
            ->mapWithKeys(fn (array $module): array => [
                preg_replace('/\s+/u', '', mb_strtoupper(trim((string) ($module['code'] ?? '')), 'UTF-8')) => (float) $module['hours'],
            ])
            ->filter(fn (float $hours, string $moduleCode): bool => $moduleCode !== '')
            ->all();

        return [
            'selection' => [
                'semester' => $selection['semester'],
                'religion' => $selection['religion'],
                'branch' => $selection['branch'],
                'artsSubject' => $selection['arts_subject'],
                'language' => $selection['language'],
            ],
            'constraints' => $constraints,
            'student' => ['studentCode' => $studentCode],
            'selected_course_keys' => $moduleCodes,
            'selected_modules_are_authoritative' => true,
            'deselected_course_keys' => [],
            'selected_course_group_keys' => [],
            'deselected_course_group_keys' => [],
            'selected_additional_course_keys' => [],
            'selected_additional_courses_required' => false,
            'regular_course_hours' => $regularCourseHours,
            'selected_timetable_type' => 'full_green',
            'selected_timetable_number' => 1,
            'selected_quality_criterion_keys' => [],
            'evaluation_criteria' => [],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $selectedModules
     * @param  array<string, list<string>>  $keysByModule
     * @return list<array<string, mixed>>
     */
    private function modulePayload(array $selectedModules, array $keysByModule): array
    {
        return collect($selectedModules)
            ->map(fn (array $module): array => [
                'selection_key' => (string) ($module['selection_key'] ?? ''),
                'code' => (string) ($module['code'] ?? ''),
                'name' => (string) ($module['name'] ?? ''),
                'hours' => is_numeric($module['hours'] ?? null) ? (float) $module['hours'] : null,
                'selected_course_keys' => $keysByModule[(string) ($module['selection_key'] ?? '')] ?? [],
            ])
            ->sortBy('selection_key')
            ->values()
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $modules
     * @param  list<array<string, mixed>>  $removalCounts
     * @return array{strategy: 'remove_one_module', module_removal_scenarios: list<array{removed_module_selection_key: string, removed_module_code: string, removed_module_name: string, possible_timetable_count: ?int, full_green_timetable_count: ?int, green_timetable_count: ?int, status: 'calculated'|'combination_limit_exceeded'}>}
     */
    private function solutionPlan(array $modules, array $removalCounts): array
    {
        $removalCountsByCode = collect($removalCounts)
            ->filter(fn (mixed $counts): bool => is_array($counts))
            ->keyBy(fn (array $counts): string => mb_strtoupper(trim((string) ($counts['removed_course_code'] ?? ''))));

        if ($removalCountsByCode->count() !== count($modules)) {
            $this->invalid('modules', 'Der Lösungsplan konnte nicht vollständig berechnet werden.');
        }

        $scenarios = collect($modules)
            ->map(function (array $module) use ($removalCountsByCode): array {
                $moduleCode = trim((string) ($module['code'] ?? ''));
                $counts = $removalCountsByCode->get(mb_strtoupper($moduleCode));

                if (! is_array($counts)) {
                    $this->invalid('modules', 'Der Lösungsplan konnte nicht vollständig berechnet werden.');
                }

                $status = ($counts['status'] ?? null) === 'combination_limit_exceeded'
                    ? 'combination_limit_exceeded'
                    : 'calculated';

                return [
                    'removed_module_selection_key' => (string) ($module['selection_key'] ?? ''),
                    'removed_module_code' => $moduleCode,
                    'removed_module_name' => (string) ($module['name'] ?? ''),
                    'possible_timetable_count' => $status === 'calculated'
                        ? (int) ($counts['possible_timetable_count'] ?? 0)
                        : null,
                    'full_green_timetable_count' => $status === 'calculated'
                        ? (int) ($counts['full_green_timetable_count'] ?? 0)
                        : null,
                    'green_timetable_count' => $status === 'calculated'
                        ? (int) ($counts['green_timetable_count'] ?? 0)
                        : null,
                    'status' => $status,
                ];
            })
            ->sort(function (array $first, array $second): int {
                $statusComparison = ($first['status'] === 'calculated' ? 0 : 1)
                    <=> ($second['status'] === 'calculated' ? 0 : 1);

                if ($statusComparison !== 0) {
                    return $statusComparison;
                }

                $possibleCountComparison = ($second['possible_timetable_count'] ?? -1)
                    <=> ($first['possible_timetable_count'] ?? -1);

                if ($possibleCountComparison !== 0) {
                    return $possibleCountComparison;
                }

                $codeComparison = strnatcasecmp(
                    $first['removed_module_code'],
                    $second['removed_module_code'],
                );

                if ($codeComparison !== 0) {
                    return $codeComparison;
                }

                return strcmp(
                    $first['removed_module_selection_key'],
                    $second['removed_module_selection_key'],
                );
            })
            ->values()
            ->all();

        return [
            'strategy' => 'remove_one_module',
            'module_removal_scenarios' => $scenarios,
        ];
    }

    /** @param array<string, mixed> $parameters */
    private function workspaceId(array $parameters): ?string
    {
        $workspaceId = trim((string) ($parameters['workspace_id'] ?? ''));

        if ($workspaceId === '') {
            return null;
        }

        if (! Str::isUuid($workspaceId)) {
            $this->invalid('workspace_id', 'Die Arbeitsbereich-ID ist ungültig.');
        }

        return $workspaceId;
    }

    /** @param array<string, mixed> $parameters */
    private function studentCode(array $parameters): ?string
    {
        $value = $parameters['student_code'] ?? '';

        if ($value !== null && ! is_scalar($value)) {
            $this->invalid('student_code', 'Der Studierendencode ist ungültig.');
        }

        $studentCode = trim((string) $value);

        if (mb_strlen($studentCode) > 255) {
            $this->invalid('student_code', 'Der Studierendencode darf höchstens 255 Zeichen enthalten.');
        }

        return $studentCode !== '' ? $studentCode : null;
    }

    /** @param array<string, mixed> $parameters */
    private function planningMode(array $parameters, ?string $studentCode): string
    {
        $planningMode = trim((string) ($parameters['planning_mode'] ?? ''));
        $planningMode = $planningMode !== ''
            ? $planningMode
            : ($studentCode === null ? 'without_student' : 'with_student');

        if (! in_array($planningMode, ['with_student', 'without_student'], true)) {
            $this->invalid('planning_mode', 'Der Planungsmodus ist ungültig.');
        }

        if ($planningMode === 'with_student' && $studentCode === null) {
            $this->invalid('student_code', 'Für diesen Planungsmodus muss ein Studierender ausgewählt sein.');
        }

        if ($planningMode === 'without_student' && $studentCode !== null) {
            $this->invalid('student_code', 'Ohne Studierenden darf kein Studierendencode übergeben werden.');
        }

        return $planningMode;
    }

    /**
     * @param  array<string, mixed>  $parameters
     * @return array<string, mixed>
     */
    private function selectionOverride(array $parameters): array
    {
        $selection = $parameters['selection'] ?? $parameters['planning_values'] ?? [];

        if (! is_array($selection)) {
            $this->invalid('selection', 'Die Planungswerte müssen als Objekt übergeben werden.');
        }

        $normalizedSelection = [];

        foreach (['religion', 'language', 'branch', 'arts_subject'] as $key) {
            if (! array_key_exists($key, $selection)) {
                continue;
            }

            $value = $selection[$key];

            if ($value !== null && ! is_scalar($value)) {
                $this->invalid("selection.{$key}", 'Der Planungswert ist ungültig.');
            }

            $normalizedValue = $value === null ? null : trim((string) $value);
            $maximumLength = $key === 'branch' ? 80 : 20;

            if ($normalizedValue !== null && mb_strlen($normalizedValue) > $maximumLength) {
                $this->invalid("selection.{$key}", "Der Planungswert darf höchstens {$maximumLength} Zeichen enthalten.");
            }

            $normalizedSelection[$key] = $normalizedValue;
        }

        return $normalizedSelection;
    }

    private function contextKey(string $planningMode, ?string $studentCode, ?string $workspaceId = null): string
    {
        if ($workspaceId !== null) {
            return 'workspace:'.hash('sha256', $workspaceId);
        }

        return $planningMode === 'without_student'
            ? 'without-student'
            : 'student:'.hash('sha256', (string) $studentCode);
    }

    private function generationLockKey(
        User $authUser,
        int $schoolyearId,
        string $sessionIdHash,
        string $contextKey,
    ): string {
        return 'students-timetables:timetable-v3:generation:'.hash('sha256', implode('|', [
            (string) $authUser->school_id,
            (string) $schoolyearId,
            (string) $authUser->id,
            $sessionIdHash,
            $contextKey,
        ]));
    }

    private function timetableForContext(
        User $authUser,
        int $schoolyearId,
        string $contextKey,
        string $sessionIdHash,
    ): ?StudentTimetableV3Timetable {
        return StudentTimetableV3Timetable::query()
            ->where('school_id', $authUser->school_id)
            ->where('schoolyear_id', $schoolyearId)
            ->where('user_id', $authUser->id)
            ->where('session_id_hash', $sessionIdHash)
            ->where('context_key', $contextKey)
            ->where('expires_at', '>', now())
            ->first();
    }

    /**
     * @param  array{items: list<array<string, mixed>>, total: int}  $timetablePage
     * @param  array{
     *     include_saturday: int,
     *     exclude_saturday: int,
     *     free_days: array{any: int, maximum: int, values: list<array{value: int, count: int}>}
     * }  $optionCounts
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function result(
        StudentTimetableV3Timetable $timetable,
        string $status,
        bool $reused,
        array $timetablePage,
        int $page,
        array $optionCounts,
        array $filters = [],
        ?int $unfilteredTotal = null,
    ): array {
        $parameters = $timetable->parameters ?? [];
        $normalizedFilters = $this->timetableFilterService->normalize($filters);
        $unfilteredTotal ??= $timetablePage['total'];

        return [
            'id' => (int) $timetable->id,
            'status' => $status,
            'reused' => $reused,
            'fingerprint' => (string) $timetable->fingerprint,
            'context' => [
                ...(isset($parameters['workspace_id']) ? ['workspace_id' => $parameters['workspace_id']] : []),
                'planning_mode' => (string) $timetable->planning_mode,
                'student_code' => $timetable->student_code,
            ],
            'modules' => $timetable->modules ?? [],
            'parameters' => $parameters,
            'summary' => $timetable->summary ?? [],
            'timetables' => $timetablePage['items'],
            'timetables_meta' => $this->timetablePageMeta(
                $timetablePage['total'],
                $unfilteredTotal,
                $page,
                $optionCounts,
                $normalizedFilters,
            ),
            'generated_at' => $timetable->generated_at?->toISOString(),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $timetables
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    private function pageFromExpandedTimetables(array $timetables, int $page): array
    {
        $offset = ($page - 1) * self::TIMETABLES_PER_PAGE;

        return [
            'items' => array_values(array_slice($timetables, $offset, self::TIMETABLES_PER_PAGE)),
            'total' => count($timetables),
        ];
    }

    /**
     * @param  array<string, mixed>|list<array<string, mixed>>  $storedTimetables
     * @param  array<string, mixed>  $filters
     * @return array{
     *     include_saturday: int,
     *     exclude_saturday: int,
     *     free_days: array{any: int, maximum: int, values: list<array{value: int, count: int}>}
     * }|null
     */
    private function timetableFilterOptionCountsForStoredTimetables(
        array $storedTimetables,
        array $filters,
    ): ?array {
        $matcherCounts = $this->timetableStorage->countMatches(
            $storedTimetables,
            $this->timetableFilterService->optionCountMatchers($filters),
        );

        if ($matcherCounts === null) {
            return null;
        }

        return $this->timetableFilterService->optionCountsFromMatcherCounts(
            $matcherCounts,
            $filters,
        );
    }

    /**
     * @param  list<array<string, mixed>>  $timetables
     * @param  array<string, mixed>  $filters
     * @return array{
     *     include_saturday: int,
     *     exclude_saturday: int,
     *     free_days: array{any: int, maximum: int, values: list<array{value: int, count: int}>}
     * }
     */
    private function timetableFilterOptionCountsForExpandedTimetables(
        array $timetables,
        array $filters,
    ): array {
        return $this->timetableFilterService->optionCounts($timetables, $filters);
    }

    /**
     * @return array{
     *     current_page: int,
     *     per_page: int,
     *     last_page: int,
     *     total: int,
     *     unfiltered_total: int,
     *     offset: int,
     *     from: int|null,
     *     to: int|null,
     *     next_page: int|null,
     *     prev_page: int|null,
     *     option_counts: array{
     *         include_saturday: int,
     *         exclude_saturday: int,
     *         free_days: array{any: int, maximum: int, values: list<array{value: int, count: int}>}
     *     },
     *     filters: array{include_saturday: bool, free_days: int|null}
     * }
     */
    private function timetablePageMeta(
        int $total,
        int $unfilteredTotal,
        int $page,
        array $optionCounts,
        array $filters,
    ): array {
        $offset = ($page - 1) * self::TIMETABLES_PER_PAGE;
        $lastPage = max(1, (int) ceil($total / self::TIMETABLES_PER_PAGE));
        $hasItems = $total > 0 && $offset < $total;

        return [
            'current_page' => $page,
            'per_page' => self::TIMETABLES_PER_PAGE,
            'last_page' => $lastPage,
            'total' => $total,
            'unfiltered_total' => $unfilteredTotal,
            'offset' => $offset,
            'from' => $hasItems ? $offset + 1 : null,
            'to' => $hasItems ? min($offset + self::TIMETABLES_PER_PAGE, $total) : null,
            'next_page' => $page < $lastPage ? $page + 1 : null,
            'prev_page' => $page > 1 ? $page - 1 : null,
            'option_counts' => $optionCounts,
            'filters' => $filters,
        ];
    }

    private function validateResultPage(int $page): void
    {
        if ($page < 1 || $page > self::MAX_TIMETABLE_PAGE) {
            $this->invalid('page', 'Die angeforderte Stundenplanseite ist ungültig.');
        }
    }

    private function validateExpectedFingerprint(?string $expectedFingerprint, int $page): void
    {
        if ($expectedFingerprint === null) {
            if ($page > 1) {
                $this->invalid('fingerprint', 'Der Stundenplanstand ist für Folgeseiten erforderlich.');
            }

            return;
        }

        if (preg_match('/\A[a-f0-9]{64}\z/', $expectedFingerprint) !== 1) {
            $this->invalid('fingerprint', 'Der Stundenplanstand ist ungültig.');
        }
    }

    /** @param array<string, mixed> $value */
    private function fingerprint(array $value): string
    {
        return hash('sha256', json_encode($this->canonicalValue($value), JSON_THROW_ON_ERROR));
    }

    private function canonicalValue(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(fn (mixed $item): mixed => $this->canonicalValue($item), $value);
        }

        ksort($value);

        return array_map(fn (mixed $item): mixed => $this->canonicalValue($item), $value);
    }

    /** @param array<string, mixed> $compactTimetables */
    private function ensurePersistableTimetableSize(array $compactTimetables): void
    {
        $encodedTimetables = json_encode($compactTimetables, JSON_THROW_ON_ERROR);

        if (strlen($encodedTimetables) > self::MAX_PERSISTED_TIMETABLE_BYTES) {
            $this->invalid(
                'modules',
                'Die kompakten Stundenplandaten sind zu groß zum Speichern. Bitte schränken Sie die Auswahl weiter ein.',
            );
        }
    }

    /**
     * @return list<int>
     */
    private function integerList(mixed $values, int $minimum, int $maximum, string $key): array
    {
        if (! is_array($values)) {
            $this->invalid($key, 'Der Wert muss eine Liste sein.');
        }

        if (count($values) > 100) {
            $this->invalid($key, 'Die Liste enthält zu viele Werte.');
        }

        $integers = [];

        foreach ($values as $value) {
            $integer = filter_var($value, FILTER_VALIDATE_INT);

            if ($integer === false || $integer < $minimum || $integer > $maximum) {
                $this->invalid($key, 'Die Liste enthält einen ungültigen Wert.');
            }

            $integers[] = $integer;
        }

        $integers = array_values(array_unique($integers));
        sort($integers, SORT_NUMERIC);

        return $integers;
    }

    /**
     * @return list<string>
     */
    private function inputStringList(
        mixed $values,
        string $key,
        int $maximumItems,
        int $maximumLength,
    ): array {
        if (! is_array($values)) {
            $this->invalid($key, 'Der Wert muss eine Liste sein.');
        }

        if (count($values) > $maximumItems) {
            $this->invalid($key, 'Die Liste enthält zu viele Werte.');
        }

        foreach ($values as $value) {
            if (! is_scalar($value) || mb_strlen(trim((string) $value)) > $maximumLength) {
                $this->invalid($key, 'Die Liste enthält einen ungültigen Wert.');
            }
        }

        return $this->stringList($values);
    }

    /**
     * @param  array<mixed>  $values
     * @return list<string>
     */
    private function stringList(array $values): array
    {
        return collect($values)
            ->map(fn (mixed $value): string => trim((string) $value))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function schoolyearIdForUser(User $authUser): int
    {
        if (! $authUser->school_id || ! $authUser->schoolyear_id) {
            abort(422, 'Bitte wählen Sie zuerst eine Schule und ein Schuljahr aus.');
        }

        $schoolyearId = (int) $authUser->schoolyear_id;
        $schoolyearBelongsToSchool = Schoolyear::query()
            ->whereKey($schoolyearId)
            ->where('school_id', $authUser->school_id)
            ->exists();

        if (! $schoolyearBelongsToSchool) {
            abort(422, 'Das ausgewählte Schuljahr gehört nicht zur ausgewählten Schule.');
        }

        return $schoolyearId;
    }

    private function subjectRuleService(): StudentTimetableSubjectRuleService
    {
        return $this->subjectRuleService ??= app(StudentTimetableSubjectRuleService::class);
    }

    private function invalid(string $key, string $message): never
    {
        throw ValidationException::withMessages([$key => $message]);
    }
}
