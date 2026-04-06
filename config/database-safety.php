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
    | Metadata for the runtime safety checks handled by the service provider.
    |
    */

    'verification' => [
        'check_testing_environment' => true,
        'ensure_test_database_is_used' => true,
        'production_databases' => ['schooltool', 'production_db', 'live_db'],
        'test_databases' => ['pest_test', 'testing', 'test'],
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
