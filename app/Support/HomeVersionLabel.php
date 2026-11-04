<?php

namespace App\Support;

final class HomeVersionLabel
{
    public static function forProject(string $projectDirectory, string $fallbackVersion): string
    {
        $branch = self::branchName($projectDirectory);

        if ($branch === null || $branch === 'main') {
            return "v{$fallbackVersion}";
        }

        $branchVersion = self::branchVersion($projectDirectory, $branch);
        $branchLabel = $branchVersion === null ? 'keine Versionsangabe' : "v{$branchVersion}";

        return "App-Version: v{$fallbackVersion} · {$branch}: {$branchLabel}";
    }

    private static function branchName(string $projectDirectory): ?string
    {
        $gitPath = $projectDirectory.DIRECTORY_SEPARATOR.'.git';
        $gitDirectory = $gitPath;

        if (is_file($gitPath)) {
            $pointer = file_get_contents($gitPath);
            if (! is_string($pointer) || ! preg_match('/\Agitdir: (.+)\z/', trim($pointer), $matches)) {
                return null;
            }

            $gitDirectory = $matches[1];
            if (! str_starts_with($gitDirectory, '/') && ! preg_match('/\A[A-Za-z]:[\\\\\/]/', $gitDirectory)) {
                $gitDirectory = $projectDirectory.DIRECTORY_SEPARATOR.$gitDirectory;
            }
        }

        $resolvedGitDirectory = realpath($gitDirectory);
        if ($resolvedGitDirectory === false) {
            return null;
        }

        $headPath = $resolvedGitDirectory.DIRECTORY_SEPARATOR.'HEAD';
        if (! is_file($headPath)) {
            return null;
        }

        $head = file_get_contents($headPath);
        if (! is_string($head) || ! preg_match('/\Aref: refs\/heads\/([A-Za-z0-9][A-Za-z0-9._\/-]*)\z/', trim($head), $matches)) {
            return null;
        }

        return $matches[1];
    }

    private static function branchVersion(string $projectDirectory, string $branch): ?string
    {
        if (! preg_match('/\Afeature\/([a-z0-9]+(?:-[a-z0-9]+)*)\z/', $branch, $matches)) {
            return null;
        }

        $path = $projectDirectory.DIRECTORY_SEPARATOR."UPDATES-{$matches[1]}.md";
        if (! is_file($path)) {
            return null;
        }

        $contents = file_get_contents($path);
        if (! is_string($contents)
            || ! preg_match('/^##[ \t]+([^\r\n]+)/m', $contents, $heading)
            || ! preg_match('/\A\d+(?:\.\d+){1,2}(?:[ \t]+!!!)?\z/', trim($heading[1]), $version)) {
            return null;
        }

        return preg_replace('/[ \t]+!!!\z/', '', $version[0]);
    }
}
