<?php

declare(strict_types=1);

const FRONTEND_RELEASE_ARCHIVE = 'deployment/frontend-build.tar.gz';
const FRONTEND_RELEASE_ARCHIVE_HASH = 'deployment/frontend-build.sha256';
const FRONTEND_RELEASE_SOURCE = 'deployment/source-commit';
const FRONTEND_RELEASE_MANIFEST = 'deployment/source-manifest.sha256';
const FRONTEND_ENVIRONMENT_VERSIONS = 'environment-versions.json';
const WINDOWS_FRONTEND_RELEASE_MOVE_MAX_ATTEMPTS = 3;
const WINDOWS_FRONTEND_RELEASE_MOVE_RETRY_DELAY_SECONDS = 2;

function releaseProjectPath(string $relativePath = ''): string
{
    $projectDirectory = dirname(__DIR__);

    return $relativePath === ''
        ? $projectDirectory
        : $projectDirectory.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
}

/** @param array<int, string> $command */
function runReleaseCommand(array $command): int
{
    $process = proc_open($command, [STDIN, STDOUT, STDERR], $pipes, releaseProjectPath());

    if (! is_resource($process)) {
        return 1;
    }

    return proc_close($process);
}

/** @param array<int, string> $command */
function releaseCommandOutput(array $command): string
{
    $process = proc_open(
        $command,
        [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ],
        $pipes,
        releaseProjectPath(),
    );

    if (! is_resource($process)) {
        throw new RuntimeException('Could not inspect the release commit.');
    }

    fclose($pipes[0]);
    $output = stream_get_contents($pipes[1]);
    $error = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);

    if ($exitCode !== 0 || ! is_string($output)) {
        throw new RuntimeException('Could not inspect the release commit: '.trim((string) $error));
    }

    return trim($output);
}

function removeReleaseDirectory(string $directory): void
{
    $projectPublicDirectory = realpath(releaseProjectPath('public'));
    $resolvedParent = realpath(dirname($directory));

    if ($projectPublicDirectory === false || $resolvedParent !== $projectPublicDirectory) {
        throw new RuntimeException("Refusing to remove unexpected release directory: {$directory}");
    }

    if (! is_dir($directory)) {
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );

    foreach ($iterator as $item) {
        $item->isDir() && ! $item->isLink()
            ? rmdir($item->getPathname())
            : unlink($item->getPathname());
    }

    rmdir($directory);
}

/**
 * @param  (Closure(string, string): bool)|null  $renameDirectory
 * @param  (Closure(): void)|null  $pauseBeforeRetry
 */
function moveReleaseDirectory(
    string $sourceDirectory,
    string $destinationDirectory,
    ?int $attemptLimit = null,
    ?Closure $renameDirectory = null,
    ?Closure $pauseBeforeRetry = null,
): bool {
    $attemptLimit = max(
        1,
        $attemptLimit ?? (PHP_OS_FAMILY === 'Windows' ? WINDOWS_FRONTEND_RELEASE_MOVE_MAX_ATTEMPTS : 1),
    );
    $renameDirectory ??= static fn (string $source, string $destination): bool => @rename($source, $destination);
    $pauseBeforeRetry ??= static function (): void {
        sleep(WINDOWS_FRONTEND_RELEASE_MOVE_RETRY_DELAY_SECONDS);
    };

    for ($attempt = 1; $attempt <= $attemptLimit; $attempt++) {
        clearstatcache(true, $sourceDirectory);
        clearstatcache(true, $destinationDirectory);

        if (file_exists($destinationDirectory) || is_link($destinationDirectory)) {
            return false;
        }

        if ($renameDirectory($sourceDirectory, $destinationDirectory)) {
            return true;
        }

        if ($attempt < $attemptLimit) {
            $pauseBeforeRetry();
        }
    }

    return false;
}

function validateReleaseSource(string $sourceCommit): void
{
    if (! preg_match('/^[0-9a-f]{40,64}$/', $sourceCommit)) {
        throw new RuntimeException('The frontend release source commit is invalid.');
    }
}

function validateFrontendManifest(string $buildDirectory): void
{
    $manifestPath = $buildDirectory.DIRECTORY_SEPARATOR.'manifest.json';

    if (! is_file($manifestPath)) {
        throw new RuntimeException('The frontend release does not contain manifest.json.');
    }

    $manifest = file_get_contents($manifestPath);

    if ($manifest === false) {
        throw new RuntimeException('The frontend manifest could not be read.');
    }

    json_decode($manifest, true, flags: JSON_THROW_ON_ERROR);
}

function writeFrontendArchiveHash(string $archivePath): void
{
    $archiveHash = hash_file('sha256', $archivePath);

    if (! is_string($archiveHash)) {
        throw new RuntimeException('The frontend release archive could not be hashed.');
    }

    if (file_put_contents(
        releaseProjectPath(FRONTEND_RELEASE_ARCHIVE_HASH),
        "{$archiveHash}  frontend-build.tar.gz\n",
    ) === false) {
        throw new RuntimeException('The frontend release archive hash could not be saved.');
    }
}

function verifyFrontendArchiveHash(string $archivePath): void
{
    $hashPath = releaseProjectPath(FRONTEND_RELEASE_ARCHIVE_HASH);

    if (! is_file($hashPath)) {
        throw new RuntimeException('The frontend release archive hash is missing.');
    }

    $storedHash = trim((string) file_get_contents($hashPath));

    if (! preg_match('/^(?<hash>[0-9a-f]{64})  frontend-build\.tar\.gz$/', $storedHash, $matches)) {
        throw new RuntimeException('The frontend release archive hash is invalid.');
    }

    $actualHash = hash_file('sha256', $archivePath);

    if (! is_string($actualHash) || ! hash_equals($matches['hash'], $actualHash)) {
        throw new RuntimeException('The frontend release archive checksum does not match.');
    }
}

/** @param array<int, string> $command */
function releaseRuntimeVersion(array $command, string $pattern, string $prefix = ''): ?string
{
    try {
        $output = releaseCommandOutput($command);
    } catch (RuntimeException) {
        return null;
    }

    if (preg_match($pattern, $output, $matches) !== 1) {
        return null;
    }

    return $prefix.$matches[1];
}

function writeFrontendEnvironmentVersions(string $buildDirectory): void
{
    $versions = [
        'composer' => releaseRuntimeVersion(
            ['composer', '--version', '--no-ansi'],
            '/Composer(?: version)?\s+(\d+(?:\.\d+){1,3})/i',
        ),
        'npm' => releaseRuntimeVersion(['npm', '--version'], '/^v?(\d+(?:\.\d+){1,3})/'),
        'node' => releaseRuntimeVersion(['node', '--version'], '/^v?(\d+(?:\.\d+){1,3})/', 'v'),
    ];

    $contents = json_encode($versions, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;

    if (file_put_contents($buildDirectory.DIRECTORY_SEPARATOR.FRONTEND_ENVIRONMENT_VERSIONS, $contents) === false) {
        throw new RuntimeException('Could not record the frontend environment versions.');
    }
}

function createFrontendRelease(string $sourceCommit): int
{
    validateReleaseSource($sourceCommit);

    $buildDirectory = releaseProjectPath('public/build');
    validateFrontendManifest($buildDirectory);

    $deploymentDirectory = releaseProjectPath('deployment');

    if (! is_dir($deploymentDirectory) && ! mkdir($deploymentDirectory, 0775, true) && ! is_dir($deploymentDirectory)) {
        throw new RuntimeException('The deployment directory could not be created.');
    }

    file_put_contents($buildDirectory.DIRECTORY_SEPARATOR.'deployment-source.txt', "{$sourceCommit}\n");
    writeFrontendEnvironmentVersions($buildDirectory);
    file_put_contents(releaseProjectPath(FRONTEND_RELEASE_SOURCE), "{$sourceCommit}\n");

    if (runReleaseCommand([
        PHP_BINARY,
        releaseProjectPath('scripts/source-manifest.php'),
        'write',
        FRONTEND_RELEASE_MANIFEST,
    ]) !== 0) {
        throw new RuntimeException('The deployment source manifest could not be created.');
    }

    $archivePath = releaseProjectPath(FRONTEND_RELEASE_ARCHIVE);

    if (is_file($archivePath) && ! unlink($archivePath)) {
        throw new RuntimeException('The previous frontend release archive could not be removed.');
    }

    if (runReleaseCommand([
        'tar',
        '-czf',
        $archivePath,
        '-C',
        $buildDirectory,
        '.',
    ]) !== 0) {
        throw new RuntimeException('The frontend release archive could not be created.');
    }

    writeFrontendArchiveHash($archivePath);

    fwrite(STDOUT, "Frontend release created for {$sourceCommit}.\n");

    return 0;
}

function releaseSourceCommit(?string $expectedSourceCommit = null): string
{
    $sourcePath = releaseProjectPath(FRONTEND_RELEASE_SOURCE);

    if (! is_file($sourcePath)) {
        throw new RuntimeException('The frontend release source marker is missing.');
    }

    $sourceCommit = trim((string) file_get_contents($sourcePath));
    validateReleaseSource($sourceCommit);

    if ($expectedSourceCommit !== null) {
        validateReleaseSource($expectedSourceCommit);
    }

    if ($expectedSourceCommit !== null && ! hash_equals($expectedSourceCommit, $sourceCommit)) {
        throw new RuntimeException("The frontend release belongs to {$sourceCommit}, not {$expectedSourceCommit}.");
    }

    return $sourceCommit;
}

function extractFrontendRelease(string $sourceCommit): string
{
    $archivePath = releaseProjectPath(FRONTEND_RELEASE_ARCHIVE);

    if (! is_file($archivePath)) {
        throw new RuntimeException('The frontend release archive is missing.');
    }

    verifyFrontendArchiveHash($archivePath);

    $temporaryDirectory = releaseProjectPath('public/.schooltool-release.'.bin2hex(random_bytes(6)));

    if (! mkdir($temporaryDirectory, 0775, true) && ! is_dir($temporaryDirectory)) {
        throw new RuntimeException('The temporary frontend release directory could not be created.');
    }

    if (runReleaseCommand(['tar', '-xzf', $archivePath, '-C', $temporaryDirectory]) !== 0) {
        removeReleaseDirectory($temporaryDirectory);

        throw new RuntimeException('The frontend release archive could not be extracted.');
    }

    try {
        validateFrontendManifest($temporaryDirectory);

        $innerSourcePath = $temporaryDirectory.DIRECTORY_SEPARATOR.'deployment-source.txt';
        $innerSourceCommit = is_file($innerSourcePath)
            ? trim((string) file_get_contents($innerSourcePath))
            : '';

        if (! hash_equals($sourceCommit, $innerSourceCommit)) {
            throw new RuntimeException('The frontend archive source marker does not match its release.');
        }
    } catch (Throwable $throwable) {
        removeReleaseDirectory($temporaryDirectory);

        throw $throwable;
    }

    return $temporaryDirectory;
}

function verifyFrontendRelease(?string $expectedSourceCommit = null): int
{
    if (runReleaseCommand([
        PHP_BINARY,
        releaseProjectPath('scripts/source-manifest.php'),
        'verify',
        FRONTEND_RELEASE_MANIFEST,
    ]) !== 0) {
        throw new RuntimeException('The pulled source and frontend release do not belong together.');
    }

    $sourceCommit = releaseSourceCommit($expectedSourceCommit);
    $temporaryDirectory = extractFrontendRelease($sourceCommit);
    removeReleaseDirectory($temporaryDirectory);

    fwrite(STDOUT, "Frontend release verified for {$sourceCommit}.\n");

    return 0;
}

function installFrontendRelease(): int
{
    if (runReleaseCommand([
        PHP_BINARY,
        releaseProjectPath('scripts/source-manifest.php'),
        'verify',
        FRONTEND_RELEASE_MANIFEST,
    ]) !== 0) {
        throw new RuntimeException('The pulled source and frontend release do not belong together.');
    }

    $sourceCommit = releaseSourceCommit();
    $temporaryDirectory = extractFrontendRelease($sourceCommit);
    $buildDirectory = releaseProjectPath('public/build');
    $backupDirectory = releaseProjectPath('public/.schooltool-build-backup.'.bin2hex(random_bytes(6)));

    if (is_dir($buildDirectory) && ! moveReleaseDirectory($buildDirectory, $backupDirectory)) {
        removeReleaseDirectory($temporaryDirectory);

        throw new RuntimeException('The existing frontend build could not be moved aside.');
    }

    if (! moveReleaseDirectory($temporaryDirectory, $buildDirectory)) {
        $rollbackSucceeded = true;

        if (is_dir($backupDirectory)) {
            $rollbackSucceeded = moveReleaseDirectory($backupDirectory, $buildDirectory);
        }

        removeReleaseDirectory($temporaryDirectory);

        if (! $rollbackSucceeded) {
            throw new RuntimeException(
                "The verified frontend release could not be installed. The previous frontend build could not be restored automatically and remains at {$backupDirectory}.",
            );
        }

        throw new RuntimeException('The verified frontend release could not be installed.');
    }

    if (is_dir($backupDirectory)) {
        removeReleaseDirectory($backupDirectory);
    }

    fwrite(STDOUT, "Frontend release installed for {$sourceCommit}.\n");

    return 0;
}

function frontendReleaseUsage(): int
{
    fwrite(STDERR, "Usage: php scripts/frontend-release.php <create SOURCE|verify [SOURCE]|install>\n");

    return 2;
}

/** @param array<int, string> $arguments */
function runFrontendRelease(array $arguments): int
{
    $command = $arguments[0] ?? null;

    return match ($command) {
        'create' => isset($arguments[1]) ? createFrontendRelease($arguments[1]) : frontendReleaseUsage(),
        'verify' => verifyFrontendRelease($arguments[1] ?? null),
        'install' => installFrontendRelease(),
        default => frontendReleaseUsage(),
    };
}

function isFrontendReleaseEntrypoint(): bool
{
    $scriptFilename = $_SERVER['SCRIPT_FILENAME'] ?? null;

    return is_string($scriptFilename) && realpath($scriptFilename) === __FILE__;
}

if (isFrontendReleaseEntrypoint()) {
    try {
        exit(runFrontendRelease(array_slice($argv, 1)));
    } catch (Throwable $throwable) {
        fwrite(STDERR, $throwable->getMessage()."\n");

        exit(1);
    }
}
