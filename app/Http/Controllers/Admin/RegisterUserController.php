<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RegisterUserDeleteRegisterUsersRequest;
use App\Http\Requests\Admin\RegisterUserIndexRequest;
use App\Http\Resources\Admin\PaginateResource;
use App\Http\Resources\Admin\RegisterUserResource;
use App\Http\Resources\Admin\UserResource;
use App\Models\Register;
use App\Models\User;
use App\Services\RegisterUserService;
use Illuminate\Http\Request;

class RegisterUserController extends Controller
{
    public function index(RegisterUserIndexRequest $request)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'user'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }


        $validated = $request->validated();
        $currentPage = $validated['page'] ?? 1;
        $search_string = $validated['search_string'] ?? null;


        $register = Register::findOrFail($validated['register_id']);



        $users = $register->users()
            ->with([
                'registerDateBookings' => function ($query) use ($register) {
                    $query->where('register_id', $register->id);
                }
            ])
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

        $count_deletable_users = User::query()
            // correct school
            ->where('school_id', $register->school_id)

            // has role "register_user"
            ->whereHas('roles', function ($q) {
                $q->where('name', 'register_user');
            })

            // and ONLY that one role
            ->withCount('roles')
            ->having('roles_count', 1)

            // user has NO bookings for this register
            ->whereDoesntHave('registerDateBookings', function ($q) use ($register) {
                $q->where('register_id', $register->id);
            })

            ->count();




        return response()->json([
            'count_deletable_users' => $count_deletable_users,
            'data' => RegisterUserResource::collection($users),
            'meta' => new PaginateResource($users),
        ]);
    }

    public function deleteRegisterUsers(RegisterUserDeleteRegisterUsersRequest $request, RegisterUserService $service)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'user'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }
        $validated = $request->validated();
        $register_id = $validated['register_id'];

        $count = $service->deleteRegisterUsers($auth_user->school_id);

        return response()->json([
            'count' => $count
        ]);
    }
}
