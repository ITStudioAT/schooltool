<?php

declare(strict_types=1);

/** Classifies immutable Git objects; the caller must authenticate the checked base and release artifacts. */
class SchooltoolReleasePolicy
{
    private const ARTIFACTS = [
        'deployment/frontend-build.tar.gz',
        'deployment/frontend-build.sha256',
        'deployment/source-commit',
        'deployment/source-manifest.sha256',
    ];

    /** @return array{lane: string, reason: string, base: string, head: string} */
    public static function classify(string $repository, string $base, string $head): array
    {
        self::validateRevisions($repository, $base, $head);
        $result = ['lane' => 'full', 'reason' => 'No documentation source changes.', 'base' => $base, 'head' => $head];
        $raw = self::git($repository, ['diff', '--raw', '--no-abbrev', '--no-renames', '-z', $base, $head, '--']);
        $entries = $raw === '' ? [] : explode("\0", rtrim($raw, "\0"));
        $sourceChanges = 0;

        if (count($entries) % 2 !== 0) {
            throw new RuntimeException('Malformed Git change list.');
        }

        for ($index = 0; $index < count($entries); $index += 2) {
            $path = $entries[$index + 1];
            $result['reason'] = 'Change requires full checks: '.$path;

            if (! preg_match('/\A:(\d{6}) (\d{6}) ([0-9a-f]{40}) ([0-9a-f]{40}) ([AM])\z/D', $entries[$index], $change)
                || $change[2] !== '100644'
                || ! in_array($change[1], ['000000', '100644'], true)) {
                return $result;
            }

            if (in_array($path, self::ARTIFACTS, true)) {
                continue;
            }

            $sourceChanges++;

            if (in_array($path, ['README.md', 'UPDATES.md'], true) || self::isDocumentationPath($path)) {
                continue;
            }

            if ($path === 'config/schooltool.php' && $change[5] === 'M'
                && self::isVersionOnlyChange(
                    self::git($repository, ['cat-file', 'blob', $change[3]]),
                    self::git($repository, ['cat-file', 'blob', $change[4]]),
                )) {
                continue;
            }

            return $result;
        }

        if ($sourceChanges > 0) {
            $result['lane'] = 'documentation';
            $result['reason'] = 'Only documentation and literal application version changes.';
        } else {
            $result['reason'] = 'No documentation source changes.';
        }

        return $result;
    }

    /** @return array{equivalent: bool, base: string, head: string} */
    public static function verifyFrontendEquivalence(string $repository, string $base, string $head): array
    {
        self::validateRevisions($repository, $base, $head);
        $before = self::frontendEntries($repository, $base);
        $after = self::frontendEntries($repository, $head);

        if ($before !== $after) {
            throw new RuntimeException('Frontend archive contents changed; full checks are required.');
        }

        return ['equivalent' => true, 'base' => $base, 'head' => $head];
    }

    private static function validateRevisions(string $repository, string $base, string $head): void
    {
        foreach ([$base, $head] as $commit) {
            if (! preg_match('/\A[0-9a-f]{40}\z/D', $commit)
                || trim(self::git($repository, ['cat-file', '-t', $commit])) !== 'commit') {
                throw new RuntimeException('Both revisions must be existing exact commit IDs.');
            }
        }

        self::git($repository, ['merge-base', '--is-ancestor', $base, $head]);
    }

    /** @return array<string, array{type: string, mode: int, hash: string}> */
    private static function frontendEntries(string $repository, string $commit): array
    {
        $source = trim(self::git($repository, ['show', $commit.':deployment/source-commit']));
        $parents = explode(' ', trim(self::git($repository, ['rev-list', '--parents', '-n', '1', $commit])));

        if (! preg_match('/\A[0-9a-f]{40}\z/D', $source) || $parents !== [$commit, $source]) {
            throw new RuntimeException('Frontend artifacts must bind their exact single source parent.');
        }

        foreach (self::ARTIFACTS as $path) {
            $entry = self::git($repository, ['ls-tree', $commit, '--', $path]);

            if (! preg_match('/\A100644 blob [0-9a-f]{40}\t'.preg_quote($path, '/').'\n\z/D', $entry)) {
                throw new RuntimeException('Release artifacts must be regular Git files.');
            }
        }

        $object = $commit.':deployment/frontend-build.tar.gz';
        $size = trim(self::git($repository, ['cat-file', '-s', $object]));

        if (! ctype_digit($size) || (int) $size > 64 * 1024 * 1024) {
            throw new RuntimeException('Frontend archive exceeds the bounded verification limit.');
        }

        $archive = self::git($repository, ['cat-file', 'blob', $object]);
        $checksum = trim(self::git($repository, ['show', $commit.':deployment/frontend-build.sha256']));

        if ($checksum !== hash('sha256', $archive).'  frontend-build.tar.gz') {
            throw new RuntimeException('Frontend archive checksum mismatch.');
        }

        $tar = self::decodeArchive($archive);

        if (strlen($tar) % 512 !== 0) {
            throw new RuntimeException('Invalid or oversized frontend gzip archive.');
        }

        $entries = [];
        $sourceFound = false;
        $ended = false;

        for ($offset = 0; $offset < strlen($tar);) {
            $header = substr($tar, $offset, 512);
            $offset += 512;

            if ($header === str_repeat("\0", 512)) {
                if (strlen($tar) - $offset < 512 || trim(substr($tar, $offset), "\0") !== '') {
                    throw new RuntimeException('Malformed frontend archive terminator.');
                }

                $ended = true;
                break;
            }

            $sum = array_sum(unpack('C*', substr_replace($header, str_repeat(' ', 8), 148, 8)));

            if ($sum !== self::tarOctal(substr($header, 148, 8)) || count($entries) >= 10000) {
                throw new RuntimeException('Invalid frontend tar header.');
            }

            $type = $header[156] === "\0" ? '0' : $header[156];
            $name = rtrim(substr($header, 0, 100), "\0");
            $prefix = rtrim(substr($header, 345, 155), "\0");
            $name = $prefix === '' ? $name : $prefix.'/'.$name;

            if (str_starts_with($name, '/') || ($type === '0' && str_ends_with($name, '/'))) {
                throw new RuntimeException('Unsafe frontend archive path.');
            }

            $name = str_starts_with($name, './') ? substr($name, 2) : $name;
            $name = rtrim($name, '/');
            $length = self::tarOctal(substr($header, 124, 12));
            $mode = self::tarOctal(substr($header, 100, 8));

            if (! in_array($type, ['0', '5'], true)
                || rtrim(substr($header, 157, 100), "\0") !== ''
                || $length > strlen($tar) - $offset
                || ($type === '5' && $length !== 0)) {
                throw new RuntimeException('Unsupported frontend archive entry.');
            }

            if (($name === '' || $name === '.') && $type === '5') {
                $name = '.';
            } else {
                foreach (explode('/', $name) as $component) {
                    if (! preg_match('/\A[A-Za-z0-9_.-]+\z/D', $component)
                        || in_array($component, ['.', '..'], true)) {
                        throw new RuntimeException('Unsafe frontend archive path.');
                    }
                }
            }

            if (isset($entries[$name])) {
                throw new RuntimeException('Duplicate frontend archive path.');
            }

            $contents = substr($tar, $offset, $length);
            $offset += (int) (ceil($length / 512) * 512);

            if ($name === 'deployment-source.txt') {
                if ($type !== '0' || $contents !== $source."\n") {
                    throw new RuntimeException('Frontend archive source marker mismatch.');
                }

                $sourceFound = true;
                $contents = '<BOUND-SOURCE>';
            }

            $entries[$name] = ['type' => $type, 'mode' => $mode, 'hash' => hash('sha256', $contents)];
        }

        if (! $ended || ! $sourceFound || ! isset($entries['manifest.json'], $entries['environment-versions.json'])) {
            throw new RuntimeException('Incomplete frontend archive.');
        }

        ksort($entries, SORT_STRING);

        return $entries;
    }

    private static function decodeArchive(string $archive): string
    {
        $context = inflate_init(ZLIB_ENCODING_GZIP);
        $decoded = '';

        for ($offset = 0; $offset < strlen($archive); $offset += 16384) {
            $chunk = @inflate_add($context, substr($archive, $offset, 16384));

            if ($chunk === false || strlen($decoded) + strlen($chunk) > 256 * 1024 * 1024) {
                throw new RuntimeException('Invalid or oversized frontend gzip archive.');
            }

            $decoded .= $chunk;

            if (inflate_get_status($context) === ZLIB_STREAM_END) {
                if (inflate_get_read_len($context) !== strlen($archive)) {
                    throw new RuntimeException('Trailing data or concatenated frontend gzip archives are forbidden.');
                }

                return $decoded;
            }
        }

        throw new RuntimeException('Incomplete frontend gzip archive.');
    }

    private static function tarOctal(string $value): int
    {
        $value = trim($value, "\0 ");

        if ($value === '' || ! preg_match('/\A[0-7]{1,11}\z/D', $value)) {
            throw new RuntimeException('Unsupported frontend archive number.');
        }

        return intval($value, 8);
    }

    private static function isDocumentationPath(string $path): bool
    {
        if (! str_starts_with($path, 'public/documentation/')) {
            return false;
        }

        foreach (explode('/', substr($path, strlen('public/documentation/'))) as $component) {
            if (! preg_match('/\A[A-Za-z0-9_][A-Za-z0-9_.-]*\z/D', $component)
                || preg_match('/\.(?:php[0-9]*|phtml|phar|cgi|pl|py|sh|shtml|asp|aspx)(?:\.|\z)/i', $component)) {
                return false;
            }
        }

        return in_array(pathinfo($path, PATHINFO_EXTENSION), [
            'html', 'css', 'js', 'json', 'txt', 'xml', 'map', 'svg', 'png', 'jpg', 'jpeg',
            'gif', 'ico', 'webp', 'avif', 'woff', 'woff2', 'ttf', 'eot', 'pdf',
        ], true);
    }

    private static function isVersionOnlyChange(string $before, string $after): bool
    {
        $old = self::withoutVersionLiteral($before);
        $new = self::withoutVersionLiteral($after);

        return $old !== null && $new !== null && $old === $new && $before !== $after;
    }

    private static function withoutVersionLiteral(string $contents): ?string
    {
        try {
            $tokens = token_get_all($contents, TOKEN_PARSE);
        } catch (ParseError) {
            return null;
        }

        $significant = [];
        $offset = 0;

        foreach ($tokens as $token) {
            $text = is_array($token) ? $token[1] : $token;
            $type = is_array($token) ? $token[0] : null;

            if (! in_array($type, [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                $significant[] = ['type' => $type, 'text' => $text, 'offset' => $offset];
            }

            $offset += strlen($text);
        }

        if (($significant[0]['type'] ?? null) !== T_OPEN_TAG
            || ($significant[1]['type'] ?? null) !== T_RETURN
            || ($significant[2]['text'] ?? null) !== '[') {
            return null;
        }

        $depth = 0;
        $version = null;

        foreach ($significant as $index => $token) {
            if ($depth === 1 && $token['type'] === T_CONSTANT_ENCAPSED_STRING
                && in_array($token['text'], ["'version'", '"version"'], true)
                && in_array($significant[$index - 1]['text'], ['[', ','], true)
                && ($significant[$index + 1]['type'] ?? null) === T_DOUBLE_ARROW) {
                $literal = $significant[$index + 2] ?? [];

                if ($version !== null || ($literal['type'] ?? null) !== T_CONSTANT_ENCAPSED_STRING
                    || ! preg_match('/\A([\'"])(?:0|[1-9][0-9]*)\.(?:0|[1-9][0-9]*)\.(?:0|[1-9][0-9]*)\1\z/D', $literal['text'])
                    || ! in_array($significant[$index + 3]['text'] ?? null, [',', ']'], true)) {
                    return null;
                }

                $version = $literal;
            }

            if ($token['type'] === null && in_array($token['text'], ['[', '(', '{'], true)) {
                $depth++;
            }

            if ($token['type'] === null && in_array($token['text'], [']', ')', '}'], true)) {
                $depth--;
            }
        }

        return $version === null ? null : substr_replace($contents, '<VERSION>', $version['offset'], strlen($version['text']));
    }

    /** @param list<string> $arguments */
    private static function git(string $repository, array $arguments): string
    {
        $process = proc_open(['git', '--no-replace-objects', '-C', $repository, ...$arguments], [
            0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w'],
        ], $pipes, $repository);

        if (! is_resource($process)) {
            throw new RuntimeException('Could not start Git for release classification.');
        }

        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]);
        stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        if (proc_close($process) !== 0 || $output === false) {
            throw new RuntimeException('Could not validate release commits or their ancestry.');
        }

        return $output;
    }
}

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    $base = '';
    $head = '';

    try {
        if (count($argv) !== 6 || ! in_array($argv[1], ['classify', 'assets'], true) || $argv[2] !== '--base' || $argv[4] !== '--head') {
            throw new RuntimeException('Usage: release-policy.php classify|assets --base <exact-commit> --head <exact-commit>');
        }

        $base = $argv[3];
        $head = $argv[5];
        $result = $argv[1] === 'assets'
            ? SchooltoolReleasePolicy::verifyFrontendEquivalence(getcwd(), $base, $head)
            : SchooltoolReleasePolicy::classify(getcwd(), $base, $head);
        echo json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES).PHP_EOL;
    } catch (Throwable $exception) {
        echo json_encode(['lane' => 'full', 'reason' => $exception->getMessage(), 'base' => $base, 'head' => $head], JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE).PHP_EOL;
        exit(1);
    }
}
