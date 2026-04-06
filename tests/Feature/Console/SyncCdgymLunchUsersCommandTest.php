<?php

use App\Models\School;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('the command rejects a missing school', function () {
    $this->artisan('restaurant:sync-cdgym-users', [
        '--school-id' => 999999,
    ])
        ->expectsOutput('Target school not found.')
        ->assertExitCode(1);
});

test('the command rejects conflicting dry run and live flags', function () {
    School::factory()->create(['id' => 1]);

    $this->artisan('restaurant:sync-cdgym-users', [
        '--school-id' => 1,
        '--dry-run' => true,
        '--live' => true,
    ])
        ->expectsOutput('Use either --dry-run or --live, not both.')
        ->assertExitCode(1);
});
