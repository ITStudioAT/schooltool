<?php

namespace App\Http\Controllers\Admin\ABA;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ABA\AbaStoreRequest;
use App\Http\Requests\Admin\ABA\AbaUpdateRequest;
use App\Http\Resources\Admin\ABA\AbaResource;
use App\Http\Resources\Admin\PaginateResource;
use App\Models\Aba;
use App\Models\Schoolyear;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AbaController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        if (! $auth_user = $this->userHasRole(['aba_teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $schoolyear_id = $auth_user->schoolyear_id;

        $abas = Aba::query()
            ->where('school_id', $auth_user->school_id)
            ->where('user_id', $auth_user->id)
            ->when($schoolyear_id, fn ($q) => $q->where('schoolyear_id', $schoolyear_id))
            ->with(['attachments', 'schoolyear'])
            ->orderByDesc('created_at')
            ->paginate(config('schooltool.pagination'));

        return response()->json([
            'data' => AbaResource::collection($abas),
            'meta' => new PaginateResource($abas),
        ]);
    }

    public function store(AbaStoreRequest $request): JsonResponse
    {
        if (! $auth_user = $this->userHasRole(['aba_teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $validated = $request->validated()['data'];
        $targetSchoolyearId = (int) ($validated['schoolyear_id'] ?? $auth_user->schoolyear_id);

        if (! $this->schoolyearBelongsToUserSchool($targetSchoolyearId, (int) $auth_user->school_id)) {
            abort(403, 'Das gewählte Schuljahr ist nicht erlaubt.');
        }

        $validated['school_id'] = $auth_user->school_id;
        $validated['schoolyear_id'] = $targetSchoolyearId;
        $validated['user_id'] = $auth_user->id;
        $validated['created_on'] = now()->toDateString();

        $aba = Aba::create($validated);
        $aba->load(['attachments', 'schoolyear']);

        return response()->json(new AbaResource($aba), 201);
    }

    public function show(Aba $aba): JsonResponse
    {
        if (! $auth_user = $this->userHasRole(['aba_teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        if (! $this->canAccessAba($aba, $auth_user->id, $auth_user->school_id)) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $aba->load(['attachments', 'schoolyear']);

        return response()->json(new AbaResource($aba));
    }

    public function update(AbaUpdateRequest $request, Aba $aba): JsonResponse
    {
        if (! $auth_user = $this->userHasRole(['aba_teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        if (! $this->canAccessAba($aba, $auth_user->id, $auth_user->school_id)) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $validated = $request->validated()['data'] ?? [];

        if (array_key_exists('schoolyear_id', $validated)) {
            $targetSchoolyearId = (int) $validated['schoolyear_id'];
            if (! $this->schoolyearBelongsToUserSchool($targetSchoolyearId, (int) $auth_user->school_id)) {
                abort(403, 'Das gewählte Schuljahr ist nicht erlaubt.');
            }
        }

        $aba->update($validated);
        $aba->load(['attachments', 'schoolyear']);

        return response()->json(new AbaResource($aba));
    }

    public function destroy(Aba $aba): Response
    {
        if (! $auth_user = $this->userHasRole(['aba_teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        if (! $this->canAccessAba($aba, $auth_user->id, $auth_user->school_id)) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $aba->delete();

        return response()->noContent();
    }

    private function canAccessAba(Aba $aba, int $userId, int $schoolId): bool
    {
        return $aba->school_id === $schoolId && $aba->user_id === $userId;
    }

    private function schoolyearBelongsToUserSchool(int $schoolyearId, int $schoolId): bool
    {
        return Schoolyear::query()
            ->where('id', $schoolyearId)
            ->where('school_id', $schoolId)
            ->exists();
    }
}
