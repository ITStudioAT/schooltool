<?php

use App\Models\School;
use App\Models\Schoolyear;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('backfills legacy teaching settings into the active schoolyear buckets', function () {
    $school = School::factory()->create();
    $schoolyear = Schoolyear::factory()->create([
        'school_id' => $school->id,
    ]);

    $user = User::factory()->create([
        'school_id' => $school->id,
        'schoolyear_id' => $schoolyear->id,
        'teaching_behaviour' => [
            ['short_name' => 'D', 'name' => 'Disziplinarbogen'],
        ],
        'teaching_notifications' => [
            ['short_name' => 'KB', 'name' => 'Klassenbucheintrag'],
        ],
        'teaching_behaviour_by_schoolyear' => null,
        'teaching_notifications_by_schoolyear' => null,
    ]);

    $this->artisan('teaching:backfill-schoolyear-settings')
        ->expectsOutputToContain('Processed 1 user(s).')
        ->expectsOutputToContain('Updated 1 user(s).')
        ->expectsOutputToContain('Backfilled behaviour settings for 1 user(s).')
        ->expectsOutputToContain('Backfilled notification settings for 1 user(s).')
        ->assertExitCode(0);

    $user->refresh();

    expect($user->teaching_behaviour_by_schoolyear[(string) $schoolyear->id][0] ?? null)
        ->toMatchArray(['short_name' => 'D', 'name' => 'Disziplinarbogen'])
        ->and($user->teaching_notifications_by_schoolyear[(string) $schoolyear->id][0] ?? null)
        ->toMatchArray(['short_name' => 'KB', 'name' => 'Klassenbucheintrag']);
});

it('does not overwrite existing schoolyear scoped teaching settings', function () {
    $school = School::factory()->create();
    $schoolyear = Schoolyear::factory()->create([
        'school_id' => $school->id,
    ]);

    $user = User::factory()->create([
        'school_id' => $school->id,
        'schoolyear_id' => $schoolyear->id,
        'teaching_behaviour' => [
            ['short_name' => 'ALT', 'name' => 'Alt Verhalten'],
        ],
        'teaching_notifications' => [
            ['short_name' => 'ALTN', 'name' => 'Alt Verständigung'],
        ],
        'teaching_behaviour_by_schoolyear' => [
            (string) $schoolyear->id => [
                ['short_name' => 'AKT', 'name' => 'Aktives Verhalten'],
            ],
        ],
        'teaching_notifications_by_schoolyear' => [
            (string) $schoolyear->id => [
                ['short_name' => 'AKTN', 'name' => 'Aktive Verständigung'],
            ],
        ],
    ]);

    $this->artisan('teaching:backfill-schoolyear-settings')
        ->expectsOutputToContain('Updated 0 user(s).')
        ->expectsOutputToContain('Backfilled behaviour settings for 0 user(s).')
        ->expectsOutputToContain('Backfilled notification settings for 0 user(s).')
        ->assertExitCode(0);

    $user->refresh();

    expect($user->teaching_behaviour_by_schoolyear[(string) $schoolyear->id][0] ?? null)
        ->toMatchArray(['short_name' => 'AKT', 'name' => 'Aktives Verhalten'])
        ->and($user->teaching_notifications_by_schoolyear[(string) $schoolyear->id][0] ?? null)
        ->toMatchArray(['short_name' => 'AKTN', 'name' => 'Aktive Verständigung']);
});
