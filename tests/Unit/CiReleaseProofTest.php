<?php

require_once dirname(__DIR__, 2).'/scripts/ci-release-proof.php';

function releaseProofRun(string $sha, int $id = 100): array
{
    return [
        'id' => $id, 'run_attempt' => 1, 'workflow_id' => 25,
        'path' => '.github/workflows/ci.yml', 'event' => 'push', 'head_branch' => 'main',
        'head_sha' => $sha, 'status' => 'completed', 'conclusion' => 'success',
        'repository' => ['id' => 5, 'full_name' => SchooltoolCiReleaseProof::Repository],
        'head_repository' => ['id' => 5, 'full_name' => SchooltoolCiReleaseProof::Repository],
    ];
}

function releaseProofJobs(array $run, ?string $base = null): array
{
    $jobs = [];
    foreach ([...SchooltoolCiReleaseProof::CommonJobs, ...SchooltoolCiReleaseProof::FullJobs] as $index => $name) {
        $jobs[] = [
            'id' => $run['id'] * 100 + $index, 'name' => $name,
            'run_id' => $run['id'], 'run_attempt' => $run['run_attempt'], 'head_sha' => $run['head_sha'],
            'status' => 'completed',
            'conclusion' => $base !== null && in_array($name, SchooltoolCiReleaseProof::FullJobs, true) ? 'skipped' : 'success',
        ];
    }
    if ($base !== null) {
        $jobs[] = [
            'id' => $run['id'] * 100 + 20, 'name' => 'Documentation checks (policy v2; base='.$base.')',
            'run_id' => $run['id'], 'run_attempt' => 1, 'head_sha' => $run['head_sha'],
            'status' => 'completed', 'conclusion' => 'success',
        ];
    }

    return $jobs;
}

function releaseProofFixture(?Closure $mutate = null, ?Closure $git = null, ?Closure $classify = null, ?Closure $assets = null): SchooltoolCiReleaseProof
{
    $head = str_repeat('a', 40);
    $base = str_repeat('b', 40);
    $api = static function (string $endpoint) use ($head, $base, $mutate): array {
        $response = match (true) {
            $endpoint === 'repos/ITStudioAT/schooltool' => ['id' => 5, 'full_name' => SchooltoolCiReleaseProof::Repository],
            str_ends_with($endpoint, '/actions/workflows/ci.yml') => ['id' => 25, 'path' => '.github/workflows/ci.yml', 'state' => 'active'],
            str_contains($endpoint, '/attempts/') => (function () use ($endpoint, $head, $base): array {
                $run = str_contains($endpoint, '/runs/90/') ? releaseProofRun($base, 90) : releaseProofRun($head);
                $jobs = releaseProofJobs($run);

                return ['total_count' => count($jobs), 'jobs' => $jobs];
            })(),
            str_contains($endpoint, '/actions/workflows/25/runs') => [
                'total_count' => 1,
                'workflow_runs' => [str_contains($endpoint, 'head_sha='.$base) || ! str_contains($endpoint, 'head_sha=') ? releaseProofRun($base, 90) : releaseProofRun($head)],
            ],
            str_ends_with($endpoint, '/actions/runs/100') => releaseProofRun($head),
            str_ends_with($endpoint, '/actions/runs/90') => releaseProofRun($base, 90),
            default => throw new RuntimeException('Unexpected fixture endpoint: '.$endpoint),
        };

        return $mutate !== null ? $mutate($endpoint, $response) : $response;
    };

    return new SchooltoolCiReleaseProof(
        $api,
        $git ?? static fn (array $arguments): string => $arguments[0] === 'rev-list' ? '1' : str_repeat('c', 40),
        $classify ?? static fn (string $base, string $head): array => ['lane' => 'documentation', 'base' => $base, 'head' => $head],
        $assets ?? static fn (string $base, string $head): array => ['equivalent' => true, 'base' => $base, 'head' => $head],
    );
}

it('accepts only the exact successful full policy run and attempt', function () {
    expect(releaseProofFixture()->verify(str_repeat('a', 40)))->toBe([
        'commit' => str_repeat('a', 40), 'run_id' => 100, 'run_attempt' => 1,
        'url' => 'https://github.com/ITStudioAT/schooltool/actions/runs/100', 'lane' => 'full',
    ]);
});

it('reports exact run progress without granting release approval', function (string $status, ?string $conclusion) {
    $proof = releaseProofFixture(static function (string $endpoint, array $response) use ($status, $conclusion): array {
        if (isset($response['workflow_runs'])) {
            $response['workflow_runs'][0]['status'] = $status;
            $response['workflow_runs'][0]['conclusion'] = $conclusion;
        }
        if (isset($response['jobs'])) {
            $response['jobs'][0]['status'] = 'in_progress';
            $response['jobs'][0]['conclusion'] = null;
        }

        return $response;
    });
    $progress = $proof->status(str_repeat('a', 40));
    expect($progress)->toMatchArray([
        'commit' => str_repeat('a', 40), 'run_id' => 100, 'run_attempt' => 1,
        'status' => $status, 'conclusion' => $conclusion,
        'url' => 'https://github.com/ITStudioAT/schooltool/actions/runs/100',
    ])->not->toHaveKey('lane')
        ->and($progress['jobs'][0])->toMatchArray(['name' => 'Validate source and dependencies', 'status' => 'in_progress'])
        ->and(fn () => $proof->verify(str_repeat('a', 40)))->toThrow(RuntimeException::class);
})->with([
    ['queued', null], ['in_progress', null], ['waiting', null],
    ['completed', 'failure'], ['completed', 'cancelled'], ['completed', 'success'],
]);

it('reports a briefly missing run and unstarted jobs without accepting missing proof', function () {
    $proof = releaseProofFixture(static function (string $endpoint, array $response): array {
        return isset($response['workflow_runs']) ? ['total_count' => 0, 'workflow_runs' => []] : $response;
    });
    expect($proof->status(str_repeat('a', 40)))->toMatchArray(['status' => 'missing', 'jobs' => []])
        ->and(fn () => $proof->verify(str_repeat('a', 40)))->toThrow(RuntimeException::class);

    $proof = releaseProofFixture(static function (string $endpoint, array $response): array {
        return isset($response['jobs']) ? ['total_count' => 0, 'jobs' => []] : $response;
    });
    expect($proof->status(str_repeat('a', 40))['jobs'][0])->toBe([
        'name' => 'Validate source and dependencies', 'status' => 'pending', 'conclusion' => null,
    ]);
});

it('rejects untrusted progress metadata before waiting', function (string $field, mixed $value) {
    $proof = releaseProofFixture(static function (string $endpoint, array $response) use ($field, $value): array {
        if (isset($response['workflow_runs'])) {
            $response['workflow_runs'][0][$field] = $value;
        }

        return $response;
    });
    expect(fn () => $proof->status(str_repeat('a', 40)))->toThrow(RuntimeException::class);
})->with([
    ['head_sha', str_repeat('f', 40)], ['head_branch', 'feature/example'],
    ['event', 'workflow_dispatch'], ['workflow_id', 26], ['path', '.github/workflows/other.yml'],
    ['run_attempt', 0], ['repository', ['id' => 6, 'full_name' => 'other/repository']],
]);
it('rejects untrusted or unsuccessful workflow runs', function (string $field, mixed $value) {
    $proof = releaseProofFixture(static function (string $endpoint, array $response) use ($field, $value): array {
        if (isset($response['workflow_runs'])) {
            $response['workflow_runs'][0][$field] = $value;
        }

        return $response;
    });
    expect(fn () => $proof->verify(str_repeat('a', 40)))->toThrow(RuntimeException::class);
})->with([
    ['status', 'in_progress'], ['conclusion', 'failure'], ['conclusion', 'cancelled'],
    ['conclusion', 'skipped'], ['conclusion', 'neutral'], ['conclusion', null],
    ['event', 'workflow_dispatch'], ['event', 'pull_request'], ['head_branch', 'feature/test'],
    ['head_sha', str_repeat('d', 40)], ['workflow_id', 26], ['path', '.github/workflows/fake.yml'],
    ['head_repository', ['id' => 6, 'full_name' => 'foreign/schooltool']],
    ['repository', ['id' => 5, 'full_name' => 'foreign/schooltool']],
]);

it('never falls back to an older successful run when the latest is pending', function () {
    $proof = releaseProofFixture(static function (string $endpoint, array $response): array {
        if (isset($response['workflow_runs'])) {
            $new = $response['workflow_runs'][0];
            $new['id'] = 101;
            $new['status'] = 'queued';
            $response['workflow_runs'][] = $new;
            $response['total_count'] = 2;
        }

        return $response;
    });
    expect(fn () => $proof->verify(str_repeat('a', 40)))->toThrow(RuntimeException::class);
});

it('rejects missing duplicate skipped failed or foreign job evidence', function (string $fault) {
    $proof = releaseProofFixture(static function (string $endpoint, array $response) use ($fault): array {
        if (isset($response['jobs'])) {
            match ($fault) {
                'missing' => array_pop($response['jobs']),
                'duplicate' => $response['jobs'][] = $response['jobs'][0],
                'skipped' => $response['jobs'][4]['conclusion'] = 'skipped',
                'failure' => $response['jobs'][0]['conclusion'] = 'failure',
                'pending' => $response['jobs'][0]['status'] = 'in_progress',
                'foreign-sha' => $response['jobs'][0]['head_sha'] = str_repeat('d', 40),
                'foreign-run' => $response['jobs'][0]['run_id'] = 99,
                'old-attempt' => $response['jobs'][0]['run_attempt'] = 0,
                'legacy' => $response['jobs'][3]['name'] = 'READY',
            };
            $response['total_count'] = count($response['jobs']);
        }

        return $response;
    });
    expect(fn () => $proof->verify(str_repeat('a', 40)))->toThrow(RuntimeException::class);
})->with(['missing', 'duplicate', 'skipped', 'failure', 'pending', 'foreign-sha', 'foreign-run', 'old-attempt', 'legacy']);

it('rejects a rerun started while the pinned attempt was being inspected', function () {
    $proof = releaseProofFixture(static function (string $endpoint, array $response): array {
        if (str_ends_with($endpoint, '/actions/runs/100')) {
            $response['run_attempt'] = 2;
        }

        return $response;
    });
    expect(fn () => $proof->verify(str_repeat('a', 40)))->toThrow(RuntimeException::class);
});

it('reads every jobs page before issuing proof', function () {
    $pages = [];
    $proof = releaseProofFixture(static function (string $endpoint, array $response) use (&$pages): array {
        if (isset($response['jobs'])) {
            $pages[] = $endpoint;
            $response['jobs'] = str_ends_with($endpoint, 'page=1') ? array_slice($response['jobs'], 0, 4) : array_slice($response['jobs'], 4);
        }

        return $response;
    });
    expect($proof->verify(str_repeat('a', 40))['lane'])->toBe('full');
    expect($pages)->toHaveCount(2);
});

it('accepts documentation only with a separately full proven compatible ancestor', function () {
    $proof = releaseProofFixture(static function (string $endpoint, array $response): array {
        if (str_contains($endpoint, '/runs/100/attempts/')) {
            $jobs = releaseProofJobs(releaseProofRun(str_repeat('a', 40)), str_repeat('b', 40));
            $response = ['total_count' => count($jobs), 'jobs' => $jobs];
        }

        return $response;
    });
    expect($proof->verify(str_repeat('a', 40))['lane'])->toBe('documentation');
});

it('rejects unsafe documentation reuse', function (string $fault) {
    $proof = releaseProofFixture(
        static function (string $endpoint, array $response) use ($fault): array {
            if (str_contains($endpoint, '/runs/100/attempts/')) {
                $jobs = releaseProofJobs(releaseProofRun(str_repeat('a', 40)), str_repeat('b', 40));
                $response = ['total_count' => count($jobs), 'jobs' => $jobs];
            }
            if ($fault === 'docs-chain' && str_contains($endpoint, '/runs/90/attempts/')) {
                $jobs = releaseProofJobs(releaseProofRun(str_repeat('b', 40), 90), str_repeat('d', 40));
                $response = ['total_count' => count($jobs), 'jobs' => $jobs];
            }

            return $response;
        },
        static function (array $arguments) use ($fault): string {
            if ($fault === 'not-ancestor' && $arguments[0] === 'merge-base') {
                throw new RuntimeException('Not ancestor.');
            }

            return $fault === 'policy-changed' && str_starts_with($arguments[2] ?? '', str_repeat('b', 40)) ? str_repeat('d', 40) : str_repeat('c', 40);
        },
        static fn (string $base, string $head): array => ['lane' => $fault === 'code-changed' ? 'full' : 'documentation', 'base' => $base, 'head' => $head],
    );
    expect(fn () => $proof->verify(str_repeat('a', 40)))->toThrow(RuntimeException::class);
})->with(['docs-chain', 'not-ancestor', 'policy-changed', 'code-changed']);

it('returns only a full proven compatible ancestor as baseline', function () {
    expect(releaseProofFixture()->baseline(str_repeat('a', 40)))->toBe(str_repeat('b', 40));
});

it('defaults to full checks when baseline evidence is unavailable', function () {
    $proof = releaseProofFixture(static fn (string $endpoint, array $response): array => throw new RuntimeException('API unavailable'));
    expect($proof->baseline(str_repeat('a', 40)))->toBeNull();
    expect(fn () => $proof->verify(str_repeat('a', 40)))->toThrow(RuntimeException::class);
});

it('rejects non exact SHA input before any API access', function (string $sha) {
    $proof = releaseProofFixture(static fn (string $endpoint, array $response): array => throw new LogicException('API must not be called'));
    expect(fn () => $proof->verify($sha))->toThrow(RuntimeException::class, '40-character');
})->with(['main', '--help', str_repeat('a', 39), str_repeat('A', 40)]);

it('rejects evidence superseded or invalidated during inspection', function (string $fault) {
    $reads = 0;
    $proof = releaseProofFixture(static function (string $endpoint, array $response) use ($fault, &$reads): array {
        if (isset($response['workflow_runs']) && ++$reads > 1) {
            if ($fault === 'new-run') {
                $response['workflow_runs'][0]['id']++;
            } else {
                $response['workflow_runs'][0]['conclusion'] = 'cancelled';
            }
        }

        return $response;
    });
    expect(fn () => $proof->verify(str_repeat('a', 40)))->toThrow(RuntimeException::class);
})->with(['new-run', 'invalidated']);

it('rejects truncated changing or incomplete paginated responses', function (string $fault) {
    $proof = releaseProofFixture(static function (string $endpoint, array $response) use ($fault): array {
        if ($fault === 'runs-truncated' && isset($response['workflow_runs'])) {
            $response['total_count'] = 2;
        }
        if (isset($response['jobs'])) {
            if ($fault === 'jobs-missing') {
                $response['total_count']++;
                if (str_ends_with($endpoint, 'page=2')) {
                    $response['jobs'] = [];
                }
            }
            if ($fault === 'jobs-changed') {
                $response['jobs'] = str_ends_with($endpoint, 'page=1') ? array_slice($response['jobs'], 0, 4) : array_slice($response['jobs'], 4);
                if (str_ends_with($endpoint, 'page=2')) {
                    $response['total_count']++;
                }
            }
        }

        return $response;
    });
    expect(fn () => $proof->verify(str_repeat('a', 40)))->toThrow(RuntimeException::class);
})->with(['runs-truncated', 'jobs-missing', 'jobs-changed']);

it('does not reuse baseline evidence after a newer failed attempt', function () {
    $proof = releaseProofFixture(static function (string $endpoint, array $response): array {
        if (str_contains($endpoint, 'head_sha=') && isset($response['workflow_runs'])) {
            $response['workflow_runs'][0]['conclusion'] = 'failure';
        }

        return $response;
    });
    expect($proof->baseline(str_repeat('a', 40)))->toBeNull();
});

it('rejects mismatched or unproven frontend archives in documentation proofs', function (string $fault) {
    $proof = releaseProofFixture(
        static function (string $endpoint, array $response): array {
            if (str_contains($endpoint, '/runs/100/attempts/')) {
                $jobs = releaseProofJobs(releaseProofRun(str_repeat('a', 40)), str_repeat('b', 40));
                $response = ['total_count' => count($jobs), 'jobs' => $jobs];
            }

            return $response;
        },
        assets: static function (string $base, string $head) use ($fault): array {
            if ($fault === 'corrupt-archive') {
                throw new RuntimeException('Archive checksum failed.');
            }

            return [
                'equivalent' => $fault !== 'different-archive',
                'base' => $fault === 'wrong-base' ? str_repeat('d', 40) : $base,
                'head' => $fault === 'wrong-head' ? str_repeat('d', 40) : $head,
            ];
        },
    );
    expect(fn () => $proof->verify(str_repeat('a', 40)))->toThrow(RuntimeException::class);
})->with(['different-archive', 'wrong-base', 'wrong-head', 'corrupt-archive']);
