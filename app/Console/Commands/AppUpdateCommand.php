<?php

namespace App\Console\Commands;

use App\Services\InstallUpdateService;
use App\Services\RecordsCreateService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

class AppUpdateCommand extends Command
{
    protected $signature = 'app:update';

    protected $description = 'Update application: frontend build, migrations, records, roles, folders, and caches';

    public function handle(InstallUpdateService $service, RecordsCreateService $recordsCreateService): int
    {
        // CLEAR CONSOLE
        $this->output->write("\033c");
        $this->info('🚀 Starting application update...');
        $this->line(str_repeat('.', 50));

        if (! $this->runPreflightChecks()) {
            return self::FAILURE;
        }

        if (! $this->runFrontendUpdate()) {
            return self::FAILURE;
        }

        $this->line(str_repeat('.', 50));

        $this->info('▶ CLEARING CONFIG CACHE');
        if (! $this->runArtisanCommand('config:clear', [], 'config:clear')) {
            return self::FAILURE;
        }
        $this->line(str_repeat('.', 50));

        $this->info('▶ MIGRATIONS');
        if (! $this->runArtisanCommand('migrate', ['--force' => true], 'Migrations')) {
            return self::FAILURE;
        }
        $this->line(str_repeat('.', 50));

        $this->info('▶ CLEAR TEST-FILES');
        $service->clearModels();
        $this->info('✅ Records in test-files deleted');
        $this->line(str_repeat('.', 50));

        $this->info('▶ ROLES AND RECORDS');
        $service->createRoles([
            'super_admin',
            'admin',
            'register_admin',
            'register_user',
            'tutoring_user',
            'tutoring_admin',
            'teacher',
            'lunch_admin',
            'lunch_candidate',
            'lunch_user',
            'teaching_admin',
            'materials_admin',
            'student',
            'materials_moderator',
            'aba_teacher',
        ]);
        $this->info('✅ Roles checked');

        $recordsCreateService->initRecords();
        $this->info('✅ Init Records checked');
        $restaurantRoleNormalization = $service->normalizeRestaurantUserRoles();
        $this->info('✅ Restaurant-Benutzer normalisiert');
        $this->line('   restaurant_confirmed_at ergänzt: '.$restaurantRoleNormalization['restaurant_confirmed_backfilled']);
        $this->line('   lunch_user zugewiesen: '.$restaurantRoleNormalization['lunch_user_roles_assigned']);
        $this->line('   lunch_candidate entfernt: '.$restaurantRoleNormalization['lunch_candidate_roles_removed']);
        $this->line(str_repeat('.', 50));

        // ✅ Folders
        $this->info('▶ FOLDERS');
        $service->findOrCreateFolders();
        $this->info('✅ Folders checked');
        $cleanup = $service->pruneOrphanPrivateSchoolFolders();
        $this->info('✅ Orphan school folders cleaned: '.count($cleanup['deleted']));
        if (! empty($cleanup['failed'])) {
            $this->warn('⚠️ Failed to delete orphan school folders: '.count($cleanup['failed']));
        }
        $this->line(str_repeat('.', 50));

        // ✅ DEV-Debugbar
        $this->info('▶ DEV:DEBUGBAR');
        $service->clearDebugbar();
        $this->info('✅ Debugbar cleared');
        $this->line(str_repeat('.', 50));

        $this->info('▶ CLEARING CACHES');
        if (! $this->runArtisanCommand('optimize:clear', [], 'optimize:clear')) {
            return self::FAILURE;
        }
        $this->info('▶ RESTARTING QUEUES');
        if (! $this->runArtisanCommand('queue:restart', [], 'queue:restart')) {
            return self::FAILURE;
        }
        $this->info('✅ Caches cleared');
        $this->line(str_repeat('.', 50));
        $this->info('🏁 Application update finished!');

        return self::SUCCESS;
    }

    private function runPreflightChecks(): bool
    {
        $this->info('▶ PREFLIGHT VALIDATION');

        $requiredFiles = [
            'package.json' => 'package.json is missing; app:update needs the frontend toolchain.',
            'package-lock.json' => 'package-lock.json is missing; npm ci requires a lockfile.',
            'scripts/check_node_version.cjs' => 'scripts/check_node_version.cjs is missing; Node version compatibility cannot be verified.',
            'scripts/build_frontend.sh' => 'scripts/build_frontend.sh is missing; the POSIX frontend wrapper should stay in sync.',
            'scripts/build_frontend.cmd' => 'scripts/build_frontend.cmd is missing; the Windows frontend wrapper should stay in sync.',
        ];

        foreach ($requiredFiles as $relativePath => $message) {
            if (! File::exists(base_path($relativePath))) {
                $this->error('❌ '.$message);

                return false;
            }
        }

        if (! $this->runProcess(['node', '--version'], 'node availability check', 30)) {
            return false;
        }

        if (! $this->runProcess(['npm', '--version'], 'npm availability check', 30)) {
            return false;
        }

        if (! $this->runProcess(['node', base_path('scripts/check_node_version.cjs')], 'Node version compatibility check', 30)) {
            return false;
        }

        $this->info('✅ Preflight validation passed');

        return true;
    }

    private function runFrontendUpdate(): bool
    {
        $this->info('▶ INSTALLING FRONTEND DEPENDENCIES');
        if (! $this->runProcess(['npm', 'ci'], 'npm ci', 900, ['PUPPETEER_SKIP_DOWNLOAD' => '1'])) {
            return false;
        }

        $this->info('▶ BUILDING FRONTEND');
        if (! $this->runProcess(['npm', 'run', 'build'], 'npm run build', 900, ['PUPPETEER_SKIP_DOWNLOAD' => '1'])) {
            return false;
        }

        $this->info('✅ Frontend build completed');

        return true;
    }

    /**
     * @param  array<int, string>  $command
     * @param  array<string, string>  $environment
     */
    private function runProcess(array $command, string $description, int $timeoutSeconds, array $environment = []): bool
    {
        $process = Process::timeout($timeoutSeconds)
            ->path(base_path());

        if ($environment !== []) {
            $process = $process->env($environment);
        }

        $result = $process->run($command, function (string $type, string $buffer): void {
            echo $buffer;
        });

        if ($result->successful()) {
            return true;
        }

        $this->error("❌ {$description} failed — aborting update.");

        if ($description === 'npm ci') {
            $this->maybeShowWindowsNodeModulesHint($result->output()."\n".$result->errorOutput());
        }

        return false;
    }

    /**
     * @param  array<int, string>  $parameters
     */
    private function runArtisanCommand(string $command, array $parameters, string $description): bool
    {
        if (Artisan::call($command, $parameters) !== 0) {
            $this->error("❌ {$description} failed — aborting update.");

            $output = trim((string) Artisan::output());
            if ($output !== '') {
                $this->line($output);
            }

            return false;
        }

        $output = trim((string) Artisan::output());
        if ($output !== '') {
            $this->line($output);
        }

        return true;
    }

    private function maybeShowWindowsNodeModulesHint(string $output): void
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            return;
        }

        $normalizedOutput = strtolower($output);
        if (! $this->looksLikeWindowsLockIssue($normalizedOutput)) {
            return;
        }

        $this->warn('Hint: close editors/watchers and unlock node_modules/esbuild files, then rerun app:update.');
    }

    private function looksLikeWindowsLockIssue(string $output): bool
    {
        foreach (['ebusy', 'eperm', 'enotempty', 'locked', 'esbuild', 'node_modules'] as $needle) {
            if (str_contains($output, $needle)) {
                return true;
            }
        }

        return false;
    }
}
