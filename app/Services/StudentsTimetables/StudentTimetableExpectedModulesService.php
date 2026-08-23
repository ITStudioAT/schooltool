<?php

namespace App\Services\StudentsTimetables;

use App\Enums\StudentTimetableStudyProgram;
use App\Models\StudentTimetableSubjectRow;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

class StudentTimetableExpectedModulesService
{
    /** @var array<string, Collection<int, StudentTimetableSubjectRow>> */
    private array $subjectRows = [];

    /** @var array<string, SubjectPlanRuleEvaluator|null> */
    private array $evaluators = [];

    public function __construct(
        protected StudentTimetableSubjectRuleService $subjectRuleService,
    ) {}

    /**
     * @param  array<string, mixed>  $selection
     * @param  array<string, mixed>  $courseResults
     * @return list<array{code: string, name: string, semester: int}>|null
     */
    public function forStudent(
        User $user,
        StudentTimetableStudyProgram $studyProgram,
        ?int $semester,
        array $selection,
        array $courseResults,
    ): ?array {
        if ($semester === null) {
            return null;
        }

        $unavailableModuleCodes = collect([
            ...((array) ($courseResults['completed'] ?? [])),
            ...((array) ($courseResults['negative'] ?? [])),
        ])
            ->map(fn (mixed $module): string => $this->normalizedComparisonCode(
                is_array($module) ? (string) ($module['code'] ?? '') : '',
            ))
            ->filter()
            ->unique()
            ->all();

        return $this->eligibleModules($user, $studyProgram, $selection)
            ->where('semester', '<=', $semester)
            ->reject(fn (array $module): bool => in_array(
                $this->normalizedComparisonCode($module['code']),
                $unavailableModuleCodes,
                true,
            ))
            ->unique(fn (array $module): string => $this->normalizedComparisonCode($module['code']))
            ->sort(fn (array $firstModule, array $secondModule): int => strnatcasecmp(
                $firstModule['code'],
                $secondModule['code'],
            ))
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $selection
     * @param  array<string, mixed>  $courseResults
     * @return list<array{code: string, name: string, semester: int}>
     */
    public function additionalForStudent(
        User $user,
        StudentTimetableStudyProgram $studyProgram,
        array $selection,
        array $courseResults,
    ): array {
        $latestModuleResults = $this->latestModuleResults($courseResults);

        return $this->eligibleModules($user, $studyProgram, $selection)
            ->map(function (array $module): array {
                $progressionParts = $this->moduleProgressionParts($module['code']);

                return [
                    ...$module,
                    'comparison_code' => $this->normalizedComparisonCode($module['code']),
                    'module_base' => $progressionParts['base'] ?? '',
                    'module_number' => $progressionParts['module_number'] ?? null,
                ];
            })
            ->filter(fn (array $module): bool => $module['module_base'] !== '' && $module['module_number'] !== null)
            ->unique('comparison_code')
            ->groupBy('module_base')
            ->flatMap(function (Collection $modules, string $moduleBase) use ($latestModuleResults): Collection {
                $latestModuleResult = $latestModuleResults[$moduleBase] ?? null;
                $sortedModules = $modules
                    ->sortBy([
                        ['module_number', 'asc'],
                        ['semester', 'asc'],
                        ['code', 'asc'],
                    ])
                    ->values();

                if ($latestModuleResult === null) {
                    return $sortedModules->take(2);
                }

                return $sortedModules
                    ->filter(fn (array $module): bool => $module['module_number'] > $latestModuleResult['module_number'])
                    ->take($latestModuleResult['passed'] ? 2 : 1);
            })
            ->map(fn (array $module): array => Arr::except($module, [
                'comparison_code',
                'module_base',
                'module_number',
            ]))
            ->sort(fn (array $firstModule, array $secondModule): int => strnatcasecmp(
                $firstModule['code'],
                $secondModule['code'],
            ))
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $selection
     * @return Collection<int, array{code: string, name: string, semester: int}>
     */
    private function eligibleModules(
        User $user,
        StudentTimetableStudyProgram $studyProgram,
        array $selection,
    ): Collection {
        $evaluator = $this->evaluator($user, $studyProgram);

        return $this->subjectRows($user, $studyProgram)
            ->flatMap(function (StudentTimetableSubjectRow $subjectRow) use ($evaluator, $selection, $studyProgram): array {
                if (! $this->matchesExplicitSelection($subjectRow, $selection, $studyProgram)) {
                    return [];
                }

                $codes = $evaluator
                    ? $this->evaluatedCodes($subjectRow, $selection, $evaluator)
                    : $this->fallbackCodes($subjectRow, $selection, $studyProgram);

                return collect($codes)
                    ->map(fn (string $code): array => [
                        'code' => trim($code),
                        'name' => trim((string) ($subjectRow->name ?: $subjectRow->json_subject ?: $code)),
                        'semester' => (int) $subjectRow->semester,
                    ])
                    ->filter(fn (array $module): bool => $module['code'] !== '')
                    ->all();
            });
    }

    /** @param array<string, mixed> $selection */
    private function matchesExplicitSelection(
        StudentTimetableSubjectRow $subjectRow,
        array $selection,
        StudentTimetableStudyProgram $studyProgram,
    ): bool {
        if (! $this->matchesSelectedBranch($subjectRow, $selection)) {
            return false;
        }

        $subjectBase = $this->subjectBase($subjectRow);

        if ($this->isReligionSubject($subjectBase, $studyProgram)) {
            $religion = trim((string) ($selection['religion'] ?? ''));

            return $religion !== '' && $this->religionMatchesSubject($religion, $subjectBase);
        }

        if ($this->isLanguageSubject($subjectBase)) {
            $language = mb_strtoupper(trim((string) ($selection['language'] ?? '')), 'UTF-8');

            return $language !== '' && ($subjectBase === 'L/F/S' || $subjectBase === $language);
        }

        if (in_array($subjectBase, ['BE', 'ME'], true)) {
            $artsSubject = mb_strtoupper(trim((string) ($selection['arts_subject'] ?? '')), 'UTF-8');

            return $artsSubject !== '' && $subjectBase === $artsSubject;
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $courseResults
     * @return array<string, array{module_number: int, passed: bool}>
     */
    private function latestModuleResults(array $courseResults): array
    {
        $latestModuleResults = [];
        $resultGroups = [
            ['modules' => (array) ($courseResults['negative'] ?? []), 'passed' => false],
            ['modules' => (array) ($courseResults['completed'] ?? []), 'passed' => true],
        ];

        foreach ($resultGroups as $resultGroup) {
            foreach ($resultGroup['modules'] as $module) {
                $progressionParts = $this->moduleProgressionParts(
                    is_array($module) ? (string) ($module['code'] ?? '') : '',
                );

                if ($progressionParts === null) {
                    continue;
                }

                $moduleBase = $progressionParts['base'];
                $existingResult = $latestModuleResults[$moduleBase] ?? null;
                $isLaterModule = $existingResult === null
                    || $progressionParts['module_number'] > $existingResult['module_number'];
                $isPassedRepeat = $existingResult !== null
                    && $progressionParts['module_number'] === $existingResult['module_number']
                    && $resultGroup['passed'];

                if (! $isLaterModule && ! $isPassedRepeat) {
                    continue;
                }

                $latestModuleResults[$moduleBase] = [
                    'module_number' => $progressionParts['module_number'],
                    'passed' => $resultGroup['passed'],
                ];
            }
        }

        return $latestModuleResults;
    }

    /** @return array{base: string, module_number: int}|null */
    private function moduleProgressionParts(string $code): ?array
    {
        $normalizedCode = $this->normalizedComparisonCode($code);

        if (preg_match('/^(.*?)(\d+)$/u', $normalizedCode, $matches) !== 1) {
            return null;
        }

        return [
            'base' => $matches[1],
            'module_number' => (int) $matches[2],
        ];
    }

    /** @return Collection<int, StudentTimetableSubjectRow> */
    private function subjectRows(User $user, StudentTimetableStudyProgram $studyProgram): Collection
    {
        $cacheKey = $this->planCacheKey($user, $studyProgram);

        return $this->subjectRows[$cacheKey] ??= StudentTimetableSubjectRow::query()
            ->forStudyProgram($studyProgram)
            ->where('school_id', $user->school_id)
            ->where('schoolyear_id', $user->schoolyear_id)
            ->where('is_active', true)
            ->orderBy('semester')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    private function evaluator(User $user, StudentTimetableStudyProgram $studyProgram): ?SubjectPlanRuleEvaluator
    {
        $cacheKey = $this->planCacheKey($user, $studyProgram);

        if (! array_key_exists($cacheKey, $this->evaluators)) {
            $this->evaluators[$cacheKey] = $this->subjectRuleService->evaluator(
                (int) $user->school_id,
                (int) $user->schoolyear_id,
                $studyProgram,
            );
        }

        return $this->evaluators[$cacheKey];
    }

    private function planCacheKey(User $user, StudentTimetableStudyProgram $studyProgram): string
    {
        return implode('|', [$user->school_id, $user->schoolyear_id, $studyProgram->value]);
    }

    /**
     * @param  array<string, mixed>  $selection
     * @return list<string>
     */
    private function evaluatedCodes(
        StudentTimetableSubjectRow $subjectRow,
        array $selection,
        SubjectPlanRuleEvaluator $evaluator,
    ): array {
        $evaluation = $evaluator->evaluate($subjectRow, $selection);

        if (! $evaluation['eligible']) {
            return [];
        }

        return $evaluation['resolved_codes'] ?: $this->defaultCodes($subjectRow);
    }

    /**
     * @param  array<string, mixed>  $selection
     * @return list<string>
     */
    private function fallbackCodes(
        StudentTimetableSubjectRow $subjectRow,
        array $selection,
        StudentTimetableStudyProgram $studyProgram,
    ): array {
        if (! $this->matchesSelectedBranch($subjectRow, $selection)) {
            return [];
        }

        $subjectBase = $this->subjectBase($subjectRow);
        $moduleNumber = $this->moduleNumber((string) $subjectRow->json_code);

        if ($this->isReligionSubject($subjectBase, $studyProgram)) {
            $religion = trim((string) ($selection['religion'] ?? ''));

            if ($religion === '' || ! $this->religionMatchesSubject($religion, $subjectBase)) {
                return [];
            }

            return ["{$religion}{$moduleNumber}"];
        }

        if ($this->isLanguageSubject($subjectBase)) {
            $language = mb_strtoupper(trim((string) ($selection['language'] ?? '')), 'UTF-8');

            if ($language === '' || ($subjectBase !== 'L/F/S' && $subjectBase !== $language)) {
                return [];
            }

            return ["{$language}{$moduleNumber}"];
        }

        if (in_array($subjectBase, ['BE', 'ME'], true)) {
            $artsSubject = mb_strtoupper(trim((string) ($selection['arts_subject'] ?? '')), 'UTF-8');

            if ($artsSubject === '' || $subjectBase !== $artsSubject) {
                return [];
            }
        }

        return $this->defaultCodes($subjectRow);
    }

    /** @param array<string, mixed> $selection */
    private function matchesSelectedBranch(StudentTimetableSubjectRow $subjectRow, array $selection): bool
    {
        if (in_array($this->subjectBase($subjectRow), ['BE', 'ME'], true)) {
            return true;
        }

        $branch = mb_strtolower(trim((string) $subjectRow->branch), 'UTF-8');
        $selectedBranch = mb_strtolower(trim((string) ($selection['branch'] ?? '')), 'UTF-8');

        return in_array($branch, ['', 'common'], true) || $branch === $selectedBranch;
    }

    private function religionMatchesSubject(string $religion, string $subjectBase): bool
    {
        if ($subjectBase === 'R/ET') {
            return true;
        }

        if (mb_strtoupper($religion, 'UTF-8') === 'ETH') {
            return in_array($subjectBase, ['ET', 'ETH'], true);
        }

        return $subjectBase === 'R';
    }

    private function isReligionSubject(string $subjectBase, StudentTimetableStudyProgram $studyProgram): bool
    {
        return $subjectBase === 'R/ET'
            || ($studyProgram === StudentTimetableStudyProgram::Kompaktstudium
                && in_array($subjectBase, ['R', 'ET', 'ETH'], true));
    }

    private function isLanguageSubject(string $subjectBase): bool
    {
        return $subjectBase === 'L/F/S' || in_array($subjectBase, ['L', 'F', 'S'], true);
    }

    /** @return list<string> */
    private function defaultCodes(StudentTimetableSubjectRow $subjectRow): array
    {
        return collect(explode('/', trim((string) ($subjectRow->json_code ?: $subjectRow->json_subject ?: $subjectRow->name))))
            ->map(fn (string $code): string => trim($code))
            ->filter()
            ->values()
            ->all();
    }

    private function subjectBase(StudentTimetableSubjectRow $subjectRow): string
    {
        $subject = trim((string) ($subjectRow->json_subject ?: $subjectRow->json_code));

        return mb_strtoupper(preg_replace('/\d+$/u', '', $subject) ?: $subject, 'UTF-8');
    }

    private function moduleNumber(string $code): string
    {
        preg_match('/(\d+)$/u', trim($code), $matches);

        return $matches[1] ?? '';
    }

    private function normalizedComparisonCode(string $code): string
    {
        $normalizedCode = preg_replace('/\s+/u', '', mb_strtoupper(trim($code), 'UTF-8')) ?: '';
        preg_match('/^(.*?)(\d*)$/u', $normalizedCode, $matches);
        $base = $matches[1] ?? '';
        $moduleNumber = $matches[2] ?? '';
        $canonicalBase = [
            'GPB' => 'GS',
            'GWB' => 'GW',
            'LET' => 'LPT',
            'MU' => 'ME',
            'REV' => 'R',
            'RIS' => 'R',
            'RK' => 'R',
            'ROR' => 'R',
            'SPA' => 'S',
        ][$base] ?? $base;

        return "{$canonicalBase}{$moduleNumber}";
    }
}
