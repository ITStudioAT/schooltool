<?php

namespace App\Http\Controllers\Admin\Teaching;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Teaching\StoreTeachingEntryGradingPartRequest;
use App\Http\Requests\Admin\Teaching\UpdateTeachingEntryGradingPartRequest;
use App\Http\Resources\Admin\Teaching\TeachingEntryAreaResource;
use App\Http\Resources\Admin\Teaching\TeachingEntryDefinitionResource;
use App\Http\Resources\Admin\Teaching\TeachingEntryGradingPartResource;
use App\Models\TeachingEntryArea;
use App\Models\TeachingEntryDefinition;
use App\Models\TeachingEntryGradingPart;
use App\Models\User;
use App\Support\TeachingGradingAdjustmentStructure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class TeachingEntryGradingPartController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $user = $this->authorizedUser();

        $gradingParts = TeachingEntryGradingPart::query()
            ->withSum(['entryDefinitions as overall_maximum_points' => fn ($query) => $query->where('properties_mode', 'points')], 'maximum_points')
            ->whereBelongsTo($user)
            ->where('school_id', $user->school_id)
            ->where('schoolyear_id', $user->schoolyear_id)
            ->orderBy('name')
            ->get();

        return TeachingEntryGradingPartResource::collection($gradingParts);
    }

    public function store(StoreTeachingEntryGradingPartRequest $request): JsonResponse
    {
        $user = $this->authorizedUser();
        $gradingPart = DB::transaction(function () use ($request, $user): TeachingEntryGradingPart {
            $areaId = $request->integer('teaching_entry_area_id');
            $this->lockArea($user, $areaId);
            TeachingGradingAdjustmentStructure::assertChange(TeachingEntryArea::query()->findOrFail($areaId), 'teaching_entry_area_id', partChange: ['id' => -1, 'points_assessment_mode' => 'individual']);
            $this->ensureFixedPercentageTotal($user, $areaId, $request->validated('fixed_percentage'));

            return TeachingEntryGradingPart::query()->create([
                'school_id' => $user->school_id,
                'schoolyear_id' => $user->schoolyear_id,
                'user_id' => $user->id,
                ...$request->validated(),
            ]);
        });

        return (new TeachingEntryGradingPartResource($gradingPart))->response()->setStatusCode(201);
    }

    public function update(
        UpdateTeachingEntryGradingPartRequest $request,
        TeachingEntryGradingPart $entryGradingPart
    ): TeachingEntryGradingPartResource {
        $user = $this->authorizedUser();
        $this->ensureGradingPartBelongsToUser($entryGradingPart, $user);
        $updatedArea = null;
        DB::transaction(function () use ($request, $entryGradingPart, $user, &$updatedArea): void {
            $this->lockArea($user, $entryGradingPart->teaching_entry_area_id);
            $entryGradingPart->refresh();
            if ($entryGradingPart->entryDefinitions()->get()->contains(fn ($entry): bool => ! $entryGradingPart->allowsEntry($entry, $request->validated('allowed_entry_types', $entryGradingPart->allowed_entry_types)))) {
                throw ValidationException::withMessages([
                    'allowed_entry_types' => 'Bitte zuerst die unzulässigen Eintragstypen ausdrücklich aus diesem Benotungsteil entfernen.',
                ]);
            }
            $this->ensureFixedPercentageTotal(
                $user,
                $entryGradingPart->teaching_entry_area_id,
                $request->validated('fixed_percentage', $entryGradingPart->fixed_percentage),
                $entryGradingPart->id,
            );
            $payload = $request->validated();
            if (array_key_exists('sign_adjustment', $payload) && ! Schema::hasColumn('teaching_entry_grading_parts', 'sign_adjustment')) {
                abort(409, 'Die vorbereitete Migration für die Notenanpassung muss zuerst angewendet werden.');
            }
            if (array_key_exists('points_assessment_mode', $payload)) {
                TeachingGradingAdjustmentStructure::assertChange(
                    TeachingEntryArea::query()->findOrFail($entryGradingPart->teaching_entry_area_id),
                    'points_assessment_mode',
                    partChange: ['id' => $entryGradingPart->id, 'points_assessment_mode' => $payload['points_assessment_mode']],
                );
            }
            foreach ($payload['entry_standard_grade_occurrences'] ?? [] as $occurrence) {
                $entry = $entryGradingPart->entryDefinitions()->whereKey($occurrence['entry_definition_id'])
                    ->where('user_id', $user->id)->where('school_id', $user->school_id)
                    ->where('schoolyear_id', $user->schoolyear_id)->where('teaching_entry_area_id', $entryGradingPart->teaching_entry_area_id)
                    ->lockForUpdate()->first();
                if (! $entry || ! TeachingEntryGradingPart::isStandardGradeType($entry)) {
                    throw ValidationException::withMessages(['entry_standard_grade_occurrences' => 'Die Anzahl ist nur für zugeordnete Standardnotentypen dieses Benotungsteils verfügbar.']);
                }
                $entry->update(['standard_grade_occurrences' => TeachingEntryDefinition::normalizeStandardGradeOccurrences($occurrence['configuration'])]);
            }
            unset($payload['entry_standard_grade_occurrences']);
            if (isset($payload['sign_grade_thresholds'])) {
                $payload['sign_grade_thresholds'] = array_map(fn (mixed $value): int => (int) $value, $payload['sign_grade_thresholds']);
            }
            if (($payload['allowed_entry_types'] ?? $entryGradingPart->allowed_entry_types) !== 'points'
                && ! in_array($payload['points_assessment_mode'] ?? $entryGradingPart->points_assessment_mode, ['sum_percent', 'plus_minus', 'sign_grade', 'sign_adjust', 'grade_each', 'grade_mean'], true)) {
                $payload['points_assessment_mode'] = 'individual';
                $payload['overall_points_grade_thresholds'] = null;
            }
            if (isset($payload['overall_points_grade_thresholds'])) {
                $payload['overall_points_grade_thresholds'] = array_map(fn (mixed $value): float => (float) $value, $payload['overall_points_grade_thresholds']);
            }
            $entryGradingPart->update($payload);
            $area = TeachingEntryArea::query()->findOrFail($entryGradingPart->teaching_entry_area_id);
            if (TeachingGradingAdjustmentStructure::clearContextWeights($area, [$entryGradingPart->grading_group_id ?? 'root'])) {
                $updatedArea = $area;
            }
        });

        $resource = new TeachingEntryGradingPartResource($entryGradingPart->refresh());
        $additional = [];
        if ($updatedArea !== null) {
            $additional['entry_area'] = (new TeachingEntryAreaResource($updatedArea->loadCount('entryDefinitions')))->resolve();
        }
        if ($request->has('entry_standard_grade_occurrences')) {
            $additional['entry_definitions'] = TeachingEntryDefinitionResource::collection($entryGradingPart->entryDefinitions)->resolve();
        }
        $resource->additional($additional);

        return $resource;
    }

    public function destroy(TeachingEntryGradingPart $entryGradingPart): Response
    {
        $user = $this->authorizedUser();
        $this->ensureGradingPartBelongsToUser($entryGradingPart, $user);
        DB::transaction(function () use ($entryGradingPart, $user): void {
            $this->lockArea($user, $entryGradingPart->teaching_entry_area_id);
            $area = TeachingEntryArea::query()->findOrFail($entryGradingPart->teaching_entry_area_id);
            $groups = $area->grading_part_groups ?? [];
            $changes = [];
            foreach ($groups as $index => $group) {
                if (collect($group['weights'] ?? [])->contains('teaching_entry_grading_part_id', $entryGradingPart->id)) {
                    unset($groups[$index]['weights']);
                    $changes['grading_part_groups'] = $groups;
                }
            }
            if (collect($area->grading_level_weights ?? [])->contains('teaching_entry_grading_part_id', $entryGradingPart->id)) {
                $changes['grading_level_weights'] = null;
            }
            if ($changes !== []) {
                $area->update($changes);
            }
            $entryGradingPart->delete();
            TeachingGradingAdjustmentStructure::clearContextWeights($area, [$entryGradingPart->grading_group_id ?? 'root']);
        });

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

    private function lockArea(User $user, int $areaId): void
    {
        TeachingEntryArea::query()
            ->whereKey($areaId)
            ->whereBelongsTo($user)
            ->where('school_id', $user->school_id)
            ->where('schoolyear_id', $user->schoolyear_id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function ensureFixedPercentageTotal(User $user, int $areaId, ?float $percentage, ?int $partId = null): void
    {
        if ($percentage === null) {
            return;
        }

        $otherPercentages = TeachingEntryGradingPart::query()
            ->whereBelongsTo($user)
            ->where('school_id', $user->school_id)
            ->where('schoolyear_id', $user->schoolyear_id)
            ->where('teaching_entry_area_id', $areaId)
            ->when($partId !== null, fn ($query) => $query->whereKeyNot($partId))
            ->sum('fixed_percentage');

        if ((int) round((float) $otherPercentages * 1000) + (int) round($percentage * 1000) > 100000) {
            throw ValidationException::withMessages([
                'fixed_percentage' => 'Die festen Prozentanteile in diesem Bereich dürfen zusammen höchstens 100 % ergeben.',
            ]);
        }
    }

    private function ensureGradingPartBelongsToUser(TeachingEntryGradingPart $gradingPart, User $user): void
    {
        if ($gradingPart->user_id !== $user->id
            || $gradingPart->school_id !== $user->school_id
            || $gradingPart->schoolyear_id !== $user->schoolyear_id) {
            abort(403, 'Sie haben keine Berechtigung');
        }
    }
}
