<?php

use function SchoolTool\WorkflowTests\createDatabase;
use function SchoolTool\WorkflowTests\localConnection;
use function SchoolTool\WorkflowTests\removeDatabase;

require_once dirname(__DIR__, 2).'/scripts/workflow-test-database.php';

it('creates and removes only its own fresh local MySQL schema with an ownership receipt', function (): void {
    if (PHP_OS_FAMILY !== 'Windows') {
        $this->markTestSkipped('This workflow provisions disposable databases on the Windows development machine.');
    }
    $receipt = sys_get_temp_dir().'/schooltool-owned-db-'.bin2hex(random_bytes(12)).'.json';
    $owned = createDatabase($receipt);
    $connection = localConnection();

    try {
        expect($owned['database'])->toMatch('/^pest_test_test_[0-9]{24}$/')
            ->and($owned['database'])->not->toBe('pest_test')
            ->and(json_decode(file_get_contents($receipt), true, flags: JSON_THROW_ON_ERROR)['state'])->toBe('created');
        $connection->exec('CREATE TABLE `'.$owned['database'].'`.`workflow_probe` (id INT PRIMARY KEY)');
        $connection->exec('INSERT INTO `'.$owned['database'].'`.`workflow_probe` (id) VALUES (1)');

        expect(fn () => removeDatabase($receipt, 'pest_test'))->toThrow(RuntimeException::class)
            ->and((int) $connection->query('SELECT COUNT(*) FROM `'.$owned['database'].'`.`workflow_probe`')->fetchColumn())->toBe(1);
    } finally {
        removeDatabase($receipt, $owned['database']);
    }

    $query = $connection->prepare('SELECT COUNT(*) FROM information_schema.schemata WHERE schema_name = ?');
    $query->execute([$owned['database']]);
    expect((int) $query->fetchColumn())->toBe(0)
        ->and(is_file($receipt))->toBeFalse();
})->group('integration');

it('rejects cleanup without matching successful local ownership', function (array $overrides, string $expected): void {
    $receipt = sys_get_temp_dir().'/schooltool-invalid-db-'.bin2hex(random_bytes(12)).'.json';
    $metadata = array_replace([
        'format' => 'schooltool-owned-test-database-v1',
        'host' => '127.0.0.1',
        'port' => 3306,
        'database' => 'pest_test_test_123456789012345678901234',
        'state' => 'created',
    ], $overrides);
    file_put_contents($receipt, json_encode($metadata, JSON_THROW_ON_ERROR));

    try {
        expect(fn () => removeDatabase($receipt, $expected))->toThrow(RuntimeException::class)
            ->and(is_file($receipt))->toBeTrue();
    } finally {
        unlink($receipt);
    }
})->with([
    'existing shared database' => [[], 'pest_test'],
    'wrong schema' => [[], 'pest_test_test_999999999999999999999999'],
    'remote host' => [['host' => 'database.example.test'], 'pest_test_test_123456789012345678901234'],
    'creation never completed' => [['state' => 'pending'], 'pest_test_test_123456789012345678901234'],
    'wrong receipt type' => [['format' => 'untrusted'], 'pest_test_test_123456789012345678901234'],
]);

it('never reuses an existing ownership receipt when creating a test database', function (): void {
    $receipt = sys_get_temp_dir().'/schooltool-existing-db-'.bin2hex(random_bytes(12)).'.json';
    file_put_contents($receipt, 'Keep this file');

    try {
        expect(fn () => createDatabase($receipt))->toThrow(RuntimeException::class)
            ->and(file_get_contents($receipt))->toBe('Keep this file');
    } finally {
        unlink($receipt);
    }
});
