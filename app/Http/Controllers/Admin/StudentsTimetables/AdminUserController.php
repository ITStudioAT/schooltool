<?php

namespace App\Http\Controllers\Admin\StudentsTimetables;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StudentsTimetables\AdminUserIndexRequest;
use App\Http\Requests\Admin\StudentsTimetables\AdminUserStoreRequest;
use App\Http\Requests\Admin\StudentsTimetables\AdminUserUpdateRequest;
use App\Http\Resources\Admin\PaginateResource;
use App\Http\Resources\Admin\TeacherResource;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AdminUserController extends Controller
{
    private const DEFAULT_ROLE_NAME = 'studentstimetables_admin';

    public function index(AdminUserIndexRequest $request): JsonResponse
    {
        $roleName = $this->roleName($request);
        $authUser = $this->authorizedUser();
        $validated = $request->validated();
        $searchString = $validated['search_string'] ?? null;

        $users = User::bySchoolAndRole($authUser->school_id, $roleName)
            ->with('roles')
            ->when($searchString, function ($query, string $searchString): void {
                $query->where(function ($query) use ($searchString): void {
                    $query->where('short', 'like', "%{$searchString}%")
                        ->orWhere('last_name', 'like', "%{$searchString}%")
                        ->orWhere('first_name', 'like', "%{$searchString}%")
                        ->orWhere('email', 'like', "%{$searchString}%");
                });
            })
            ->orderBy('short')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(config('schooltool.pagination'));

        return response()->json([
            'data' => TeacherResource::collection($users),
            'meta' => new PaginateResource($users),
        ]);
    }

    public function store(AdminUserStoreRequest $request): JsonResponse
    {
        $roleName = $this->roleName($request);
        $authUser = $this->authorizedUser();
        $validated = $request->validated();
        $this->assertUniqueUserFields($authUser->school_id, $validated);

        $user = User::create([
            'school_id' => $authUser->school_id,
            'short' => $this->normalizedShort($validated),
            'last_name' => $validated['last_name'],
            'first_name' => $validated['first_name'] ?? null,
            'email' => $validated['email'],
            'password' => Hash::make(now()),
        ]);
        $user->email_verified_at = now();
        $user->confirmed_at = now();
        $user->is_active = 1;
        $user->save();

        Role::firstOrCreate([
            'name' => $roleName,
            'guard_name' => 'web',
        ]);
        $user->assignRole($roleName);

        return response()->json(new TeacherResource($user->fresh()->load('roles')), 200);
    }

    public function update(AdminUserUpdateRequest $request, User $adminUser): JsonResponse
    {
        $roleName = $this->roleName($request);
        $authUser = $this->authorizedUser();
        $validated = $request->validated();

        if ((int) $validated['id'] !== $adminUser->id) {
            abort(422, 'Die Benutzerdaten passen nicht zur Anfrage.');
        }

        $this->assertManageableUser($authUser, $adminUser, $roleName);
        $this->assertUniqueUserFields($authUser->school_id, $validated, $adminUser);

        $adminUser->update([
            'short' => $this->normalizedShort($validated),
            'last_name' => $validated['last_name'],
            'first_name' => $validated['first_name'] ?? null,
            'email' => $validated['email'],
        ]);

        return response()->json(new TeacherResource($adminUser->fresh()->load('roles')), 200);
    }

    public function toggleActive(Request $request): JsonResponse
    {
        $roleName = $this->roleName($request);
        $authUser = $this->authorizedUser();
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);
        $user = User::findOrFail($validated['user_id']);
        $this->assertManageableUser($authUser, $user, $roleName);

        $user->is_active = ! (bool) $user->is_active;
        $user->save();

        return response()->json(new TeacherResource($user->fresh()->load('roles')), 200);
    }

    private function authorizedUser(): User
    {
        if (! $authUser = $this->userHasRole(['super_admin', 'admin', self::DEFAULT_ROLE_NAME])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        return $authUser;
    }

    private function assertManageableUser(User $authUser, User $user, string $roleName): void
    {
        if ($user->school_id !== $authUser->school_id || ! $user->hasRole($roleName)) {
            abort(403, 'Diese Änderung kann nicht durchgeführt werden.');
        }
    }

    private function roleName(Request $request): string
    {
        return (string) ($request->route('managedRole') ?: self::DEFAULT_ROLE_NAME);
    }

    private function assertUniqueUserFields(int $schoolId, array $data, ?User $ignoreUser = null): void
    {
        $short = $this->normalizedShort($data);
        if ($short !== null) {
            $shortExists = User::where('school_id', $schoolId)
                ->when($ignoreUser, fn ($query, User $user) => $query->whereNot('id', $user->id))
                ->where('short', $short)
                ->exists();

            if ($shortExists) {
                abort(409, 'Das Kurzzeichen wird bereits verwendet');
            }
        }

        $emailExists = User::where('school_id', $schoolId)
            ->when($ignoreUser, fn ($query, User $user) => $query->whereNot('id', $user->id))
            ->where('email', $data['email'])
            ->exists();

        if ($emailExists) {
            abort(409, 'Die E-Mail-Adresse wird bereits verwendet');
        }
    }

    private function normalizedShort(array $data): ?string
    {
        $short = trim((string) ($data['short'] ?? ''));

        return $short !== '' ? $short : null;
    }
}
