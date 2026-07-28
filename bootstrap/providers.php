<?php

use App\Providers\AppServiceProvider;
use App\Providers\BroadcastServiceProvider;
use App\Providers\DatabaseSafetyServiceProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\HorizonServiceProvider;

return [
    AppServiceProvider::class,
    BroadcastServiceProvider::class,
    DatabaseSafetyServiceProvider::class,
    FortifyServiceProvider::class,
    HorizonServiceProvider::class,
];
