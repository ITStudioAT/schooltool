<?php

namespace App\Listeners;

use App\Models\User;
use App\Services\StudentsTimetables\StudentTimetableV3SessionScope;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Events\Dispatcher;

class StudentTimetableV3SessionSubscriber
{
    public function __construct(private StudentTimetableV3SessionScope $sessionScope) {}

    public function subscribe(Dispatcher $events): void
    {
        $events->listen(Login::class, [$this, 'deleteExpiredTimetables']);
        $events->listen(Logout::class, [$this, 'deleteCurrentSessionTimetables']);
    }

    public function deleteExpiredTimetables(Login $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        $this->sessionScope->deleteExpiredForUser($event->user);
    }

    public function deleteCurrentSessionTimetables(Logout $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        $this->sessionScope->deleteCurrentForUser($event->user);
    }
}
