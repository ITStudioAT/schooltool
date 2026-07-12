<?php

namespace App\Http\Controllers\Admin\Teaching;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Teaching\StoreTeachingEntryAreaRequest;
use App\Http\Requests\Admin\Teaching\UpdateTeachingEntryAreaRequest;
use App\Http\Resources\Admin\Teaching\TeachingEntryAreaResource;
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

        return TeachingEntryAreaResource::collection($areas);
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

        return (new TeachingEntryAreaResource($area))->response()->setStatusCode(201);
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
}
