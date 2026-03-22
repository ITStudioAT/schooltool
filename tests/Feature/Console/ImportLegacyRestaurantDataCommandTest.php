<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('the command rejects a missing school', function () {
    $this->artisan('restaurant:import-legacy', [
        'school_id' => 999999,
        '--source-database' => 'cdgym_info',
    ])
        ->expectsOutput('Target school not found.')
        ->assertExitCode(1);
});
