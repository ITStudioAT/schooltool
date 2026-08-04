<?php

declare(strict_types=1);

const SOURCE_MANIFEST_PATHS = [
    'app',
    'artisan',
    'bootstrap',
    'composer.json',
    'composer.lock',
    'config',
    'database',
    'lang',
    'package-lock.json',
    'package.json',
    'public',
    'resources',
    'routes',
    'scripts',
    'vite.config.js',
];

const SOURCE_MANIFEST_EXCLUDED_PATHS = [
    'bootstrap/cache',
    'database/database.sqlite',
    'public/build',
    'public/hot',
    'public/storage',
    'resources/js/actions',
    'resources/js/routes',
    'resources/js/wayfinder',
];

function projectPath(string $relativePath = ''): string
{
    $projectDirectory = dirname(__DIR__);

    return $relativePath === ''
        ? $projectDirectory
        : $projectDirectory.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
}

function normalizeRelativePath(string $path): string
{
    $relativePath = substr($path, strlen(projectPath()) + 1);

    return str_replace(DIRECTORY_SEPARATOR, '/', $relativePath);
}

function isExcludedSourcePath(string $relativePath): bool
{
    foreach (SOURCE_MANIFEST_EXCLUDED_PATHS as $excludedPath) {
        if ($relativePath === $excludedPath || str_starts_with($relativePath, "{$excludedPath}/")) {
            return true;
        }
    }

    return str_starts_with($relativePath, 'database/database.sqlite');
}

/** @return array<int, string> */
function sourceFiles(): array
{
    $files = [];

    foreach (SOURCE_MANIFEST_PATHS as $relativeRoot) {
        $absoluteRoot = projectPath($relativeRoot);

        if (is_file($absoluteRoot)) {
            $files[] = $relativeRoot;

            continue;
        }

        if (! is_dir($absoluteRoot)) {
            continue;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($absoluteRoot, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->isLink()) {
                continue;
            }

            $relativePath = normalizeRelativePath($file->getPathname());

            if (! isExcludedSourcePath($relativePath)) {
                $files[] = $relativePath;
            }
        }
    }

    sort($files, SORT_STRING);

    return array_values(array_unique($files));
}

function buildSourceManifest(): string
{
    $lines = [];

    foreach (sourceFiles() as $relativePath) {
        if (str_contains($relativePath, "\n") || str_contains($relativePath, "\r")) {
            throw new RuntimeException("Source path contains a line break: {$relativePath}");
        }

        $hash = hash_file('sha256', projectPath($relativePath));

        if ($hash === false) {
            throw new RuntimeException("Could not hash source file: {$relativePath}");
        }

        $lines[] = "{$hash}  {$relativePath}";
    }

    return implode("\n", $lines)."\n";
}

/** @return array<string, string> */
function parseSourceManifest(string $manifest): array
{
    $entries = [];

    foreach (preg_split('/\R/', trim($manifest)) ?: [] as $line) {
        if ($line === '') {
            continue;
        }

        if (! preg_match('/^([0-9a-f]{64})  (.+)$/', $line, $matches)) {
            throw new RuntimeException('The source manifest contains an invalid line.');
        }

        $entries[$matches[2]] = $matches[1];
    }

    return $entries;
}

function describeManifestMismatch(string $expected, string $actual): void
{
    $expectedEntries = parseSourceManifest($expected);
    $actualEntries = parseSourceManifest($actual);
    $differences = [];

    foreach ($expectedEntries as $path => $hash) {
        if (! array_key_exists($path, $actualEntries)) {
            $differences[] = "missing: {$path}";

            continue;
        }

        if ($actualEntries[$path] !== $hash) {
            $differences[] = "changed: {$path}";
        }
    }

    foreach (array_diff_key($actualEntries, $expectedEntries) as $path => $hash) {
        $differences[] = "unexpected: {$path}";
    }

    foreach (array_slice($differences, 0, 10) as $difference) {
        fwrite(STDERR, "  - {$difference}\n");
    }

    if (count($differences) > 10) {
        fwrite(STDERR, '  - and '.(count($differences) - 10)." more difference(s)\n");
    }
}

function writeSourceManifest(string $manifestPath): int
{
    $absoluteManifestPath = projectPath($manifestPath);
    $directory = dirname($absoluteManifestPath);

    if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
        fwrite(STDERR, "Could not create source manifest directory: {$directory}\n");

        return 1;
    }

    if (file_put_contents($absoluteManifestPath, buildSourceManifest()) === false) {
        fwrite(STDERR, "Could not write source manifest: {$absoluteManifestPath}\n");

        return 1;
    }

    fwrite(STDOUT, "Source manifest written to {$manifestPath}.\n");

    return 0;
}

function verifySourceManifest(string $manifestPath): int
{
    $absoluteManifestPath = projectPath($manifestPath);

    if (! is_file($absoluteManifestPath)) {
        fwrite(STDERR, "Source manifest is missing: {$manifestPath}\n");

        return 1;
    }

    $expected = file_get_contents($absoluteManifestPath);

    if ($expected === false) {
        fwrite(STDERR, "Could not read source manifest: {$manifestPath}\n");

        return 1;
    }

    $actual = buildSourceManifest();

    if (! hash_equals($expected, $actual)) {
        fwrite(STDERR, "The pulled source does not match its deployment release:\n");
        describeManifestMismatch($expected, $actual);
        fwrite(STDERR, "Pull main again after gitpush has completed.\n");

        return 1;
    }

    fwrite(STDOUT, "Deployment source manifest verified.\n");

    return 0;
}

function sourceManifestUsage(): int
{
    fwrite(STDERR, "Usage: php scripts/source-manifest.php <write|verify> <manifest-path>\n");

    return 2;
}

$command = $argv[1] ?? null;
$manifestPath = $argv[2] ?? null;

if (! is_string($manifestPath) || $manifestPath === '' || str_starts_with($manifestPath, '..')) {
    exit(sourceManifestUsage());
}

try {
    exit(match ($command) {
        'write' => writeSourceManifest($manifestPath),
        'verify' => verifySourceManifest($manifestPath),
        default => sourceManifestUsage(),
    });
} catch (Throwable $throwable) {
    fwrite(STDERR, $throwable->getMessage()."\n");

    exit(1);
}
