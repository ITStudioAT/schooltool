<?php

namespace App\Http\Controllers\Admin\Teaching;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Teaching\StoreTeachingEntryGradingPartRequest;
use App\Http\Resources\Admin\Teaching\TeachingEntryGradingPartResource;
use App\Models\TeachingEntryGradingPart;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class TeachingEntryGradingPartController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $user = $this->authorizedUser();

        $gradingParts = TeachingEntryGradingPart::query()
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
        $gradingPart = TeachingEntryGradingPart::query()->create([
            'school_id' => $user->school_id,
            'schoolyear_id' => $user->schoolyear_id,
            'user_id' => $user->id,
            ...$request->validated(),
        ]);

        return (new TeachingEntryGradingPartResource($gradingPart))->response()->setStatusCode(201);
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

    private function ensureGradingPartBelongsToUser(TeachingEntryGradingPart $gradingPart, User $user): void
    {
        if ($gradingPart->user_id !== $user->id
            || $gradingPart->school_id !== $user->school_id
            || $gradingPart->schoolyear_id !== $user->schoolyear_id) {
            abort(403, 'Sie haben keine Berechtigung');
        }
    }
}
