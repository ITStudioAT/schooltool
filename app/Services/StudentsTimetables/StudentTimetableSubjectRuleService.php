<?php

namespace App\Services\StudentsTimetables;

use App\Enums\StudentTimetableStudyProgram;
use App\Models\Schoolyear;
use App\Models\StudentTimetableSubjectRow;
use App\Models\StudentTimetableSubjectRuleSet;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StudentTimetableSubjectRuleService
{
    public function ruleSet(
        int $schoolId,
        int $schoolyearId,
        StudentTimetableStudyProgram $studyProgram,
    ): ?StudentTimetableSubjectRuleSet {
        return StudentTimetableSubjectRuleSet::query()
            ->forPlan($schoolId, $schoolyearId, $studyProgram)
            ->first();
    }

    public function evaluator(
        int $schoolId,
        int $schoolyearId,
        StudentTimetableStudyProgram $studyProgram,
    ): ?SubjectPlanRuleEvaluator {
        $ruleSet = $this->ruleSet($schoolId, $schoolyearId, $studyProgram);

        return $ruleSet ? new SubjectPlanRuleEvaluator($ruleSet->rules ?? []) : null;
    }

    /** @return array{version: int, rules: list<array<string, mixed>>} */
    public function payload(
        int $schoolId,
        int $schoolyearId,
        StudentTimetableStudyProgram $studyProgram,
    ): array {
        $ruleSet = $this->ruleSet($schoolId, $schoolyearId, $studyProgram);

        return [
            'version' => $ruleSet?->version ?? 0,
            'rules' => array_values($ruleSet?->rules ?? []),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rules
     * @return array{version: int, rules: list<array<string, mixed>>}
     */
    public function replace(
        int $schoolId,
        int $schoolyearId,
        StudentTimetableStudyProgram $studyProgram,
        int $userId,
        int $expectedVersion,
        array $rules,
    ): array {
        return DB::transaction(function () use (
            $schoolId,
            $schoolyearId,
            $studyProgram,
            $userId,
            $expectedVersion,
            $rules,
        ): array {
            Schoolyear::query()
                ->whereKey($schoolyearId)
                ->where('school_id', $schoolId)
                ->lockForUpdate()
                ->firstOrFail();

            $subjectRows = StudentTimetableSubjectRow::query()
                ->forStudyProgram($studyProgram)
                ->where('school_id', $schoolId)
                ->where('schoolyear_id', $schoolyearId)
                ->get(['stable_key', 'is_active']);
            $subjectKeys = $subjectRows->pluck('stable_key');
            $referencedKeys = collect($rules)
                ->flatMap(fn (array $rule): Collection => collect($rule['options'] ?? [])
                    ->flatMap(fn (array $option): array => $option['subject_keys'] ?? []))
                ->unique();

            if ($referencedKeys->diff($subjectKeys)->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'rules' => 'Mindestens ein zugeordnetes Fach gehört nicht zu diesem Schuljahr und Studienprogramm.',
                ]);
            }

            $inactiveSubjectKeys = $subjectRows->where('is_active', false)->pluck('stable_key');
            $activeRuleReferences = collect($rules)
                ->filter(fn (array $rule): bool => ($rule['is_active'] ?? false) === true)
                ->flatMap(fn (array $rule): Collection => collect($rule['options'] ?? [])
                    ->flatMap(fn (array $option): array => $option['subject_keys'] ?? []));

            if ($activeRuleReferences->intersect($inactiveSubjectKeys)->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'rules' => 'Aktive Regeln dürfen keine inaktiven Fächer verwenden.',
                ]);
            }

            $ruleSet = StudentTimetableSubjectRuleSet::query()
                ->forPlan($schoolId, $schoolyearId, $studyProgram)
                ->lockForUpdate()
                ->first();
            $currentVersion = $ruleSet?->version ?? 0;

            if ($currentVersion !== $expectedVersion) {
                throw new HttpResponseException(response()->json([
                    'message' => 'Die Regeln wurden inzwischen geändert. Bitte laden Sie den aktuellen Stand.',
                    'current_version' => $currentVersion,
                ], 409));
            }

            $ruleSet ??= new StudentTimetableSubjectRuleSet;
            $ruleSet->forceFill([
                'school_id' => $schoolId,
                'schoolyear_id' => $schoolyearId,
                'study_program' => $studyProgram->value,
                'version' => $currentVersion + 1,
                'rules' => array_values($rules),
                'updated_by_user_id' => $userId,
            ])->save();

            return [
                'version' => $ruleSet->version,
                'rules' => array_values($ruleSet->rules ?? []),
            ];
        });
    }

    public function ensureDefaultRuleSet(
        int $schoolId,
        int $schoolyearId,
        StudentTimetableStudyProgram $studyProgram,
        ?int $userId = null,
    ): bool {
        return DB::transaction(function () use ($schoolId, $schoolyearId, $studyProgram, $userId): bool {
            Schoolyear::query()
                ->whereKey($schoolyearId)
                ->where('school_id', $schoolId)
                ->lockForUpdate()
                ->firstOrFail();

            if (StudentTimetableSubjectRuleSet::query()->forPlan($schoolId, $schoolyearId, $studyProgram)->lockForUpdate()->exists()) {
                return false;
            }

            $rows = StudentTimetableSubjectRow::query()
                ->forStudyProgram($studyProgram)
                ->where('school_id', $schoolId)
                ->where('schoolyear_id', $schoolyearId)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get();

            if ($rows->isEmpty()) {
                return false;
            }

            StudentTimetableSubjectRuleSet::query()->create([
                'school_id' => $schoolId,
                'schoolyear_id' => $schoolyearId,
                'study_program' => $studyProgram->value,
                'version' => 1,
                'rules' => $this->defaultRules($rows),
                'updated_by_user_id' => $userId,
            ]);

            return true;
        });
    }

    /**
     * @param  Collection<int, StudentTimetableSubjectRow>  $rows
     * @return list<array<string, mixed>>
     */
    public function defaultRules(Collection $rows): array
    {
        $option = static fn (string $value, string $label, Collection $subjectRows, ?string $courseCodePrefix = null): array => [
            'stable_key' => (string) Str::uuid(),
            'value' => $value,
            'label' => $label,
            'course_code_prefix' => $courseCodePrefix,
            'subject_keys' => $subjectRows->pluck('stable_key')->unique()->values()->all(),
        ];
        $rule = static fn (string $name, string $label, string $selectionKey, array $options): array => [
            'stable_key' => (string) Str::uuid(),
            'name' => $name,
            'label' => $label,
            'selection_key' => $selectionKey,
            'selection_mode' => 'single',
            'min_selections' => 1,
            'max_selections' => 1,
            'conditions' => [],
            'is_active' => true,
            'options' => array_values($options),
        ];
        $rules = [];
        $branchLabels = [
            'wirtschaftskundlich' => 'Wirtschaftskundlicher Zweig',
            'gymnasial' => 'Gymnasialer Zweig',
        ];
        $branchOptions = $rows
            ->filter(fn (StudentTimetableSubjectRow $row): bool => ! in_array(trim((string) $row->branch), ['', 'common'], true))
            ->groupBy('branch')
            ->map(fn (Collection $branchRows, string $branch): array => $option(
                $branch,
                $branchLabels[$branch] ?? $branch,
                $branchRows,
            ))
            ->values()
            ->all();

        if (count($branchOptions) >= 2) {
            $rules[] = $rule('Zweig', 'Zweig', 'branch', $branchOptions);
        }

        $rules = $this->appendCodeRule(
            $rules,
            $rows,
            $rule,
            $option,
            'Sprache',
            'Sprache',
            'language',
            ['L' => 'L - Latein', 'F' => 'F - Französisch', 'S' => 'S - Spanisch'],
            ['L/F/S'],
        );
        $artsRows = $rows->filter(fn (StudentTimetableSubjectRow $row): bool => $this->artsRuleControlsRow($row));
        $rules = $this->appendCodeRule(
            $rules,
            $artsRows,
            $rule,
            $option,
            'Künstlerisches Fach',
            'ME / BE',
            'arts_subject',
            ['ME' => 'ME - Musikerziehung', 'BE' => 'BE - Bildnerische Erziehung'],
        );

        $religionRows = $rows->filter(fn (StudentTimetableSubjectRow $row): bool => in_array($this->subjectBase($row), ['R/ET', 'R', 'ET', 'ETH'], true));
        $religionLabels = [
            'ETH' => 'Ethik',
            'Rev' => 'Evangelisch',
            'Ris' => 'Islamisch',
            'Rk' => 'Katholisch',
            'Ror' => 'Orthodox',
        ];
        $religionOptions = collect($religionLabels)
            ->map(function (string $label, string $value) use ($religionRows, $option): array {
                $matchingRows = $religionRows->filter(function (StudentTimetableSubjectRow $row) use ($value): bool {
                    $base = $this->subjectBase($row);

                    return $base === 'R/ET'
                        || ($value === 'ETH' ? in_array($base, ['ET', 'ETH'], true) : $base === 'R');
                });

                return $option($value, $label, $matchingRows, $value);
            })
            ->filter(fn (array $religionOption): bool => $religionOption['subject_keys'] !== [])
            ->values()
            ->all();

        if (count($religionOptions) >= 2) {
            $rules[] = $rule('Ethik / Religion', 'Ethik / Religion', 'religion', $religionOptions);
        }

        return $rules;
    }

    /**
     * @param  list<array<string, mixed>>  $rules
     * @param  Collection<int, StudentTimetableSubjectRow>  $rows
     * @param  array<string, string>  $labels
     * @param  list<string>  $genericBases
     * @return list<array<string, mixed>>
     */
    private function appendCodeRule(
        array $rules,
        Collection $rows,
        callable $rule,
        callable $option,
        string $name,
        string $label,
        string $selectionKey,
        array $labels,
        array $genericBases = [],
    ): array {
        $options = collect($labels)
            ->map(function (string $optionLabel, string $value) use ($rows, $option, $genericBases): array {
                $matchingRows = $rows->filter(
                    fn (StudentTimetableSubjectRow $row): bool => in_array($this->subjectBase($row), [$value, ...$genericBases], true),
                );

                return $option(
                    $value,
                    $optionLabel,
                    $matchingRows,
                    $matchingRows->contains(fn (StudentTimetableSubjectRow $row): bool => in_array($this->subjectBase($row), $genericBases, true))
                        ? $value
                        : null,
                );
            })
            ->filter(fn (array $codeOption): bool => $codeOption['subject_keys'] !== [])
            ->values()
            ->all();

        if (count($options) >= 2) {
            $rules[] = $rule($name, $label, $selectionKey, $options);
        }

        return $rules;
    }

    private function subjectBase(StudentTimetableSubjectRow $row): string
    {
        $base = trim((string) ($row->json_subject ?: $row->json_code));

        return mb_strtoupper(preg_replace('/\d+$/u', '', $base) ?: $base, 'UTF-8');
    }

    private function artsRuleControlsRow(StudentTimetableSubjectRow $row): bool
    {
        if (! in_array($this->subjectBase($row), ['BE', 'ME'], true)) {
            return false;
        }

        preg_match('/(\d+)$/u', trim((string) $row->json_code), $matches);
        $moduleNumber = (int) ($matches[1] ?? 0);
        $branch = trim((string) $row->branch);

        return ($branch === 'wirtschaftskundlich' && $moduleNumber === 1)
            || ($branch === 'gymnasial' && $moduleNumber === 2);
    }
}
