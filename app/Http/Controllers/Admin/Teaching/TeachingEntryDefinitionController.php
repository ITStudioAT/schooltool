<?php

namespace App\Http\Controllers\Admin\Teaching;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Teaching\StoreTeachingEntryDefinitionRequest;
use App\Http\Requests\Admin\Teaching\UpdateTeachingEntryDefinitionRequest;
use App\Http\Resources\Admin\Teaching\TeachingEntryDefinitionResource;
use App\Models\TeachingEntryDefinition;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class TeachingEntryDefinitionController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $authUser = $this->authorizedUser();

        $entryDefinitions = TeachingEntryDefinition::query()
            ->whereBelongsTo($authUser)
            ->where('school_id', $authUser->school_id)
            ->where('schoolyear_id', $authUser->schoolyear_id)
            ->orderBy('teaching_entry_area_id')
            ->orderBy('category')
            ->orderBy('name')
            ->get();

        return TeachingEntryDefinitionResource::collection($entryDefinitions);
    }

    public function store(StoreTeachingEntryDefinitionRequest $request): JsonResponse
    {
        $authUser = $this->authorizedUser();
        $entryDefinition = TeachingEntryDefinition::query()->create([
            ...$this->entryPayload($request->validated()),
            'school_id' => $authUser->school_id,
            'schoolyear_id' => $authUser->schoolyear_id,
            'user_id' => $authUser->id,
        ]);

        return (new TeachingEntryDefinitionResource($entryDefinition))
            ->response()
            ->setStatusCode(201);
    }

    public function update(
        UpdateTeachingEntryDefinitionRequest $request,
        TeachingEntryDefinition $entryDefinition
    ): TeachingEntryDefinitionResource {
        $authUser = $this->authorizedUser();
        $this->ensureEntryBelongsToUser($entryDefinition, $authUser);

        $entryDefinition->update($this->entryPayload($request->validated()));

        return new TeachingEntryDefinitionResource($entryDefinition->refresh());
    }

    public function destroy(TeachingEntryDefinition $entryDefinition): Response
    {
        $authUser = $this->authorizedUser();
        $this->ensureEntryBelongsToUser($entryDefinition, $authUser);

        $entryDefinition->delete();

        return response()->noContent();
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{
     *     teaching_entry_area_id: int,
     *     short_name: string,
     *     name: string,
     *     category: string,
     *     has_properties: bool,
     *     properties_mode: string,
     *     fixed_properties: array<int, string>
     * }
     */
    private function entryPayload(array $validated): array
    {
        $hasProperties = (bool) $validated['has_properties'];
        $propertiesMode = $hasProperties ? $validated['properties_mode'] : 'free';
        $fixedProperties = $hasProperties && $propertiesMode === 'fixed'
            ? collect($validated['fixed_properties'] ?? [])
                ->map(fn (string $property): string => trim($property))
                ->filter()
                ->unique()
                ->values()
                ->all()
            : [];

        return [
            'teaching_entry_area_id' => (int) $validated['teaching_entry_area_id'],
            'short_name' => Str::of($validated['short_name'])->trim()->upper()->toString(),
            'name' => Str::of($validated['name'])->trim()->toString(),
            'category' => $validated['category'],
            'has_properties' => $hasProperties,
            'properties_mode' => $propertiesMode,
            'fixed_properties' => $fixedProperties,
        ];
    }

    private function authorizedUser(): User
    {
        if (! $authUser = $this->userHasRole(['admin', 'teaching_admin', 'teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        if (! $authUser->schoolyear_id) {
            abort(409, 'Es ist kein Schuljahr ausgewählt.');
        }

        return $authUser;
    }

    private function ensureEntryBelongsToUser(TeachingEntryDefinition $entryDefinition, User $user): void
    {
        if (
            $entryDefinition->user_id !== $user->id
            || $entryDefinition->school_id !== $user->school_id
            || $entryDefinition->schoolyear_id !== $user->schoolyear_id
        ) {
            abort(403, 'Sie haben keine Berechtigung');
        }
    }
}
