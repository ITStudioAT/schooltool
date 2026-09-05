<?php

namespace App\Services\Teaching;

use App\Models\Schoolyear;
use App\Models\TeachingCurriculum;
use App\Models\TeachingImportedCurriculum;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ImportedCurriculumService
{
    public function importFromJson(User $user, string $json): TeachingImportedCurriculum
    {
        try {
            /** @var mixed $decoded */
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw ValidationException::withMessages([
                'file' => 'Die Datei enthält kein gültiges Curriculum-JSON.',
            ]);
        }

        if (! is_array($decoded)) {
            throw ValidationException::withMessages([
                'file' => 'Die Datei enthält kein gültiges Curriculum-JSON.',
            ]);
        }

        $payload = $this->normalizeImportPayload($decoded);

        $previous = TeachingImportedCurriculum::query()
            ->where('school_id', $user->school_id)
            ->where('user_id', $user->id)
            ->where('curriculum_key', $payload['curriculum_key'])
            ->first();
        $oldArchive = $previous?->materials['archive_path'] ?? null;
        $imported = TeachingImportedCurriculum::query()->updateOrCreate(
            [
                'school_id' => $user->school_id,
                'user_id' => $user->id,
                'curriculum_key' => $payload['curriculum_key'],
            ],
            [
                'title' => $payload['curriculum']['title'],
                'description' => $payload['curriculum']['description'],
                'topics' => $payload['curriculum']['topics'],
                'source_schema_version' => $payload['schema_version'],
                'source_exported_at' => $payload['exported_at'],
                'imported_at' => now(),
                'materials' => null,
            ]
        );
        if (is_string($oldArchive)) {
            DB::afterCommit(fn () => app(CurriculumArchiveService::class)->deleteStoredArchive($oldArchive));
        }

        return $imported;
    }

    public function adoptForUser(TeachingImportedCurriculum $importedCurriculum, User $user): TeachingCurriculum
    {
        if ($this->hasAlreadyBeenAdopted($importedCurriculum, $user)) {
            throw ValidationException::withMessages([
                'curriculum' => 'Dieses importierte Curriculum wurde bereits übernommen.',
            ]);
        }

        $createdPaths = [];
        try {
            return DB::transaction(function () use ($importedCurriculum, $user, &$createdPaths): TeachingCurriculum {
                $lockedImport = TeachingImportedCurriculum::query()->lockForUpdate()->findOrFail($importedCurriculum->id);
                if ($this->hasAlreadyBeenAdopted($lockedImport, $user)) {
                    throw ValidationException::withMessages(['curriculum' => 'Dieses importierte Curriculum wurde bereits übernommen.']);
                }
                $curriculum = TeachingCurriculum::query()->create([
                    'school_id' => $user->school_id,
                    'schoolyear_id' => $user->schoolyear_id,
                    'user_id' => $user->id,
                    'title' => (string) $lockedImport->title,
                    'description' => $lockedImport->description !== null ? (string) $lockedImport->description : null,
                    'topics' => is_array($lockedImport->topics) ? $lockedImport->topics : [],
                ]);

                app(CurriculumArchiveService::class)->adopt($lockedImport, $curriculum, $user, $createdPaths);
                $lockedImport->forceFill([
                    'adopted_curriculum_id' => $curriculum->id,
                ])->save();

                return $curriculum;
            });
        } catch (\Throwable $exception) {
            if ($createdPaths !== []) {
                Storage::disk('local')->delete($createdPaths);
            }
            throw $exception;
        }
    }

    private function hasAlreadyBeenAdopted(TeachingImportedCurriculum $importedCurriculum, User $user): bool
    {
        if (! filled($importedCurriculum->adopted_curriculum_id)) {
            return false;
        }

        return TeachingCurriculum::query()
            ->whereKey($importedCurriculum->adopted_curriculum_id)
            ->where('school_id', $user->school_id)
            ->where('user_id', $user->id)
            ->exists();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{
     *     export_type: string,
     *     schema_version: int,
     *     curriculum_key: string,
     *     exported_at: ?string,
     *     curriculum: array{
     *         title: string,
     *         description: ?string,
     *         topics: array<int, array<string, mixed>>
     *     }
     * }
     */
    public function normalizeImportPayload(array $payload): array
    {
        $validated = Validator::validate($payload, [
            'export_type' => 'required|string|in:teaching_curriculum',
            'schema_version' => 'required|integer|min:1',
            'curriculum_key' => 'required|string|size:36',
            'exported_at' => 'nullable|date',
            'curriculum' => 'required|array',
            'curriculum.title' => 'required|string|max:255',
            'curriculum.description' => 'nullable|string',
            'curriculum.topics' => 'nullable|array',
            'curriculum.topics.*' => 'array',
            'curriculum.topics.*.id' => 'nullable|string|max:100',
            'curriculum.topics.*.title' => 'required|string|max:255',
            'curriculum.topics.*.units' => 'nullable|array',
            'curriculum.topics.*.units.*' => 'array',
            'curriculum.topics.*.units.*.id' => 'nullable|string|max:100',
            'curriculum.topics.*.units.*.title' => 'required|string|max:255',
            'curriculum.topics.*.units.*.is_exam' => 'sometimes|boolean',
        ]);

        return [
            'export_type' => (string) $validated['export_type'],
            'schema_version' => (int) $validated['schema_version'],
            'curriculum_key' => (string) $validated['curriculum_key'],
            'exported_at' => filled($validated['exported_at'] ?? null) ? (string) $validated['exported_at'] : null,
            'curriculum' => [
                'title' => trim((string) $validated['curriculum']['title']),
                'description' => filled($validated['curriculum']['description'] ?? null)
                    ? trim((string) $validated['curriculum']['description'])
                    : null,
                'topics' => $this->normalizeContentTopics(is_array($validated['curriculum']['topics'] ?? null) ? $validated['curriculum']['topics'] : []),
            ],
        ];
    }

    /**
     * @param  array<int, mixed>  $topics
     * @return array<int, array<string, mixed>>
     */
    private function normalizeContentTopics(array $topics): array
    {
        return collect($topics)->values()->map(function (mixed $topic, int $topicIndex): array {
            $normalizedTopic = is_array($topic) ? $topic : [];

            return [
                'id' => filled($normalizedTopic['id'] ?? null) ? (string) $normalizedTopic['id'] : "topic-{$topicIndex}",
                'title' => trim((string) ($normalizedTopic['title'] ?? '')),
                'units' => collect(is_array($normalizedTopic['units'] ?? null) ? $normalizedTopic['units'] : [])
                    ->values()
                    ->map(function (mixed $unit, int $unitIndex) use ($topicIndex): array {
                        $normalizedUnit = is_array($unit) ? $unit : [];

                        return [
                            'id' => filled($normalizedUnit['id'] ?? null) ? (string) $normalizedUnit['id'] : "unit-{$topicIndex}-{$unitIndex}",
                            'title' => trim((string) ($normalizedUnit['title'] ?? '')),
                            'is_exam' => (bool) ($normalizedUnit['is_exam'] ?? false),
                        ];
                    })
                    ->all(),
            ];
        })->all();
    }

    private function mondayWeekRule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            try {
                $weekStart = CarbonImmutable::createFromFormat('Y-m-d', (string) $value)->startOfDay();
            } catch (\Throwable) {
                return;
            }

            if (! $weekStart->isMonday()) {
                $fail('Wochen müssen mit einem Montag gespeichert werden.');
            }
        };
    }

    /**
     * @param  array<int, mixed>  $topics
     * @return array<int, array{
     *     id: string,
     *     title: string,
     *     assignment_type: string,
     *     month_key: ?string,
     *     month_keys: array<int, string>,
     *     week_keys: array<int, string>,
     *     units: array<int, array{
     *         id: string,
     *         title: string,
     *         is_exam: bool,
     *         assignment_type: string,
     *         month_key: ?string,
     *         month_keys: array<int, string>,
     *         week_keys: array<int, string>,
     *         checked_week_keys: array<int, string>
     *     }>
     * }>
     */
    private function normalizeTopics(array $topics): array
    {
        return collect($topics)
            ->filter(fn (mixed $topic): bool => is_array($topic))
            ->values()
            ->map(function (array $topic, int $index): array {
                $normalizedTopic = $this->normalizeScheduledItem(
                    $topic,
                    "curriculum.topics.{$index}",
                    'Bitte einen gültigen Thementitel angeben.'
                );

                return [
                    ...$normalizedTopic,
                    'units' => $this->normalizeUnits(is_array($topic['units'] ?? null) ? $topic['units'] : [], $index),
                ];
            })
            ->all();
    }

    /**
     * @param  array<int, mixed>  $units
     * @return array<int, array{
     *     id: string,
     *     title: string,
     *     is_exam: bool,
     *     assignment_type: string,
     *     month_key: ?string,
     *     month_keys: array<int, string>,
     *     week_keys: array<int, string>,
     *     checked_week_keys: array<int, string>
     * }>
     */
    private function normalizeUnits(array $units, int $topicIndex): array
    {
        return collect($units)
            ->filter(fn (mixed $unit): bool => is_array($unit))
            ->values()
            ->map(function (array $unit, int $unitIndex) use ($topicIndex): array {
                return [
                    ...$this->normalizeScheduledItem(
                        $unit,
                        "curriculum.topics.{$topicIndex}.units.{$unitIndex}",
                        'Bitte einen gültigen Einheitentitel angeben.'
                    ),
                    'is_exam' => (bool) ($unit['is_exam'] ?? false),
                    'checked_week_keys' => [],
                ];
            })
            ->all();
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array{
     *     id: string,
     *     title: string,
     *     assignment_type: string,
     *     month_key: ?string,
     *     month_keys: array<int, string>,
     *     week_keys: array<int, string>
     * }
     */
    private function normalizeScheduledItem(array $item, string $path, string $emptyTitleMessage): array
    {
        $title = trim((string) ($item['title'] ?? ''));
        $assignmentType = trim((string) ($item['assignment_type'] ?? ''));
        $monthKeys = $this->normalizeMonthKeys(
            is_array($item['month_keys'] ?? null)
                ? $item['month_keys']
                : [($item['month_key'] ?? null)]
        );

        if ($title === '') {
            throw ValidationException::withMessages([
                "{$path}.title" => $emptyTitleMessage,
            ]);
        }

        if ($assignmentType === 'month' && $monthKeys === []) {
            throw ValidationException::withMessages([
                "{$path}.month_keys" => 'Bitte mindestens einen Monat auswählen.',
            ]);
        }

        return [
            'id' => trim((string) ($item['id'] ?? '')) !== ''
                ? trim((string) $item['id'])
                : (string) Str::uuid(),
            'title' => $title,
            'assignment_type' => $assignmentType,
            'month_key' => $assignmentType === 'month' ? ($monthKeys[0] ?? null) : null,
            'month_keys' => $assignmentType === 'month' ? $monthKeys : [],
            'week_keys' => [],
        ];
    }

    /**
     * @param  array<int, mixed>  $weekKeys
     * @return array<int, string>
     */
    private function normalizeWeekKeys(array $weekKeys): array
    {
        return collect($weekKeys)
            ->filter(fn (mixed $weekKey): bool => filled($weekKey))
            ->map(fn (mixed $weekKey): string => trim((string) $weekKey))
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, mixed>  $monthKeys
     * @return array<int, string>
     */
    private function normalizeMonthKeys(array $monthKeys): array
    {
        return collect($monthKeys)
            ->filter(fn (mixed $monthKey): bool => filled($monthKey))
            ->map(fn (mixed $monthKey): string => trim((string) $monthKey))
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string>  $weekKeys
     * @return array<int, string>
     */
    private function mapWeekKeysToSchoolyear(array $weekKeys, ?Schoolyear $schoolyear): array
    {
        $weekMap = $this->schoolyearWeekMap($schoolyear);
        if ($weekMap === []) {
            return $this->normalizeWeekKeys($weekKeys);
        }

        return $this->normalizeWeekKeys(
            collect($weekKeys)
                ->map(function (string $weekKey) use ($weekMap): string {
                    try {
                        $weekStart = CarbonImmutable::createFromFormat('Y-m-d', $weekKey)->startOfDay();
                    } catch (\Throwable) {
                        return $weekKey;
                    }

                    return $weekMap[$weekStart->isoWeek()] ?? $weekKey;
                })
                ->all()
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $topics
     * @return array<int, array<string, mixed>>
     */
    private function mapTopicsToSchoolyear(array $topics, ?Schoolyear $schoolyear): array
    {
        return collect($topics)
            ->filter(fn (mixed $topic): bool => is_array($topic))
            ->map(function (array $topic) use ($schoolyear): array {
                $mappedTopic = [
                    ...$topic,
                    'month_key' => $this->mapMonthKeyToSchoolyear(filled($topic['month_key'] ?? null) ? (string) $topic['month_key'] : null, $schoolyear),
                    'month_keys' => $this->mapMonthKeysToSchoolyear(is_array($topic['month_keys'] ?? null) ? $topic['month_keys'] : [], $schoolyear),
                    'week_keys' => [],
                ];

                $mappedTopic['units'] = collect(is_array($topic['units'] ?? null) ? $topic['units'] : [])
                    ->filter(fn (mixed $unit): bool => is_array($unit))
                    ->map(function (array $unit) use ($schoolyear): array {
                        return [
                            ...$unit,
                            'month_key' => $this->mapMonthKeyToSchoolyear(filled($unit['month_key'] ?? null) ? (string) $unit['month_key'] : null, $schoolyear),
                            'month_keys' => $this->mapMonthKeysToSchoolyear(is_array($unit['month_keys'] ?? null) ? $unit['month_keys'] : [], $schoolyear),
                            'week_keys' => [],
                            'checked_week_keys' => [],
                        ];
                    })
                    ->values()
                    ->all();

                return $mappedTopic;
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string>  $monthKeys
     * @return array<int, string>
     */
    private function mapMonthKeysToSchoolyear(array $monthKeys, ?Schoolyear $schoolyear): array
    {
        return $this->normalizeMonthKeys(
            collect($monthKeys)
                ->map(fn (string $monthKey): ?string => $this->mapMonthKeyToSchoolyear($monthKey, $schoolyear))
                ->filter()
                ->all()
        );
    }

    private function mapMonthKeyToSchoolyear(?string $monthKey, ?Schoolyear $schoolyear): ?string
    {
        if (! filled($monthKey)) {
            return null;
        }

        $startYear = $this->schoolyearStartYear($schoolyear);
        if ($startYear === null) {
            return $monthKey;
        }

        $month = (int) substr($monthKey, 5, 2);
        if ($month < 1 || $month > 12) {
            return $monthKey;
        }

        $targetYear = $month >= 9 ? $startYear : $startYear + 1;

        return sprintf('%04d-%02d', $targetYear, $month);
    }

    /**
     * @return array<int, string>
     */
    private function schoolyearWeekMap(?Schoolyear $schoolyear): array
    {
        $startYear = $this->schoolyearStartYear($schoolyear);
        if ($startYear === null) {
            return [];
        }

        $weekMap = [];
        $monthSequence = [
            ['year' => $startYear, 'month' => 9],
            ['year' => $startYear, 'month' => 10],
            ['year' => $startYear, 'month' => 11],
            ['year' => $startYear, 'month' => 12],
            ['year' => $startYear + 1, 'month' => 1],
            ['year' => $startYear + 1, 'month' => 2],
            ['year' => $startYear + 1, 'month' => 3],
            ['year' => $startYear + 1, 'month' => 4],
            ['year' => $startYear + 1, 'month' => 5],
            ['year' => $startYear + 1, 'month' => 6],
            ['year' => $startYear + 1, 'month' => 7],
        ];

        foreach ($monthSequence as $entry) {
            $firstDayOfMonth = CarbonImmutable::create($entry['year'], $entry['month'], 1, 0, 0, 0)->startOfDay();
            $weekStart = $firstDayOfMonth->subDays($firstDayOfMonth->dayOfWeekIso - 1);

            while (true) {
                $weekHasMonthDay = false;

                for ($dayOffset = 0; $dayOffset < 7; $dayOffset++) {
                    if ($weekStart->addDays($dayOffset)->month === $entry['month']) {
                        $weekHasMonthDay = true;
                        break;
                    }
                }

                if (! $weekHasMonthDay) {
                    break;
                }

                $weekMap[$weekStart->isoWeek()] ??= $weekStart->format('Y-m-d');
                $weekStart = $weekStart->addWeek();
            }
        }

        ksort($weekMap);

        return $weekMap;
    }

    private function schoolyearStartYear(?Schoolyear $schoolyear): ?int
    {
        if (! $schoolyear) {
            return null;
        }

        if (filled($schoolyear->from)) {
            try {
                return CarbonImmutable::parse((string) $schoolyear->from)->year;
            } catch (\Throwable) {
            }
        }

        if (filled($schoolyear->until)) {
            try {
                return CarbonImmutable::parse((string) $schoolyear->until)->subMonths(10)->year;
            } catch (\Throwable) {
            }
        }

        return null;
    }
}
