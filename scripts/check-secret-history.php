<?php

declare(strict_types=1);

/**
 * Reject Laravel application keys introduced anywhere in a commit range,
 * including keys that are added and removed again before the final diff.
 */
$options = getopt('', ['base:', 'head::']);
$base = trim((string) ($options['base'] ?? ''));
$head = trim((string) ($options['head'] ?? 'HEAD'));

if ($base === '') {
    fwrite(STDERR, "Missing required --base=<git-sha> argument.\n");

    exit(2);
}

/**
 * @param  array<int, string>  $command
 */
function runGit(array $command): string
{
    $process = proc_open(
        ['git', ...$command],
        [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ],
        $pipes,
    );

    if (! is_resource($process)) {
        fwrite(STDERR, "Unable to start git.\n");

        exit(2);
    }

    fclose($pipes[0]);
    $output = stream_get_contents($pipes[1]);
    $error = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    $exitCode = proc_close($process);
    if ($exitCode !== 0) {
        fwrite(STDERR, trim((string) $error)."\n");

        exit(2);
    }

    return (string) $output;
}

runGit(['rev-parse', '--verify', "{$base}^{commit}"]);
runGit(['rev-parse', '--verify', "{$head}^{commit}"]);

$commits = array_values(array_filter(array_map(
    'trim',
    explode("\n", runGit(['rev-list', '--reverse', "{$base}..{$head}"]))
)));

$findings = [];

foreach ($commits as $commit) {
    $patch = runGit([
        'show',
        '--format=',
        '--no-ext-diff',
        '--unified=0',
        '--no-renames',
        $commit,
    ]);

    if (preg_match(
        '/^\+(?!\+\+)\s*APP_KEY\s*[:=]\s*["\']?(?:base64:)?[A-Za-z0-9+\/=]{20,}["\']?\s*$/m',
        $patch,
    ) === 1) {
        $findings[] = substr($commit, 0, 12);
    }
}

if ($findings !== []) {
    fwrite(
        STDERR,
        'Potential Laravel APP_KEY introduced in commit(s): '.implode(', ', $findings).". Values were intentionally not printed.\n"
    );

    exit(1);
}

fwrite(STDOUT, 'No Laravel APP_KEY was introduced in the scanned commit range.'.PHP_EOL);
