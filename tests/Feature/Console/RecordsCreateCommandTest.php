<?php

namespace Tests\Feature\Console;

use App\Services\RecordsCreateService;
use Mockery;

it('initializes records via service', function () {
    $service = Mockery::mock(RecordsCreateService::class);
    $service->shouldReceive('initRecords')->once();

    app()->instance(RecordsCreateService::class, $service);

    $this->artisan('records:create')->assertExitCode(0);
});
