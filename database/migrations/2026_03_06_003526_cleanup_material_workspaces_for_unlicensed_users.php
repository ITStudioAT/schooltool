<?php

use App\Models\User;
use App\Services\LicenceService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('material_workspaces') || ! Schema::hasTable('users')) {
            return;
        }

        $eligibleUserIds = $this->eligibleMaterialWorkspaceUserIds();
        $eligibleUserLookup = array_fill_keys($eligibleUserIds->all(), true);

        $workspaceUserIds = DB::table('material_workspaces')
            ->select('user_id')
            ->distinct()
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values();

        $ineligibleWorkspaceUserIds = $workspaceUserIds
            ->filter(fn (int $userId) => ! isset($eligibleUserLookup[$userId]))
            ->values();

        foreach ($ineligibleWorkspaceUserIds->chunk(200) as $chunk) {
            $userIds = $chunk
                ->map(fn ($id) => (int) $id)
                ->filter(fn (int $id) => $id > 0)
                ->values()
                ->all();

            if ($userIds === []) {
                continue;
            }

            if (Schema::hasTable('material_cards') && Schema::hasColumn('material_cards', 'workspace_id')) {
                DB::table('material_cards')
                    ->whereIn('user_id', $userIds)
                    ->update(['workspace_id' => null]);
            }

            if (Schema::hasTable('material_subjects') && Schema::hasColumn('material_subjects', 'workspace_id')) {
                DB::table('material_subjects')
                    ->whereIn('user_id', $userIds)
                    ->update(['workspace_id' => null]);
            }

            if (Schema::hasTable('material_share_rules') && Schema::hasColumn('material_share_rules', 'workspace_id')) {
                DB::table('material_share_rules')
                    ->whereIn('created_by_user_id', $userIds)
                    ->update(['workspace_id' => null]);
            }

            DB::table('material_workspaces')
                ->whereIn('user_id', $userIds)
                ->delete();
        }

        foreach ($eligibleUserIds as $userId) {
            $this->ensureDefaultWorkspaceForUser((int) $userId);
        }
    }

    public function down(): void
    {
        // Data cleanup migration: no safe automatic rollback.
    }

    private function eligibleMaterialWorkspaceUserIds(): Collection
    {
        if (! class_exists(User::class) || ! class_exists(LicenceService::class)) {
            return collect();
        }

        /** @var LicenceService $licenceService */
        $licenceService = app(LicenceService::class);
        $candidateRoles = ['admin', 'materials_admin', 'materials_moderator', 'super_admin'];
        $eligibleIds = collect();

        User::query()
            ->select(['id', 'school_id'])
            ->with('selectedSchool')
            ->chunkById(200, function ($users) use ($licenceService, $candidateRoles, &$eligibleIds): void {
                foreach ($users as $user) {
                    if (! $user instanceof User) {
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

    private function ensureDefaultWorkspaceForUser(int $userId): void
    {
        if ($userId <= 0) {
            return;
        }

        $now = now();
        $workspaceLabel = 'Workspace';
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
                return;
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
};
