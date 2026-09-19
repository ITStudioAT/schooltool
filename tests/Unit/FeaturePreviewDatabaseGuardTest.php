<?php

use App\Services\FeaturePreviewDatabaseGuard;

it('accepts only the intended schema privileges', function (array $grants, bool $readOnly): void {
    (new FeaturePreviewDatabaseGuard)->assertGrants($grants, 'schooltool', $readOnly);
    expect(true)->toBeTrue();
})->with([
    'read only' => [['GRANT USAGE ON *.* TO `reader`@`localhost`', 'GRANT SELECT ON `schooltool`.* TO `reader`@`localhost`'], true],
    'target' => [['GRANT ALL PRIVILEGES ON `schooltool`.* TO `preview`@`localhost`'], false],
]);

it('rejects unsafe or unrecognized database grants', function (array $grants, bool $readOnly): void {
    expect(fn () => (new FeaturePreviewDatabaseGuard)->assertGrants($grants, 'schooltool', $readOnly))->toThrow(RuntimeException::class);
})->with([
    'empty' => [[], true],
    'source writes' => [['GRANT SELECT, INSERT ON `schooltool`.* TO `reader`@`localhost`'], true],
    'source all' => [['GRANT ALL PRIVILEGES ON `schooltool`.* TO `reader`@`localhost`'], true],
    'global read' => [['GRANT SELECT ON *.* TO `reader`@`localhost`'], true],
    'global writes' => [['GRANT ALL PRIVILEGES ON *.* TO `preview`@`localhost`'], false],
    'other schema' => [['GRANT ALL PRIVILEGES ON `production`.* TO `preview`@`localhost`'], false],
    'role' => [['GRANT `administrator`@`%` TO `preview`@`localhost`'], false],
    'grant option' => [['GRANT SELECT ON `schooltool`.* TO `reader`@`localhost` WITH GRANT OPTION'], true],
    'usage grant option' => [['GRANT USAGE ON *.* TO `reader`@`localhost` WITH GRANT OPTION'], true],
    'schema wildcard' => [['GRANT SELECT ON `school%`.* TO `reader`@`localhost`'], true],
    'column grants' => [['GRANT SELECT (password) ON `schooltool`.* TO `reader`@`localhost`'], true],
]);

it('handles literal escaped schema underscores without accepting wildcard underscores', function (): void {
    $guard = new FeaturePreviewDatabaseGuard;
    $guard->assertGrants(['GRANT SELECT ON `schooltool\\_preview`.* TO `reader`@`localhost`'], 'schooltool_preview', true);
    expect(fn () => $guard->assertGrants(['GRANT SELECT ON `schooltool_preview`.* TO `reader`@`localhost`'], 'schooltool_preview', true))->toThrow(RuntimeException::class);
});

it('rejects unsafe table definitions before any import', function (string $definition): void {
    expect(fn () => (new FeaturePreviewDatabaseGuard)->assertCreateStatement('users', $definition))->toThrow(RuntimeException::class);
})->with([
    'another table' => 'CREATE TABLE `other` (`id` bigint) ENGINE=InnoDB',
    'nontransactional' => 'CREATE TABLE `users` (`id` bigint) ENGINE=MyISAM',
    'extra statement' => 'CREATE TABLE `users` (`id` bigint) ENGINE=InnoDB; DROP DATABASE main',
    'mysql execution comment' => 'CREATE TABLE `users` (`id` bigint) ENGINE=InnoDB /*! something */',
    'outside file' => "CREATE TABLE `users` (`id` bigint) ENGINE=InnoDB DATA DIRECTORY='/outside'",
    'foreign schema' => 'CREATE TABLE `users` (`id` bigint, FOREIGN KEY (`id`) REFERENCES `live`.`schools` (`id`)) ENGINE=InnoDB',
]);

it('accepts a regular transactional table definition', function (): void {
    (new FeaturePreviewDatabaseGuard)->assertCreateStatement('users', 'CREATE TABLE `users` (`id` bigint NOT NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    expect(true)->toBeTrue();
});
