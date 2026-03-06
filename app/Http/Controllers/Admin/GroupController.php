<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Import116;
use App\Models\Teacher;
use App\Models\TeachingCourse;
use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class GroupController extends Controller
{
    public function index(Request $request)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'materials_admin', 'materials_moderator'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }
        $this->assertGroupsFeatureLicence($auth_user);

        $schoolId = $this->currentSchoolId($auth_user);
        $this->ensureDefaultSchoolGroups($schoolId, (int) $auth_user->id);
        $this->syncTeacherSchoolGroupMembers($schoolId, (int) $auth_user->id);
        $this->repairMissingImportUserLinksByEmail($schoolId);
        $this->syncClassSchoolGroupMembers($schoolId, (int) $auth_user->id);

        $groups = UserGroup::query()
            ->where('school_id', $schoolId)
            ->withCount('members')
            ->orderByRaw("CASE type WHEN 'school' THEN 1 WHEN 'materials' THEN 2 ELSE 3 END")
            ->orderByRaw('LOWER(name)')
            ->get();
        $schoolGroupSourceCounts = $this->schoolGroupSourceUserCounts($schoolId);

        return response()->json([
            'data' => $groups->map(fn (UserGroup $group) => $this->serializeGroup($group, $schoolGroupSourceCounts))->values(),
            'meta' => [
                'permissions' => [
                    UserGroup::TYPE_SCHOOL => $this->canManageType($auth_user, UserGroup::TYPE_SCHOOL),
                    UserGroup::TYPE_MATERIALS => $this->canManageType($auth_user, UserGroup::TYPE_MATERIALS),
                    UserGroup::TYPE_OWN => $this->canManageType($auth_user, UserGroup::TYPE_OWN),
                ],
            ],
        ]);
    }

    public function store(Request $request)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'materials_admin', 'materials_moderator'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }
        $this->assertGroupsFeatureLicence($auth_user);

        $validated = $request->validate([
            'type' => ['required', 'string', 'in:'.implode(',', UserGroup::TYPES)],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
        ]);

        $type = (string) $validated['type'];
        $this->assertTypePermission($auth_user, $type);

        $group = UserGroup::query()->create([
            'school_id' => $this->currentSchoolId($auth_user),
            'type' => $type,
            'name' => trim((string) $validated['name']),
            'description' => isset($validated['description']) ? trim((string) $validated['description']) : null,
            'created_by_user_id' => $auth_user->id,
        ]);

        $group->loadCount('members');

        return response()->json([
            'message' => 'Gruppe erstellt.',
            'data' => $this->serializeGroup($group),
        ], 201);
    }

    public function update(Request $request, UserGroup $group)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'materials_admin', 'materials_moderator'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }
        $this->assertGroupsFeatureLicence($auth_user);

        $this->assertGroupInCurrentSchool($group, $this->currentSchoolId($auth_user));
        $this->assertTypePermission($auth_user, (string) $group->type);
        $this->assertNotSystemDefaultSchoolGroup($group);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
        ]);

        $group->name = trim((string) $validated['name']);
        $group->description = isset($validated['description']) ? trim((string) $validated['description']) : null;
        $group->save();
        $group->loadCount('members');

        return response()->json([
            'message' => 'Gruppe gespeichert.',
            'data' => $this->serializeGroup($group),
        ]);
    }

    public function destroy(Request $request, UserGroup $group)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'materials_admin', 'materials_moderator'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }
        $this->assertGroupsFeatureLicence($auth_user);

        $this->assertGroupInCurrentSchool($group, $this->currentSchoolId($auth_user));
        $this->assertTypePermission($auth_user, (string) $group->type);
        $this->assertNotSystemDefaultSchoolGroup($group);

        if ($group->members()->exists()) {
            abort(409, 'Gruppe kann nur gelöscht werden, wenn sie keine Mitglieder enthält.');
        }

        $group->delete();

        return response()->json([
            'message' => 'Gruppe gelöscht.',
        ]);
    }

    public function members(Request $request, UserGroup $group)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'materials_admin', 'materials_moderator'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }
        $this->assertGroupsFeatureLicence($auth_user);

        $schoolId = $this->currentSchoolId($auth_user);
        $this->assertGroupInCurrentSchool($group, $schoolId);
        $this->assertTypePermission($auth_user, (string) $group->type);

        $members = $group->members()
            ->where('users.school_id', $schoolId)
            ->orderBy('users.last_name')
            ->orderBy('users.first_name')
            ->orderBy('users.email')
            ->get(['users.id', 'users.last_name', 'users.first_name', 'users.email', 'users.schoolclass']);

        return response()->json([
            'data' => $members->map(function (User $user) {
                $fullName = trim((string) (($user->last_name ?? '').' '.($user->first_name ?? '')));

                return [
                    'id' => (int) $user->id,
                    'name' => $fullName !== '' ? $fullName : ($user->email ?? 'Benutzer'),
                    'last_name' => $user->last_name,
                    'first_name' => $user->first_name,
                    'email' => $user->email,
                    'schoolclass' => $user->schoolclass,
                ];
            })->values(),
        ]);
    }

    public function removeMember(Request $request, UserGroup $group, User $user)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'materials_admin', 'materials_moderator'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }
        $this->assertGroupsFeatureLicence($auth_user);

        $schoolId = $this->currentSchoolId($auth_user);
        $this->assertGroupInCurrentSchool($group, $schoolId);
        $this->assertTypePermission($auth_user, (string) $group->type);
        $this->assertMembersManageable($group);

        if ((int) $user->school_id !== $schoolId) {
            abort(404, 'Benutzer nicht gefunden.');
        }

        $group->members()->detach($user->id);
        $group->loadCount('members');

        return response()->json([
            'message' => 'Benutzer wurde aus der Gruppe entfernt.',
            'group' => $this->serializeGroup($group),
        ]);
    }

    public function removeMembers(Request $request, UserGroup $group)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'materials_admin', 'materials_moderator'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }
        $this->assertGroupsFeatureLicence($auth_user);

        $schoolId = $this->currentSchoolId($auth_user);
        $this->assertGroupInCurrentSchool($group, $schoolId);
        $this->assertTypePermission($auth_user, (string) $group->type);
        $this->assertMembersManageable($group);

        $validated = $request->validate([
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => ['required', 'integer', 'distinct', 'exists:users,id'],
        ]);

        $userIds = collect($validated['user_ids'])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $validUserIds = User::query()
            ->where('school_id', $schoolId)
            ->whereIn('id', $userIds)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values();

        if ($validUserIds->isEmpty()) {
            abort(422, 'Keine passenden Benutzer in der aktuellen Schule gefunden.');
        }

        $group->members()->detach($validUserIds->all());
        $group->loadCount('members');

        return response()->json([
            'message' => 'Benutzer wurden aus der Gruppe entfernt.',
            'meta' => [
                'removed_count' => $validUserIds->count(),
                'members_count' => (int) $group->members_count,
            ],
            'group' => $this->serializeGroup($group),
        ]);
    }

    public function assignableUsers(Request $request, UserGroup $group)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'materials_admin', 'materials_moderator'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }
        $this->assertGroupsFeatureLicence($auth_user);

        $schoolId = $this->currentSchoolId($auth_user);
        $this->assertGroupInCurrentSchool($group, $schoolId);
        $this->assertTypePermission($auth_user, (string) $group->type);

        $validated = $request->validate([
            'search_string' => ['nullable', 'string', 'max:255'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:5000'],
            'role_name' => ['nullable', 'string', 'in:teacher,student'],
        ]);

        $search = trim((string) ($validated['search_string'] ?? ''));
        $limit = (int) ($validated['limit'] ?? 25);
        $roleName = trim((string) ($validated['role_name'] ?? ''));

        if ($roleName === 'student') {
            return $this->assignableStudentsFromImport116($auth_user, $group, $search, $limit);
        }

        $query = User::query()
            ->where('school_id', $schoolId)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->orderBy('email');

        if (in_array($roleName, ['teacher', 'student'], true)) {
            $query->whereHas('roles', function ($q) use ($roleName) {
                $q->where('name', $roleName);
            });
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('last_name', 'like', "%{$search}%")
                    ->orWhere('first_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $users = $query->limit($limit)->get(['id', 'last_name', 'first_name', 'email', 'schoolclass']);

        $existingMemberIds = $group->members()->pluck('users.id')->map(fn ($id) => (int) $id)->all();

        return response()->json([
            'data' => $users->map(function (User $user) use ($existingMemberIds) {
                $fullName = trim((string) (($user->last_name ?? '').' '.($user->first_name ?? '')));

                return [
                    'id' => (int) $user->id,
                    'name' => $fullName !== '' ? $fullName : ($user->email ?? 'Benutzer'),
                    'last_name' => $user->last_name,
                    'first_name' => $user->first_name,
                    'email' => $user->email,
                    'schoolclass' => $user->schoolclass,
                    'already_member' => in_array((int) $user->id, $existingMemberIds, true),
                ];
            })->values(),
        ]);
    }

    private function assignableStudentsFromImport116($auth_user, UserGroup $group, string $search, int $limit)
    {
        $schoolId = $this->currentSchoolId($auth_user);
        $schoolyearId = $auth_user->schoolyear_id ? (int) $auth_user->schoolyear_id : null;

        $query = Import116::query()
            ->where('school_id', $schoolId)
            ->orderByRaw('LOWER(class)')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->orderBy('email');

        if ($schoolyearId) {
            $query->where('schoolyear_id', $schoolyearId);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('last_name', 'like', "%{$search}%")
                    ->orWhere('first_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('class', 'like', "%{$search}%");
            });
        }

        $importRows = $query
            ->limit($limit)
            ->get(['id', 'schoolyear_id', 'class', 'last_name', 'first_name', 'email']);

        if ($importRows->isEmpty()) {
            return response()->json(['data' => []]);
        }

        $userQuery = User::query()
            ->where('school_id', $schoolId)
            ->whereIn('import116_id', $importRows->pluck('id')->map(fn ($id) => (int) $id)->all())
            ->get(['id', 'import116_id']);

        $usersByImportId = $userQuery
            ->filter(fn (User $user) => ! empty($user->import116_id))
            ->keyBy(fn (User $user) => (int) $user->import116_id);

        $existingMemberIds = $group->members()->pluck('users.id')->map(fn ($id) => (int) $id)->all();

        $data = $importRows
            ->map(function (Import116 $import) use ($usersByImportId, $existingMemberIds) {
                $mappedUser = $usersByImportId->get((int) $import->id);
                $fullName = trim((string) (($import->last_name ?? '').' '.($import->first_name ?? '')));

                return [
                    'id' => $mappedUser ? (int) $mappedUser->id : null,
                    'user_id' => $mappedUser ? (int) $mappedUser->id : null,
                    'import116_id' => (int) $import->id,
                    'name' => $fullName !== '' ? $fullName : ($import->email ?? 'Schüler:in'),
                    'last_name' => $import->last_name,
                    'first_name' => $import->first_name,
                    'email' => $import->email,
                    'schoolclass' => $import->class,
                    'has_user_account' => (bool) $mappedUser,
                    'already_member' => $mappedUser
                        ? in_array((int) $mappedUser->id, $existingMemberIds, true)
                        : false,
                ];
            })
            ->values();

        return response()->json(['data' => $data]);
    }

    public function assignUsers(Request $request, UserGroup $group)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'materials_admin', 'materials_moderator'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }
        $this->assertGroupsFeatureLicence($auth_user);

        $schoolId = $this->currentSchoolId($auth_user);
        $this->assertGroupInCurrentSchool($group, $schoolId);
        $this->assertTypePermission($auth_user, (string) $group->type);
        $this->assertMembersManageable($group);

        $validated = $request->validate([
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => ['required', 'integer', 'distinct', 'exists:users,id'],
        ]);

        $userIds = collect($validated['user_ids'])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $validUserIds = User::query()
            ->where('school_id', $schoolId)
            ->whereIn('id', $userIds)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values();

        if ($validUserIds->isEmpty()) {
            abort(422, 'Keine passenden Benutzer in der aktuellen Schule gefunden.');
        }

        $existingIds = $group->members()->whereIn('users.id', $validUserIds)->pluck('users.id')->map(fn ($id) => (int) $id);
        $attachPayload = [];
        foreach ($validUserIds as $userId) {
            $attachPayload[$userId] = ['added_by_user_id' => $auth_user->id];
        }
        $group->members()->syncWithoutDetaching($attachPayload);
        $group->loadCount('members');

        return response()->json([
            'message' => 'Benutzer wurden zugeordnet.',
            'meta' => [
                'assigned_count' => $validUserIds->count(),
                'new_count' => $validUserIds->diff($existingIds)->count(),
                'members_count' => (int) $group->members_count,
            ],
            'group' => $this->serializeGroup($group),
        ]);
    }

    public function assignableGroups(Request $request, UserGroup $group)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'materials_admin', 'materials_moderator'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }
        $this->assertGroupsFeatureLicence($auth_user);

        $schoolId = $this->currentSchoolId($auth_user);
        $this->assertGroupInCurrentSchool($group, $schoolId);
        $this->assertTypePermission($auth_user, (string) $group->type);

        $groups = UserGroup::query()
            ->where('school_id', $schoolId)
            ->where('id', '!=', $group->id)
            ->whereHas('members')
            ->withCount('members')
            ->orderByRaw("CASE type WHEN 'school' THEN 1 WHEN 'materials' THEN 2 ELSE 3 END")
            ->orderByRaw('LOWER(name)')
            ->get();

        return response()->json([
            'data' => $groups->map(fn (UserGroup $row) => [
                'id' => (int) $row->id,
                'type' => (string) $row->type,
                'name' => (string) $row->name,
                'members_count' => (int) ($row->members_count ?? 0),
            ])->values(),
        ]);
    }

    public function assignFromGroup(Request $request, UserGroup $group)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'materials_admin', 'materials_moderator'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }
        $this->assertGroupsFeatureLicence($auth_user);

        $schoolId = $this->currentSchoolId($auth_user);
        $this->assertGroupInCurrentSchool($group, $schoolId);
        $this->assertTypePermission($auth_user, (string) $group->type);
        $this->assertMembersManageable($group);

        $validated = $request->validate([
            'source_group_id' => ['required', 'integer', 'exists:user_groups,id'],
        ]);

        $sourceGroup = UserGroup::query()->findOrFail((int) $validated['source_group_id']);
        $this->assertGroupInCurrentSchool($sourceGroup, $schoolId);
        if ((int) $sourceGroup->id === (int) $group->id) {
            abort(422, 'Quellgruppe und Zielgruppe dürfen nicht identisch sein.');
        }

        $sourceMemberIds = $sourceGroup->members()->pluck('users.id')->map(fn ($id) => (int) $id)->unique()->values();
        if ($sourceMemberIds->isEmpty()) {
            return response()->json([
                'message' => 'Die Quellgruppe enthält keine Mitglieder.',
                'meta' => ['assigned_count' => 0, 'new_count' => 0],
            ]);
        }

        return $this->assignUsers(new Request(['user_ids' => $sourceMemberIds->all()]), $group);
    }

    public function myTeachingCourses(Request $request, UserGroup $group)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'materials_admin', 'materials_moderator'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }
        $this->assertGroupsFeatureLicence($auth_user);

        $schoolId = $this->currentSchoolId($auth_user);
        $this->assertGroupInCurrentSchool($group, $schoolId);
        $this->assertTypePermission($auth_user, (string) $group->type);

        if ((string) $group->type !== UserGroup::TYPE_OWN) {
            abort(422, 'Diese Funktion ist nur für Eigene Gruppen verfügbar.');
        }

        $schoolyearId = $auth_user->schoolyear_id ? (int) $auth_user->schoolyear_id : null;

        $coursesQuery = TeachingCourse::query()
            ->where('school_id', $schoolId)
            ->where('user_id', (int) $auth_user->id)
            ->with([
                'teachingCourseStudents' => function ($q) {
                    $q->select(['id', 'teaching_course_id', 'user_id', 'import116_id']);
                },
                'teachingCourseStudents.user:id,last_name,first_name,email,schoolclass',
                'teachingCourseStudents.import116:id,class,last_name,first_name,email',
            ])
            ->orderByRaw('LOWER(title)');

        if ($schoolyearId) {
            $coursesQuery->where('schoolyear_id', $schoolyearId);
        }

        $courses = $coursesQuery->get(['id', 'school_id', 'schoolyear_id', 'title', 'classes']);
        $existingMemberIds = $group->members()->pluck('users.id')->map(fn ($id) => (int) $id)->all();

        $data = $courses->map(function (TeachingCourse $course) use ($existingMemberIds) {
            $students = $course->teachingCourseStudents
                ->map(function ($courseStudent) use ($existingMemberIds) {
                    $user = $courseStudent->user;
                    $import = $courseStudent->import116;

                    $name = null;
                    $email = null;
                    $schoolclass = null;

                    if ($user) {
                        $name = trim((string) (($user->last_name ?? '').' '.($user->first_name ?? '')));
                        $email = $user->email;
                        $schoolclass = $user->schoolclass;
                    } elseif ($import) {
                        $name = trim((string) (($import->last_name ?? '').' '.($import->first_name ?? '')));
                        $email = $import->email;
                        $schoolclass = $import->class;
                    }

                    $userId = $user ? (int) $user->id : null;

                    return [
                        'id' => $userId,
                        'user_id' => $userId,
                        'import116_id' => $courseStudent->import116_id ? (int) $courseStudent->import116_id : null,
                        'name' => $name !== '' ? $name : ($email ?: 'Schüler:in'),
                        'email' => $email,
                        'schoolclass' => $schoolclass,
                        'has_user_account' => (bool) $userId,
                        'already_member' => $userId ? in_array($userId, $existingMemberIds, true) : false,
                    ];
                })
                ->values();

            $classes = is_array($course->classes) ? array_values(array_filter($course->classes, fn ($v) => trim((string) $v) !== '')) : [];

            return [
                'id' => (int) $course->id,
                'title' => (string) ($course->title ?: 'Fach'),
                'classes' => $classes,
                'students' => $students,
            ];
        })->values();

        return response()->json(['data' => $data]);
    }

    private function currentSchoolId($auth_user): int
    {
        $schoolId = (int) ($auth_user->selectedSchool?->id ?? 0);
        if ($schoolId <= 0) {
            abort(422, 'Keine aktive Schule ausgewählt.');
        }

        return $schoolId;
    }

    private function assertGroupsFeatureLicence($auth_user): void
    {
        $school = $auth_user->selectedSchool;
        if (! $school) {
            abort(422, 'Keine aktive Schule ausgewählt.');
        }

        $licenceService = app(\App\Services\LicenceService::class);
        $hasValidLicence =
            $licenceService->licenceStatus($school, 'Lehrertool') === 'active'
            || $licenceService->licenceStatus($school, 'Materialientool') === 'active';

        if (! $hasValidLicence) {
            abort(403, 'Gruppen sind nur mit gültiger Lehrertool- oder Materialientool-Lizenz verfügbar.');
        }
    }

    private function canManageType($user, string $type): bool
    {
        if ($user->hasAnyRole(['super_admin', 'admin'])) {
            return true;
        }

        if ($type === UserGroup::TYPE_MATERIALS && $user->hasRole('materials_admin')) {
            return true;
        }

        if ($type === UserGroup::TYPE_OWN && $user->hasAnyRole(['materials_admin', 'materials_moderator'])) {
            return true;
        }

        return false;
    }

    private function assertTypePermission($user, string $type): void
    {
        if (! $this->canManageType($user, $type)) {
            abort(403, 'Sie dürfen diesen Gruppentyp nicht verwalten.');
        }
    }

    private function assertGroupInCurrentSchool(UserGroup $group, int $schoolId): void
    {
        if ((int) $group->school_id !== $schoolId) {
            abort(404, 'Gruppe nicht gefunden.');
        }
    }

    /**
     * @param  array<string, int>|null  $schoolGroupSourceCounts
     */
    private function serializeGroup(UserGroup $group, ?array $schoolGroupSourceCounts = null): array
    {
        $membersCount = (int) ($group->members_count ?? 0);
        $isSystemDefault = $this->isSystemDefaultSchoolGroup($group);
        $displayName = (string) $group->name;
        $sourceUsersCount = null;
        if ((string) $group->type === UserGroup::TYPE_SCHOOL) {
            $normalizedName = $this->normalizeGroupName((string) $group->name);
            if ($this->isTeacherGroupName($normalizedName)) {
                $normalizedName = $this->normalizeGroupName($this->defaultTeacherGroupName());
                $displayName = $this->defaultTeacherGroupName();
            }
            $sourceUsersCount = is_array($schoolGroupSourceCounts)
                ? (int) ($schoolGroupSourceCounts[$normalizedName] ?? 0)
                : null;
        }

        return [
            'id' => (int) $group->id,
            'type' => (string) $group->type,
            'name' => $displayName,
            'description' => $group->description,
            'members_count' => $membersCount,
            'source_users_count' => $sourceUsersCount,
            'can_delete' => ! $isSystemDefault && ($membersCount === 0),
            'can_edit' => ! $isSystemDefault,
            'can_manage_members' => ! $isSystemDefault,
            'is_system_default' => $isSystemDefault,
            'created_by_user_id' => $group->created_by_user_id ? (int) $group->created_by_user_id : null,
            'updated_at' => optional($group->updated_at)?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function schoolGroupSourceUserCounts(int $schoolId): array
    {
        $counts = [];

        $classGroups = $this->buildImportClassGroupMappings($schoolId);
        foreach ($classGroups['by_class'] as $normalizedClass => $classData) {
            $counts[$normalizedClass] = count($classData['import_ids']);
        }
        foreach ($classGroups['by_family'] as $normalizedFamily => $familyData) {
            $counts[$normalizedFamily] = count($familyData['import_ids']);
        }

        $counts[$this->normalizeGroupName($this->defaultTeacherGroupName())] = $this->teacherSourceUsersCount($schoolId);

        return $counts;
    }

    private function typeLabel(string $type): string
    {
        return match ($type) {
            UserGroup::TYPE_SCHOOL => 'Schulgruppen',
            UserGroup::TYPE_MATERIALS => 'Materialiengruppen',
            UserGroup::TYPE_OWN => 'Eigene Gruppen',
            default => $type,
        };
    }

    private function normalizeGroupName(string $name): string
    {
        return mb_strtolower(trim($name));
    }

    private function isSystemDefaultSchoolGroup(UserGroup $group): bool
    {
        if ((string) $group->type !== UserGroup::TYPE_SCHOOL) {
            return false;
        }

        if ($this->isTeacherGroupName($this->normalizeGroupName((string) $group->name))) {
            return true;
        }

        $requiredNames = $this->requiredSchoolGroupNames((int) $group->school_id);

        return array_key_exists($this->normalizeGroupName((string) $group->name), $requiredNames);
    }

    private function assertNotSystemDefaultSchoolGroup(UserGroup $group): void
    {
        if ($this->isSystemDefaultSchoolGroup($group)) {
            abort(409, 'Standard-Schulgruppen können nicht geändert oder gelöscht werden.');
        }
    }

    private function assertMembersManageable(UserGroup $group): void
    {
        if ($this->isSystemDefaultSchoolGroup($group)) {
            abort(409, 'Standard-Schulgruppen werden automatisch verwaltet und können nicht manuell bearbeitet werden.');
        }
    }

    private function ensureDefaultSchoolGroups(int $schoolId, int $creatorUserId): void
    {
        $this->normalizeLegacyTeacherGroupName($schoolId);

        $existingNames = UserGroup::query()
            ->where('school_id', $schoolId)
            ->where('type', UserGroup::TYPE_SCHOOL)
            ->get(['name'])
            ->map(fn (UserGroup $group) => $this->normalizeGroupName((string) $group->name))
            ->values()
            ->all();

        $existingMap = array_fill_keys($existingNames, true);

        foreach ($this->requiredSchoolGroupNames($schoolId) as $normalized => $name) {
            if (isset($existingMap[$normalized])) {
                continue;
            }

            UserGroup::query()->create([
                'school_id' => $schoolId,
                'type' => UserGroup::TYPE_SCHOOL,
                'name' => $name,
                'description' => null,
                'created_by_user_id' => $creatorUserId > 0 ? $creatorUserId : null,
            ]);
        }
    }

    private function syncTeacherSchoolGroupMembers(int $schoolId, int $actorUserId): void
    {
        $teacherGroup = UserGroup::query()
            ->where('school_id', $schoolId)
            ->where('type', UserGroup::TYPE_SCHOOL)
            ->whereRaw('LOWER(TRIM(name)) IN (?, ?)', [
                $this->normalizeGroupName($this->defaultTeacherGroupName()),
                $this->normalizeGroupName('Teacher'),
            ])
            ->first();

        if (! $teacherGroup) {
            $teacherGroup = UserGroup::query()->create([
                'school_id' => $schoolId,
                'type' => UserGroup::TYPE_SCHOOL,
                'name' => $this->defaultTeacherGroupName(),
                'description' => null,
                'created_by_user_id' => $actorUserId > 0 ? $actorUserId : null,
            ]);
        }

        if ($this->normalizeGroupName((string) $teacherGroup->name) !== $this->normalizeGroupName($this->defaultTeacherGroupName())) {
            $teacherGroup->name = $this->defaultTeacherGroupName();
            $teacherGroup->save();
        }

        $matchingUserIds = $this->resolveTeacherSchoolGroupUserIds($schoolId);

        $syncPayload = [];
        foreach ($matchingUserIds as $userId) {
            $syncPayload[$userId] = [
                'added_by_user_id' => $actorUserId > 0 ? $actorUserId : null,
            ];
        }

        $teacherGroup->members()->sync($syncPayload);
    }

    private function syncClassSchoolGroupMembers(int $schoolId, int $actorUserId): void
    {
        if (! Schema::hasTable('import116')) {
            return;
        }

        $classGroupMappings = $this->buildImportClassGroupMappings($schoolId);
        if (empty($classGroupMappings['by_class'])) {
            return;
        }

        $importIdsByClass = collect($classGroupMappings['by_class'])
            ->map(fn (array $row) => $row['import_ids'])
            ->flatten()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $usersByImportId = User::query()
            ->where('school_id', $schoolId)
            ->whereNotNull('import116_id')
            ->whereIn('import116_id', $importIdsByClass)
            ->get(['id', 'import116_id'])
            ->groupBy(fn (User $user) => (int) $user->import116_id);

        $classGroups = UserGroup::query()
            ->where('school_id', $schoolId)
            ->where('type', UserGroup::TYPE_SCHOOL)
            ->get();

        foreach ($classGroups as $group) {
            $normalizedGroupName = $this->normalizeGroupName((string) $group->name);
            if ($this->isTeacherGroupName($normalizedGroupName)) {
                continue;
            }

            $importIds = $classGroupMappings['by_family'][$normalizedGroupName]['import_ids']
                ?? $classGroupMappings['by_class'][$normalizedGroupName]['import_ids']
                ?? [];
            if (empty($importIds)) {
                continue;
            }

            $memberIds = collect($importIds)
                ->flatMap(fn (int $importId) => $usersByImportId->get($importId, collect())->pluck('id'))
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values();

            $syncPayload = [];
            foreach ($memberIds as $userId) {
                $syncPayload[$userId] = [
                    'added_by_user_id' => $actorUserId > 0 ? $actorUserId : null,
                ];
            }

            $group->members()->sync($syncPayload);
        }
    }

    private function repairMissingImportUserLinksByEmail(int $schoolId): void
    {
        if (! Schema::hasTable('import116') || ! Schema::hasTable('users')) {
            return;
        }

        $importRows = Import116::query()
            ->where('school_id', $schoolId)
            ->whereNotNull('email')
            ->whereRaw('TRIM(email) <> ?', [''])
            ->orderByDesc('id')
            ->get(['id', 'schoolyear_id', 'class', 'email', 'user_id']);

        if ($importRows->isEmpty()) {
            return;
        }

        $unlinkedUsers = User::query()
            ->where('school_id', $schoolId)
            ->whereNull('import116_id')
            ->whereNotNull('email')
            ->whereRaw('TRIM(email) <> ?', [''])
            ->orderByDesc('id')
            ->get(['id', 'schoolyear_id', 'schoolclass', 'email']);

        if ($unlinkedUsers->isEmpty()) {
            return;
        }

        $usersByEmail = $unlinkedUsers
            ->groupBy(fn (User $user) => mb_strtolower(trim((string) ($user->email ?? ''))))
            ->map(fn ($rows) => $rows->values());

        foreach ($importRows as $importRow) {
            $normalizedEmail = mb_strtolower(trim((string) ($importRow->email ?? '')));
            if ($normalizedEmail === '') {
                continue;
            }

            $candidateRows = $usersByEmail->get($normalizedEmail, collect());
            if ($candidateRows->isEmpty()) {
                continue;
            }

            $preferredCandidate = null;
            $importSchoolyearId = (int) ($importRow->schoolyear_id ?? 0);
            if ($importSchoolyearId > 0) {
                $preferredCandidate = $candidateRows
                    ->first(fn (User $row) => (int) ($row->schoolyear_id ?? 0) === $importSchoolyearId);
            }
            if (! $preferredCandidate) {
                $preferredCandidate = $candidateRows->first();
            }
            if (! $preferredCandidate) {
                continue;
            }

            $updatePayload = [
                'import116_id' => (int) $importRow->id,
            ];
            if (trim((string) ($preferredCandidate->schoolclass ?? '')) === '' && trim((string) ($importRow->class ?? '')) !== '') {
                $updatePayload['schoolclass'] = trim((string) $importRow->class);
            }
            if ((int) ($preferredCandidate->schoolyear_id ?? 0) <= 0 && $importSchoolyearId > 0) {
                $updatePayload['schoolyear_id'] = $importSchoolyearId;
            }

            User::query()
                ->where('id', (int) $preferredCandidate->id)
                ->whereNull('import116_id')
                ->update($updatePayload);

            if ((int) ($importRow->user_id ?? 0) <= 0) {
                Import116::query()
                    ->where('id', (int) $importRow->id)
                    ->whereNull('user_id')
                    ->update(['user_id' => (int) $preferredCandidate->id]);
            }

            $remainingCandidates = $candidateRows
                ->reject(fn (User $row) => (int) $row->id === (int) $preferredCandidate->id)
                ->values();

            if ($remainingCandidates->isEmpty()) {
                $usersByEmail->forget($normalizedEmail);
            } else {
                $usersByEmail->put($normalizedEmail, $remainingCandidates);
            }
        }
    }

    /**
     * @return array<string, string>
     */
    private function requiredSchoolGroupNames(int $schoolId): array
    {
        $names = [$this->defaultTeacherGroupName()];

        $classGroups = $this->buildImportClassGroupMappings($schoolId);
        foreach ($classGroups['by_class'] as $classData) {
            $names[] = $classData['name'];
        }
        foreach ($classGroups['by_family'] as $familyData) {
            $names[] = $familyData['name'];
        }

        $normalizedMap = [];
        foreach ($names as $name) {
            $normalized = $this->normalizeGroupName($name);
            if ($normalized === '' || isset($normalizedMap[$normalized])) {
                continue;
            }
            $normalizedMap[$normalized] = $name;
        }

        return collect($normalizedMap)
            ->sortBy(fn (string $name) => $this->normalizeGroupName($name))
            ->all();
    }

    private function defaultTeacherGroupName(): string
    {
        return 'Lehrer';
    }

    private function isTeacherGroupName(string $normalizedGroupName): bool
    {
        return in_array(
            $normalizedGroupName,
            [
                $this->normalizeGroupName($this->defaultTeacherGroupName()),
                $this->normalizeGroupName('Teacher'),
            ],
            true
        );
    }

    private function normalizeLegacyTeacherGroupName(int $schoolId): void
    {
        $normalizedTarget = $this->normalizeGroupName($this->defaultTeacherGroupName());
        $hasTargetGroup = UserGroup::query()
            ->where('school_id', $schoolId)
            ->where('type', UserGroup::TYPE_SCHOOL)
            ->whereRaw('LOWER(TRIM(name)) = ?', [$normalizedTarget])
            ->exists();

        if ($hasTargetGroup) {
            return;
        }

        $legacyGroup = UserGroup::query()
            ->where('school_id', $schoolId)
            ->where('type', UserGroup::TYPE_SCHOOL)
            ->whereRaw('LOWER(TRIM(name)) = ?', [$this->normalizeGroupName('Teacher')])
            ->orderBy('id')
            ->first();

        if (! $legacyGroup) {
            return;
        }

        $legacyGroup->name = $this->defaultTeacherGroupName();
        $legacyGroup->save();
    }

    /**
     * @return Collection<int, int>
     */
    private function teacherRoleUserIds(int $schoolId): Collection
    {
        return User::query()
            ->where('school_id', $schoolId)
            ->whereHas('roles', fn ($q) => $q->where('name', 'teacher'))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
    }

    /**
     * @return Collection<int, int>
     */
    private function resolveTeacherSchoolGroupUserIds(int $schoolId): Collection
    {
        $idsByTeacherList = collect();
        if (Schema::hasTable('teachers')) {
            $teacherEmails = Teacher::query()
                ->where('school_id', $schoolId)
                ->whereNotNull('email')
                ->select('email')
                ->distinct()
                ->pluck('email')
                ->map(fn ($email) => mb_strtolower(trim((string) $email)))
                ->filter(fn (string $email) => $email !== '')
                ->values();

            if ($teacherEmails->isNotEmpty()) {
                $idsByTeacherList = User::query()
                    ->where('school_id', $schoolId)
                    ->whereNotNull('email')
                    ->whereRaw('TRIM(email) <> ?', [''])
                    ->whereIn(DB::raw('LOWER(TRIM(email))'), $teacherEmails->all())
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->unique()
                    ->values();
            }
        }

        return $idsByTeacherList
            ->merge($this->teacherRoleUserIds($schoolId))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
    }

    private function teacherSourceUsersCount(int $schoolId): int
    {
        if (! Schema::hasTable('teachers')) {
            return 0;
        }

        return (int) Teacher::query()
            ->where('school_id', $schoolId)
            ->whereNotNull('email')
            ->whereRaw('TRIM(email) <> ?', [''])
            ->selectRaw('COUNT(DISTINCT LOWER(TRIM(email))) as aggregate')
            ->value('aggregate');
    }

    /**
     * @return array{
     *     by_class: array<string, array{name: string, import_ids: array<int, int>}>,
     *     by_family: array<string, array{name: string, import_ids: array<int, int>}>
     * }
     */
    private function buildImportClassGroupMappings(int $schoolId): array
    {
        $result = [
            'by_class' => [],
            'by_family' => [],
        ];

        if (! Schema::hasTable('import116')) {
            return $result;
        }

        $importRows = Import116::query()
            ->where('school_id', $schoolId)
            ->whereNotNull('class')
            ->orderByRaw('LOWER(class)')
            ->orderBy('id')
            ->get(['id', 'class']);

        if ($importRows->isEmpty()) {
            return $result;
        }

        $familiesWithVariants = [];

        foreach ($importRows as $row) {
            $className = trim((string) ($row->class ?? ''));
            if ($className === '') {
                continue;
            }

            $normalizedClassName = $this->normalizeGroupName($className);
            if (! isset($result['by_class'][$normalizedClassName])) {
                $result['by_class'][$normalizedClassName] = [
                    'name' => $className,
                    'import_ids' => [],
                ];
            }
            $result['by_class'][$normalizedClassName]['import_ids'][] = (int) $row->id;

            $familyClassName = $this->detectCombinedClassFamilyName($className);
            if (! is_string($familyClassName) || $familyClassName === '') {
                continue;
            }

            $normalizedFamilyName = $this->normalizeGroupName($familyClassName);
            if (! isset($familiesWithVariants[$normalizedFamilyName])) {
                $familiesWithVariants[$normalizedFamilyName] = [
                    'name' => $familyClassName,
                    'import_ids' => [],
                    'class_names' => [],
                ];
            }
            $familiesWithVariants[$normalizedFamilyName]['import_ids'][] = (int) $row->id;
            $familiesWithVariants[$normalizedFamilyName]['class_names'][$normalizedClassName] = true;
        }

        foreach ($result['by_class'] as $normalizedClassName => $classData) {
            $result['by_class'][$normalizedClassName]['import_ids'] = collect($classData['import_ids'])
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all();
        }

        foreach ($familiesWithVariants as $normalizedFamilyName => $familyData) {
            if (count($familyData['class_names']) < 2) {
                continue;
            }

            $result['by_family'][$normalizedFamilyName] = [
                'name' => (string) $familyData['name'],
                'import_ids' => collect($familyData['import_ids'])
                    ->map(fn ($id) => (int) $id)
                    ->unique()
                    ->values()
                    ->all(),
            ];
        }

        return $result;
    }

    private function detectCombinedClassFamilyName(string $className): ?string
    {
        $value = trim((string) preg_replace('/\s+/u', ' ', $className));
        if ($value === '') {
            return null;
        }

        $base = null;
        if (preg_match('/^(.+?)[\\-_\\/|:;]+.+$/u', $value, $matches) === 1) {
            $base = trim((string) ($matches[1] ?? ''));
        } else {
            $parts = preg_split('/\s+/u', $value) ?: [];
            if (count($parts) >= 2) {
                $base = trim((string) ($parts[0] ?? ''));
            } elseif (preg_match('/^(\\d{1,2}[[:alpha:]]{1,2})([[:alpha:]]{1,3})$/u', preg_replace('/\s+/u', '', $value) ?: '', $matches) === 1) {
                $base = trim((string) ($matches[1] ?? ''));
            }
        }

        if (! is_string($base) || $base === '') {
            return null;
        }

        $base = preg_replace('/\s+/u', '', $base) ?: '';
        if ($base === '') {
            return null;
        }

        if (preg_match('/^\\d{1,2}[[:alpha:]]{1,3}$/u', $base) !== 1) {
            return null;
        }

        return mb_strtoupper($base);
    }
}
