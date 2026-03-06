<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Import116;
use App\Models\SchoolTool;
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
        $this->syncOwnTeachingCourseGroups($auth_user, $schoolId, (int) $auth_user->id);

        $groups = UserGroup::query()
            ->where('school_id', $schoolId)
            ->withCount('members')
            ->orderByRaw("CASE type WHEN 'school' THEN 1 WHEN 'materials' THEN 2 ELSE 3 END")
            ->orderByRaw('LOWER(name)')
            ->get();
        $schoolGroupSourceCounts = $this->schoolGroupSourceUserCounts($schoolId);
        $parentGroupContacts = $this->parentContactsForSchoolGroups($schoolId);
        $allSchoolMembers = $this->allSchoolMembersCollections($schoolId);
        $ownCourseSourceCounts = $this->ownCourseSourceUserCounts($groups);
        $ownCourseParentContacts = $this->parentContactsForAutomaticOwnCourseGroups($groups, $schoolId);

        return response()->json([
            'data' => $groups->map(fn (UserGroup $group) => $this->serializeGroup($group, $schoolGroupSourceCounts, $ownCourseSourceCounts, $parentGroupContacts, $ownCourseParentContacts, $allSchoolMembers))->values(),
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
        $this->assertNotSystemManagedGroup($group);

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
        $this->assertNotSystemManagedGroup($group);

        if ((string) $group->type !== UserGroup::TYPE_OWN && $group->members()->exists()) {
            abort(409, 'Gruppe kann nur gelöscht werden, wenn sie keine Mitglieder enthält.');
        }

        if ((string) $group->type === UserGroup::TYPE_OWN && $group->members()->exists()) {
            $group->members()->detach();
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

        if ($this->isAllSchoolMembersGroup($group)) {
            $members = $this->allSchoolMembersCollections($schoolId)['registered'];

            return response()->json([
                'data' => $members->values()->all(),
            ]);
        }

        if ($this->isParentSchoolGroup($group)) {
            $contacts = $this->parentContactsForGroup($schoolId, $group, true);

            return response()->json([
                'data' => $contacts->values()->all(),
            ]);
        }

        if ($this->isSystemManagedCourseParentGroup($group)) {
            $contacts = $this->parentContactsForAutomaticOwnCourseGroup($schoolId, $group, true);

            return response()->json([
                'data' => $contacts->values()->all(),
            ]);
        }

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

    public function sourceMembers(Request $request, UserGroup $group)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'materials_admin', 'materials_moderator'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }
        $this->assertGroupsFeatureLicence($auth_user);

        $schoolId = $this->currentSchoolId($auth_user);
        $this->assertGroupInCurrentSchool($group, $schoolId);
        $this->assertTypePermission($auth_user, (string) $group->type);

        if ($this->isAllSchoolMembersGroup($group)) {
            $members = $this->allSchoolMembersCollections($schoolId)['all'];

            return response()->json([
                'data' => $members->values()->all(),
                'meta' => ['total' => $members->count()],
            ]);
        }

        if ($this->isTeacherGroupName($this->normalizeGroupName((string) $group->name))) {
            $teachers = $this->teacherSourceMembers($schoolId, $group);

            return response()->json([
                'data' => $teachers->values()->all(),
                'meta' => ['total' => $teachers->count()],
            ]);
        }

        if ($this->isParentSchoolGroup($group)) {
            $contacts = $this->parentContactsForGroup($schoolId, $group, false);

            return response()->json([
                'data' => $contacts->values()->all(),
                'meta' => ['total' => $contacts->count()],
            ]);
        }

        if ($this->isSystemManagedCourseParentGroup($group)) {
            $contacts = $this->parentContactsForAutomaticOwnCourseGroup($schoolId, $group, false);

            return response()->json([
                'data' => $contacts->values()->all(),
                'meta' => ['total' => $contacts->count()],
            ]);
        }

        if ($this->isSystemManagedCourseGroup($group)) {
            $students = $this->sourceMembersForAutomaticCourseGroup($group, $schoolId);

            return response()->json([
                'data' => $students->values()->all(),
                'meta' => ['total' => $students->count()],
            ]);
        }

        if ((string) $group->type !== UserGroup::TYPE_SCHOOL) {
            return response()->json([
                'data' => [],
                'meta' => ['total' => 0],
            ]);
        }

        $importIds = $this->importIdsForSchoolGroup($schoolId, $group);
        if (empty($importIds)) {
            return response()->json([
                'data' => [],
                'meta' => ['total' => 0],
            ]);
        }

        $importRows = $this->import116QueryForActiveSchoolyear($schoolId)
            ->whereIn('id', $importIds)
            ->orderByRaw('LOWER(class)')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->orderBy('email')
            ->get(['id', 'class', 'last_name', 'first_name', 'email']);

        if ($importRows->isEmpty()) {
            return response()->json([
                'data' => [],
                'meta' => ['total' => 0],
            ]);
        }

        $usersByImportId = User::query()
            ->where('school_id', $schoolId)
            ->whereIn('import116_id', $importRows->pluck('id')->map(fn ($id) => (int) $id)->all())
            ->get(['id', 'import116_id', 'last_name', 'first_name', 'email', 'schoolclass'])
            ->keyBy(fn (User $user) => (int) $user->import116_id);

        $existingMemberIds = $group->members()->pluck('users.id')->map(fn ($id) => (int) $id)->all();

        $data = $importRows->map(function (Import116 $import) use ($usersByImportId, $existingMemberIds) {
            $mappedUser = $usersByImportId->get((int) $import->id);
            $fullName = trim((string) (($import->last_name ?? '').' '.($import->first_name ?? '')));
            $userId = $mappedUser ? (int) $mappedUser->id : null;

            return [
                'id' => (int) $import->id,
                'import116_id' => (int) $import->id,
                'user_id' => $userId,
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
        })->values();

        return response()->json([
            'data' => $data,
            'meta' => ['total' => $data->count()],
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
        $query = $this->import116QueryForActiveSchoolyear($schoolId)
            ->orderByRaw('LOWER(class)')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->orderBy('email');

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
     * @param  array<int, int>|null  $ownCourseSourceCounts
     * @param  array{registered: array<string, \Illuminate\Support\Collection>, all: array<string, \Illuminate\Support\Collection>}|null  $parentGroupContacts
     * @param  array{registered: array<int, \Illuminate\Support\Collection>, all: array<int, \Illuminate\Support\Collection>}|null  $ownCourseParentContacts
     * @param  array{registered: \Illuminate\Support\Collection<int, array<string, mixed>>, all: \Illuminate\Support\Collection<int, array<string, mixed>>}|null  $allSchoolMembers
     */
    private function serializeGroup(
        UserGroup $group,
        ?array $schoolGroupSourceCounts = null,
        ?array $ownCourseSourceCounts = null,
        ?array $parentGroupContacts = null,
        ?array $ownCourseParentContacts = null,
        ?array $allSchoolMembers = null
    ): array
    {
        $membersCount = (int) ($group->members_count ?? 0);
        $isSystemDefault = $this->isSystemDefaultSchoolGroup($group);
        $isSystemManagedCourseGroup = $this->isSystemManagedCourseGroup($group);
        $isParentGroup = $this->isParentGroup($group);
        $isAllSchoolMembersGroup = $this->isAllSchoolMembersGroup($group);
        $displayName = (string) $group->name;
        $sourceUsersCount = null;
        if ((string) $group->type === UserGroup::TYPE_SCHOOL) {
            $normalizedName = $this->normalizeGroupName((string) $group->name);
            if ($isAllSchoolMembersGroup) {
                $registeredAllSchoolMembers = is_array($allSchoolMembers) ? ($allSchoolMembers['registered'] ?? collect()) : collect();
                $allSchoolMembersEntries = is_array($allSchoolMembers) ? ($allSchoolMembers['all'] ?? collect()) : collect();
                $membersCount = (int) $registeredAllSchoolMembers->count();
                $sourceUsersCount = (int) $allSchoolMembersEntries->count();
            } elseif ($isParentGroup) {
                $registeredParentContacts = is_array($parentGroupContacts) ? ($parentGroupContacts['registered'][$normalizedName] ?? collect()) : collect();
                $allParentContacts = is_array($parentGroupContacts) ? ($parentGroupContacts['all'][$normalizedName] ?? collect()) : collect();
                $membersCount = (int) $registeredParentContacts->count();
                $sourceUsersCount = (int) $allParentContacts->count();
            } elseif ($this->isTeacherGroupName($normalizedName)) {
                $normalizedName = $this->normalizeGroupName($this->defaultTeacherGroupName());
                $displayName = $this->defaultTeacherGroupName();
                $sourceUsersCount = is_array($schoolGroupSourceCounts)
                    ? (int) ($schoolGroupSourceCounts[$normalizedName] ?? 0)
                    : null;
            } else {
                $sourceUsersCount = is_array($schoolGroupSourceCounts)
                    ? (int) ($schoolGroupSourceCounts[$normalizedName] ?? 0)
                    : null;
            }
        } elseif ($isSystemManagedCourseGroup && $group->teaching_course_id) {
            $courseId = (int) $group->teaching_course_id;
            if ($this->isSystemManagedCourseParentGroup($group)) {
                $registeredParentContacts = is_array($ownCourseParentContacts) ? ($ownCourseParentContacts['registered'][$courseId] ?? collect()) : collect();
                $allParentContacts = is_array($ownCourseParentContacts) ? ($ownCourseParentContacts['all'][$courseId] ?? collect()) : collect();
                $membersCount = (int) $registeredParentContacts->count();
                $sourceUsersCount = (int) $allParentContacts->count();
            } else {
                $sourceUsersCount = is_array($ownCourseSourceCounts)
                    ? (int) ($ownCourseSourceCounts[$courseId] ?? 0)
                    : 0;
            }
        }

        $canDelete = ! $isSystemDefault
            && ! $isSystemManagedCourseGroup
            && (
                (string) $group->type === UserGroup::TYPE_OWN
                || $membersCount === 0
            );

        return [
            'id' => (int) $group->id,
            'type' => (string) $group->type,
            'name' => $displayName,
            'description' => $group->description,
            'members_count' => $membersCount,
            'source_users_count' => $sourceUsersCount,
            'can_delete' => $canDelete,
            'can_edit' => ! $isSystemDefault && ! $isSystemManagedCourseGroup,
            'can_manage_members' => ! $isSystemDefault && ! $isSystemManagedCourseGroup,
            'is_system_default' => $isSystemDefault,
            'is_system_managed' => $isSystemDefault || $isSystemManagedCourseGroup,
            'is_parent_group' => $isParentGroup,
            'is_all_school_members_group' => $isAllSchoolMembersGroup,
            'created_by_user_id' => $group->created_by_user_id ? (int) $group->created_by_user_id : null,
            'teaching_course_id' => $group->teaching_course_id ? (int) $group->teaching_course_id : null,
            'teaching_course_group_type' => $this->isSystemManagedCourseGroup($group) ? $this->teachingCourseGroupType($group) : null,
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

    /**
     * @return array{registered: Collection<int, array<string, mixed>>, all: Collection<int, array<string, mixed>>}
     */
    private function allSchoolMembersCollections(int $schoolId): array
    {
        $importRows = collect();
        $usersByImportId = collect();

        if (Schema::hasTable('import116')) {
            $importRows = $this->import116QueryForActiveSchoolyear($schoolId)
                ->orderByRaw('LOWER(class)')
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->orderBy('email')
                ->get([
                    'id',
                    'class',
                    'user_id',
                    'last_name',
                    'first_name',
                    'email',
                    'mother_name',
                    'mother_email',
                    'mother_phone_1',
                    'mother_phone_2',
                    'father_name',
                    'father_email',
                    'father_phone_1',
                    'father_phone_2',
                ]);

            $importIds = $importRows->pluck('id')->map(fn ($id) => (int) $id)->values();

            if ($importIds->isNotEmpty()) {
                $usersByImportId = User::query()
                    ->where('school_id', $schoolId)
                    ->whereNotNull('import116_id')
                    ->whereIn('import116_id', $importIds->all())
                    ->get(['id', 'import116_id', 'last_name', 'first_name', 'email', 'schoolclass'])
                    ->keyBy(fn (User $user) => (int) $user->import116_id);
            }
        }

        $registeredStudents = $this->allSchoolStudentEntries($importRows, $usersByImportId, true);
        $allStudents = $this->allSchoolStudentEntries($importRows, $usersByImportId, false);

        $registeredParents = $this->allSchoolParentEntries(
            $this->buildParentContacts($importRows, $usersByImportId, true)
        );
        $allParents = $this->allSchoolParentEntries(
            $this->buildParentContacts($importRows, $usersByImportId, false)
        );

        $allTeachers = $this->allSchoolTeacherEntries($this->teacherSourceMembers($schoolId));
        $registeredTeachers = $this->allSchoolTeacherEntries(
            $this->teacherSourceMembers($schoolId)->filter(fn (array $entry) => ! empty($entry['user_id']))->values()
        );

        $adminEntries = $this->allSchoolAdminEntries($schoolId);

        return [
            'registered' => $this->mergeAllSchoolMemberEntries([
                $registeredStudents,
                $registeredParents,
                $registeredTeachers,
                $adminEntries,
            ]),
            'all' => $this->mergeAllSchoolMemberEntries([
                $allStudents,
                $allParents,
                $allTeachers,
                $adminEntries,
            ]),
        ];
    }

    private function activeImportSchoolyearId(int $schoolId): ?int
    {
        if (! Schema::hasTable('school_tools')) {
            return null;
        }

        $schoolyearId = SchoolTool::query()
            ->where('school_id', $schoolId)
            ->value('active_schoolyear_id');

        return $schoolyearId ? (int) $schoolyearId : null;
    }

    private function import116QueryForActiveSchoolyear(int $schoolId)
    {
        $query = Import116::query()->where('school_id', $schoolId);
        $activeSchoolyearId = $this->activeImportSchoolyearId($schoolId);

        if ($activeSchoolyearId) {
            return $query->where('schoolyear_id', $activeSchoolyearId);
        }

        return $query->whereRaw('1 = 0');
    }

    /**
     * @return array{registered: array<string, Collection>, all: array<string, Collection>}
     */
    private function parentContactsForSchoolGroups(int $schoolId): array
    {
        $result = [
            'registered' => [],
            'all' => [],
        ];

        if (! Schema::hasTable('import116')) {
            return $result;
        }

        $classGroups = $this->buildImportClassGroupMappings($schoolId);
        $allMappings = collect($classGroups['by_class'])
            ->merge($classGroups['by_family']);

        if ($allMappings->isEmpty()) {
            return $result;
        }

        $importIds = $allMappings
            ->pluck('import_ids')
            ->flatten()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($importIds->isEmpty()) {
            return $result;
        }

        $importRows = $this->import116QueryForActiveSchoolyear($schoolId)
            ->whereIn('id', $importIds->all())
            ->get([
                'id',
                'class',
                'user_id',
                'last_name',
                'first_name',
                'mother_name',
                'mother_email',
                'mother_phone_1',
                'mother_phone_2',
                'father_name',
                'father_email',
                'father_phone_1',
                'father_phone_2',
            ])
            ->keyBy(fn (Import116 $row) => (int) $row->id);

        $usersByImportId = User::query()
            ->where('school_id', $schoolId)
            ->whereNotNull('import116_id')
            ->whereIn('import116_id', $importIds->all())
            ->get(['id', 'import116_id'])
            ->keyBy(fn (User $user) => (int) $user->import116_id);

        foreach ($allMappings as $mapping) {
            $groupName = $this->parentGroupName((string) $mapping['name']);
            $normalizedGroupName = $this->normalizeGroupName($groupName);
            $rows = collect($mapping['import_ids'])
                ->map(fn ($id) => $importRows->get((int) $id))
                ->filter();

            $result['registered'][$normalizedGroupName] = $this->buildParentContacts($rows, $usersByImportId, true);
            $result['all'][$normalizedGroupName] = $this->buildParentContacts($rows, $usersByImportId, false);
        }

        return $result;
    }

    /**
     * @param  Collection<int, UserGroup>  $groups
     * @return array<int, int>
     */
    private function ownCourseSourceUserCounts(Collection $groups): array
    {
        $courseIds = $groups
            ->filter(fn (UserGroup $group) => $this->isSystemManagedCourseStudentGroup($group) && ! empty($group->teaching_course_id))
            ->pluck('teaching_course_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($courseIds->isEmpty()) {
            return [];
        }

        return TeachingCourse::query()
            ->whereIn('id', $courseIds->all())
            ->withCount('teachingCourseStudents')
            ->get(['id'])
            ->mapWithKeys(fn (TeachingCourse $course) => [
                (int) $course->id => (int) ($course->teaching_course_students_count ?? 0),
            ])
            ->all();
    }

    /**
     * @param  Collection<int, UserGroup>  $groups
     * @return array{registered: array<int, Collection>, all: array<int, Collection>}
     */
    private function parentContactsForAutomaticOwnCourseGroups(Collection $groups, int $schoolId): array
    {
        $result = [
            'registered' => [],
            'all' => [],
        ];

        $courseIds = $groups
            ->filter(fn (UserGroup $group) => $this->isSystemManagedCourseParentGroup($group) && ! empty($group->teaching_course_id))
            ->pluck('teaching_course_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($courseIds->isEmpty()) {
            return $result;
        }

        $courses = TeachingCourse::query()
            ->where('school_id', $schoolId)
            ->whereIn('id', $courseIds->all())
            ->with([
                'teachingCourseStudents:id,teaching_course_id,user_id,import116_id',
                'teachingCourseStudents.user:id,import116_id',
            ])
            ->get(['id']);

        if ($courses->isEmpty()) {
            return $result;
        }

        $importIds = $courses
            ->flatMap(fn (TeachingCourse $course) => $course->teachingCourseStudents->map(function ($courseStudent) {
                if ($courseStudent->import116_id) {
                    return (int) $courseStudent->import116_id;
                }

                if ($courseStudent->user?->import116_id) {
                    return (int) $courseStudent->user->import116_id;
                }

                return null;
            }))
            ->filter(fn ($id) => ! empty($id))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($importIds->isEmpty()) {
            foreach ($courses as $course) {
                $result['registered'][(int) $course->id] = collect();
                $result['all'][(int) $course->id] = collect();
            }

            return $result;
        }

        $importRows = $this->import116QueryForActiveSchoolyear($schoolId)
            ->whereIn('id', $importIds->all())
            ->get([
                'id',
                'class',
                'user_id',
                'last_name',
                'first_name',
                'mother_name',
                'mother_email',
                'mother_phone_1',
                'mother_phone_2',
                'father_name',
                'father_email',
                'father_phone_1',
                'father_phone_2',
            ])
            ->keyBy(fn (Import116 $row) => (int) $row->id);

        $usersByImportId = User::query()
            ->where('school_id', $schoolId)
            ->whereNotNull('import116_id')
            ->whereIn('import116_id', $importIds->all())
            ->get(['id', 'import116_id'])
            ->keyBy(fn (User $user) => (int) $user->import116_id);

        foreach ($courses as $course) {
            $rows = $course->teachingCourseStudents
                ->map(function ($courseStudent) use ($importRows) {
                    $importId = $courseStudent->import116_id ?: $courseStudent->user?->import116_id;

                    return $importId ? $importRows->get((int) $importId) : null;
                })
                ->filter()
                ->unique(fn (Import116 $row) => (int) $row->id)
                ->values();

            $result['registered'][(int) $course->id] = $this->buildParentContacts($rows, $usersByImportId, true);
            $result['all'][(int) $course->id] = $this->buildParentContacts($rows, $usersByImportId, false);
        }

        return $result;
    }

    private function parentContactsForAutomaticOwnCourseGroup(int $schoolId, UserGroup $group, bool $registeredStudentsOnly): Collection
    {
        if (! $this->isSystemManagedCourseParentGroup($group) || ! $group->teaching_course_id) {
            return collect();
        }

        $contacts = $this->parentContactsForAutomaticOwnCourseGroups(collect([$group]), $schoolId);
        $bucket = $registeredStudentsOnly ? 'registered' : 'all';

        return $contacts[$bucket][(int) $group->teaching_course_id] ?? collect();
    }

    /**
     * @param  Collection<int, Import116>  $rows
     * @param  Collection<int, User>  $usersByImportId
     * @return Collection<int, array<string, mixed>>
     */
    private function allSchoolStudentEntries(Collection $rows, Collection $usersByImportId, bool $registeredOnly): Collection
    {
        return $rows
            ->map(function (Import116 $row) use ($usersByImportId, $registeredOnly) {
                $mappedUser = $usersByImportId->get((int) $row->id);
                $userId = $mappedUser ? (int) $mappedUser->id : ((int) ($row->user_id ?? 0) ?: null);
                if ($registeredOnly && ! $userId) {
                    return null;
                }

                $name = $mappedUser
                    ? trim((string) (($mappedUser->last_name ?? '').' '.($mappedUser->first_name ?? '')))
                    : trim((string) (($row->last_name ?? '').' '.($row->first_name ?? '')));
                $email = $mappedUser?->email ?: $row->email;
                $schoolclass = $mappedUser?->schoolclass ?: $row->class;
                $entryKey = $userId ? 'user:'.$userId : 'student-import:'.(int) $row->id;

                return [
                    'entry_key' => $entryKey,
                    'id' => $userId ?: (int) $row->id,
                    'user_id' => $userId,
                    'import116_id' => (int) $row->id,
                    'name' => $name !== '' ? $name : ($email ?: 'Schüler:in'),
                    'email' => $email,
                    'schoolclass' => $schoolclass,
                    'phone' => null,
                    'children_label' => null,
                    'member_type_label' => 'Schüler:in',
                ];
            })
            ->filter()
            ->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $contacts
     * @return Collection<int, array<string, mixed>>
     */
    private function allSchoolParentEntries(Collection $contacts): Collection
    {
        return $contacts
            ->map(function (array $contact) {
                $contactId = (string) ($contact['id'] ?? '');

                return [
                    'entry_key' => 'parent:'.$contactId,
                    'id' => $contactId !== '' ? $contactId : md5((string) json_encode($contact)),
                    'user_id' => null,
                    'import116_id' => null,
                    'name' => $contact['name'] ?? 'Erziehungsberechtigte:r',
                    'email' => $contact['email'] ?? 'Keine E-Mail',
                    'schoolclass' => $contact['schoolclass'] ?? null,
                    'phone' => $contact['phone'] ?? null,
                    'children_label' => $contact['children_label'] ?? null,
                    'member_type_label' => 'Eltern',
                ];
            })
            ->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $teachers
     * @return Collection<int, array<string, mixed>>
     */
    private function allSchoolTeacherEntries(Collection $teachers): Collection
    {
        return $teachers
            ->map(function (array $entry) {
                $userId = ! empty($entry['user_id']) ? (int) $entry['user_id'] : null;
                $entryId = (string) ($entry['id'] ?? ($userId ?: 'teacher'));

                return [
                    'entry_key' => $userId ? 'user:'.$userId : 'teacher-source:'.$entryId,
                    'id' => $userId ?: $entryId,
                    'user_id' => $userId,
                    'import116_id' => null,
                    'name' => $entry['name'] ?? 'Lehrer:in',
                    'email' => $entry['email'] ?? null,
                    'schoolclass' => $entry['schoolclass'] ?? null,
                    'phone' => null,
                    'children_label' => null,
                    'member_type_label' => 'Lehrer:in',
                ];
            })
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function allSchoolAdminEntries(int $schoolId): Collection
    {
        return User::query()
            ->where('school_id', $schoolId)
            ->whereHas('roles', fn ($query) => $query->whereIn('name', $this->adminRoleNames()))
            ->get(['id', 'last_name', 'first_name', 'email', 'schoolclass'])
            ->map(function (User $user) {
                $name = trim((string) (($user->last_name ?? '').' '.($user->first_name ?? '')));
                $userId = (int) $user->id;

                return [
                    'entry_key' => 'user:'.$userId,
                    'id' => $userId,
                    'user_id' => $userId,
                    'import116_id' => $user->import116_id ? (int) $user->import116_id : null,
                    'name' => $name !== '' ? $name : ($user->email ?? 'Admin'),
                    'email' => $user->email,
                    'schoolclass' => $user->schoolclass,
                    'phone' => null,
                    'children_label' => null,
                    'member_type_label' => 'Admin',
                ];
            })
            ->values();
    }

    /**
     * @param  array<int, Collection<int, array<string, mixed>>>  $collections
     * @return Collection<int, array<string, mixed>>
     */
    private function mergeAllSchoolMemberEntries(array $collections): Collection
    {
        $merged = [];

        foreach ($collections as $collection) {
            foreach ($collection as $entry) {
                $entryKey = (string) ($entry['entry_key'] ?? '');
                if ($entryKey === '') {
                    continue;
                }

                if (! isset($merged[$entryKey])) {
                    $merged[$entryKey] = [
                        'id' => $entry['id'] ?? $entryKey,
                        'user_id' => $entry['user_id'] ?? null,
                        'import116_id' => $entry['import116_id'] ?? null,
                        'name' => $entry['name'] ?? '',
                        'email' => $entry['email'] ?? null,
                        'schoolclass' => $entry['schoolclass'] ?? null,
                        'phone' => $entry['phone'] ?? null,
                        'children_label' => $entry['children_label'] ?? null,
                        'member_types' => [],
                    ];
                }

                $typeLabel = trim((string) ($entry['member_type_label'] ?? ''));
                if ($typeLabel !== '') {
                    $merged[$entryKey]['member_types'][$typeLabel] = $typeLabel;
                }

                foreach (['name', 'email', 'schoolclass', 'phone', 'children_label'] as $field) {
                    $currentValue = trim((string) ($merged[$entryKey][$field] ?? ''));
                    $nextValue = trim((string) ($entry[$field] ?? ''));
                    if ($currentValue === '' && $nextValue !== '') {
                        $merged[$entryKey][$field] = $entry[$field];
                    }
                }
            }
        }

        return collect($merged)
            ->map(function (array $entry) {
                $typeLabels = array_values($entry['member_types']);
                usort($typeLabels, fn (string $a, string $b) => $this->allSchoolMemberTypeSortOrder($a) <=> $this->allSchoolMemberTypeSortOrder($b));
                $entry['member_type_label'] = implode(', ', $typeLabels);
                unset($entry['member_types']);

                return $entry;
            })
            ->sortBy([
                fn (array $entry) => mb_strtolower(trim((string) ($entry['name'] ?? ''))),
                fn (array $entry) => mb_strtolower(trim((string) ($entry['email'] ?? ''))),
            ])
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function sourceMembersForAutomaticCourseGroup(UserGroup $group, int $schoolId): Collection
    {
        if (! $group->teaching_course_id) {
            return collect();
        }

        $course = TeachingCourse::query()
            ->where('id', (int) $group->teaching_course_id)
            ->where('school_id', $schoolId)
            ->with([
                'teachingCourseStudents' => function ($query) {
                    $query->select(['id', 'teaching_course_id', 'user_id', 'import116_id']);
                },
                'teachingCourseStudents.user:id,last_name,first_name,email,schoolclass',
                'teachingCourseStudents.import116:id,class,last_name,first_name,email',
            ])
            ->first();

        if (! $course) {
            return collect();
        }

        $existingMemberIds = $group->members()
            ->pluck('users.id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return $course->teachingCourseStudents
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
                $importId = $courseStudent->import116_id ? (int) $courseStudent->import116_id : null;

                return [
                    'id' => $importId ?: $userId ?: (int) $courseStudent->id,
                    'user_id' => $userId,
                    'import116_id' => $importId,
                    'name' => $name !== '' ? $name : ($email ?: 'Schüler:in'),
                    'email' => $email,
                    'schoolclass' => $schoolclass,
                    'has_user_account' => (bool) $userId,
                    'already_member' => $userId ? in_array($userId, $existingMemberIds, true) : false,
                ];
            })
            ->values();
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

    private function assertNotSystemManagedGroup(UserGroup $group): void
    {
        if ($this->isSystemDefaultSchoolGroup($group)) {
            abort(409, 'Standard-Schulgruppen können nicht geändert oder gelöscht werden.');
        }

        if ($this->isSystemManagedCourseGroup($group)) {
            abort(409, 'Automatisch erstellte Kursgruppen können nicht geändert oder gelöscht werden.');
        }
    }

    private function assertMembersManageable(UserGroup $group): void
    {
        if ($this->isSystemDefaultSchoolGroup($group)) {
            abort(409, 'Standard-Schulgruppen werden automatisch verwaltet und können nicht manuell bearbeitet werden.');
        }

        if ($this->isSystemManagedCourseGroup($group)) {
            abort(409, 'Automatisch erstellte Kursgruppen werden automatisch verwaltet und können nicht manuell bearbeitet werden.');
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

    private function syncOwnTeachingCourseGroups($authUser, int $schoolId, int $actorUserId): void
    {
        if (! Schema::hasTable('teaching_courses') || ! Schema::hasTable('teaching_course_students')) {
            return;
        }

        $schoolyearId = $authUser->schoolyear_id ? (int) $authUser->schoolyear_id : null;

        $coursesQuery = TeachingCourse::query()
            ->where('school_id', $schoolId)
            ->where('user_id', (int) $authUser->id)
            ->with([
                'teachingCourseStudents:id,teaching_course_id,user_id,import116_id',
            ])
            ->orderByRaw('LOWER(title)')
            ->orderBy('id');

        if ($schoolyearId) {
            $coursesQuery->where('schoolyear_id', $schoolyearId);
        }

        $courses = $coursesQuery->get(['id', 'school_id', 'schoolyear_id', 'user_id', 'title', 'classes']);
        $courseIds = $courses->pluck('id')->map(fn ($id) => (int) $id)->values();

        $automaticCourseGroups = UserGroup::query()
            ->where('school_id', $schoolId)
            ->where('type', UserGroup::TYPE_OWN)
            ->whereNotNull('teaching_course_id')
            ->where('created_by_user_id', (int) $authUser->id)
            ->get();

        $staleGroupIds = $automaticCourseGroups
            ->reject(fn (UserGroup $group) => $courseIds->contains((int) $group->teaching_course_id))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values();

        if ($staleGroupIds->isNotEmpty()) {
            UserGroup::query()->whereIn('id', $staleGroupIds)->delete();
        }

        $importIds = $courses
            ->flatMap(fn (TeachingCourse $course) => $course->teachingCourseStudents->pluck('import116_id'))
            ->filter(fn ($id) => ! empty($id))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $usersByImportId = $importIds->isEmpty()
            ? collect()
            : User::query()
                ->where('school_id', $schoolId)
                ->whereNotNull('import116_id')
                ->whereIn('import116_id', $importIds->all())
                ->get(['id', 'import116_id'])
                ->groupBy(fn (User $user) => (int) $user->import116_id);

        foreach ($courses as $course) {
            $memberIds = $course->teachingCourseStudents
                ->flatMap(function ($courseStudent) use ($usersByImportId) {
                    if ($courseStudent->user_id) {
                        return [(int) $courseStudent->user_id];
                    }

                    if ($courseStudent->import116_id) {
                        return $usersByImportId
                            ->get((int) $courseStudent->import116_id, collect())
                            ->pluck('id')
                            ->map(fn ($id) => (int) $id)
                            ->all();
                    }

                    return [];
                })
                ->map(fn ($id) => (int) $id)
                ->filter(fn (int $id) => $id > 0)
                ->unique()
                ->values();

            $syncPayload = [];
            foreach ($memberIds as $userId) {
                $syncPayload[$userId] = [
                    'added_by_user_id' => $actorUserId > 0 ? $actorUserId : null,
                ];
            }

            $studentGroup = $this->firstAutomaticOwnCourseGroup((int) $course->id, UserGroup::TEACHING_COURSE_GROUP_TYPE_STUDENTS);

            if (! $studentGroup) {
                $studentGroup = UserGroup::query()->create([
                    'school_id' => $schoolId,
                    'type' => UserGroup::TYPE_OWN,
                    'name' => $this->courseGroupName($course),
                    'description' => $this->courseGroupDescription($course),
                    'created_by_user_id' => $actorUserId > 0 ? $actorUserId : null,
                    'teaching_course_id' => (int) $course->id,
                    'teaching_course_group_type' => UserGroup::TEACHING_COURSE_GROUP_TYPE_STUDENTS,
                ]);
            } else {
                $studentGroup->fill([
                    'school_id' => $schoolId,
                    'type' => UserGroup::TYPE_OWN,
                    'name' => $this->courseGroupName($course),
                    'description' => $this->courseGroupDescription($course),
                    'created_by_user_id' => $actorUserId > 0 ? $actorUserId : null,
                    'teaching_course_group_type' => UserGroup::TEACHING_COURSE_GROUP_TYPE_STUDENTS,
                ]);

                if ($studentGroup->isDirty()) {
                    $studentGroup->save();
                }
            }

            $studentGroup->members()->sync($syncPayload);

            $parentGroup = $this->firstAutomaticOwnCourseGroup((int) $course->id, UserGroup::TEACHING_COURSE_GROUP_TYPE_PARENTS);

            if (! $parentGroup) {
                $parentGroup = UserGroup::query()->create([
                    'school_id' => $schoolId,
                    'type' => UserGroup::TYPE_OWN,
                    'name' => $this->parentCourseGroupName($course),
                    'description' => $this->parentCourseGroupDescription($course),
                    'created_by_user_id' => $actorUserId > 0 ? $actorUserId : null,
                    'teaching_course_id' => (int) $course->id,
                    'teaching_course_group_type' => UserGroup::TEACHING_COURSE_GROUP_TYPE_PARENTS,
                ]);
            } else {
                $parentGroup->fill([
                    'school_id' => $schoolId,
                    'type' => UserGroup::TYPE_OWN,
                    'name' => $this->parentCourseGroupName($course),
                    'description' => $this->parentCourseGroupDescription($course),
                    'created_by_user_id' => $actorUserId > 0 ? $actorUserId : null,
                    'teaching_course_group_type' => UserGroup::TEACHING_COURSE_GROUP_TYPE_PARENTS,
                ]);

                if ($parentGroup->isDirty()) {
                    $parentGroup->save();
                }
            }

            $parentGroup->members()->sync([]);
        }
    }

    private function repairMissingImportUserLinksByEmail(int $schoolId): void
    {
        if (! Schema::hasTable('import116') || ! Schema::hasTable('users')) {
            return;
        }

        $importRows = $this->import116QueryForActiveSchoolyear($schoolId)
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
            ->get(['id', 'schoolclass', 'email']);

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

            $preferredCandidate = $candidateRows->first();
            if (! $preferredCandidate) {
                continue;
            }

            $updatePayload = [
                'import116_id' => (int) $importRow->id,
            ];
            if (trim((string) ($preferredCandidate->schoolclass ?? '')) === '' && trim((string) ($importRow->class ?? '')) !== '') {
                $updatePayload['schoolclass'] = trim((string) $importRow->class);
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
        $names = [
            $this->defaultTeacherGroupName(),
            $this->defaultAllSchoolMembersGroupName(),
        ];

        $classGroups = $this->buildImportClassGroupMappings($schoolId);
        foreach ($classGroups['by_class'] as $classData) {
            $names[] = $classData['name'];
            $names[] = $this->parentGroupName((string) $classData['name']);
        }
        foreach ($classGroups['by_family'] as $familyData) {
            $names[] = $familyData['name'];
            $names[] = $this->parentGroupName((string) $familyData['name']);
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

    private function defaultAllSchoolMembersGroupName(): string
    {
        return 'Alle Schulmitglieder';
    }

    private function parentGroupName(string $baseGroupName): string
    {
        return trim($baseGroupName).' Eltern';
    }

    private function isParentSchoolGroup(UserGroup $group): bool
    {
        return (string) $group->type === UserGroup::TYPE_SCHOOL
            && str_ends_with($this->normalizeGroupName((string) $group->name), $this->normalizeGroupName(' Eltern'));
    }

    private function isAllSchoolMembersGroup(UserGroup $group): bool
    {
        return (string) $group->type === UserGroup::TYPE_SCHOOL
            && $this->normalizeGroupName((string) $group->name) === $this->normalizeGroupName($this->defaultAllSchoolMembersGroupName());
    }

    private function parentGroupBaseName(UserGroup $group): string
    {
        $normalizedName = $this->normalizeGroupName((string) $group->name);
        $suffix = $this->normalizeGroupName(' Eltern');

        if (! str_ends_with($normalizedName, $suffix)) {
            return $normalizedName;
        }

        return trim(substr($normalizedName, 0, -mb_strlen($suffix)));
    }

    private function parentContactsForGroup(int $schoolId, UserGroup $group, bool $registeredStudentsOnly): Collection
    {
        if (! $this->isParentSchoolGroup($group)) {
            return collect();
        }

        $allContacts = $this->parentContactsForSchoolGroups($schoolId);
        $normalizedGroupName = $this->normalizeGroupName((string) $group->name);
        $bucket = $registeredStudentsOnly ? 'registered' : 'all';

        return $allContacts[$bucket][$normalizedGroupName] ?? collect();
    }

    /**
     * @param  Collection<int, Import116>  $rows
     * @param  Collection<int, User>  $usersByImportId
     * @return Collection<int, array<string, mixed>>
     */
    private function buildParentContacts(Collection $rows, Collection $usersByImportId, bool $registeredStudentsOnly): Collection
    {
        $contacts = [];

        foreach ($rows as $row) {
            $hasRegisteredStudent = (int) ($row->user_id ?? 0) > 0 || $usersByImportId->has((int) $row->id);
            if ($registeredStudentsOnly && ! $hasRegisteredStudent) {
                continue;
            }

            $studentName = trim((string) (($row->last_name ?? '').' '.($row->first_name ?? '')));
            $schoolclass = trim((string) ($row->class ?? ''));

            foreach ($this->parentContactsFromImportRow($row) as $contact) {
                $key = $this->parentContactKey($contact);
                if (! isset($contacts[$key])) {
                    $contacts[$key] = [
                        'id' => $key,
                        'name' => $contact['name'],
                        'email' => $contact['email'] !== '' ? $contact['email'] : 'Keine E-Mail',
                        'schoolclass' => $schoolclass,
                        'phone' => $contact['phone'],
                        'children' => [],
                        'classes' => [],
                    ];
                }

                if ($studentName !== '') {
                    $contacts[$key]['children'][$studentName] = $studentName;
                }
                if ($schoolclass !== '') {
                    $contacts[$key]['classes'][$schoolclass] = $schoolclass;
                }
            }
        }

        return collect($contacts)
            ->map(function (array $contact) {
                $children = array_values($contact['children']);
                sort($children, SORT_NATURAL | SORT_FLAG_CASE);
                $classes = array_values($contact['classes']);
                sort($classes, SORT_NATURAL | SORT_FLAG_CASE);

                $contact['children_label'] = implode(', ', $children);
                $contact['schoolclass'] = implode(', ', $classes);
                unset($contact['children'], $contact['classes']);

                return $contact;
            })
            ->sortBy([
                fn (array $contact) => mb_strtolower(trim((string) ($contact['name'] ?? ''))),
                fn (array $contact) => mb_strtolower(trim((string) ($contact['email'] ?? ''))),
            ])
            ->values();
    }

    /**
     * @return array<int, array{name: string, email: string, phone: string}>
     */
    private function parentContactsFromImportRow(Import116 $row): array
    {
        $contacts = [];

        foreach (['mother', 'father'] as $prefix) {
            $name = trim((string) ($row->{$prefix.'_name'} ?? ''));
            $email = trim((string) ($row->{$prefix.'_email'} ?? ''));
            $phones = collect([
                trim((string) ($row->{$prefix.'_phone_1'} ?? '')),
                trim((string) ($row->{$prefix.'_phone_2'} ?? '')),
            ])->filter(fn (string $value) => $value !== '')->unique()->values()->all();

            if ($name === '' && $email === '' && empty($phones)) {
                continue;
            }

            $contacts[] = [
                'name' => $name !== '' ? $name : ($email !== '' ? $email : 'Erziehungsberechtigte:r'),
                'email' => $email,
                'phone' => implode(' / ', $phones),
            ];
        }

        return $contacts;
    }

    /**
     * @param  array{name: string, email: string, phone: string}  $contact
     */
    private function parentContactKey(array $contact): string
    {
        $email = mb_strtolower(trim((string) ($contact['email'] ?? '')));
        if ($email !== '') {
            return 'email:'.$email;
        }

        return 'fallback:'.md5(implode('|', [
            mb_strtolower(trim((string) ($contact['name'] ?? ''))),
            mb_strtolower(trim((string) ($contact['phone'] ?? ''))),
        ]));
    }

    private function teachingCourseGroupType(UserGroup $group): string
    {
        return (string) ($group->teaching_course_group_type ?: UserGroup::TEACHING_COURSE_GROUP_TYPE_STUDENTS);
    }

    private function isSystemManagedCourseGroup(UserGroup $group): bool
    {
        return (string) $group->type === UserGroup::TYPE_OWN
            && ! empty($group->teaching_course_id);
    }

    private function isSystemManagedCourseStudentGroup(UserGroup $group): bool
    {
        return $this->isSystemManagedCourseGroup($group)
            && $this->teachingCourseGroupType($group) === UserGroup::TEACHING_COURSE_GROUP_TYPE_STUDENTS;
    }

    private function isSystemManagedCourseParentGroup(UserGroup $group): bool
    {
        return $this->isSystemManagedCourseGroup($group)
            && $this->teachingCourseGroupType($group) === UserGroup::TEACHING_COURSE_GROUP_TYPE_PARENTS;
    }

    private function isParentGroup(UserGroup $group): bool
    {
        return $this->isParentSchoolGroup($group) || $this->isSystemManagedCourseParentGroup($group);
    }

    /**
     * @return array<int, string>
     */
    private function adminRoleNames(): array
    {
        return ['super_admin', 'admin', 'materials_admin', 'materials_moderator'];
    }

    private function allSchoolMemberTypeSortOrder(string $label): int
    {
        return match ($label) {
            'Schüler:in' => 1,
            'Eltern' => 2,
            'Lehrer:in' => 3,
            'Admin' => 4,
            default => 99,
        };
    }

    private function firstAutomaticOwnCourseGroup(int $courseId, string $groupType): ?UserGroup
    {
        return UserGroup::query()
            ->where('type', UserGroup::TYPE_OWN)
            ->where('teaching_course_id', $courseId)
            ->where(function ($query) use ($groupType) {
                if ($groupType === UserGroup::TEACHING_COURSE_GROUP_TYPE_STUDENTS) {
                    $query
                        ->where('teaching_course_group_type', UserGroup::TEACHING_COURSE_GROUP_TYPE_STUDENTS)
                        ->orWhereNull('teaching_course_group_type');

                    return;
                }

                $query->where('teaching_course_group_type', $groupType);
            })
            ->orderBy('id')
            ->first();
    }

    private function courseGroupName(TeachingCourse $course): string
    {
        $title = trim((string) ($course->title ?? ''));
        $classesLabel = $this->courseClassesLabel($course);

        if ($title === '' && $classesLabel === '') {
            return 'Kurs';
        }

        if ($title === '') {
            return $classesLabel;
        }

        if ($classesLabel === '') {
            return $title;
        }

        return sprintf('%s (%s)', $title, $classesLabel);
    }

    private function courseGroupDescription(TeachingCourse $course): ?string
    {
        return null;
    }

    private function parentCourseGroupName(TeachingCourse $course): string
    {
        return trim($this->courseGroupName($course)).' Eltern';
    }

    private function parentCourseGroupDescription(TeachingCourse $course): ?string
    {
        return null;
    }

    private function courseClassesLabel(TeachingCourse $course): string
    {
        $classes = collect(is_array($course->classes) ? $course->classes : [])
            ->map(fn ($value) => trim((string) $value))
            ->filter(fn (string $value) => $value !== '')
            ->unique()
            ->sort(fn (string $a, string $b) => strnatcasecmp($a, $b))
            ->values()
            ->all();

        return implode(', ', $classes);
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
        return $this->teacherSourceMembers($schoolId)->count();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function teacherSourceMembers(int $schoolId, ?UserGroup $group = null): Collection
    {
        $existingMemberIds = $group
            ? $group->members()->pluck('users.id')->map(fn ($id) => (int) $id)->all()
            : [];

        $teacherRowsByEmail = collect();
        if (Schema::hasTable('teachers')) {
            $teacherRowsByEmail = Teacher::query()
                ->where('school_id', $schoolId)
                ->whereNotNull('email')
                ->whereRaw('TRIM(email) <> ?', [''])
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->orderBy('email')
                ->get(['id', 'last_name', 'first_name', 'email', 'short'])
                ->mapWithKeys(function (Teacher $teacher) {
                    $normalizedEmail = mb_strtolower(trim((string) ($teacher->email ?? '')));

                    return $normalizedEmail !== '' ? [$normalizedEmail => $teacher] : [];
                });
        }

        $matchedUsersByEmail = $teacherRowsByEmail->isEmpty()
            ? collect()
            : User::query()
                ->where('school_id', $schoolId)
                ->whereNotNull('email')
                ->whereRaw('TRIM(email) <> ?', [''])
                ->whereIn(DB::raw('LOWER(TRIM(email))'), $teacherRowsByEmail->keys()->all())
                ->get(['id', 'last_name', 'first_name', 'email', 'schoolclass'])
                ->keyBy(fn (User $user) => mb_strtolower(trim((string) ($user->email ?? ''))));

        $entries = collect($teacherRowsByEmail->all())->map(function (Teacher $teacher, string $normalizedEmail) use ($matchedUsersByEmail, $existingMemberIds) {
            $user = $matchedUsersByEmail->get($normalizedEmail);
            $teacherName = trim((string) (($teacher->last_name ?? '').' '.($teacher->first_name ?? '')));
            $userName = $user ? trim((string) (($user->last_name ?? '').' '.($user->first_name ?? ''))) : '';
            $userId = $user ? (int) $user->id : null;

            return [
                'id' => 'teacher-email:'.$normalizedEmail,
                'user_id' => $userId,
                'import116_id' => null,
                'name' => $teacherName !== '' ? $teacherName : ($userName !== '' ? $userName : ((string) ($teacher->email ?? 'Lehrer:in'))),
                'email' => $teacher->email ?: ($user?->email),
                'schoolclass' => $user?->schoolclass,
                'has_user_account' => (bool) $userId,
                'already_member' => $userId ? in_array($userId, $existingMemberIds, true) : false,
            ];
        })->values();

        $roleEntries = User::query()
            ->where('school_id', $schoolId)
            ->whereHas('roles', fn ($query) => $query->where('name', 'teacher'))
            ->get(['id', 'last_name', 'first_name', 'email', 'schoolclass'])
            ->reject(function (User $user) use ($teacherRowsByEmail) {
                $normalizedEmail = mb_strtolower(trim((string) ($user->email ?? '')));

                return $normalizedEmail !== '' && $teacherRowsByEmail->has($normalizedEmail);
            })
            ->map(function (User $user) use ($existingMemberIds) {
                $name = trim((string) (($user->last_name ?? '').' '.($user->first_name ?? '')));
                $userId = (int) $user->id;

                return [
                    'id' => 'teacher-role:'.$userId,
                    'user_id' => $userId,
                    'import116_id' => null,
                    'name' => $name !== '' ? $name : ((string) ($user->email ?? 'Lehrer:in')),
                    'email' => $user->email,
                    'schoolclass' => $user->schoolclass,
                    'has_user_account' => true,
                    'already_member' => in_array($userId, $existingMemberIds, true),
                ];
            })
            ->values()
            ->toBase();

        return $entries
            ->merge($roleEntries)
            ->sortBy([
                fn (array $entry) => mb_strtolower(trim((string) ($entry['name'] ?? ''))),
                fn (array $entry) => mb_strtolower(trim((string) ($entry['email'] ?? ''))),
            ])
            ->values();
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

        $importRows = $this->import116QueryForActiveSchoolyear($schoolId)
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

    /**
     * @return array<int, int>
     */
    private function importIdsForSchoolGroup(int $schoolId, UserGroup $group): array
    {
        if ((string) $group->type !== UserGroup::TYPE_SCHOOL) {
            return [];
        }

        $normalizedGroupName = $this->normalizeGroupName((string) $group->name);
        if ($this->isTeacherGroupName($normalizedGroupName)) {
            return [];
        }

        $classGroupMappings = $this->buildImportClassGroupMappings($schoolId);
        $importIds = $classGroupMappings['by_family'][$normalizedGroupName]['import_ids']
            ?? $classGroupMappings['by_class'][$normalizedGroupName]['import_ids']
            ?? [];

        return collect($importIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }
}
