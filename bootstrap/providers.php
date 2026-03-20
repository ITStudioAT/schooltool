<?php

use App\Providers\AppServiceProvider;
use App\Providers\BroadcastServiceProvider;
use Barryvdh\Debugbar\ServiceProvider;

return [
    AppServiceProvider::class,
    BroadcastServiceProvider::class,
    ServiceProvider::class,
];
