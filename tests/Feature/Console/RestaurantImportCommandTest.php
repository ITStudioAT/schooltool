<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('the restaurant import command rejects a missing school', function () {
    $this->artisan('app:restaurant-import', [
        '--school-id' => 999999,
        '--source-database' => 'cdgym_info',
    ])
        ->expectsOutput('Target school not found.')
        ->assertExitCode(1);
});
