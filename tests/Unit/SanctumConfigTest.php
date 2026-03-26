<?php

use Tests\TestCase;

uses(TestCase::class);

it('normalizes configured sanctum stateful domains to hosts', function () {
    $previousStatefulDomains = getenv('SANCTUM_STATEFUL_DOMAINS');
    $previousAppUrl = getenv('APP_URL');
    $previousFrontendUrl = getenv('FRONTEND_URL');

    putenv('SANCTUM_STATEFUL_DOMAINS=https://SchoolTool.test, http://localhost:8000, 127.0.0.1:5173');
    putenv('APP_URL=http://schooltool.test');
    putenv('FRONTEND_URL=https://schooltool.test:8443');

    $_ENV['SANCTUM_STATEFUL_DOMAINS'] = 'https://SchoolTool.test, http://localhost:8000, 127.0.0.1:5173';
    $_SERVER['SANCTUM_STATEFUL_DOMAINS'] = 'https://SchoolTool.test, http://localhost:8000, 127.0.0.1:5173';
    $_ENV['APP_URL'] = 'http://schooltool.test';
    $_SERVER['APP_URL'] = 'http://schooltool.test';
    $_ENV['FRONTEND_URL'] = 'https://schooltool.test:8443';
    $_SERVER['FRONTEND_URL'] = 'https://schooltool.test:8443';

    try {
        $sanctumConfig = require base_path('config/sanctum.php');

        expect($sanctumConfig['stateful'])->toContain(
            'schooltool.test',
            'localhost:8000',
            '127.0.0.1:5173',
            'schooltool.test:8443',
        )->not->toContain(
            'https://schooltool.test',
            'http://localhost:8000',
        );
    } finally {
        restoreSanctumConfigEnv('SANCTUM_STATEFUL_DOMAINS', $previousStatefulDomains);
        restoreSanctumConfigEnv('APP_URL', $previousAppUrl);
        restoreSanctumConfigEnv('FRONTEND_URL', $previousFrontendUrl);
    }
});

it('keeps the current app host stateful even when the env list is stale', function () {
    $previousStatefulDomains = getenv('SANCTUM_STATEFUL_DOMAINS');
    $previousAppUrl = getenv('APP_URL');
    $previousFrontendUrl = getenv('FRONTEND_URL');

    putenv('SANCTUM_STATEFUL_DOMAINS=localhost:8000');
    putenv('APP_URL=http://schooltool.test');
    putenv('FRONTEND_URL');

    $_ENV['SANCTUM_STATEFUL_DOMAINS'] = 'localhost:8000';
    $_SERVER['SANCTUM_STATEFUL_DOMAINS'] = 'localhost:8000';
    $_ENV['APP_URL'] = 'http://schooltool.test';
    $_SERVER['APP_URL'] = 'http://schooltool.test';
    unset($_ENV['FRONTEND_URL'], $_SERVER['FRONTEND_URL']);

    try {
        $sanctumConfig = require base_path('config/sanctum.php');

        expect($sanctumConfig['stateful'])->toContain('localhost:8000', 'schooltool.test');
    } finally {
        restoreSanctumConfigEnv('SANCTUM_STATEFUL_DOMAINS', $previousStatefulDomains);
        restoreSanctumConfigEnv('APP_URL', $previousAppUrl);
        restoreSanctumConfigEnv('FRONTEND_URL', $previousFrontendUrl);
    }
});

function restoreSanctumConfigEnv(string $key, string|false $value): void
{
    if ($value === false) {
        putenv($key);
        unset($_ENV[$key], $_SERVER[$key]);

        return;
    }

    putenv("{$key}={$value}");
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
}
