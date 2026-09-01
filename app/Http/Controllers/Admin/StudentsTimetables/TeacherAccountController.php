<?php

namespace App\Http\Controllers\Admin\StudentsTimetables;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StudentsTimetables\AdminUserIndexRequest;
use App\Http\Requests\Admin\StudentsTimetables\StoreTeacherRosterEntryRequest;
use App\Http\Requests\Admin\StudentsTimetables\UpdateTeacherRosterEntryRequest;
use App\Http\Resources\Admin\PaginateResource;
use App\Models\Role;
use App\Models\School;
use App\Models\Teacher;
use App\Models\TeachingCourse;
use App\Models\User;
use App\Models\UserGroupMember;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TeacherAccountController extends Controller
{
    private const MANAGER_ROLES = ['super_admin', 'admin', 'studentstimetables_admin'];

    private const TIMETABLE_ROLES = ['studentstimetables_admin', 'studentstimetables_moderator'];

    private const ROSTER_ROLES = ['teacher', ...self::TIMETABLE_ROLES];

    public function index(AdminUserIndexRequest $request): JsonResponse
    {
        $authUser = $this->authorizedUser();
        $validated = $request->validated();
        $searchString = $validated['search_string'] ?? null;
        $page = max(1, (int) ($validated['page'] ?? 1));
        $perPage = (int) config('schooltool.pagination');

        $teachers = Teacher::query()
            ->select(['id', 'school_id', 'short', 'last_name', 'first_name', 'email', 'is_active'])
            ->where('school_id', $authUser->school_id)
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from((new User)->getTable())
                    ->whereColumn('users.school_id', 'teachers.school_id')
                    ->whereRaw('LOWER(TRIM(users.email)) = LOWER(TRIM(teachers.email))');
            })
            ->when($searchString, function ($query, string $searchString): void {
                $this->applySearch($query, $searchString);
            })
            ->get()
            ->map(fn (Teacher $teacher): array => $this->teacherListItem($teacher));

        $users = $this->rosterUsersQuery($authUser)
            ->select(['id', 'school_id', 'short', 'last_name', 'first_name', 'email', 'is_active'])
            ->with('roles:id,name')
            ->when($searchString, function ($query, string $searchString): void {
                $this->applySearch($query, $searchString);
            })
            ->get()
            ->map(fn (User $user): array => $this->userListItem($user, $authUser));

        $items = $teachers
            ->concat($users)
            ->sortBy(
                fn (array $item): string => mb_strtolower("{$item['last_name']}|{$item['first_name']}|{$item['short']}"),
                SORT_NATURAL,
            )
            ->values();

        $paginator = new LengthAwarePaginator(
            $items->forPage($page, $perPage)->values(),
            $items->count(),
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ],
        );

        return response()->json([
            'data' => $paginator->items(),
            'meta' => new PaginateResource($paginator),
        ]);
    }

    public function store(StoreTeacherRosterEntryRequest $request): JsonResponse
    {
        $authUser = $this->authorizedUser();
        $validated = $request->validated();
        $this->assertTeacherEmailAvailable($authUser->school_id, $validated['email']);

        $teacher = Teacher::query()->create([
            'school_id' => $authUser->school_id,
            'short' => $validated['short'] ?? '',
            'last_name' => $validated['last_name'],
            'first_name' => $validated['first_name'] ?? null,
            'email' => $validated['email'],
            'is_active' => true,
        ]);

        return response()->json($this->teacherListItem($teacher), 201);
    }

    public function setActiveState(Request $request): JsonResponse
    {
        $authUser = $this->authorizedUser();
        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);
        $isActive = (bool) $validated['is_active'];

        $updatedCounts = DB::transaction(function () use ($authUser, $isActive): array {
            $teacherUsers = $this->rosterUsersQuery($authUser);

            if (! $isActive) {
                $teacherUsers
                    ->whereKeyNot($authUser->id)
                    ->whereDoesntHave('roles', function (Builder $query): void {
                        $query->whereIn('name', ['admin', 'super_admin']);
                    });
            }

            return [
                'imported_teachers' => Teacher::query()
                    ->where('school_id', $authUser->school_id)
                    ->update(['is_active' => $isActive]),
                'registered_users' => $teacherUsers->update([
                    'is_active' => $isActive,
                    'students_timetables_teacher_listed' => true,
                ]),
            ];
        }, attempts: 3);

        return response()->json([
            'message' => $isActive
                ? 'Alle Lehrkräfte wurden aktiviert.'
                : 'Alle deaktivierbaren Lehrkräfte wurden deaktiviert.',
            'data' => [
                'is_active' => $isActive,
                ...$updatedCounts,
            ],
        ]);
    }

    public function activate(Teacher $teacher): JsonResponse
    {
        $authUser = $this->authorizedUser();
        $this->assertSameSchool($authUser, $teacher->school_id);

        $user = DB::transaction(function () use ($authUser, $teacher): User {
            $lockedTeacher = Teacher::query()
                ->whereKey($teacher->id)
                ->lockForUpdate()
                ->firstOrFail();
            $this->assertSameSchool($authUser, $lockedTeacher->school_id);

            $user = User::query()
                ->where('school_id', $authUser->school_id)
                ->whereRaw('LOWER(TRIM(email)) = ?', [mb_strtolower(trim($lockedTeacher->email))])
                ->lockForUpdate()
                ->first();

            if (! $user) {
                $user = User::create([
                    'school_id' => $authUser->school_id,
                    'short' => $lockedTeacher->short ?: null,
                    'last_name' => $lockedTeacher->last_name,
                    'first_name' => $lockedTeacher->first_name,
                    'email' => mb_strtolower(trim($lockedTeacher->email)),
                    'password' => Hash::make(now()),
                ]);
                $user->email_verified_at = now();
                $user->confirmed_at = now();
            }

            $user->is_active = true;
            $user->students_timetables_teacher_listed = true;
            $user->save();

            Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']);
            $user->assignRole('teacher');
            $lockedTeacher->delete();

            return $user->fresh()->load('roles');
        }, attempts: 3);

        return response()->json($this->userListItem($user, $authUser));
    }

    public function toggleActive(User $teacherUser): JsonResponse
    {
        $authUser = $this->authorizedUser();
        $this->assertManageableRosterUser($authUser, $teacherUser);

        $newState = ! (bool) $teacherUser->is_active;
        if (! $newState && ($teacherUser->is($authUser) || $teacherUser->hasAnyRole(['admin', 'super_admin']))) {
            abort(403, 'Dieses Konto kann hier nicht deaktiviert werden.');
        }

        $teacherUser->is_active = $newState;
        $teacherUser->students_timetables_teacher_listed = true;
        $teacherUser->save();

        Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']);
        $teacherUser->assignRole('teacher');

        return response()->json($this->userListItem($teacherUser->fresh()->load('roles'), $authUser));
    }

    public function toggleTeacherRole(User $teacherUser): JsonResponse
    {
        $authUser = $this->authorizedUser();
        $this->assertManageableRosterUser($authUser, $teacherUser);

        abort(409, 'Die Lehrerrolle ist für Einträge der Lehrerliste verpflichtend.');
    }

    public function setRole(Request $request, User $teacherUser): JsonResponse
    {
        $authUser = $this->authorizedUser();
        $this->assertManageableRosterUser($authUser, $teacherUser);

        if ($teacherUser->is($authUser)) {
            abort(403, 'Die eigene TT-Rolle kann hier nicht geändert werden.');
        }

        if (! $teacherUser->is_active) {
            abort(409, 'Die Rolle kann nur einem aktiven Konto zugewiesen werden.');
        }

        $validated = $request->validate([
            'role' => ['nullable', 'string', Rule::in(self::TIMETABLE_ROLES)],
        ]);
        $roleName = $validated['role'] ?? null;

        DB::transaction(function () use ($roleName, $teacherUser): void {
            Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']);
            $teacherUser->assignRole('teacher');

            foreach (self::TIMETABLE_ROLES as $timetableRole) {
                Role::firstOrCreate(['name' => $timetableRole, 'guard_name' => 'web']);

                if ($timetableRole !== $roleName && $teacherUser->hasRole($timetableRole)) {
                    $teacherUser->removeRole($timetableRole);
                }
            }

            if ($roleName !== null) {
                $teacherUser->assignRole($roleName);
            }

            $teacherUser->students_timetables_teacher_listed = true;
            $teacherUser->save();
        }, attempts: 3);

        return response()->json($this->userListItem($teacherUser->fresh()->load('roles'), $authUser));
    }

    public function update(
        UpdateTeacherRosterEntryRequest $request,
        Teacher $teacher,
    ): JsonResponse {
        $authUser = $this->authorizedUser();
        $this->assertSameSchool($authUser, $teacher->school_id);
        $validated = $request->validated();
        $this->assertTeacherEmailAvailable($authUser->school_id, $validated['email'], teacherToIgnore: $teacher);

        $teacher->update([
            'short' => $validated['short'] ?? '',
            'last_name' => $validated['last_name'],
            'first_name' => $validated['first_name'] ?? null,
            'email' => $validated['email'],
        ]);

        return response()->json($this->teacherListItem($teacher->fresh()));
    }

    public function updateUser(
        UpdateTeacherRosterEntryRequest $request,
        User $teacherUser,
    ): JsonResponse {
        $authUser = $this->authorizedUser();
        $this->assertManageableRosterUser($authUser, $teacherUser);
        $validated = $request->validated();
        $previousEmail = mb_strtolower(trim($teacherUser->email));
        $this->assertTeacherEmailAvailable(
            $authUser->school_id,
            $validated['email'],
            userToIgnore: $teacherUser,
            teacherEmailToIgnore: $previousEmail,
        );

        DB::transaction(function () use ($authUser, $previousEmail, $teacherUser, $validated): void {
            Teacher::query()
                ->where('school_id', $authUser->school_id)
                ->whereRaw('LOWER(TRIM(email)) = ?', [$previousEmail])
                ->update([
                    'short' => $validated['short'] ?? '',
                    'last_name' => $validated['last_name'],
                    'first_name' => $validated['first_name'] ?? null,
                    'email' => $validated['email'],
                ]);

            $teacherUser->update([
                'short' => $validated['short'] ?? null,
                'last_name' => $validated['last_name'],
                'first_name' => $validated['first_name'] ?? null,
                'email' => $validated['email'],
            ]);
            $teacherUser->students_timetables_teacher_listed = true;
            $teacherUser->save();

            Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']);
            $teacherUser->assignRole('teacher');
        }, attempts: 3);

        return response()->json($this->userListItem($teacherUser->fresh()->load('roles'), $authUser));
    }

    public function destroy(Teacher $teacher): JsonResponse
    {
        $authUser = $this->authorizedUser();
        $this->assertSameSchool($authUser, $teacher->school_id);

        DB::transaction(function () use ($authUser, $teacher): void {
            $this->lockSchool($authUser->school_id);

            $lockedTeacher = Teacher::query()
                ->whereKey($teacher->id)
                ->lockForUpdate()
                ->firstOrFail();
            $this->assertSameSchool($authUser, $lockedTeacher->school_id);
            $this->assertNoTeacherDependencies(
                $this->teacherGroupDependencyCount($authUser->school_id, [$lockedTeacher->id]),
            );

            $lockedTeacher->delete();
        }, attempts: 3);

        return response()->json([
            'message' => 'Die Lehrkraft wurde aus der Lehrerliste entfernt.',
        ]);
    }

    public function destroyUser(User $teacherUser): JsonResponse
    {
        $authUser = $this->authorizedUser();
        $this->assertManageableRosterUser($authUser, $teacherUser);

        if ($teacherUser->is($authUser)) {
            abort(403, 'Sie können sich nicht selbst aus der Lehrerliste entfernen.');
        }

        DB::transaction(function () use ($authUser, $teacherUser): void {
            $this->lockSchool($authUser->school_id);

            $lockedUser = User::query()
                ->whereKey($teacherUser->id)
                ->with('roles')
                ->lockForUpdate()
                ->firstOrFail();
            $this->assertManageableRosterUser($authUser, $lockedUser);

            if ($lockedUser->is($authUser)) {
                abort(403, 'Sie können sich nicht selbst aus der Lehrerliste entfernen.');
            }

            $matchingTeachers = Teacher::query()
                ->where('school_id', $authUser->school_id)
                ->whereRaw('LOWER(TRIM(email)) = ?', [mb_strtolower(trim($lockedUser->email))])
                ->lockForUpdate()
                ->get();
            $teacherIds = $matchingTeachers->pluck('id')->map(fn ($id): int => (int) $id)->all();

            $courseDependencyCount = TeachingCourse::query()
                ->where('school_id', $authUser->school_id)
                ->where('user_id', $lockedUser->id)
                ->lockForUpdate()
                ->pluck('id')
                ->count();

            $this->assertNoTeacherDependencies(
                $this->teacherGroupDependencyCount($authUser->school_id, $teacherIds),
                $courseDependencyCount,
            );

            Teacher::query()->whereKey($teacherIds)->delete();

            $lockedUser->students_timetables_teacher_listed = false;
            $lockedUser->save();

            foreach (self::ROSTER_ROLES as $roleName) {
                if ($lockedUser->hasRole($roleName)) {
                    $lockedUser->removeRole($roleName);
                }
            }
        }, attempts: 3);

        return response()->json([
            'message' => 'Die Lehrkraft wurde aus der Lehrerliste entfernt. Das Benutzerkonto bleibt erhalten.',
        ]);
    }

    private function authorizedUser(): User
    {
        if (! $authUser = $this->userHasRole(self::MANAGER_ROLES)) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        return $authUser;
    }

    private function assertManageableRosterUser(User $authUser, User $teacherUser): void
    {
        $this->assertSameSchool($authUser, $teacherUser->school_id);

        $matchesImportedTeacher = Teacher::query()
            ->where('school_id', $authUser->school_id)
            ->whereRaw('LOWER(TRIM(email)) = ?', [mb_strtolower(trim($teacherUser->email))])
            ->exists();

        if (! $teacherUser->students_timetables_teacher_listed
            && ! $teacherUser->hasAnyRole(self::ROSTER_ROLES)
            && ! $matchesImportedTeacher) {
            abort(403, 'Dieses Konto gehört nicht zur Lehrerliste.');
        }
    }

    private function rosterUsersQuery(User $authUser): Builder
    {
        return User::query()
            ->where('school_id', $authUser->school_id)
            ->where(function (Builder $query): void {
                $query->where('students_timetables_teacher_listed', true)
                    ->orWhereHas('roles', function (Builder $query): void {
                        $query->whereIn('name', self::ROSTER_ROLES);
                    })
                    ->orWhereExists(function ($query): void {
                        $query->selectRaw('1')
                            ->from((new Teacher)->getTable())
                            ->whereColumn('teachers.school_id', 'users.school_id')
                            ->whereRaw('LOWER(TRIM(teachers.email)) = LOWER(TRIM(users.email))');
                    });
            });
    }

    private function assertSameSchool(User $authUser, int $schoolId): void
    {
        if ($authUser->school_id !== $schoolId) {
            abort(403, 'Diese Änderung kann nicht durchgeführt werden.');
        }
    }

    private function lockSchool(int $schoolId): void
    {
        School::query()
            ->whereKey($schoolId)
            ->lockForUpdate()
            ->firstOrFail();
    }

    /** @param array<int, int> $teacherIds */
    private function teacherGroupDependencyCount(int $schoolId, array $teacherIds): int
    {
        if ($teacherIds === []) {
            return 0;
        }

        $memberReferences = array_map(
            fn (int $teacherId): string => 'teacher_list.teacher:'.$teacherId,
            $teacherIds,
        );

        return UserGroupMember::query()
            ->where('member_provider', UserGroupMember::PROVIDER_TEACHER_LIST_TEACHER)
            ->whereIn('member_ref', $memberReferences)
            ->whereHas('group', fn (Builder $query): Builder => $query->where('school_id', $schoolId))
            ->lockForUpdate()
            ->pluck('id')
            ->count();
    }

    private function assertNoTeacherDependencies(int $groupCount, int $courseCount = 0): void
    {
        if ($groupCount === 0 && $courseCount === 0) {
            return;
        }

        $dependencies = [];

        if ($groupCount > 0) {
            $dependencies[] = $groupCount === 1
                ? '1 Gruppenmitgliedschaft'
                : "{$groupCount} Gruppenmitgliedschaften";
        }

        if ($courseCount > 0) {
            $dependencies[] = $courseCount === 1
                ? '1 Unterrichtskurs'
                : "{$courseCount} Unterrichtskurse";
        }

        abort(
            409,
            'Die Lehrkraft kann nicht entfernt werden. Abhängigkeiten: '.implode(' und ', $dependencies).'.',
        );
    }

    private function assertTeacherEmailAvailable(
        int $schoolId,
        string $email,
        ?Teacher $teacherToIgnore = null,
        ?User $userToIgnore = null,
        ?string $teacherEmailToIgnore = null,
    ): void {
        $normalizedEmail = mb_strtolower(trim($email));

        $teacherExists = Teacher::query()
            ->where('school_id', $schoolId)
            ->when($teacherToIgnore, fn (Builder $query, Teacher $teacher): Builder => $query->whereKeyNot($teacher->id))
            ->when($teacherEmailToIgnore, function (Builder $query, string $emailToIgnore): void {
                $query->whereRaw('LOWER(TRIM(email)) <> ?', [mb_strtolower(trim($emailToIgnore))]);
            })
            ->whereRaw('LOWER(TRIM(email)) = ?', [$normalizedEmail])
            ->exists();

        $userExists = User::query()
            ->where('school_id', $schoolId)
            ->when($userToIgnore, fn (Builder $query, User $user): Builder => $query->whereKeyNot($user->id))
            ->whereRaw('LOWER(TRIM(email)) = ?', [$normalizedEmail])
            ->exists();

        if ($teacherExists || $userExists) {
            throw ValidationException::withMessages([
                'email' => ['Diese E-Mail-Adresse wird bereits verwendet.'],
            ]);
        }
    }

    private function applySearch(Builder $query, string $searchString): void
    {
        $query->where(function ($query) use ($searchString): void {
            $query->where('short', 'like', "%{$searchString}%")
                ->orWhere('last_name', 'like', "%{$searchString}%")
                ->orWhere('first_name', 'like', "%{$searchString}%")
                ->orWhere('email', 'like', "%{$searchString}%");
        });
    }

    /** @return array<string, mixed> */
    private function teacherListItem(Teacher $teacher): array
    {
        return [
            'id' => "teacher-{$teacher->id}",
            'teacher_id' => $teacher->id,
            'user_id' => null,
            'short' => $teacher->short,
            'last_name' => $teacher->last_name,
            'first_name' => $teacher->first_name,
            'email' => $teacher->email,
            'is_active' => (bool) $teacher->is_active,
            'can_toggle_active' => false,
            'can_manage_timetable_role' => true,
            'can_remove' => true,
            'roles' => ['teacher'],
        ];
    }

    /** @return array<string, mixed> */
    private function userListItem(User $user, User $authUser): array
    {
        $roles = $user->roles->sortBy('name')->pluck('name')->values();

        return [
            'id' => "user-{$user->id}",
            'teacher_id' => null,
            'user_id' => $user->id,
            'short' => $user->short,
            'last_name' => $user->last_name,
            'first_name' => $user->first_name,
            'email' => $user->email,
            'is_active' => (bool) $user->is_active,
            'can_toggle_active' => ! $user->is($authUser)
                && (! $user->is_active || $roles->intersect(['admin', 'super_admin'])->isEmpty()),
            'can_manage_timetable_role' => ! $user->is($authUser),
            'can_remove' => ! $user->is($authUser),
            'roles' => $roles,
        ];
    }
}
