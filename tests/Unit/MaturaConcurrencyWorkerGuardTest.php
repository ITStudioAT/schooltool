<?php

use Symfony\Component\Process\Process;

function runMaturaConcurrencyGuardWorker(array $overrides, array $arguments = []): Process
{
    $root = dirname(__DIR__, 2);
    $environment = array_replace([
        'APP_ENV' => 'testing',
        'APP_CONFIG_CACHE' => sys_get_temp_dir().'/schooltool-matura-guard-missing.php',
        'DB_CONNECTION' => 'mysql',
        'DB_HOST' => '127.0.0.1',
        'DB_DATABASE' => 'pest_test_test_123456789012345678901234',
        'DB_DATABASE_TEST' => 'pest_test_test_123456789012345678901234',
        'DB_URL' => '',
        'CACHE_STORE' => 'array',
        'SESSION_DRIVER' => 'array',
        'GITHUB_ACTIONS' => 'false',
        'RUNNER_ENVIRONMENT' => '',
    ], $overrides);
    $environment['DB_DATABASE_TEST'] = $environment['DB_DATABASE'];
    $process = new Process([PHP_BINARY, $root.'/tests/Support/matura-concurrency-worker.php', ...$arguments], $root, $environment, timeout: 20);

    $process->run();

    return $process;
}

test('concurrent Matura worker refuses unsafe database environments before processing a request', function (array $overrides): void {
    $process = runMaturaConcurrencyGuardWorker($overrides);

    expect($process->getExitCode())->toBe(1);
    expect($process->getErrorOutput())->toContain('Concurrent Matura tests require an owned disposable local database or the hosted CI database.');
    expect($process->getOutput())->toBe('');
})->with([
    'ordinary local test database' => [['DB_DATABASE' => 'pest_test', 'DB_DATABASE_TEST' => 'pest_test']],
    'unowned database on hosted CI' => [['DB_DATABASE' => 'unowned_database', 'GITHUB_ACTIONS' => 'true', 'RUNNER_ENVIRONMENT' => 'github-hosted']],
    'self-hosted CI shared database' => [['DB_DATABASE' => 'pest_test', 'GITHUB_ACTIONS' => 'true', 'RUNNER_ENVIRONMENT' => 'self-hosted']],
    'host marker without GitHub Actions' => [['DB_DATABASE' => 'pest_test', 'RUNNER_ENVIRONMENT' => 'github-hosted']],
    'unowned database with test prefix' => [['DB_DATABASE' => 'pest_test_test_unowned']],
    'remote database host' => [['DB_HOST' => 'database.example.test']],
    'database URL overriding local configuration' => [['DB_URL' => 'mysql://root@database.example.test/schooltool']],
    'non-testing application' => [['APP_ENV' => 'production']],
    'non-MySQL connection' => [['DB_CONNECTION' => 'sqlite']],
]);

test('concurrent Matura worker accepts isolated databases and reaches request parsing without querying a database', function (array $overrides): void {
    $process = runMaturaConcurrencyGuardWorker($overrides, ['0', '0', 'invalid-json', '0']);

    expect($process->getOutput())->toContain('Syntax error');
    expect($process->getOutput().$process->getErrorOutput())->not->toContain('Concurrent Matura tests require');
})->with([
    'owned local database' => [[]],
    'owned localhost database' => [['DB_HOST' => 'localhost']],
    'owned IPv6 loopback database' => [['DB_HOST' => '::1']],
    'ephemeral GitHub-hosted database' => [['DB_DATABASE' => 'pest_test', 'GITHUB_ACTIONS' => 'true', 'RUNNER_ENVIRONMENT' => 'github-hosted']],
]);
