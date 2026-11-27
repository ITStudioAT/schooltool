<?php

use App\Models\NetworkTestQuestion;
use App\Models\School;
use App\Models\SchoolTool;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
    $this->base = '/api/admin/teaching-tools/tests/networks/additional-questions';
});

test('a teacher creates, reads, edits and deletes a personal network question with its answer', function (): void {
    $this->actingAs($this->teacher, 'web');
    $this->getJson($this->base)->assertOk()->assertExactJson([]);
    $this->postJson($this->base)->assertUnprocessable()->assertJsonValidationErrors(['question', 'answer']);
    $entry = $this->postJson($this->base, ['question' => 'Was ist ein LAN?', 'answer' => 'Ein lokales Netzwerk.'])
        ->assertCreated()
        ->assertJsonPath('question', 'Was ist ein LAN?')
        ->json();
    $path = $this->base.'/'.$entry['id'];
    $this->getJson($this->base)->assertOk()->assertExactJson([$entry]);
    $this->putJson($path, ['question' => 'Was ist ein WLAN?', 'answer' => 'Ein drahtloses lokales Netzwerk.'])
        ->assertOk()
        ->assertJsonPath('question', 'Was ist ein WLAN?')
        ->assertJsonPath('answer', 'Ein drahtloses lokales Netzwerk.');
    $this->deleteJson($path)->assertNoContent();
    $this->getJson($this->base)->assertOk()->assertExactJson([]);
});

test('network questions are scoped to the active teacher and school', function (): void {
    $this->getJson($this->base)->assertUnauthorized();
    $otherTeacher = User::factory()->create(['school_id' => $this->school->id, 'is_active' => true])->assignRole('teacher');
    $otherSchool = School::factory()->create();
    $this->actingAs($this->teacher, 'web');
    $payload = ['question' => 'Was ist ein Switch?', 'answer' => 'Er verbindet Geräte im Netzwerk.'];
    foreach ([
        ['school_id' => $this->school->id, 'user_id' => $otherTeacher->id],
        ['school_id' => $otherSchool->id, 'user_id' => $this->teacher->id],
    ] as $owner) {
        $private = NetworkTestQuestion::query()->create($owner + $payload);
        $path = $this->base.'/'.$private->id;
        $this->putJson($path, $payload)->assertNotFound();
        $this->deleteJson($path)->assertNotFound();
        expect($private->fresh())->not->toBeNull();
    }
    $this->getJson($this->base)->assertOk()->assertExactJson([]);
});
