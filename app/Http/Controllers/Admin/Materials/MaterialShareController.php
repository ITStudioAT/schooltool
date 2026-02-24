<?php

namespace App\Http\Controllers\Admin\Materials;

use App\Http\Controllers\Controller;
use App\Models\MaterialCard;
use App\Models\MaterialShareRule;
use App\Models\MaterialShareTarget;
use App\Models\MaterialSubject;
use App\Models\MaterialTopic;
use App\Models\MaterialUnit;
use App\Models\School;
use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MaterialShareController extends Controller
{
    public function index(Request $request)
    {
        $authUser = $this->materialsShareUser();

        if (! $this->shareTablesAvailable()) {
            return response()->json([
                'data' => [],
                'meta' => [
                    'needs_migration' => true,
                    'total' => 0,
                ],
            ]);
        }

        $schoolId = (int) $authUser->school_id;
        $scopeTypeFilter = (string) $request->query('scope_type', '');
        $scopeIdFilter = $request->query('scope_id');
        $scopeIdFilter = Number_format((float) $scopeIdFilter, 0, '', '') === (string) $scopeIdFilter
            ? (int) $scopeIdFilter
            : null;

        $rules = MaterialShareRule::query()
            ->where('school_id', $schoolId)
            ->when($scopeTypeFilter !== '', fn ($query) => $query->where('scope_type', $scopeTypeFilter))
            ->when($scopeTypeFilter !== '' && $scopeIdFilter !== null, fn ($query) => $query->where('scope_id', $scopeIdFilter))
            ->with([
                'creator:id,first_name,last_name,email',
                'targets',
                'targets.group:id,school_id,type,name,created_by_user_id',
                'targets.user:id,school_id,first_name,last_name,email',
                'targets.user.selectedSchool:id,long_name,short_name',
            ])
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'data' => $rules->map(fn (MaterialShareRule $rule) => $this->serializeRule($rule, $schoolId))->values(),
            'meta' => [
                'needs_migration' => false,
                'total' => $rules->count(),
                'active_count' => $rules->where('is_active', true)->count(),
            ],
        ]);
    }

    public function lookupUsers(Request $request)
    {
        $authUser = $this->materialsShareUser();
        $this->abortIfShareTablesMissing();

        $search = trim((string) $request->query('search', ''));
        if ($search === '') {
            return response()->json(['data' => []]);
        }

        $tokens = preg_split('/\s+/', $search) ?: [];

        $rows = User::query()
            ->where('school_id', (int) $authUser->school_id)
            ->where(function ($query) use ($search, $tokens) {
                $like = '%'.$search.'%';
                $query
                    ->where('email', 'like', $like)
                    ->orWhere('first_name', 'like', $like)
                    ->orWhere('last_name', 'like', $like);

                foreach ($tokens as $token) {
                    $token = trim((string) $token);
                    if ($token === '') {
                        continue;
                    }
                    $query->orWhere('first_name', 'like', '%'.$token.'%')
                        ->orWhere('last_name', 'like', '%'.$token.'%');
                }
            })
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->limit(25)
            ->get(['id', 'first_name', 'last_name', 'email']);

        return response()->json([
            'data' => $rows->map(function (User $user) {
                $fullName = trim((string) (($user->last_name ?? '').' '.($user->first_name ?? '')));
                return [
                    'id' => (int) $user->id,
                    'label' => $fullName !== '' ? $fullName : ($user->email ?: 'Benutzer'),
                    'first_name' => (string) ($user->first_name ?? ''),
                    'last_name' => (string) ($user->last_name ?? ''),
                    'email' => (string) ($user->email ?? ''),
                ];
            })->values(),
        ]);
    }

    public function lookupGroups(Request $request)
    {
        $authUser = $this->materialsShareUser();
        $this->abortIfShareTablesMissing();

        $type = (string) $request->query('type', '');
        if (! in_array($type, [UserGroup::TYPE_MATERIALS, UserGroup::TYPE_OWN], true)) {
            throw ValidationException::withMessages([
                'type' => ['Ungültiger Gruppentyp.'],
            ]);
        }

        $groups = UserGroup::query()
            ->where('school_id', (int) $authUser->school_id)
            ->where('type', $type)
            ->when($type === UserGroup::TYPE_OWN, fn ($query) => $query->where('created_by_user_id', (int) $authUser->id))
            ->withCount('members')
            ->orderBy('name')
            ->get(['id', 'school_id', 'type', 'name', 'description', 'created_by_user_id']);

        return response()->json([
            'data' => $groups->map(fn (UserGroup $group) => [
                'id' => (int) $group->id,
                'type' => (string) $group->type,
                'type_label' => $this->groupTypeLabel((string) $group->type),
                'name' => (string) $group->name,
                'description' => (string) ($group->description ?? ''),
                'members_count' => (int) ($group->members_count ?? 0),
                'label' => (string) $group->name,
            ])->values(),
        ]);
    }

    public function lookupSchools(Request $request)
    {
        $authUser = $this->materialsShareUser();
        $this->abortIfShareTablesMissing();

        $schools = School::query()
            ->selectables()
            ->where('id', '!=', (int) $authUser->school_id)
            ->get(['id', 'long_name', 'short_name']);

        return response()->json([
            'data' => $schools->map(function (School $school) {
                $label = trim((string) ($school->long_name ?: $school->short_name ?: 'Schule'));
                return [
                    'id' => (int) $school->id,
                    'label' => $label,
                    'long_name' => (string) ($school->long_name ?? ''),
                    'short_name' => (string) ($school->short_name ?? ''),
                ];
            })->values(),
        ]);
    }

    public function storeTarget(Request $request)
    {
        $authUser = $this->materialsShareUser();
        $this->abortIfShareTablesMissing();

        $data = $request->validate([
            'scope_type' => ['required', 'string', Rule::in(MaterialShareRule::SCOPES)],
            'scope_id' => ['nullable', 'integer', 'min:1'],
            'target_type' => ['required', 'string', Rule::in(MaterialShareTarget::TARGETS)],
            'audience_scope' => ['nullable', 'string', Rule::in(MaterialShareTarget::AUDIENCE_SCOPES)],
            'permission' => ['required', 'string', Rule::in(MaterialShareTarget::PERMISSIONS)],
            'user_id' => ['nullable', 'integer', 'min:1'],
            'target_school_id' => ['nullable', 'integer', 'min:1'],
            'user_email' => ['nullable', 'string', 'email'],
            'user_group_id' => ['nullable', 'integer', 'min:1'],
        ]);

        $scopeType = (string) $data['scope_type'];
        $scopeId = $scopeType === MaterialShareRule::SCOPE_ALL ? null : (int) ($data['scope_id'] ?? 0);
        if ($scopeType !== MaterialShareRule::SCOPE_ALL && $scopeId <= 0) {
            throw ValidationException::withMessages([
                'scope_id' => ['Für diese Ebene ist eine gültige ID erforderlich.'],
            ]);
        }

        $targetType = (string) $data['target_type'];
        $audienceScope = null;
        $userId = null;
        $groupId = null;

        if ($targetType === MaterialShareTarget::TARGET_EVERYONE) {
            $audienceScope = (string) ($data['audience_scope'] ?? '');
            if (! in_array($audienceScope, MaterialShareTarget::AUDIENCE_SCOPES, true)) {
                throw ValidationException::withMessages([
                    'audience_scope' => ['Bitte Reichweite für "Jeder" wählen.'],
                ]);
            }
        } elseif ($targetType === MaterialShareTarget::TARGET_USER) {
            $userId = (int) ($data['user_id'] ?? 0);
            $user = null;
            if ($userId > 0) {
                $user = User::query()
                    ->where('school_id', (int) $authUser->school_id)
                    ->find($userId);
            } else {
                $targetSchoolId = (int) ($data['target_school_id'] ?? 0);
                $userEmail = trim((string) ($data['user_email'] ?? ''));

                if ($targetSchoolId <= 0) {
                    throw ValidationException::withMessages([
                        'target_school_id' => ['Bitte Schule wählen.'],
                    ]);
                }
                if ($userEmail === '') {
                    throw ValidationException::withMessages([
                        'user_email' => ['Bitte E-Mail-Adresse eingeben.'],
                    ]);
                }

                $school = School::query()->find($targetSchoolId);
                if (! $school) {
                    throw ValidationException::withMessages([
                        'target_school_id' => ['Schule wurde nicht gefunden.'],
                    ]);
                }

                $user = User::query()
                    ->where('school_id', $targetSchoolId)
                    ->whereRaw('LOWER(email) = ?', [mb_strtolower($userEmail)])
                    ->first();
            }

            if (! $user) {
                throw ValidationException::withMessages([
                    'user_id' => ['Benutzer wurde nicht gefunden.'],
                ]);
            }
            $userId = (int) $user->id;
        } elseif ($targetType === MaterialShareTarget::TARGET_GROUP) {
            $groupId = (int) ($data['user_group_id'] ?? 0);
            $group = UserGroup::query()
                ->where('school_id', (int) $authUser->school_id)
                ->whereIn('type', [UserGroup::TYPE_MATERIALS, UserGroup::TYPE_OWN])
                ->find($groupId);
            if (! $group) {
                throw ValidationException::withMessages([
                    'user_group_id' => ['Gruppe wurde nicht gefunden.'],
                ]);
            }
            if ((string) $group->type === UserGroup::TYPE_OWN && (int) $group->created_by_user_id !== (int) $authUser->id) {
                abort(403, 'Eigene Gruppen können nur vom Ersteller verwendet werden.');
            }
        }

        $rule = MaterialShareRule::query()
            ->where('school_id', (int) $authUser->school_id)
            ->where('scope_type', $scopeType)
            ->where('scope_id', $scopeId)
            ->orderByDesc('id')
            ->first();

        if (! $rule) {
            $rule = MaterialShareRule::create([
                'school_id' => (int) $authUser->school_id,
                'created_by_user_id' => (int) $authUser->id,
                'scope_type' => $scopeType,
                'scope_id' => $scopeId,
                'is_active' => true,
            ]);
        } else {
            if (! $rule->is_active) {
                $rule->is_active = true;
                $rule->save();
            }
        }

        $target = $this->findExistingTargetForScope(
            schoolId: (int) $authUser->school_id,
            scopeType: $scopeType,
            scopeId: $scopeId,
            targetType: $targetType,
            audienceScope: $audienceScope,
            userId: $userId,
            groupId: $groupId,
        );

        if (! $target) {
            $target = new MaterialShareTarget();
        }

        $target->material_share_rule_id = (int) $rule->id;
        $target->target_type = $targetType;
        $target->audience_scope = $audienceScope;
        $target->permission = (string) $data['permission'];
        $target->user_id = $targetType === MaterialShareTarget::TARGET_USER ? $userId : null;
        $target->user_group_id = $targetType === MaterialShareTarget::TARGET_GROUP ? $groupId : null;
        $target->save();

        $rule->refresh()->load([
            'creator:id,first_name,last_name,email',
            'targets',
            'targets.group:id,school_id,type,name,created_by_user_id',
            'targets.user:id,school_id,first_name,last_name,email',
            'targets.user.selectedSchool:id,long_name,short_name',
        ]);

        return response()->json([
            'message' => 'Freigabe gespeichert.',
            'rule' => $this->serializeRule($rule, (int) $authUser->school_id),
            'target_id' => (int) $target->id,
        ]);
    }

    public function destroyTarget(Request $request, MaterialShareTarget $material_share_target)
    {
        $authUser = $this->materialsShareUser();
        $this->abortIfShareTablesMissing();

        $material_share_target->loadMissing('rule');
        $rule = $material_share_target->rule;

        if (! $rule || (int) $rule->school_id !== (int) $authUser->school_id) {
            abort(404, 'Freigabe-Ziel nicht gefunden.');
        }

        $ruleId = (int) $rule->id;
        $material_share_target->delete();

        $hasTargets = MaterialShareTarget::query()
            ->where('material_share_rule_id', $ruleId)
            ->exists();

        if (! $hasTargets) {
            MaterialShareRule::query()->whereKey($ruleId)->delete();
        }

        return response()->json([
            'message' => 'Freigabe entfernt.',
        ]);
    }

    public function updateRule(Request $request, MaterialShareRule $material_share_rule)
    {
        $authUser = $this->materialsShareUser();
        $this->abortIfShareTablesMissing();

        if ((int) $material_share_rule->school_id !== (int) $authUser->school_id) {
            abort(404, 'Freigabe nicht gefunden.');
        }

        $data = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $material_share_rule->is_active = (bool) $data['is_active'];
        $material_share_rule->save();

        $material_share_rule->load([
            'creator:id,first_name,last_name,email',
            'targets',
            'targets.group:id,school_id,type,name,created_by_user_id',
            'targets.user:id,school_id,first_name,last_name,email',
            'targets.user.selectedSchool:id,long_name,short_name',
        ]);

        return response()->json([
            'message' => 'Freigabe-Status gespeichert.',
            'rule' => $this->serializeRule($material_share_rule, (int) $authUser->school_id),
        ]);
    }

    private function materialsShareUser()
    {
        if (! $authUser = $this->userHasRole(['admin', 'materials_admin', 'materials_moderator'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        return $authUser;
    }

    private function shareTablesAvailable(): bool
    {
        return Schema::hasTable('material_share_rules') && Schema::hasTable('material_share_targets');
    }

    private function abortIfShareTablesMissing(): void
    {
        if ($this->shareTablesAvailable()) {
            return;
        }

        abort(409, 'Freigaben-Tabellen fehlen. Bitte Migration ausführen.');
    }

    private function findExistingTargetForScope(
        int $schoolId,
        string $scopeType,
        ?int $scopeId,
        string $targetType,
        ?string $audienceScope,
        ?int $userId,
        ?int $groupId,
    ): ?MaterialShareTarget {
        return MaterialShareTarget::query()
            ->where('target_type', $targetType)
            ->when($targetType === MaterialShareTarget::TARGET_EVERYONE, fn ($query) => $query->where('audience_scope', $audienceScope))
            ->when($targetType === MaterialShareTarget::TARGET_USER, fn ($query) => $query->where('user_id', $userId))
            ->when($targetType === MaterialShareTarget::TARGET_GROUP, fn ($query) => $query->where('user_group_id', $groupId))
            ->whereHas('rule', function ($query) use ($schoolId, $scopeType, $scopeId) {
                $query
                    ->where('school_id', $schoolId)
                    ->where('scope_type', $scopeType)
                    ->where('scope_id', $scopeId);
            })
            ->orderByDesc('id')
            ->first();
    }

    private function serializeRule(MaterialShareRule $rule, int $schoolId): array
    {
        [$scopeLabel, $scopeObjectLabel] = $this->resolveScopeLabels($rule, $schoolId);

        $targets = $rule->targets
            ->map(fn (MaterialShareTarget $target) => $this->serializeTarget($target, $schoolId))
            ->filter()
            ->values();

        $creatorName = trim((string) (($rule->creator?->last_name ?? '').' '.($rule->creator?->first_name ?? '')));
        $creatorLabel = $creatorName !== '' ? $creatorName : ($rule->creator?->email ?: null);

        return [
            'id' => (int) $rule->id,
            'scope_type' => (string) $rule->scope_type,
            'scope_id' => $rule->scope_id ? (int) $rule->scope_id : null,
            'scope_label' => $scopeLabel,
            'scope_object_label' => $scopeObjectLabel,
            'is_active' => (bool) $rule->is_active,
            'targets' => $targets,
            'targets_count' => $targets->count(),
            'created_by_user_id' => $rule->created_by_user_id ? (int) $rule->created_by_user_id : null,
            'created_by_label' => $creatorLabel,
            'created_at' => optional($rule->created_at)?->toIso8601String(),
            'updated_at' => optional($rule->updated_at)?->toIso8601String(),
        ];
    }

    private function serializeTarget(MaterialShareTarget $target, int $schoolId): ?array
    {
        $permission = (string) ($target->permission ?: MaterialShareTarget::PERMISSION_READ_ONLY);

        if ($target->target_type === MaterialShareTarget::TARGET_EVERYONE) {
            $audienceScope = (string) ($target->audience_scope ?: MaterialShareTarget::AUDIENCE_SCOPE_SCHOOL);
            return [
                'id' => (int) $target->id,
                'target_type' => MaterialShareTarget::TARGET_EVERYONE,
                'audience_scope' => $audienceScope,
                'audience_scope_label' => $this->audienceScopeLabel($audienceScope),
                'permission' => $permission,
                'permission_label' => $this->permissionLabel($permission),
                'label' => $this->audienceScopeLabel($audienceScope),
            ];
        }

        if ($target->target_type === MaterialShareTarget::TARGET_GROUP) {
            $group = $target->group;
            if (! $group || (int) $group->school_id !== $schoolId) {
                return null;
            }

            return [
                'id' => (int) $target->id,
                'target_type' => MaterialShareTarget::TARGET_GROUP,
                'user_group_id' => (int) $group->id,
                'permission' => $permission,
                'permission_label' => $this->permissionLabel($permission),
                'label' => $group->name,
                'meta' => [
                    'group_type' => (string) $group->type,
                    'group_type_label' => $this->groupTypeLabel((string) $group->type),
                ],
            ];
        }

        if ($target->target_type === MaterialShareTarget::TARGET_USER) {
            $user = $target->user;
            if (! $user) {
                return null;
            }

            $fullName = trim((string) (($user->last_name ?? '').' '.($user->first_name ?? '')));
            $targetSchoolId = (int) ($user->school_id ?? 0);
            $targetSchoolLabel = trim((string) ($user->selectedSchool?->long_name ?: $user->selectedSchool?->short_name ?: ''));
            return [
                'id' => (int) $target->id,
                'target_type' => MaterialShareTarget::TARGET_USER,
                'user_id' => (int) $user->id,
                'permission' => $permission,
                'permission_label' => $this->permissionLabel($permission),
                'label' => $fullName !== '' ? $fullName : ($user->email ?? 'Benutzer'),
                'meta' => [
                    'email' => $user->email,
                    'school_id' => $targetSchoolId,
                    'school_label' => $targetSchoolLabel !== '' ? $targetSchoolLabel : null,
                    'is_other_school' => $targetSchoolId > 0 && $targetSchoolId !== $schoolId,
                ],
            ];
        }

        return null;
    }

    /**
     * @return array{0:string,1:string}
     */
    private function resolveScopeLabels(MaterialShareRule $rule, int $schoolId): array
    {
        $scopeType = (string) $rule->scope_type;
        $scopeId = $rule->scope_id ? (int) $rule->scope_id : null;

        if ($scopeType === MaterialShareRule::SCOPE_ALL) {
            return ['Alles', 'Alle Materialien'];
        }

        if (! $scopeId) {
            return [$this->scopeTypeLabel($scopeType), 'Unbekannt'];
        }

        return match ($scopeType) {
            MaterialShareRule::SCOPE_SUBJECT => [
                'Fach',
                MaterialSubject::query()->find($scopeId)?->name ?: 'Fach #'.$scopeId,
            ],
            MaterialShareRule::SCOPE_TOPIC => [
                'Thema',
                MaterialTopic::query()->find($scopeId)?->name ?: 'Thema #'.$scopeId,
            ],
            MaterialShareRule::SCOPE_UNIT => [
                'Einheit',
                MaterialUnit::query()->find($scopeId)?->name ?: 'Einheit #'.$scopeId,
            ],
            MaterialShareRule::SCOPE_MATERIAL => [
                'Material',
                MaterialCard::query()
                    ->where('school_id', $schoolId)
                    ->find($scopeId)?->title ?: 'Material #'.$scopeId,
            ],
            default => [$this->scopeTypeLabel($scopeType), '#'.$scopeId],
        };
    }

    private function scopeTypeLabel(string $scopeType): string
    {
        return match ($scopeType) {
            MaterialShareRule::SCOPE_ALL => 'Alles',
            MaterialShareRule::SCOPE_SUBJECT => 'Fach',
            MaterialShareRule::SCOPE_TOPIC => 'Thema',
            MaterialShareRule::SCOPE_UNIT => 'Einheit',
            MaterialShareRule::SCOPE_MATERIAL => 'Material',
            default => $scopeType,
        };
    }

    private function groupTypeLabel(string $groupType): string
    {
        return match ($groupType) {
            UserGroup::TYPE_MATERIALS => 'Materialiengruppe',
            UserGroup::TYPE_OWN => 'Eigene Gruppe',
            UserGroup::TYPE_SCHOOL => 'Schulgruppe',
            default => $groupType,
        };
    }

    private function permissionLabel(string $permission): string
    {
        return match ($permission) {
            MaterialShareTarget::PERMISSION_FULL_ACCESS => 'Vollzugriff',
            MaterialShareTarget::PERMISSION_READ_WRITE => 'Lesen/Schreiben',
            MaterialShareTarget::PERMISSION_READ_ONLY => 'Nur Lesen',
            default => $permission,
        };
    }

    private function audienceScopeLabel(string $scope): string
    {
        return match ($scope) {
            MaterialShareTarget::AUDIENCE_SCOPE_GLOBAL => 'Jeder (auch schulfremd)',
            MaterialShareTarget::AUDIENCE_SCOPE_SCHOOL => 'Nur Schulweit',
            default => 'Alle',
        };
    }
}
