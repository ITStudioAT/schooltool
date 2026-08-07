<?php

use Laravel\Horizon\Contracts\MasterSupervisorRepository;
use Laravel\Horizon\Contracts\SupervisorRepository;

it('reports a running Horizon supervisor', function (): void {
    $repository = Mockery::mock(MasterSupervisorRepository::class);
    $repository->shouldReceive('all')->once()->andReturn([
        (object) ['status' => 'running'],
    ]);
    app()->instance(MasterSupervisorRepository::class, $repository);

    $supervisors = Mockery::mock(SupervisorRepository::class);
    $supervisors->shouldReceive('all')->once()->andReturn([
        (object) [
            'status' => 'running',
            'options' => [
                'queue' => 'critical,notifications,default,imports,materials,maintenance',
            ],
        ],
    ]);
    app()->instance(SupervisorRepository::class, $supervisors);

    $this->artisan('queue:health-check')
        ->expectsOutputToContain('Horizon laeuft.')
        ->assertExitCode(0);
});

it('reports queues missing from running Horizon supervisors', function (): void {
    $repository = Mockery::mock(MasterSupervisorRepository::class);
    $repository->shouldReceive('all')->once()->andReturn([
        (object) ['status' => 'running'],
    ]);
    app()->instance(MasterSupervisorRepository::class, $repository);

    $supervisors = Mockery::mock(SupervisorRepository::class);
    $supervisors->shouldReceive('all')->once()->andReturn([
        (object) [
            'status' => 'running',
            'options' => ['queue' => 'critical,notifications'],
        ],
    ]);
    app()->instance(SupervisorRepository::class, $supervisors);

    $this->artisan('queue:health-check')
        ->expectsOutputToContain('Horizon bedient nicht alle erwarteten Queues.')
        ->expectsOutputToContain('Fehlende Queues: default, imports, materials, maintenance')
        ->assertExitCode(3);
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

it('requires a replacement Horizon master when an old process is excluded', function (): void {
    $repository = Mockery::mock(MasterSupervisorRepository::class);
    $repository->shouldReceive('all')->once()->andReturn([
        (object) [
            'name' => 'schooltool-old-master',
            'pid' => '123',
            'status' => 'running',
            'supervisors' => ['schooltool-old-supervisor'],
        ],
    ]);
    app()->instance(MasterSupervisorRepository::class, $repository);

    $supervisors = Mockery::mock(SupervisorRepository::class);
    $supervisors->shouldReceive('all')->never();
    app()->instance(SupervisorRepository::class, $supervisors);

    $this->artisan('queue:health-check --exclude-master-pid=123')
        ->expectsOutputToContain('Horizon ist inaktiv.')
        ->assertExitCode(2);
});

it('uses only supervisors owned by a replacement Horizon master', function (): void {
    $repository = Mockery::mock(MasterSupervisorRepository::class);
    $repository->shouldReceive('all')->once()->andReturn([
        (object) [
            'name' => 'schooltool-old-master',
            'pid' => '123',
            'status' => 'running',
            'supervisors' => ['schooltool-old-supervisor'],
        ],
        (object) [
            'name' => 'schooltool-new-master',
            'pid' => '456',
            'status' => 'running',
            'supervisors' => ['schooltool-new-supervisor'],
        ],
    ]);
    app()->instance(MasterSupervisorRepository::class, $repository);

    $supervisors = Mockery::mock(SupervisorRepository::class);
    $supervisors->shouldReceive('all')->once()->andReturn([
        (object) [
            'name' => 'schooltool-old-supervisor',
            'status' => 'running',
            'options' => [
                'queue' => 'critical,notifications,default,imports,materials,maintenance',
            ],
        ],
        (object) [
            'name' => 'schooltool-new-supervisor',
            'status' => 'running',
            'options' => ['queue' => 'critical,notifications'],
        ],
    ]);
    app()->instance(SupervisorRepository::class, $supervisors);

    $this->artisan('queue:health-check --exclude-master-pid=123')
        ->expectsOutputToContain('Horizon bedient nicht alle erwarteten Queues.')
        ->expectsOutputToContain('Fehlende Queues: default, imports, materials, maintenance')
        ->assertExitCode(3);
});

it('accepts a replacement Horizon master serving every configured queue', function (): void {
    $repository = Mockery::mock(MasterSupervisorRepository::class);
    $repository->shouldReceive('all')->once()->andReturn([
        (object) [
            'name' => 'schooltool-old-master',
            'pid' => '123',
            'status' => 'running',
            'supervisors' => ['schooltool-old-supervisor'],
        ],
        (object) [
            'name' => 'schooltool-new-master',
            'pid' => '456',
            'status' => 'running',
            'supervisors' => ['schooltool-new-supervisor'],
        ],
    ]);
    app()->instance(MasterSupervisorRepository::class, $repository);

    $supervisors = Mockery::mock(SupervisorRepository::class);
    $supervisors->shouldReceive('all')->once()->andReturn([
        (object) [
            'name' => 'schooltool-old-supervisor',
            'status' => 'running',
            'options' => ['queue' => 'critical'],
        ],
        (object) [
            'name' => 'schooltool-new-supervisor',
            'status' => 'running',
            'options' => [
                'queue' => 'critical,notifications,default,imports,materials,maintenance',
            ],
        ],
    ]);
    app()->instance(SupervisorRepository::class, $supervisors);

    $this->artisan('queue:health-check --exclude-master-pid=123')
        ->expectsOutputToContain('Horizon laeuft.')
        ->assertSuccessful();
});
