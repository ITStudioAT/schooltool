<?php

namespace App\Support;

use Illuminate\Support\Str;

final class PrivateImportSourceFile
{
    public static function resolve(?string $relativePath, string $expectedRelativeDirectory): ?string
    {
        if (! is_string($relativePath) || trim($relativePath) === '') {
            return null;
        }

        $normalizedPath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, trim($relativePath));
        $normalizedDirectory = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, trim($expectedRelativeDirectory));
        $resolvedFile = realpath(storage_path($normalizedPath));
        $resolvedDirectory = realpath(storage_path($normalizedDirectory));

        if ($resolvedFile === false || $resolvedDirectory === false || ! is_file($resolvedFile)) {
            return null;
        }

        $directoryPrefix = rtrim($resolvedDirectory, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;

        return str_starts_with($resolvedFile, $directoryPrefix) ? $resolvedFile : null;
    }

    public static function downloadName(?string $originalFilename, string $fallbackBaseName, string $extension): string
    {
        $normalizedFilename = str_replace('\\', '/', trim((string) $originalFilename));
        $baseName = pathinfo(basename($normalizedFilename), PATHINFO_FILENAME);
        $safeBaseName = Str::slug($baseName) ?: Str::slug($fallbackBaseName) ?: 'import';
        $safeExtension = preg_replace('/[^a-z0-9]/', '', Str::lower($extension)) ?: 'dat';

        return "{$safeBaseName}.{$safeExtension}";
    }
}
