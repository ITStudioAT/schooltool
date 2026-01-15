<?php

use App\Providers\BroadcastServiceProvider;
use Illuminate\Support\Facades\Broadcast;
use Tests\TestCase;

uses(TestCase::class);

test('broadcast service provider registers broadcast routes and channels', function () {
    Broadcast::shouldReceive('routes')->once();
    Broadcast::shouldReceive('channel')
        ->once()
        ->with('user.{id}', \Mockery::type('Closure'));

    $provider = new BroadcastServiceProvider(app());
    $provider->boot();
});
