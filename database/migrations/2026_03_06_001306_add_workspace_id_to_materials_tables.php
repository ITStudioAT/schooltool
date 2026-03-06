<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('material_cards') && ! Schema::hasColumn('material_cards', 'workspace_id')) {
            Schema::table('material_cards', function (Blueprint $table) {
                $table->foreignId('workspace_id')
                    ->nullable()
                    ->after('user_id')
                    ->constrained('material_workspaces')
                    ->nullOnDelete();
                $table->index(
                    ['user_id', 'workspace_id', 'status'],
                    'material_cards_user_workspace_status_idx'
                );
            });
        }

        if (Schema::hasTable('material_subjects') && ! Schema::hasColumn('material_subjects', 'workspace_id')) {
            Schema::table('material_subjects', function (Blueprint $table) {
                $table->foreignId('workspace_id')
                    ->nullable()
                    ->after('user_id')
                    ->constrained('material_workspaces')
                    ->nullOnDelete();
            });

            Schema::table('material_subjects', function (Blueprint $table) {
                if (Schema::hasColumn('material_subjects', 'sort_order')) {
                    $table->index(
                        ['user_id', 'workspace_id', 'sort_order'],
                        'material_subjects_user_workspace_sort_order_idx'
                    );

                    return;
                }

                $table->index(
                    ['user_id', 'workspace_id'],
                    'material_subjects_user_workspace_idx'
                );
            });
        }

        if (Schema::hasTable('material_share_rules') && ! Schema::hasColumn('material_share_rules', 'workspace_id')) {
            Schema::table('material_share_rules', function (Blueprint $table) {
                $table->foreignId('workspace_id')
                    ->nullable()
                    ->after('created_by_user_id')
                    ->constrained('material_workspaces')
                    ->nullOnDelete();
                $table->index(
                    ['school_id', 'workspace_id', 'is_active'],
                    'material_share_rules_school_workspace_active_idx'
                );
            });
        }

        $this->assignDefaultWorkspacePerUser();
        $this->updateSubjectUniqueConstraints();
    }

    public function down(): void
    {
        if (Schema::hasTable('material_subjects')) {
            Schema::table('material_subjects', function (Blueprint $table) {
                if ($this->hasIndex('material_subjects', 'material_subjects_workspace_id_name_unique')) {
                    $table->dropUnique('material_subjects_workspace_id_name_unique');
                }
                if ($this->hasIndex('material_subjects', 'material_subjects_user_workspace_sort_order_idx')) {
                    $table->dropIndex('material_subjects_user_workspace_sort_order_idx');
                }
                if ($this->hasIndex('material_subjects', 'material_subjects_user_workspace_idx')) {
                    $table->dropIndex('material_subjects_user_workspace_idx');
                }
                if (Schema::hasColumn('material_subjects', 'workspace_id')) {
                    $table->dropConstrainedForeignId('workspace_id');
                }
            });

            Schema::table('material_subjects', function (Blueprint $table) {
                if (! $this->hasIndex('material_subjects', 'material_subjects_user_id_name_unique')) {
                    $table->unique(['user_id', 'name'], 'material_subjects_user_id_name_unique');
                }
            });
        }

        if (Schema::hasTable('material_cards')) {
            Schema::table('material_cards', function (Blueprint $table) {
                if ($this->hasIndex('material_cards', 'material_cards_user_workspace_status_idx')) {
                    $table->dropIndex('material_cards_user_workspace_status_idx');
                }
                if (Schema::hasColumn('material_cards', 'workspace_id')) {
                    $table->dropConstrainedForeignId('workspace_id');
                }
            });
        }

        if (Schema::hasTable('material_share_rules')) {
            Schema::table('material_share_rules', function (Blueprint $table) {
                if ($this->hasIndex('material_share_rules', 'material_share_rules_school_workspace_active_idx')) {
                    $table->dropIndex('material_share_rules_school_workspace_active_idx');
                }
                if (Schema::hasColumn('material_share_rules', 'workspace_id')) {
                    $table->dropConstrainedForeignId('workspace_id');
                }
            });
        }
    }

    private function assignDefaultWorkspacePerUser(): void
    {
        if (! Schema::hasTable('material_workspaces') || ! Schema::hasTable('users')) {
            return;
        }

        $now = now();
        $workspaceLabel = 'Workspace';
        $userIds = $this->eligibleMaterialWorkspaceUserIds();

        foreach ($userIds as $userId) {
            $workspace = DB::table('material_workspaces')
                ->where('user_id', $userId)
                ->orderByDesc('is_default')
                ->orderBy('id')
                ->first();

            if (! $workspace) {
                $workspaceId = (int) DB::table('material_workspaces')->insertGetId([
                    'user_id' => $userId,
                    'name' => $workspaceLabel,
                    'is_default' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            } else {
                $workspaceId = (int) ($workspace->id ?? 0);
                if ($workspaceId <= 0) {
                    continue;
                }

                if (! (bool) ($workspace->is_default ?? false)) {
                    DB::table('material_workspaces')
                        ->where('id', $workspaceId)
                        ->update([
                            'is_default' => true,
                            'updated_at' => $now,
                        ]);
                }
            }

            DB::table('material_workspaces')
                ->where('user_id', $userId)
                ->where('id', '<>', $workspaceId)
                ->where('is_default', true)
                ->update([
                    'is_default' => false,
                    'updated_at' => $now,
                ]);

            if (Schema::hasTable('material_cards') && Schema::hasColumn('material_cards', 'workspace_id')) {
                DB::table('material_cards')
                    ->where('user_id', $userId)
                    ->whereNull('workspace_id')
                    ->update(['workspace_id' => $workspaceId]);
            }

            if (Schema::hasTable('material_subjects') && Schema::hasColumn('material_subjects', 'workspace_id')) {
                DB::table('material_subjects')
                    ->where('user_id', $userId)
                    ->whereNull('workspace_id')
                    ->update(['workspace_id' => $workspaceId]);
            }

            if (Schema::hasTable('material_share_rules') && Schema::hasColumn('material_share_rules', 'workspace_id')) {
                DB::table('material_share_rules')
                    ->where('created_by_user_id', $userId)
                    ->whereNull('workspace_id')
                    ->update(['workspace_id' => $workspaceId]);
            }
        }
    }

    private function eligibleMaterialWorkspaceUserIds(): \Illuminate\Support\Collection
    {
        if (! Schema::hasTable('users')) {
            return collect();
        }

        if (! class_exists(\App\Models\User::class) || ! class_exists(\App\Services\LicenceService::class)) {
            return collect();
        }

        /** @var \App\Services\LicenceService $licenceService */
        $licenceService = app(\App\Services\LicenceService::class);
        $candidateRoles = ['admin', 'materials_admin', 'materials_moderator', 'super_admin'];
        $eligibleIds = collect();

        \App\Models\User::query()
            ->select(['id', 'school_id'])
            ->with('selectedSchool')
            ->chunkById(200, function ($users) use ($licenceService, $candidateRoles, &$eligibleIds): void {
                foreach ($users as $user) {
                    if (! $user instanceof \App\Models\User) {
                        continue;
                    }

                    $status = $licenceService->toolAccessStatusForUser(
                        user: $user,
                        school: $user->selectedSchool,
                        app: 'Materialientool',
                        candidateRoleNames: $candidateRoles,
                    );

                    if ($status === 'active') {
                        $eligibleIds->push((int) $user->id);
                    }
                }
            });

        return $eligibleIds
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values();
    }

    private function updateSubjectUniqueConstraints(): void
    {
        if (! Schema::hasTable('material_subjects')) {
            return;
        }

        Schema::table('material_subjects', function (Blueprint $table) {
            if ($this->hasIndex('material_subjects', 'material_subjects_user_id_name_unique')) {
                $table->dropUnique('material_subjects_user_id_name_unique');
            }
            if (! $this->hasIndex('material_subjects', 'material_subjects_workspace_id_name_unique')) {
                $table->unique(['workspace_id', 'name'], 'material_subjects_workspace_id_name_unique');
            }
        });
    }

    private function hasIndex(string $tableName, string $indexName): bool
    {
        $databaseName = DB::getDatabaseName();
        if (! is_string($databaseName) || $databaseName === '') {
            return false;
        }

        $result = DB::table('information_schema.statistics')
            ->where('table_schema', $databaseName)
            ->where('table_name', $tableName)
            ->where('index_name', $indexName)
            ->exists();

        return (bool) $result;
    }
};
