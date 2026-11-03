<?php

use App\Models\School;
use App\Models\Schoolyear;
use App\Models\TeachingPersonalAppointment;
use App\Models\TeachingSchoolHour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

test('persists only the selected occurrence hour text and can restore the series text', function () {
    $appointment = TeachingPersonalAppointment::factory()->create([
        'user_id' => $this->teacher->id, 'school_id' => $this->school->id, 'schoolyear_id' => $this->year->id,
        'date' => '2026-10-12', 'repeat_until' => '2026-11-02', 'title' => 'Serie', 'school_hours' => [1, 2],
    ]);
    $other = TeachingPersonalAppointment::factory()->create([
        'user_id' => $this->teacher->id, 'school_id' => $this->school->id, 'schoolyear_id' => $this->year->id,
        'date' => '2026-10-19', 'school_hours' => [1],
    ]);
    $original = $appointment->fresh()->getAttributes();
    $this->actingAs($this->teacher, 'sanctum')->putJson("{$this->baseUrl}/{$appointment->id}/occurrence", [
        'date' => '2026-10-19', 'hour' => 1, 'title' => 'Einzeltext', 'kind' => 'consultation', 'school_hours' => [3],
    ])->assertOk()->assertJsonPath('data.title_exceptions.2026-10-19:1', 'Einzeltext');
    $saved = $appointment->fresh()->getAttributes();
    foreach ($original as $key => $value) {
        if (! in_array($key, ['updated_at', 'title_exceptions'], true)) {
            expect($saved[$key])->toBe($value);
        }
    }
    expect($other->fresh()->title_exceptions)->toBeNull();
    $this->getJson($this->baseUrl)->assertOk()->assertJsonPath('data.0.title_exceptions.2026-10-19:1', 'Einzeltext');
    $this->putJson("{$this->baseUrl}/{$appointment->id}/occurrence", ['date' => '2026-10-19', 'hour' => 2, 'title' => 'Zweite Stunde'])->assertOk();
    $this->putJson("{$this->baseUrl}/{$appointment->id}/occurrence", ['date' => '2026-10-19', 'hour' => 1, 'title' => null, 'reset' => true])
        ->assertOk()->assertJsonMissingPath('data.title_exceptions.2026-10-19:1')->assertJsonPath('data.title_exceptions.2026-10-19:2', 'Zweite Stunde');
});

test('rejects occurrence dates and hours outside the saved appointment', function (array $changes, string $field) {
    $appointment = TeachingPersonalAppointment::factory()->create([
        'user_id' => $this->teacher->id, 'school_id' => $this->school->id, 'schoolyear_id' => $this->year->id,
        'date' => '2026-10-12', 'repeat_until' => '2026-11-02', 'school_hours' => [1, 2],
    ]);
    $this->actingAs($this->teacher, 'sanctum')->putJson("{$this->baseUrl}/{$appointment->id}/occurrence", [
        'date' => '2026-10-19', 'hour' => 1, 'title' => 'Text', ...$changes,
    ])->assertUnprocessable()->assertJsonValidationErrors($field);
    expect($appointment->fresh()->title_exceptions)->toBeNull();
})->with([
    [['date' => '2026-10-11'], 'date'], [['date' => '2026-10-20'], 'date'], [['date' => '2026-11-09'], 'date'],
    [['date' => '2026-02-30'], 'date'], [['hour' => 3], 'hour'], [['hour' => null], 'hour'], [['title' => str_repeat('x', 121)], 'title'],
]);

test('refuses occurrence edits across owners schools and years', function (string $scope) {
    $attributes = ['user_id' => $this->teacher->id, 'school_id' => $this->school->id, 'schoolyear_id' => $this->year->id];
    $attributes[$scope] = match ($scope) {
        'user_id' => User::factory()->create()->id,
        'school_id' => School::factory()->create()->id,
        'schoolyear_id' => Schoolyear::factory()->create()->id,
    };
    $appointment = TeachingPersonalAppointment::factory()->create($attributes);
    $this->actingAs($this->teacher, 'sanctum')->putJson("{$this->baseUrl}/{$appointment->id}/occurrence", ['date' => $appointment->date, 'title' => 'Text'])
        ->assertForbidden();
    expect($appointment->fresh()->title_exceptions)->toBeNull();
})->with(['user_id', 'school_id', 'schoolyear_id']);

test('keeps a date exception after editing the series and validates free time occurrences', function () {
    $this->actingAs($this->teacher, 'sanctum');
    $id = $this->postJson($this->baseUrl, [...$this->payload, 'weekly' => true, 'repeat_until' => '2026-11-02'])->assertCreated()->json('data.id');
    $this->putJson("{$this->baseUrl}/{$id}/occurrence", ['date' => '2026-10-26', 'title' => 'Ausnahme'])->assertOk();
    $this->putJson("{$this->baseUrl}/{$id}", [...$this->payload, 'title' => 'Neue Serie', 'weekly' => true, 'repeat_until' => '2026-11-02'])
        ->assertOk()->assertJsonPath('data.title', 'Neue Serie')->assertJsonPath('data.title_exceptions.2026-10-26:all', 'Ausnahme');
    $this->putJson("{$this->baseUrl}/{$id}/occurrence", ['date' => '2026-10-26', 'hour' => 1, 'title' => 'Text'])->assertUnprocessable()->assertJsonValidationErrors('hour');
    $this->putJson("{$this->baseUrl}/{$id}/occurrence", ['date' => '2026-10-26', 'title' => null, 'reset' => true])->assertOk()->assertJsonMissingPath('data.title_exceptions.2026-10-26:all');
});

beforeEach(function () {
    Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);
    $this->school = School::factory()->create();
    $this->year = Schoolyear::factory()->create(['school_id' => $this->school->id, 'from' => '2026-09-01', 'until' => '2027-08-31']);
    enableSchoolToolModuleForTests($this->school, 'teaching');
    grantSchoolToolLicenceForTests($this->school, 'Lehrertool');
    $this->teacher = User::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->year->id]);
    $this->teacher->assignRole('teacher');
    $this->payload = ['kind' => 'lunch_supervision', 'title' => 'Mittagspause', 'date' => '2026-10-12', 'starts_at' => '12:30', 'ends_at' => '13:20', 'weekly' => false];
    $this->baseUrl = '/api/admin/teaching/personal_appointments';
});

test('stores an own single appointment without requiring repetition and ignores supplied ownership', function () {
    $id = $this->actingAs($this->teacher, 'sanctum')->postJson($this->baseUrl, [
        ...$this->payload, 'user_id' => 9999, 'school_id' => 9999, 'schoolyear_id' => 9999,
    ])->assertCreated()->assertJsonPath('data.repeat_until', null)->assertJsonPath('data.starts_at', '12:30')->json('data.id');
    $this->assertDatabaseHas('teaching_personal_appointments', ['id' => $id, 'user_id' => $this->teacher->id, 'school_id' => $this->school->id, 'schoolyear_id' => $this->year->id]);
    $this->getJson($this->baseUrl)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.title', 'Mittagspause');
});

test('stores a bounded weekly series once and can turn it into a single appointment', function () {
    $this->actingAs($this->teacher, 'sanctum');
    $id = $this->postJson($this->baseUrl, [...$this->payload, 'weekly' => true, 'repeat_until' => '2027-01-18'])
        ->assertCreated()->assertJsonPath('data.repeat_until', '2027-01-18')->json('data.id');
    $this->assertDatabaseCount('teaching_personal_appointments', 1);
    $this->putJson("{$this->baseUrl}/{$id}", [...$this->payload, 'kind' => 'day_care_standby', 'repeat_until' => '2027-01-18'])
        ->assertOk()->assertJsonPath('data.repeat_until', null)->assertJsonPath('data.kind', 'day_care_standby');
    $this->deleteJson("{$this->baseUrl}/{$id}")->assertNoContent();
    $this->assertDatabaseCount('teaching_personal_appointments', 0);
});

test('rejects invalid times and unbounded or out of year recurrence', function (array $changes, string $field) {
    $this->actingAs($this->teacher, 'sanctum')->postJson($this->baseUrl, [...$this->payload, ...$changes])
        ->assertUnprocessable()->assertJsonValidationErrors($field);
    $this->assertDatabaseCount('teaching_personal_appointments', 0);
})->with([
    [['ends_at' => '12:30'], 'ends_at'],
    [['ends_at' => '10:30'], 'ends_at'],
    [['starts_at' => '25:00'], 'starts_at'],
    [['weekly' => true], 'repeat_until'],
    [['weekly' => true, 'repeat_until' => '2026-10-11'], 'repeat_until'],
    [['weekly' => true, 'repeat_until' => '2027-09-01'], 'repeat_until'],
    [['date' => '2026-08-31'], 'date'],
    [['date' => '2027-09-01'], 'date'],
    [['kind' => 'unknown'], 'kind'],
    [['kind' => 'substitution'], 'kind'],
    [['kind' => 'supervision'], 'kind'],
    [['kind' => 'daily_standby'], 'kind'],
]);

test('persists each confirmed appointment kind', function (string $kind) {
    $this->actingAs($this->teacher, 'sanctum')->postJson($this->baseUrl, [...$this->payload, 'kind' => $kind])
        ->assertCreated()->assertJsonPath('data.kind', $kind);
    $this->assertDatabaseHas('teaching_personal_appointments', ['kind' => $kind, 'user_id' => $this->teacher->id]);
})->with(['supplier_standby', 'consultation', 'standby', 'lunch_supervision', 'day_care_standby', 'special_assignment', 'break_supervision']);

test('excludes other owners schools and years and rejects modification of their records', function (string $scope) {
    $attributes = ['user_id' => $this->teacher->id, 'school_id' => $this->school->id, 'schoolyear_id' => $this->year->id];
    $attributes[$scope] = match ($scope) {
        'user_id' => User::factory()->create()->id,
        'school_id' => School::factory()->create()->id,
        'schoolyear_id' => Schoolyear::factory()->create()->id,
    };
    $foreign = TeachingPersonalAppointment::factory()->create($attributes);
    $this->actingAs($this->teacher, 'sanctum')->getJson($this->baseUrl)->assertOk()->assertJsonCount(0, 'data');
    $this->putJson("{$this->baseUrl}/{$foreign->id}", $this->payload)->assertForbidden();
    $this->deleteJson("{$this->baseUrl}/{$foreign->id}")->assertForbidden();
    expect($foreign->fresh()->title)->toBeNull();
})->with(['user_id', 'school_id', 'schoolyear_id']);

test('requires authentication and a teaching role', function () {
    $this->getJson($this->baseUrl)->assertUnauthorized();
    $user = User::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->year->id]);
    $user->assignRole('user');
    $this->actingAs($user, 'sanctum')->getJson($this->baseUrl)->assertForbidden();
    $this->postJson($this->baseUrl, $this->payload)->assertForbidden();
});

test('stores exactly the selected school hours using this school and year times', function (array $hours) {
    foreach ([1 => ['07:45', '08:35'], 2 => ['08:40', '09:30'], 3 => ['10:00', '10:50']] as $hour => [$from, $until]) {
        TeachingSchoolHour::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->year->id, 'hour' => $hour, 'from' => $from, 'until' => $until]);
    }
    $expected = array_values(array_map(fn (int $hour): array => [
        'hour' => $hour, 'starts_at' => [1 => '07:45', 2 => '08:40', 3 => '10:00'][$hour], 'ends_at' => [1 => '08:35', 2 => '09:30', 3 => '10:50'][$hour],
    ], $hours));
    $id = $this->actingAs($this->teacher, 'sanctum')->postJson($this->baseUrl, [
        ...$this->payload, 'school_hours' => $hours, 'starts_at' => '18:00', 'ends_at' => '19:00',
        'time_segments' => [['starts_at' => '00:00', 'ends_at' => '23:59']],
    ])->assertCreated()->assertJsonPath('data.school_hours', $hours)->assertJsonPath('data.time_segments', $expected)->json('data.id');
    expect(TeachingPersonalAppointment::findOrFail($id)->time_segments)->toEqual($expected);
    $this->getJson($this->baseUrl)->assertOk()->assertJsonPath('data.0.time_segments', fn (array $segments): bool => $segments == $expected);
})->with([[[1]], [[1, 2]], [[1, 3]]]);

test('keeps weekly school hour snapshots and can edit a series to free time', function () {
    $hour = TeachingSchoolHour::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->year->id, 'hour' => 2, 'from' => '09:10', 'until' => '10:00']);
    $this->actingAs($this->teacher, 'sanctum');
    $id = $this->postJson($this->baseUrl, [...$this->payload, 'school_hours' => [2], 'starts_at' => null, 'ends_at' => null, 'weekly' => true, 'repeat_until' => '2026-11-02'])
        ->assertCreated()->assertJsonPath('data.starts_at', '09:10')->assertJsonPath('data.repeat_until', '2026-11-02')->json('data.id');
    $hour->update(['from' => '10:10', 'until' => '11:00']);
    $this->getJson($this->baseUrl)->assertOk()->assertJsonPath('data.0.time_segments.0.starts_at', '09:10');
    $this->putJson("{$this->baseUrl}/{$id}", [...$this->payload, 'school_hours' => [], 'weekly' => true, 'repeat_until' => '2026-11-02'])
        ->assertOk()->assertJsonPath('data.school_hours', [])->assertJsonPath('data.time_segments', fn (array $segments): bool => $segments == [['starts_at' => '12:30', 'ends_at' => '13:20']]);
    $this->assertDatabaseCount('teaching_personal_appointments', 1);
});

test('rejects missing foreign duplicated or incorrectly configured school hours', function (string $case) {
    $attributes = ['school_id' => $this->school->id, 'schoolyear_id' => $this->year->id, 'hour' => 1, 'from' => '08:00', 'until' => '08:50'];
    if ($case === 'other school') {
        $attributes['school_id'] = School::factory()->create()->id;
    } elseif ($case === 'other year') {
        $attributes['schoolyear_id'] = Schoolyear::factory()->create()->id;
    } elseif ($case === 'reversed time') {
        $attributes['until'] = '07:50';
    } elseif ($case === 'invalid time') {
        $attributes['from'] = '25:00';
    }
    if ($case !== 'missing') {
        TeachingSchoolHour::factory()->create($attributes);
    }
    if ($case === 'overlap') {
        TeachingSchoolHour::factory()->create([...$attributes, 'hour' => 2, 'from' => '08:30', 'until' => '09:20']);
    }
    $hours = match ($case) {
        'duplicate selection' => [1, 1],
        'overlap' => [1, 2],
        default => [1],
    };
    $this->actingAs($this->teacher, 'sanctum')->postJson($this->baseUrl, [...$this->payload, 'school_hours' => $hours, 'starts_at' => null, 'ends_at' => null])
        ->assertUnprocessable()->assertJsonValidationErrors($case === 'duplicate selection' ? 'school_hours.0' : 'school_hours');
    $this->assertDatabaseCount('teaching_personal_appointments', 0);
})->with(['missing', 'other school', 'other year', 'reversed time', 'invalid time', 'overlap', 'duplicate selection']);
