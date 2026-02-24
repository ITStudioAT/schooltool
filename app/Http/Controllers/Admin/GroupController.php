<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
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
}
