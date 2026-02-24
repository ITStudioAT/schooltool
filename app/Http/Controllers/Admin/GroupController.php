<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Import116;
use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Http\Request;

class GroupController extends Controller
{
    public function index(Request $request)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'materials_admin', 'materials_moderator'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }
        $this->assertGroupsFeatureLicence($auth_user);

        $schoolId = $this->currentSchoolId($auth_user);

        $groups = UserGroup::query()
            ->where('school_id', $schoolId)
            ->withCount('members')
            ->orderByRaw("CASE type WHEN 'school' THEN 1 WHEN 'materials' THEN 2 ELSE 3 END")
            ->orderByRaw('LOWER(name)')
            ->get();

        return response()->json([
            'data' => $groups->map(fn(UserGroup $group) => $this->serializeGroup($group))->values(),
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
            'type' => ['required', 'string', 'in:' . implode(',', UserGroup::TYPES)],
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

    private function serializeGroup(UserGroup $group): array
    {
        $membersCount = (int) ($group->members_count ?? 0);

        return [
            'id' => (int) $group->id,
            'type' => (string) $group->type,
            'name' => (string) $group->name,
            'description' => $group->description,
            'members_count' => $membersCount,
            'can_delete' => ($membersCount === 0),
            'created_by_user_id' => $group->created_by_user_id ? (int) $group->created_by_user_id : null,
            'updated_at' => optional($group->updated_at)?->toIso8601String(),
        ];
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
}
