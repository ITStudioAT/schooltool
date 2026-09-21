<?php

use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

require_once dirname(__DIR__, 2).'/scripts/release-policy.php';

function releasePolicyGit(string $directory, string ...$arguments): string
{
    $process = new Process(['git', '-c', 'user.name=Policy Test', '-c', 'user.email=policy@example.test', '-c', 'commit.gpgsign=false', '-c', 'core.autocrlf=false', '-c', 'core.hooksPath=.git/no-hooks', ...$arguments], $directory);
    $process->mustRun();

    return trim($process->getOutput());
}

function releasePolicyWrite(string $directory, string $path, string $contents): void
{
    (new Filesystem)->ensureDirectoryExists(dirname($directory.'/'.$path));
    file_put_contents($directory.'/'.$path, $contents);
}

function releasePolicyCommit(string $directory): string
{
    releasePolicyGit($directory, 'add', '--all');
    releasePolicyGit($directory, 'commit', '--allow-empty', '-m', 'Policy fixture');

    return releasePolicyGit($directory, 'rev-parse', 'HEAD');
}

beforeEach(function (): void {
    $this->policyDirectory = sys_get_temp_dir().'/schooltool-release-policy-'.bin2hex(random_bytes(12));
    mkdir($this->policyDirectory);
    releasePolicyGit($this->policyDirectory, 'init', '--initial-branch=main');
    $this->policyConfig = "<?php\nreturn [\n    'version' => '3.48.7',\n    'preview' => ['instance' => false],\n];\n";
    releasePolicyWrite($this->policyDirectory, 'config/schooltool.php', $this->policyConfig);
    releasePolicyWrite($this->policyDirectory, 'README.md', 'Documentation');
    $this->policyBase = releasePolicyCommit($this->policyDirectory);
});

afterEach(function (): void {
    (new Filesystem)->deleteDirectory($this->policyDirectory);
});

it('classifies version notes and generated documentation together', function (): void {
    releasePolicyWrite($this->policyDirectory, 'config/schooltool.php', str_replace('3.48.7', '3.49.0', $this->policyConfig));
    releasePolicyWrite($this->policyDirectory, 'UPDATES.md', "## 3.49.0\nRelease notes");
    releasePolicyWrite($this->policyDirectory, 'public/documentation/assets/js/main.abc123.js', 'console.log("documentation");');
    $head = releasePolicyCommit($this->policyDirectory);

    expect(SchooltoolReleasePolicy::classify($this->policyDirectory, $this->policyBase, $head))
        ->toMatchArray(['lane' => 'documentation', 'base' => $this->policyBase, 'head' => $head]);
});

it('allows narrowly scoped documentation', function (string $path): void {
    releasePolicyWrite($this->policyDirectory, $path, 'Updated documentation');
    $head = releasePolicyCommit($this->policyDirectory);

    expect(SchooltoolReleasePolicy::classify($this->policyDirectory, $this->policyBase, $head)['lane'])->toBe('documentation');
})->with(['README.md', 'UPDATES.md', 'public/documentation/guide/index.html', 'public/documentation/assets/css/styles.abc.css', 'public/documentation/img/logo.png']);

it('requires full checks for code executable documentation and unknown paths', function (string $path): void {
    releasePolicyWrite($this->policyDirectory, $path, 'changed');
    $head = releasePolicyCommit($this->policyDirectory);

    expect(SchooltoolReleasePolicy::classify($this->policyDirectory, $this->policyBase, $head)['lane'])->toBe('full');
})->with([
    'app/Service.php', 'tests/Unit/ExampleTest.php', 'composer.lock', 'package-lock.json',
    'resources/js/main.js', 'public/build/assets/main.js', '.github/workflows/ci.yml', '.ai/rules/scripts.md',
    'scripts/release-policy.php', 'deployment/unknown.json', 'documentation/index.html', 'public/documentation-evil/index.html',
    'public/documentation/payload.php', 'public/documentation/payload.phtml', 'public/documentation/payload.php.jpg',
    'public/documentation/.htaccess', 'public/documentation/.hidden/index.html', 'public/documentation/web.config',
    'public/documentation/unsafe.shtml', 'public/documentation/unsafe.cgi',
]);

it('rejects config edits beyond the version literal', function (string $replacement): void {
    $after = str_replace("'version' => '3.48.7'", $replacement, $this->policyConfig);
    releasePolicyWrite($this->policyDirectory, 'config/schooltool.php', $after);
    $head = releasePolicyCommit($this->policyDirectory);

    expect(SchooltoolReleasePolicy::classify($this->policyDirectory, $this->policyBase, $head)['lane'])->toBe('full');
})->with([
    "'version' => env('VERSION', '3.49.0')", "'version' => '3.49.0-rc1'", "'version' => '03.49.0'",
    "'version' => '3.49.0', 'unsafe' => true", "'version' => '3.49.0' /* changed comment */",
]);

it('does not mistake nested duplicate commented or interpolated versions for the application version', function (string $before): void {
    releasePolicyWrite($this->policyDirectory, 'config/schooltool.php', $before);
    $base = releasePolicyCommit($this->policyDirectory);
    releasePolicyWrite($this->policyDirectory, 'config/schooltool.php', str_replace('3.48.7', '3.49.0', $before));
    $head = releasePolicyCommit($this->policyDirectory);

    expect(SchooltoolReleasePolicy::classify($this->policyDirectory, $base, $head)['lane'])->toBe('full');
})->with([
    "<?php return ['preview' => ['version' => '3.48.7']];",
    "<?php return ['version' => '3.48.7', 'version' => '3.48.7'];",
    "<?php return [/* 'version' => '3.48.7', */ 'other' => true];",
    '<?php return ["version" => "3.48.7$dynamic"];',
]);

it('requires full checks for deleted or renamed documentation', function (bool $rename): void {
    if ($rename) {
        rename($this->policyDirectory.'/README.md', $this->policyDirectory.'/UPDATES-OLD.md');
    } else {
        unlink($this->policyDirectory.'/README.md');
    }

    $head = releasePolicyCommit($this->policyDirectory);
    expect(SchooltoolReleasePolicy::classify($this->policyDirectory, $this->policyBase, $head)['lane'])->toBe('full');
})->with([false, true]);

it('rejects executable modes and symlinks from git objects on every operating system', function (string $mode): void {
    $blob = releasePolicyGit($this->policyDirectory, 'rev-parse', $this->policyBase.':README.md');
    releasePolicyGit($this->policyDirectory, 'update-index', '--cacheinfo', $mode.','.$blob.',README.md');
    releasePolicyGit($this->policyDirectory, 'commit', '-m', 'Unsafe Git mode');
    $head = releasePolicyGit($this->policyDirectory, 'rev-parse', 'HEAD');

    expect(SchooltoolReleasePolicy::classify($this->policyDirectory, $this->policyBase, $head)['lane'])->toBe('full');
})->with(['100755', '120000']);

it('requires full checks for empty or artifact-only changes', function (bool $artifacts): void {
    if ($artifacts) {
        foreach (['frontend-build.tar.gz', 'frontend-build.sha256', 'source-commit', 'source-manifest.sha256'] as $name) {
            releasePolicyWrite($this->policyDirectory, 'deployment/'.$name, 'artifact fixture');
        }
    }

    $head = releasePolicyCommit($this->policyDirectory);
    expect(SchooltoolReleasePolicy::classify($this->policyDirectory, $this->policyBase, $head)['lane'])->toBe('full');
})->with([false, true]);

it('excludes exactly the four release artifacts from a documentation source delta', function (): void {
    foreach (['frontend-build.tar.gz', 'frontend-build.sha256', 'source-commit', 'source-manifest.sha256'] as $name) {
        releasePolicyWrite($this->policyDirectory, 'deployment/'.$name, 'artifact fixture');
    }
    releasePolicyWrite($this->policyDirectory, 'README.md', 'Documentation source');
    $head = releasePolicyCommit($this->policyDirectory);

    expect(SchooltoolReleasePolicy::classify($this->policyDirectory, $this->policyBase, $head)['lane'])->toBe('documentation');
});

it('classifies immutable commits independently of dirty staged files', function (): void {
    releasePolicyWrite($this->policyDirectory, 'README.md', 'Committed documentation');
    $head = releasePolicyCommit($this->policyDirectory);
    releasePolicyWrite($this->policyDirectory, 'app/Unsafe.php', '<?php exit;');
    releasePolicyGit($this->policyDirectory, 'add', '--all');

    expect(SchooltoolReleasePolicy::classify($this->policyDirectory, $this->policyBase, $head)['lane'])->toBe('documentation');
});

it('rejects missing abbreviated symbolic or non-commit revisions', function (string $revision): void {
    $base = $revision === 'tree' ? releasePolicyGit($this->policyDirectory, 'rev-parse', 'HEAD^{tree}') : $revision;

    expect(fn () => SchooltoolReleasePolicy::classify($this->policyDirectory, $base, $this->policyBase))->toThrow(RuntimeException::class);
})->with(['', 'HEAD', '--help', 'abcdef0', str_repeat('f', 40), 'tree']);

it('rejects a base which is not an ancestor', function (): void {
    releasePolicyWrite($this->policyDirectory, 'README.md', 'Newer documentation');
    $newer = releasePolicyCommit($this->policyDirectory);

    expect(fn () => SchooltoolReleasePolicy::classify($this->policyDirectory, $newer, $this->policyBase))->toThrow(RuntimeException::class);
});

it('ignores replacement objects which could hide application changes', function (): void {
    releasePolicyWrite($this->policyDirectory, 'app/Code.php', '<?php exit;');
    $head = releasePolicyCommit($this->policyDirectory);
    releasePolicyGit($this->policyDirectory, 'replace', $head, $this->policyBase);

    expect(SchooltoolReleasePolicy::classify($this->policyDirectory, $this->policyBase, $head)['lane'])->toBe('full');
});

it('exposes machine-readable CLI results and fail-closed errors', function (): void {
    releasePolicyWrite($this->policyDirectory, 'README.md', 'Updated documentation');
    $head = releasePolicyCommit($this->policyDirectory);
    $script = dirname(__DIR__, 2).'/scripts/release-policy.php';
    $process = new Process([PHP_BINARY, $script, 'classify', '--base', $this->policyBase, '--head', $head], $this->policyDirectory);
    $process->mustRun();
    expect(json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR)['lane'])->toBe('documentation');

    $invalid = new Process([PHP_BINARY, $script, 'classify', '--base', 'HEAD', '--head', $head], $this->policyDirectory);
    $invalid->run();
    expect($invalid->getExitCode())->toBe(1)
        ->and(json_decode($invalid->getOutput(), true, flags: JSON_THROW_ON_ERROR)['lane'])->toBe('full');
});

/** @param array<string, string> $overrides */
function releasePolicyArtifacts(string $directory, string $source, array $overrides = [], ?Closure $mutate = null): string
{
    $temporary = $directory.'/.git/fixture-'.bin2hex(random_bytes(6)).'.tar';
    $archive = new PharData($temporary);

    foreach (array_replace([
        'manifest.json' => '{"main":{"file":"assets/main.js"}}',
        'environment-versions.json' => '{"node":"v24.0.0"}',
        'deployment-source.txt' => $source."\n",
        'assets/main.js' => 'console.log("application");',
    ], $overrides) as $name => $contents) {
        $archive->addFromString($name, $contents);
    }

    unset($archive);
    $tar = file_get_contents($temporary);
    unlink($temporary);
    $gzip = gzencode($mutate === null ? $tar : $mutate($tar));
    releasePolicyWrite($directory, 'deployment/frontend-build.tar.gz', $gzip);
    releasePolicyWrite($directory, 'deployment/frontend-build.sha256', hash('sha256', $gzip)."  frontend-build.tar.gz\n");
    releasePolicyWrite($directory, 'deployment/source-commit', $source."\n");
    releasePolicyWrite($directory, 'deployment/source-manifest.sha256', 'Manifest separately checked by release validation');

    return releasePolicyCommit($directory);
}

function releasePolicyTarHeader(string $tar, int $offset, string $replacement, int $length): string
{
    $tar = substr_replace($tar, str_pad($replacement, $length, "\0"), $offset, $length);
    $header = substr_replace(substr($tar, 0, 512), str_repeat(' ', 8), 148, 8);
    $checksum = sprintf('%06o', array_sum(unpack('C*', $header)))."\0 ";

    return substr_replace($tar, $checksum, 148, 8);
}

it('proves frontend equivalence while allowing only each bound source marker to differ', function (): void {
    $base = releasePolicyArtifacts($this->policyDirectory, $this->policyBase);
    releasePolicyWrite($this->policyDirectory, 'README.md', 'Documentation changed');
    $source = releasePolicyCommit($this->policyDirectory);
    $head = releasePolicyArtifacts($this->policyDirectory, $source);

    expect(SchooltoolReleasePolicy::verifyFrontendEquivalence($this->policyDirectory, $base, $head))
        ->toBe(['equivalent' => true, 'base' => $base, 'head' => $head]);

    $command = new Process([PHP_BINARY, dirname(__DIR__, 2).'/scripts/release-policy.php', 'assets', '--base', $base, '--head', $head], $this->policyDirectory);
    $command->mustRun();
    expect(json_decode($command->getOutput(), true, flags: JSON_THROW_ON_ERROR)['equivalent'])->toBeTrue();
});

it('requires full checks for changed frontend payloads and runtime metadata', function (string $path): void {
    $base = releasePolicyArtifacts($this->policyDirectory, $this->policyBase);
    releasePolicyWrite($this->policyDirectory, 'README.md', 'Documentation changed');
    $source = releasePolicyCommit($this->policyDirectory);
    $head = releasePolicyArtifacts($this->policyDirectory, $source, [$path => 'changed']);

    expect(fn () => SchooltoolReleasePolicy::verifyFrontendEquivalence($this->policyDirectory, $base, $head))
        ->toThrow(RuntimeException::class);
})->with(['assets/main.js', 'environment-versions.json', 'manifest.json', 'assets/injected.js', 'deployment-source.txt']);

it('rejects unsafe malformed and ambiguous frontend tar entries without extracting anything', function (string $kind): void {
    $base = releasePolicyArtifacts($this->policyDirectory, $this->policyBase);
    releasePolicyWrite($this->policyDirectory, 'README.md', 'Documentation changed');
    $source = releasePolicyCommit($this->policyDirectory);
    $mutate = static function (string $tar) use ($kind): string {
        return match ($kind) {
            'traversal' => releasePolicyTarHeader($tar, 0, '../escape', 100),
            'absolute' => releasePolicyTarHeader($tar, 0, '/escape', 100),
            'backslash' => releasePolicyTarHeader($tar, 0, '..\\escape', 100),
            'symlink' => releasePolicyTarHeader($tar, 156, '2', 1),
            'hardlink' => releasePolicyTarHeader($tar, 156, '1', 1),
            'pax' => releasePolicyTarHeader($tar, 156, 'x', 1),
            'duplicate' => substr($tar, 0, 512 + (int) ceil(octdec(trim(substr($tar, 124, 12), "\0 ")) / 512) * 512).$tar,
            'checksum' => substr_replace($tar, '!', 0, 1),
            'missing terminator' => substr($tar, 0, -1024),
            'trailing payload' => $tar.str_repeat('!', 512),
        };
    };
    $head = releasePolicyArtifacts($this->policyDirectory, $source, mutate: $mutate);

    expect(fn () => SchooltoolReleasePolicy::verifyFrontendEquivalence($this->policyDirectory, $base, $head))
        ->toThrow(RuntimeException::class)
        ->and(file_exists(dirname($this->policyDirectory).'/escape'))->toBeFalse();
})->with(['traversal', 'absolute', 'backslash', 'symlink', 'hardlink', 'pax', 'duplicate', 'checksum', 'missing terminator', 'trailing payload']);

it('rejects artifacts with incorrect parent source binding or checksum', function (string $kind): void {
    $base = releasePolicyArtifacts($this->policyDirectory, $this->policyBase);
    releasePolicyWrite($this->policyDirectory, 'README.md', 'Documentation changed');
    $source = releasePolicyCommit($this->policyDirectory);
    $head = releasePolicyArtifacts($this->policyDirectory, $kind === 'source' ? $this->policyBase : $source);

    if ($kind === 'checksum') {
        releasePolicyWrite($this->policyDirectory, 'deployment/frontend-build.sha256', str_repeat('0', 64)."  frontend-build.tar.gz\n");
        releasePolicyGit($this->policyDirectory, 'add', '--all');
        releasePolicyGit($this->policyDirectory, 'commit', '--amend', '--no-edit');
        $head = releasePolicyGit($this->policyDirectory, 'rev-parse', 'HEAD');
    }

    expect(fn () => SchooltoolReleasePolicy::verifyFrontendEquivalence($this->policyDirectory, $base, $head))
        ->toThrow(RuntimeException::class);
})->with(['source', 'checksum']);

it('rejects concatenated gzip members and trailing compressed data', function (bool $secondMember): void {
    $base = releasePolicyArtifacts($this->policyDirectory, $this->policyBase);
    releasePolicyWrite($this->policyDirectory, 'README.md', 'Documentation changed');
    $source = releasePolicyCommit($this->policyDirectory);
    releasePolicyArtifacts($this->policyDirectory, $source);
    $archive = file_get_contents($this->policyDirectory.'/deployment/frontend-build.tar.gz');
    $archive .= $secondMember ? $archive : 'trailing';
    releasePolicyWrite($this->policyDirectory, 'deployment/frontend-build.tar.gz', $archive);
    releasePolicyWrite($this->policyDirectory, 'deployment/frontend-build.sha256', hash('sha256', $archive)."  frontend-build.tar.gz\n");
    releasePolicyGit($this->policyDirectory, 'add', '--all');
    releasePolicyGit($this->policyDirectory, 'commit', '--amend', '--no-edit');
    $head = releasePolicyGit($this->policyDirectory, 'rev-parse', 'HEAD');

    expect(fn () => SchooltoolReleasePolicy::verifyFrontendEquivalence($this->policyDirectory, $base, $head))
        ->toThrow(RuntimeException::class, 'Trailing data or concatenated');
})->with([false, true]);
