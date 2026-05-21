<?php

namespace App\Services\StudentsTimetables;

use App\Models\StudentTimetableEvaluationSetting;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class StudentTimetableEvaluationSettingsService
{
    private const SETTINGS_VERSION = 1;

    private const CRITERIA = [
        [
            'key' => 'saturday_free',
            'label' => 'Samstag kein Unterricht',
            'description' => 'Samstagstermine werden bei der späteren Bewertung vermieden.',
        ],
        [
            'key' => 'free_days',
            'label' => 'Anzahl freie Tage',
            'description' => 'Stundenpläne mit mehr freien Tagen werden höher gereiht.',
        ],
        [
            'key' => 'few_gaps',
            'label' => 'Wenig Lücken',
            'description' => 'Freistunden zwischen Unterrichtsblöcken werden vermieden.',
        ],
        [
            'key' => 'starts_from_period_10',
            'label' => 'Unterricht idealerweise ab 10. Stunde',
            'description' => 'Frühere Unterrichtsstarts werden später nachrangig bewertet.',
        ],
        [
            'key' => 'ends_by_period_13',
            'label' => 'Unterricht nicht länger als 13. Stunde',
            'description' => 'Stundenpläne mit Unterricht nach der 13. Stunde werden später nachrangig bewertet.',
        ],
    ];

    /**
     * @return array{
     *     version:int,
     *     criteria:array<int, array<string, mixed>>
     * }
     */
    public function settingsForUser(User $user): array
    {
        $setting = $this->queryForUser($user)->first();

        return $this->payloadFromStoredSettings($setting?->settings ?? []);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function activeCriteriaForUser(User $user): array
    {
        return collect($this->settingsForUser($user)['criteria'])
            ->filter(fn (array $criterion): bool => ($criterion['enabled'] ?? false) === true)
            ->sortBy('priority')
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $criteria
     * @return array<int, array<string, mixed>>
     */
    public function activeCriteriaForRun(array $criteria): array
    {
        return collect($this->payloadFromStoredSettings($this->settingsPayloadForStorage($criteria))['criteria'])
            ->filter(fn (array $criterion): bool => ($criterion['enabled'] ?? false) === true)
            ->sortBy('priority')
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $criteria
     * @return array{
     *     version:int,
     *     criteria:array<int, array<string, mixed>>
     * }
     */
    public function updateForUser(User $user, array $criteria): array
    {
        $settings = $this->settingsPayloadForStorage($criteria);

        $this->queryForUser($user)->updateOrCreate(
            [
                'school_id' => $user->school_id,
                'schoolyear_id' => $user->schoolyear_id,
            ],
            [
                'settings' => $settings,
            ],
        );

        return $this->payloadFromStoredSettings($settings);
    }

    /**
     * @return array<int, string>
     */
    public function criterionKeys(): array
    {
        return collect(self::CRITERIA)
            ->pluck('key')
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function optionValues(): array
    {
        return collect(self::CRITERIA)
            ->flatMap(fn (array $criterion): array => collect($criterion['options'] ?? [])
                ->pluck('value')
                ->all())
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return Builder<StudentTimetableEvaluationSetting>
     */
    private function queryForUser(User $user): Builder
    {
        return StudentTimetableEvaluationSetting::query()
            ->where('school_id', $user->school_id)
            ->where('schoolyear_id', $user->schoolyear_id);
    }

    /**
     * @param  array<string, mixed>  $storedSettings
     * @return array{
     *     version:int,
     *     criteria:array<int, array<string, mixed>>
     * }
     */
    private function payloadFromStoredSettings(array $storedSettings): array
    {
        $storedCriteria = collect($storedSettings['criteria'] ?? [])
            ->filter(fn (mixed $criterion): bool => is_array($criterion))
            ->keyBy(fn (array $criterion): string => (string) ($criterion['key'] ?? ''));

        return [
            'version' => self::SETTINGS_VERSION,
            'criteria' => $this->criteriaDefinitions()
                ->map(function (array $definition) use ($storedCriteria): array {
                    $storedCriterion = $storedCriteria->get($definition['key'], []);

                    return $this->criterionPayload(
                        $definition,
                        (bool) ($storedCriterion['enabled'] ?? false),
                        (int) ($storedCriterion['priority'] ?? $definition['priority']),
                        $storedCriterion['option'] ?? null,
                    );
                })
                ->sortBy('priority')
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $criteria
     * @return array{
     *     version:int,
     *     criteria:array<int, array<string, mixed>>
     * }
     */
    private function settingsPayloadForStorage(array $criteria): array
    {
        $submittedCriteria = collect($criteria)
            ->keyBy(fn (array $criterion): string => (string) ($criterion['key'] ?? ''));

        $normalizedCriteria = $this->criteriaDefinitions()
            ->map(function (array $definition) use ($submittedCriteria): array {
                $submittedCriterion = $submittedCriteria->get($definition['key'], []);

                return $this->criterionStoragePayload(
                    $definition,
                    (bool) ($submittedCriterion['enabled'] ?? false),
                    (int) ($submittedCriterion['priority'] ?? $definition['priority']),
                    $submittedCriterion['option'] ?? null,
                );
            })
            ->sortBy('priority')
            ->values()
            ->map(function (array $criterion, int $index): array {
                $criterion['priority'] = $index + 1;

                return $criterion;
            })
            ->all();

        return [
            'version' => self::SETTINGS_VERSION,
            'criteria' => $normalizedCriteria,
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function criteriaDefinitions(): Collection
    {
        return collect(self::CRITERIA)
            ->values()
            ->map(function (array $criterion, int $index): array {
                $criterion['priority'] = $index + 1;

                return $criterion;
            });
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return array<string, mixed>
     */
    private function criterionPayload(array $definition, bool $enabled, int $priority, mixed $option): array
    {
        return [
            'key' => $definition['key'],
            'label' => $definition['label'],
            'description' => $definition['description'],
            'enabled' => $enabled,
            'priority' => $priority,
            'option' => $this->normalizedOption($definition, $option),
            'options' => $definition['options'] ?? [],
        ];
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return array<string, mixed>
     */
    private function criterionStoragePayload(array $definition, bool $enabled, int $priority, mixed $option): array
    {
        return [
            'key' => $definition['key'],
            'enabled' => $enabled,
            'priority' => $priority,
            'option' => $this->normalizedOption($definition, $option),
        ];
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function normalizedOption(array $definition, mixed $option): ?string
    {
        $options = collect($definition['options'] ?? []);

        if ($options->isEmpty()) {
            return null;
        }

        $optionValues = $options->pluck('value');

        if (is_string($option) && $optionValues->contains($option)) {
            return $option;
        }

        return $definition['default_option'] ?? $optionValues->first();
    }
}
