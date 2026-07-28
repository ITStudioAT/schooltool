<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('material_share_rules')) {
            $this->addRuleNaturalKey();
        }

        if (Schema::hasTable('material_share_targets')) {
            $this->addTargetNaturalKey();
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('material_share_targets')) {
            $this->dropNaturalKey(
                'material_share_targets',
                'material_share_targets_natural_unique',
            );
        }

        if (Schema::hasTable('material_share_rules')) {
            $this->dropNaturalKey(
                'material_share_rules',
                'material_share_rules_natural_unique',
            );
        }
    }

    private function addRuleNaturalKey(): void
    {
        $seenKeys = [];

        foreach (DB::table('material_share_rules')->orderBy('id')->cursor() as $rule) {
            $naturalKey = implode(':', [
                (int) $rule->school_id,
                (int) ($rule->created_by_user_id ?? 0),
                (int) ($rule->workspace_id ?? 0),
                (string) $rule->scope_type,
                (int) ($rule->scope_id ?? 0),
            ]);

            if (isset($seenKeys[$naturalKey])) {
                throw new RuntimeException(
                    'Material share rule natural keys contain duplicates; conflicting rule IDs: '
                    .$seenKeys[$naturalKey].' and '.(int) $rule->id
                );
            }

            $seenKeys[$naturalKey] = (int) $rule->id;
        }

        Schema::table('material_share_rules', function (Blueprint $table) {
            $table->string('natural_key', 191)->default('');
        });

        DB::table('material_share_rules')
            ->orderBy('id')
            ->eachById(function (object $rule): void {
                DB::table('material_share_rules')
                    ->where('id', (int) $rule->id)
                    ->update([
                        'natural_key' => implode(':', [
                            (int) $rule->school_id,
                            (int) ($rule->created_by_user_id ?? 0),
                            (int) ($rule->workspace_id ?? 0),
                            (string) $rule->scope_type,
                            (int) ($rule->scope_id ?? 0),
                        ]),
                    ]);
            }, 500);

        Schema::table('material_share_rules', function (Blueprint $table) {
            $table->string('natural_key', 191)->change();
        });

        Schema::table('material_share_rules', function (Blueprint $table) {
            $table->unique('natural_key', 'material_share_rules_natural_unique');
        });
    }

    private function addTargetNaturalKey(): void
    {
        $seenKeys = [];

        foreach (DB::table('material_share_targets')->orderBy('id')->cursor() as $target) {
            $naturalKey = $this->targetNaturalKey($target);

            if (isset($seenKeys[$naturalKey])) {
                throw new RuntimeException(
                    'Material share target natural keys contain duplicates; conflicting target IDs: '
                    .$seenKeys[$naturalKey].' and '.(int) $target->id
                );
            }

            $seenKeys[$naturalKey] = (int) $target->id;
        }

        Schema::table('material_share_targets', function (Blueprint $table) {
            $table->string('natural_key', 191)->default('');
        });

        DB::table('material_share_targets')
            ->orderBy('id')
            ->eachById(function (object $target): void {
                DB::table('material_share_targets')
                    ->where('id', (int) $target->id)
                    ->update([
                        'natural_key' => $this->targetNaturalKey($target),
                    ]);
            }, 500);

        Schema::table('material_share_targets', function (Blueprint $table) {
            $table->string('natural_key', 191)->change();
        });

        Schema::table('material_share_targets', function (Blueprint $table) {
            $table->unique('natural_key', 'material_share_targets_natural_unique');
        });
    }

    private function dropNaturalKey(string $tableName, string $indexName): void
    {
        if (Schema::hasIndex($tableName, $indexName)) {
            Schema::table($tableName, function (Blueprint $table) use ($indexName) {
                $table->dropUnique($indexName);
            });
        }

        if (Schema::hasColumn($tableName, 'natural_key')) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn('natural_key');
            });
        }
    }

    private function targetNaturalKey(object $target): string
    {
        $targetIdentity = match ((string) $target->target_type) {
            'everyone' => 'everyone:'.(string) ($target->audience_scope ?? ''),
            'user' => 'user:'.(int) ($target->user_id ?? 0),
            'group' => 'group:'.(int) ($target->user_group_id ?? 0),
            default => (string) $target->target_type.':',
        };

        return (int) $target->material_share_rule_id.':'.$targetIdentity;
    }
};
