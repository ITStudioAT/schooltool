<?php

use App\Models\DropboxConnection;
use App\Models\McCatalog;
use App\Models\McTest;
use App\Models\School;
use App\Models\Schoolyear;
use App\Models\TeachingCourse;
use App\Models\TeachingCourseWork;
use App\Models\TeachingEntryArea;
use App\Models\TeachingEntryDefinition;
use App\Models\TeachingWorkModuleLink;
use App\Models\User;
use App\Services\DropboxModuleLinks;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function workMcFixture(object $context): void
{
    $context->travelTo(CarbonImmutable::parse('2026-10-01 12:00:00', 'Europe/Vienna'));
    Http::preventStrayRequests();
    Mail::fake();
    foreach (['teacher', 'student'] as $role) {
        Role::findOrCreate($role, 'web');
    }
    $school = School::factory()->create();
    $year = Schoolyear::factory()->create(['school_id' => $school->id, 'name' => '2026/27', 'from' => '2026-09-01', 'until' => '2027-07-10']);
    enableSchoolToolModuleForTests($school, 'teaching');
    grantSchoolToolLicenceForTests($school, 'Lehrertool');
    $context->teacher = User::factory()->create(['school_id' => $school->id, 'schoolyear_id' => $year->id])->assignRole('teacher');
    $context->student = User::factory()->create(['school_id' => $school->id, 'schoolyear_id' => $year->id])->assignRole('student');
    $area = TeachingEntryArea::factory()->create(['school_id' => $school->id, 'schoolyear_id' => $year->id, 'user_id' => $context->teacher->id]);
    TeachingEntryDefinition::factory()->create(['school_id' => $school->id, 'schoolyear_id' => $year->id, 'user_id' => $context->teacher->id,
        'teaching_entry_area_id' => $area->id, 'short_name' => 'MA', 'name' => 'Leistungsfeststellung', 'category' => 'Benotung',
        'has_properties' => true, 'properties_mode' => 'points', 'maximum_points' => 5]);
    $context->course = TeachingCourse::factory()->forSchool($school)->forSchoolyear($year)->forTeacher($context->teacher)->create(['teaching_entry_area_id' => $area->id]);
    $context->course->teachingCourseStudents()->create(['user_id' => $context->student->id]);
    $context->work = TeachingCourseWork::factory()->create(['teaching_course_id' => $context->course->id,
        'date_for_all_groups' => '2026-10-20', 'finish_until_date' => '2026-10-20', 'finish_until_time' => null,
        'groups' => [], 'is_group_work' => false, 'status' => ['automation_enabled' => true]]);
    $questions = [];
    for ($index = 1; $index <= 10; $index++) {
        $questions[] = ['id' => 'q'.$index, 'prompt' => 'Frage '.$index, 'type' => 'single', 'active' => true, 'reviewed' => true,
            'options' => [['id' => 'a', 'text' => 'A'], ['id' => 'b', 'text' => 'B']], 'correct_option_ids' => ['a']];
    }
    $context->catalog = McCatalog::query()->create(['school_id' => $school->id, 'owner_id' => $context->teacher->id,
        'title' => 'Geprüfte Grundlagen', 'questions' => $questions]);
    $context->base = '/api/admin/teaching/course_works/'.$context->work->id;
    $context->payload = ['mc_mode' => 'new', 'catalog_id' => $context->catalog->id, 'total_points' => 5,
        'mc_points' => 2, 'expected_maximum_points' => 5,
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
        ->and($test->start_mode)->toBe('individual')->and($test->closes_at->setTimezone('Europe/Vienna')->format('Y-m-d H:i:s'))->toBe('2026-10-20 23:59:00')
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

test('the LF uses the selected question count and rejects unavailable or invalid counts', function (): void {
    workMcFixture($this);
    $this->postJson($this->base.'/prepare-exercise', [...$this->payload, 'question_count' => 0])
        ->assertUnprocessable()->assertJsonValidationErrors('question_count');
    $this->postJson($this->base.'/prepare-exercise', [...$this->payload, 'question_count' => 11])
        ->assertUnprocessable()->assertJsonValidationErrors('question_count');
    expect(McTest::query()->count())->toBe(0);

    $response = $this->postJson($this->base.'/prepare-exercise', [...$this->payload, 'question_count' => 7, 'duration_minutes' => 7])
        ->assertOk()->assertJsonPath('data.context.multiple_choice_test.question_count', 7);
    $test = McTest::query()->findOrFail($response->json('data.context.multiple_choice_test.test_id'));
    expect($test->question_count)->toBe(7)->and($test->duration_seconds)->toBe(420);
});

test('a small reviewed catalog can be used for a test with fewer than ten questions', function (): void {
    workMcFixture($this);
    $this->catalog->update(['questions' => array_slice($this->catalog->questions, 0, 4)]);
    $this->getJson($this->base.'/exercise-options?date=2026-10-20')->assertOk()
        ->assertJsonPath('catalogs.0.reviewed_question_count', 4);
    $this->postJson($this->base.'/prepare-exercise', [...$this->payload, 'question_count' => 4])
        ->assertOk()->assertJsonPath('data.context.multiple_choice_test.question_count', 4);
});

test('work responses show only the titles of tests actually bound to the work and its school year', function (): void {
    workMcFixture($this);
    $id = $this->postJson($this->base.'/prepare-exercise', $this->payload)->assertOk()->json('data.context.multiple_choice_test.test_id');
    $test = McTest::query()->findOrFail($id);
    $otherSchool = School::factory()->create();
    $otherYear = Schoolyear::factory()->create(['school_id' => $otherSchool->id]);
    $foreign = $test->replicate();
    $foreign->school_id = $otherSchool->id;
    $foreign->schoolyear_id = $otherYear->id;
    $foreign->title = 'Fremder Test';
    $foreign->save();
    $notBound = $test->replicate();
    $notBound->title = 'Nicht zugeordneter Test';
    $notBound->save();
    $status = $this->work->fresh()->status;
    $status['multiple_choice_tests']['foreign'] = ['work_id' => $this->work->id, 'test_id' => $foreign->id];
    $status['multiple_choice_tests']['other_work'] = ['work_id' => $this->work->id + 1, 'test_id' => $notBound->id];
    $this->work->update(['status' => $status]);

    $this->getJson($this->base)->assertOk()
        ->assertJsonPath('data.multiple_choice_test_titles.'.$id, $test->title)
        ->assertJsonMissingPath('data.multiple_choice_test_titles.'.$foreign->id)
        ->assertJsonMissingPath('data.multiple_choice_test_titles.'.$notBound->id);
    $this->getJson('/api/admin/teaching/course_works?course_id='.$this->course->id)->assertOk()
        ->assertJsonPath('data.0.multiple_choice_test_titles.'.$id, $test->title)
        ->assertJsonMissingPath('data.0.multiple_choice_test_titles.'.$foreign->id);
});

test('LF MC tests use the saved end time when one is specified', function (): void {
    workMcFixture($this);
    $this->work->update(['finish_until_date' => '2026-11-13', 'finish_until_time' => '12:25']);
    $id = $this->postJson($this->base.'/prepare-exercise', $this->payload)->assertOk()->json('data.context.multiple_choice_test.test_id');
    expect(McTest::query()->findOrFail($id)->closes_at->setTimezone('Europe/Vienna')->toIso8601String())->toBe('2026-11-13T12:25:00+01:00');
});

test('LF MC creation requires a future saved end date and does not invent one', function (?string $date): void {
    workMcFixture($this);
    $this->work->update(['finish_until_date' => $date]);
    $this->postJson($this->base.'/prepare-exercise', $this->payload)->assertUnprocessable()->assertJsonValidationErrors('mc_mode');
    expect(McTest::query()->count())->toBe(0);
})->with([null, '2026-09-30']);

test('linking an unstarted common MC test changes its start and deadline without replacing questions or links', function (): void {
    workMcFixture($this);
    $id = $this->postJson($this->base.'/prepare-exercise', $this->payload)->assertOk()->json('data.context.multiple_choice_test.test_id');
    $test = McTest::query()->findOrFail($id);
    $snapshot = $test->snapshot;
    $snapshot['start_mode'] = 'common';
    $snapshot['closes_at'] = null;
    $test->update(['start_mode' => 'common', 'closes_at' => null, 'snapshot' => $snapshot]);
    $attempts = $test->attempts()->get()->toArray();
    $this->postJson($this->base.'/prepare-exercise', [...$this->payload, 'mc_mode' => 'existing', 'test_id' => $id,
        'expected_test_id' => $id, 'request_id' => 'e9b85247-dc5e-4e97-a281-5c57970eb739'])->assertOk();
    expect($test->fresh()->start_mode)->toBe('individual')
        ->and($test->fresh()->snapshot['start_mode'])->toBe('individual')
        ->and($test->fresh()->snapshot['questions'])->toBe($snapshot['questions'])
        ->and($test->attempts()->get()->toArray())->toBe($attempts);
});

test('linking cannot change a common MC test whose countdown or personal attempt already started', function (bool $countdown): void {
    workMcFixture($this);
    $id = $this->postJson($this->base.'/prepare-exercise', $this->payload)->assertOk()->json('data.context.multiple_choice_test.test_id');
    $test = McTest::query()->findOrFail($id);
    $test->update(['start_mode' => 'common', 'closes_at' => null, 'starts_at' => $countdown ? now()->addMinute() : null]);
    if (! $countdown) {
        $test->attempts()->first()->update(['started_at' => now()]);
    }
    $before = $test->fresh()->getAttributes();
    $this->postJson($this->base.'/prepare-exercise', [...$this->payload, 'mc_mode' => 'existing', 'test_id' => $id,
        'expected_test_id' => $id, 'request_id' => '29bc5361-d0dc-4fe8-9988-598062327c0e'])->assertUnprocessable()->assertJsonValidationErrors('mc_mode');
    expect($test->fresh()->getAttributes())->toBe($before);
})->with([true, false]);

test('new LF MC tests use the saved performance date work title class and course group', function (string $workTitle, string $courseTitle, array $classes, string $expectedTitle): void {
    workMcFixture($this);
    $this->course->update(['title' => $courseTitle, 'classes' => $classes]);
    $this->work->update(['title' => $workTitle]);

    $response = $this->postJson($this->base.'/prepare-exercise', $this->payload)->assertOk()
        ->assertJsonPath('data.context.multiple_choice_test.title', $expectedTitle);

    expect(McTest::query()->findOrFail($response->json('data.context.multiple_choice_test.test_id'))->title)->toBe($expectedTitle);
})->with([
    'user naming pattern' => ['Informatik', 'INF - 5A - 1', ['5A'], '2026-10-20_Informatik_5A_Gruppe-1'],
    'work title and second course group' => ['Dateimanagement', 'DGB - 3B - 2', ['3B'], '2026-10-20_Dateimanagement_3B_Gruppe-2'],
    'no course group' => ['Informatik', 'Informatik', ['5A'], '2026-10-20_Informatik_5A'],
    'joint classes without invented group' => ['Informatik', 'Informatik', ['5A', '5B'], '2026-10-20_Informatik_5A-5B'],
    'multiword title' => ['Dateien und Ordner', 'DGB - 3B - 1', ['3B'], '2026-10-20_Dateien-und-Ordner_3B_Gruppe-1'],
]);

test('the MC name follows the selected work group date instead of a deadline or creation date', function (): void {
    workMcFixture($this);
    $this->course->update(['title' => 'Informatik - 5A - 1', 'classes' => ['5A']]);
    $this->work->update(['title' => 'Dateimanagement', 'date_for_all_groups' => null, 'finish_until_date' => '2026-11-20',
        'groups' => [['date' => '2026-11-13', 'student_ids' => [$this->student->id]]]]);

    $this->postJson($this->base.'/prepare-exercise', [...$this->payload, 'date' => '2026-11-13'])->assertOk()
        ->assertJsonPath('data.context.multiple_choice_test.title', '2026-11-13_Dateimanagement_5A_Gruppe-1');
});

test('an existing MC test is reused without a second test or changing its name or attempts', function (): void {
    workMcFixture($this);
    $id = $this->postJson($this->base.'/prepare-exercise', $this->payload)->assertOk()->json('data.context.multiple_choice_test.test_id');
    McTest::query()->findOrFail($id)->update(['title' => 'Eigener Testname']);
    $attempts = McTest::query()->findOrFail($id)->attempts()->get()->toArray();
    $this->postJson($this->base.'/prepare-exercise', ['mc_mode' => 'existing', 'mc_points' => 2, 'expected_maximum_points' => 5, 'test_id' => $id, 'expected_test_id' => $id,
        'date' => '2026-10-20', 'request_id' => 'a4c4b2fd-94de-4830-9bdf-c1db638ac17f'])->assertOk()
        ->assertJsonPath('data.context.multiple_choice_test.test_id', $id)
        ->assertJsonPath('data.context.multiple_choice_test.title', 'Eigener Testname');
    expect(McTest::query()->count())->toBe(1)->and(McTest::query()->findOrFail($id)->attempts()->get()->toArray())->toBe($attempts);
    $this->postJson($this->base.'/prepare-exercise', [...$this->payload, 'request_id' => '571bbef6-a38e-4bb0-8c8c-892eb9361fa5'])
        ->assertConflict();
    expect(McTest::query()->count())->toBe(1);
});

test('a removed linked test can be replaced using the actual binding from the refreshed choices', function (): void {
    workMcFixture($this);
    $oldId = $this->postJson($this->base.'/prepare-exercise', $this->payload)->assertOk()->json('data.context.multiple_choice_test.test_id');
    $oldTest = McTest::query()->findOrFail($oldId);
    $oldTest->attempts()->delete();
    $oldTest->delete();

    $options = $this->getJson($this->base.'/exercise-options?date=2026-10-20')->assertOk()
        ->assertJsonPath('linked_test_id', null)
        ->assertJsonPath('expected_test_id', $oldId);
    $payload = [...$this->payload, 'request_id' => '81d80352-d86c-4872-a14f-e8c6d5be8f5c'];
    $this->postJson($this->base.'/prepare-exercise', $payload)->assertConflict();
    $response = $this->postJson($this->base.'/prepare-exercise', [...$payload,
        'expected_test_id' => $options->json('expected_test_id')])->assertOk();

    expect($response->json('data.context.multiple_choice_test.test_id'))->not->toBe($oldId);
    expect(McTest::query()->count())->toBe(1);
});

test('a numeric expected binding from JSON is compared by ID without weakening stale binding protection', function (): void {
    workMcFixture($this);
    $id = $this->postJson($this->base.'/prepare-exercise', $this->payload)->assertOk()->json('data.context.multiple_choice_test.test_id');

    $this->postJson($this->base.'/prepare-exercise', [...$this->payload, 'mc_mode' => 'existing', 'test_id' => $id,
        'expected_test_id' => (string) $id, 'request_id' => '5ae82094-4653-4d5b-b63f-c0b89d0df3a7'])->assertOk()
        ->assertJsonPath('data.context.multiple_choice_test.test_id', $id);
    $this->postJson($this->base.'/prepare-exercise', [...$this->payload, 'expected_test_id' => (string) ($id + 1),
        'request_id' => 'c2d40bfb-0886-4b70-bf56-c8b922e2681a'])->assertConflict();
    expect(McTest::query()->count())->toBe(1);
});

test('existing test choices identify their catalog even when it can no longer create a new test', function (): void {
    workMcFixture($this);
    $id = $this->postJson($this->base.'/prepare-exercise', $this->payload)->assertOk()->json('data.context.multiple_choice_test.test_id');
    $this->catalog->update(['questions' => []]);
    $this->getJson($this->base.'/exercise-options')->assertOk()
        ->assertJsonCount(0, 'catalogs')
        ->assertJsonPath('tests.0.id', $id)
        ->assertJsonPath('tests.0.catalog_id', $this->catalog->id)
        ->assertJsonPath('tests.0.catalog_title', 'Geprüfte Grundlagen')
        ->assertJsonMissingPath('tests.0.questions')
        ->assertJsonMissingPath('tests.0.snapshot');
    $other = User::factory()->create(['school_id' => $this->teacher->school_id, 'schoolyear_id' => $this->teacher->schoolyear_id]);
    $this->catalog->update(['owner_id' => $other->id]);
    $this->getJson($this->base.'/exercise-options')->assertOk()->assertJsonPath('tests.0.catalog_title', null);
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
    $this->postJson($this->base.'/prepare-exercise', ['mc_mode' => 'existing', 'mc_points' => 2, 'expected_maximum_points' => 5, 'test_id' => $id, 'expected_test_id' => $id,
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
    $this->postJson($this->base.'/prepare-exercise', ['mc_mode' => 'existing', 'mc_points' => 2, 'expected_maximum_points' => 5, 'test_id' => $id, 'expected_test_id' => $id,
        'date' => '2026-10-20', 'request_id' => 'a4c4b2fd-94de-4830-9bdf-c1db638ac17f'])->assertNotFound();
})->with(['owner', 'year']);

test('MC allocation must fit the saved LF points and never creates an orphan test on failure', function (mixed $points): void {
    workMcFixture($this);
    $this->postJson($this->base.'/prepare-exercise', [...$this->payload, 'mc_points' => $points])->assertUnprocessable()
        ->assertJsonValidationErrors('mc_points');
    expect(McTest::query()->count())->toBe(0)->and($this->work->fresh()->status)->not->toHaveKey('multiple_choice_tests');
})->with([null, 0, -1, 5.01]);

test('MC creation records an explicit conversion budget without increasing the LF maximum', function (): void {
    workMcFixture($this);
    $this->postJson($this->base.'/prepare-exercise', [...$this->payload, 'total_points' => 10])->assertOk()
        ->assertJsonPath('data.context.multiple_choice_test.points_plan.mc_points', 2)
        ->assertJsonPath('data.context.multiple_choice_test.points_plan.other_points', 3)
        ->assertJsonPath('data.context.multiple_choice_test.points_plan.test_maximum_points', 10);
    expect($this->work->fresh()->status['multiple_choice_tests']['2026-10-20']['points_plan']['maximum_points'])->toEqual(5);
});

test('assessment points can differ from the saved Schooltool scale without changing it', function (): void {
    workMcFixture($this);
    $this->course->teachingEntryArea->entryDefinitions()->update(['maximum_points' => 15]);
    $payload = [...$this->payload, 'mc_points' => 5, 'total_points' => 5, 'other_points' => 30, 'expected_maximum_points' => 15];

    $response = $this->postJson($this->base.'/prepare-exercise', $payload)->assertOk()
        ->assertJsonPath('data.context.multiple_choice_test.points_plan.maximum_points', 35)
        ->assertJsonPath('data.context.multiple_choice_test.points_plan.mc_points', 5)
        ->assertJsonPath('data.context.multiple_choice_test.points_plan.other_points', 30)
        ->assertJsonPath('data.context.multiple_choice_test.points_plan.schooltool_maximum_points', 15);
    expect($response->json('data.text'))->toContain('Umrechnung auf die gespeicherte Schooltool-Bewertungsskala vor der Übernahme mit der Lehrperson abstimmen');
    expect($this->course->teachingEntryArea->entryDefinitions()->first()->maximum_points)->toEqual(15);
    $this->getJson($this->base.'/exercise-options?date=2026-10-20')->assertOk()
        ->assertJsonPath('linked_other_points', 30);
});

test('invalid remaining points do not create a test', function (mixed $otherPoints): void {
    workMcFixture($this);

    $this->postJson($this->base.'/prepare-exercise', [...$this->payload, 'other_points' => $otherPoints])
        ->assertUnprocessable()->assertJsonValidationErrors('other_points');
    expect(McTest::query()->count())->toBe(0);
})->with([-1, '2.345', 100000]);

test('changed LF totals invalidate an open MC allocation before creating a test', function (): void {
    workMcFixture($this);
    TeachingEntryDefinition::query()->where('teaching_entry_area_id', $this->course->teaching_entry_area_id)->update(['maximum_points' => 4]);
    $this->postJson($this->base.'/prepare-exercise', $this->payload)->assertConflict();
    expect(McTest::query()->count())->toBe(0);
});

test('a confirmed participant dispatch blocks MC additions even after an earlier unlocked preview', function (string $mode): void {
    workMcFixture($this);
    app()->instance('env', 'local');
    $this->getJson($this->base.'/exercise-options')->assertOk()->assertJsonPath('configuration.locked', false);
    $hash = str_repeat('a', 64);
    $record = ['student_id' => $this->student->id, 'purpose' => 'tasks', 'origin' => 'dispatch_import',
        'log_sha256' => $hash, 'sent_at' => '2026-10-19T10:00:00Z', 'provider_id' => 'confirmed-1', 'mode' => $mode];
    $status = ['automation_enabled' => true, 'dispatch_logs' => [['origin' => 'dispatch_import', 'purpose' => 'tasks', 'sha256' => $hash]],
        ($mode === 'live' ? 'dispatch_notifications' : 'dispatch_attempts') => [$record]];
    $this->work->update(['status' => $status]);
    $status = $this->work->fresh()->status;
    $this->getJson($this->base.'/exercise-options')->assertOk()->assertJsonPath('configuration.locked', true);
    $this->postJson($this->base.'/prepare-exercise', $this->payload)->assertUnprocessable()->assertJsonValidationErrors('mc_mode');
    expect(McTest::query()->count())->toBe(0)->and($this->work->fresh()->status)->toBe($status);
    $this->getJson($this->base.'/ai-export?purpose=exercise&include_mc=1&date=2026-10-20')->assertUnprocessable();
})->with(['live', 'mailpit']);

test('MC mutation rechecks Dropbox dispatch and fails closed for partial or unclear delivery', function (string $dispatchStatus): void {
    workMcFixture($this);
    $connection = DropboxConnection::factory()->create(['user_id' => $this->teacher->id]);
    TeachingWorkModuleLink::query()->create(['dropbox_connection_id' => $connection->id,
        'teaching_course_work_id' => $this->work->id, 'module_id' => 'a263b873-4f27-4167-90f0-7d94ea0f67fa', 'schema_version' => 1,
        'account_id' => $connection->account_id, 'folder_id' => 'id:module', 'folder_name' => 'Modul',
        'folder_path' => '/Materialien/Lerninhalte/Modul', 'handshake_file_id' => 'id:handshake', 'handshake_revision' => 'r1']);
    $this->partialMock(DropboxModuleLinks::class)->shouldReceive('exercise')->once()
        ->withArgs(fn ($checkedConnection, $checkedWork, $link, $date, $refresh, $initial): bool => $checkedConnection->id === $connection->id && $checkedWork->id === $this->work->id
            && $date === '2026-10-20' && $refresh === true && $initial === false)
        ->andReturn(['status' => 'found', 'folder' => ['id' => 'id:lf'],
            'email_dispatch' => ['status' => $dispatchStatus, 'confirmed_count' => $dispatchStatus === 'partial' ? 1 : 0]]);
    $this->postJson($this->base.'/prepare-exercise', $this->payload)->assertUnprocessable()->assertJsonValidationErrors('mc_mode');
    expect(McTest::query()->count())->toBe(0)->and($this->work->fresh()->status)->not->toHaveKey('multiple_choice_tests');
    Http::assertNothingSent();
})->with(['partial', 'unknown']);
