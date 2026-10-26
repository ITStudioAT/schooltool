<?php

namespace App\Services;

use App\Models\TeachingCourseWork;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class TeachingWorkRefreshProgress
{
    public function key(User $actor, TeachingCourseWork $work, string $id): string
    {
        return 'teaching-work-refresh-progress:'.hash('sha256', $actor->id.':'.$work->id.':'.$id);
    }

    /** @param array<string, mixed> $progress */
    public function save(?string $key, array $progress): void
    {
        if ($key !== null) {
            Cache::store('file')->put($key, $progress, now()->addMinutes(5));
        }
    }

    /** @return array<string, mixed>|null */
    public function read(string $key): ?array
    {
        $progress = Cache::store('file')->get($key);

        return is_array($progress) ? $progress : null;
    }
}
