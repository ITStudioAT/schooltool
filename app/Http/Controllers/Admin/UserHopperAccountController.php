<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DeleteUserHopperAccountRequest;
use App\Http\Requests\Admin\StoreUserHopperAccountRequest;
use App\Http\Requests\Admin\SwitchUserHopperAccountRequest;
use App\Models\User;
use App\Services\UserHopperService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class UserHopperAccountController extends Controller
{
    public function index(Request $request, UserHopperService $service): JsonResponse
    {
        $authUser = $this->authorizedUser($request);

        return response()->json([
            'data' => $service->loadHopperAccounts($authUser),
        ]);
    }

    public function loadSwitchableSchools(Request $request, UserHopperService $service): JsonResponse
    {
        $authUser = $this->authorizedUser($request);

        $validated = $request->validate([
            'email' => ['nullable', 'string', 'email', 'max:255'],
        ]);

        return response()->json(
            $service->loadSwitchableSchools($authUser, $validated['email'] ?? null)->values(),
            200
        );
    }

    public function searchUsers(Request $request, UserHopperService $service): JsonResponse
    {
        $authUser = $this->authorizedUser($request);

        $validated = $request->validate([
            'last_name' => ['nullable', 'string', 'max:255'],
        ]);

        return response()->json(
            $service->searchUsers($authUser, $validated['last_name'] ?? null),
            200
        );
    }

    public function store(StoreUserHopperAccountRequest $request, UserHopperService $service): JsonResponse
    {
        $authUser = $this->authorizedUser($request);
        $validated = $request->validated();

        $targetUser = $service->findHopperTarget(
            $validated['email'],
            (int) $validated['school_id']
        );

        if (! $targetUser) {
            abort(422, 'Kein passendes Konto gefunden.');
        }

        return response()->json([
            'data' => $service->storeHopperAccount($authUser, $targetUser),
        ], 200);
    }

    public function destroy(DeleteUserHopperAccountRequest $request, UserHopperService $service): JsonResponse
    {
        $authUser = $this->authorizedUser($request);

        return response()->json([
            'data' => $service->removeHopperAccount($authUser, (int) $request->validated()['target_user_id']),
        ], 200);
    }

    public function switch(SwitchUserHopperAccountRequest $request, UserHopperService $service): Response
    {
        $authUser = $this->authorizedUser($request);

        $service->switchToHopperAccount($authUser, (int) $request->validated()['target_user_id']);

        return response()->noContent();
    }

    private function authorizedUser(Request $request): User
    {
        $authUser = $request->user();
        if (! $authUser || ! $authUser->hasAdminShellAccess()) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $freshUser = $authUser->fresh();

        return $freshUser instanceof User ? $freshUser->loadMissing('roles') : $authUser;
    }
}
