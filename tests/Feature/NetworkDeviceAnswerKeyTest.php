<?php

use App\Models\NetworkDeviceAnswerKey;
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
    $this->base = '/api/admin/teaching-tools/tests/networks/device-questions';
});

test('saves model answers for correction without overwriting a newer version or the shared defaults', function (): void {
    Role::findOrCreate('teacher', 'web');
    $teacher = User::factory()->create(['school_id' => $this->school->id, 'is_active' => true]);
    $teacher->assignRole('teacher');
    $this->actingAs($teacher, 'web');

    $defaults = $this->getJson($this->base)
        ->assertOk()
        ->assertJsonPath('school_id', $this->school->id)
        ->assertJsonPath('revision', 0)
        ->assertJsonCount(5, 'answers')
        ->json();
    $custom = $defaults;
    $custom['answers']['switch'] = 'Testspezifische Musterantwort zum Switch.';
    $this->putJson($this->base, $custom)
        ->assertOk()
        ->assertJsonPath('revision', 1)
        ->assertJsonPath('answers.switch', $custom['answers']['switch']);
    $this->putJson($this->base, $defaults)->assertConflict();
    $this->getJson($this->base)->assertOk()->assertJsonPath('answers.switch', $custom['answers']['switch']);

    $custom['revision'] = 1;
    $custom['answers']['switch'] = 'Überarbeitete Musterantwort zum Switch.';
    $this->putJson($this->base, $custom)->assertOk()->assertJsonPath('revision', 2);
    expect(NetworkDeviceAnswerKey::forSchool($this->school->id)->answers['switch'])->toBe($custom['answers']['switch']);
    expect(NetworkDeviceAnswerKey::forSchool(School::factory()->create()->id)->answers)->toMatchArray($defaults['answers']);

    $custom['revision'] = 2;
    unset($custom['answers']['router']);
    $this->putJson($this->base, $custom)->assertUnprocessable()->assertJsonValidationErrors('answers.router');
});

test('keeps the answer key outside student and guest access', function (): void {
    $this->getJson($this->base)->assertUnauthorized();
    Role::findOrCreate('student', 'web');
    $student = User::factory()->create(['school_id' => $this->school->id, 'is_active' => true]);
    $student->assignRole('student');
    $this->actingAs($student, 'web');
    $this->getJson($this->base)->assertForbidden();
    $this->putJson($this->base)->assertForbidden();
});
