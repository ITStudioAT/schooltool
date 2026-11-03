<?php

namespace App\Http\Controllers\Admin\Teaching;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Teaching\StoreTeachingEntryAreaRequest;
use App\Http\Requests\Admin\Teaching\UpdateTeachingEntryAreaRequest;
use App\Http\Resources\Admin\Teaching\TeachingEntryAreaResource;
use App\Http\Resources\Admin\Teaching\TeachingEntryGradingPartResource;
use App\Models\Schoolyear;
use App\Models\TeachingEntryArea;
use App\Models\User;
use App\Support\TeachingGradingAdjustmentStructure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class TeachingEntryAreaController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $user = $this->authorizedUser();

        $areas = TeachingEntryArea::query()
            ->whereBelongsTo($user)
            ->where('school_id', $user->school_id)
            ->where('schoolyear_id', $user->schoolyear_id)
            ->withCount('entryDefinitions')
            ->with('gradingParts')
            ->orderBy('name')
            ->get();

        return TeachingEntryAreaResource::collection($areas)->additional([
            'meta' => [
                'previous_year_import' => $areas->isEmpty()
                    ? $this->previousYearImportOffer($user)
                    : null,
            ],
        ]);
    }

    public function store(StoreTeachingEntryAreaRequest $request): JsonResponse
    {
        $user = $this->authorizedUser();
        $area = TeachingEntryArea::query()->create([
            'school_id' => $user->school_id,
            'schoolyear_id' => $user->schoolyear_id,
            'user_id' => $user->id,
            'name' => $request->validated('name'),
        ]);
        $area->setAttribute('entry_definitions_count', 0);
        $gradingPart = $area->createInitialGradingPart();

        return (new TeachingEntryAreaResource($area))
            ->additional([
                'grading_part' => (new TeachingEntryGradingPartResource($gradingPart))->resolve(),
            ])
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateTeachingEntryAreaRequest $request, TeachingEntryArea $entryArea): TeachingEntryAreaResource
    {
        $this->ensureAreaBelongsToUser($entryArea, $this->authorizedUser());
        DB::transaction(function () use ($request, $entryArea): void {
            $area = TeachingEntryArea::query()->whereKey($entryArea->id)->lockForUpdate()->firstOrFail();
            $payload = $request->validated();
            $affectedContexts = [];
            if (array_key_exists('grading_level_weights', $payload) && (array_key_exists('grading_part_groups', $payload) || array_key_exists('grading_group_weights', $payload))) {
                throw ValidationException::withMessages(['grading_level_weights' => 'Die Gewichtungsebenen bitte getrennt speichern.']);
            }
            if (array_key_exists('grading_part_groups', $payload)) {
                abort_unless(Schema::hasColumn('teaching_entry_areas', 'grading_part_groups'), 409, 'Die Gruppenverwaltung ist noch nicht eingerichtet.');
                $parts = $area->gradingParts()->where('user_id', $area->user_id)
                    ->where('school_id', $area->school_id)->where('schoolyear_id', $area->schoolyear_id)
                    ->lockForUpdate()->get();
                $assignments = [];
                foreach ($payload['grading_part_groups'] as $group) {
                    $children = collect($group['part_ids'])->map(fn (mixed $id): string => 'part:'.(int) $id)
                        ->merge(collect($payload['grading_part_groups'])->filter(fn (array $item): bool => ($item['parent_group_id'] ?? null) === $group['id'])->pluck('id')->map(fn (string $id): string => 'group:'.$id))->sort()->values()->all();
                    if (count($children) < 2) {
                        $saved = collect($area->grading_part_groups ?? [])->firstWhere('id', $group['id']);
                        $previous = $parts->where('grading_group_id', $group['id'])->pluck('id')->map(fn (int $id): string => 'part:'.$id)
                            ->merge(collect($area->grading_part_groups ?? [])->filter(fn (array $item): bool => ($item['parent_group_id'] ?? null) === $group['id'])->pluck('id')->map(fn (string $id): string => 'group:'.$id))->sort()->values()->all();
                        if ($saved === null || $previous !== $children) {
                            throw ValidationException::withMessages(['grading_part_groups' => 'Eine Gruppe braucht mindestens zwei direkte Bausteine.']);
                        }
                    }
                    foreach ($group['part_ids'] as $partId) {
                        if (! $parts->contains('id', $partId) || array_key_exists($partId, $assignments)) {
                            throw ValidationException::withMessages(['grading_part_groups' => 'Jeder Benotungsteil muss zu diesem Bereich gehören und darf nur einer Gruppe zugeordnet sein.']);
                        }
                        $assignments[$partId] = $group['id'];
                    }
                }
                TeachingGradingAdjustmentStructure::assertChange($area, 'grading_part_groups', $payload['grading_part_groups']);
                $previousMemberships = $parts->pluck('grading_group_id', 'id')->all();
                foreach ($previousMemberships as $partId => $previousParent) {
                    if ($previousParent !== ($assignments[$partId] ?? null)) {
                        $affectedContexts[] = $previousParent ?? 'root';
                        $affectedContexts[] = $assignments[$partId] ?? 'root';
                    }
                }
                foreach ($payload['grading_part_groups'] as $group) {
                    $previous = collect($area->grading_part_groups ?? [])->firstWhere('id', $group['id']);
                    if ($previous === null || ($previous['parent_group_id'] ?? null) !== ($group['parent_group_id'] ?? null) || $previous['name'] !== $group['name']) {
                        $affectedContexts[] = $previous['parent_group_id'] ?? 'root';
                        $affectedContexts[] = $group['parent_group_id'] ?? 'root';
                        $affectedContexts[] = $group['id'];
                    }
                }
                foreach ($area->grading_part_groups ?? [] as $previous) {
                    if (! collect($payload['grading_part_groups'])->contains('id', $previous['id'])) {
                        $affectedContexts[] = $previous['parent_group_id'] ?? 'root';
                    }
                }
                foreach ($parts as $part) {
                    $part->update(['grading_group_id' => $assignments[$part->id] ?? null]);
                }
                $newGroups = $payload['grading_part_groups'];
                $payload['grading_part_groups'] = array_map(function (array $group) use ($area, $previousMemberships, $newGroups): array {
                    $saved = collect($area->grading_part_groups ?? [])->firstWhere('id', $group['id']);
                    $stored = ['id' => $group['id'], 'name' => trim($group['name'])];
                    if (isset($group['parent_group_id'])) {
                        $stored['parent_group_id'] = $group['parent_group_id'];
                    }
                    $oldIds = collect($previousMemberships)->filter(fn (?string $id): bool => $id === $group['id'])->keys()->sort()->values()->all();
                    $newIds = collect($group['part_ids'])->map(fn (mixed $id): int => (int) $id)->sort()->values()->all();
                    $oldChildren = collect($area->grading_part_groups ?? [])->filter(fn (array $item): bool => ($item['parent_group_id'] ?? null) === $group['id'])->pluck('id')->sort()->values()->all();
                    $newChildren = collect($newGroups)->filter(fn (array $item): bool => ($item['parent_group_id'] ?? null) === $group['id'])->pluck('id')->sort()->values()->all();
                    if (isset($saved['weights']) && $oldIds === $newIds && $oldChildren === $newChildren) {
                        $stored['weights'] = $saved['weights'];
                    }

                    return $stored;
                }, $payload['grading_part_groups']);
                if (Schema::hasColumn('teaching_entry_areas', 'grading_level_weights')) {
                    $oldLevel = collect($area->grading_part_groups ?? [])->filter(fn (array $group): bool => ! isset($group['parent_group_id']))->pluck('id')->map(fn (string $id): string => 'group:'.$id)
                        ->merge(collect($previousMemberships)->filter(fn (?string $id): bool => $id === null)->keys()->map(fn (int $id): string => 'part:'.$id))->sort()->values()->all();
                    $newLevel = collect($payload['grading_part_groups'])->filter(fn (array $group): bool => ! isset($group['parent_group_id']))->pluck('id')->map(fn (string $id): string => 'group:'.$id)
                        ->merge($parts->filter(fn ($part): bool => ! isset($assignments[$part->id]))->pluck('id')->map(fn (int $id): string => 'part:'.$id))->sort()->values()->all();
                    if ($oldLevel !== $newLevel) {
                        $payload['grading_level_weights'] = null;
                    }
                }
            }
            if (array_key_exists('grading_group_weights', $payload)) {
                if (array_key_exists('grading_part_groups', $payload)) {
                    throw ValidationException::withMessages(['grading_group_weights' => 'Gruppen und Gewichtungen bitte getrennt speichern.']);
                }
                $configuration = $payload['grading_group_weights'];
                $groups = $area->grading_part_groups ?? [];
                $index = array_search($configuration['group_id'], array_column($groups, 'id'), true);
                if ($index === false) {
                    throw ValidationException::withMessages(['grading_group_weights' => 'Die Gruppe gehört nicht zu diesem Bereich.']);
                }
                $memberIds = $area->gradingParts()->where('grading_group_id', $configuration['group_id'])
                    ->where('user_id', $area->user_id)->where('school_id', $area->school_id)->where('schoolyear_id', $area->schoolyear_id)
                    ->lockForUpdate()->pluck('id')->sort()->values()->all();
                $expectedChildren = collect($memberIds)->map(fn (int $id): string => 'part:'.$id)
                    ->merge(collect($groups)->filter(fn (array $group): bool => ($group['parent_group_id'] ?? null) === $configuration['group_id'])->pluck('id')->map(fn (string $id): string => 'group:'.$id))->sort()->values()->all();
                $weights = $configuration['weights'];
                if ($weights !== null) {
                    if (TeachingGradingAdjustmentStructure::hasAdjustment($area, $configuration['group_id'])) {
                        throw ValidationException::withMessages(['grading_group_weights' => 'Diese Ebene verwendet eine Notenanpassung und wird nicht gewichtet.']);
                    }
                    $submittedIds = collect($weights)->map(fn (array $item): string => isset($item['group_id']) ? 'group:'.$item['group_id'] : 'part:'.(int) $item['part_id'])->sort()->values()->all();
                    if ($expectedChildren !== $submittedIds) {
                        throw ValidationException::withMessages(['grading_group_weights' => 'Bitte ausschließlich alle aktuellen Mitglieder dieser Gruppe gewichten.']);
                    }
                    $groups[$index]['weights'] = array_map(fn (array $item): array => [
                        ...(isset($item['group_id']) ? ['grading_group_id' => $item['group_id']] : ['teaching_entry_grading_part_id' => (int) $item['part_id']]), 'weight' => (float) $item['weight'],
                    ], $weights);
                } else {
                    unset($groups[$index]['weights']);
                }
                unset($payload['grading_group_weights']);
                $payload['grading_part_groups'] = $groups;
            }
            if ($request->exists('grading_level_weights')) {
                abort_unless(Schema::hasColumn('teaching_entry_areas', 'grading_level_weights'), 409, 'Die äußere Gewichtung ist noch nicht eingerichtet.');
                $weights = $payload['grading_level_weights'];
                if ($weights !== null) {
                    if (TeachingGradingAdjustmentStructure::hasAdjustment($area)) {
                        throw ValidationException::withMessages(['grading_level_weights' => 'Diese Ebene verwendet eine Notenanpassung und wird nicht gewichtet.']);
                    }
                    $parts = $area->gradingParts()->where('user_id', $area->user_id)->where('school_id', $area->school_id)
                        ->where('schoolyear_id', $area->schoolyear_id)->lockForUpdate()->get();
                    $groupIds = collect($area->grading_part_groups ?? [])->pluck('id')->all();
                    $expected = collect($area->grading_part_groups ?? [])->filter(fn (array $group): bool => ! isset($group['parent_group_id']))->pluck('id')->map(fn (string $id): string => 'group:'.$id)
                        ->merge($parts->filter(fn ($part): bool => ! in_array($part->grading_group_id, $groupIds, true))->pluck('id')->map(fn (int $id): string => 'part:'.$id))->sort()->values()->all();
                    $submitted = collect($weights)->map(fn (array $item): string => isset($item['group_id']) ? 'group:'.$item['group_id'] : 'part:'.(int) $item['part_id'])->sort()->values()->all();
                    if ($expected !== $submitted) {
                        throw ValidationException::withMessages(['grading_level_weights' => 'Bitte ausschließlich alle aktuellen Bausteine dieser äußeren Ebene gewichten.']);
                    }
                    $payload['grading_level_weights'] = array_map(fn (array $item): array => [
                        ...(isset($item['group_id']) ? ['grading_group_id' => $item['group_id']] : ['teaching_entry_grading_part_id' => (int) $item['part_id']]), 'weight' => (float) $item['weight'],
                    ], $weights);
                }
            }
            $area->update($payload);
            TeachingGradingAdjustmentStructure::clearContextWeights($area, $affectedContexts);
        });

        return new TeachingEntryAreaResource($entryArea->refresh()->loadCount('entryDefinitions'));
    }

    public function destroy(TeachingEntryArea $entryArea): JsonResponse|Response
    {
        $this->ensureAreaBelongsToUser($entryArea, $this->authorizedUser());
        $entryCount = $entryArea->entryDefinitions()->count();

        if ($entryCount > 0) {
            return response()->json([
                'message' => 'Dieser Bereich enthält noch Einträge und kann nicht gelöscht werden.',
                'entry_count' => $entryCount,
            ], 409);
        }

        $courseCount = $entryArea->teachingCourses()->count();

        if ($courseCount > 0) {
            return response()->json([
                'message' => 'Dieser Bereich wird noch als Benotungsschema verwendet und kann nicht gelöscht werden.',
                'course_count' => $courseCount,
            ], 409);
        }

        $entryArea->delete();

        return response()->noContent();
    }

    private function authorizedUser(): User
    {
        if (! $user = $this->userHasRole(['admin', 'teaching_admin', 'teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        if (! $user->schoolyear_id) {
            abort(409, 'Es ist kein Schuljahr ausgewählt.');
        }

        return $user;
    }

    private function ensureAreaBelongsToUser(TeachingEntryArea $area, User $user): void
    {
        if ($area->user_id !== $user->id
            || $area->school_id !== $user->school_id
            || $area->schoolyear_id !== $user->schoolyear_id) {
            abort(403, 'Sie haben keine Berechtigung');
        }
    }

    /**
     * @return array{
     *     schoolyear: array{id: int, label: string},
     *     area_count: int,
     *     entry_count: int,
     * }|null
     */
    private function previousYearImportOffer(User $user): ?array
    {
        $previousSchoolyear = $this->previousSchoolyearForImport($user->selectedSchoolyear);

        if (! $previousSchoolyear) {
            return null;
        }

        $sourceAreas = TeachingEntryArea::query()
            ->whereBelongsTo($user)
            ->where('school_id', $user->school_id)
            ->where('schoolyear_id', $previousSchoolyear->id)
            ->withCount('entryDefinitions')
            ->get();

        if ($sourceAreas->isEmpty()) {
            return null;
        }

        return [
            'schoolyear' => [
                'id' => (int) $previousSchoolyear->id,
                'label' => (string) ($previousSchoolyear->concerns ?: $previousSchoolyear->name),
            ],
            'area_count' => $sourceAreas->count(),
            'entry_count' => (int) $sourceAreas->sum('entry_definitions_count'),
        ];
    }

    private function previousSchoolyearForImport(?Schoolyear $schoolyear): ?Schoolyear
    {
        if (! $schoolyear) {
            return null;
        }

        $previousConcern = $this->previousSchoolyearConcern($schoolyear->concerns);

        if ($previousConcern === null) {
            return null;
        }

        return Schoolyear::query()
            ->where('school_id', $schoolyear->school_id)
            ->get()
            ->first(fn (Schoolyear $candidate): bool => $this->normalizeSchoolyearConcern($candidate->concerns) === $previousConcern);
    }

    private function previousSchoolyearConcern(?string $value): ?string
    {
        $normalizedValue = $this->normalizeSchoolyearConcern($value);

        if (! preg_match('/^(\d{4})\/(\d{2})$/', $normalizedValue, $matches)) {
            return null;
        }

        $startYear = (int) $matches[1];
        $endYear = (int) substr((string) ($startYear + 1), -2);

        return sprintf('%d/%02d', $startYear - 1, $endYear - 1);
    }

    private function normalizeSchoolyearConcern(?string $value): string
    {
        if (! is_string($value)) {
            return '';
        }

        preg_match('/(\d{4})\/(\d{2}|\d{4})/', $value, $matches);

        if ($matches === []) {
            return '';
        }

        return sprintf('%s/%s', $matches[1], substr($matches[2], -2));
    }
}
