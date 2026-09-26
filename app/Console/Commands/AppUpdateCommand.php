<?php

namespace App\Console\Commands;

use App\Services\InstallUpdateService;
use App\Services\RecordsCreateService;
use Illuminate\Console\Command;
use Illuminate\Contracts\Process\ProcessResult as ProcessResultContract;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use JsonException;
use Throwable;

class AppUpdateCommand extends Command
{
    private const string NPM_CACHE_RELATIVE_PATH = 'storage/framework/npm-cache';

    private const string ENVIRONMENT_VERSIONS_RELATIVE_PATH = 'storage/framework/environment-versions.json';

    private const string FRONTEND_ENVIRONMENT_VERSIONS_RELATIVE_PATH = 'public/build/environment-versions.json';

    private const int WINDOWS_NPM_CI_MAX_ATTEMPTS = 3;

    private const int WINDOWS_NPM_CI_RETRY_DELAY_SECONDS = 2;

    private const int WINDOWS_UNLOCK_TIMEOUT_SECONDS = 30;

    private const int PROCESS_HEARTBEAT_INTERVAL_SECONDS = 15;

    protected $signature = 'app:update
                            {--skip-frontend : Use the prebuilt frontend artifact already installed in public/build}
                            {--versions-only : Record environment versions without updating the application}';

    protected $description = 'Update application: frontend build, migrations, records, roles, folders, and caches';

    public function handle(InstallUpdateService $service, RecordsCreateService $recordsCreateService): int
    {
        if ($this->option('versions-only')) {
            $this->recordEnvironmentVersions();

            return self::SUCCESS;
        }

        // CLEAR CONSOLE
        $this->output->write("\033c");
        $this->info('🚀 Starting application update...');
        $this->line(str_repeat('.', 50));

        if ($this->option('skip-frontend')) {
            if (! $this->validatePrebuiltFrontend()) {
                return self::FAILURE;
            }
        } else {
            if (! $this->runPreflightChecks()) {
                return self::FAILURE;
            }

            if (! $this->runFrontendUpdate()) {
                return self::FAILURE;
            }
        }

        $this->recordEnvironmentVersions();

        $this->line(str_repeat('.', 50));

        $this->info('▶ CLEARING CONFIG CACHE');
        $this->waitingLine('Dusting off cached config so Laravel reads the fresh notes.');
        if (! $this->runArtisanCommand('config:clear', [], 'config:clear')) {
            return self::FAILURE;
        }
        $this->line(str_repeat('.', 50));

        $this->info('▶ MIGRATIONS');
        $this->waitingLine('Checking the database floorboards before anyone steps on them.');
        if (! $this->runArtisanCommand('migrate', ['--force' => true], 'Migrations')) {
            return self::FAILURE;
        }
        $this->line(str_repeat('.', 50));

        $this->info('▶ LICENCE BACKFILL');
        $this->waitingLine('Matching licences with their users. Tiny paperwork parade.');
        if (! $this->runArtisanCommand('schooltool:backfill-school-user-licences', [], 'School user licence backfill')) {
            return self::FAILURE;
        }
        $this->line(str_repeat('.', 50));

        $this->info('▶ TEACHING WORK GROUP INDEX BACKFILL');
        $this->waitingLine('Tidying teaching work group indexes so future searches feel snappy.');
        if (! $this->runArtisanCommand('schooltool:backfill-teaching-course-work-group-students', [], 'Teaching course work group student index backfill')) {
            return self::FAILURE;
        }
        $this->line(str_repeat('.', 50));

        $this->info('▶ CLEAR TEST-FILES');
        $this->waitingLine('Clearing old test-file crumbs from the table.');
        $service->clearModels();
        $this->info('✅ Records in test-files deleted');
        $this->line(str_repeat('.', 50));

        $this->info('▶ ROLES AND RECORDS');
        $this->waitingLine('Polishing roles and seed records. The boring bits are doing useful work.');
        $service->createRoles([
            'super_admin',
            'admin',
            'register_admin',
            'register_user',
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
            'studentstimetables_moderator',
            'studentstimetables_user',
            'Director',
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
        $this->waitingLine('Making sure every folder has a proper place to live.');
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
        $this->waitingLine('Emptying debug drawers before the next investigation.');
        $service->clearDebugbar();
        $this->info('✅ Debugbar cleared');
        $this->line(str_repeat('.', 50));

        $this->info('▶ CLEARING CACHES');
        $this->waitingLine('Sweeping application caches. Fresh air for the next request.');
        if (! $this->runArtisanCommand('optimize:clear', [], 'optimize:clear')) {
            return self::FAILURE;
        }
        $this->info('✅ Caches cleared');
        $this->line(str_repeat('.', 50));
        $this->info('🏁 Application update finished!');

        return self::SUCCESS;
    }

    private function validatePrebuiltFrontend(): bool
    {
        $manifestPath = base_path('public/build/manifest.json');

        if (! File::exists($manifestPath)) {
            $this->error("❌ Prebuilt frontend manifest is missing at {$manifestPath}.");

            return false;
        }

        try {
            $manifest = json_decode(File::get($manifestPath), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $this->error("❌ Prebuilt frontend manifest is invalid at {$manifestPath}.");

            return false;
        }

        if (! is_array($manifest)) {
            $this->error("❌ Prebuilt frontend manifest is invalid at {$manifestPath}.");

            return false;
        }

        $this->info('▶ USING PREBUILT FRONTEND');
        $this->waitingLine('The verified frontend release is already installed; skipping npm and Vite on this server.');

        return true;
    }

    private function runPreflightChecks(): bool
    {
        $this->info('▶ PREFLIGHT VALIDATION');
        $this->waitingLine('Checking the toolbox before we start turning screws.');

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
        $this->prepareNpmCacheDirectory();
        $this->prepareWindowsFrontendInstall();

        if (! $this->prepareNonWindowsFrontendInstall()) {
            return false;
        }

        $this->info('▶ INSTALLING FRONTEND DEPENDENCIES');
        $this->waitingLine('npm is arranging a very large drawer of tiny packages.');
        if (! $this->runNpmCi()) {
            return false;
        }

        $this->info('▶ CLEARING ROUTE CACHE');
        $this->waitingLine('Refreshing Laravel routes before Wayfinder generates frontend actions.');
        if (! $this->runArtisanCommand('route:clear', [], 'route:clear')) {
            return false;
        }

        $this->info('▶ BUILDING FRONTEND');
        $this->waitingLine('Vite is baking the frontend. Please enjoy the smell of compiled assets.');
        if (! $this->runProcess(['npm', 'run', 'build'], 'npm run build', 900, $this->frontendEnvironment(), $this->viteBuildHeartbeatMessages())) {
            return false;
        }

        $this->info('✅ Frontend build completed');

        return true;
    }

    private function recordEnvironmentVersions(): void
    {
        $frontendVersions = $this->frontendEnvironmentVersions();
        $versions = [
            'composer' => $this->detectRuntimeVersion(
                ['composer', '--version', '--no-ansi'],
                '/Composer(?: version)?\s+(\d+(?:\.\d+){1,3})/i',
            ) ?? ($frontendVersions['composer'] ?? null),
            'npm' => $this->detectRuntimeVersion(
                ['npm', '--version'],
                '/^v?(\d+(?:\.\d+){1,3})/',
            ) ?? ($frontendVersions['npm'] ?? null),
            'node' => $this->detectRuntimeVersion(
                ['node', '--version'],
                '/^v?(\d+(?:\.\d+){1,3})/',
                'v',
            ) ?? ($frontendVersions['node'] ?? null),
        ];

        File::ensureDirectoryExists(dirname(base_path(self::ENVIRONMENT_VERSIONS_RELATIVE_PATH)));

        try {
            $contents = json_encode($versions, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
        } catch (JsonException) {
            $this->warn('⚠️ Environment versions could not be encoded.');

            return;
        }

        if (File::put(base_path(self::ENVIRONMENT_VERSIONS_RELATIVE_PATH), $contents) === false) {
            $this->warn('⚠️ Environment versions could not be recorded.');

            return;
        }

        Cache::forget('admin.environment_versions.v13');

        $this->info('✅ Environment versions recorded');
    }

    /** @return array<string, string|null> */
    private function frontendEnvironmentVersions(): array
    {
        $path = base_path(self::FRONTEND_ENVIRONMENT_VERSIONS_RELATIVE_PATH);
        if (! File::exists($path)) {
            return [];
        }

        try {
            $versions = json_decode(File::get($path), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $versions = [];
        }

        File::delete($path);

        return is_array($versions) ? $versions : [];
    }

    /** @param array<int, string> $command */
    private function detectRuntimeVersion(array $command, string $pattern, string $prefix = ''): ?string
    {
        try {
            $result = Process::timeout(5)
                ->path(base_path())
                ->run($command);
        } catch (Throwable) {
            return null;
        }

        if (! $result->successful()) {
            return null;
        }

        $output = trim($result->output() ?: $result->errorOutput());
        if (preg_match($pattern, $output, $matches) !== 1) {
            return null;
        }

        return $prefix.$matches[1];
    }

    private function waitingLine(string $message): void
    {
        $this->line("   {$message}");
    }

    private function runNpmCi(): bool
    {
        $attemptLimit = $this->npmCiAttemptLimit();
        $combinedOutput = '';

        for ($attempt = 1; $attempt <= $attemptLimit; $attempt++) {
            $result = $this->executeProcess(['npm', 'ci'], 900, $this->frontendEnvironment(), $this->npmInstallHeartbeatMessages());

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
        $this->maybeShowNpmCachePermissionHint($combinedOutput);
        $this->maybeShowWindowsNodeModulesHint($combinedOutput);

        return false;
    }

    private function prepareNonWindowsFrontendInstall(): bool
    {
        if (! $this->shouldCleanNodeModulesBeforeNpmCi()) {
            return true;
        }

        $nodeModulesPath = base_path('node_modules');
        if (! File::isDirectory($nodeModulesPath)) {
            return true;
        }

        $this->warn('Removing existing node_modules before npm ci for a clean install.');

        if (File::deleteDirectory($nodeModulesPath)) {
            return true;
        }

        $this->error("❌ Could not remove existing node_modules at {$nodeModulesPath}; fix ownership/permissions and rerun app:update.");

        return false;
    }

    protected function shouldCleanNodeModulesBeforeNpmCi(): bool
    {
        return PHP_OS_FAMILY !== 'Windows';
    }

    private function prepareNpmCacheDirectory(): void
    {
        File::ensureDirectoryExists($this->npmCacheDirectory());

        $this->line("Using npm cache: {$this->npmCacheDirectory()}");
    }

    /**
     * @return array<string, string>
     */
    private function frontendEnvironment(): array
    {
        return [
            'PUPPETEER_SKIP_DOWNLOAD' => '1',
            'NPM_CONFIG_CACHE' => $this->npmCacheDirectory(),
            'npm_config_cache' => $this->npmCacheDirectory(),
        ];
    }

    private function npmCacheDirectory(): string
    {
        return base_path(self::NPM_CACHE_RELATIVE_PATH);
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
     * @param  array<int, string>  $heartbeatMessages
     */
    private function runProcess(array $command, string $description, int $timeoutSeconds, array $environment = [], array $heartbeatMessages = []): bool
    {
        $result = $this->executeProcess($command, $timeoutSeconds, $environment, $heartbeatMessages);

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
     * @param  array<int, string>  $heartbeatMessages
     */
    private function executeProcess(array $command, int $timeoutSeconds, array $environment = [], array $heartbeatMessages = []): ProcessResultContract
    {
        $process = Process::timeout($timeoutSeconds)
            ->path(base_path());

        if ($environment !== []) {
            $process = $process->env($environment);
        }

        if ($heartbeatMessages !== []) {
            return $this->executeProcessWithHeartbeat($process, $command, $heartbeatMessages);
        }

        return $process->run($command, function (string $type, string $buffer): void {
            $this->output->write($buffer);
        });
    }

    /**
     * @param  array<int, string>  $command
     * @param  array<int, string>  $heartbeatMessages
     */
    private function executeProcessWithHeartbeat(PendingProcess $process, array $command, array $heartbeatMessages): ProcessResultContract
    {
        $lastOutputAt = microtime(true);
        $nextHeartbeatAt = $lastOutputAt + $this->processHeartbeatIntervalSeconds();
        $heartbeatMessageIndex = 0;

        $runningProcess = $process->start($command, function (string $type, string $buffer) use (&$lastOutputAt): void {
            $lastOutputAt = microtime(true);

            $this->output->write($buffer);
        });

        while ($runningProcess->running()) {
            if (method_exists($runningProcess, 'ensureNotTimedOut')) {
                $runningProcess->ensureNotTimedOut();
            }

            $now = microtime(true);
            if ($now >= $nextHeartbeatAt && $now - $lastOutputAt >= $this->processHeartbeatIntervalSeconds()) {
                $this->waitingLine($heartbeatMessages[$heartbeatMessageIndex % count($heartbeatMessages)]);

                $heartbeatMessageIndex++;
                $nextHeartbeatAt = $now + $this->processHeartbeatIntervalSeconds();
            }

            $this->pauseBeforeProcessHeartbeatCheck();
        }

        return $runningProcess->wait();
    }

    protected function processHeartbeatIntervalSeconds(): int
    {
        if (app()->runningUnitTests()) {
            return 0;
        }

        return self::PROCESS_HEARTBEAT_INTERVAL_SECONDS;
    }

    private function pauseBeforeProcessHeartbeatCheck(): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        sleep(1);
    }

    /**
     * @return array<int, string>
     */
    private function npmInstallHeartbeatMessages(): array
    {
        return [
            'Still installing dependencies. npm is sorting versions, scripts, and small opinions.',
            'Still here. node_modules is getting rebuilt piece by piece.',
            'Dependencies are still landing. This is the quiet part with the most tiny boxes.',
        ];
    }

    /**
     * @return array<int, string>
     */
    private function viteBuildHeartbeatMessages(): array
    {
        return [
            'Still building. Vite is transforming modules and keeping count.',
            'Still building. Rollup is packing the frontend suitcase.',
            'Assets are being bundled, hashed, and folded into place.',
        ];
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

    private function maybeShowNpmCachePermissionHint(string $output): void
    {
        $normalizedOutput = strtolower($output);

        if (! str_contains($normalizedOutput, 'cache folder contains root-owned files')
            && ! (str_contains($normalizedOutput, 'eacces') && str_contains($normalizedOutput, '/.npm'))) {
            return;
        }

        $this->warn("Hint: npm reported an unwritable home cache. app:update uses {$this->npmCacheDirectory()}; if this persists, remove any shell override for npm_config_cache/NPM_CONFIG_CACHE.");
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
