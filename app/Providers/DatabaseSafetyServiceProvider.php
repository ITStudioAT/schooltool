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
            $defaultConnection = (string) config('database.default');
            $currentDatabase = (string) config("database.connections.{$defaultConnection}.database");

            return [
                'is_testing_environment' => App::environment('testing'),
                'current_connection' => $defaultConnection,
                'current_database' => $currentDatabase,
                'expected_testing_database' => (string) config('database-safety.rules.testing_database', 'pest_test'),
                'production_databases' => (array) config('database-safety.verification.production_databases', ['schooltool', 'production_db', 'live_db']),
                'test_databases' => (array) config('database-safety.verification.test_databases', ['pest_test', 'testing', 'test']),
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
        $expectedTestingDb = $safety['expected_testing_database'];
        $productionDbs = $safety['production_databases'];

        if ($currentDb !== $expectedTestingDb) {
            throw new RuntimeException(
                "SAFETY VIOLATION: Tests must use database '{$expectedTestingDb}', but '{$currentDb}' is configured. ".
                    'This would delete the wrong data. '.
                    "Set DB_DATABASE_TEST='{$expectedTestingDb}' and keep DB_DATABASE pointed at the test database when APP_ENV=testing."
            );
        }

        if (in_array($currentDb, $productionDbs, true)) {
            throw new RuntimeException(
                "SAFETY VIOLATION: Tests are attempting to run against production database '{$currentDb}'! ".
                    'This would delete all production data. '.
                    "Tests must use a separate test database (configured in phpunit.xml as '{$expectedTestingDb}'). ".
                    "Check your configuration and ensure DB_DATABASE is set to '{$expectedTestingDb}' in testing environment."
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

        $defaultConnection = (string) config('database.default');
        $currentDb = (string) config("database.connections.{$defaultConnection}.database");
        $expectedTestingDb = (string) config('database-safety.rules.testing_database', 'pest_test');
        $productionDbs = (array) config('database-safety.verification.production_databases', ['schooltool', 'production_db', 'live_db']);

        return $currentDb === $expectedTestingDb && ! in_array($currentDb, $productionDbs, true);
    }

    /**
     * Get current database safety status.
     */
    public static function getSafetyStatus(): array
    {
        $defaultConnection = (string) config('database.default');
        $currentDb = (string) config("database.connections.{$defaultConnection}.database");
        $expectedTestingDb = (string) config('database-safety.rules.testing_database', 'pest_test');

        return [
            'environment' => App::environment(),
            'current_connection' => $defaultConnection,
            'current_database' => $currentDb,
            'expected_testing_database' => $expectedTestingDb,
            'is_safe_for_testing' => self::isDatabaseSafeForTesting(),
            'warning' => ! self::isDatabaseSafeForTesting()
                ? 'WARNING: Tests may delete production data or the wrong database!'
                : "Safe: Tests are using '{$expectedTestingDb}'.",
            'timestamp' => now()->toDateTimeString(),
        ];
    }
}
