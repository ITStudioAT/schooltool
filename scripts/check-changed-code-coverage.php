<?php

declare(strict_types=1);

class ChangedCodeCoverageGate
{
    private const DefaultMinimumCoverage = 80.0;

    /**
     * @param  array<int, string>  $arguments
     */
    public static function main(array $arguments): int
    {
        try {
            $options = self::parseOptions(array_slice($arguments, 1));
            $rootPath = realpath(dirname(__DIR__));

            if ($rootPath === false) {
                throw new RuntimeException('Unable to resolve the repository root.');
            }

            $base = self::resolveBase($rootPath, $options['base']);
            $head = self::resolveCommit($rootPath, $options['head']);
            $coveragePath = self::resolveCoveragePath($rootPath, $options['coverage']);
            $diff = self::runGit($rootPath, [
                'diff',
                '--unified=0',
                '--no-color',
                '--no-ext-diff',
                '--diff-filter=ACMR',
                '--find-renames',
                $base,
                $head,
                '--',
                'app',
            ]);

            $changedLines = self::parseDiff($diff);
            $coverage = self::parseCloverFile($coveragePath, $rootPath);
            $result = self::evaluate($changedLines, $coverage, $options['min']);

            self::printResult($result, $options['min']);

            return $result['passes'] ? 0 : 1;
        } catch (Throwable $throwable) {
            fwrite(STDERR, "Changed-code coverage gate error: {$throwable->getMessage()}".PHP_EOL);

            return 2;
        }
    }

    /**
     * @param  array<int, string>  $arguments
     * @return array{
     *     base: string,
     *     coverage: string,
     *     head: string,
     *     min: float,
     * }
     */
    public static function parseOptions(array $arguments): array
    {
        $options = [
            'base' => '',
            'coverage' => '',
            'head' => 'HEAD',
            'min' => self::DefaultMinimumCoverage,
        ];

        for ($index = 0; $index < count($arguments); $index++) {
            $argument = $arguments[$index];

            if (! str_starts_with($argument, '--')) {
                throw new InvalidArgumentException("Unexpected argument: {$argument}");
            }

            [$name, $inlineValue] = array_pad(explode('=', substr($argument, 2), 2), 2, null);

            if (! array_key_exists($name, $options)) {
                throw new InvalidArgumentException("Unknown option: --{$name}");
            }

            $value = $inlineValue;

            if ($value === null) {
                $index++;
                $value = $arguments[$index] ?? null;
            }

            if ($value === null || $value === '' || str_starts_with($value, '--')) {
                throw new InvalidArgumentException("Option --{$name} requires a value.");
            }

            $options[$name] = $name === 'min' ? self::parseMinimumCoverage($value) : $value;
        }

        if ($options['base'] === '') {
            throw new InvalidArgumentException('Option --base is required.');
        }

        if ($options['coverage'] === '') {
            throw new InvalidArgumentException('Option --coverage is required.');
        }

        self::assertSafeGitReference($options['base'], 'base');
        self::assertSafeGitReference($options['head'], 'head');

        return $options;
    }

    /**
     * @return array<string, array<int, true>>
     */
    public static function parseDiff(string $diff): array
    {
        $changedLines = [];
        $currentPath = null;

        foreach (preg_split('/\R/', $diff) ?: [] as $line) {
            if (str_starts_with($line, '+++ ')) {
                $currentPath = self::normalizeDiffPath(substr($line, 4));

                if (
                    $currentPath === null
                    || ! str_starts_with($currentPath, 'app/')
                    || ! str_ends_with(strtolower($currentPath), '.php')
                ) {
                    $currentPath = null;
                }

                continue;
            }

            if ($currentPath === null) {
                continue;
            }

            if (! preg_match('/^@@ -\d+(?:,\d+)? \+(\d+)(?:,(\d+))? @@/', $line, $matches)) {
                continue;
            }

            $startLine = (int) $matches[1];
            $lineCount = array_key_exists(2, $matches) && $matches[2] !== ''
                ? (int) $matches[2]
                : 1;

            for ($lineNumber = $startLine; $lineNumber < $startLine + $lineCount; $lineNumber++) {
                $changedLines[$currentPath][$lineNumber] = true;
            }
        }

        ksort($changedLines);

        return $changedLines;
    }

    /**
     * @return array<string, array<int, int>>
     */
    public static function parseCloverFile(string $coveragePath, string $rootPath): array
    {
        $contents = file_get_contents($coveragePath);

        if ($contents === false) {
            throw new RuntimeException("Unable to read Clover coverage report: {$coveragePath}");
        }

        return self::parseCloverXml($contents, $rootPath);
    }

    /**
     * @return array<string, array<int, int>>
     */
    public static function parseCloverXml(string $xml, string $rootPath): array
    {
        $previousErrorMode = libxml_use_internal_errors(true);
        $document = simplexml_load_string($xml, SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);
        $errors = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors($previousErrorMode);

        if ($document === false) {
            $message = $errors === [] ? 'unknown XML error' : trim($errors[0]->message);

            throw new RuntimeException("Invalid Clover coverage report: {$message}");
        }

        $coverage = [];

        foreach ($document->xpath('//file') ?: [] as $file) {
            $relativePath = self::relativeCoveragePath((string) $file['name'], $rootPath);

            if (
                $relativePath === null
                || ! str_starts_with($relativePath, 'app/')
                || ! str_ends_with(strtolower($relativePath), '.php')
            ) {
                continue;
            }

            $coverage[$relativePath] ??= [];

            foreach ($file->line as $line) {
                if ((string) $line['type'] !== 'stmt') {
                    continue;
                }

                $lineNumber = (int) $line['num'];

                if ($lineNumber < 1) {
                    throw new RuntimeException("Clover contains an invalid line number for {$relativePath}.");
                }

                $coverage[$relativePath][$lineNumber] = max(
                    $coverage[$relativePath][$lineNumber] ?? 0,
                    (int) $line['count'],
                );
            }
        }

        ksort($coverage);

        return $coverage;
    }

    /**
     * @param  array<string, array<int, true>>  $changedLines
     * @param  array<string, array<int, int>>  $coverage
     * @return array{
     *     covered: int,
     *     executable: int,
     *     passes: bool,
     *     percentage: float,
     *     uncovered: array<string, array<int, int>>,
     * }
     */
    public static function evaluate(array $changedLines, array $coverage, float $minimumCoverage): array
    {
        $coveredLines = 0;
        $executableLines = 0;
        $uncoveredLines = [];

        foreach ($changedLines as $relativePath => $lineNumbers) {
            if (! array_key_exists($relativePath, $coverage)) {
                throw new RuntimeException(
                    "Changed PHP file is missing from the Clover report: {$relativePath}",
                );
            }

            foreach (array_keys($lineNumbers) as $lineNumber) {
                if (! array_key_exists($lineNumber, $coverage[$relativePath])) {
                    continue;
                }

                $executableLines++;

                if ($coverage[$relativePath][$lineNumber] > 0) {
                    $coveredLines++;

                    continue;
                }

                $uncoveredLines[$relativePath][] = $lineNumber;
            }
        }

        $percentage = $executableLines === 0
            ? 100.0
            : ($coveredLines / $executableLines) * 100;

        return [
            'covered' => $coveredLines,
            'executable' => $executableLines,
            'passes' => $percentage + PHP_FLOAT_EPSILON >= $minimumCoverage,
            'percentage' => $percentage,
            'uncovered' => $uncoveredLines,
        ];
    }

    private static function parseMinimumCoverage(string $value): float
    {
        if (! is_numeric($value)) {
            throw new InvalidArgumentException('Option --min must be numeric.');
        }

        $minimumCoverage = (float) $value;

        if ($minimumCoverage < 0 || $minimumCoverage > 100) {
            throw new InvalidArgumentException('Option --min must be between 0 and 100.');
        }

        return $minimumCoverage;
    }

    private static function assertSafeGitReference(string $reference, string $option): void
    {
        if (
            ! preg_match('/\A(?!-)[A-Za-z0-9][A-Za-z0-9._\/-]*\z/', $reference)
            || str_contains($reference, '..')
        ) {
            throw new InvalidArgumentException("Option --{$option} contains an unsafe Git reference.");
        }
    }

    private static function resolveBase(string $rootPath, string $base): string
    {
        if (! preg_match('/\A0{40}\z/', $base)) {
            return self::resolveCommit($rootPath, $base);
        }

        $candidates = [];
        $githubBaseRef = trim((string) getenv('GITHUB_BASE_REF'));

        if ($githubBaseRef !== '') {
            self::assertSafeGitReference($githubBaseRef, 'GITHUB_BASE_REF');
            $candidates[] = "origin/{$githubBaseRef}";
        }

        $remoteHead = self::tryGit($rootPath, [
            'symbolic-ref',
            '--quiet',
            'refs/remotes/origin/HEAD',
        ]);

        if ($remoteHead !== null) {
            $candidates[] = trim($remoteHead);
        }

        $candidates[] = 'origin/main';

        foreach (array_unique($candidates) as $candidate) {
            try {
                return self::resolveCommit($rootPath, $candidate);
            } catch (RuntimeException) {
                continue;
            }
        }

        throw new RuntimeException(
            'The push event has no previous commit and no default remote branch could be resolved.',
        );
    }

    private static function resolveCommit(string $rootPath, string $reference): string
    {
        $commit = trim(self::runGit($rootPath, [
            'rev-parse',
            '--verify',
            "{$reference}^{commit}",
        ]));

        if (! preg_match('/\A[0-9a-f]{40}\z/i', $commit)) {
            throw new RuntimeException("Git reference did not resolve to a commit: {$reference}");
        }

        return $commit;
    }

    private static function resolveCoveragePath(string $rootPath, string $coveragePath): string
    {
        $candidate = self::isAbsolutePath($coveragePath)
            ? $coveragePath
            : $rootPath.DIRECTORY_SEPARATOR.$coveragePath;
        $resolvedPath = realpath($candidate);

        if ($resolvedPath === false || ! is_file($resolvedPath)) {
            throw new RuntimeException("Clover coverage report does not exist: {$coveragePath}");
        }

        return $resolvedPath;
    }

    private static function isAbsolutePath(string $path): bool
    {
        return preg_match('/\A(?:[A-Za-z]:[\/\\\\]|[\/\\\\]{2}|\/)/', $path) === 1;
    }

    private static function normalizeDiffPath(string $path): ?string
    {
        $path = trim($path);

        if ($path === '/dev/null') {
            return null;
        }

        if (str_starts_with($path, '"') && str_ends_with($path, '"')) {
            $path = stripcslashes(substr($path, 1, -1));
        }

        if (str_starts_with($path, 'b/')) {
            $path = substr($path, 2);
        }

        return self::normalizePath($path);
    }

    private static function relativeCoveragePath(string $path, string $rootPath): ?string
    {
        $path = ltrim(self::normalizePath($path), './');
        $rootPath = rtrim(self::normalizePath($rootPath), '/');

        if (strncasecmp($path, "{$rootPath}/", strlen($rootPath) + 1) === 0) {
            return substr($path, strlen($rootPath) + 1);
        }

        if (str_starts_with($path, 'app/')) {
            return $path;
        }

        $applicationPathPosition = strripos("/{$path}", '/app/');

        if ($applicationPathPosition === false) {
            return null;
        }

        return substr("/{$path}", $applicationPathPosition + 1);
    }

    private static function normalizePath(string $path): string
    {
        return preg_replace('#/+#', '/', str_replace('\\', '/', $path)) ?? $path;
    }

    /**
     * @param  array<int, string>  $arguments
     */
    private static function runGit(string $rootPath, array $arguments): string
    {
        $process = proc_open(
            ['git', '-c', 'core.quotepath=false', ...$arguments],
            [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes,
            $rootPath,
        );

        if (! is_resource($process)) {
            throw new RuntimeException('Unable to start Git.');
        }

        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]);
        $errorOutput = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        if ($exitCode !== 0) {
            $message = trim($errorOutput) ?: 'Git exited without an error message.';

            throw new RuntimeException($message);
        }

        if ($output === false) {
            throw new RuntimeException('Unable to read Git output.');
        }

        return $output;
    }

    /**
     * @param  array<int, string>  $arguments
     */
    private static function tryGit(string $rootPath, array $arguments): ?string
    {
        try {
            return self::runGit($rootPath, $arguments);
        } catch (RuntimeException) {
            return null;
        }
    }

    /**
     * @param array{
     *     covered: int,
     *     executable: int,
     *     passes: bool,
     *     percentage: float,
     *     uncovered: array<string, array<int, int>>,
     * } $result
     */
    private static function printResult(array $result, float $minimumCoverage): void
    {
        foreach ($result['uncovered'] as $relativePath => $lineNumbers) {
            foreach ($lineNumbers as $lineNumber) {
                fwrite(
                    STDERR,
                    "::error file={$relativePath},line={$lineNumber}::Changed executable line is not covered."
                    .PHP_EOL,
                );
            }
        }

        $percentage = number_format($result['percentage'], 2, '.', '');
        $minimum = number_format($minimumCoverage, 2, '.', '');

        fwrite(
            $result['passes'] ? STDOUT : STDERR,
            "Changed-code coverage: {$percentage}% "
            ."({$result['covered']}/{$result['executable']} executable changed lines; required {$minimum}%)."
            .PHP_EOL,
        );
    }
}

if (realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === realpath(__FILE__)) {
    exit(ChangedCodeCoverageGate::main($argv));
}
