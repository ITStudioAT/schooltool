<?php

use App\Services\SchoolToolModuleStatusService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('school_tools')) {
            return;
        }

        Schema::table('school_tools', function (Blueprint $table) {
            foreach ($this->modules() as $moduleKey) {
                $adminVisibleField = sprintf('%s_visible_admin', $moduleKey);
                $userVisibleField = sprintf('%s_visible_user', $moduleKey);
                $userTestModeField = sprintf('%s_user_test_mode', $moduleKey);
                $userComingSoonField = sprintf('%s_user_comming_soon', $moduleKey);

                if (! Schema::hasColumn('school_tools', $adminVisibleField)) {
                    $table->boolean($adminVisibleField)->default($this->defaultIsActive($moduleKey))->after('school_id');
                }

                if (! Schema::hasColumn('school_tools', $userVisibleField)) {
                    $table->boolean($userVisibleField)->default($this->defaultIsActive($moduleKey))->after($adminVisibleField);
                }

                if (! Schema::hasColumn('school_tools', $userTestModeField)) {
                    $table->boolean($userTestModeField)->default(false)->after($userVisibleField);
                }

                if (! Schema::hasColumn('school_tools', $userComingSoonField)) {
                    $table->boolean($userComingSoonField)->default(false)->after($userTestModeField);
                }
            }
        });

        $legacyStatusColumns = array_filter(
            array_map(fn (string $moduleKey): string => sprintf('%s_status', $moduleKey), $this->modules()),
            fn (string $column): bool => Schema::hasColumn('school_tools', $column)
        );

        DB::table('school_tools')
            ->orderBy('id')
            ->get()
            ->each(function (object $schoolTool) use ($legacyStatusColumns): void {
                $update = [];

                foreach ($this->modules() as $moduleKey) {
                    $legacyStatusField = sprintf('%s_status', $moduleKey);
                    $legacyStatus = in_array($legacyStatusField, $legacyStatusColumns, true)
                        ? (string) ($schoolTool->{$legacyStatusField} ?? 'inactive')
                        : ($this->defaultIsActive($moduleKey) ? 'active' : 'inactive');

                    $update = array_merge($update, $this->legacyStatusAttributes($moduleKey, $legacyStatus));
                }

                if ($update !== []) {
                    DB::table('school_tools')
                        ->where('id', $schoolTool->id)
                        ->update($update);
                }
            });
    }

    public function down(): void
    {
        if (! Schema::hasTable('school_tools')) {
            return;
        }

        Schema::table('school_tools', function (Blueprint $table) {
            $columns = [];

            foreach ($this->modules() as $moduleKey) {
                foreach ([
                    sprintf('%s_visible_admin', $moduleKey),
                    sprintf('%s_visible_user', $moduleKey),
                    sprintf('%s_user_test_mode', $moduleKey),
                    sprintf('%s_user_comming_soon', $moduleKey),
                ] as $column) {
                    if (Schema::hasColumn('school_tools', $column)) {
                        $columns[] = $column;
                    }
                }
            }

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }

    /**
     * @return array<int, string>
     */
    private function modules(): array
    {
        return ['register', 'tutoring', 'teaching', 'materials', 'restaurant'];
    }

    private function defaultIsActive(string $moduleKey): bool
    {
        return SchoolToolModuleStatusService::moduleEnabledByDefault($moduleKey);
    }

    /**
     * @return array<string, bool>
     */
    private function legacyStatusAttributes(string $moduleKey, string $legacyStatus): array
    {
        return match ($legacyStatus) {
            'active' => [
                sprintf('%s_visible_admin', $moduleKey) => true,
                sprintf('%s_visible_user', $moduleKey) => true,
                sprintf('%s_user_test_mode', $moduleKey) => false,
                sprintf('%s_user_comming_soon', $moduleKey) => false,
            ],
            'test_modus' => [
                sprintf('%s_visible_admin', $moduleKey) => true,
                sprintf('%s_visible_user', $moduleKey) => false,
                sprintf('%s_user_test_mode', $moduleKey) => true,
                sprintf('%s_user_comming_soon', $moduleKey) => false,
            ],
            'comming_soon' => [
                sprintf('%s_visible_admin', $moduleKey) => true,
                sprintf('%s_visible_user', $moduleKey) => false,
                sprintf('%s_user_test_mode', $moduleKey) => false,
                sprintf('%s_user_comming_soon', $moduleKey) => true,
            ],
            default => [
                sprintf('%s_visible_admin', $moduleKey) => false,
                sprintf('%s_visible_user', $moduleKey) => false,
                sprintf('%s_user_test_mode', $moduleKey) => false,
                sprintf('%s_user_comming_soon', $moduleKey) => false,
            ],
        };
    }
};
