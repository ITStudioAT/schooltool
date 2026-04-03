<?php

namespace Tests\Feature\Console;

use App\Models\School;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('fails when model option is missing', function () {
    $this->artisan('testrecords:create')->assertExitCode(1);
});

it('fails when model class does not exist', function () {
    $this->artisan('testrecords:create --model=MissingModel')
        ->expectsOutputToContain('does not exist')
        ->assertExitCode(1);
});

it('creates records for a model with factory', function () {
    $this->artisan('testrecords:create --model=School --count=2')->assertExitCode(0);

    expect(School::count())->toBe(2);
});
