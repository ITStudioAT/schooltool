<?php

namespace App\Services\StudentsTimetables;

use App\Models\StudentTimetableV3Timetable;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Session\SessionManager;
use Illuminate\Support\Carbon;
use LogicException;

class StudentTimetableV3SessionScope
{
    public function __construct(private SessionManager $sessions) {}

    public function currentHash(): string
    {
        return $this->hash($this->sessions->driver()->getId());
    }

    public function hash(string $sessionId): string
    {
        if ($sessionId === '') {
            throw new LogicException('A started session is required for V3 timetable persistence.');
        }

        $applicationKey = (string) config('app.key');

        if ($applicationKey === '') {
            throw new LogicException('The application key is required for V3 timetable session scoping.');
        }

        return hash_hmac('sha256', $sessionId, $applicationKey);
    }

    public function expiresAt(): Carbon
    {
        return now()->addMinutes(max(1, (int) config('session.lifetime', 120)));
    }

    public function deleteCurrentForUser(User $user): int
    {
        return $this->queryForUser($user)
            ->where('session_id_hash', $this->currentHash())
            ->delete();
    }

    public function deleteExpiredForUser(User $user): int
    {
        return $this->queryForUser($user)
            ->where(function (Builder $query): void {
                $query
                    ->whereNull('session_id_hash')
                    ->orWhereNull('expires_at')
                    ->orWhere('expires_at', '<=', now());
            })
            ->delete();
    }

    private function queryForUser(User $user): Builder
    {
        return StudentTimetableV3Timetable::query()
            ->where('school_id', $user->school_id)
            ->where('user_id', $user->id);
    }
}
