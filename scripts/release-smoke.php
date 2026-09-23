<?php

declare(strict_types=1);
use Composer\InstalledVersions;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Database\DatabaseManager;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Request;

// Executed only by release-policy.php in a fresh process and credential-free snapshot.
[$script, $snapshot, $vendor] = $argv;

try {
    if (getenv('APP_ENV') !== 'testing' || getenv('DB_CONNECTION') !== 'sqlite' || getenv('DB_DATABASE') !== ':memory:' || is_file($snapshot.'/.env')) {
        throw new RuntimeException('Runtime smoke requires an isolated in-memory testing environment.');
    }
    $loader = require $vendor.'/autoload.php';
    $composer = json_decode(file_get_contents($snapshot.'/composer.json'), true, flags: JSON_THROW_ON_ERROR);
    if (isset($composer['autoload']['files']) || isset($composer['autoload']['classmap'])) {
        throw new RuntimeException('Runtime smoke requires explicit support for custom application autoload files/classmaps.');
    }
    foreach ($composer['autoload']['psr-4'] as $prefix => $paths) {
        if (! is_string($paths) || str_contains($paths, '..') || str_starts_with($paths, '/')) {
            throw new RuntimeException('Unsupported application autoload path.');
        }
        $loader->setPsr4($prefix, [$snapshot.'/'.$paths]);
        foreach ($loader->getClassMap() as $class => $file) {
            if (str_starts_with($class, $prefix)) {
                $loader->addClassMap([$class => $snapshot.'/'.$paths.str_replace('\\', '/', substr($class, strlen($prefix))).'.php']);
            }
        }
    }
    $lock = json_decode(file_get_contents($snapshot.'/composer.lock'), true, flags: JSON_THROW_ON_ERROR);
    foreach ($lock['packages'] as $package) {
        $reference = $package['dist']['reference'] ?? $package['source']['reference'] ?? null;
        if (! InstalledVersions::isInstalled($package['name']) || ($reference !== null && InstalledVersions::getReference($package['name']) !== $reference)) {
            throw new RuntimeException('Installed dependencies do not match the release: '.$package['name']);
        }
    }
    foreach (['bootstrap/cache', 'storage/framework/cache/data', 'storage/framework/sessions', 'storage/framework/views', 'storage/logs'] as $path) {
        if (! is_dir($snapshot.'/'.$path)) {
            mkdir($snapshot.'/'.$path, 0700, true);
        }
    }
    $app = require $snapshot.'/bootstrap/app.php';
    $app->resolving('db', static function (DatabaseManager $database): void {
        foreach (['mysql', 'mariadb', 'pgsql', 'sqlsrv'] as $driver) {
            $database->extend($driver, static function (): never {
                throw new RuntimeException('External database connections are forbidden in runtime smoke checks.');
            });
        }
    });
    $app->afterResolving(Factory::class, static function (Factory $http): void {
        $http->preventStrayRequests();
    });
    $kernel = $app->make(Kernel::class);
    $response = $kernel->handle(Request::create('/up', 'GET', server: ['HTTP_ACCEPT' => 'application/json']));
    if ($response->getStatusCode() !== 200 || realpath($app->basePath()) !== realpath($snapshot) || $app['config']->get('database.default') !== 'sqlite' || $app['config']->get('database.connections.sqlite.database') !== ':memory:') {
        throw new RuntimeException('The exact candidate failed its isolated application health request (HTTP '.$response->getStatusCode().').');
    }
    echo 'BOOTSTRAP_HEALTH_OK';
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage().PHP_EOL);
    exit(1);
}
