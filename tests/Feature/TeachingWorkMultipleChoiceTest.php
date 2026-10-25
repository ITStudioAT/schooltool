<?php

use App\Models\McCatalog;
use App\Models\McTest;
use App\Models\School;
use App\Models\Schoolyear;
use App\Models\TeachingCourse;
use App\Models\TeachingCourseWork;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function workMcFixture(object $context): void
{
    Http::preventStrayRequests();
    Mail::fake();
    foreach (['teacher', 'student'] as $role) {
        Role::findOrCreate($role, 'web');
    }
    $school = School::factory()->create();
    $year = Schoolyear::factory()->create(['school_id' => $school->id]);
    enableSchoolToolModuleForTests($school, 'teaching');
    grantSchoolToolLicenceForTests($school, 'Lehrertool');
    $context->teacher = User::factory()->create(['school_id' => $school->id, 'schoolyear_id' => $year->id])->assignRole('teacher');
    $context->student = User::factory()->create(['school_id' => $school->id, 'schoolyear_id' => $year->id])->assignRole('student');
    $context->course = TeachingCourse::factory()->forSchool($school)->forSchoolyear($year)->forTeacher($context->teacher)->create();
    $context->course->teachingCourseStudents()->create(['user_id' => $context->student->id]);
    $context->work = TeachingCourseWork::factory()->create(['teaching_course_id' => $context->course->id,
        'date_for_all_groups' => '2026-10-20', 'groups' => [], 'is_group_work' => false, 'status' => ['automation_enabled' => true]]);
    $questions = [];
    for ($index = 1; $index <= 10; $index++) {
        $questions[] = ['id' => 'q'.$index, 'prompt' => 'Frage '.$index, 'type' => 'single', 'active' => true, 'reviewed' => true,
            'options' => [['id' => 'a', 'text' => 'A'], ['id' => 'b', 'text' => 'B']], 'correct_option_ids' => ['a']];
    }
    $context->catalog = McCatalog::query()->create(['school_id' => $school->id, 'owner_id' => $context->teacher->id,
        'title' => 'Geprüfte Grundlagen', 'questions' => $questions]);
    $context->base = '/api/admin/teaching/course_works/'.$context->work->id;
    $context->payload = ['mc_mode' => 'new', 'catalog_id' => $context->catalog->id, 'total_points' => 5,
        'duration_minutes' => 15, 'request_id' => 'b10ca8e3-181f-48b7-b842-01a16c855ae8',
        'expected_test_id' => null, 'date' => '2026-10-20'];
    $context->actingAs($context->teacher, 'sanctum');
}

test('the LF creates and binds one draft MC test with its actual candidates and safely resumes the same request', function (): void {
    workMcFixture($this);
    $before = $this->work->getAttributes();
    $response = $this->postJson($this->base.'/prepare-exercise', $this->payload)->assertOk()
        ->assertJsonPath('data.context.include_multiple_choice', true)
        ->assertJsonPath('data.context.multiple_choice_test.status', 'draft');
    $id = $response->json('data.context.multiple_choice_test.test_id');
    $test = McTest::query()->findOrFail($id);
    expect($test->question_count)->toBe(10)->and($test->duration_seconds)->toBe(900)
        ->and($test->attempts()->pluck('student_id')->all())->toBe([$this->student->id]);
    expect($response->json('data.text'))->toContain('Schooltool-MC-Test '.$id, 'Keinen zweiten MC-Test');
    expect($this->work->fresh()->status['multiple_choice_tests']['2026-10-20']['test_id'])->toBe($id);
    expect($this->work->fresh()->getAttributes())->not->toBe($before);
    expect($this->work->fresh()->groups)->toBe($this->work->groups);
    $response->assertJsonPath('data.context.multiple_choice_test.access_links_ready', false)
        ->assertJsonPath('data.context.multiple_choice_test.exercise_export_url', null)
        ->assertJsonCount(10, 'data.context.multiple_choice_test.question_pool');
    $this->postJson($this->base.'/prepare-exercise', $this->payload)->assertOk()->assertJsonPath('data.context.multiple_choice_test.test_id', $id);
    expect(McTest::query()->count())->toBe(1);
    $this->getJson($this->base.'/exercise-options?date=2026-10-20')->assertOk()->assertJsonPath('linked_test_id', $id)
        ->assertJsonMissingPath('catalogs.0.questions')->assertJsonMissingPath('tests.0.snapshot');
    Mail::assertNothingSent();
    Http::assertNothingSent();
});

test('an existing MC test is reused without a second test or changing its attempts', function (): void {
    workMcFixture($this);
    $id = $this->postJson($this->base.'/prepare-exercise', $this->payload)->assertOk()->json('data.context.multiple_choice_test.test_id');
    $attempts = McTest::query()->findOrFail($id)->attempts()->get()->toArray();
    $this->postJson($this->base.'/prepare-exercise', ['mc_mode' => 'existing', 'test_id' => $id, 'expected_test_id' => $id,
        'date' => '2026-10-20', 'request_id' => 'a4c4b2fd-94de-4830-9bdf-c1db638ac17f'])->assertOk()
        ->assertJsonPath('data.context.multiple_choice_test.test_id', $id);
    expect(McTest::query()->count())->toBe(1)->and(McTest::query()->findOrFail($id)->attempts()->get()->toArray())->toBe($attempts);
    $this->postJson($this->base.'/prepare-exercise', [...$this->payload, 'request_id' => '571bbef6-a38e-4bb0-8c8c-892eb9361fa5'])
        ->assertConflict();
    expect(McTest::query()->count())->toBe(1);
});

test('MC choices and creation enforce course and catalog ownership without creating orphan tests', function (): void {
    workMcFixture($this);
    $other = User::factory()->create(['school_id' => $this->teacher->school_id, 'schoolyear_id' => $this->teacher->schoolyear_id])->assignRole('teacher');
    $this->catalog->update(['owner_id' => $other->id]);
    $this->getJson($this->base.'/exercise-options')->assertOk()->assertJsonCount(0, 'catalogs');
    $this->postJson($this->base.'/prepare-exercise', $this->payload)->assertNotFound();
    expect(McTest::query()->count())->toBe(0)->and($this->work->fresh()->status)->not->toHaveKey('multiple_choice_tests');
    $this->actingAs($other, 'sanctum')->getJson($this->base.'/exercise-options')->assertForbidden();
    $this->postJson($this->base.'/prepare-exercise', $this->payload)->assertForbidden();
});

test('incomplete question pools and dates outside the LF roll back the MC draft and binding', function (): void {
    workMcFixture($this);
    $this->catalog->update(['questions' => array_slice($this->catalog->questions, 0, 9)]);
    $this->postJson($this->base.'/prepare-exercise', $this->payload)->assertUnprocessable();
    $this->postJson($this->base.'/prepare-exercise', [...$this->payload, 'date' => '2026-10-21'])->assertUnprocessable();
    expect(McTest::query()->count())->toBe(0)->and($this->work->fresh()->status)->not->toHaveKey('multiple_choice_tests');
});

test('an existing test cannot be bound to candidates missing from its saved attempts', function (): void {
    workMcFixture($this);
    $id = $this->postJson($this->base.'/prepare-exercise', $this->payload)->assertOk()->json('data.context.multiple_choice_test.test_id');
    $other = User::factory()->create(['school_id' => $this->teacher->school_id, 'schoolyear_id' => $this->teacher->schoolyear_id])->assignRole('student');
    $this->course->teachingCourseStudents()->create(['user_id' => $other->id]);
    $this->work->update(['groups' => [['date' => '2026-10-20', 'student_ids' => [$other->id]]]]);
    $before = $this->work->fresh()->getAttributes();
    $this->postJson($this->base.'/prepare-exercise', ['mc_mode' => 'existing', 'test_id' => $id, 'expected_test_id' => $id,
        'date' => '2026-10-20', 'request_id' => 'a4c4b2fd-94de-4830-9bdf-c1db638ac17f'])->assertUnprocessable();
    expect($this->work->fresh()->getAttributes())->toBe($before)
        ->and(McTest::query()->findOrFail($id)->attempts()->pluck('student_id')->all())->toBe([$this->student->id]);
});

test('MC test selection rejects other owners and schoolyears even when an ID is supplied directly', function (string $changed): void {
    workMcFixture($this);
    $id = $this->postJson($this->base.'/prepare-exercise', $this->payload)->assertOk()->json('data.context.multiple_choice_test.test_id');
    $test = McTest::query()->findOrFail($id);
    $test->update($changed === 'owner' ? ['owner_id' => $this->student->id]
        : ['schoolyear_id' => Schoolyear::factory()->create(['school_id' => $this->teacher->school_id])->id]);
    $this->getJson($this->base.'/exercise-options')->assertOk()->assertJsonCount(0, 'tests')->assertJsonPath('linked_test_id', null);
    $this->postJson($this->base.'/prepare-exercise', ['mc_mode' => 'existing', 'test_id' => $id, 'expected_test_id' => $id,
        'date' => '2026-10-20', 'request_id' => 'a4c4b2fd-94de-4830-9bdf-c1db638ac17f'])->assertNotFound();
})->with(['owner', 'year']);
