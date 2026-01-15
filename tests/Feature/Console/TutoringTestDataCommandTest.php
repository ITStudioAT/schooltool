<?php

namespace Tests\Feature\Console;

use App\Console\Commands\TutoringTestDataCommand;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(RefreshDatabase::class);

it('fails for invalid tutoring test-data action', function () {
    $this->artisan('tutoring:test-data invalid-action')
        ->expectsOutputToContain('Verwendung:')
        ->assertExitCode(1);
});

it('reports missing schools when creating test user without seed data', function () {
    $this->artisan('tutoring:test-data create-test-user')
        ->expectsOutputToContain('ABG-SB')
        ->assertExitCode(0);
});
