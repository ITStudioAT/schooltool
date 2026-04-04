<?php

namespace App\Console\Commands;

use App\Providers\DatabaseSafetyServiceProvider;
use Illuminate\Console\Command;

class CheckDatabaseSafetyCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:safety-check';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check database safety configuration and warn if tests might delete production data';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $this->info('🔒 Database Safety Check');
        $this->line('');

        // Get safety status
        $status = DatabaseSafetyServiceProvider::getSafetyStatus();

        // Display status
        $this->table(
            ['Setting', 'Value'],
            [
                ['Environment', $status['environment']],
                ['Current Database', $status['current_database']],
                ['Safe for Testing', $status['is_safe_for_testing'] ? '✅ Yes' : '❌ No'],
                ['Status', $status['warning']],
                ['Checked At', $status['timestamp']],
            ]
        );

        $this->line('');

        // Show detailed information
        $this->info('📋 Safety Rules:');
        $this->line('1. Tests MUST NEVER delete or modify real production data.');
        $this->line('2. Tests MUST use a separate testing database (configured in phpunit.xml).');
        $this->line('3. Migrations run during tests MUST only affect the testing database.');
        $this->line('4. Database operations in tests MUST be isolated and cleaned up after each test.');
        $this->line('5. Real user data MUST be protected from accidental deletion or modification.');
        $this->line('');

        // Check phpunit.xml configuration
        $phpunitConfig = $this->getPhpunitDatabaseConfig();
        $this->info('⚙️ PHPUnit Configuration:');
        $this->line("Test Database: {$phpunitConfig['database']}");
        $this->line('Expected in phpunit.xml: DB_DATABASE=pest_test');
        $this->line('');

        // Additional warning for local environment using production database
        $currentDb = $status['current_database'];
        $productionDbs = ['schooltool', 'production_db', 'live_db'];

        if (in_array($currentDb, $productionDbs)) {
            $this->warn('⚠️  WARNING: You are currently using a production database.');
            $this->warn("   Database: {$currentDb}");
            $this->warn('   If you run tests without APP_ENV=testing, they WILL DELETE PRODUCTION DATA!');
            $this->line('');
        }

        // Final warning if unsafe for testing
        if (! $status['is_safe_for_testing']) {
            $this->error('🚨 CRITICAL WARNING:');
            $this->error('Tests are configured to run against a production database!');
            $this->error('Running tests will DELETE ALL PRODUCTION DATA!');
            $this->error('');
            $this->error('Immediate Actions Required:');
            $this->error('1. STOP all test execution');
            $this->error('2. Verify phpunit.xml has DB_DATABASE=pest_test');
            $this->error('3. Ensure the "pest_test" database exists');
            $this->error('4. Never run tests against "schooltool" database');
            $this->error('');
            $this->error('This safety check will now THROW AN EXCEPTION to prevent data loss.');

            // Throw exception to prevent continuation
            throw new \RuntimeException(
                'Database safety check failed: Tests would delete production data. '.
                    'Check configuration and ensure tests use "pest_test" database.'
            );
        }

        $this->info('✅ Database safety check passed. Tests are safe to run.');
    }

    /**
     * Get PHPUnit database configuration.
     */
    private function getPhpunitDatabaseConfig(): array
    {
        $phpunitPath = base_path('phpunit.xml');

        if (! file_exists($phpunitPath)) {
            return ['database' => 'phpunit.xml not found'];
        }

        $xml = simplexml_load_file($phpunitPath);
        $database = 'Not found';

        foreach ($xml->php->env as $env) {
            $attributes = $env->attributes();
            if ((string) $attributes['name'] === 'DB_DATABASE') {
                $database = (string) $attributes['value'];
                break;
            }
        }

        return ['database' => $database];
    }
}
