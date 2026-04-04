<?php

namespace App\Providers;

use Illuminate\Support\Facades\App;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class DatabaseSafetyServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register safety checks
        $this->registerSafetyChecks();
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Run safety checks on boot
        $this->runSafetyChecks();
    }

    /**
     * Register safety check functions.
     */
    private function registerSafetyChecks(): void
    {
        $this->app->singleton('database.safety', function () {
            return [
                'is_testing_environment' => App::environment('testing'),
                'current_database' => config('database.connections.mysql.database'),
                'production_databases' => ['schooltool', 'production_db', 'live_db'],
                'test_databases' => ['pest_test', 'testing', 'test'],
            ];
        });
    }

    /**
     * Run safety checks to prevent production data deletion.
     */
    private function runSafetyChecks(): void
    {
        // Only run checks in testing environment
        if (! App::environment('testing')) {
            return;
        }

        $safety = $this->app->make('database.safety');
        $currentDb = $safety['current_database'];
        $productionDbs = $safety['production_databases'];

        // Check if we're trying to run tests against a production database
        if (in_array($currentDb, $productionDbs)) {
            throw new RuntimeException(
                "SAFETY VIOLATION: Tests are attempting to run against production database '{$currentDb}'! ".
                    'This would delete all production data. '.
                    "Tests must use a separate test database (configured in phpunit.xml as 'pest_test'). ".
                    "Check your configuration and ensure DB_DATABASE is set to 'pest_test' in testing environment."
            );
        }

        // Log safety check passed
        if (function_exists('info')) {
            info("Database safety check passed. Tests are running against '{$currentDb}' database.");
        }
    }

    /**
     * Check if current database is safe for testing.
     */
    public static function isDatabaseSafeForTesting(): bool
    {
        if (! App::environment('testing')) {
            return true;
        }

        $currentDb = config('database.connections.mysql.database');
        $productionDbs = ['schooltool', 'production_db', 'live_db'];

        return ! in_array($currentDb, $productionDbs);
    }

    /**
     * Get current database safety status.
     */
    public static function getSafetyStatus(): array
    {
        return [
            'environment' => App::environment(),
            'current_database' => config('database.connections.mysql.database'),
            'is_safe_for_testing' => self::isDatabaseSafeForTesting(),
            'warning' => ! self::isDatabaseSafeForTesting()
                ? 'WARNING: Tests may delete production data!'
                : 'Safe: Tests are using a test database.',
            'timestamp' => now()->toDateTimeString(),
        ];
    }
}
