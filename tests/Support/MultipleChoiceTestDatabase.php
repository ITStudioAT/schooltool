<?php

/** Refuse database test discovery before RefreshDatabase can run against an unowned database. */
function requireMultipleChoiceTestDatabase(): void
{
    $value = fn (string $key): string => (string) ($_SERVER[$key] ?? $_ENV[$key] ?? getenv($key) ?: '');
    $database = $value('DB_DATABASE');
    $receiptPath = $value('SCHOOLTOOL_MC_TEST_RECEIPT');
    if (preg_match('/\Apest_test_test_[0-9]{24}\z/', $database) !== 1
        || $value('DB_DATABASE_TEST') !== $database
        || $value('DB_HOST') !== '127.0.0.1'
        || $value('DB_PORT') !== '3306'
        || $value('DB_CONNECTION') !== 'mysql'
        || $receiptPath === '' || ! is_file($receiptPath) || is_link($receiptPath)
        || filesize($receiptPath) > 4096) {
        throw new RuntimeException('Multiple Choice database tests require an explicitly bound, individually owned database and SCHOOLTOOL_MC_TEST_RECEIPT.');
    }

    $receipt = json_decode(file_get_contents($receiptPath), true, flags: JSON_THROW_ON_ERROR);
    if (($receipt['format'] ?? null) !== 'schooltool-owned-test-database-v1'
        || ($receipt['database'] ?? null) !== $database || ($receipt['state'] ?? null) !== 'created'
        || ($receipt['host'] ?? null) !== '127.0.0.1' || ($receipt['port'] ?? null) !== 3306) {
        throw new RuntimeException('The receipt does not prove ownership of the Multiple Choice test database.');
    }
}
