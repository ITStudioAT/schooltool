<?php

use Illuminate\Container\Container;
use Illuminate\Foundation\Application;

function frameworkConfigTestBackupEnv(string $name): string|false
{
    $value = getenv($name);

    return $value === false ? false : $value;
}

function frameworkConfigTestRestoreEnv(string $name, string|false $value): void
{
    if ($value === false) {
        putenv($name);
        unset($_ENV[$name], $_SERVER[$name]);

        return;
    }

    putenv(sprintf('%s=%s', $name, $value));
    $_ENV[$name] = $value;
    $_SERVER[$name] = $value;
}

it('defaults framework database-backed runtime config to mysql when db env is missing', function () {
    $projectRoot = dirname(__DIR__, 2);
    $names = ['DB_CONNECTION', 'SESSION_CONNECTION', 'DB_CACHE_CONNECTION', 'DB_CACHE_LOCK_CONNECTION', 'DB_QUEUE_CONNECTION'];
    $backup = [];
    $originalApp = Container::getInstance();

    foreach ($names as $name) {
        $backup[$name] = frameworkConfigTestBackupEnv($name);
        putenv($name);
        unset($_ENV[$name], $_SERVER[$name]);
    }

    try {
        Container::setInstance(new Application($projectRoot));

        $database = require $projectRoot.'/config/database.php';
        $session = require $projectRoot.'/config/session.php';
        $cache = require $projectRoot.'/config/cache.php';
        $queue = require $projectRoot.'/config/queue.php';

        expect($database['default'])->toBe('mysql')
            ->and($session['connection'])->toBe('mysql')
            ->and($cache['stores']['database']['connection'])->toBe('mysql')
            ->and($cache['stores']['database']['lock_connection'])->toBe('mysql')
            ->and($queue['connections']['database']['connection'])->toBe('mysql')
            ->and($queue['batching']['database'])->toBe('mysql')
            ->and($queue['failed']['database'])->toBe('mysql');
    } finally {
        Container::setInstance($originalApp);

        foreach ($backup as $name => $value) {
            frameworkConfigTestRestoreEnv($name, $value);
        }
    }
});
