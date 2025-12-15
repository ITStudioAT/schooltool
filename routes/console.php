<?php

use App\Jobs\HealthJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;



Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::job(new HealthJob)
    ->onQueue('default')
    ->everyMinute()
    ->withoutOverlapping();
