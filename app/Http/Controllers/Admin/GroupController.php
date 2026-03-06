<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Import116;
use App\Models\SchoolTool;
use App\Models\Teacher;
use App\Models\TeachingCourse;
use App\Models\User;
use App\Models\UserGroup;
use App\Models\UserGroupMember;
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
        $this->syncParentSchoolGroupMembers($schoolId, (int) $auth_user->id);
        $this->syncAllSchoolMembersGroup($schoolId, (int) $auth_user->id);
        $this->syncOwnTeachingCourseGroups($auth_user, $schoolId, (int) $auth_user->id);

        $groups = UserGroup::query()
            ->where('school_id', $schoolId)
            ->withCount(['groupMembers', 'members'])
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

        $group->loadCount(['groupMembers', 'members']);

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
        $group->loadCount(['groupMembers', 'members']);

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

        if ((string) $group->type !== UserGroup::TYPE_OWN && $group->groupMembers()->exists()) {
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

        $this->refreshGroupMembersSyncState($group, $schoolId);

        $members = $group->groupMembers()
            ->with('linkedUser:id')
            ->orderByRaw('LOWER(display_name)')
            ->orderByRaw('LOWER(display_email)')
            ->get();

        return response()->json([
            'data' => $members->map(fn (UserGroupMember $member) => $this->serializeStoredGroupMember($member))->values(),
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

        $existingMembers = $this->existingStoredGroupMembers($group);

        $data = $importRows
            ->map(function (Import116 $import) use ($usersByImportId, $existingMembers) {
                return $this->serializeAssignableMemberPayload(
                    $this->payloadForImportStudent($import, $usersByImportId->get((int) $import->id)),
                    $existingMembers,
                );
            })
            ->values();

        return response()->json([
            'data' => $data,
            'meta' => ['total' => $data->count()],
        ]);
    }

    public function removeMember(Request $request, UserGroup $group, UserGroupMember $member)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'materials_admin', 'materials_moderator'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }
        $this->assertGroupsFeatureLicence($auth_user);

        $schoolId = $this->currentSchoolId($auth_user);
        $this->assertGroupInCurrentSchool($group, $schoolId);
        $this->assertTypePermission($auth_user, (string) $group->type);
        $this->assertMembersManageable($group);

        if ((int) $member->user_group_id !== (int) $group->id || (int) $member->school_id !== $schoolId) {
            abort(404, 'Gruppenmitglied nicht gefunden.');
        }

        $member->delete();
        $group->loadCount(['groupMembers', 'members']);

        return response()->json([
            'message' => 'Mitglied wurde aus der Gruppe entfernt.',
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
            'member_ids' => ['required', 'array', 'min:1'],
            'member_ids.*' => ['required', 'integer', 'distinct'],
        ]);

        $memberIds = collect($validated['member_ids'])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $validMemberIds = $group->groupMembers()
            ->where('school_id', $schoolId)
            ->whereIn('id', $memberIds)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values();

        if ($validMemberIds->isEmpty()) {
            abort(422, 'Keine passenden Gruppenmitglieder in der aktuellen Schule gefunden.');
        }

        $group->groupMembers()->whereIn('id', $validMemberIds->all())->delete();
        $group->loadCount(['groupMembers', 'members']);

        return response()->json([
            'message' => 'Mitglieder wurden aus der Gruppe entfernt.',
            'meta' => [
                'removed_count' => $validMemberIds->count(),
                'members_count' => (int) ($group->group_members_count ?? 0),
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

        $existingMembers = $this->existingStoredGroupMembers($group);

        if ($roleName === 'student') {
            return $this->assignableStudentsFromImport116($auth_user, $group, $search, $limit, $existingMembers);
        }

        $payloads = $roleName === 'teacher'
            ? $this->assignableTeacherPayloads($schoolId, $search, $limit)
            : $this->combinedAssignablePayloads($schoolId, $search, $limit);

        $data = $payloads
            ->reject(fn (array $payload) => (int) ($payload['linked_user_id'] ?? 0) === (int) $auth_user->id)
            ->map(fn (array $payload) => $this->serializeAssignableMemberPayload($payload, $existingMembers))
            ->values();

        return response()->json(['data' => $data]);
    }

    private function assignableStudentsFromImport116($auth_user, UserGroup $group, string $search, int $limit, Collection $existingMembers)
    {
        $schoolId = $this->currentSchoolId($auth_user);
        $data = $this->assignableStudentPayloads($schoolId, $search, $limit)
            ->reject(fn (array $payload) => (int) ($payload['linked_user_id'] ?? 0) === (int) $auth_user->id)
            ->map(fn (array $payload) => $this->serializeAssignableMemberPayload($payload, $existingMembers))
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
            'user_ids' => ['nullable', 'array', 'min:1'],
            'user_ids.*' => ['required', 'integer', 'distinct', 'exists:users,id'],
            'members' => ['nullable', 'array', 'min:1'],
            'members.*.member_provider' => ['required_with:members', 'string', 'max:64'],
            'members.*.member_ref' => ['required_with:members', 'string', 'max:191'],
        ]);

        $payloads = collect();

        $userIds = collect($validated['user_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values();
        if ($userIds->isNotEmpty()) {
            $payloads = $payloads->concat(
                User::query()
                    ->where('school_id', $schoolId)
                    ->whereIn('id', $userIds->all())
                    ->get(['id', 'school_id', 'schoolyear_id', 'last_name', 'first_name', 'email', 'phone', 'schoolclass'])
                    ->map(fn (User $user) => $this->payloadForUserSource($user))
                    ->values()
            );
        }

        $memberPayloads = collect($validated['members'] ?? [])
            ->map(function (array $payload) use ($schoolId) {
                return $this->resolveStoredMemberPayloadByProviderAndRef(
                    (string) ($payload['member_provider'] ?? ''),
                    (string) ($payload['member_ref'] ?? ''),
                    $schoolId,
                );
            })
            ->filter()
            ->values();

        $payloads = $payloads
            ->concat($memberPayloads)
            ->unique(fn (array $payload) => $this->storedGroupMemberKeyFromPayload($payload))
            ->values();

        if ($payloads->isEmpty()) {
            abort(422, 'Keine passenden Mitglieder in der aktuellen Schule gefunden.');
        }

        $result = $this->storeGroupMembers($group, $payloads, (int) $auth_user->id);
        $group->loadCount(['groupMembers', 'members']);

        return response()->json([
            'message' => 'Mitglieder wurden zugeordnet.',
            'meta' => [
                'assigned_count' => (int) ($result['assigned_count'] ?? 0),
                'new_count' => (int) ($result['new_count'] ?? 0),
                'members_count' => (int) ($group->group_members_count ?? 0),
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
            ->whereHas('groupMembers')
            ->withCount(['groupMembers', 'members'])
            ->orderByRaw("CASE type WHEN 'school' THEN 1 WHEN 'materials' THEN 2 ELSE 3 END")
            ->orderByRaw('LOWER(name)')
            ->get();

        return response()->json([
            'data' => $groups->map(fn (UserGroup $row) => [
                'id' => (int) $row->id,
                'type' => (string) $row->type,
                'name' => (string) $row->name,
                'members_count' => (int) ($row->group_members_count ?? 0),
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

        $sourcePayloads = $sourceGroup->groupMembers()
            ->get()
            ->map(function (UserGroupMember $member) {
                return [
                    'member_provider' => (string) $member->member_provider,
                    'member_ref' => (string) $member->member_ref,
                    'linked_user_id' => $member->linked_user_id ? (int) $member->linked_user_id : null,
                    'source_schoolyear_id' => $member->source_schoolyear_id ? (int) $member->source_schoolyear_id : null,
                    'display_name' => $member->display_name,
                    'display_email' => $member->display_email,
                    'display_phone' => $member->display_phone,
                    'display_schoolclass' => $member->display_schoolclass,
                    'display_children_label' => $member->display_children_label,
                    'member_type_label' => $member->member_type_label,
                    'source_status' => $member->source_status,
                    'linked_user_status' => $member->linked_user_status,
                    'meta' => $member->meta,
                ];
            })
            ->values();

        if ($sourcePayloads->isEmpty()) {
            return response()->json([
                'message' => 'Die Quellgruppe enthält keine Mitglieder.',
                'meta' => ['assigned_count' => 0, 'new_count' => 0],
            ]);
        }

        $result = $this->storeGroupMembers($group, $sourcePayloads, (int) $auth_user->id);
        $group->loadCount(['groupMembers', 'members']);

        return response()->json([
            'message' => 'Mitglieder aus der Quellgruppe wurden übernommen.',
            'meta' => [
                'assigned_count' => (int) ($result['assigned_count'] ?? 0),
                'new_count' => (int) ($result['new_count'] ?? 0),
                'members_count' => (int) ($group->group_members_count ?? 0),
            ],
            'group' => $this->serializeGroup($group),
        ]);
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
        $existingMembers = $this->existingStoredGroupMembers($group);

        $data = $courses->map(function (TeachingCourse $course) use ($existingMembers, $schoolId) {
            $students = $course->teachingCourseStudents
                ->map(function ($courseStudent) use ($existingMembers, $schoolId) {
                    $user = $courseStudent->user;
                    $import = $courseStudent->import116;

                    if ($import) {
                        return $this->serializeAssignableMemberPayload(
                            $this->payloadForImportStudent($import, $user ?: $this->linkedUserForImportStudent($schoolId, $import)),
                            $existingMembers,
                        );
                    }

                    if ($user) {
                        return $this->serializeAssignableMemberPayload(
                            $this->payloadForUserSource($user, 'Schüler:in'),
                            $existingMembers,
                        );
                    }

                    return null;
                })
                ->filter()
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
        $storedMembersCount = (int) ($group->group_members_count ?? 0);
        $linkedMembersCount = (int) ($group->members_count ?? 0);
        $membersCount = $storedMembersCount > 0 ? $storedMembersCount : $linkedMembersCount;
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
                $membersCount = $linkedMembersCount;
                $sourceUsersCount = is_array($schoolGroupSourceCounts)
                    ? (int) ($schoolGroupSourceCounts[$normalizedName] ?? 0)
                    : null;
            } else {
                $membersCount = $linkedMembersCount;
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
                $membersCount = $linkedMembersCount;
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

        $existingMembers = $this->existingStoredGroupMembers($group);

        return $course->teachingCourseStudents
            ->map(function ($courseStudent) use ($existingMembers, $schoolId) {
                $user = $courseStudent->user;
                $import = $courseStudent->import116;

                if ($import) {
                    return $this->serializeAssignableMemberPayload(
                        $this->payloadForImportStudent($import, $user ?: $this->linkedUserForImportStudent($schoolId, $import)),
                        $existingMembers,
                    );
                }

                if ($user) {
                    return $this->serializeAssignableMemberPayload(
                        $this->payloadForUserSource($user, 'Schüler:in'),
                        $existingMembers,
                    );
                }

                return null;
            })
            ->filter()
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

        $this->syncGroupMembersFromPayloads(
            $teacherGroup,
            $this->assignableTeacherPayloads($schoolId, '', 5000),
            $actorUserId,
        );
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
            ->merge(collect($classGroupMappings['by_family'])->map(fn (array $row) => $row['import_ids'])->flatten())
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $importsById = $importIdsByClass->isEmpty()
            ? collect()
            : Import116::query()
                ->where('school_id', $schoolId)
                ->whereIn('id', $importIdsByClass->all())
                ->get(['id', 'school_id', 'schoolyear_id', 'class', 'user_id', 'last_name', 'first_name', 'email', 'phone_1', 'phone_2'])
                ->keyBy(fn (Import116 $import) => (int) $import->id);

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
            if (empty($importIds) || $this->isParentSchoolGroup($group) || $this->isAllSchoolMembersGroup($group)) {
                continue;
            }

            $payloads = collect($importIds)
                ->map(fn (int $importId) => $importsById->get($importId))
                ->filter()
                ->map(fn (Import116 $import) => $this->payloadForImportStudent($import, $this->linkedUserForImportStudent($schoolId, $import)))
                ->values();

            $this->syncGroupMembersFromPayloads($group, $payloads, $actorUserId);
        }
    }

    private function syncParentSchoolGroupMembers(int $schoolId, int $actorUserId): void
    {
        $parentContacts = $this->parentContactsForSchoolGroups($schoolId);
        $activeSchoolyearId = $this->activeImportSchoolyearId($schoolId);
        if (! $activeSchoolyearId) {
            return;
        }

        $groups = UserGroup::query()
            ->where('school_id', $schoolId)
            ->where('type', UserGroup::TYPE_SCHOOL)
            ->get();

        foreach ($groups as $group) {
            if (! $this->isParentSchoolGroup($group)) {
                continue;
            }

            $normalizedGroupName = $this->normalizeGroupName((string) $group->name);
            $payloads = collect($parentContacts['all'][$normalizedGroupName] ?? [])
                ->map(fn (array $contact) => $this->payloadForParentContact($schoolId, $activeSchoolyearId, $contact))
                ->values();

            $this->syncGroupMembersFromPayloads($group, $payloads, $actorUserId);
        }
    }

    private function syncAllSchoolMembersGroup(int $schoolId, int $actorUserId): void
    {
        $group = UserGroup::query()
            ->where('school_id', $schoolId)
            ->where('type', UserGroup::TYPE_SCHOOL)
            ->whereRaw('LOWER(TRIM(name)) = ?', [$this->normalizeGroupName($this->defaultAllSchoolMembersGroupName())])
            ->first();

        if (! $group) {
            return;
        }

        $payloads = $this->allSchoolMembersCollections($schoolId)['all']
            ->map(fn (array $entry) => $this->payloadFromAllSchoolEntry($schoolId, $entry))
            ->filter()
            ->values();

        $this->syncGroupMembersFromPayloads($group, $payloads, $actorUserId);
    }

    /**
     * @param  array<string, mixed>  $entry
     * @return array<string, mixed>|null
     */
    private function payloadFromAllSchoolEntry(int $schoolId, array $entry): ?array
    {
        $memberTypeLabel = (string) ($entry['member_type_label'] ?? '');
        $userId = (int) ($entry['user_id'] ?? 0);
        $import116Id = (int) ($entry['import116_id'] ?? 0);

        if ($import116Id > 0 && str_contains($memberTypeLabel, 'Schüler:in')) {
            $import = Import116::query()
                ->where('school_id', $schoolId)
                ->where('id', $import116Id)
                ->first(['id', 'school_id', 'schoolyear_id', 'class', 'user_id', 'last_name', 'first_name', 'email', 'phone_1', 'phone_2']);
            if ($import) {
                $linkedUser = $userId > 0
                    ? User::query()->where('school_id', $schoolId)->where('id', $userId)->first(['id', 'school_id', 'import116_id', 'last_name', 'first_name', 'email', 'schoolclass'])
                    : $this->linkedUserForImportStudent($schoolId, $import);

                return $this->payloadForImportStudent($import, $linkedUser);
            }
        }

        if (str_contains($memberTypeLabel, 'Eltern')) {
            $activeSchoolyearId = $this->activeImportSchoolyearId($schoolId);
            if (! $activeSchoolyearId) {
                return null;
            }

            return $this->payloadForParentContact($schoolId, $activeSchoolyearId, [
                'name' => $entry['name'] ?? 'Erziehungsberechtigte:r',
                'email' => $entry['email'] ?? null,
                'phone' => $entry['phone'] ?? null,
                'schoolclass' => $entry['schoolclass'] ?? null,
                'children_label' => $entry['children_label'] ?? null,
            ]);
        }

        if ($userId > 0) {
            $user = User::query()
                ->where('school_id', $schoolId)
                ->where('id', $userId)
                ->first(['id', 'school_id', 'schoolyear_id', 'last_name', 'first_name', 'email', 'phone', 'schoolclass']);

            return $user ? $this->payloadForUserSource($user, $memberTypeLabel !== '' ? $memberTypeLabel : 'Benutzer') : null;
        }

        if (str_contains($memberTypeLabel, 'Lehrer:in')) {
            $teacher = Teacher::query()
                ->where('school_id', $schoolId)
                ->whereNotNull('email')
                ->whereRaw('LOWER(TRIM(email)) = ?', [$this->normalizeEmail((string) ($entry['email'] ?? ''))])
                ->first(['id', 'school_id', 'last_name', 'first_name', 'email', 'short']);

            return $teacher ? $this->payloadForTeacherSource($teacher, $this->linkedUserByEmail($schoolId, $teacher->email)) : null;
        }

        return null;
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
                'teachingCourseStudents.user:id,school_id,schoolyear_id,import116_id,last_name,first_name,email,phone,schoolclass',
                'teachingCourseStudents.import116:id,school_id,schoolyear_id,class,user_id,last_name,first_name,email,phone_1,phone_2,mother_name,mother_email,mother_phone_1,mother_phone_2,father_name,father_email,father_phone_1,father_phone_2',
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

        $activeImportSchoolyearId = $this->activeImportSchoolyearId($schoolId);

        foreach ($courses as $course) {
            $studentPayloads = $course->teachingCourseStudents
                ->map(function ($courseStudent) use ($schoolId) {
                    if ($courseStudent->import116) {
                        return $this->payloadForImportStudent(
                            $courseStudent->import116,
                            $courseStudent->user ?: $this->linkedUserForImportStudent($schoolId, $courseStudent->import116),
                        );
                    }

                    if ($courseStudent->user) {
                        return $this->payloadForUserSource($courseStudent->user, 'Schüler:in');
                    }

                    return null;
                })
                ->filter()
                ->values();

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

            $this->syncGroupMembersFromPayloads($studentGroup, $studentPayloads, $actorUserId);

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

            $parentImportIds = $course->teachingCourseStudents
                ->flatMap(function ($courseStudent) {
                    $ids = [];
                    if ($courseStudent->import116) {
                        $ids[] = (int) $courseStudent->import116->id;
                    }
                    if ((int) ($courseStudent->user?->import116_id ?? 0) > 0) {
                        $ids[] = (int) $courseStudent->user->import116_id;
                    }

                    return $ids;
                })
                ->filter(fn ($id) => (int) $id > 0)
                ->unique()
                ->values();

            $parentImportRows = $parentImportIds->isEmpty()
                ? collect()
                : Import116::query()
                    ->where('school_id', $schoolId)
                    ->whereIn('id', $parentImportIds->all())
                    ->get([
                        'id',
                        'schoolyear_id',
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
                    ]);

            $usersByImportId = $parentImportIds->isEmpty()
                ? collect()
                : User::query()
                    ->where('school_id', $schoolId)
                    ->whereNotNull('import116_id')
                    ->whereIn('import116_id', $parentImportIds->all())
                    ->get(['id', 'import116_id'])
                    ->keyBy(fn (User $user) => (int) $user->import116_id);

            $parentPayloads = $this->buildParentContacts($parentImportRows, $usersByImportId, false)
                ->map(fn (array $contact) => $this->payloadForParentContact($schoolId, $activeImportSchoolyearId ?: (int) $course->schoolyear_id, $contact))
                ->values();

            $this->syncGroupMembersFromPayloads($parentGroup, $parentPayloads, $actorUserId);
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
        $existingMembers = $group ? $this->existingStoredGroupMembers($group) : collect();

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

        $entries = collect($teacherRowsByEmail->all())->map(function (Teacher $teacher, string $normalizedEmail) use ($matchedUsersByEmail, $existingMembers) {
            $user = $matchedUsersByEmail->get($normalizedEmail);
            $teacherName = trim((string) (($teacher->last_name ?? '').' '.($teacher->first_name ?? '')));
            $userName = $user ? trim((string) (($user->last_name ?? '').' '.($user->first_name ?? ''))) : '';
            $userId = $user ? (int) $user->id : null;
            $storedKey = $this->storedGroupMemberKey(
                UserGroupMember::PROVIDER_TEACHER_LIST_TEACHER,
                $this->teacherListTeacherMemberRef((int) $teacher->id),
            );

            return [
                'id' => 'teacher-email:'.$normalizedEmail,
                'user_id' => $userId,
                'import116_id' => null,
                'name' => $teacherName !== '' ? $teacherName : ($userName !== '' ? $userName : ((string) ($teacher->email ?? 'Lehrer:in'))),
                'email' => $teacher->email ?: ($user?->email),
                'schoolclass' => $user?->schoolclass,
                'has_user_account' => (bool) $userId,
                'already_member' => $existingMembers->has($storedKey),
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
            ->map(function (User $user) use ($existingMembers) {
                $name = trim((string) (($user->last_name ?? '').' '.($user->first_name ?? '')));
                $userId = (int) $user->id;
                $storedKey = $this->storedGroupMemberKey(
                    UserGroupMember::PROVIDER_USER,
                    $this->userMemberRef($userId),
                );

                return [
                    'id' => 'teacher-role:'.$userId,
                    'user_id' => $userId,
                    'import116_id' => null,
                    'name' => $name !== '' ? $name : ((string) ($user->email ?? 'Lehrer:in')),
                    'email' => $user->email,
                    'schoolclass' => $user->schoolclass,
                    'has_user_account' => true,
                    'already_member' => $existingMembers->has($storedKey),
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

    private function userMemberRef(int $userId): string
    {
        return 'user:'.$userId;
    }

    private function import116StudentMemberRef(int $importId): string
    {
        return 'import116.student:'.$importId;
    }

    private function teacherListTeacherMemberRef(int $teacherId): string
    {
        return 'teacher_list.teacher:'.$teacherId;
    }

    private function import116ParentContactMemberRef(int $schoolyearId, string $contactKey): string
    {
        return 'import116.parent_contact:'.$schoolyearId.':'.base64_encode($contactKey);
    }

    private function storedGroupMemberKey(string $provider, string $memberRef): string
    {
        return $provider.'|'.$memberRef;
    }

    /**
     * @param  array{member_provider:string,member_ref:string}  $payload
     */
    private function storedGroupMemberKeyFromPayload(array $payload): string
    {
        return $this->storedGroupMemberKey((string) $payload['member_provider'], (string) $payload['member_ref']);
    }

    private function normalizeEmail(?string $value): string
    {
        return mb_strtolower(trim((string) ($value ?? '')));
    }

    private function linkedUserByEmail(int $schoolId, ?string $email): ?User
    {
        $normalizedEmail = $this->normalizeEmail($email);
        if ($normalizedEmail === '') {
            return null;
        }

        return User::query()
            ->where('school_id', $schoolId)
            ->whereNotNull('email')
            ->whereRaw('LOWER(TRIM(email)) = ?', [$normalizedEmail])
            ->orderBy('id')
            ->first(['id', 'school_id', 'import116_id', 'last_name', 'first_name', 'email', 'schoolclass']);
    }

    private function linkedUserForImportStudent(int $schoolId, Import116 $import): ?User
    {
        $directUserId = (int) ($import->user_id ?? 0);
        if ($directUserId > 0) {
            $directUser = User::query()
                ->where('school_id', $schoolId)
                ->where('id', $directUserId)
                ->first(['id', 'school_id', 'import116_id', 'last_name', 'first_name', 'email', 'schoolclass']);
            if ($directUser) {
                return $directUser;
            }
        }

        $linkedByImportId = User::query()
            ->where('school_id', $schoolId)
            ->where('import116_id', (int) $import->id)
            ->orderBy('id')
            ->first(['id', 'school_id', 'import116_id', 'last_name', 'first_name', 'email', 'schoolclass']);
        if ($linkedByImportId) {
            return $linkedByImportId;
        }

        return $this->linkedUserByEmail($schoolId, $import->email);
    }

    /**
     * @return array<string, mixed>
     */
    private function payloadForUserSource(User $user, string $memberTypeLabel = 'Benutzer'): array
    {
        $name = trim((string) (($user->last_name ?? '').' '.($user->first_name ?? '')));

        return [
            'member_provider' => UserGroupMember::PROVIDER_USER,
            'member_ref' => $this->userMemberRef((int) $user->id),
            'linked_user_id' => (int) $user->id,
            'source_schoolyear_id' => $user->schoolyear_id ? (int) $user->schoolyear_id : null,
            'display_name' => $name !== '' ? $name : ($user->email ?? 'Benutzer'),
            'display_email' => $user->email,
            'display_phone' => $user->phone,
            'display_schoolclass' => $user->schoolclass,
            'display_children_label' => null,
            'member_type_label' => $memberTypeLabel,
            'source_status' => UserGroupMember::SOURCE_STATUS_ACTIVE,
            'linked_user_status' => UserGroupMember::LINKED_USER_STATUS_LINKED,
            'meta' => [
                'user_id' => (int) $user->id,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function payloadForImportStudent(Import116 $import, ?User $linkedUser = null): array
    {
        $name = $linkedUser
            ? trim((string) (($linkedUser->last_name ?? '').' '.($linkedUser->first_name ?? '')))
            : trim((string) (($import->last_name ?? '').' '.($import->first_name ?? '')));
        $email = $linkedUser?->email ?: $import->email;
        $schoolclass = $linkedUser?->schoolclass ?: $import->class;

        return [
            'member_provider' => UserGroupMember::PROVIDER_IMPORT116_STUDENT,
            'member_ref' => $this->import116StudentMemberRef((int) $import->id),
            'linked_user_id' => $linkedUser ? (int) $linkedUser->id : null,
            'source_schoolyear_id' => $import->schoolyear_id ? (int) $import->schoolyear_id : null,
            'display_name' => $name !== '' ? $name : ($email ?: 'Schüler:in'),
            'display_email' => $email,
            'display_phone' => trim((string) implode(' / ', collect([
                trim((string) ($import->phone_1 ?? '')),
                trim((string) ($import->phone_2 ?? '')),
            ])->filter()->unique()->all())),
            'display_schoolclass' => $schoolclass,
            'display_children_label' => null,
            'member_type_label' => 'Schüler:in',
            'source_status' => UserGroupMember::SOURCE_STATUS_ACTIVE,
            'linked_user_status' => $linkedUser
                ? UserGroupMember::LINKED_USER_STATUS_LINKED
                : UserGroupMember::LINKED_USER_STATUS_NOT_APPLICABLE,
            'meta' => [
                'import116_id' => (int) $import->id,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function payloadForTeacherSource(Teacher $teacher, ?User $linkedUser = null): array
    {
        $teacherName = trim((string) (($teacher->last_name ?? '').' '.($teacher->first_name ?? '')));
        $userName = $linkedUser
            ? trim((string) (($linkedUser->last_name ?? '').' '.($linkedUser->first_name ?? '')))
            : '';

        return [
            'member_provider' => UserGroupMember::PROVIDER_TEACHER_LIST_TEACHER,
            'member_ref' => $this->teacherListTeacherMemberRef((int) $teacher->id),
            'linked_user_id' => $linkedUser ? (int) $linkedUser->id : null,
            'source_schoolyear_id' => null,
            'display_name' => $teacherName !== '' ? $teacherName : ($userName !== '' ? $userName : ($teacher->email ?? 'Lehrer:in')),
            'display_email' => $teacher->email ?: ($linkedUser?->email),
            'display_phone' => null,
            'display_schoolclass' => $linkedUser?->schoolclass,
            'display_children_label' => null,
            'member_type_label' => 'Lehrer:in',
            'source_status' => UserGroupMember::SOURCE_STATUS_ACTIVE,
            'linked_user_status' => $linkedUser
                ? UserGroupMember::LINKED_USER_STATUS_LINKED
                : UserGroupMember::LINKED_USER_STATUS_NOT_APPLICABLE,
            'meta' => [
                'teacher_id' => (int) $teacher->id,
            ],
        ];
    }

    /**
     * @param  array{name:string,email:string,phone:string,schoolclass?:string|null,children_label?:string|null,id?:string}  $contact
     * @return array<string, mixed>
     */
    private function payloadForParentContact(int $schoolId, int $schoolyearId, array $contact): array
    {
        $linkedUser = $this->linkedUserByEmail($schoolId, $contact['email'] ?? null);
        $contactKey = $this->parentContactKey([
            'name' => $contact['name'] ?? '',
            'email' => $contact['email'] ?? '',
            'phone' => $contact['phone'] ?? '',
        ]);

        return [
            'member_provider' => UserGroupMember::PROVIDER_IMPORT116_PARENT_CONTACT,
            'member_ref' => $this->import116ParentContactMemberRef($schoolyearId, $contactKey),
            'linked_user_id' => $linkedUser ? (int) $linkedUser->id : null,
            'source_schoolyear_id' => $schoolyearId,
            'display_name' => $contact['name'] ?? 'Erziehungsberechtigte:r',
            'display_email' => $contact['email'] ?? null,
            'display_phone' => $contact['phone'] ?? null,
            'display_schoolclass' => $contact['schoolclass'] ?? null,
            'display_children_label' => $contact['children_label'] ?? null,
            'member_type_label' => 'Eltern',
            'source_status' => UserGroupMember::SOURCE_STATUS_ACTIVE,
            'linked_user_status' => $linkedUser
                ? UserGroupMember::LINKED_USER_STATUS_LINKED
                : UserGroupMember::LINKED_USER_STATUS_NOT_APPLICABLE,
            'meta' => [
                'contact_key' => $contactKey,
            ],
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $payloads
     * @return array{assigned_count:int,new_count:int}
     */
    private function storeGroupMembers(UserGroup $group, Collection $payloads, int $actorUserId): array
    {
        $payloads = $payloads
            ->filter(fn (array $payload) => trim((string) ($payload['member_provider'] ?? '')) !== '' && trim((string) ($payload['member_ref'] ?? '')) !== '')
            ->keyBy(fn (array $payload) => $this->storedGroupMemberKeyFromPayload($payload));

        if ($payloads->isEmpty()) {
            return ['assigned_count' => 0, 'new_count' => 0];
        }

        $existingMembers = $group->groupMembers()
            ->get()
            ->keyBy(fn (UserGroupMember $member) => $this->storedGroupMemberKey((string) $member->member_provider, (string) $member->member_ref));

        $newCount = 0;
        foreach ($payloads as $storedKey => $payload) {
            $attributes = [
                'school_id' => (int) $group->school_id,
                'member_provider' => (string) $payload['member_provider'],
                'member_ref' => (string) $payload['member_ref'],
                'linked_user_id' => isset($payload['linked_user_id']) && $payload['linked_user_id'] !== null ? (int) $payload['linked_user_id'] : null,
                'source_schoolyear_id' => isset($payload['source_schoolyear_id']) && $payload['source_schoolyear_id'] !== null ? (int) $payload['source_schoolyear_id'] : null,
                'display_name' => $payload['display_name'] ?? null,
                'display_email' => $payload['display_email'] ?? null,
                'display_phone' => $payload['display_phone'] ?? null,
                'display_schoolclass' => $payload['display_schoolclass'] ?? null,
                'display_children_label' => $payload['display_children_label'] ?? null,
                'member_type_label' => $payload['member_type_label'] ?? null,
                'source_status' => $payload['source_status'] ?? UserGroupMember::SOURCE_STATUS_ACTIVE,
                'linked_user_status' => $payload['linked_user_status'] ?? UserGroupMember::LINKED_USER_STATUS_NOT_APPLICABLE,
                'meta' => $payload['meta'] ?? null,
                'added_by_user_id' => $actorUserId > 0 ? $actorUserId : null,
            ];

            /** @var UserGroupMember|null $existingMember */
            $existingMember = $existingMembers->get($storedKey);
            if ($existingMember) {
                $existingMember->fill($attributes);
                if ($existingMember->isDirty()) {
                    $existingMember->save();
                }
                continue;
            }

            $group->groupMembers()->create($attributes);
            $newCount++;
        }

        return [
            'assigned_count' => $payloads->count(),
            'new_count' => $newCount,
        ];
    }

    /**
     * @return Collection<string, UserGroupMember>
     */
    private function existingStoredGroupMembers(UserGroup $group): Collection
    {
        return $group->groupMembers()
            ->get()
            ->keyBy(fn (UserGroupMember $member) => $this->storedGroupMemberKey((string) $member->member_provider, (string) $member->member_ref));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  Collection<string, UserGroupMember>  $existingMembers
     * @return array<string, mixed>
     */
    private function serializeAssignableMemberPayload(array $payload, Collection $existingMembers): array
    {
        $storedKey = $this->storedGroupMemberKeyFromPayload($payload);
        /** @var UserGroupMember|null $existingMember */
        $existingMember = $existingMembers->get($storedKey);

        $provider = (string) ($payload['member_provider'] ?? '');
        $memberRef = (string) ($payload['member_ref'] ?? '');
        $import116Id = null;
        if ($provider === UserGroupMember::PROVIDER_IMPORT116_STUDENT) {
            $import116Id = (int) preg_replace('/[^0-9]/', '', $memberRef);
        }

        return [
            'id' => $memberRef,
            'user_id' => isset($payload['linked_user_id']) && $payload['linked_user_id'] !== null ? (int) $payload['linked_user_id'] : null,
            'linked_user_id' => isset($payload['linked_user_id']) && $payload['linked_user_id'] !== null ? (int) $payload['linked_user_id'] : null,
            'import116_id' => $import116Id,
            'name' => $payload['display_name'] ?? ($payload['display_email'] ?? 'Mitglied'),
            'email' => $payload['display_email'] ?? null,
            'schoolclass' => $payload['display_schoolclass'] ?? null,
            'phone' => $payload['display_phone'] ?? null,
            'children_label' => $payload['display_children_label'] ?? null,
            'member_type_label' => $payload['member_type_label'] ?? null,
            'member_provider' => $provider,
            'member_ref' => $memberRef,
            'has_user_account' => ($payload['linked_user_status'] ?? null) === UserGroupMember::LINKED_USER_STATUS_LINKED,
            'already_member' => $existingMember !== null,
            'assigned_member_id' => $existingMember ? (int) $existingMember->id : null,
            'status_label' => $existingMember ? $this->groupMemberStatusLabel($existingMember) : null,
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function assignableStudentPayloads(int $schoolId, string $search, int $limit): Collection
    {
        $query = $this->import116QueryForActiveSchoolyear($schoolId)
            ->orderByRaw('LOWER(class)')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->orderBy('email');

        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder->where('last_name', 'like', "%{$search}%")
                    ->orWhere('first_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('class', 'like', "%{$search}%");
            });
        }

        return $query
            ->limit($limit)
            ->get(['id', 'school_id', 'schoolyear_id', 'class', 'user_id', 'last_name', 'first_name', 'email', 'phone_1', 'phone_2'])
            ->map(fn (Import116 $import) => $this->payloadForImportStudent($import, $this->linkedUserForImportStudent($schoolId, $import)))
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function assignableTeacherPayloads(int $schoolId, string $search, int $limit): Collection
    {
        $teacherRows = Teacher::query()
            ->where('school_id', $schoolId)
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($builder) use ($search) {
                    $builder->where('last_name', 'like', "%{$search}%")
                        ->orWhere('first_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('short', 'like', "%{$search}%");
                });
            })
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->orderBy('email')
            ->limit($limit)
            ->get(['id', 'school_id', 'last_name', 'first_name', 'email', 'short']);

        $teacherPayloads = $teacherRows
            ->map(fn (Teacher $teacher) => $this->payloadForTeacherSource($teacher, $this->linkedUserByEmail($schoolId, $teacher->email)))
            ->values();

        $matchedTeacherEmails = $teacherRows
            ->map(fn (Teacher $teacher) => $this->normalizeEmail($teacher->email))
            ->filter(fn (string $email) => $email !== '')
            ->unique()
            ->values()
            ->all();

        $roleTeacherPayloads = User::query()
            ->where('school_id', $schoolId)
            ->whereHas('roles', fn ($query) => $query->where('name', 'teacher'))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($builder) use ($search) {
                    $builder->where('last_name', 'like', "%{$search}%")
                        ->orWhere('first_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->orderBy('email')
            ->limit($limit)
            ->get(['id', 'school_id', 'schoolyear_id', 'last_name', 'first_name', 'email', 'phone', 'schoolclass'])
            ->reject(fn (User $user) => in_array($this->normalizeEmail($user->email), $matchedTeacherEmails, true))
            ->map(fn (User $user) => $this->payloadForUserSource($user, 'Lehrer:in'))
            ->values();

        return $teacherPayloads
            ->concat($roleTeacherPayloads)
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function assignableParentPayloads(int $schoolId, string $search, int $limit): Collection
    {
        $query = $this->import116QueryForActiveSchoolyear($schoolId)
            ->orderByRaw('LOWER(class)')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->orderBy('email');

        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder->where('last_name', 'like', "%{$search}%")
                    ->orWhere('first_name', 'like', "%{$search}%")
                    ->orWhere('class', 'like', "%{$search}%")
                    ->orWhere('mother_name', 'like', "%{$search}%")
                    ->orWhere('mother_email', 'like', "%{$search}%")
                    ->orWhere('father_name', 'like', "%{$search}%")
                    ->orWhere('father_email', 'like', "%{$search}%");
            });
        }

        $rows = $query
            ->limit(max($limit * 3, $limit))
            ->get([
                'id',
                'schoolyear_id',
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
            ]);

        if ($rows->isEmpty()) {
            return collect();
        }

        $usersByImportId = User::query()
            ->where('school_id', $schoolId)
            ->whereNotNull('import116_id')
            ->whereIn('import116_id', $rows->pluck('id')->map(fn ($id) => (int) $id)->all())
            ->get(['id', 'import116_id'])
            ->keyBy(fn (User $user) => (int) $user->import116_id);

        $activeSchoolyearId = $this->activeImportSchoolyearId($schoolId);

        return $this->buildParentContacts($rows, $usersByImportId, false)
            ->map(fn (array $contact) => $this->payloadForParentContact($schoolId, $activeSchoolyearId ?? (int) ($rows->first()->schoolyear_id ?? 0), $contact))
            ->filter(fn (array $payload) => $this->searchMatchesPayload($payload, $search))
            ->take($limit)
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function assignableAdminPayloads(int $schoolId, string $search, int $limit): Collection
    {
        return User::query()
            ->where('school_id', $schoolId)
            ->whereHas('roles', fn ($query) => $query->whereIn('name', $this->adminRoleNames()))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($builder) use ($search) {
                    $builder->where('last_name', 'like', "%{$search}%")
                        ->orWhere('first_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->orderBy('email')
            ->limit($limit)
            ->get(['id', 'school_id', 'schoolyear_id', 'last_name', 'first_name', 'email', 'phone', 'schoolclass'])
            ->map(fn (User $user) => $this->payloadForUserSource($user, 'Admin'))
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function combinedAssignablePayloads(int $schoolId, string $search, int $limit): Collection
    {
        $seenLinkedUsers = [];
        $seenKeys = [];
        $merged = collect();

        foreach ([
            $this->assignableStudentPayloads($schoolId, $search, $limit),
            $this->assignableTeacherPayloads($schoolId, $search, $limit),
            $this->assignableParentPayloads($schoolId, $search, $limit),
            $this->assignableAdminPayloads($schoolId, $search, $limit),
        ] as $payloadCollection) {
            foreach ($payloadCollection as $payload) {
                $storedKey = $this->storedGroupMemberKeyFromPayload($payload);
                if (isset($seenKeys[$storedKey])) {
                    continue;
                }

                $linkedUserId = isset($payload['linked_user_id']) && $payload['linked_user_id'] !== null
                    ? (int) $payload['linked_user_id']
                    : 0;

                if ((string) ($payload['member_provider'] ?? '') === UserGroupMember::PROVIDER_USER && $linkedUserId > 0 && isset($seenLinkedUsers[$linkedUserId])) {
                    continue;
                }

                $seenKeys[$storedKey] = true;
                if ($linkedUserId > 0) {
                    $seenLinkedUsers[$linkedUserId] = true;
                }
                $merged->push($payload);
            }
        }

        return $merged->take($limit)->values();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function searchMatchesPayload(array $payload, string $search): bool
    {
        $search = trim($search);
        if ($search === '') {
            return true;
        }

        $needle = mb_strtolower($search);
        $haystacks = [
            $payload['display_name'] ?? '',
            $payload['display_email'] ?? '',
            $payload['display_schoolclass'] ?? '',
            $payload['display_children_label'] ?? '',
            $payload['member_type_label'] ?? '',
        ];

        foreach ($haystacks as $haystack) {
            if (str_contains(mb_strtolower((string) $haystack), $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $payloads
     */
    private function syncGroupMembersFromPayloads(UserGroup $group, Collection $payloads, int $actorUserId): void
    {
        $payloads = $payloads
            ->filter(fn (array $payload) => trim((string) ($payload['member_provider'] ?? '')) !== '' && trim((string) ($payload['member_ref'] ?? '')) !== '')
            ->values();

        $this->storeGroupMembers($group, $payloads, $actorUserId);

        $validKeys = $payloads
            ->map(fn (array $payload) => $this->storedGroupMemberKeyFromPayload($payload))
            ->unique()
            ->values()
            ->all();

        $staleIds = $group->groupMembers()
            ->get()
            ->reject(fn (UserGroupMember $member) => in_array($this->storedGroupMemberKey((string) $member->member_provider, (string) $member->member_ref), $validKeys, true))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (! empty($staleIds)) {
            $group->groupMembers()->whereIn('id', $staleIds)->delete();
        }
    }

    /**
     * @return Collection<int, UserGroupMember>
     */
    private function refreshGroupMembersSyncState(UserGroup $group, int $schoolId): Collection
    {
        $members = $group->groupMembers()->get();

        foreach ($members as $member) {
            $this->refreshSingleGroupMemberSyncState($member, $schoolId);
        }

        return $members;
    }

    private function refreshSingleGroupMemberSyncState(UserGroupMember $member, int $schoolId): void
    {
        $resolvedPayload = $this->resolveStoredMemberPayload($member, $schoolId);

        if ($resolvedPayload) {
            $member->fill([
                'linked_user_id' => $resolvedPayload['linked_user_id'] ?? null,
                'source_schoolyear_id' => $resolvedPayload['source_schoolyear_id'] ?? null,
                'display_name' => $resolvedPayload['display_name'] ?? $member->display_name,
                'display_email' => $resolvedPayload['display_email'] ?? $member->display_email,
                'display_phone' => $resolvedPayload['display_phone'] ?? $member->display_phone,
                'display_schoolclass' => $resolvedPayload['display_schoolclass'] ?? $member->display_schoolclass,
                'display_children_label' => $resolvedPayload['display_children_label'] ?? $member->display_children_label,
                'member_type_label' => $resolvedPayload['member_type_label'] ?? $member->member_type_label,
                'source_status' => $resolvedPayload['source_status'] ?? UserGroupMember::SOURCE_STATUS_ACTIVE,
                'linked_user_status' => $resolvedPayload['linked_user_status'] ?? UserGroupMember::LINKED_USER_STATUS_NOT_APPLICABLE,
                'meta' => $resolvedPayload['meta'] ?? $member->meta,
            ]);
        } else {
            $member->source_status = UserGroupMember::SOURCE_STATUS_MISSING;
            if ($member->linked_user_id) {
                $linkedUserExists = User::query()
                    ->where('school_id', $schoolId)
                    ->where('id', (int) $member->linked_user_id)
                    ->exists();
                $member->linked_user_status = $linkedUserExists
                    ? UserGroupMember::LINKED_USER_STATUS_LINKED
                    : UserGroupMember::LINKED_USER_STATUS_MISSING;
            } else {
                $member->linked_user_status = UserGroupMember::LINKED_USER_STATUS_NOT_APPLICABLE;
            }
        }

        if ($member->isDirty()) {
            $member->save();
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveStoredMemberPayload(UserGroupMember $member, int $schoolId): ?array
    {
        return match ((string) $member->member_provider) {
            UserGroupMember::PROVIDER_USER => $this->resolveUserStoredPayload($member, $schoolId),
            UserGroupMember::PROVIDER_IMPORT116_STUDENT => $this->resolveImportStudentStoredPayload($member, $schoolId),
            UserGroupMember::PROVIDER_TEACHER_LIST_TEACHER => $this->resolveTeacherStoredPayload($member, $schoolId),
            UserGroupMember::PROVIDER_IMPORT116_PARENT_CONTACT => $this->resolveParentContactStoredPayload($member, $schoolId),
            default => null,
        };
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveStoredMemberPayloadByProviderAndRef(string $provider, string $memberRef, int $schoolId): ?array
    {
        $member = new UserGroupMember([
            'member_provider' => $provider,
            'member_ref' => $memberRef,
        ]);

        return $this->resolveStoredMemberPayload($member, $schoolId);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveUserStoredPayload(UserGroupMember $member, int $schoolId): ?array
    {
        $userId = (int) preg_replace('/[^0-9]/', '', (string) $member->member_ref);
        if ($userId <= 0) {
            return null;
        }

        $user = User::query()
            ->where('school_id', $schoolId)
            ->where('id', $userId)
            ->first(['id', 'school_id', 'schoolyear_id', 'last_name', 'first_name', 'email', 'phone', 'schoolclass']);

        return $user ? $this->payloadForUserSource($user, (string) ($member->member_type_label ?: 'Benutzer')) : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveImportStudentStoredPayload(UserGroupMember $member, int $schoolId): ?array
    {
        $importId = (int) preg_replace('/[^0-9]/', '', (string) $member->member_ref);
        if ($importId <= 0) {
            return null;
        }

        $import = Import116::query()
            ->where('school_id', $schoolId)
            ->where('id', $importId)
            ->first(['id', 'school_id', 'schoolyear_id', 'class', 'user_id', 'last_name', 'first_name', 'email', 'phone_1', 'phone_2']);
        if (! $import) {
            return null;
        }

        $payload = $this->payloadForImportStudent($import, $this->linkedUserForImportStudent($schoolId, $import));
        $activeSchoolyearId = $this->activeImportSchoolyearId($schoolId);
        if ($activeSchoolyearId && (int) $import->schoolyear_id !== $activeSchoolyearId) {
            $payload['source_status'] = UserGroupMember::SOURCE_STATUS_OUT_OF_SCOPE;
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveTeacherStoredPayload(UserGroupMember $member, int $schoolId): ?array
    {
        $teacherId = (int) preg_replace('/[^0-9]/', '', (string) $member->member_ref);
        if ($teacherId <= 0) {
            return null;
        }

        $teacher = Teacher::query()
            ->where('school_id', $schoolId)
            ->where('id', $teacherId)
            ->first(['id', 'school_id', 'last_name', 'first_name', 'email', 'short']);

        return $teacher ? $this->payloadForTeacherSource($teacher, $this->linkedUserByEmail($schoolId, $teacher->email)) : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveParentContactStoredPayload(UserGroupMember $member, int $schoolId): ?array
    {
        $parts = explode(':', (string) $member->member_ref, 4);
        if (count($parts) !== 4) {
            return null;
        }

        $schoolyearId = (int) ($parts[2] ?? 0);
        $contactKey = base64_decode((string) ($parts[3] ?? ''), true);
        if ($schoolyearId <= 0 || ! is_string($contactKey) || $contactKey === '') {
            return null;
        }

        $payload = $this->parentContactPayloadForSchoolyearAndKey($schoolId, $schoolyearId, $contactKey);
        if (! $payload) {
            return null;
        }

        $activeSchoolyearId = $this->activeImportSchoolyearId($schoolId);
        if ($activeSchoolyearId && $activeSchoolyearId !== $schoolyearId) {
            $payload['source_status'] = UserGroupMember::SOURCE_STATUS_OUT_OF_SCOPE;
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function parentContactPayloadForSchoolyearAndKey(int $schoolId, int $schoolyearId, string $contactKey): ?array
    {
        if (! Schema::hasTable('import116')) {
            return null;
        }

        $rows = Import116::query()
            ->where('school_id', $schoolId)
            ->where('schoolyear_id', $schoolyearId)
            ->get([
                'id',
                'schoolyear_id',
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
            ]);

        if ($rows->isEmpty()) {
            return null;
        }

        $importIds = $rows->pluck('id')->map(fn ($id) => (int) $id)->all();
        $usersByImportId = User::query()
            ->where('school_id', $schoolId)
            ->whereNotNull('import116_id')
            ->whereIn('import116_id', $importIds)
            ->get(['id', 'import116_id'])
            ->keyBy(fn (User $user) => (int) $user->import116_id);

        $contact = $this->buildParentContacts($rows, $usersByImportId, false)
            ->first(fn (array $row) => $this->parentContactKey([
                'name' => $row['name'] ?? '',
                'email' => $row['email'] ?? '',
                'phone' => $row['phone'] ?? '',
            ]) === $contactKey);

        return $contact ? $this->payloadForParentContact($schoolId, $schoolyearId, $contact) : null;
    }

    private function groupMemberStatusLabel(UserGroupMember $member): ?string
    {
        if ((string) $member->source_status === UserGroupMember::SOURCE_STATUS_OUT_OF_SCOPE) {
            return 'Nicht im aktiven Schuljahr';
        }
        if ((string) $member->source_status === UserGroupMember::SOURCE_STATUS_MISSING) {
            return 'Quelle fehlt';
        }
        if ((string) $member->linked_user_status === UserGroupMember::LINKED_USER_STATUS_MISSING) {
            return 'Benutzerkonto fehlt';
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeStoredGroupMember(UserGroupMember $member): array
    {
        $provider = (string) $member->member_provider;
        $memberRef = (string) $member->member_ref;
        $statusLabel = $this->groupMemberStatusLabel($member);

        $import116Id = null;
        if ($provider === UserGroupMember::PROVIDER_IMPORT116_STUDENT) {
            $import116Id = (int) preg_replace('/[^0-9]/', '', $memberRef);
        }

        return [
            'id' => (int) $member->id,
            'user_id' => $member->linked_user_id ? (int) $member->linked_user_id : null,
            'linked_user_id' => $member->linked_user_id ? (int) $member->linked_user_id : null,
            'import116_id' => $import116Id,
            'name' => (string) ($member->display_name ?: ($member->display_email ?: 'Mitglied')),
            'email' => $member->display_email,
            'schoolclass' => $member->display_schoolclass,
            'phone' => $member->display_phone,
            'children_label' => $member->display_children_label,
            'member_type_label' => $member->member_type_label,
            'member_provider' => $provider,
            'member_ref' => $memberRef,
            'has_user_account' => (string) $member->linked_user_status === UserGroupMember::LINKED_USER_STATUS_LINKED,
            'source_status' => (string) $member->source_status,
            'linked_user_status' => (string) $member->linked_user_status,
            'is_missing' => $statusLabel !== null,
            'status_label' => $statusLabel,
        ];
    }
}
