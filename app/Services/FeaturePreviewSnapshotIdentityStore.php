<?php

namespace App\Services;

use App\Models\User;
use RuntimeException;
use Throwable;

class FeaturePreviewSnapshotIdentityStore
{
    public function matchesAuthentication(User $liveUser): bool
    {
        try {
            $fingerprint = $this->read()['users'][(string) $liveUser->getKey()] ?? null;

            return is_string($fingerprint) && hash_equals($fingerprint, FeaturePreviewService::authenticationFingerprint($liveUser));
        } catch (Throwable) {
            return false;
        }
    }

    public function directory(): string
    {
        $configured = (string) config('schooltool.preview.snapshot_directory');
        $directory = realpath($configured);
        $application = realpath(base_path());
        if ($configured === '' || $directory === false || $application === false || is_link($configured)
            || str_replace('\\', '/', $configured) !== str_replace('\\', '/', $directory)
            || str_starts_with(strtolower(str_replace('\\', '/', $directory)).'/', strtolower(str_replace('\\', '/', $application)).'/')
            || (PHP_OS_FAMILY !== 'Windows' && (fileperms($directory) & 0077) !== 0)) {
            throw new RuntimeException('Configure an existing private snapshot directory outside the application, with permissions 0700 and no symlinks.');
        }

        return $directory;
    }

    /** @return array<string, mixed> */
    public function read(string $name = 'snapshot-state.json'): array
    {
        $path = $this->path($name);
        if (! is_file($path)) {
            return [];
        }
        $this->assertPrivateFile($path);
        $contents = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        if (! is_array($contents)) {
            throw new RuntimeException('Invalid preview snapshot state.');
        }

        return $contents;
    }

    /** @param array<string, mixed> $state */
    public function write(array $state, string $name = 'snapshot-state.json'): void
    {
        $path = $this->path($name);
        $temporary = $path.'.'.bin2hex(random_bytes(8));
        $mask = umask(0077);
        try {
            $handle = fopen($temporary, 'x+b');
        } finally {
            umask($mask);
        }
        if ($handle === false) {
            throw new RuntimeException('Cannot create private preview snapshot state.');
        }
        try {
            if (! chmod($temporary, 0600)) {
                throw new RuntimeException('Cannot protect the private preview snapshot state.');
            }
            $content = json_encode($state, JSON_THROW_ON_ERROR);
            if (fwrite($handle, $content) !== strlen($content) || ! fflush($handle)) {
                throw new RuntimeException('Cannot write private preview snapshot state.');
            }
            fclose($handle);
            $handle = null;
            if (is_link($path) || ! rename($temporary, $path)) {
                throw new RuntimeException('Cannot activate private preview snapshot state.');
            }
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }
            if (is_file($temporary)) {
                unlink($temporary);
            }
        }
    }

    public function path(string $name): string
    {
        if (preg_match('/\A[a-zA-Z0-9._-]+\z/', $name) !== 1 || str_contains($name, '..')) {
            throw new RuntimeException('Invalid private snapshot filename.');
        }

        return $this->directory().DIRECTORY_SEPARATOR.$name;
    }

    public function assertPrivateFile(string $path): void
    {
        if (! is_file($path) || is_link($path) || realpath(dirname($path)) !== $this->directory()
            || (PHP_OS_FAMILY !== 'Windows' && (fileperms($path) & 0077) !== 0)) {
            throw new RuntimeException('Snapshot files must be private regular files in the configured snapshot directory.');
        }
    }
}
