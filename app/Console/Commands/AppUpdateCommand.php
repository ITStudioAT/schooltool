<?php

namespace App\Console\Commands;

use App\Services\InstallUpdateService;
use App\Services\RecordsCreateService;
use Illuminate\Console\Command;
use Illuminate\Contracts\Process\ProcessResult as ProcessResultContract;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

class AppUpdateCommand extends Command
{
    private const int WINDOWS_NPM_CI_MAX_ATTEMPTS = 3;

    private const int WINDOWS_NPM_CI_RETRY_DELAY_SECONDS = 2;

    private const int WINDOWS_UNLOCK_TIMEOUT_SECONDS = 30;

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
            'studentstimetables_admin',
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
        $this->prepareWindowsFrontendInstall();

        $this->info('▶ INSTALLING FRONTEND DEPENDENCIES');
        if (! $this->runNpmCi()) {
            return false;
        }

        $this->info('▶ BUILDING FRONTEND');
        if (! $this->runProcess(['npm', 'run', 'build'], 'npm run build', 900, ['PUPPETEER_SKIP_DOWNLOAD' => '1'])) {
            return false;
        }

        $this->info('✅ Frontend build completed');

        return true;
    }

    private function runNpmCi(): bool
    {
        $attemptLimit = $this->npmCiAttemptLimit();
        $combinedOutput = '';

        for ($attempt = 1; $attempt <= $attemptLimit; $attempt++) {
            $result = $this->executeProcess(['npm', 'ci'], 900, ['PUPPETEER_SKIP_DOWNLOAD' => '1']);

            if ($result->successful()) {
                return true;
            }

            $combinedOutput = trim($result->output()."\n".$result->errorOutput());

            if (! $this->shouldRetryNpmCi($combinedOutput, $attempt, $attemptLimit)) {
                break;
            }

            $this->remediateWindowsNpmCiLock();
            $this->warn(sprintf(
                'npm ci hit a Windows file lock on attempt %d of %d; retrying in %d seconds...',
                $attempt,
                $attemptLimit,
                self::WINDOWS_NPM_CI_RETRY_DELAY_SECONDS,
            ));

            $this->pauseBeforeNpmCiRetry();
        }

        $this->error('❌ npm ci failed — aborting update.');
        $this->maybeShowWindowsNodeModulesHint($combinedOutput);

        return false;
    }

    private function prepareWindowsFrontendInstall(): void
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            return;
        }

        $hotPath = base_path('public/hot');
        if (! File::exists($hotPath)) {
            return;
        }

        File::delete($hotPath);
        $this->warn('Detected active Vite hot mode; removed public/hot before npm ci.');
        $this->stopProjectLocalWindowsFrontendProcesses();
    }

    /**
     * @param  array<int, string>  $command
     * @param  array<string, string>  $environment
     */
    private function runProcess(array $command, string $description, int $timeoutSeconds, array $environment = []): bool
    {
        $result = $this->executeProcess($command, $timeoutSeconds, $environment);

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
     * @param  array<int, string>  $command
     * @param  array<string, string>  $environment
     */
    private function executeProcess(array $command, int $timeoutSeconds, array $environment = []): ProcessResultContract
    {
        $process = Process::timeout($timeoutSeconds)
            ->path(base_path());

        if ($environment !== []) {
            $process = $process->env($environment);
        }

        return $process->run($command, function (string $type, string $buffer): void {
            echo $buffer;
        });
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

    private function remediateWindowsNpmCiLock(): void
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            return;
        }

        $this->warn('Attempting to stop project-local node/esbuild processes before retrying npm ci...');
        $this->stopProjectLocalWindowsFrontendProcesses();
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

    private function npmCiAttemptLimit(): int
    {
        if (PHP_OS_FAMILY === 'Windows') {
            return self::WINDOWS_NPM_CI_MAX_ATTEMPTS;
        }

        return 1;
    }

    private function shouldRetryNpmCi(string $output, int $attempt, int $attemptLimit): bool
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            return false;
        }

        if ($attempt >= $attemptLimit) {
            return false;
        }

        return $this->looksLikeWindowsLockIssue(strtolower($output));
    }

    private function pauseBeforeNpmCiRetry(): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        sleep(self::WINDOWS_NPM_CI_RETRY_DELAY_SECONDS);
    }

    private function stopProjectLocalWindowsFrontendProcesses(): void
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            return;
        }

        $this->executeProcess([
            'powershell',
            '-NoProfile',
            '-ExecutionPolicy',
            'Bypass',
            '-Command',
            '$projectPath = [string]$env:APP_UPDATE_PROJECT_PATH; '.
            '$esbuildPath = [string]$env:APP_UPDATE_ESBUILD_PATH; '.
            '$esbuildDirectory = [string]$env:APP_UPDATE_ESBUILD_DIRECTORY; '.
            'if ([string]::IsNullOrWhiteSpace($projectPath)) { exit 0 } '.
            '$projectPath = $projectPath.ToLowerInvariant(); '.
            '$esbuildPath = $esbuildPath.ToLowerInvariant(); '.
            '$processes = Get-CimInstance Win32_Process | Where-Object { '.
            '$name = if ($null -ne $_.Name) { ([string]$_.Name).ToLowerInvariant() } else { "" }; '.
            'if ($name -notin @("node.exe", "esbuild.exe")) { return $false } '.
            '$commandLine = if ($null -ne $_.CommandLine) { ([string]$_.CommandLine).ToLowerInvariant() } else { "" }; '.
            '$executablePath = if ($null -ne $_.ExecutablePath) { ([string]$_.ExecutablePath).ToLowerInvariant() } else { "" }; '.
            'return $commandLine.Contains($projectPath) -or ($esbuildPath -ne "" -and $executablePath.Contains($esbuildPath)) '.
            '}; '.
            '$processes | ForEach-Object { Stop-Process -Id $_.ProcessId -Force -ErrorAction SilentlyContinue }; '.
            'Start-Sleep -Milliseconds 750; '.
            'if (-not [string]::IsNullOrWhiteSpace($esbuildDirectory) -and (Test-Path -LiteralPath $esbuildDirectory)) { '.
            'Remove-Item -LiteralPath $esbuildDirectory -Recurse -Force -ErrorAction SilentlyContinue }',
        ], self::WINDOWS_UNLOCK_TIMEOUT_SECONDS, [
            'APP_UPDATE_PROJECT_PATH' => base_path(),
            'APP_UPDATE_ESBUILD_PATH' => base_path('node_modules/@esbuild/win32-x64/esbuild.exe'),
            'APP_UPDATE_ESBUILD_DIRECTORY' => base_path('node_modules/@esbuild'),
        ]);
    }
}
