<?php

namespace App\Http\Controllers\Admin\Materials;

use App\Http\Controllers\Controller;
use App\Models\MaterialCard;
use App\Models\MaterialShareRule;
use App\Models\MaterialShareTarget;
use App\Models\MaterialSubject;
use App\Models\MaterialTopic;
use App\Models\MaterialUnit;
use App\Models\UserGroup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class MaterialShareController extends Controller
{
    public function index(Request $request)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'materials_admin', 'materials_moderator'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        if (! Schema::hasTable('material_share_rules') || ! Schema::hasTable('material_share_targets')) {
            return response()->json([
                'data' => [],
                'meta' => [
                    'needs_migration' => true,
                    'total' => 0,
                ],
            ]);
        }

        $schoolId = (int) $auth_user->school_id;

        $rules = MaterialShareRule::query()
            ->where('school_id', $schoolId)
            ->with([
                'creator:id,first_name,last_name,email',
                'targets',
                'targets.group:id,school_id,type,name',
                'targets.user:id,school_id,first_name,last_name,email',
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
        if ($target->target_type === MaterialShareTarget::TARGET_EVERYONE) {
            return [
                'id' => (int) $target->id,
                'target_type' => MaterialShareTarget::TARGET_EVERYONE,
                'label' => 'Alle',
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
                'label' => $group->name,
                'meta' => [
                    'group_type' => (string) $group->type,
                    'group_type_label' => $this->groupTypeLabel((string) $group->type),
                ],
            ];
        }

        if ($target->target_type === MaterialShareTarget::TARGET_USER) {
            $user = $target->user;
            if (! $user || (int) $user->school_id !== $schoolId) {
                return null;
            }

            $fullName = trim((string) (($user->last_name ?? '').' '.($user->first_name ?? '')));
            return [
                'id' => (int) $target->id,
                'target_type' => MaterialShareTarget::TARGET_USER,
                'user_id' => (int) $user->id,
                'label' => $fullName !== '' ? $fullName : ($user->email ?? 'Benutzer'),
                'meta' => [
                    'email' => $user->email,
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
}
