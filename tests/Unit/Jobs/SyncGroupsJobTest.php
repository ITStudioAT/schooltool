<?php

use App\Jobs\SyncGroupsJob;
use App\Services\Groups\GroupSynchronizationService;

test('group sync job depends on the domain service instead of an HTTP controller', function () {
    $handle = new ReflectionMethod(SyncGroupsJob::class, 'handle');
    $parameters = $handle->getParameters();

    expect($parameters)->toHaveCount(1)
        ->and($parameters[0]->getType()?->getName())->toBe(GroupSynchronizationService::class);
});
