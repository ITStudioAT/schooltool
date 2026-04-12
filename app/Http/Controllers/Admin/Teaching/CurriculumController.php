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
     *         week_keys: array<int, string>
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
        ]);

        $validated['semester_count'] = (int) ($validated['semester_count'] ?? 2);
        $validated['free_weeks'] = $request->has('free_weeks')
            ? $this->normalizeWeekKeys($validated['free_weeks'] ?? [])
            : collect($curriculum?->free_weeks ?? [])->values()->all();
        $validated['topics'] = $request->has('topics')
            ? $this->normalizeTopics($validated['topics'] ?? [])
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
     *     week_keys: array<int, string>
     * }>
     */
    private function normalizeTopics(array $topics): array
    {
        return collect($topics)->values()->map(function (mixed $topic, int $index): array {
            $normalizedTopic = is_array($topic) ? $topic : [];
            $title = trim((string) ($normalizedTopic['title'] ?? ''));
            $assignmentType = trim((string) ($normalizedTopic['assignment_type'] ?? ''));
            $monthKeys = $this->normalizeMonthKeys(
                is_array($normalizedTopic['month_keys'] ?? null)
                    ? $normalizedTopic['month_keys']
                    : [($normalizedTopic['month_key'] ?? null)]
            );
            $weekKeys = $this->normalizeWeekKeys(is_array($normalizedTopic['week_keys'] ?? null) ? $normalizedTopic['week_keys'] : []);

            if ($title === '') {
                throw ValidationException::withMessages([
                    "topics.{$index}.title" => 'Bitte einen gültigen Thementitel angeben.',
                ]);
            }

            if ($assignmentType === 'month' && $monthKeys === []) {
                throw ValidationException::withMessages([
                    "topics.{$index}.month_keys" => 'Bitte mindestens einen Monat auswählen.',
                ]);
            }

            if ($assignmentType === 'weeks' && $weekKeys === []) {
                throw ValidationException::withMessages([
                    "topics.{$index}.week_keys" => 'Bitte mindestens eine Woche auswählen.',
                ]);
            }

            return [
                'id' => trim((string) ($normalizedTopic['id'] ?? '')) !== ''
                    ? trim((string) $normalizedTopic['id'])
                    : (string) Str::uuid(),
                'title' => $title,
                'assignment_type' => $assignmentType,
                'month_key' => $assignmentType === 'month' ? $monthKeys[0] : null,
                'month_keys' => $assignmentType === 'month' ? $monthKeys : [],
                'week_keys' => $assignmentType === 'weeks' ? $weekKeys : [],
            ];
        })->all();
    }
}
