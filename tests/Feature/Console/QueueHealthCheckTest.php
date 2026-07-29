<?php

use Laravel\Horizon\Contracts\MasterSupervisorRepository;

it('reports a running Horizon supervisor', function (): void {
    $repository = Mockery::mock(MasterSupervisorRepository::class);
    $repository->shouldReceive('all')->once()->andReturn([
        (object) ['status' => 'running'],
    ]);
    app()->instance(MasterSupervisorRepository::class, $repository);

    $this->artisan('queue:health-check')
        ->expectsOutputToContain('Horizon laeuft.')
        ->assertExitCode(0);
});

it('reports a paused Horizon supervisor', function (): void {
    $repository = Mockery::mock(MasterSupervisorRepository::class);
    $repository->shouldReceive('all')->once()->andReturn([
        (object) ['status' => 'paused'],
    ]);
    app()->instance(MasterSupervisorRepository::class, $repository);

    $this->artisan('queue:health-check')
        ->expectsOutputToContain('Horizon ist pausiert.')
        ->assertExitCode(1);
});

it('reports Horizon as inactive without starting or killing processes', function (): void {
    $repository = Mockery::mock(MasterSupervisorRepository::class);
    $repository->shouldReceive('all')->once()->andReturn([]);
    app()->instance(MasterSupervisorRepository::class, $repository);

    $this->artisan('queue:health-check')
        ->expectsOutputToContain('Horizon ist inaktiv.')
        ->expectsOutputToContain('Erwartete Queues: critical, notifications, default, imports, materials, maintenance')
        ->expectsOutputToContain('Produktionsprozess: php artisan horizon')
        ->assertExitCode(2);

    $source = file_get_contents(app_path('Console/Commands/QueueHealthCheck.php'));

    expect($source)
        ->not->toContain('exec(')
        ->not->toContain('popen(')
        ->not->toContain('pkill')
        ->not->toContain('queue:retry');
});
