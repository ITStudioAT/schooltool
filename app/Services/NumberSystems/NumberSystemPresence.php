<?php

namespace App\Services\NumberSystems;

use App\Models\NumberSystemGameRun;
use App\Models\NumberSystemGameSession;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class NumberSystemPresence
{
    public function record(NumberSystemGameRun $run, User $student): bool
    {
        app(NumberSystemGameService::class)->authorizeRun($student, $run);
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
        return $run->members->map(function (User $member) use ($seen): array {
            $at = isset($seen[$member->id]) ? Carbon::parse($seen[$member->id]) : null;
            $age = $at ? max(0, now()->getTimestamp() - $at->getTimestamp()) : null;

            return ['name' => trim($member->first_name.' '.$member->last_name), 'last_seen_at' => $at?->toIso8601String(),
                'status' => $age === null ? 'unknown' : ($age <= 45 ? 'connected' : ($age <= 90 ? 'delayed' : 'disconnected'))];
        })->values()->all();
    }
}
