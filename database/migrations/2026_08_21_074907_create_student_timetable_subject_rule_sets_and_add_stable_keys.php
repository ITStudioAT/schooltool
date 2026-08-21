<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('student_timetable_subject_rows', function (Blueprint $table) {
            $table->uuid('stable_key')->nullable()->after('study_program');
        });

        DB::table('student_timetable_subject_rows')
            ->select('id')
            ->orderBy('id')
            ->eachById(function (object $row): void {
                DB::table('student_timetable_subject_rows')
                    ->where('id', $row->id)
                    ->update(['stable_key' => (string) Str::uuid()]);
            });

        Schema::table('student_timetable_subject_rows', function (Blueprint $table) {
            $table->uuid('stable_key')->nullable(false)->change();
            $table->unique(
                ['school_id', 'schoolyear_id', 'study_program', 'stable_key'],
                'student_tt_subject_rows_stable_key_unique',
            );
        });

        Schema::create('student_timetable_subject_rule_sets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('schoolyear_id')->constrained()->cascadeOnDelete();
            $table->string('study_program', 32);
            $table->unsignedInteger('version')->default(1);
            $table->json('rules');
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(
                ['school_id', 'schoolyear_id', 'study_program'],
                'student_tt_subject_rule_sets_scope_unique',
            );
        });

        $this->backfillRuleSets();
    }

    private function backfillRuleSets(): void
    {
        DB::table('student_timetable_subject_rows')
            ->select(['school_id', 'schoolyear_id', 'study_program'])
            ->distinct()
            ->orderBy('school_id')
            ->orderBy('schoolyear_id')
            ->orderBy('study_program')
            ->get()
            ->each(function (object $scope): void {
                $rows = DB::table('student_timetable_subject_rows')
                    ->where('school_id', $scope->school_id)
                    ->where('schoolyear_id', $scope->schoolyear_id)
                    ->where('study_program', $scope->study_program)
                    ->orderBy('sort_order')
                    ->orderBy('id')
                    ->get();

                $rules = $this->defaultRules($rows);

                DB::table('student_timetable_subject_rule_sets')->insert([
                    'school_id' => $scope->school_id,
                    'schoolyear_id' => $scope->schoolyear_id,
                    'study_program' => $scope->study_program,
                    'version' => 1,
                    'rules' => json_encode($rules, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
    }

    /**
     * @param  Collection<int, object>  $rows
     * @return list<array<string, mixed>>
     */
    private function defaultRules($rows): array
    {
        $subjectBase = static function (object $row): string {
            $base = trim((string) ($row->json_subject ?: $row->json_code));

            return mb_strtoupper(preg_replace('/\d+$/u', '', $base) ?: $base, 'UTF-8');
        };
        $artsRuleControlsRow = static function (object $row) use ($subjectBase): bool {
            if (! in_array($subjectBase($row), ['BE', 'ME'], true)) {
                return false;
            }

            preg_match('/(\d+)$/u', trim((string) $row->json_code), $matches);
            $moduleNumber = (int) ($matches[1] ?? 0);
            $branch = trim((string) $row->branch);

            return ($branch === 'wirtschaftskundlich' && $moduleNumber === 1)
                || ($branch === 'gymnasial' && $moduleNumber === 2);
        };
        $option = static fn (string $value, string $label, array $subjectKeys, ?string $courseCodePrefix = null): array => [
            'stable_key' => (string) Str::uuid(),
            'value' => $value,
            'label' => $label,
            'course_code_prefix' => $courseCodePrefix,
            'subject_keys' => array_values(array_unique($subjectKeys)),
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
            'options' => $options,
        ];
        $rules = [];

        $branchLabels = [
            'wirtschaftskundlich' => 'Wirtschaftskundlicher Zweig',
            'gymnasial' => 'Gymnasialer Zweig',
        ];
        $branchOptions = $rows
            ->filter(fn (object $row): bool => trim((string) $row->branch) !== '' && $row->branch !== 'common')
            ->groupBy(fn (object $row): string => (string) $row->branch)
            ->map(fn ($branchRows, string $branch): array => $option(
                $branch,
                $branchLabels[$branch] ?? $branch,
                $branchRows->pluck('stable_key')->all(),
            ))
            ->values()
            ->all();

        if (count($branchOptions) >= 2) {
            $rules[] = $rule('Zweig', 'Zweig', 'branch', $branchOptions);
        }

        $languageLabels = ['L' => 'L - Latein', 'F' => 'F - Französisch', 'S' => 'S - Spanisch'];
        $languageRows = $rows->filter(fn (object $row): bool => in_array($subjectBase($row), ['L', 'F', 'S', 'L/F/S'], true));
        $languageOptions = collect($languageLabels)
            ->map(function (string $label, string $value) use ($languageRows, $option, $subjectBase): array {
                $matchingRows = $languageRows->filter(
                    fn (object $row): bool => in_array($subjectBase($row), [$value, 'L/F/S'], true),
                );

                return $option(
                    $value,
                    $label,
                    $matchingRows->pluck('stable_key')->all(),
                    $matchingRows->contains(fn (object $row): bool => $subjectBase($row) === 'L/F/S') ? $value : null,
                );
            })
            ->filter(fn (array $languageOption): bool => $languageOption['subject_keys'] !== [])
            ->values()
            ->all();

        if (count($languageOptions) >= 2) {
            $rules[] = $rule('Sprache', 'Sprache', 'language', $languageOptions);
        }

        $artsLabels = ['ME' => 'ME - Musikerziehung', 'BE' => 'BE - Bildnerische Erziehung'];
        $artsRows = $rows->filter($artsRuleControlsRow);
        $artsOptions = collect($artsLabels)
            ->map(fn (string $label, string $value): array => $option(
                $value,
                $label,
                $artsRows->filter(fn (object $row): bool => $subjectBase($row) === $value)->pluck('stable_key')->all(),
            ))
            ->filter(fn (array $artsOption): bool => $artsOption['subject_keys'] !== [])
            ->values()
            ->all();

        if (count($artsOptions) >= 2) {
            $rules[] = $rule('Künstlerisches Fach', 'ME / BE', 'arts_subject', $artsOptions);
        }

        $religionLabels = [
            'ETH' => 'Ethik',
            'Rev' => 'Evangelisch',
            'Ris' => 'Islamisch',
            'Rk' => 'Katholisch',
            'Ror' => 'Orthodox',
        ];
        $religionRows = $rows->filter(fn (object $row): bool => in_array($subjectBase($row), ['R/ET', 'R', 'ET', 'ETH'], true));
        $religionOptions = collect($religionLabels)
            ->map(function (string $label, string $value) use ($religionRows, $option, $subjectBase): array {
                $matchingRows = $religionRows->filter(function (object $row) use ($subjectBase, $value): bool {
                    $base = $subjectBase($row);

                    return $base === 'R/ET'
                        || ($value === 'ETH' ? in_array($base, ['ET', 'ETH'], true) : $base === 'R');
                });

                return $option($value, $label, $matchingRows->pluck('stable_key')->all(), $value);
            })
            ->filter(fn (array $religionOption): bool => $religionOption['subject_keys'] !== [])
            ->values()
            ->all();

        if (count($religionOptions) >= 2) {
            $rules[] = $rule('Ethik / Religion', 'Ethik / Religion', 'religion', $religionOptions);
        }

        return $rules;
    }
};
