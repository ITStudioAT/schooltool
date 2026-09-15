<?php

declare(strict_types=1);

namespace SchoolTool\DeploymentStatus;

const STATES = ['idle', 'scheduled', 'maintenance', 'completed', 'failed'];

/** @return array{state: string, id: ?string} */
function readStatus(string $projectDirectory): array
{
    $path = $projectDirectory.'/storage/framework/deployment-status.json';
    $contents = is_file($path) ? @file_get_contents($path) : false;
    $stored = $contents === false ? null : json_decode($contents, true);
    $state = is_array($stored) && in_array($stored['state'] ?? null, STATES, true)
        ? $stored['state']
        : 'idle';
    $id = is_array($stored) && is_string($stored['id'] ?? null)
        && preg_match('/\A[a-f0-9]{32}\z/', $stored['id']) === 1
        ? $stored['id']
        : null;

    if (is_file($projectDirectory.'/storage/framework/down')) {
        $state = $state === 'failed' ? 'failed' : 'maintenance';
    } elseif (in_array($state, ['maintenance', 'failed'], true)) {
        $state = 'idle';
    }

    return ['state' => $state, 'id' => $id];
}

function writeStatus(string $projectDirectory, string $state): void
{
    if (! in_array($state, STATES, true)) {
        throw new \InvalidArgumentException('Unknown deployment status.');
    }

    $id = $state === 'scheduled'
        ? bin2hex(random_bytes(16))
        : readStatus($projectDirectory)['id'];

    if ($state === 'idle') {
        $id = null;
    }

    $path = $projectDirectory.'/storage/framework/deployment-status.json';
    $temporaryPath = $path.'.'.bin2hex(random_bytes(8)).'.tmp';
    $contents = json_encode(['state' => $state, 'id' => $id], JSON_THROW_ON_ERROR)."\n";

    try {
        if (file_put_contents($temporaryPath, $contents, LOCK_EX) !== strlen($contents)) {
            throw new \RuntimeException('Could not write deployment status.');
        }

        if (! rename($temporaryPath, $path)) {
            throw new \RuntimeException('Could not publish deployment status.');
        }
    } finally {
        if (is_file($temporaryPath)) {
            unlink($temporaryPath);
        }
    }
}

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    try {
        writeStatus(dirname(__DIR__), $argv[1] ?? '');
    } catch (\Throwable $exception) {
        fwrite(STDERR, $exception->getMessage()."\n");
        exit(1);
    }
}
