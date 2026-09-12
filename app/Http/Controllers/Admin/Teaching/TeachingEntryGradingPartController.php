<?php

namespace App\Http\Controllers\Admin\Teaching;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Teaching\StoreTeachingEntryGradingPartRequest;
use App\Http\Requests\Admin\Teaching\UpdateTeachingEntryGradingPartRequest;
use App\Http\Resources\Admin\Teaching\TeachingEntryGradingPartResource;
use App\Models\TeachingEntryArea;
use App\Models\TeachingEntryGradingPart;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
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
        DB::transaction(function () use ($request, $entryGradingPart, $user): void {
            $this->lockArea($user, $entryGradingPart->teaching_entry_area_id);
            $entryGradingPart->refresh();
            if ($request->validated('allowed_entry_types', $entryGradingPart->allowed_entry_types) === 'points'
                && $entryGradingPart->entryDefinitions()->where('properties_mode', '!=', 'points')->exists()) {
                throw ValidationException::withMessages([
                    'allowed_entry_types' => 'Bitte zuerst alle anderen Eintragstypen aus diesem Benotungsteil entfernen oder in Punktetypen ändern.',
                ]);
            }
            $this->ensureFixedPercentageTotal(
                $user,
                $entryGradingPart->teaching_entry_area_id,
                $request->validated('fixed_percentage', $entryGradingPart->fixed_percentage),
                $entryGradingPart->id,
            );
            $payload = $request->validated();
            if (($payload['allowed_entry_types'] ?? $entryGradingPart->allowed_entry_types) !== 'points') {
                $payload['points_assessment_mode'] = 'individual';
                $payload['overall_points_grade_thresholds'] = null;
            }
            if (isset($payload['overall_points_grade_thresholds'])) {
                $payload['overall_points_grade_thresholds'] = array_map(fn (mixed $value): float => (float) $value, $payload['overall_points_grade_thresholds']);
            }
            $entryGradingPart->update($payload);
        });

        return new TeachingEntryGradingPartResource($entryGradingPart->refresh());
    }

    public function destroy(TeachingEntryGradingPart $entryGradingPart): Response
    {
        $this->ensureGradingPartBelongsToUser($entryGradingPart, $this->authorizedUser());
        $entryGradingPart->delete();

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
