<?php

use App\Models\McAttempt;
use App\Models\McCatalog;
use App\Models\McTest;
use App\Models\School;
use App\Models\Schoolyear;
use App\Models\TeachingCourse;
use App\Models\TeachingCourseStudentEntry;
use App\Models\TeachingCourseWork;
use App\Models\User;
use App\Services\MultipleChoice\McTestExchange;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

require_once dirname(__DIR__).'/Support/MultipleChoiceTestDatabase.php';
requireMultipleChoiceTestDatabase();

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Http::preventStrayRequests();
    Storage::fake('local');
    Role::findOrCreate('teacher', 'web');
    $this->school = School::factory()->create();
    $this->year = Schoolyear::factory()->create(['school_id' => $this->school->id]);
    enableSchoolToolModuleForTests($this->school, 'teaching');
    grantSchoolToolLicenceForTests($this->school, 'Lehrertool');
    $this->teacher = User::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->year->id])->assignRole('teacher');
    $this->course = TeachingCourse::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->year->id, 'user_id' => $this->teacher->id]);
    $this->work = TeachingCourseWork::factory()->create(['teaching_course_id' => $this->course->id, 'status' => []]);
    $this->url = '/api/admin/teaching/course_works/'.$this->work->id;
    $this->actingAs($this->teacher, 'sanctum');
});

function deletionMcFixture(object $context): McTest
{
    $catalog = McCatalog::query()->create(['school_id' => $context->school->id, 'owner_id' => $context->teacher->id, 'title' => 'Gemeinsam', 'questions' => []]);
    $test = McTest::query()->create(['school_id' => $context->school->id, 'schoolyear_id' => $context->year->id,
        'owner_id' => $context->teacher->id, 'catalog_id' => $catalog->id, 'title' => 'Test',
        'status' => 'published', 'question_count' => 1, 'total_points' => 1, 'duration_seconds' => 300]);
    $student = User::factory()->create(['school_id' => $context->school->id, 'schoolyear_id' => $context->year->id]);
    McAttempt::query()->create(['test_id' => $test->id, 'student_id' => $student->id, 'student_name' => 'Test',
        'email' => $student->email, 'questions' => [], 'answers' => [], 'access_token' => 'token', 'access_token_hash' => hash('sha256', 'token')]);
    $context->work->update(['status' => ['multiple_choice_tests' => ['all' => ['work_id' => $context->work->id, 'test_id' => $test->id]]]]);

    return $test;
}

test('deleting a work removes its linked test and attempts but keeps its catalog', function (): void {
    $test = deletionMcFixture($this);
    $entry = TeachingCourseStudentEntry::query()->create(['teaching_course_id' => $this->course->id,
        'teaching_course_work_id' => $this->work->id, 'source' => 'course_work']);
    $bindingPath = "multiple-choice/test-folders/{$this->school->id}/{$this->teacher->id}/{$test->id}.json";
    $identity = app(McTestExchange::class)->identity($test);
    Storage::disk('local')->put($bindingPath, json_encode(['identity' => $identity, 'provider' => 'local']));
    $historyPath = "multiple-choice/exchange-history/{$this->school->id}/{$this->teacher->id}/{$test->id}/b10ca8e3-181f-48b7-b842-01a16c855ae8.json.enc";
    $otherPath = "multiple-choice/exchange-history/{$this->school->id}/{$this->teacher->id}/99999/b10ca8e3-181f-48b7-b842-01a16c855ae8.json.enc";
    Storage::disk('local')->put($historyPath, encrypt(['actor' => $this->teacher->id, 'package' => ['identity' => $identity]]));
    Storage::disk('local')->put($otherPath, 'other test');
    $this->deleteJson($this->url)->assertNoContent();
    $this->assertDatabaseMissing('teaching_course_works', ['id' => $this->work->id]);
    $this->assertDatabaseMissing('mc_tests', ['id' => $test->id]);
    $this->assertDatabaseMissing('mc_attempts', ['test_id' => $test->id]);
    $this->assertDatabaseMissing('teaching_course_student_entries', ['id' => $entry->id]);
    $this->assertDatabaseHas('mc_catalogs', ['id' => $test->catalog_id]);
    Storage::disk('local')->assertMissing($bindingPath);
    Storage::disk('local')->assertMissing($historyPath);
    Storage::disk('local')->assertExists($otherPath);
});

test('an unverified test history file stops the deletion and remains intact', function (): void {
    $test = deletionMcFixture($this);
    $path = "multiple-choice/exchange-history/{$this->school->id}/{$this->teacher->id}/{$test->id}/b10ca8e3-181f-48b7-b842-01a16c855ae8.json.enc";
    Storage::disk('local')->put($path, encrypt(['actor' => $this->teacher->id, 'package' => ['identity' => 'foreign']]));
    $this->deleteJson($this->url)->assertStatus(409);
    $this->assertDatabaseHas('teaching_course_works', ['id' => $this->work->id]);
    $this->assertDatabaseHas('mc_tests', ['id' => $test->id]);
    Storage::disk('local')->assertExists($path);
});

test('an unverified local test folder binding stops work deletion', function (): void {
    $test = deletionMcFixture($this);
    $path = "multiple-choice/test-folders/{$this->school->id}/{$this->teacher->id}/{$test->id}.json";
    Storage::disk('local')->put($path, json_encode(['identity' => 'different', 'provider' => 'local']));
    $this->deleteJson($this->url)->assertStatus(409);
    $this->assertDatabaseHas('teaching_course_works', ['id' => $this->work->id]);
    $this->assertDatabaseHas('mc_tests', ['id' => $test->id]);
    Storage::disk('local')->assertExists($path);
});

test('shared and ambiguous MC bindings leave the work and test untouched', function (string $mode): void {
    $test = deletionMcFixture($this);
    if ($mode === 'shared') {
        TeachingCourseWork::factory()->create(['teaching_course_id' => $this->course->id,
            'status' => ['multiple_choice_tests' => ['all' => ['work_id' => 999, 'test_id' => (string) $test->id]]]]);
    } else {
        $this->work->update(['status' => ['multiple_choice_tests' => ['all' => ['work_id' => 999, 'test_id' => $test->id]]]]);
    }
    $this->deleteJson($this->url)->assertStatus(409);
    $this->assertDatabaseHas('teaching_course_works', ['id' => $this->work->id]);
    $this->assertDatabaseHas('mc_tests', ['id' => $test->id]);
    $this->assertDatabaseHas('mc_attempts', ['test_id' => $test->id]);
})->with(['shared', 'ambiguous']);

test('a missing old MC test does not prevent work deletion', function (): void {
    $this->work->update(['status' => ['multiple_choice_tests' => ['all' => ['work_id' => $this->work->id, 'test_id' => 999999]]]]);
    $this->deleteJson($this->url)->assertNoContent();
});

test('an imported assessment with no folder link requires clarification', function (): void {
    $test = deletionMcFixture($this);
    $status = $this->work->fresh()->status;
    $status['assessment_json_packages'] = [['exercise_id' => 'known-assessment']];
    $this->work->update(['status' => $status]);
    $this->deleteJson($this->url)->assertStatus(409);
    $this->assertDatabaseHas('teaching_course_works', ['id' => $this->work->id]);
    $this->assertDatabaseHas('mc_tests', ['id' => $test->id]);
});

test('a different teacher cannot delete the work or test', function (): void {
    $test = deletionMcFixture($this);
    $other = User::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->year->id])->assignRole('teacher');
    $this->actingAs($other, 'sanctum')->deleteJson($this->url)->assertForbidden();
    $this->assertDatabaseHas('teaching_course_works', ['id' => $this->work->id]);
    $this->assertDatabaseHas('mc_tests', ['id' => $test->id]);
});
