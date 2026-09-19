<?php

declare(strict_types=1);

namespace SchoolTool\WorkflowTests;

use PDO;
use RuntimeException;
use Throwable;

function localConnection(): PDO
{
    return new PDO('mysql:host=127.0.0.1;port=3306;charset=utf8mb4', 'root', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 5,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}

function assertOwnedDatabaseName(string $database): void
{
    if (preg_match('/\Apest_test_test_[0-9]{24}\z/', $database) !== 1) {
        throw new RuntimeException('Only an individually created workflow test database may be removed.');
    }
}

/** @param resource $handle */
function writeReceipt($handle, array $receipt): void
{
    $contents = json_encode($receipt, JSON_THROW_ON_ERROR)."\n";
    rewind($handle);
    if (! ftruncate($handle, 0) || fwrite($handle, $contents) !== strlen($contents) || ! fflush($handle) || ! fsync($handle)) {
        throw new RuntimeException('Could not persist test database ownership.');
    }
}

/** @return array{database: string, receipt: string} */
function createDatabase(string $receiptPath): array
{
    $directory = realpath(dirname($receiptPath));
    if ($directory === false || ! is_dir($directory) || is_link($receiptPath)) {
        throw new RuntimeException('The test database receipt requires an existing local directory.');
    }
    $receiptPath = $directory.DIRECTORY_SEPARATOR.basename($receiptPath);
    if (file_exists($receiptPath)) {
        throw new RuntimeException('The test database ownership receipt already exists.');
    }
    $handle = @fopen($receiptPath, 'x+');
    if ($handle === false) {
        throw new RuntimeException('The test database ownership receipt already exists or cannot be created.');
    }
    chmod($receiptPath, 0600);
    $database = 'pest_test_test_';
    for ($index = 0; $index < 24; $index++) {
        $database .= (string) random_int(0, 9);
    }
    $receipt = ['format' => 'schooltool-owned-test-database-v1', 'host' => '127.0.0.1', 'port' => 3306, 'database' => $database, 'state' => 'pending'];
    $created = false;
    $connection = null;
    try {
        if (! flock($handle, LOCK_EX)) {
            throw new RuntimeException('Could not lock the test database ownership receipt.');
        }
        writeReceipt($handle, $receipt);
        $connection = localConnection();
        $connection->exec("CREATE DATABASE `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $created = true;
        $receipt['state'] = 'created';
        writeReceipt($handle, $receipt);
    } catch (Throwable $exception) {
        if ($created) {
            try {
                $connection->exec("DROP DATABASE `{$database}`");
            } catch (Throwable $cleanupException) {
                throw new RuntimeException("Owned test database {$database} remains; inspect {$receiptPath}. Cleanup failed: ".$cleanupException->getMessage(), previous: $exception);
            }
        }
        fclose($handle);
        $handle = null;
        if (! unlink($receiptPath)) {
            throw new RuntimeException("Test database setup failed and its receipt could not be removed: {$receiptPath}", previous: $exception);
        }
        throw $exception;
    } finally {
        if (is_resource($handle)) {
            fclose($handle);
        }
    }

    return ['database' => $database, 'receipt' => $receiptPath];
}

function removeDatabase(string $receiptPath, string $expectedDatabase): void
{
    assertOwnedDatabaseName($expectedDatabase);
    if (is_link($receiptPath) || ! is_file($receiptPath) || filesize($receiptPath) > 4096) {
        throw new RuntimeException('A valid local ownership receipt is required before test database cleanup.');
    }
    $handle = fopen($receiptPath, 'r+');
    if ($handle === false) {
        throw new RuntimeException('Could not open the test database ownership receipt.');
    }
    try {
        if (! flock($handle, LOCK_EX)) {
            throw new RuntimeException('Could not lock the test database ownership receipt.');
        }
        $receipt = json_decode(stream_get_contents($handle), true, flags: JSON_THROW_ON_ERROR);
        if (($receipt['format'] ?? null) !== 'schooltool-owned-test-database-v1'
            || ($receipt['database'] ?? null) !== $expectedDatabase
            || ($receipt['host'] ?? null) !== '127.0.0.1'
            || ($receipt['port'] ?? null) !== 3306
            || ! in_array($receipt['state'] ?? null, ['created', 'removed'], true)) {
            throw new RuntimeException('The receipt does not prove ownership of this local test database.');
        }
        if ($receipt['state'] === 'created') {
            localConnection()->exec("DROP DATABASE IF EXISTS `{$expectedDatabase}`");
            $receipt['state'] = 'removed';
            writeReceipt($handle, $receipt);
        }
    } finally {
        fclose($handle);
    }
    if (! unlink($receiptPath)) {
        throw new RuntimeException("The test database was removed, but its receipt remains at {$receiptPath}.");
    }
}

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    try {
        if (($argv[1] ?? '') === 'create' && count($argv) === 3) {
            fwrite(STDOUT, json_encode(createDatabase($argv[2]), JSON_THROW_ON_ERROR)."\n");
        } elseif (($argv[1] ?? '') === 'remove' && count($argv) === 4) {
            removeDatabase($argv[2], $argv[3]);
        } else {
            throw new RuntimeException('Usage: workflow-test-database.php create RECEIPT | remove RECEIPT OWNED_DATABASE');
        }
    } catch (Throwable $exception) {
        fwrite(STDERR, $exception->getMessage()."\n");
        exit(1);
    }
}
