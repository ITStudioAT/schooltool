<?php

namespace Tests\Feature;

use App\Providers\DatabaseSafetyServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseSafetyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that database safety system is active.
     */
    public function test_database_safety_system_is_active(): void
    {
        // This test should only run in testing environment with pest_test database
        $status = DatabaseSafetyServiceProvider::getSafetyStatus();

        $this->assertEquals('testing', $status['environment']);
        $this->assertEquals('pest_test', $status['current_database']);
        $this->assertTrue($status['is_safe_for_testing']);
    }

    /**
     * Test that production database is protected.
     */
    public function test_production_database_is_protected(): void
    {
        // This is a meta-test to ensure the safety system works
        // In real scenario, the safety system would throw exception
        // if tests tried to run against production database

        $this->assertTrue(
            DatabaseSafetyServiceProvider::isDatabaseSafeForTesting(),
            'Tests should only run against test database, not production'
        );
    }

    /**
     * Test that safety check command works.
     */
    public function test_safety_check_command_works(): void
    {
        // This test verifies the safety check command can be executed
        $this->artisan('db:safety-check')
            ->assertExitCode(0);
    }
}
