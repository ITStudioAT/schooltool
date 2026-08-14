<?php

use App\Models\School;
use App\Models\Schoolyear;
use App\Models\StudentTimetableV3Timetable;
use App\Models\User;
use App\Services\StudentsTimetables\StudentTimetableV3SessionScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;

uses(RefreshDatabase::class);

it('deletes only the current login session timetables on logout', function () {
    [$user, $schoolyear] = timetableSessionUser();
    $sessionScope = app(StudentTimetableV3SessionScope::class);
    $currentSessionId = str_repeat('c', 40);
    $otherSessionId = str_repeat('d', 40);

    session()->setId($currentSessionId);
    Auth::guard('web')->login($user);

    $currentTimetable = timetableForSession(
        $user,
        $schoolyear,
        $sessionScope->currentHash(),
        now()->addHour(),
    );
    $otherDeviceTimetable = timetableForSession(
        $user,
        $schoolyear,
        $sessionScope->hash($otherSessionId),
        now()->addHour(),
    );

    Auth::guard('web')->logout();

    expect($currentTimetable->fresh())->toBeNull()
        ->and($otherDeviceTimetable->fresh())->not->toBeNull()
        ->and(StudentTimetableV3Timetable::query()->count())->toBe(1);
});

it('removes only expired and legacy timetable data for the user on login', function () {
    [$user, $schoolyear] = timetableSessionUser();
    [$otherUser, $otherSchoolyear] = timetableSessionUser();
    $sessionScope = app(StudentTimetableV3SessionScope::class);

    $activeTimetable = timetableForSession(
        $user,
        $schoolyear,
        $sessionScope->hash(str_repeat('e', 40)),
        now()->addHour(),
    );
    $expiredTimetable = timetableForSession(
        $user,
        $schoolyear,
        $sessionScope->hash(str_repeat('f', 40)),
        now()->subMinute(),
    );
    $legacyTimetable = timetableForSession($user, $schoolyear, null, null);
    $otherUserExpiredTimetable = timetableForSession(
        $otherUser,
        $otherSchoolyear,
        $sessionScope->hash(str_repeat('g', 40)),
        now()->subMinute(),
    );

    session()->setId(str_repeat('h', 40));
    Auth::guard('web')->login($user);

    expect($activeTimetable->fresh())->not->toBeNull()
        ->and($expiredTimetable->fresh())->toBeNull()
        ->and($legacyTimetable->fresh())->toBeNull()
        ->and($otherUserExpiredTimetable->fresh())->not->toBeNull()
        ->and(StudentTimetableV3Timetable::query()->count())->toBe(2);
});

it('marks expired and legacy timetable data as prunable without selecting active sessions', function () {
    [$user, $schoolyear] = timetableSessionUser();
    $sessionScope = app(StudentTimetableV3SessionScope::class);

    $activeTimetable = timetableForSession(
        $user,
        $schoolyear,
        $sessionScope->hash(str_repeat('i', 40)),
        now()->addHour(),
    );
    $expiredTimetable = timetableForSession(
        $user,
        $schoolyear,
        $sessionScope->hash(str_repeat('j', 40)),
        now()->subMinute(),
    );
    $legacyTimetable = timetableForSession($user, $schoolyear, null, null);

    $prunableIds = (new StudentTimetableV3Timetable)->prunable()->pluck('id');

    expect($prunableIds)
        ->toContain($expiredTimetable->id, $legacyTimetable->id)
        ->not->toContain($activeTimetable->id);
});

/** @return array{User, Schoolyear} */
function timetableSessionUser(): array
{
    $school = School::factory()->create();
    $schoolyear = Schoolyear::factory()->create(['school_id' => $school->id]);
    $user = User::factory()->create([
        'school_id' => $school->id,
        'schoolyear_id' => $schoolyear->id,
    ]);

    return [$user, $schoolyear];
}

function timetableForSession(
    User $user,
    Schoolyear $schoolyear,
    ?string $sessionIdHash,
    DateTimeInterface|string|null $expiresAt,
): StudentTimetableV3Timetable {
    return StudentTimetableV3Timetable::query()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'user_id' => $user->id,
        'session_id_hash' => $sessionIdHash,
        'context_key' => 'workspace:'.hash('sha256', fake()->uuid()),
        'planning_mode' => 'without_student',
        'student_code' => null,
        'fingerprint' => str_repeat('a', 64),
        'modules' => [],
        'parameters' => ['planning_mode' => 'without_student'],
        'summary' => ['algorithm_version' => 6, 'timetable_count' => 0],
        'timetables' => [
            'storage_version' => 1,
            'lessons' => [],
            'timetables' => [],
        ],
        'generated_at' => now(),
        'expires_at' => $expiresAt,
    ]);
}
