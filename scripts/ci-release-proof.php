<?php

declare(strict_types=1);

/** Verifies GitHub evidence without booting the application or reading its environment. */
class SchooltoolCiReleaseProof
{
    public const Repository = 'ITStudioAT/schooltool';

    public const CommonJobs = [
        'Validate source and dependencies',
        'Classify release changes',
        'Verify commit-bound deployment release',
        'Release approval (policy v2)',
    ];

    public const FullJobs = [
        'PHP formatting and static analysis',
        'Frontend tests and release build',
        'PHP sequential coverage',
        'MySQL, Redis, and Horizon integration',
        'Windows PowerShell workflow tests',
    ];

    private array $repository;

    private array $workflow;

    public function __construct(
        private Closure $api,
        private Closure $git,
        private Closure $classify,
        private Closure $assets,
    ) {}

    public static function fromRepository(string $directory): self
    {
        $git = static fn (array $arguments): string => self::command(['git', '--no-replace-objects', ...$arguments], $directory);
        $origin = trim($git(['remote', 'get-url', 'origin']));
        if (! in_array($origin, [
            'https://github.com/'.self::Repository.'.git',
            'https://github.com/'.self::Repository,
            'git@github.com:'.self::Repository.'.git',
            'ssh://git@github.com/'.self::Repository.'.git',
        ], true)) {
            throw new RuntimeException('Release proof requires the trusted Schooltool GitHub origin.');
        }

        return new self(
            static fn (string $endpoint): array => json_decode(self::command([
                'gh', 'api', '--hostname', 'github.com', '-H', 'Accept: application/vnd.github+json',
                '-H', 'X-GitHub-Api-Version: 2022-11-28', $endpoint,
            ], $directory), true, 512, JSON_THROW_ON_ERROR),
            $git,
            static fn (string $base, string $head): array => json_decode(self::command([
                PHP_BINARY, __DIR__.'/release-policy.php', 'classify', '--base', $base, '--head', $head,
            ], $directory), true, 512, JSON_THROW_ON_ERROR),
            static fn (string $base, string $head): array => json_decode(self::command([
                PHP_BINARY, __DIR__.'/release-policy.php', 'assets', '--base', $base, '--head', $head,
            ], $directory), true, 512, JSON_THROW_ON_ERROR),
        );
    }

    public function verify(string $commit, bool $fullOnly = false): array
    {
        $this->assertCommit($commit);
        $this->initialize();
        $run = $this->latestRun($commit);
        $this->assertRun($run, $commit);
        $jobs = $this->jobs($run);
        foreach (self::CommonJobs as $name) {
            $this->assertJob($jobs, $name, 'success');
        }

        $full = true;
        foreach (self::FullJobs as $name) {
            $full = $full && ($jobs[$name]['conclusion'] ?? null) === 'success';
        }
        $documentation = array_filter(array_keys($jobs), static fn (string $name): bool => str_starts_with($name, 'Documentation checks (policy v2; base='));
        if ($full) {
            foreach (self::FullJobs as $name) {
                $this->assertJob($jobs, $name, 'success');
            }
            foreach ($documentation as $name) {
                $this->assertJob($jobs, $name, 'skipped');
            }
        } else {
            if ($fullOnly || count($documentation) !== 1) {
                throw new RuntimeException('The release has no complete policy v2 full-test proof.');
            }
            $name = array_values($documentation)[0];
            if (preg_match('/^Documentation checks \(policy v2; base=([a-f0-9]{40})\)$/D', $name, $matches) !== 1) {
                throw new RuntimeException('The documentation proof has no exact baseline.');
            }
            $base = $matches[1];
            $this->assertJob($jobs, $name, 'success');
            foreach (self::FullJobs as $fullName) {
                $this->assertJob($jobs, $fullName, 'skipped');
            }
            $this->assertCompatibleBaseline($base, $commit);
            $classification = ($this->classify)($base, $commit);
            if (($classification['lane'] ?? null) !== 'documentation'
                || ($classification['base'] ?? null) !== $base || ($classification['head'] ?? null) !== $commit) {
                throw new RuntimeException('Documentation evidence does not cover these source changes.');
            }
            $assets = ($this->assets)($base, $commit);
            if (($assets['equivalent'] ?? null) !== true
                || ($assets['base'] ?? null) !== $base || ($assets['head'] ?? null) !== $commit) {
                throw new RuntimeException('Documentation evidence does not cover these frontend artifacts.');
            }
            $this->verify($base, true);
        }

        $fresh = $this->request('/actions/runs/'.$run['id']);
        $this->assertRun($fresh, $commit);
        if ($fresh['id'] !== $run['id'] || $fresh['run_attempt'] !== $run['run_attempt']) {
            throw new RuntimeException('The release check was rerun while verifying its evidence.');
        }
        $latest = $this->latestRun($commit);
        $this->assertRun($latest, $commit);
        if ($latest['id'] !== $run['id'] || ($latest['run_attempt'] ?? null) !== $run['run_attempt']) {
            throw new RuntimeException('A newer release check superseded the verified run.');
        }

        return [
            'commit' => $commit,
            'run_id' => $run['id'],
            'run_attempt' => $run['run_attempt'],
            'url' => 'https://github.com/'.self::Repository.'/actions/runs/'.$run['id'],
            'lane' => $full ? 'full' : 'documentation',
        ];
    }

    public function baseline(string $head): ?string
    {
        $this->assertCommit($head);
        try {
            $this->initialize();
            $response = $this->request('/actions/workflows/'.$this->workflow['id'].'/runs?event=push&branch=main&per_page=100&page=1');
            $candidates = [];
            foreach ($response['workflow_runs'] ?? [] as $run) {
                $base = $run['head_sha'] ?? '';
                try {
                    $this->assertCommit($base);
                    $this->assertCompatibleBaseline($base, $head);
                    $distance = trim(($this->git)(['rev-list', '--count', $base.'..'.$head]));
                    if (! ctype_digit($distance)) {
                        continue;
                    }
                    $candidates[$base] = (int) $distance;
                } catch (Throwable) {
                    continue;
                }
            }
            asort($candidates, SORT_NUMERIC);
            foreach (array_keys($candidates) as $base) {
                try {
                    $this->verify($base, true);

                    return $base;
                } catch (Throwable) {
                    continue;
                }
            }
        } catch (Throwable) {
            // Unknown evidence can only select the full lane, never approve deployment.
        }

        return null;
    }

    private function initialize(): void
    {
        $this->repository = $this->request('');
        $this->workflow = $this->request('/actions/workflows/ci.yml');
        if (($this->repository['full_name'] ?? null) !== self::Repository || ! is_int($this->repository['id'] ?? null)
            || ! is_int($this->workflow['id'] ?? null) || ($this->workflow['path'] ?? null) !== '.github/workflows/ci.yml'
            || ($this->workflow['state'] ?? null) !== 'active') {
            throw new RuntimeException('The trusted repository or CI workflow could not be verified.');
        }
    }

    private function latestRun(string $commit): array
    {
        $response = $this->request('/actions/workflows/'.$this->workflow['id'].'/runs?event=push&branch=main&head_sha='.$commit.'&per_page=100&page=1');
        $runs = $response['workflow_runs'] ?? [];
        if (! is_array($runs) || $runs === [] || ! is_int($response['total_count'] ?? null)
            || $response['total_count'] > 100 || count($runs) !== $response['total_count']) {
            throw new RuntimeException('No unambiguous exact-commit release check is available.');
        }
        usort($runs, static fn (array $left, array $right): int => ($right['id'] ?? 0) <=> ($left['id'] ?? 0));

        return $runs[0];
    }

    private function assertRun(array $run, string $commit): void
    {
        foreach (['repository', 'head_repository'] as $key) {
            if (($run[$key]['id'] ?? null) !== $this->repository['id'] || ($run[$key]['full_name'] ?? null) !== self::Repository) {
                throw new RuntimeException('The release check belongs to a different repository.');
            }
        }
        if (($run['head_sha'] ?? null) !== $commit || ($run['head_branch'] ?? null) !== 'main'
            || ($run['event'] ?? null) !== 'push' || ($run['workflow_id'] ?? null) !== $this->workflow['id']
            || ($run['path'] ?? null) !== '.github/workflows/ci.yml'
            || ($run['status'] ?? null) !== 'completed' || ($run['conclusion'] ?? null) !== 'success'
            || ! is_int($run['id'] ?? null) || $run['id'] < 1
            || ! is_int($run['run_attempt'] ?? null) || $run['run_attempt'] < 1) {
            throw new RuntimeException('The exact release check is missing, pending, failed, or not trusted.');
        }
    }

    private function jobs(array $run): array
    {
        $jobs = [];
        $ids = [];
        $total = null;
        for ($page = 1; $page <= 10; $page++) {
            $response = $this->request('/actions/runs/'.$run['id'].'/attempts/'.$run['run_attempt'].'/jobs?per_page=100&page='.$page);
            if (! is_int($response['total_count'] ?? null) || ! is_array($response['jobs'] ?? null)
                || ($total !== null && $total !== $response['total_count'])) {
                throw new RuntimeException('The release job list is incomplete or changed.');
            }
            $total = $response['total_count'];
            foreach ($response['jobs'] as $job) {
                $name = $job['name'] ?? '';
                $id = $job['id'] ?? null;
                if (! is_string($name) || $name === '' || isset($jobs[$name]) || ! is_int($id) || isset($ids[$id])
                    || ($job['run_id'] ?? null) !== $run['id'] || ($job['run_attempt'] ?? null) !== $run['run_attempt']
                    || ($job['head_sha'] ?? null) !== $run['head_sha']) {
                    throw new RuntimeException('Duplicate or foreign release job evidence.');
                }
                $jobs[$name] = $job;
                $ids[$id] = true;
            }
            if (count($jobs) === $total) {
                return $jobs;
            }
            if (count($jobs) > $total || $response['jobs'] === []) {
                break;
            }
        }

        throw new RuntimeException('The release job list could not be fully verified.');
    }

    private function assertJob(array $jobs, string $name, string $conclusion): void
    {
        if (($jobs[$name]['status'] ?? null) !== 'completed' || ($jobs[$name]['conclusion'] ?? null) !== $conclusion) {
            throw new RuntimeException('Required release job is not valid: '.$name);
        }
    }

    private function assertCompatibleBaseline(string $base, string $head): void
    {
        if ($base === $head) {
            throw new RuntimeException('The baseline must precede the release.');
        }
        ($this->git)(['merge-base', '--is-ancestor', $base, $head]);
        foreach (['.github/workflows/ci.yml', 'scripts/ci-release-proof.php', 'scripts/release-policy.php'] as $path) {
            $baseBlob = trim(($this->git)(['rev-parse', '--verify', $base.':'.$path]));
            $headBlob = trim(($this->git)(['rev-parse', '--verify', $head.':'.$path]));
            if (! preg_match('/^[a-f0-9]{40}$/D', $baseBlob) || $baseBlob !== $headBlob) {
                throw new RuntimeException('The baseline uses a different release policy.');
            }
        }
    }

    private function assertCommit(string $commit): void
    {
        if (! preg_match('/^[a-f0-9]{40}$/D', $commit)) {
            throw new RuntimeException('An exact 40-character commit SHA is required.');
        }
    }

    private function request(string $path): array
    {
        return ($this->api)('repos/'.self::Repository.$path);
    }

    private static function command(array $command, string $directory): string
    {
        $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['file', PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null', 'w']], $pipes, $directory, null, ['bypass_shell' => true]);
        if (! is_resource($process)) {
            throw new RuntimeException('Could not start the release proof command.');
        }
        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        if (proc_close($process) !== 0 || $output === false) {
            throw new RuntimeException('Release proof command failed; verify GitHub access and local Git objects.');
        }

        return $output;
    }
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    try {
        $action = $argv[1] ?? '';
        if (count($argv) !== 4 || ! in_array($action, ['verify', 'baseline'], true)
            || ($argv[2] ?? '') !== ($action === 'verify' ? '--commit' : '--head')) {
            throw new RuntimeException('Usage: ci-release-proof.php verify --commit SHA | baseline --head SHA');
        }
        $proof = SchooltoolCiReleaseProof::fromRepository(getcwd());
        $result = $action === 'verify' ? $proof->verify($argv[3]) : ['base' => $proof->baseline($argv[3])];
        echo json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES).PHP_EOL;
    } catch (Throwable $exception) {
        fwrite(STDERR, $exception->getMessage().PHP_EOL);
        exit(1);
    }
}
