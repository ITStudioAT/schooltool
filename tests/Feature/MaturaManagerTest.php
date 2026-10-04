<?php

use App\Models\Import116;
use App\Models\MaturaSession;
use App\Models\MaturaVisit;
use App\Models\School;
use App\Models\Schoolyear;
use App\Models\User;
use App\Services\Matura\MaturaReportService;
use App\Services\Matura\MaturaSetupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function maturaFixture(int $places = 1, bool $active = true): array
{
    $school = School::factory()->create();
    $year = Schoolyear::factory()->create(['school_id' => $school->id]);
    $user = User::factory()->create(['school_id' => $school->id, 'schoolyear_id' => $year->id, 'is_active' => true]);
    Role::findOrCreate('teacher', 'web');
    $user->assignRole('teacher');
    $session = app(MaturaSetupService::class)->save($user, [
        'name' => 'Deutsch · Haupttermin', 'exam_date' => '2026-10-04', 'schoolyear_id' => $year->id, 'waiting_places' => $places,
        'rooms' => [
            ['name' => 'Raum A', 'student_ids' => [], 'manual_students' => [['name' => 'Müller Änne', 'class_name' => '8A'], ['name' => 'Öztürk Emil', 'class_name' => '8A']]],
            ['name' => 'Raum B', 'student_ids' => [], 'manual_students' => [['name' => 'Groß Zoë', 'class_name' => '8B']]],
        ],
    ]);
    if ($active) {
        $session->update(['status' => 'active']);
    }

    return [$user, $session->fresh(), $year];
}

function maturaAction(object $test, MaturaSession $session, string $action, ?MaturaVisit $visit = null, array $data = []): TestResponse
{
    return $test->postJson(route('admin.matura.action', $session), $data + [
        'action' => $action, 'operation_key' => (string) Str::uuid(),
        'visit_id' => $visit?->id, 'expected_status' => $visit?->fresh()->status,
    ]);
}

function maturaRequest(object $test, MaturaSession $session, int $studentIndex = 0): MaturaVisit
{
    maturaAction($test, $session, 'request', data: ['student_id' => $session->students()->orderBy('id')->get()[$studentIndex]->id])->assertOk();

    return $session->visits()->latest('id')->first();
}

test('00-manager requires authentication and preserves school and ownership boundaries', function () {
    [$owner, $session] = maturaFixture();
    $this->getJson(route('admin.matura.index'))->assertUnauthorized();
    $this->actingAs($owner)->getJson(route('admin.matura.show', $session))->assertOk()->assertJsonPath('actor.manager', true);
    $outsider = User::factory()->create(['school_id' => $owner->school_id, 'is_active' => true]);
    $outsider->assignRole('teacher');
    $this->actingAs($outsider)->getJson(route('admin.matura.show', $session))->assertForbidden();
    $this->getJson(route('admin.matura.index'))->assertJsonCount(0, 'sessions');
    [$other] = maturaFixture();
    $this->actingAs($other)->getJson(route('admin.matura.show', $session))->assertNotFound();
    $this->getJson(route('admin.matura.pdf', $session))->assertNotFound();
});

test('setup snapshots current imported students and rejects cross-school and duplicate assignments', function () {
    [$owner, , $year] = maturaFixture();
    $source = Import116::factory()->forSchool(School::findOrFail($owner->school_id))->importedBy($owner)->create(['schoolyear_id' => $year->id, 'last_name' => 'Änderung', 'class' => '8Z']);
    $payload = ['name' => 'Mathematik', 'exam_date' => '2026-10-04', 'waiting_places' => 0, 'schoolyear_id' => $year->id,
        'rooms' => [['name' => 'A', 'student_ids' => [$source->id], 'manual_students' => []]]];
    $this->actingAs($owner)->postJson(route('admin.matura.store'), $payload)->assertCreated();
    $this->assertDatabaseHas('matura_students', ['import116_id' => $source->id, 'class_name' => '8Z']);
    $payload['rooms'][] = ['name' => 'B', 'student_ids' => [$source->id], 'manual_students' => []];
    $this->postJson(route('admin.matura.store'), $payload)->assertUnprocessable();
    $payload['rooms'] = [['name' => 'A', 'student_ids' => [$source->id], 'manual_students' => []]];
    $payload['schoolyear_id'] = Schoolyear::factory()->create()->id;
    $this->postJson(route('admin.matura.store'), $payload)->assertNotFound();
});

test('capacity reserves every released place including zero waiting places', function (int $places) {
    [$owner, $session] = maturaFixture($places);
    $this->actingAs($owner);
    $visits = [];
    for ($index = 0; $index < 3; $index++) {
        $visits[] = maturaRequest($this, $session, $index);
    }
    for ($index = 0; $index <= $places; $index++) {
        maturaAction($this, $session, 'approve', $visits[$index])->assertOk();
    }
    maturaAction($this, $session, 'approve', $visits[$places + 1])->assertConflict();
    expect($session->visits()->where('status', 'approved')->count())->toBe($places + 1);
})->with([0, 1]);

test('requests and transitions are idempotent and reject duplicate or stale actions', function () {
    [$owner, $session] = maturaFixture();
    $this->actingAs($owner);
    $payload = ['action' => 'request', 'operation_key' => (string) Str::uuid(), 'student_id' => $session->students()->first()->id];
    $this->postJson(route('admin.matura.action', $session), $payload)->assertOk();
    $this->postJson(route('admin.matura.action', $session), $payload)->assertOk();
    expect($session->visits()->count())->toBe(1);
    $payload['operation_key'] = (string) Str::uuid();
    $this->postJson(route('admin.matura.action', $session), $payload)->assertConflict();
    $visit = $session->visits()->first();
    maturaAction($this, $session, 'enter', $visit)->assertConflict();
    $transition = ['action' => 'approve', 'operation_key' => (string) Str::uuid(), 'visit_id' => $visit->id, 'expected_status' => 'requested'];
    $this->postJson(route('admin.matura.action', $session), $transition)->assertOk();
    $this->postJson(route('admin.matura.action', $session), $transition)->assertOk();
    maturaAction($this, $session, 'depart', $visit, ['expected_status' => 'requested'])->assertConflict();
    expect($session->events()->where('action', 'approve')->count())->toBe(1);
});

test('actual toilet entry and exit remain separate from requests and return durations', function () {
    [$owner, $session] = maturaFixture();
    $this->actingAs($owner);
    $this->travelTo(now()->setDate(2026, 10, 4)->setTime(9, 0));
    $visit = maturaRequest($this, $session);
    $this->travel(1)->minutes();
    maturaAction($this, $session, 'approve', $visit)->assertOk();
    $this->travel(1)->minutes();
    maturaAction($this, $session, 'depart', $visit)->assertOk();
    $this->travel(1)->minutes();
    maturaAction($this, $session, 'arrive', $visit)->assertOk();
    $this->travel(1)->minutes();
    maturaAction($this, $session, 'enter', $visit)->assertOk();
    $this->getJson(route('admin.matura.report', $session))->assertJsonPath('summary.actual_visits', 1)->assertJsonPath('rows.0.toilet_seconds', null)->assertJsonPath('rows.0.absence_seconds', null);
    $this->travel(3)->minutes();
    maturaAction($this, $session, 'exit', $visit)->assertOk();
    $this->travel(1)->minutes();
    maturaAction($this, $session, 'return', $visit)->assertOk();
    $this->getJson(route('admin.matura.report', $session))
        ->assertJsonPath('rows.0.toilet_seconds', 180)->assertJsonPath('rows.0.absence_seconds', 360)
        ->assertJsonPath('rows.0.room_wait_seconds', 120)->assertJsonPath('rows.0.station_wait_seconds', 60)
        ->assertJsonPath('summary.open', 0);
    $this->travelBack();
});

test('toilet remains single occupancy and returning students no longer reserve a place', function () {
    [$owner, $session] = maturaFixture(1);
    $this->actingAs($owner);
    $first = maturaRequest($this, $session);
    $second = maturaRequest($this, $session, 1);
    foreach ([$first, $second] as $visit) {
        foreach (['approve', 'depart', 'arrive'] as $action) {
            maturaAction($this, $session, $action, $visit)->assertOk();
        }
    }
    maturaAction($this, $session, 'enter', $first)->assertOk();
    maturaAction($this, $session, 'enter', $second)->assertConflict();
    maturaAction($this, $session, 'exit', $first)->assertOk()->assertJsonPath('reserved', 1);
    maturaAction($this, $session, 'enter', $second)->assertOk();
});

test('cancellation and corrections retain an audit trail without inventing toilet times', function () {
    [$owner, $session] = maturaFixture();
    $this->actingAs($owner);
    $visit = maturaRequest($this, $session);
    maturaAction($this, $session, 'cancel', $visit)->assertUnprocessable();
    maturaAction($this, $session, 'cancel', $visit, ['reason' => 'Versehentlich angemeldet'])->assertOk();
    $visit = maturaRequest($this, $session);
    foreach (['approve', 'depart'] as $action) {
        maturaAction($this, $session, $action, $visit)->assertOk();
    }
    maturaAction($this, $session, 'cancel', $visit, ['reason' => 'Falscher Schritt'])->assertConflict();
    maturaAction($this, $session, 'return_without_toilet', $visit, ['reason' => 'Nicht mehr notwendig'])->assertOk();
    $this->getJson(route('admin.matura.report', $session))->assertJsonPath('summary.actual_visits', 0)->assertJsonPath('rows.1.entered_at', null);
    maturaAction($this, $session, 'void', $visit, ['reason' => 'Falscher Schüler erfasst'])->assertOk();
    $this->assertDatabaseHas('matura_events', ['action' => 'void', 'matura_visit_id' => $visit->id]);
    $this->getJson(route('admin.matura.report', $session))->assertJsonPath('rows.1.absence_seconds', null);
});

test('guest access is limited to its station, must be claimed and is revoked immediately', function () {
    [$owner, $session] = maturaFixture();
    $room = $session->rooms()->first();
    $result = $this->actingAs($owner)->postJson(route('admin.matura.invite', $session), ['room_id' => $room->id, 'name' => 'Gast Aufsicht', 'hours' => 4])->assertCreated();
    $token = explode('#', $result->json('url'))[1];
    expect($session->accesses()->first()->token_hash)->not->toBe($token);
    auth()->forgetGuards();
    $this->postJson(route('matura.login'), ['token' => $token])->assertOk()->assertJsonCount(2, 'students')->assertJsonPath('actor.manager', false);
    $payload = ['action' => 'request', 'operation_key' => (string) Str::uuid(), 'student_id' => $session->students()->first()->id];
    $this->postJson(route('matura.action'), $payload)->assertConflict();
    $this->postJson(route('matura.action'), ['action' => 'claim', 'operation_key' => (string) Str::uuid()])->assertOk();
    $this->postJson(route('matura.action'), $payload)->assertOk();
    $payload['student_id'] = $session->students()->latest('id')->first()->id;
    $payload['operation_key'] = (string) Str::uuid();
    $this->postJson(route('matura.action'), $payload)->assertForbidden();
    $this->getJson(route('admin.matura.index'))->assertUnauthorized();
    $this->actingAs($owner)->deleteJson(route('admin.matura.revoke', [$session, $result->json('access.id')]))->assertOk();
    auth()->forgetGuards();
    $this->getJson(route('matura.state'))->assertForbidden();
});

test('assigned teachers can claim their station but cannot manage or view reports', function () {
    [$owner, $session] = maturaFixture();
    $teacher = User::factory()->create(['school_id' => $owner->school_id, 'is_active' => true]);
    $teacher->assignRole('teacher');
    $this->actingAs($owner)->postJson(route('admin.matura.invite', $session), ['user_id' => $teacher->id, 'room_id' => null, 'hours' => 4])->assertCreated()->assertJsonPath('url', null);
    $this->actingAs($teacher)->getJson(route('admin.matura.show', $session))->assertOk()->assertJsonPath('actor.manager', false);
    maturaAction($this, $session, 'claim')->assertOk()->assertJsonPath('actor.owns_station', true);
    $this->getJson(route('admin.matura.report', $session))->assertForbidden();
    $this->putJson(route('admin.matura.lifecycle', $session), ['status' => 'closed', 'waiting_places' => 1])->assertForbidden();
});

test('supervisor handover invalidates the prior operator and rejects stale takeover', function () {
    [$owner, $session] = maturaFixture();
    $room = $session->rooms()->first();
    $first = app(MaturaSetupService::class)->invite($session, $owner, ['name' => 'Erste Aufsicht', 'room_id' => $room->id, 'hours' => 4]);
    $second = app(MaturaSetupService::class)->invite($session, $owner, ['name' => 'Zweite Aufsicht', 'room_id' => $room->id, 'hours' => 4]);
    $this->postJson(route('matura.login'), ['token' => $first['token']])->assertOk();
    $this->postJson(route('matura.action'), ['action' => 'claim', 'operation_key' => (string) Str::uuid()])->assertOk();
    $this->postJson(route('matura.login'), ['token' => $second['token']])->assertOk();
    $this->postJson(route('matura.action'), ['action' => 'claim', 'operation_key' => (string) Str::uuid()])->assertConflict();
    $this->postJson(route('matura.action'), ['action' => 'claim', 'operation_key' => (string) Str::uuid(), 'previous_access_id' => $first['access']->id])->assertOk();
    $this->postJson(route('matura.login'), ['token' => $first['token']])->assertOk()->assertJsonPath('actor.owns_station', false);
    $this->postJson(route('matura.action'), ['action' => 'request', 'operation_key' => (string) Str::uuid(), 'student_id' => $session->students()->first()->id])->assertConflict();
});

test('expired tokens, foreign visits and unsafe capacity reduction are rejected', function () {
    [$owner, $session] = maturaFixture();
    $guest = app(MaturaSetupService::class)->invite($session, $owner, ['name' => 'Expired', 'hours' => 1]);
    $this->travel(2)->hours();
    $this->postJson(route('matura.login'), ['token' => $guest['token']])->assertUnprocessable();
    $this->travelBack();
    $this->actingAs($owner);
    foreach ([0, 1] as $index) {
        $visit = maturaRequest($this, $session, $index);
        maturaAction($this, $session, 'approve', $visit)->assertOk();
    }
    $this->putJson(route('admin.matura.lifecycle', $session), ['status' => 'active', 'waiting_places' => 0])->assertConflict();
    $this->putJson(route('admin.matura.lifecycle', $session), ['status' => 'closed', 'waiting_places' => 1])->assertConflict();
    [, $other] = maturaFixture();
    $foreign = $other->visits()->create(['matura_student_id' => $other->students()->first()->id, 'matura_room_id' => $other->rooms()->first()->id, 'request_key' => Str::uuid(), 'status' => 'requested', 'requested_at' => now()]);
    maturaAction($this, $session, 'approve', $foreign)->assertNotFound();
});

test('PDF renders real unicode pages and respects room and student filters', function () {
    [$owner, $session] = maturaFixture();
    $this->actingAs($owner);
    $first = maturaRequest($this, $session);
    maturaRequest($this, $session, 2);
    $url = route('admin.matura.report', $session).'?room_id='.$first->matura_room_id.'&student_id='.$first->matura_student_id;
    $this->getJson($url)->assertJsonCount(1, 'rows')->assertJsonPath('rows.0.student_name', 'Müller Änne');
    $report = app(MaturaReportService::class)->report($session, []);
    $html = view('pdfs.matura-report', $report)->render();
    expect($html)->toContain('Müller Änne', 'Europe/Vienna', 'Toilettengang');
    $response = $this->get(route('admin.matura.pdf', $session))->assertOk()->assertHeader('content-type', 'application/pdf');
    expect($response->getContent())->toStartWith('%PDF-');
});

test('a schoolyear with a matura cannot be removed through the schoolyear endpoint', function () {
    [$owner, $session, $year] = maturaFixture();
    Role::findOrCreate('admin', 'web');
    $owner->assignRole('admin');
    $this->actingAs($owner)->deleteJson('/api/admin/schoolyears/'.$year->id)->assertConflict();
    $this->assertModelExists($session);
    $this->assertModelExists($year);
});
