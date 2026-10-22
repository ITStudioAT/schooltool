<?php

use App\Jobs\HealthJob;
use App\Models\QueueTest;
use App\Models\StudentTimetableV3Timetable;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

if (config('schooltool.preview.instance', false)) {
    return;
}

/*
Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
*/

Schedule::command('health:scheduler-heartbeat')
    ->everyMinute()
    ->onOneServer()
    ->withoutOverlapping();

Schedule::job(new HealthJob)
    ->everyMinute()
    ->onOneServer()
    ->withoutOverlapping();

Schedule::command('horizon:snapshot')
    ->everyFiveMinutes()
    ->when(fn (): bool => ! app()->environment('local')
        || config('queue.connections.'.config('queue.default').'.driver') === 'redis')
    ->onOneServer()
    ->withoutOverlapping();

Schedule::command('uploads:cleanup-expired')
    ->hourlyAt(17)
    ->onOneServer()
    ->withoutOverlapping();

Schedule::command('private:prune-orphan-school-folders')
    ->dailyAt('03:00')
    ->onOneServer()
    ->withoutOverlapping();

Schedule::command('model:prune', [
    '--model' => [QueueTest::class, StudentTimetableV3Timetable::class],
])
    ->dailyAt('03:10')
    ->onOneServer()
    ->withoutOverlapping();

Schedule::command('teaching:backup-maintenance')
    ->everyFiveMinutes()
    ->onOneServer()
    ->withoutOverlapping(10);

Schedule::command('teaching:send-reminders')
    ->everyMinute()
    ->onOneServer()
    ->withoutOverlapping();

Schedule::command('multiple-choice:finalize-expired')
    ->everyMinute()
    ->onOneServer()
    ->withoutOverlapping();
