<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Database Safety Configuration
    |--------------------------------------------------------------------------
    |
    | This configuration file documents and enforces safety rules for database
    | operations in the application, particularly for tests and migrations.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Safety Rules
    |--------------------------------------------------------------------------
    |
    | The following rules MUST be followed at all times:
    |
    | 1. Tests MUST NEVER delete or modify real production data.
    | 2. Tests MUST use a separate testing database (configured in phpunit.xml).
    | 3. Migrations run during tests MUST only affect the testing database.
    | 4. Database operations in tests MUST be isolated and cleaned up after each test.
    | 5. Real user data MUST be protected from accidental deletion or modification.
    |
    */

    'rules' => [
        'tests_use_separate_database' => true,
        'testing_database' => env('DB_DATABASE_TEST', 'pest_test'),
        'prevent_production_data_deletion' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Current Configuration
    |--------------------------------------------------------------------------
    |
    | The current database configuration for different environments.
    |
    */

    'environments' => [
        'production' => [
            'database' => env('DB_DATABASE', 'schooltool'),
            'warning' => 'This database contains real user data. NEVER run tests against this database.',
        ],
        'testing' => [
            'database' => env('DB_DATABASE', 'pest_test'),
            'note' => 'Tests use a separate database to prevent real data deletion.',
        ],
        'local' => [
            'database' => env('DB_DATABASE', 'schooltool'),
            'warning' => 'Even in local development, be cautious with data deletion operations.',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Verification
    |--------------------------------------------------------------------------
    |
    | Methods to verify safety rules are being followed.
    |
    */

    'verification' => [
        'check_testing_environment' => function () {
            return app()->environment('testing') &&
                   config('database.connections.mysql.database') === 'pest_test';
        },
        'ensure_test_database_is_used' => function () {
            if (app()->environment('testing') &&
                config('database.connections.mysql.database') !== 'pest_test') {
                throw new RuntimeException(
                    'Tests are attempting to run against the production database! '.
                    'This is prohibited. Check phpunit.xml configuration.'
                );
            }
        },
    ],

    /*
    |--------------------------------------------------------------------------
    | Emergency Procedures
    |--------------------------------------------------------------------------
    |
    | What to do if real data is accidentally affected:
    |
    | 1. IMMEDIATELY stop all operations
    | 2. Check database backups
    | 3. Contact the system administrator
    | 4. DO NOT attempt to fix without proper backup verification
    |
    */

    'emergency' => [
        'contact' => env('SYSADMIN_EMAIL', 'admin@example.com'),
        'backup_location' => env('DB_BACKUP_PATH', 'storage/backups'),
        'recovery_procedure' => 'Use the latest verified backup to restore data.',
    ],
];
