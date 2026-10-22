<?php

namespace App\Services\NumberSystems;

use App\Models\NumberSystemGameGuest;
use App\Models\NumberSystemGameRun;
use App\Models\NumberSystemGameSession;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class NumberSystemPresence
{
    public function record(NumberSystemGameRun $run, User|NumberSystemGameGuest $student): bool
    {
        app(NumberSystemGameService::class)->authorizeRun($student, $run);
        if ($student instanceof NumberSystemGameGuest) {
            $student->update(['last_seen_at' => now()]);

            return true;
        }
        if (! Schema::hasColumn('number_system_game_members', 'last_seen_at')) {
            return false;
        }
        DB::table('number_system_game_members')->where('run_id', $run->id)->where('user_id', $student->id)->update(['last_seen_at' => now()]);

        return true;
    }

    /** @return array<int, ?string> */
    public function snapshot(NumberSystemGameSession $session): array
    {
        return Schema::hasColumn('number_system_game_members', 'last_seen_at')
            ? DB::table('number_system_game_members')->where('session_id', $session->id)->pluck('last_seen_at', 'user_id')->all() : [];
    }

    /** @param array<int, ?string> $seen */
    public function members(NumberSystemGameRun $run, array $seen): array
    {
        return $run->participants()->map(function (User|NumberSystemGameGuest $member) use ($seen): array {
            $at = $member instanceof NumberSystemGameGuest ? $member->last_seen_at : (isset($seen[$member->id]) ? Carbon::parse($seen[$member->id]) : null);
            $age = $at ? max(0, now()->getTimestamp() - $at->getTimestamp()) : null;

            return ['name' => trim($member->first_name.' '.$member->last_name), 'external' => $member instanceof NumberSystemGameGuest, 'last_seen_at' => $at?->toIso8601String(),
                'status' => $age === null ? 'unknown' : ($age <= 45 ? 'connected' : ($age <= 90 ? 'delayed' : 'disconnected'))];
        })->values()->all();
    }
}
