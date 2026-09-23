<?php

use Illuminate\Filesystem\Filesystem;
use SebastianBergmann\CodeCoverage\CodeCoverage;
use SebastianBergmann\CodeCoverage\Data\RawCodeCoverageData;
use SebastianBergmann\CodeCoverage\Driver\Driver;
use SebastianBergmann\CodeCoverage\Filter;
use SebastianBergmann\CodeCoverage\Report\PHP as PhpCoverageReport;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;

require_once dirname(__DIR__, 2).'/scripts/ci-php-tests.php';

function releaseCiWorkflow(): array
{
    return Yaml::parseFile(dirname(__DIR__, 2).'/.github/workflows/ci.yml');
}

it('runs every expensive gate on main code changes and isolates platform resources', function (): void {
    $workflow = releaseCiWorkflow();

    foreach (['php-quality', 'php-tests', 'infrastructure', 'windows-workflow'] as $name) {
        expect($workflow['jobs'][$name]['needs'])->toBe('classify')
            ->and($workflow['jobs'][$name]['if'])->toBe("needs.classify.outputs.lane == 'full'");
    }

    expect($workflow['jobs']['frontend']['needs'])->toBe('classify')
        ->and($workflow['jobs']['frontend']['if'])->toBe("needs.classify.outputs.lane == 'full' || needs.classify.outputs.lane == 'frontend'")
        ->and($workflow['jobs']['php-tests']['services']['mysql']['image'])->toBe('mysql:8.4')
        ->and($workflow['jobs']['infrastructure']['services']['mysql']['image'])->toBe('mysql:8.4')
        ->and($workflow['jobs']['windows-workflow']['runs-on'])->toBe('windows-latest')
        ->and($workflow['permissions'])->toBe(['contents' => 'read', 'actions' => 'read'])
        ->and($workflow['jobs']['documentation']['name'])->toBe('Documentation checks (policy v3; base=${{ needs.classify.outputs.base }})')
        ->and($workflow['jobs']['frontend-proof']['name'])->toBe('Frontend checks (policy v3; base=${{ needs.classify.outputs.base }})');
});

it('requires exact baseline proof before selecting either shortened release lane', function (): void {
    $workflow = releaseCiWorkflow();
    $classify = $workflow['jobs']['classify'];
    $script = $classify['steps'][2]['run'];

    expect($script)
        ->toContain('lane=full')
        ->toContain('ci-release-proof.php baseline --head "$RELEASE_HEAD"')
        ->toContain('release-policy.php classify --base "$base" --head "$RELEASE_HEAD"')
        ->toContain('[[ "$base" =~ ^[0-9a-f]{40}$ ]]')
        ->toContain('[[ "$RELEASE_EVENT" == push && "$RELEASE_REF" == refs/heads/main ]]')
        ->and($workflow['jobs']['documentation']['if'])
        ->toBe("needs.classify.outputs.lane == 'documentation' && needs.classify.outputs.base-proven == 'true'")
        ->and($workflow['jobs']['frontend-proof']['needs'])->toBe('classify')
        ->and($workflow['jobs']['frontend-proof']['if'])
        ->toBe("needs.classify.outputs.lane == 'frontend' && needs.classify.outputs.base-proven == 'true'")
        ->and($workflow['jobs']['release-approval']['needs'])->toBe([
            'validate', 'classify', 'documentation', 'frontend-proof', 'php-quality', 'frontend',
            'php-tests', 'infrastructure', 'windows-workflow', 'release-integrity',
        ])
        ->and($workflow['jobs']['release-approval']['steps'][0]['env']['FRONTEND_PROOF_RESULT'])
        ->toBe('${{ needs.frontend-proof.result }}');
});

it('keeps pull requests scheduled manual and non-main runs on the full lane', function (string $event, string $ref): void {
    $bash = PHP_OS_FAMILY === 'Windows' ? 'C:/Program Files/Git/bin/bash.exe' : (new ExecutableFinder)->find('bash');
    $output = tempnam(sys_get_temp_dir(), 'schooltool-ci-classify-');
    $script = releaseCiWorkflow()['jobs']['classify']['steps'][2]['run'];

    try {
        $process = new Process([$bash, '-c', "php() { exit 91; }\njq() { exit 92; }\n".$script], dirname(__DIR__, 2), [
            'RELEASE_EVENT' => $event,
            'RELEASE_REF' => $ref,
            'GITHUB_OUTPUT' => $output,
        ]);
        $process->run();

        expect($process->isSuccessful())->toBeTrue($process->getOutput().$process->getErrorOutput())
            ->and(file_get_contents($output))->toBe("lane=full\nbase=\nbase-proven=false\n");
    } finally {
        unlink($output);
    }
})->with([
    ['pull_request', 'refs/pull/123/merge'],
    ['schedule', 'refs/heads/main'],
    ['workflow_dispatch', 'refs/heads/main'],
    ['push', 'refs/heads/feature/example'],
]);

it('provides the locked Windows dependency extensions without bypassing platform checks', function (): void {
    $steps = releaseCiWorkflow()['jobs']['windows-workflow']['steps'];
    $setup = array_values(array_filter($steps, fn (array $step): bool => ($step['uses'] ?? '') === 'shivammathur/setup-php@v2'))[0];
    $extensions = array_map('trim', explode(',', $setup['with']['extensions']));
    $install = array_values(array_filter($steps, fn (array $step): bool => ($step['name'] ?? '') === 'Install PHP dependencies'))[0];
    $lock = json_decode(file_get_contents(dirname(__DIR__, 2).'/composer.lock'), true, flags: JSON_THROW_ON_ERROR);
    $requirements = [];

    foreach ([...$lock['packages'], ...$lock['packages-dev']] as $package) {
        $requirements += $package['require'] ?? [];
    }

    expect($requirements)->toHaveKeys(['ext-fileinfo', 'ext-sockets'])
        ->and($extensions)->toContain('fileinfo', 'sockets', 'pdo_sqlite')
        ->and($install['run'])->not->toContain('--ignore-platform-req');
});

it('boots the Windows dependency setup with an isolated database accepted by the unchanged safety provider', function (): void {
    $environment = releaseCiWorkflow()['jobs']['windows-workflow']['env'];

    expect($environment['APP_ENV'])->toBe('testing')
        ->and($environment['DB_CONNECTION'])->toBe('sqlite')
        ->and($environment['DB_DATABASE'])->toBe(':memory:')
        ->and($environment['DB_DATABASE_TEST'])->toBe(':memory:')
        ->and($environment['PULSE_ENABLED'])->toBeFalse();

    $environment = array_map(fn (mixed $value): string => is_bool($value) ? ($value ? 'true' : 'false') : (string) $value, $environment);
    $environment['APP_BASE_PATH'] = dirname(__DIR__, 2);
    $environment['APP_CONFIG_CACHE'] = sys_get_temp_dir().'/schooltool-ci-config-'.bin2hex(random_bytes(8)).'.php';
    $code = <<<'PHP'
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
echo json_encode($app->make('database.safety'), JSON_THROW_ON_ERROR);
PHP;
    $process = new Process([PHP_BINARY, '-r', $code], dirname(__DIR__, 2), $environment);
    $process->run();

    expect($process->isSuccessful())->toBeTrue($process->getOutput().$process->getErrorOutput());
    $safety = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
    expect($safety['is_testing_environment'])->toBeTrue()
        ->and($safety['current_connection'])->toBe('sqlite')
        ->and($safety['current_database'])->toBe(':memory:')
        ->and($safety['expected_testing_database'])->toBe(':memory:');
});

function runReleaseApproval(array $overrides, string $lane): Process
{
    $bash = PHP_OS_FAMILY === 'Windows' ? 'C:/Program Files/Git/bin/bash.exe' : (new ExecutableFinder)->find('bash');

    if ($bash === null) {
        throw new RuntimeException('The release approval shell requires Bash.');
    }

    $step = releaseCiWorkflow()['jobs']['release-approval']['steps'][0];
    $environment = array_fill_keys(array_keys($step['env']), 'success');
    $environment['RELEASE_LANE'] = $lane;
    $environment['BASE_PROVEN'] = 'true';
    $environment['DOCUMENTATION_RESULT'] = $lane === 'documentation' ? 'success' : 'skipped';
    $environment['FRONTEND_PROOF_RESULT'] = $lane === 'frontend' ? 'success' : 'skipped';

    if (in_array($lane, ['documentation', 'frontend'], true)) {
        foreach (['PHP_QUALITY_RESULT', 'PHP_TESTS_RESULT', 'INFRASTRUCTURE_RESULT', 'WINDOWS_WORKFLOW_RESULT'] as $name) {
            $environment[$name] = 'skipped';
        }
    }

    if ($lane === 'documentation') {
        $environment['FRONTEND_RESULT'] = 'skipped';
    }

    $process = new Process([$bash, '-c', $step['run']], dirname(__DIR__, 2), array_replace($environment, $overrides));
    $process->run();

    return $process;
}

it('rejects a failed skipped cancelled unknown or incomplete release gate', function (string $key, string $value, string $lane): void {
    expect(runReleaseApproval([$key => $value], $lane)->isSuccessful())->toBeFalse();
})->with([
    ['VALIDATE_RESULT', 'failure', 'full'],
    ['CLASSIFY_RESULT', 'skipped', 'full'],
    ['RELEASE_INTEGRITY_RESULT', 'cancelled', 'full'],
    ['PHP_QUALITY_RESULT', 'skipped', 'full'],
    ['FRONTEND_RESULT', 'pending', 'full'],
    ['PHP_TESTS_RESULT', '', 'full'],
    ['INFRASTRUCTURE_RESULT', 'failure', 'full'],
    ['WINDOWS_WORKFLOW_RESULT', 'skipped', 'full'],
    ['RELEASE_LANE', 'unknown', 'full'],
    ['DOCUMENTATION_RESULT', 'success', 'full'],
    ['FRONTEND_PROOF_RESULT', 'success', 'full'],
    ['FRONTEND_PROOF_RESULT', '', 'full'],
    ['BASE_PROVEN', 'false', 'documentation'],
    ['DOCUMENTATION_RESULT', 'skipped', 'documentation'],
    ['VALIDATE_RESULT', 'failure', 'documentation'],
    ['PHP_TESTS_RESULT', 'failure', 'documentation'],
    ['FRONTEND_PROOF_RESULT', 'success', 'documentation'],
    ['FRONTEND_PROOF_RESULT', 'cancelled', 'documentation'],
]);

it('rejects every incomplete or unexpected frontend lane gate', function (string $key, string|false $value): void {
    expect(runReleaseApproval([$key => $value], 'frontend')->isSuccessful())->toBeFalse();
})->with(function (): array {
    $expected = [
        'VALIDATE_RESULT' => 'success',
        'CLASSIFY_RESULT' => 'success',
        'RELEASE_INTEGRITY_RESULT' => 'success',
        'FRONTEND_RESULT' => 'success',
        'FRONTEND_PROOF_RESULT' => 'success',
        'DOCUMENTATION_RESULT' => 'skipped',
        'PHP_QUALITY_RESULT' => 'skipped',
        'PHP_TESTS_RESULT' => 'skipped',
        'INFRASTRUCTURE_RESULT' => 'skipped',
        'WINDOWS_WORKFLOW_RESULT' => 'skipped',
        'BASE_PROVEN' => 'true',
    ];
    $cases = [];

    foreach ($expected as $key => $required) {
        foreach (['success', 'skipped', 'failure', 'cancelled', 'pending', '', 'false', false] as $value) {
            if ($value !== $required) {
                $cases[$key.'='.var_export($value, true)] = [$key, $value];
            }
        }
    }

    return $cases;
});

it('accepts only a complete full or proven shortened release gate', function (string $lane): void {
    $process = runReleaseApproval([], $lane);

    expect($process->isSuccessful())->toBeTrue($process->getOutput().$process->getErrorOutput());
})->with(['full', 'documentation', 'frontend']);

it('discovers all PHP tests exactly once in sorted sequential batches of at most ten', function (): void {
    $directory = sys_get_temp_dir().'/schooltool-ci-batches-'.bin2hex(random_bytes(8));
    mkdir($directory.'/tests/Unit/Nested', 0700, true);
    mkdir($directory.'/tests/Feature', 0700, true);

    try {
        for ($index = 20; $index >= 0; $index--) {
            file_put_contents($directory.'/tests/Unit/Nested/Example'.$index.'Test.php', '<?php');
        }

        file_put_contents($directory.'/tests/Feature/FeatureTest.php', '<?php');
        file_put_contents($directory.'/tests/Unit/ignored.php', '<?php');
        $batches = CiPhpTests::batches($directory, 'coverage');
        $flat = array_merge(...$batches);
        $sorted = $flat;
        sort($sorted, SORT_STRING);

        expect(array_map('count', $batches))->toBe([10, 10, 2])
            ->and($flat)->toBe($sorted)->toHaveCount(22)
            ->and(array_unique($flat))->toHaveCount(22);
    } finally {
        (new Filesystem)->deleteDirectory($directory);
    }
});

it('stops immediately after a failed PHP batch instead of running later tests', function (): void {
    $seen = [];

    $execute = function () use (&$seen): void {
        CiPhpTests::executeBatches([['first'], ['second'], ['third']], function (array $files, int $number) use (&$seen): int {
            $seen[] = $number;

            return $number === 2 ? 7 : 0;
        });
    };
    expect($execute)->toThrow(RuntimeException::class, 'batch 2 failed');

    expect($seen)->toBe([1, 2]);
});

it('assigns the real MySQL snapshot and SQLite-only backup tests to dedicated complete batches', function (): void {
    $root = dirname(__DIR__, 2);
    $batches = CiPhpTests::batches($root, 'coverage');
    $all = array_merge(...$batches);
    $last = array_slice($batches, -2);

    expect($last)->toBe([
        ['tests/Feature/FeaturePreviewReadOnlySourceTest.php', 'tests/Feature/FeaturePreviewSnapshotTest.php'],
        ['tests/Feature/TeachingBackupCurriculumAttachmentsTest.php', 'tests/Feature/TeachingBackupEntryAreasTest.php'],
    ])->and(array_unique($all))->toHaveCount(count($all));

    $windows = array_merge(...CiPhpTests::batches($root, 'windows'));
    expect($windows)->toContain('tests/Unit/GitBranchWorkflowTest.php', 'tests/Unit/GitDeploymentSshTest.php', 'tests/Unit/DeploymentLauncherTest.php', 'tests/Unit/PreviewDeploymentTest.php');
});

it('rejects oversized and empty PHP test batches', function (array $batch): void {
    expect(fn () => CiPhpTests::executeBatches([$batch], fn (): int => 0))->toThrow(RuntimeException::class);
})->with([fn () => [], fn () => array_fill(0, 11, 'test.php')]);

it('displays warning details without changing the CI warning failure policy', function (): void {
    $command = CiPhpTests::testCommand();

    expect($command)->toContain('--display-warnings', '--exclude-group=integration')
        ->not->toContain('--fail-on-warnings', '--no-logging');
});

it('creates only an empty disposable CI environment and refuses an existing file', function (string $job): void {
    $steps = releaseCiWorkflow()['jobs'][$job]['steps'];
    $name = 'Create an empty CI environment file without credentials';
    $index = array_search($name, array_column($steps, 'name'), true);
    $composerIndex = array_search('Install PHP dependencies', array_column($steps, 'name'), true);
    expect($index)->toBeInt()->toBeLessThan($composerIndex);
    expect(preg_match('/^php -r "(.+)"$/', $steps[$index]['run'], $matches))->toBe(1);
    $directory = sys_get_temp_dir().'/schooltool-ci-dotenv-'.bin2hex(random_bytes(8));
    mkdir($directory, 0700);

    try {
        $process = new Process([PHP_BINARY, '-r', $matches[1]], $directory);
        $process->mustRun();
        expect(file_get_contents($directory.'/.env'))->toBe('');
        file_put_contents($directory.'/.env', 'existing fixture must be preserved');
        $process->run();
        expect($process->isSuccessful())->toBeFalse()
            ->and(file_get_contents($directory.'/.env'))->toBe('existing fixture must be preserved');
    } finally {
        (new Filesystem)->deleteDirectory($directory);
    }
})->with(['php-tests', 'windows-workflow']);

it('merges coverage from every batch and rejects missing or invalid reports', function (): void {
    $directory = sys_get_temp_dir().'/schooltool-ci-coverage-'.bin2hex(random_bytes(8));
    mkdir($directory, 0700);
    $source = $directory.'/example.php';
    file_put_contents($source, "<?php\nfunction example(): int {\n    return 1;\n}\n");
    $source = realpath($source);
    $filter = new Filter;
    $filter->includeFile($source);
    $driver = new class extends Driver
    {
        public function nameAndVersion(): string
        {
            return 'Fixture';
        }

        public function start(): void {}

        public function stop(): RawCodeCoverageData
        {
            return RawCodeCoverageData::fromXdebugWithoutPathCoverage([]);
        }
    };

    try {
        foreach (['first', 'second'] as $testId) {
            $coverage = new CodeCoverage($driver, $filter);
            $coverage->append(RawCodeCoverageData::fromXdebugWithoutPathCoverage([$source => [3 => 1]]), $testId);
            (new PhpCoverageReport)->process($coverage, $directory.'/'.$testId.'.php');
        }

        $merged = CiPhpTests::mergeCoverage(null, $directory.'/first.php');
        $merged = CiPhpTests::mergeCoverage($merged, $directory.'/second.php');
        expect($merged->getData()->lineCoverage()[$source][3])->toBe(['first', 'second']);
        expect(fn () => CiPhpTests::mergeCoverage($merged, $directory.'/missing.php'))->toThrow(RuntimeException::class);
        file_put_contents($directory.'/invalid.php', '<?php return null;');
        expect(fn () => CiPhpTests::mergeCoverage($merged, $directory.'/invalid.php'))->toThrow(RuntimeException::class);
    } finally {
        (new Filesystem)->deleteDirectory($directory);
    }
});
