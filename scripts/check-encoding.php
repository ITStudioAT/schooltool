#!/usr/bin/env php
<?php

declare(strict_types=1);

$repositoryRoot = realpath(__DIR__.'/..');

if ($repositoryRoot === false) {
    fwrite(STDERR, "Unable to resolve repository root.\n");
    exit(1);
}

chdir($repositoryRoot);

$trackedFiles = shell_exec('git ls-files -z --cached --others --exclude-standard');

if (! is_string($trackedFiles)) {
    fwrite(STDERR, "Unable to enumerate tracked files via git ls-files.\n");
    exit(1);
}

$knownBinaryExtensions = [
    '7z',
    'ai',
    'avif',
    'bin',
    'class',
    'dll',
    'doc',
    'docx',
    'eot',
    'exe',
    'gif',
    'gz',
    'ico',
    'jpeg',
    'jpg',
    'mp3',
    'mp4',
    'otf',
    'pdf',
    'phar',
    'png',
    'ppt',
    'pptx',
    'psd',
    'rar',
    'so',
    'sqlite',
    'ttf',
    'wav',
    'webm',
    'webp',
    'woff',
    'woff2',
    'xls',
    'xlsx',
    'zip',
];

$germanCharacters = [
    mb_chr(0x00E4, 'UTF-8'),
    mb_chr(0x00F6, 'UTF-8'),
    mb_chr(0x00FC, 'UTF-8'),
    mb_chr(0x00DF, 'UTF-8'),
    mb_chr(0x00C4, 'UTF-8'),
    mb_chr(0x00D6, 'UTF-8'),
    mb_chr(0x00DC, 'UTF-8'),
];

$punctuationCharacters = [
    mb_chr(0x00B7, 'UTF-8'),
    mb_chr(0x2013, 'UTF-8'),
    mb_chr(0x2014, 'UTF-8'),
    mb_chr(0x201C, 'UTF-8'),
    mb_chr(0x201E, 'UTF-8'),
    mb_chr(0x2019, 'UTF-8'),
    mb_chr(0x2018, 'UTF-8'),
    mb_chr(0x2026, 'UTF-8'),
    mb_chr(0x20AC, 'UTF-8'),
];

$suspiciousSequences = [
    ...suspiciousMojibakeSequences($germanCharacters),
    ...suspiciousMojibakeSequences($punctuationCharacters),
    mb_chr(0x00C3, 'UTF-8'),
    mb_chr(0x00C2, 'UTF-8'),
    mb_chr(0x00E2, 'UTF-8'),
    mb_chr(0x00F0, 'UTF-8'),
    mb_chr(0xFFFD, 'UTF-8'),
];

$allowedSuspiciousSequences = [];

$bomViolations = [];
$utf16Violations = [];
$utf8Violations = [];
$mojibakeViolations = [];

foreach (array_filter(explode("\0", $trackedFiles)) as $relativePath) {
    $fullPath = $repositoryRoot.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativePath);

    if (! is_file($fullPath)) {
        continue;
    }

    $contents = file_get_contents($fullPath);

    if (! is_string($contents)) {
        continue;
    }

    if (! shouldCheckFile($relativePath, $contents, $knownBinaryExtensions)) {
        continue;
    }

    if (str_starts_with($contents, "\xEF\xBB\xBF")) {
        $bomViolations[] = $relativePath;
    }

    if (str_starts_with($contents, "\xFF\xFE") || str_starts_with($contents, "\xFE\xFF")) {
        $utf16Violations[] = $relativePath;

        continue;
    }

    if (! mb_check_encoding($contents, 'UTF-8')) {
        $utf8Violations[] = $relativePath;

        continue;
    }

    $unexpectedSequences = array_values(array_filter(
        array_unique($suspiciousSequences),
        static fn (string $sequence): bool => str_contains($contents, $sequence)
    ));

    if ($unexpectedSequences === []) {
        continue;
    }

    $allowedSequences = $allowedSuspiciousSequences[$relativePath] ?? [];
    $unexpectedSequences = array_values(array_diff($unexpectedSequences, $allowedSequences));

    if ($unexpectedSequences !== []) {
        $mojibakeViolations[$relativePath] = $unexpectedSequences;
    }
}

if ($bomViolations === [] && $utf16Violations === [] && $utf8Violations === [] && $mojibakeViolations === []) {
    echo "Encoding check passed: tracked text files are UTF-8 without BOM.\n";

    exit(0);
}

fwrite(STDERR, "Encoding check failed.\n");

if ($bomViolations !== []) {
    fwrite(STDERR, "\nFiles with UTF-8 BOM:\n");

    foreach ($bomViolations as $path) {
        fwrite(STDERR, " - {$path}\n");
    }
}

if ($utf16Violations !== []) {
    fwrite(STDERR, "\nFiles with UTF-16 BOM:\n");

    foreach ($utf16Violations as $path) {
        fwrite(STDERR, " - {$path}\n");
    }
}

if ($utf8Violations !== []) {
    fwrite(STDERR, "\nFiles that are not valid UTF-8:\n");

    foreach ($utf8Violations as $path) {
        fwrite(STDERR, " - {$path}\n");
    }
}

if ($mojibakeViolations !== []) {
    fwrite(STDERR, "\nFiles containing suspicious mojibake sequences:\n");

    foreach ($mojibakeViolations as $path => $sequences) {
        fwrite(STDERR, ' - '.$path.' -> '.implode(', ', $sequences)."\n");
    }
}

exit(1);

/**
 * @param  array<int, string>  $knownBinaryExtensions
 */
function shouldCheckFile(string $relativePath, string $contents, array $knownBinaryExtensions): bool
{
    $extension = strtolower(pathinfo($relativePath, PATHINFO_EXTENSION));

    if ($extension !== '' && in_array($extension, $knownBinaryExtensions, true)) {
        return false;
    }

    if (preg_match('/\x00/', substr($contents, 0, 8192)) === 1) {
        return false;
    }

    return true;
}

/**
 * @param  array<int, string>  $characters
 * @return array<int, string>
 */
function suspiciousMojibakeSequences(array $characters): array
{
    $sequences = [];

    foreach ($characters as $character) {
        $singleMojibake = mojibakeSequence($character);
        $sequences[] = $singleMojibake;
        $sequences[] = mojibakeSequence($singleMojibake);
    }

    return array_values(array_unique($sequences));
}

function mojibakeSequence(string $value): string
{
    return mb_convert_encoding($value, 'UTF-8', 'Windows-1252');
}
