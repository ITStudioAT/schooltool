<?php

use App\Support\LocalMigrationSafety;

it('accepts only a local development database target', function (): void {
    $connection = [
        'driver' => 'mysql',
        'host' => '127.0.0.1',
        'database' => 'schooltool',
        'url' => null,
        'unix_socket' => '',
    ];

    expect(LocalMigrationSafety::allowsTarget('local', false, 'mysql', $connection))->toBeTrue()
        ->and(LocalMigrationSafety::allowsTarget('local', false, 'mysql', [...$connection, 'database' => 'schooltool_feature_helpers']))->toBeTrue()
        ->and(LocalMigrationSafety::allowsTarget('production', false, 'mysql', $connection))->toBeFalse()
        ->and(LocalMigrationSafety::allowsTarget('local', true, 'mysql', $connection))->toBeFalse()
        ->and(LocalMigrationSafety::allowsTarget('local', false, 'cloudways', $connection))->toBeFalse()
        ->and(LocalMigrationSafety::allowsTarget('local', false, 'mysql', [...$connection, 'host' => 'db.example.test']))->toBeFalse()
        ->and(LocalMigrationSafety::allowsTarget('local', false, 'mysql', [...$connection, 'database' => 'schooltool_preview']))->toBeFalse()
        ->and(LocalMigrationSafety::allowsTarget('local', false, 'mysql', [...$connection, 'database' => 'pest_test']))->toBeFalse()
        ->and(LocalMigrationSafety::allowsTarget('local', false, 'mysql', [...$connection, 'database' => 'stocks']))->toBeFalse()
        ->and(LocalMigrationSafety::allowsTarget('local', false, 'mysql', [...$connection, 'url' => 'mysql://remote']))->toBeFalse();
});

it('requires an inspectable SQL plan for every pending migration', function (): void {
    $name = '2026_09_26_165732_create_teaching_class_heads_table';
    $output = "  $name .... Pending\n  ⇂ create table `teaching_class_heads` (`id` bigint unsigned)\n  ⇂ alter table `teaching_class_heads` add index `heads_index`(`id`)\n";

    expect(LocalMigrationSafety::plannedStatements($output, [$name]))->toBe([
        'create table `teaching_class_heads` (`id` bigint unsigned)',
        'alter table `teaching_class_heads` add index `heads_index`(`id`)',
    ])->and(LocalMigrationSafety::plannedStatements($output, [$name, '2026_09_26_180000_unknown']))->toBeNull();
});

it('permits additive schema SQL and blocks destructive or data-changing SQL', function (string $sql, bool $allowed): void {
    expect(LocalMigrationSafety::allowsStatement($sql))->toBe($allowed);
})->with([
    ['create table `teaching_class_heads` (`id` bigint unsigned)', true],
    ['alter table `teaching_class_heads` add constraint `heads_school_foreign` foreign key (`school_id`) references `schools` (`id`) on delete cascade', true],
    ['alter table `teaching_class_heads` add index `heads_index`(`school_id`)', true],
    ['alter table `teachers` add `middle_name` varchar(255) null', true],
    ['drop table `teachers`', false],
    ['alter table `teachers` drop column `name`', false],
    ['alter table `teachers` add `name` varchar(255), drop column `old_name`', false],
    ['update `teachers` set `name` = null', false],
    ['create table `new_table` (`id` integer); drop table `teachers`', false],
]);
