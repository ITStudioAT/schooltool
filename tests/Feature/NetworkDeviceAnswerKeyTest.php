<?php

use App\Models\NetworkTestQuestion;
use App\Models\School;
use App\Models\SchoolTool;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

require_once dirname(__DIR__).'/Support/MultipleChoiceTestDatabase.php';
requireMultipleChoiceTestDatabase();
uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->school = School::factory()->create();
    SchoolTool::factory()->create(['school_id' => $this->school->id, 'teaching_visible_admin' => true, 'teaching_visible_user' => true]);
    grantSchoolToolLicenceForTests($this->school, 'Lehrertool');
    Role::findOrCreate('teacher', 'web');
    $this->teacher = User::factory()->create(['school_id' => $this->school->id, 'is_active' => true])->assignRole('teacher');
    $this->base = '/api/admin/teaching-tools/tests/networks/device-questions';
});

test('converts existing school answers once and keeps deleted device questions deleted after reloading', function (): void {
    $this->actingAs($this->teacher, 'web');
    $legacy = DB::table('network_device_answer_keys')->whereNull('school_id')->value('answers');
    $answers = json_decode($legacy, true, flags: JSON_THROW_ON_ERROR);
    $answers['switch'] = 'Testspezifische Musterantwort zum Switch.';
    DB::table('network_device_answer_keys')->insert([
        'school_id' => $this->school->id,
        'answers' => json_encode($answers, JSON_THROW_ON_ERROR),
        'revision' => 3,
    ]);

    $entries = $this->getJson($this->base)->assertOk()
        ->assertJsonPath('school_id', $this->school->id)->assertJsonCount(5, 'questions')->json('questions');
    expect(array_column($entries, 'question'))->toBe([
        'Was ist die Aufgabe des Patchpanels?',
        'Was ist die Aufgabe des Switchs?',
        'Was ist die Aufgabe des Routers?',
        'Was ist die Aufgabe des Datenservers?',
        'Was ist die Aufgabe des WLAN-Access-Points?',
    ]);
    expect(array_column($entries, 'answer'))->toBe([
        $answers['patch_panel'], $answers['switch'], $answers['router'], $answers['data_server'], $answers['access_point'],
    ]);
    $this->getJson($this->base)->assertOk()->assertJsonPath('questions', $entries);

    $path = $this->base.'/'.$entries[1]['id'];
    $this->putJson($path, [
        'question' => 'Welche Aufgabe hat ein Switch?',
        'answer' => 'Überarbeitete Musterantwort zum Switch.',
        'revision' => $entries[1]['revision'],
    ])->assertOk()->assertJsonPath('question', 'Welche Aufgabe hat ein Switch?');
    $entries = $this->getJson($this->base)->assertOk()->assertJsonCount(5, 'questions')
        ->assertJsonPath('questions.1.answer', 'Überarbeitete Musterantwort zum Switch.')->json('questions');

    foreach ($entries as $entry) {
        $this->deleteJson($this->base.'/'.$entry['id'], ['revision' => $entry['revision']])->assertNoContent();
    }

    $this->getJson($this->base)->assertOk()->assertJsonPath('questions', []);
    expect(NetworkTestQuestion::query()->where('school_id', $this->school->id)->count())->toBe(0);
    expect(DB::table('network_device_answer_keys')->whereNull('school_id')->value('answers'))->toBe($legacy);
});

test('creates, edits and deletes a single device question and rejects stale writes to the same record', function (): void {
    $this->actingAs($this->teacher, 'web');
    $defaults = $this->getJson($this->base)->assertOk()->assertJsonCount(5, 'questions')->json('questions');
    $payload = ['question' => 'Wozu dient ein Repeater?', 'answer' => 'Er vergrößert die Reichweite.'];
    $this->postJson($this->base, ['school_id' => $this->school->id])->assertUnprocessable()
        ->assertJsonValidationErrors(['question', 'answer']);
    $this->postJson($this->base, ['school_id' => $this->school->id + 1] + $payload)->assertConflict();
    $entry = $this->postJson($this->base, ['school_id' => $this->school->id] + $payload)
        ->assertCreated()->assertJsonPath('answer', $payload['answer'])->json();
    $path = $this->base.'/'.$entry['id'];
    $this->getJson($this->base)->assertOk()->assertExactJson([
        'school_id' => $this->school->id, 'questions' => [...$defaults, $entry],
    ]);

    $changes = ['question' => 'Was macht ein Repeater?', 'answer' => 'Er empfängt ein Signal und sendet es erneut.'];
    $updated = $this->putJson($path, $changes + ['revision' => $entry['revision']])->assertOk()
        ->assertJsonPath('revision', $entry['revision'] + 1)->json();
    $this->putJson($path, $payload + ['revision' => $entry['revision']])->assertConflict();
    $this->deleteJson($path, ['revision' => $entry['revision']])->assertConflict();
    $this->getJson($this->base)->assertOk()->assertExactJson([
        'school_id' => $this->school->id, 'questions' => [...$defaults, $updated],
    ]);
    $this->assertDatabaseHas('network_test_questions', $changes + ['id' => $entry['id'], 'user_id' => null]);

    $this->deleteJson($path, ['revision' => $updated['revision']])->assertNoContent();
    $this->assertDatabaseMissing('network_test_questions', ['id' => $entry['id']]);
    $this->getJson($this->base)->assertOk()->assertJsonPath('questions', $defaults);
});

test('shares device questions within a school and isolates schools and additional questions', function (): void {
    $additional = '/api/admin/teaching-tools/tests/networks/additional-questions';
    $this->actingAs($this->teacher, 'web');
    $payload = ['question' => 'Künstliche Zusatzfrage', 'answer' => 'Künstliche Antwort'];
    $entry = $this->postJson($additional, $payload)->assertCreated()->json();
    $devices = $this->getJson($this->base)->assertOk()->assertJsonCount(5, 'questions')->json('questions');
    $this->deleteJson($this->base.'/'.$entry['id'], ['revision' => 0])->assertNotFound();
    $this->putJson($additional.'/'.$devices[0]['id'], $payload)->assertNotFound();
    $this->getJson($additional)->assertOk()->assertExactJson([$entry]);

    $colleague = User::factory()->create(['school_id' => $this->school->id, 'is_active' => true])->assignRole('teacher');
    Auth::forgetGuards();
    $this->actingAs($colleague, 'web')->getJson($this->base)->assertOk()->assertJsonPath('questions', $devices);
    $this->getJson($additional)->assertOk()->assertExactJson([]);

    $otherSchool = School::factory()->create();
    SchoolTool::factory()->create(['school_id' => $otherSchool->id, 'teaching_visible_admin' => true, 'teaching_visible_user' => true]);
    grantSchoolToolLicenceForTests($otherSchool, 'Lehrertool');
    $otherTeacher = User::factory()->create(['school_id' => $otherSchool->id, 'is_active' => true])->assignRole('teacher');
    Auth::forgetGuards();
    $this->actingAs($otherTeacher, 'web')->deleteJson($this->base.'/'.$devices[0]['id'], ['revision' => 0])->assertNotFound();
    $this->getJson($this->base)->assertOk()->assertJsonPath('school_id', $otherSchool->id)->assertJsonCount(5, 'questions');
});

test('keeps the answer key outside student and guest access', function (): void {
    $this->getJson($this->base)->assertUnauthorized();
    Role::findOrCreate('student', 'web');
    $student = User::factory()->create(['school_id' => $this->school->id, 'is_active' => true]);
    $student->assignRole('student');
    $this->actingAs($student, 'web');
    $this->getJson($this->base)->assertForbidden();
    $this->postJson($this->base)->assertForbidden();
    $this->putJson($this->base.'/1')->assertForbidden();
    $this->deleteJson($this->base.'/1')->assertForbidden();
});
