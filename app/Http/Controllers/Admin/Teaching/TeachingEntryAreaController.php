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
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

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
        $entryArea->update(['name' => $request->validated('name')]);

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
