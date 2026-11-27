<?php

namespace App\Support;

class TeachingCourseNotificationKey
{
    public static function behaviour(int $entryId): string
    {
        return hash('sha256', 'course-notification-'.$entryId);
    }

    public static function workResult(int $workId, string $changedAt): string
    {
        return hash('sha256', 'work-result-'.$workId.'|'.$changedAt);
    }
}
