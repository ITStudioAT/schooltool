<?php

namespace App\Support;

use Illuminate\Support\Str;

class TeachingWorkActionHistory
{
    public const KEY = 'action_history';

    public static function valid(mixed $history): bool
    {
        if (! is_array($history) || ! array_is_list($history)) {
            return false;
        }

        $ids = [];
        foreach ($history as $action) {
            if (! is_array($action) || count($action) !== 6
                || ! is_string($action['id'] ?? null) || ! Str::isUuid($action['id']) || isset($ids[$action['id']])
                || ! is_string($action['at'] ?? null) || ! preg_match('/\A\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{6}Z\z/', $action['at'])
                || ! is_string($action['action'] ?? null) || ! preg_match('/\A[a-z_]{2,24}\.[a-z_]{2,40}\z/', $action['action'])
                || ! in_array($action['actor'] ?? null, ['staff', 'student_portal'], true)
                || ! in_array($action['source'] ?? null, ['local', 'online'], true)
                || ! in_array($action['result'] ?? null, ['completed', 'already_completed', 'queued', 'failed', 'blocked', 'open'], true)) {
                return false;
            }
            $ids[$action['id']] = true;
        }

        return true;
    }
}
