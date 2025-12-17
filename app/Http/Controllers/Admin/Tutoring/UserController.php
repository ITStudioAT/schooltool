<?php

namespace App\Http\Controllers\Admin\Tutoring;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Tutoring\UserConfirmUsersRequest;
use App\Http\Requests\Admin\Tutoring\UserDeleteUsersRequest;
use App\Http\Requests\Admin\Tutoring\UserIndexRequest;
use App\Http\Requests\Admin\Tutoring\UserStoreRequest;
use App\Http\Requests\Admin\Tutoring\UserUpdateRequest;
use App\Http\Resources\Admin\PaginateResource;
use App\Http\Resources\Admin\UserResource;
use App\Models\User;
use App\Services\UserService;
use Barryvdh\Debugbar\Facades\Debugbar;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index(UserIndexRequest $request)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'tutoring_admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $validated = $request->validated();
        $search_string = $validated['search_string'] ?? null;
        $selected_filter = $validated['selected_filter'] ?? null;

        $users = User::bySchoolAndRole($auth_user->school_id, 'tutoring_user')
            ->when($search_string, function ($query, $search_string) {
                $query->where(function ($q) use ($search_string) {
                    $q->where('last_name', 'like', "%{$search_string}%")
                        ->orWhere('first_name', 'like', "%{$search_string}%")
                        ->orWhere('email', 'like', "%{$search_string}%");
                });
            })
            ->when($selected_filter == 'confirmation', function ($query) {
                $query->whereNull('confirmed_at')->whereNotNull('email_verified_at');
            })
            ->when($selected_filter == 'email', function ($query) {
                $query->whereNull('email_verified_at');
            })
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(config('schooltool.pagination'));


        // Löschbare Benutzer zählen (E-Mail nicht bestätigt und nur Rolle tutoring_user)
        $count_deletable_users = User::bySchoolAndRole($auth_user->school_id, 'tutoring_user')
            ->whereNull('email_verified_at')
            ->whereHas('roles', function ($query) {
                $query->havingRaw('COUNT(*) = 1');
            }, '=', 1)
            ->count();


        return response()->json([
            'data' => UserResource::collection($users),
            'meta' => new PaginateResource($users),
            'count_deletable_users' => $count_deletable_users,
        ]);
    }


    public function store(UserStoreRequest $request, UserService $service)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'tutoring_admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $validated = $request->validated();
        $validated['roles'] = [
            ['name' => 'tutoring_user', 'checked' => true]
        ];
        $user = $service->store($auth_user->school_id, $validated);

        return response()->json(new UserResource($user), 200);
    }

    public function update(UserUpdateRequest $request, User $user, UserService $service)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'tutoring_admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $validated = $request->validated();
        $validated['roles'] = [
            ['name' => 'tutoring_user', 'checked' => true]
        ];

        $user = $service->update($validated);

        return response()->json(new UserResource($user), 200);
    }

    public function deleteUsers(UserDeleteUsersRequest $request, UserService $service)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'tutoring_admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $validated = $request->validated();
        $service->deleteTutoringUsers($validated['data']);
        return response()->noContent();
    }

    public function confirmUsers(UserConfirmUsersRequest $request, UserService $service)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'tutoring_admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $validated = $request->validated();
        $service->confirmTutoringUsers($validated['data']);
        return response()->noContent();
    }

    public function cleanUsers(Request $request, UserService $service)
    // Löschen aller Benutzer, die nicht mehr benötigt werden (keine E-Mail bestätigt)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'tutoring_admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $service->cleanTutoringUsers($auth_user->school_id);
        return response()->noContent();
    }
}
