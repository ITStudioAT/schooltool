<?php

use Illuminate\Filesystem\Filesystem;

$projectRoot = dirname(__DIR__);

require $projectRoot.'/vendor/autoload.php';

$testProcessToken = (string) ($_SERVER['TEST_TOKEN'] ?? $_ENV['TEST_TOKEN'] ?? 'default');
$testProcessToken = preg_replace('/[^a-zA-Z0-9_-]/', '-', $testProcessToken) ?: 'default';
$testingStorageRoot = $projectRoot.'/storage/framework/testing';
$testStoragePath = $testingStorageRoot.'/process-'.$testProcessToken;

$normalizedTestingStorageRoot = rtrim(str_replace('\\', '/', $testingStorageRoot), '/').'/';
$normalizedTestStoragePath = rtrim(str_replace('\\', '/', $testStoragePath), '/').'/';

if (! str_starts_with($normalizedTestStoragePath, $normalizedTestingStorageRoot)) {
    throw new RuntimeException('The PHPUnit storage path must remain inside storage/framework/testing.');
}

$filesystem = new Filesystem;
$filesystem->deleteDirectory($testStoragePath);

foreach ([
    'app/private',
    'app/public',
    'framework/cache/data',
    'framework/sessions',
    'framework/views',
    'logs',
] as $directory) {
    $filesystem->ensureDirectoryExists($testStoragePath.'/'.$directory);
}

$_ENV['LARAVEL_STORAGE_PATH'] = $testStoragePath;
$_SERVER['LARAVEL_STORAGE_PATH'] = $testStoragePath;
