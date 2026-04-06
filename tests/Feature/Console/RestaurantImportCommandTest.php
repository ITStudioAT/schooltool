<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('the restaurant import command rejects a missing school', function () {
    $this->artisan('restaurant:import-legacy', [
        '--school-id' => 999999,
    ])
        ->expectsOutput('Target school not found.')
        ->assertExitCode(1);
});
