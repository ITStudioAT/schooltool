<?php

namespace App\Http\Controllers\Admin\Teaching;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\Teaching\TeachingEntryAreaResource;
use App\Http\Resources\Admin\Teaching\TeachingEntryDefinitionResource;
use App\Http\Resources\Admin\Teaching\TeachingEntryGradingPartResource;
use App\Models\Schoolyear;
use App\Models\TeachingEntryArea;
use App\Models\TeachingEntryDefinition;
use App\Models\TeachingEntryGradingPart;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TeachingEntryAreaImportsController extends Controller
{
    public function store(): JsonResponse
    {
        $user = $this->authorizedUser();
        $currentSchoolyear = $user->selectedSchoolyear;
        $previousSchoolyear = $this->previousSchoolyearForImport($currentSchoolyear);

        if (! $currentSchoolyear || ! $previousSchoolyear) {
            abort(422, 'Import nicht möglich.');
        }

        return DB::transaction(function () use ($user, $currentSchoolyear, $previousSchoolyear): JsonResponse {
            Schoolyear::query()->whereKey($currentSchoolyear->id)->lockForUpdate()->firstOrFail();

            $hasCurrentAreas = TeachingEntryArea::query()
                ->whereBelongsTo($user)
                ->where('school_id', $user->school_id)
                ->where('schoolyear_id', $currentSchoolyear->id)
                ->exists();

            if ($hasCurrentAreas) {
                return response()->json([
                    'message' => 'Für das aktuelle Schuljahr sind bereits Bereiche vorhanden.',
                ], 409);
            }

            $sourceAreas = TeachingEntryArea::query()
                ->whereBelongsTo($user)
                ->where('school_id', $user->school_id)
                ->where('schoolyear_id', $previousSchoolyear->id)
                ->with(['entryDefinitions' => fn (HasMany $query) => $query->orderBy('id')])
                ->orderBy('name')
                ->get();

            if ($sourceAreas->isEmpty()) {
                return response()->json([
                    'message' => 'Im vorherigen Schuljahr sind keine Bereiche vorhanden.',
                ], 422);
            }

            $copiedEntries = collect();
            $copiedGradingParts = collect();
            $copiedAreas = $sourceAreas->map(function (TeachingEntryArea $sourceArea) use ($user, $currentSchoolyear, $copiedEntries, $copiedGradingParts): TeachingEntryArea {
                $copiedArea = $sourceArea->replicate();
                $copiedArea->school_id = $user->school_id;
                $copiedArea->schoolyear_id = $currentSchoolyear->id;
                $copiedArea->user_id = $user->id;
                $copiedArea->save();
                $copiedGradingParts->push($copiedArea->createInitialGradingPart());

                $sourceArea->entryDefinitions->each(function (TeachingEntryDefinition $sourceEntry) use ($user, $currentSchoolyear, $copiedArea, $copiedEntries): void {
                    $copiedEntry = $sourceEntry->replicate();
                    $copiedEntry->school_id = $user->school_id;
                    $copiedEntry->schoolyear_id = $currentSchoolyear->id;
                    $copiedEntry->user_id = $user->id;
                    $copiedEntry->teaching_entry_area_id = $copiedArea->id;
                    $copiedEntry->save();

                    $copiedEntries->push($copiedEntry);
                });

                $copiedArea->setAttribute('entry_definitions_count', $sourceArea->entryDefinitions->count());

                return $copiedArea;
            });

            return $this->importResponse($copiedAreas, $copiedEntries, $copiedGradingParts);
        });
    }

    /**
     * @param  Collection<int, TeachingEntryArea>  $areas
     * @param  Collection<int, TeachingEntryDefinition>  $entries
     * @param  Collection<int, TeachingEntryGradingPart>  $gradingParts
     */
    private function importResponse(Collection $areas, Collection $entries, Collection $gradingParts): JsonResponse
    {
        return response()->json([
            'data' => [
                'areas' => TeachingEntryAreaResource::collection($areas)->resolve(),
                'entries' => TeachingEntryDefinitionResource::collection($entries)->resolve(),
                'grading_parts' => TeachingEntryGradingPartResource::collection($gradingParts)->resolve(),
            ],
            'imported_area_count' => $areas->count(),
            'imported_entry_count' => $entries->count(),
        ], 201);
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
