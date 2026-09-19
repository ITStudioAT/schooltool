<?php

declare(strict_types=1);

function updateProjectPath(string $relativePath = ''): string
{
    $projectDirectory = dirname(__DIR__);

    return $relativePath === ''
        ? $projectDirectory
        : $projectDirectory.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
}

function dotEnvValue(string $key): ?string
{
    $environmentPath = updateProjectPath('.env');

    if (! is_file($environmentPath)) {
        return null;
    }

    $lines = file($environmentPath, FILE_IGNORE_NEW_LINES);

    if ($lines === false) {
        return null;
    }

    foreach ($lines as $line) {
        if (! preg_match('/^\s*'.preg_quote($key, '/').'\s*=\s*(.*)\s*$/', $line, $matches)) {
            continue;
        }

        $value = trim($matches[1]);

        if (strlen($value) >= 2 && $value[0] === $value[strlen($value) - 1] && in_array($value[0], ['"', "'"], true)) {
            $value = substr($value, 1, -1);
        }

        return $value;
    }

    return null;
}

function requestedUpdateTarget(array $arguments): string
{
    foreach ($arguments as $argument) {
        if (str_starts_with($argument, '--target=')) {
            return substr($argument, strlen('--target='));
        }
    }

    $configuredTarget = getenv('SCHOOLTOOL_UPDATE_TARGET');

    if (is_string($configuredTarget) && $configuredTarget !== '') {
        return $configuredTarget;
    }

    if (PHP_OS_FAMILY === 'Windows') {
        return 'local';
    }

    $environment = getenv('APP_ENV') ?: dotEnvValue('APP_ENV');

    return $environment === 'production' ? 'cloudways' : 'local';
}

/** @param array<int, string> $command */
function displayUpdateCommand(array $command): string
{
    return implode(' ', array_map(
        fn (string $argument): string => str_contains($argument, ' ') ? '"'.$argument.'"' : $argument,
        $command,
    ));
}

/** @param array<int, string> $command */
function windowsShellCommand(array $command): string
{
    $executable = $command[0];

    if (! str_contains($executable, '/') && ! str_contains($executable, '\\')) {
        $pathDirectories = explode(PATH_SEPARATOR, getenv('PATH') ?: '');
        $extensions = explode(';', getenv('PATHEXT') ?: '.COM;.EXE;.BAT;.CMD');

        foreach ($pathDirectories as $pathDirectory) {
            $pathDirectory = trim($pathDirectory, " \t\n\r\0\x0B\"");

            foreach ($extensions as $extension) {
                $candidate = $pathDirectory.DIRECTORY_SEPARATOR.$executable.strtolower($extension);

                if (is_file($candidate)) {
                    $executable = $candidate;
                    break 2;
                }
            }
        }
    }

    $command[0] = $executable;

    return 'call '.implode(' ', array_map(
        fn (string $argument): string => '"'.str_replace(['%', '"'], ['%%', '""'], $argument).'"',
        $command,
    ));
}

/** @param array<int, string>|string $command */
function runUpdateCommand(array|string $command): int
{
    fwrite(STDOUT, '$ '.(is_array($command) ? displayUpdateCommand($command) : $command)."\n");

    $process = proc_open($command, [STDIN, STDOUT, STDERR], $pipes, updateProjectPath());

    if (! is_resource($process)) {
        return 1;
    }

    return proc_close($process);
}

function writeDependencyState(string $statePath, string $lockHash): bool
{
    $stateDirectory = dirname($statePath);

    if (! is_dir($stateDirectory) && ! mkdir($stateDirectory, 0775, true) && ! is_dir($stateDirectory)) {
        return false;
    }

    return file_put_contents($statePath, "{$lockHash}\n") !== false;
}

function composerDependenciesAreCurrent(): bool
{
    $lockPath = updateProjectPath('composer.lock');
    $statePath = updateProjectPath('storage/framework/composer-dependencies.sha256');
    $installedPath = updateProjectPath('vendor/composer/installed.php');

    if (! is_file(updateProjectPath('vendor/autoload.php')) || ! is_file($installedPath)) {
        return false;
    }

    $installed = require $installedPath;

    if (! is_array($installed) || ! ($installed['root']['dev'] ?? false)) {
        return false;
    }

    $lockHash = hash_file('sha256', $lockPath);

    if (! is_string($lockHash)) {
        return false;
    }

    if (is_file($statePath)) {
        $savedHash = trim((string) file_get_contents($statePath));

        return hash_equals($lockHash, $savedHash);
    }

    $lockModifiedAt = filemtime($lockPath);
    $installedModifiedAt = filemtime($installedPath);

    if ($lockModifiedAt === false || $installedModifiedAt === false || $installedModifiedAt < $lockModifiedAt) {
        return false;
    }

    if (! writeDependencyState($statePath, $lockHash)) {
        fwrite(STDERR, "Could not initialize the Composer dependency state; continuing with the existing installation.\n");
    }

    return true;
}

function installComposerDependencies(): int
{
    if (composerDependenciesAreCurrent()) {
        fwrite(STDOUT, "Composer dependencies match composer.lock; skipping composer install.\n");

        return 0;
    }

    $command = [
        'composer',
        'install',
        '--prefer-dist',
        '--no-interaction',
        '--no-progress',
    ];

    $exitCode = runUpdateCommand(PHP_OS_FAMILY === 'Windows' ? windowsShellCommand($command) : $command);

    if ($exitCode !== 0) {
        return $exitCode;
    }

    $lockHash = hash_file('sha256', updateProjectPath('composer.lock'));

    if (! is_string($lockHash)
        || ! writeDependencyState(updateProjectPath('storage/framework/composer-dependencies.sha256'), $lockHash)) {
        fwrite(STDERR, "Could not save the Composer dependency state.\n");

        return 1;
    }

    return 0;
}

function frontendDependenciesAreCurrent(): bool
{
    $lockPath = updateProjectPath('package-lock.json');
    $statePath = updateProjectPath('storage/framework/frontend-dependencies.sha256');
    $installedLockPath = updateProjectPath('node_modules/.package-lock.json');

    if (! is_dir(updateProjectPath('node_modules'))) {
        return false;
    }

    if (! is_file($installedLockPath)) {
        return false;
    }

    $lockHash = hash_file('sha256', $lockPath);

    if (! is_string($lockHash)) {
        return false;
    }

    if (is_file($statePath)) {
        $savedHash = trim((string) file_get_contents($statePath));

        return hash_equals($lockHash, $savedHash);
    }

    $lockModifiedAt = filemtime($lockPath);
    $installedLockModifiedAt = filemtime($installedLockPath);

    if ($lockModifiedAt === false || $installedLockModifiedAt === false || $installedLockModifiedAt < $lockModifiedAt) {
        return false;
    }

    if (! writeDependencyState($statePath, $lockHash)) {
        fwrite(STDERR, "Could not initialize the frontend dependency state; continuing with the existing installation.\n");
    }

    return true;
}

function installFrontendDependencies(): int
{
    if (frontendDependenciesAreCurrent()) {
        fwrite(STDOUT, "Frontend dependencies match package-lock.json; skipping npm ci.\n");

        return 0;
    }

    $command = ['npm', 'ci'];
    $exitCode = runUpdateCommand(PHP_OS_FAMILY === 'Windows' ? windowsShellCommand($command) : $command);

    if ($exitCode !== 0) {
        return $exitCode;
    }

    $lockHash = hash_file('sha256', updateProjectPath('package-lock.json'));

    if (! is_string($lockHash)
        || ! writeDependencyState(updateProjectPath('storage/framework/frontend-dependencies.sha256'), $lockHash)) {
        fwrite(STDERR, "Could not save the frontend dependency state.\n");

        return 1;
    }

    return 0;
}

/** @param array<int, string> $arguments */
function localUpdateGitOutput(array $arguments): string
{
    $errors = tmpfile();
    if ($errors === false) {
        throw new RuntimeException('Could not initialize the local deployment Git check.');
    }

    $process = proc_open(
        ['git', ...$arguments],
        [['pipe', 'r'], ['pipe', 'w'], $errors],
        $pipes,
        updateProjectPath(),
        array_merge(getenv(), ['GIT_TERMINAL_PROMPT' => '0', 'GCM_INTERACTIVE' => 'Never']),
    );
    if (! is_resource($process)) {
        fclose($errors);
        throw new RuntimeException('Git is required for a full local deployment.');
    }

    fclose($pipes[0]);
    $output = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $exitCode = proc_close($process);
    fclose($errors);

    if ($exitCode !== 0 || $output === false) {
        throw new RuntimeException('The local deployment Git check failed. Verify Git and access to origin; no application update was started.');
    }

    return trim($output);
}

function assertLocalUpdateUsesPublishedMain(): void
{
    $root = realpath(localUpdateGitOutput(['rev-parse', '--show-toplevel']));
    $project = realpath(updateProjectPath());
    if ($root === false || $project === false
        || (PHP_OS_FAMILY === 'Windows' ? strcasecmp($root, $project) !== 0 : $root !== $project)) {
        throw new RuntimeException('A full local deployment must run inside its own Git checkout.');
    }

    $branch = localUpdateGitOutput(['symbolic-ref', '--quiet', '--short', 'HEAD']);
    if ($branch !== 'main') {
        throw new RuntimeException('A full local deployment requires main. Run gitmain first.');
    }

    foreach (['MERGE_HEAD', 'CHERRY_PICK_HEAD', 'REVERT_HEAD', 'rebase-merge', 'rebase-apply', 'BISECT_LOG'] as $operation) {
        $path = localUpdateGitOutput(['rev-parse', '--path-format=absolute', '--git-path', $operation]);
        if (file_exists($path)) {
            throw new RuntimeException('Finish the current Git operation before a full local deployment.');
        }
    }

    if (localUpdateGitOutput(['status', '--porcelain=v1', '--untracked-files=all']) !== '') {
        throw new RuntimeException('A full local deployment requires a clean main, including untracked files. Save your changes first.');
    }

    localUpdateGitOutput(['fetch', '--no-tags', '--no-recurse-submodules', 'origin', 'refs/heads/main:refs/remotes/origin/main']);
    $local = localUpdateGitOutput(['rev-parse', '--verify', 'HEAD']);
    $published = localUpdateGitOutput(['rev-parse', '--verify', 'refs/remotes/origin/main']);
    if ($local !== $published) {
        throw new RuntimeException('A full local deployment requires the current published origin/main. Run gitmain successfully first.');
    }

    if (localUpdateGitOutput(['symbolic-ref', '--quiet', '--short', 'HEAD']) !== 'main'
        || localUpdateGitOutput(['status', '--porcelain=v1', '--untracked-files=all']) !== '') {
        throw new RuntimeException('The checkout changed during the local deployment check. Start again from clean main.');
    }
}

function runLocalUpdate(bool $prepareOnly): int
{
    fwrite(STDOUT, "Update target: Windows development workstation.\n");

    if (! $prepareOnly) {
        try {
            assertLocalUpdateUsesPublishedMain();
        } catch (RuntimeException $exception) {
            fwrite(STDERR, $exception->getMessage()."\n");

            return 1;
        }
    }

    $composerExitCode = installComposerDependencies();

    if ($composerExitCode !== 0) {
        return $composerExitCode;
    }

    $npmExitCode = installFrontendDependencies();

    if ($npmExitCode !== 0 || $prepareOnly) {
        return $npmExitCode;
    }

    $artifactExitCode = runUpdateCommand([
        PHP_BINARY,
        updateProjectPath('scripts/frontend-release.php'),
        'install',
    ]);

    if ($artifactExitCode !== 0) {
        return $artifactExitCode;
    }

    return runUpdateCommand([
        PHP_BINARY,
        updateProjectPath('artisan'),
        'app:update',
        '--no-interaction',
        '--skip-frontend',
        '--ansi',
    ]);
}

function updateUsage(): int
{
    fwrite(STDERR, "Usage: php scripts/update.php [--target=local|cloudways] [--prepare] [--dry-run]\n");

    return 2;
}

$arguments = array_slice($argv, 1);
if (filter_var(getenv('SCHOOLTOOL_PREVIEW_INSTANCE') ?: false, FILTER_VALIDATE_BOOLEAN)
    || filter_var(dotEnvValue('SCHOOLTOOL_PREVIEW_INSTANCE') ?: false, FILTER_VALIDATE_BOOLEAN)) {
    fwrite(STDERR, "Production/local deployment is disabled on the preview instance. Use gitpreview from a development workstation.\n");
    exit(1);
}
$target = requestedUpdateTarget($arguments);
$prepareOnly = in_array('--prepare', $arguments, true);
$dryRun = in_array('--dry-run', $arguments, true);

if (! in_array($target, ['local', 'cloudways'], true)) {
    exit(updateUsage());
}

if ($dryRun) {
    fwrite(STDOUT, "Update target: {$target}\n");
    fwrite(STDOUT, $target === 'local'
        ? "Plan: Composer dependencies, changed frontend dependencies, verified release artifact, application update.\n"
        : "Plan: guarded Cloudways production deployment.\n");

    exit(0);
}

if ($target === 'local') {
    $exitCode = runLocalUpdate($prepareOnly);
    if ($exitCode === 0 && ! $prepareOnly) {
        echo 'Abgeschlossen: ', (new DateTimeImmutable('now', new DateTimeZone('Europe/Vienna')))->format('d.m.Y H:i:s T'), ' (Europe/Vienna)', PHP_EOL;
    }
    exit($exitCode);
}

fwrite(STDOUT, "Update target: Cloudways production.\n");

$cloudwaysCommand = ['bash', updateProjectPath('scripts/deploy_cloudways.sh')];

if ($prepareOnly) {
    $cloudwaysCommand[] = '--prepare';
}

exit(runUpdateCommand($cloudwaysCommand));
