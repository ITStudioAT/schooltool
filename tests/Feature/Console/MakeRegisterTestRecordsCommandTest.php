<?php

namespace Tests\Feature\Console;

use App\Console\Commands\MakeRegisterTestRecordsCommand;
use App\Services\RegisterTestRecordsService;
use Mockery;
use Tests\TestCase;

it('fails when register requirements are not met', function () {
    $service = Mockery::mock(RegisterTestRecordsService::class);
    $service->shouldReceive('checkRequirement')->once()->andReturn(false);
    $service->shouldReceive('checkOrCreateUsers')->never();
    $service->shouldReceive('createRegisterEntries')->never();

    app()->instance(RegisterTestRecordsService::class, $service);

    $this->artisan('make-test:register')->assertExitCode(1);
});

it('creates users and bookings when requirements are met', function () {
    $service = Mockery::mock(RegisterTestRecordsService::class);
    $service->shouldReceive('checkRequirement')->once()->andReturn(true);
    $service->shouldReceive('checkOrCreateUsers')->once()->andReturn(true);
    $service->shouldReceive('createRegisterEntries')->once()->andReturn(true);

    app()->instance(RegisterTestRecordsService::class, $service);

    $this->artisan('make-test:register')->assertExitCode(0);
});
