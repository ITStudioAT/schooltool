<?php

namespace App\Http\Controllers\Admin\Teaching;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Teaching\StoreTeachingEntryDefinitionRequest;
use App\Http\Requests\Admin\Teaching\UpdateTeachingEntryDefinitionRequest;
use App\Http\Resources\Admin\Teaching\TeachingEntryDefinitionResource;
use App\Models\TeachingEntryDefinition;
use App\Models\User;
use App\Services\TeachingCourseStudentEntryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

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

        $payload = $this->entryPayload($request->validated());
        if (! in_array($entryDefinition->grading_part_other_assessment_mode, TeachingEntryDefinition::allowedOtherAssessmentModes($payload['properties_mode']), true)) {
            $payload['grading_part_other_assessment_mode'] = null;
        }
        if ($entryDefinition->gradingPart?->allowed_entry_types === 'points' && $payload['properties_mode'] !== 'points') {
            throw ValidationException::withMessages(['properties_mode' => 'Dieser Benotungsteil erlaubt nur Punktetypen. Bitte zuerst die Zuordnung entfernen.']);
        }
        if ($payload['properties_mode'] !== 'points'
            || collect($entryDefinition->points_grade_thresholds ?? [])->contains(fn (mixed $threshold): bool => $threshold > $payload['maximum_points'])) {
            $payload['points_grade_thresholds'] = null;
        }
        if (! (new TeachingEntryDefinition($payload))->supportsFreeGrading()) {
            $payload['free_grading_mode'] = null;
            $payload['free_deficit_grade_thresholds'] = null;
            $payload['free_points_grade_thresholds'] = null;
        }
        if ($payload['properties_mode'] !== 'plus') {
            $payload['allows_maximum_plus'] = false;
            $payload['sum_plus_evaluations'] = false;
            $payload['maximum_plus_grading_mode'] = null;
            $payload['maximum_plus_grade_thresholds'] = null;
        }
        $hasFreeProperties = $payload['has_properties'] && $payload['properties_mode'] === 'free';
        $propertyPattern = $payload['has_properties'] ? TeachingCourseStudentEntryService::propertyPattern($payload['properties_mode']) : null;
        $specialProperties = $payload['enabled_special_properties'] ?? $entryDefinition->enabled_special_properties;
        if (! $payload['has_properties']) {
            $payload['calculation_mode'] = 'individual';
        }

        $payload['property_evaluations'] = collect($payload['property_evaluations'] ?? $entryDefinition->property_evaluations ?? [])
            ->filter(fn (array $evaluation): bool => ($payload['has_properties'] && in_array($evaluation['property'], $specialProperties, true)) || $hasFreeProperties || ($propertyPattern !== null
                ? preg_match($propertyPattern, $evaluation['property']) === 1
                : in_array($evaluation['property'], $payload['fixed_properties'], true)))
            ->values()
            ->all();

        if ($entryDefinition->teaching_entry_grading_part_id !== null
            && ($payload['category'] !== 'Benotung'
                || $payload['teaching_entry_area_id'] !== $entryDefinition->teaching_entry_area_id)) {
            $payload['teaching_entry_grading_part_id'] = null;
        }

        $entryDefinition->update($payload);

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
     *     description: ?string,
     *     category: string,
     *     has_properties: bool,
     *     properties_mode: string,
     *     fixed_properties: array<int, string>,
     *     has_notifications: bool,
     *     notification_recipients: array<int, string>,
     *     has_table_marking: bool,
     *     table_marking_color: ?string
     * }
     */
    private function entryPayload(array $validated): array
    {
        $isGradingEntry = $validated['category'] === 'Benotung';
        $hasProperties = $isGradingEntry && (bool) $validated['has_properties'];
        $propertiesMode = $hasProperties ? $validated['properties_mode'] : 'free';
        $fixedProperties = $hasProperties && in_array($propertiesMode, ['fixed', 'free'], true)
            ? collect($validated['fixed_properties'] ?? [])
                ->map(fn (string $property): string => trim($property))
                ->filter(fn (string $property): bool => $property !== '')
                ->unique()
                ->values()
                ->all()
            : [];
        $hasNotifications = ! $isGradingEntry && (bool) $validated['has_notifications'];
        $notificationRecipients = $hasNotifications
            ? collect($validated['notification_recipients'] ?? [])
                ->unique()
                ->values()
                ->all()
            : [];
        $hasTableMarking = $isGradingEntry && (bool) $validated['has_table_marking'];
        $tableMarkingColor = $hasTableMarking ? $validated['table_marking_color'] : null;

        return [
            ...(array_key_exists('property_evaluations', $validated) ? ['property_evaluations' => $validated['property_evaluations']] : []),
            ...(array_key_exists('enabled_special_properties', $validated) ? ['enabled_special_properties' => $validated['enabled_special_properties']] : []),
            'teaching_entry_area_id' => (int) $validated['teaching_entry_area_id'],
            'short_name' => Str::of($validated['short_name'])->trim()->upper()->toString(),
            'name' => Str::of($validated['name'])->trim()->toString(),
            'description' => $validated['description'] ?? null,
            'category' => $validated['category'],
            'has_properties' => $hasProperties,
            'properties_mode' => $propertiesMode,
            'maximum_points' => $propertiesMode === 'points' ? (float) $validated['maximum_points'] : null,
            'fixed_properties' => $fixedProperties,
            'has_notifications' => $hasNotifications,
            'notification_recipients' => $notificationRecipients,
            'has_table_marking' => $hasTableMarking,
            'table_marking_color' => $tableMarkingColor,
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
