<?php

use App\Models\Import116;
use App\Models\School;
use App\Models\SchoolTool;
use App\Models\Schoolyear;
use App\Models\TeachingCourse;
use App\Models\TeachingSchema;
use App\Models\User;
use App\Services\TeachingCourseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    foreach (['teacher', 'admin', 'teaching_admin', 'student', 'super_admin'] as $role) {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
    }

    $this->school = School::factory()->create();
    $this->schoolyear = Schoolyear::factory()->create([
        'school_id' => $this->school->id,
        'name' => '2025/26',
        'concerns' => '2025/26',
    ]);
    grantSchoolToolLicenceForTests($this->school, 'Lehrertool');
    enableSchoolToolModuleForTests($this->school, 'teaching');
    SchoolTool::where('school_id', $this->school->id)->update(['active_schoolyear_id' => $this->schoolyear->id]);
    $attributes = ['school_id' => $this->school->id, 'schoolyear_id' => $this->schoolyear->id];
    $this->teacher = User::factory()->create($attributes);
    $this->teacher->assignRole('teacher');
    $this->student = User::factory()->create($attributes);
    $this->student->assignRole('student');
    $this->course = TeachingCourse::factory()->create([
        ...$attributes,
        'user_id' => $this->teacher->id,
        'classes' => ['3B'],
        'teaching_schema_id' => 'test-schema',
    ]);
    TeachingSchema::query()->create([
        ...$attributes,
        'user_id' => $this->teacher->id,
        'schema_id' => 'test-schema',
        'name' => 'Test',
        'works' => [],
        'grading' => [],
    ]);
    Import116::factory()->create([
        ...$attributes,
        'class' => '3B',
        'import_user_id' => $this->teacher->id,
    ]);
    $this->courseStudent = $this->course->teachingCourseStudents()->create(['user_id' => $this->student->id]);
    $this->endpoint = "/api/admin/teaching/courses/{$this->course->id}/students/{$this->courseStudent->id}/special-information";
    $this->actingAs($this->teacher, 'sanctum');
});

test('special information is encrypted and returned only through its dedicated endpoint', function () {
    $this->putJson($this->endpoint, ['special_information' => '  Vertraulicher Hinweis  '])
        ->assertSuccessful()
        ->assertJsonPath('data.special_information', 'Vertraulicher Hinweis')
        ->assertJsonPath('data.has_special_information', true);

    $student = $this->courseStudent->fresh();
    expect($student->special_information)->toBe('Vertraulicher Hinweis')
        ->and($student->getRawOriginal('special_information'))->not->toContain('Vertraulicher Hinweis')
        ->and($student->toArray())->not->toHaveKey('special_information')
        ->and($student->toLegacyPayloadArray())->not->toHaveKey('special_information');

    $this->getJson($this->endpoint)
        ->assertSuccessful()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertJsonPath('data.special_information', 'Vertraulicher Hinweis');

    $response = $this->getJson("/api/admin/teaching/courses/{$this->course->id}")->assertSuccessful();
    expect($response->json('data.students.0.has_special_information'))->toBeTrue()
        ->and($response->getContent())->not->toContain('Vertraulicher Hinweis')
        ->and($response->json('data.students.0'))->not->toHaveKey('special_information');
});

test('special information can be cleared and otherwise survives course student synchronization', function () {
    $this->putJson($this->endpoint, ['special_information' => 'Wichtige Information'])->assertSuccessful();
    app(TeachingCourseService::class)->syncCourseStudents($this->course, [
        ['user_id' => $this->student->id, 'comment' => 'Normaler Kommentar'],
    ]);
    expect($this->courseStudent->fresh()->special_information)->toBe('Wichtige Information');

    app(TeachingCourseService::class)->syncCourseStudents($this->course, []);
    expect($this->courseStudent->fresh()->deleted_at)->toBeNull();

    $this->putJson($this->endpoint, ['special_information' => '   '])
        ->assertSuccessful()
        ->assertJsonPath('data.has_special_information', false)
        ->assertJsonPath('data.special_information', '');
    expect($this->courseStudent->fresh()->getRawOriginal('special_information'))->toBeNull();
});

test('special information endpoints reject other teachers and students', function () {
    $otherTeacher = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
    ]);
    $otherTeacher->assignRole('teacher');
    foreach ([$otherTeacher, $this->student] as $user) {
        $this->actingAs($user, 'sanctum');
        $this->getJson($this->endpoint)->assertForbidden();
        $this->putJson($this->endpoint, ['special_information' => 'Blocked'])->assertForbidden();
    }
});

test('special information endpoints reject mismatched courses and schoolyears', function () {
    $otherCourse = TeachingCourse::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->teacher->id,
    ]);
    $url = "/api/admin/teaching/courses/{$otherCourse->id}/students/{$this->courseStudent->id}/special-information";
    $this->getJson($url)->assertNotFound();
    $this->putJson($url, ['special_information' => 'Blocked'])->assertNotFound();

    $this->teacher->update(['schoolyear_id' => Schoolyear::factory()->create(['school_id' => $this->school->id])->id]);
    $this->getJson($this->endpoint)->assertForbidden();
    $this->putJson($this->endpoint, ['special_information' => 'Blocked'])->assertForbidden();
});

test('special information endpoints reject access from another school', function () {
    $otherSchool = School::factory()->create();
    $otherSchoolyear = Schoolyear::factory()->create(['school_id' => $otherSchool->id]);
    grantSchoolToolLicenceForTests($otherSchool, 'Lehrertool');
    enableSchoolToolModuleForTests($otherSchool, 'teaching');
    $admin = User::factory()->create(['school_id' => $otherSchool->id, 'schoolyear_id' => $otherSchoolyear->id]);
    $admin->assignRole('admin');

    $this->actingAs($admin, 'sanctum');
    $this->getJson($this->endpoint)->assertForbidden();
    $this->putJson($this->endpoint, ['special_information' => 'Blocked'])->assertForbidden();
});

test('course comment and star updates preserve special information without exposing it', function () {
    $this->putJson($this->endpoint, ['special_information' => 'Vertraulicher Hinweis'])->assertSuccessful();
    $this->putJson("/api/admin/teaching/courses/{$this->course->id}", [
        'title' => $this->course->title,
        'classes' => ['3B'],
        'teaching_schema_id' => 'test-schema',
        'students_info' => [[
            'user_id' => $this->student->id,
            'comment' => '<p>Normaler Kommentar</p><script>alert(1)</script>',
            'stars' => [['comment' => 'Besondere Leistung', 'date' => '2026-09-05', 'value' => 1]],
        ]],
    ])->assertSuccessful()->assertDontSee('Vertraulicher Hinweis');

    $student = $this->courseStudent->fresh();
    expect($student->special_information)->toBe('Vertraulicher Hinweis')
        ->and($student->comment)->toContain('Normaler Kommentar')->not->toContain('<script>')
        ->and($student->stars[0]['comment'])->toBe('Besondere Leistung');
});

test('special information rejects invalid values', function (array $payload) {
    $this->putJson($this->endpoint, $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('special_information');
})->with([
    'missing' => [[]],
    'array' => [['special_information' => ['invalid']]],
    'too long' => [['special_information' => str_repeat('x', 4097)]],
]);

test('special information is never included in student course responses', function () {
    $this->putJson($this->endpoint, ['special_information' => 'Vertraulicher Hinweis'])->assertSuccessful();
    $this->actingAs($this->student, 'web');
    $response = $this->getJson("/api/homepage/student/courses/{$this->course->id}")->assertSuccessful();
    expect($response->getContent())->not->toContain('Vertraulicher Hinweis', 'special_information');
});

test('course updates validate student info comments and star reasons', function (array $invalidFields, string $error) {
    $this->putJson("/api/admin/teaching/courses/{$this->course->id}", [
        'title' => $this->course->title,
        'classes' => ['3B'],
        'teaching_schema_id' => 'test-schema',
        'students_info' => [['user_id' => $this->student->id, ...$invalidFields]],
    ])->assertUnprocessable()->assertJsonValidationErrors($error);
})->with([
    'invalid comment' => [['comment' => ['invalid']], 'students_info.0.comment'],
    'long star reason' => [['stars' => [['comment' => str_repeat('x', 1025)]]], 'students_info.0.stars.0.comment'],
    'invalid star value' => [['stars' => [['comment' => 'Good work', 'value' => 2]]], 'students_info.0.stars.0.value'],
    'sensitive field in general update' => [['special_information' => 'Private'], 'students_info.0.special_information'],
]);

test('imported students without user accounts can also have special information', function () {
    $import = Import116::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'import_user_id' => $this->teacher->id,
        'user_id' => null,
    ]);
    $courseStudent = $this->course->teachingCourseStudents()->create(['import116_id' => $import->id]);
    $url = "/api/admin/teaching/courses/{$this->course->id}/students/{$courseStudent->id}/special-information";
    $this->putJson($url, ['special_information' => 'Information'])
        ->assertSuccessful()->assertJsonPath('data.has_special_information', true);
});
