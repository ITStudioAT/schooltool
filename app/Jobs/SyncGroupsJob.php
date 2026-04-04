<?php

namespace App\Jobs;

use App\Http\Controllers\Admin\GroupController;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

class SyncGroupsJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $uniqueFor = 900;

    public function __construct(public int $schoolId, public int $actorUserId) {}

    public function uniqueId(): string
    {
        return 'school:'.$this->schoolId;
    }

    public function uniqueVia(): Repository
    {
        return Cache::store();
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('groups-sync:'.$this->schoolId))
                ->shared()
                ->dontRelease()
                ->expireAfter($this->uniqueFor),
        ];
    }

    public function handle(GroupController $groupController): void
    {
        Cache::forget(self::queuedCacheKey($this->schoolId));
        Cache::put(self::runningCacheKey($this->schoolId), true, now()->addSeconds($this->uniqueFor));

        try {
            $actorUser = User::query()
                ->where('school_id', $this->schoolId)
                ->where('id', $this->actorUserId)
                ->first();

            $groupController->runHeavySync($actorUser, $this->schoolId, $this->actorUserId);

            Cache::put(self::lastSyncedAtCacheKey($this->schoolId), now()->toIso8601String(), now()->addDay());
        } finally {
            Cache::forget(self::queuedCacheKey($this->schoolId));
            Cache::forget(self::runningCacheKey($this->schoolId));
        }
    }

    public static function queuedCacheKey(int $schoolId): string
    {
        return 'groups-sync:queued:'.$schoolId;
    }

    public static function runningCacheKey(int $schoolId): string
    {
        return 'groups-sync:running:'.$schoolId;
    }

    public static function lastSyncedAtCacheKey(int $schoolId): string
    {
        return 'groups-sync:last-synced-at:'.$schoolId;
    }
}
