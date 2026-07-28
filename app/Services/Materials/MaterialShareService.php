<?php

namespace App\Services\Materials;

use App\Models\MaterialShareRule;
use App\Models\MaterialShareTarget;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class MaterialShareService
{
    /**
     * @return array{rule: MaterialShareRule, target: MaterialShareTarget}
     */
    public function storeTarget(
        User $actor,
        int $workspaceId,
        string $scopeType,
        ?int $scopeId,
        string $targetType,
        ?string $audienceScope,
        ?int $userId,
        ?int $groupId,
        string $permission,
    ): array {
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            try {
                return $this->storeTargetOnce(
                    $actor,
                    $workspaceId,
                    $scopeType,
                    $scopeId,
                    $targetType,
                    $audienceScope,
                    $userId,
                    $groupId,
                    $permission,
                );
            } catch (UniqueConstraintViolationException $exception) {
                if ($attempt === 3) {
                    throw $exception;
                }
            }
        }

        throw new \LogicException('Material share target persistence retry was exhausted.');
    }

    /**
     * @return array{rule: MaterialShareRule, target: MaterialShareTarget}
     */
    private function storeTargetOnce(
        User $actor,
        int $workspaceId,
        string $scopeType,
        ?int $scopeId,
        string $targetType,
        ?string $audienceScope,
        ?int $userId,
        ?int $groupId,
        string $permission,
    ): array {
        return DB::transaction(function () use (
            $actor,
            $workspaceId,
            $scopeType,
            $scopeId,
            $targetType,
            $audienceScope,
            $userId,
            $groupId,
            $permission,
        ): array {
            $rule = MaterialShareRule::query()
                ->where('school_id', (int) $actor->school_id)
                ->where('created_by_user_id', (int) $actor->id)
                ->where('workspace_id', $workspaceId)
                ->where('scope_type', $scopeType)
                ->where('scope_id', $scopeId)
                ->lockForUpdate()
                ->latest('id')
                ->first();

            if (! $rule) {
                $rule = MaterialShareRule::query()->create([
                    'school_id' => (int) $actor->school_id,
                    'created_by_user_id' => (int) $actor->id,
                    'workspace_id' => $workspaceId,
                    'scope_type' => $scopeType,
                    'scope_id' => $scopeId,
                    'is_active' => true,
                ]);
            } elseif (! $rule->is_active) {
                $rule->is_active = true;
                $rule->save();
            }

            $target = $this->targetQuery(
                $rule,
                $targetType,
                $audienceScope,
                $userId,
                $groupId,
            )
                ->lockForUpdate()
                ->first() ?? new MaterialShareTarget;

            $target->material_share_rule_id = (int) $rule->id;
            $target->target_type = $targetType;
            $target->audience_scope = $audienceScope;
            $target->permission = $permission;
            $target->user_id = $targetType === MaterialShareTarget::TARGET_USER ? $userId : null;
            $target->user_group_id = $targetType === MaterialShareTarget::TARGET_GROUP ? $groupId : null;
            $target->save();

            return [
                'rule' => $rule,
                'target' => $target,
            ];
        }, attempts: 3);
    }

    public function destroyTarget(User $actor, MaterialShareTarget $target): void
    {
        DB::transaction(function () use ($actor, $target): void {
            $lockedTarget = MaterialShareTarget::query()
                ->whereKey($target->getKey())
                ->lockForUpdate()
                ->first();

            if (! $lockedTarget) {
                abort(404, 'Freigabe-Ziel nicht gefunden.');
            }

            $rule = MaterialShareRule::query()
                ->whereKey((int) $lockedTarget->material_share_rule_id)
                ->lockForUpdate()
                ->first();

            $this->assertOwnedBy($rule, $actor, 'Freigabe-Ziel nicht gefunden.');

            $lockedTarget->delete();

            if (! $rule->targets()->exists()) {
                $rule->delete();
            }
        }, attempts: 3);
    }

    public function updateTargetPermission(
        User $actor,
        MaterialShareTarget $target,
        string $permission,
    ): MaterialShareRule {
        return DB::transaction(function () use ($actor, $target, $permission): MaterialShareRule {
            $lockedTarget = MaterialShareTarget::query()
                ->whereKey($target->getKey())
                ->lockForUpdate()
                ->first();

            if (! $lockedTarget) {
                abort(404, 'Freigabe-Ziel nicht gefunden.');
            }

            $rule = MaterialShareRule::query()
                ->whereKey((int) $lockedTarget->material_share_rule_id)
                ->lockForUpdate()
                ->first();

            $this->assertOwnedBy($rule, $actor, 'Freigabe-Ziel nicht gefunden.');

            $lockedTarget->permission = $permission;
            $lockedTarget->save();
            $rule->touch();

            return $rule->refresh();
        }, attempts: 3);
    }

    public function updateRuleActiveState(
        User $actor,
        MaterialShareRule $rule,
        bool $isActive,
    ): MaterialShareRule {
        return DB::transaction(function () use ($actor, $rule, $isActive): MaterialShareRule {
            $lockedRule = MaterialShareRule::query()
                ->whereKey($rule->getKey())
                ->lockForUpdate()
                ->first();

            $this->assertOwnedBy($lockedRule, $actor, 'Freigabe nicht gefunden.');

            $lockedRule->is_active = $isActive;
            $lockedRule->save();

            return $lockedRule;
        }, attempts: 3);
    }

    private function targetQuery(
        MaterialShareRule $rule,
        string $targetType,
        ?string $audienceScope,
        ?int $userId,
        ?int $groupId,
    ): HasMany {
        return $rule->targets()
            ->where('target_type', $targetType)
            ->when(
                $targetType === MaterialShareTarget::TARGET_EVERYONE,
                fn ($query) => $query->where('audience_scope', $audienceScope),
            )
            ->when(
                $targetType === MaterialShareTarget::TARGET_USER,
                fn ($query) => $query->where('user_id', $userId),
            )
            ->when(
                $targetType === MaterialShareTarget::TARGET_GROUP,
                fn ($query) => $query->where('user_group_id', $groupId),
            );
    }

    private function assertOwnedBy(
        ?MaterialShareRule $rule,
        User $actor,
        string $message,
    ): void {
        if (
            ! $rule
            || (int) $rule->school_id !== (int) $actor->school_id
            || (int) $rule->created_by_user_id !== (int) $actor->id
        ) {
            abort(404, $message);
        }
    }
}
