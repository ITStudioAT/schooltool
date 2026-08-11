<?php

namespace App\Services\StudentsTimetables;

use App\Models\User;

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
    ) {}

    /** @return array<string, mixed> */
    public function informationForStudent(User $user, ?string $studentCode, array $selectionOverride = []): array
    {
        $selectionSummary = $this->studentOverviewService->selectionSummaryForStudentCode(
            $user,
            $studentCode,
            $selectionOverride,
        );

        return [
            'student_code' => (string) data_get($selectionSummary, 'student.student_code', ''),
            'religion' => (string) data_get($selectionSummary, 'student.religion', ''),
            'instruction_type' => (string) data_get($selectionSummary, 'student.instruction_type', ''),
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
            'module_selection_groups' => $this->moduleSelectionGroups(
                (array) ($selectionSummary['module_selection_groups'] ?? []),
            ),
        ];
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
                        $hoursLabel = trim((string) ($module['hours_label'] ?? ''));

                        return [
                            'selection_key' => trim((string) ($module['selection_key'] ?? '')),
                            'code' => trim((string) ($module['code'] ?? '')),
                            'name' => trim((string) ($module['name'] ?? '')),
                            'semester' => $semester,
                            'semester_label' => $semester ? "{$semester}. Semester" : null,
                            'hours_label' => $hoursLabel !== '' ? $hoursLabel : null,
                            'status_label' => match ($groupKey) {
                                'finished' => $grade === 'B' ? 'Befreit' : 'Bestanden',
                                'negative' => 'Negativ',
                                default => null,
                            },
                            'grades' => $this->moduleGradeValues($module),
                            'selected_by_default' => (bool) ($module['selected_by_default'] ?? false),
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
