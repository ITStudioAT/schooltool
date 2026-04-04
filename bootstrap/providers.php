<?php

use App\Providers\AppServiceProvider;
use App\Providers\BroadcastServiceProvider;
use App\Providers\DatabaseSafetyServiceProvider;
use Barryvdh\Debugbar\ServiceProvider;

return [
    AppServiceProvider::class,
    BroadcastServiceProvider::class,
    DatabaseSafetyServiceProvider::class,
    ServiceProvider::class,
];
