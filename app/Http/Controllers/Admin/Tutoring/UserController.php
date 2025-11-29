<?php

namespace App\Http\Controllers\Admin\Tutoring;

use App\Http\Controllers\Controller;
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
        $currentPage = $validated['page'] ?? 1;
        $search_string = $validated['search_string'] ?? null;

        $users = User::bySchoolAndRole($auth_user->school_id, 'tutoring_user')
            ->when($search_string, function ($query, $search_string) {
                $query->where(function ($q) use ($search_string) {
                    $q->where('last_name', 'like', "%{$search_string}%")
                        ->orWhere('first_name', 'like', "%{$search_string}%")
                        ->orWhere('email', 'like', "%{$search_string}%");
                });
            })
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(config('schooltool.pagination'));

        return response()->json([
            'data' => UserResource::collection($users),
            'meta' => new PaginateResource($users),
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
}
