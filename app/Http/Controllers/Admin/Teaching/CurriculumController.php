<?php

namespace App\Http\Controllers\Admin\Teaching;

use App\Http\Controllers\Controller;
use App\Models\TeachingCurriculum;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CurriculumController extends Controller
{
    public function index(Request $request)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'teaching_admin', 'teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $perPage = (int) $request->input('per_page', 10);
        $perPage = max(1, min($perPage, 100));

        $query = TeachingCurriculum::query()
            ->where('school_id', $auth_user->school_id)
            ->where('user_id', $auth_user->id)
            ->orderByDesc('updated_at');

        if ($search = trim((string) $request->input('search', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $paginated = $query->paginate($perPage)->withQueryString();

        return response()->json([
            'data' => $paginated->items(),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'teaching_admin', 'teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $validated = $this->validatedPayload($request);

        $curriculum = TeachingCurriculum::create([
            'school_id' => $auth_user->school_id,
            'schoolyear_id' => $auth_user->schoolyear_id,
            'user_id' => $auth_user->id,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'semester_count' => $validated['semester_count'] ?? 2,
            'free_weeks' => $validated['free_weeks'],
            'topics' => $validated['topics'],
        ]);

        return response()->json(['data' => $curriculum], 201);
    }

    public function show(TeachingCurriculum $curriculum)
    {
        $auth_user = $this->authorizeCurriculum($curriculum);

        return response()->json(['data' => $curriculum]);
    }

    public function update(Request $request, TeachingCurriculum $curriculum)
    {
        $this->authorizeCurriculum($curriculum);

        $validated = $this->validatedPayload($request, $curriculum);

        $curriculum->update($validated);

        return response()->json(['data' => $curriculum]);
    }

    public function destroy(TeachingCurriculum $curriculum)
    {
        $this->authorizeCurriculum($curriculum);

        $curriculum->delete();

        return response()->json(null, 204);
    }

    private function authorizeCurriculum(TeachingCurriculum $curriculum)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'teaching_admin', 'teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        if ($curriculum->school_id !== $auth_user->school_id || $curriculum->user_id !== $auth_user->id) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        return $auth_user;
    }

    /**
     * @return array{
     *     title: string,
     *     description: ?string,
     *     semester_count: int,
     *     free_weeks: array<int, string>,
     *     topics: array<int, array{
     *         id: string,
     *         title: string,
     *         assignment_type: string,
     *         month_key: ?string,
     *         month_keys: array<int, string>,
     *         week_keys: array<int, string>,
     *         units: array<int, array{
     *             id: string,
     *             title: string,
     *             is_exam: bool,
     *             assignment_type: string,
     *             month_key: ?string,
     *             month_keys: array<int, string>,
     *             week_keys: array<int, string>
     *         }>
     *     }>
     * }
     */
    private function validatedPayload(Request $request, ?TeachingCurriculum $curriculum = null): array
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'semester_count' => 'nullable|integer|in:1,2',
            'free_weeks' => 'nullable|array',
            'free_weeks.*' => [
                'string',
                'date_format:Y-m-d',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    try {
                        $weekStart = CarbonImmutable::createFromFormat('Y-m-d', (string) $value)->startOfDay();
                    } catch (\Throwable) {
                        return;
                    }

                    if (! $weekStart->isMonday()) {
                        $fail('Freie Wochen müssen mit einem Montag gespeichert werden.');
                    }
                },
            ],
            'topics' => 'nullable|array',
            'topics.*' => 'array',
            'topics.*.id' => 'nullable|string|max:100',
            'topics.*.title' => 'required|string|max:255',
            'topics.*.assignment_type' => 'required|string|in:none,all_weeks,month,weeks',
            'topics.*.month_key' => 'nullable|string|regex:/^\d{4}-\d{2}$/',
            'topics.*.month_keys' => 'nullable|array',
            'topics.*.month_keys.*' => 'string|regex:/^\d{4}-\d{2}$/',
            'topics.*.week_keys' => 'nullable|array',
            'topics.*.week_keys.*' => [
                'string',
                'date_format:Y-m-d',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    try {
                        $weekStart = CarbonImmutable::createFromFormat('Y-m-d', (string) $value)->startOfDay();
                    } catch (\Throwable) {
                        return;
                    }

                    if (! $weekStart->isMonday()) {
                        $fail('Themen-Wochen müssen mit einem Montag gespeichert werden.');
                    }
                },
            ],
            'topics.*.units' => 'nullable|array',
            'topics.*.units.*' => 'array',
            'topics.*.units.*.id' => 'nullable|string|max:100',
            'topics.*.units.*.title' => 'required|string|max:255',
            'topics.*.units.*.is_exam' => 'sometimes|boolean',
            'topics.*.units.*.assignment_type' => 'required|string|in:none,all_weeks,month,weeks',
            'topics.*.units.*.month_key' => 'nullable|string|regex:/^\d{4}-\d{2}$/',
            'topics.*.units.*.month_keys' => 'nullable|array',
            'topics.*.units.*.month_keys.*' => 'string|regex:/^\d{4}-\d{2}$/',
            'topics.*.units.*.week_keys' => 'nullable|array',
            'topics.*.units.*.week_keys.*' => [
                'string',
                'date_format:Y-m-d',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    try {
                        $weekStart = CarbonImmutable::createFromFormat('Y-m-d', (string) $value)->startOfDay();
                    } catch (\Throwable) {
                        return;
                    }

                    if (! $weekStart->isMonday()) {
                        $fail('Einheiten-Wochen müssen mit einem Montag gespeichert werden.');
                    }
                },
            ],
        ]);

        $validated['semester_count'] = (int) ($validated['semester_count'] ?? 2);
        $validated['free_weeks'] = $request->has('free_weeks')
            ? $this->normalizeWeekKeys($validated['free_weeks'] ?? [])
            : collect($curriculum?->free_weeks ?? [])->values()->all();
        $validated['topics'] = $request->has('topics')
            ? $this->normalizeTopics($validated['topics'] ?? [], is_array($curriculum?->topics) ? $curriculum->topics : [])
            : collect($curriculum?->topics ?? [])->values()->all();

        return $validated;
    }

    /**
     * @param  array<int, mixed>  $weekKeys
     * @return array<int, string>
     */
    private function normalizeWeekKeys(array $weekKeys): array
    {
        return collect($weekKeys)
            ->filter(fn (mixed $weekStart): bool => filled($weekStart))
            ->map(fn (mixed $weekStart): string => (string) $weekStart)
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
     * @param  array<int, array<string, mixed>>  $topics
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
     *         week_keys: array<int, string>
     *     }>
     * }>
     */
    private function normalizeTopics(array $topics, array $existingTopics = []): array
    {
        $existingTopicsById = collect($existingTopics)
            ->filter(fn (mixed $topic): bool => is_array($topic) && filled($topic['id'] ?? null))
            ->mapWithKeys(fn (array $topic): array => [(string) $topic['id'] => $topic]);

        return collect($topics)->values()->map(function (mixed $topic, int $index) use ($existingTopicsById): array {
            $normalizedTopic = is_array($topic) ? $topic : [];
            $topicItem = $this->normalizeScheduledItem(
                $normalizedTopic,
                "topics.{$index}",
                'Bitte einen gültigen Thementitel angeben.'
            );
            $units = $this->normalizeUnits(
                is_array($normalizedTopic['units'] ?? null) ? $normalizedTopic['units'] : [],
                $index
            );
            $existingTopic = $existingTopicsById->get($topicItem['id']);
            $winner = $this->resolveTopicAssignmentWinner($topicItem, $units, is_array($existingTopic) ? $existingTopic : null);

            [$topicItem, $units] = $this->reconcileTopicUnitAssignments($topicItem, $units, $winner);

            return [
                ...$topicItem,
                'units' => $units,
            ];
        })->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $units
     * @return array<int, array{
     *     id: string,
     *     title: string,
     *     is_exam: bool,
     *     assignment_type: string,
     *     month_key: ?string,
     *     month_keys: array<int, string>,
     *     week_keys: array<int, string>
     * }>
     */
    private function normalizeUnits(array $units, int $topicIndex): array
    {
        return collect($units)->values()->map(function (mixed $unit, int $unitIndex) use ($topicIndex): array {
            $normalizedUnit = is_array($unit) ? $unit : [];

            return [
                ...$this->normalizeScheduledItem(
                    $normalizedUnit,
                    "topics.{$topicIndex}.units.{$unitIndex}",
                    'Bitte einen gültigen Einheitentitel angeben.'
                ),
                'is_exam' => (bool) ($normalizedUnit['is_exam'] ?? false),
            ];
        })->all();
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
        $weekKeys = $this->normalizeWeekKeys(is_array($item['week_keys'] ?? null) ? $item['week_keys'] : []);

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

        if ($assignmentType === 'weeks' && $weekKeys === []) {
            throw ValidationException::withMessages([
                "{$path}.week_keys" => 'Bitte mindestens eine Woche auswählen.',
            ]);
        }

        return [
            'id' => trim((string) ($item['id'] ?? '')) !== ''
                ? trim((string) $item['id'])
                : (string) Str::uuid(),
            'title' => $title,
            'assignment_type' => $assignmentType,
            'month_key' => $assignmentType === 'month' ? $monthKeys[0] : null,
            'month_keys' => $assignmentType === 'month' ? $monthKeys : [],
            'week_keys' => $assignmentType === 'weeks' ? $weekKeys : [],
        ];
    }

    /**
     * @param  array{id:string,title:string,assignment_type:string,month_key:?string,month_keys:array<int,string>,week_keys:array<int,string>}  $topic
     * @param  array<int, array{id:string,title:string,is_exam:bool,assignment_type:string,month_key:?string,month_keys:array<int,string>,week_keys:array<int,string>}>  $units
     * @param  array<string, mixed>|null  $existingTopic
     */
    private function resolveTopicAssignmentWinner(array $topic, array $units, ?array $existingTopic): ?string
    {
        if ($existingTopic === null) {
            return 'units';
        }

        if ($this->assignmentsDiffer($topic, $existingTopic)) {
            return 'topic';
        }

        if ($this->unitsAssignmentsDiffer($units, $existingTopic)) {
            return 'units';
        }

        return null;
    }

    /**
     * @param  array{id:string,title:string,assignment_type:string,month_key:?string,month_keys:array<int,string>,week_keys:array<int,string>}  $topic
     * @param  array<int, array{id:string,title:string,is_exam:bool,assignment_type:string,month_key:?string,month_keys:array<int,string>,week_keys:array<int,string>}>  $units
     * @return array{
     *     0: array{id:string,title:string,assignment_type:string,month_key:?string,month_keys:array<int,string>,week_keys:array<int,string>},
     *     1: array<int, array{id:string,title:string,is_exam:bool,assignment_type:string,month_key:?string,month_keys:array<int,string>,week_keys:array<int,string>}>
     * }
     */
    private function reconcileTopicUnitAssignments(array $topic, array $units, ?string $winner): array
    {
        if ($winner === 'topic') {
            return [
                $topic,
                collect($units)
                    ->map(fn (array $unit): array => $this->removeAssignmentOverlap($unit, $topic))
                    ->all(),
            ];
        }

        if ($winner === 'units') {
            $reconciledTopic = $topic;

            foreach ($units as $unit) {
                $reconciledTopic = $this->removeAssignmentOverlap($reconciledTopic, $unit);
            }

            return [$reconciledTopic, $units];
        }

        return [$topic, $units];
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array{assignment_type:string,month_keys:array<int,string>,week_keys:array<int,string>}
     */
    private function assignmentState(array $item): array
    {
        $assignmentType = trim((string) ($item['assignment_type'] ?? ''));
        $assignmentType = in_array($assignmentType, ['none', 'all_weeks', 'month', 'weeks'], true) ? $assignmentType : 'none';

        return [
            'assignment_type' => $assignmentType,
            'month_keys' => $assignmentType === 'month'
                ? $this->normalizeMonthKeys(
                    is_array($item['month_keys'] ?? null)
                        ? $item['month_keys']
                        : [($item['month_key'] ?? null)]
                )
                : [],
            'week_keys' => $assignmentType === 'weeks'
                ? $this->normalizeWeekKeys(is_array($item['week_keys'] ?? null) ? $item['week_keys'] : [])
                : [],
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function hasActiveAssignment(array $item): bool
    {
        return $this->assignmentState($item)['assignment_type'] !== 'none';
    }

    /**
     * @param  array<string, mixed>  $left
     * @param  array<string, mixed>  $right
     */
    private function assignmentsDiffer(array $left, array $right): bool
    {
        return $this->assignmentState($left) !== $this->assignmentState($right);
    }

    /**
     * @param  array<int, array{id:string,title:string,is_exam:bool,assignment_type:string,month_key:?string,month_keys:array<int,string>,week_keys:array<int,string>}>  $units
     * @param  array<string, mixed>  $existingTopic
     */
    private function unitsAssignmentsDiffer(array $units, array $existingTopic): bool
    {
        $existingUnitsById = collect(is_array($existingTopic['units'] ?? null) ? $existingTopic['units'] : [])
            ->filter(fn (mixed $unit): bool => is_array($unit) && filled($unit['id'] ?? null))
            ->mapWithKeys(fn (array $unit): array => [(string) $unit['id'] => $unit]);

        foreach ($units as $unit) {
            $existingUnit = $existingUnitsById->get($unit['id']);

            if ($existingUnit === null) {
                if ($this->hasActiveAssignment($unit)) {
                    return true;
                }

                continue;
            }

            if ($this->assignmentsDiffer($unit, is_array($existingUnit) ? $existingUnit : [])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array{id:string,title:string,assignment_type:string,month_key:?string,month_keys:array<int,string>,week_keys:array<int,string>}  $target
     * @param  array{id:string,title:string,assignment_type:string,month_key:?string,month_keys:array<int,string>,week_keys:array<int,string>}  $blocking
     * @return array{id:string,title:string,assignment_type:string,month_key:?string,month_keys:array<int,string>,week_keys:array<int,string>}
     */
    private function removeAssignmentOverlap(array $target, array $blocking): array
    {
        $targetState = $this->assignmentState($target);
        $blockingState = $this->assignmentState($blocking);

        if ($targetState['assignment_type'] === 'none' || $blockingState['assignment_type'] === 'none') {
            return $target;
        }

        if ($targetState['assignment_type'] === 'all_weeks') {
            return $this->clearAssignment($target);
        }

        if ($blockingState['assignment_type'] === 'all_weeks') {
            return $this->clearAssignment($target);
        }

        if ($targetState['assignment_type'] === 'month') {
            $blockingMonths = $blockingState['assignment_type'] === 'month'
                ? $blockingState['month_keys']
                : collect($blockingState['week_keys'])->map(fn (string $weekKey): string => $this->monthKeyFromWeekKey($weekKey))->unique()->values()->all();

            $remainingMonthKeys = array_values(array_diff($targetState['month_keys'], $blockingMonths));

            return $remainingMonthKeys === []
                ? $this->clearAssignment($target)
                : $this->applyMonthAssignment($target, $remainingMonthKeys);
        }

        $remainingWeekKeys = $targetState['week_keys'];

        if ($blockingState['assignment_type'] === 'month') {
            $remainingWeekKeys = collect($remainingWeekKeys)
                ->reject(fn (string $weekKey): bool => in_array($this->monthKeyFromWeekKey($weekKey), $blockingState['month_keys'], true))
                ->values()
                ->all();
        } else {
            $remainingWeekKeys = array_values(array_diff($remainingWeekKeys, $blockingState['week_keys']));
        }

        return $remainingWeekKeys === []
            ? $this->clearAssignment($target)
            : $this->applyWeekAssignment($target, $remainingWeekKeys);
    }

    /**
     * @param  array{id:string,title:string,assignment_type:string,month_key:?string,month_keys:array<int,string>,week_keys:array<int,string>}  $item
     * @return array{id:string,title:string,assignment_type:string,month_key:?string,month_keys:array<int,string>,week_keys:array<int,string>}
     */
    private function clearAssignment(array $item): array
    {
        return [
            ...$item,
            'assignment_type' => 'none',
            'month_key' => null,
            'month_keys' => [],
            'week_keys' => [],
        ];
    }

    /**
     * @param  array{id:string,title:string,assignment_type:string,month_key:?string,month_keys:array<int,string>,week_keys:array<int,string>}  $item
     * @param  array<int, string>  $monthKeys
     * @return array{id:string,title:string,assignment_type:string,month_key:?string,month_keys:array<int,string>,week_keys:array<int,string>}
     */
    private function applyMonthAssignment(array $item, array $monthKeys): array
    {
        $monthKeys = $this->normalizeMonthKeys($monthKeys);

        return [
            ...$item,
            'assignment_type' => 'month',
            'month_key' => $monthKeys[0] ?? null,
            'month_keys' => $monthKeys,
            'week_keys' => [],
        ];
    }

    /**
     * @param  array{id:string,title:string,assignment_type:string,month_key:?string,month_keys:array<int,string>,week_keys:array<int,string>}  $item
     * @param  array<int, string>  $weekKeys
     * @return array{id:string,title:string,assignment_type:string,month_key:?string,month_keys:array<int,string>,week_keys:array<int,string>}
     */
    private function applyWeekAssignment(array $item, array $weekKeys): array
    {
        $weekKeys = $this->normalizeWeekKeys($weekKeys);

        return [
            ...$item,
            'assignment_type' => 'weeks',
            'month_key' => null,
            'month_keys' => [],
            'week_keys' => $weekKeys,
        ];
    }

    private function monthKeyFromWeekKey(string $weekKey): string
    {
        return CarbonImmutable::createFromFormat('Y-m-d', $weekKey)->format('Y-m');
    }
}
