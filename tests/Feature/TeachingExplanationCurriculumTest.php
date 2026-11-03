<?php

use App\Models\School;
use App\Models\SchoolTool;
use App\Models\Schoolyear;
use App\Models\TeachingCourse;
use App\Models\TeachingCourseDate;
use App\Models\TeachingCurriculum;
use App\Models\TeachingCurriculumDocument;
use App\Models\TeachingExplanationShare;
use App\Models\User;
use App\Services\Teaching\TeachingExplanationCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

require_once dirname(__DIR__).'/Support/MultipleChoiceTestDatabase.php';
requireMultipleChoiceTestDatabase();
uses(RefreshDatabase::class);

function curriculumExplanationAs(TestCase $test, ?User $user): void
{
    app('auth')->forgetGuards();
    auth()->shouldUse('web');
    if ($user) {
        $test->actingAs($user, 'web');
    } else {
        auth()->guard('web')->logout();
    }
}

beforeEach(function (): void {
    Role::findOrCreate('teacher', 'web');
    Role::findOrCreate('student', 'web');
    $this->school = School::factory()->create();
    $this->year = Schoolyear::factory()->create(['school_id' => $this->school->id]);
    SchoolTool::factory()->create(['school_id' => $this->school->id, 'active_schoolyear_id' => $this->year->id, 'teaching_visible_admin' => true, 'teaching_visible_user' => true]);
    grantSchoolToolLicenceForTests($this->school, 'Lehrertool');
    $this->teacher = User::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->year->id, 'is_active' => true]);
    $this->teacher->assignRole('teacher');
    $this->student = User::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->year->id, 'is_active' => true]);
    $this->student->assignRole('student');
    $this->curriculum = TeachingCurriculum::create([
        'school_id' => $this->school->id, 'schoolyear_id' => $this->year->id, 'user_id' => $this->teacher->id, 'title' => 'Informatik',
        'topics' => [['id' => 'numbers', 'title' => 'Zahlen', 'units' => [['id' => 'dual', 'title' => 'Dualzahlen'], ['id' => 'practice', 'title' => 'Übung']]]],
    ]);
    $this->course = TeachingCourse::factory()->create([
        'school_id' => $this->school->id, 'schoolyear_id' => $this->year->id, 'user_id' => $this->teacher->id,
        'teaching_curriculum_id' => $this->curriculum->id, 'students' => [$this->student->id],
    ]);
    $this->date = TeachingCourseDate::create(['teaching_course_id' => $this->course->id, 'date' => now()->toDateString(), 'hours' => [1]]);
    $this->unitEndpoint = "/api/admin/teaching/curricula/{$this->curriculum->id}/topics/numbers/units/dual";
    curriculumExplanationAs($this, $this->teacher);
    $this->file = TeachingCurriculumDocument::findOrFail($this->postJson($this->unitEndpoint.'/explanations', ['explanation_key' => 'decimal-to-binary'])->assertCreated()->json('data.id'));
    $this->visibilityUrl = "/api/admin/teaching/course_dates/{$this->date->id}/curriculum-files/{$this->file->id}/visibility";
    $this->publicationUrl = '/api/admin/teaching-explanations/decimal-to-binary/publication';
});

test('curriculum assignments store stable registered keys and have distinct protected links', function (): void {
    $this->getJson($this->unitEndpoint.'/files')->assertOk()->assertJsonPath('explanations.0.category', 'Zahlensysteme')->assertJsonPath('data.0.explanation_key', 'decimal-to-binary')->assertJsonPath('data.0.download_url', null);
    $firstUrl = route('curriculum-explanations.show', $this->file, false);
    $second = $this->postJson(str_replace('/dual', '/practice', $this->unitEndpoint).'/explanations', ['explanation_key' => 'decimal-to-binary'])->assertCreated();
    expect($second->json('data.preview_url'))->not->toBe($firstUrl)
        ->and($this->file->file_path)->toBeNull()->and(TeachingExplanationShare::count())->toBe(0);
    $this->get($firstUrl)->assertOk()->assertViewIs('homepage');
    $this->getJson($firstUrl)->assertOk()->assertJsonPath('explanation', 'decimal-to-binary')->assertJsonPath('return_url', '/admin/teaching/curricula?curriculum='.$this->curriculum->id);
    $this->postJson($this->unitEndpoint.'/explanations', ['explanation_key' => 'unknown'])->assertUnprocessable();
    $this->postJson(str_replace('/dual', '/missing', $this->unitEndpoint).'/explanations', ['explanation_key' => 'decimal-to-binary'])->assertNotFound();
    $this->getJson('/api/admin/teaching/curricula/'.$this->curriculum->id)->assertOk()->assertJsonPath('data.unit_file_counts.numbers.dual', 1);
    $repeat = $this->postJson($this->unitEndpoint.'/explanations', ['explanation_key' => 'decimal-to-binary'])->assertCreated();
    expect($repeat->json('data.id'))->not->toBe($this->file->id)
        ->and($repeat->json('data.preview_url'))->not->toBe($firstUrl);
    curriculumExplanationAs($this, $this->student);
    $this->getJson($firstUrl)->assertForbidden();
    curriculumExplanationAs($this, null);
    $this->getJson($firstUrl)->assertUnauthorized();
});

test('explanation choices exclude assignments throughout this curriculum and return after the last removal', function (): void {
    $otherUnitEndpoint = str_replace('/dual', '/practice', $this->unitEndpoint);
    $this->getJson($otherUnitEndpoint.'/files')->assertOk()
        ->assertJsonMissing(['key' => 'decimal-to-binary'])
        ->assertJsonFragment(['key' => 'decimal-to-hexadecimal']);

    $otherCurriculum = TeachingCurriculum::create([
        'school_id' => $this->school->id, 'schoolyear_id' => $this->year->id,
        'user_id' => $this->teacher->id, 'title' => 'Weiteres Curriculum',
        'topics' => $this->curriculum->topics,
    ]);
    $this->getJson("/api/admin/teaching/curricula/{$otherCurriculum->id}/topics/numbers/units/dual/files")
        ->assertOk()->assertJsonFragment(['key' => 'decimal-to-binary']);

    $duplicateId = $this->postJson($otherUnitEndpoint.'/explanations', ['explanation_key' => 'decimal-to-binary'])
        ->assertCreated()->json('data.id');
    $this->deleteJson($this->unitEndpoint.'/files/'.$this->file->id)->assertNoContent();
    $this->getJson($this->unitEndpoint.'/files')->assertOk()->assertJsonMissing(['key' => 'decimal-to-binary']);
    $this->deleteJson($otherUnitEndpoint.'/files/'.$duplicateId)->assertNoContent();
    $this->getJson($this->unitEndpoint.'/files')->assertOk()->assertJsonFragment(['key' => 'decimal-to-binary']);
});

test('new explanations appear at the bottom in the dialog and reloaded curriculum', function (): void {
    $secondId = $this->postJson($this->unitEndpoint.'/explanations', ['explanation_key' => 'decimal-to-hexadecimal'])
        ->assertCreated()->json('data.id');
    $thirdId = $this->postJson($this->unitEndpoint.'/explanations', ['explanation_key' => 'binary-to-decimal'])
        ->assertCreated()->json('data.id');

    $this->getJson($this->unitEndpoint.'/files')->assertOk()
        ->assertJsonPath('data.0.id', $this->file->id)
        ->assertJsonPath('data.1.id', $secondId)
        ->assertJsonPath('data.2.id', $thirdId);
    $this->getJson('/api/admin/teaching/curricula/'.$this->curriculum->id)->assertOk()
        ->assertJsonPath('data.unit_files.numbers.dual.0.id', $this->file->id)
        ->assertJsonPath('data.unit_files.numbers.dual.1.id', $secondId)
        ->assertJsonPath('data.unit_files.numbers.dual.2.id', $thirdId);
});

test('visible teaching material works without a publication and stays accessible after share revocation', function (): void {
    $this->putJson($this->visibilityUrl, ['student_visible' => true])->assertOk()->assertJsonPath('data.shared_curriculum_attachments_count', 1);
    $attachment = $this->date->materials()->sole()->attachments()->sole();
    $url = route('student-material-explanations.show', $attachment, false);
    expect($attachment->file_path)->toBeNull()->and($attachment->explanation_key)->toBe('decimal-to-binary');
    curriculumExplanationAs($this, $this->student);
    $this->get($url)->assertOk()->assertViewIs('homepage');
    $this->getJson($url)->assertOk()->assertJsonPath('explanation', 'decimal-to-binary')->assertJsonPath('return_url', '/student/course/'.$this->course->id);
    $this->getJson('/api/homepage/student/courses/'.$this->course->id)->assertOk()->assertJsonPath('course.course_dates.0.adopted_materials.0.attachments.0.preview_url', $url);
    curriculumExplanationAs($this, $this->teacher);
    $share = $this->postJson($this->publicationUrl, ['access_mode' => 'public'])->assertOk();
    $this->deleteJson($this->publicationUrl)->assertOk();
    $this->get($share->json('invitation.url'))->assertNotFound();
    curriculumExplanationAs($this, $this->student);
    $this->getJson($url)->assertOk();
});

test('hiding one assignment leaves another course assignment and the separate public share intact', function (): void {
    $this->putJson($this->visibilityUrl, ['student_visible' => true])->assertOk();
    $first = $this->date->materials()->sole()->attachments()->sole();
    $otherCourse = TeachingCourse::factory()->create([
        'school_id' => $this->school->id, 'schoolyear_id' => $this->year->id, 'user_id' => $this->teacher->id,
        'teaching_curriculum_id' => $this->curriculum->id, 'students' => [$this->student->id],
    ]);
    $otherDate = TeachingCourseDate::create(['teaching_course_id' => $otherCourse->id, 'date' => now()->toDateString(), 'hours' => [2]]);
    $this->putJson("/api/admin/teaching/course_dates/{$otherDate->id}/curriculum-files/{$this->file->id}/visibility", ['student_visible' => true])->assertOk();
    $second = $otherDate->materials()->sole()->attachments()->sole();
    $firstUrl = route('student-material-explanations.show', $first, false);
    $secondUrl = route('student-material-explanations.show', $second, false);
    expect($firstUrl)->not->toBe($secondUrl);
    $shareUrl = $this->postJson($this->publicationUrl, ['access_mode' => 'public'])->assertOk()->json('invitation.url');
    $this->putJson($this->visibilityUrl, ['student_visible' => false])->assertOk();
    $this->getJson(route('teaching-material-explanations.show', $first, false))->assertOk()->assertJsonPath('return_url', '/admin/teaching?course='.$this->course->id.'&panel=table');
    curriculumExplanationAs($this, $this->student);
    $this->getJson($firstUrl)->assertForbidden();
    $this->get($firstUrl)->assertForbidden();
    $this->getJson($secondUrl)->assertOk();
    $this->getJson('/api/homepage/student/courses/'.$this->course->id)->assertOk()->assertJsonCount(0, 'course.course_dates.0.adopted_materials.0.attachments');
    curriculumExplanationAs($this, null);
    $this->get($shareUrl)->assertOk();
    $this->getJson($secondUrl)->assertUnauthorized();
});

test('course explanation access enforces school active year enrollment and active student', function (string $boundary): void {
    $this->putJson($this->visibilityUrl, ['student_visible' => true])->assertOk();
    $attachment = $this->date->materials()->sole()->attachments()->sole();
    if ($boundary === 'school') {
        $this->course->update(['school_id' => School::factory()->create()->id]);
    } elseif ($boundary === 'year') {
        SchoolTool::where('school_id', $this->school->id)->update(['active_schoolyear_id' => Schoolyear::factory()->create(['school_id' => $this->school->id])->id]);
    } elseif ($boundary === 'enrollment') {
        $this->course->teachingCourseStudents()->delete();
    } elseif ($boundary === 'canceled') {
        $this->course->teachingCourseStudents()->update(['canceled_at' => now()]);
    } else {
        $this->student->forceFill(['is_active' => false])->save();
    }
    curriculumExplanationAs($this, $this->student);
    $this->getJson(route('student-material-explanations.show', $attachment, false))->assertForbidden();
})->with(['school', 'year', 'enrollment', 'canceled', 'inactive']);

test('another teacher cannot manage or open a curriculum or teaching assignment', function (): void {
    $this->putJson($this->visibilityUrl, ['student_visible' => true])->assertOk();
    $attachment = $this->date->materials()->sole()->attachments()->sole();
    $other = User::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->year->id, 'is_active' => true]);
    $other->assignRole('teacher');
    curriculumExplanationAs($this, $other);
    $this->postJson($this->unitEndpoint.'/explanations', ['explanation_key' => 'decimal-to-binary'])->assertForbidden();
    $this->putJson($this->visibilityUrl, ['student_visible' => false])->assertForbidden();
    $this->getJson(route('curriculum-explanations.show', $this->file, false))->assertForbidden();
    $this->getJson(route('teaching-material-explanations.show', $attachment, false))->assertForbidden();
});

test('hexadecimal and binary have independent publications and teaching references', function (): void {
    $hex = $this->postJson($this->unitEndpoint.'/explanations', ['explanation_key' => 'decimal-to-hexadecimal'])->assertCreated();
    $hexFile = TeachingCurriculumDocument::findOrFail($hex->json('data.id'));
    $this->putJson("/api/admin/teaching/course_dates/{$this->date->id}/curriculum-files/{$hexFile->id}/visibility", ['student_visible' => true])->assertOk();
    $attachment = $this->date->materials()->sole()->attachments()->sole();
    $binaryUrl = $this->postJson($this->publicationUrl, ['access_mode' => 'public'])->assertOk()->json('invitation.url');
    $hexPublication = '/api/admin/teaching-explanations/decimal-to-hexadecimal/publication';
    $hexUrl = $this->postJson($hexPublication, ['access_mode' => 'public'])->assertOk()->json('invitation.url');
    expect($hexUrl)->not->toBe($binaryUrl);
    $this->deleteJson($hexPublication)->assertOk();
    $this->getJson('/api/explanations/'.basename($hexUrl))->assertNotFound();
    $this->getJson('/api/explanations/'.basename($binaryUrl))->assertOk()->assertJsonPath('explanation', 'decimal-to-binary');
    curriculumExplanationAs($this, $this->student);
    $this->getJson(route('student-material-explanations.show', $attachment, false))->assertOk()->assertJsonPath('explanation', 'decimal-to-hexadecimal');
});

test('all registered explanations have separate public shares and course assignment links', function (): void {
    $explanations = TeachingExplanationCatalog::all();
    $links = [];
    $shares = [];
    foreach ($explanations as $key => $definition) {
        $file = $this->postJson($this->unitEndpoint.'/explanations', ['explanation_key' => $key])->assertCreated()->json('data.id');
        $this->putJson("/api/admin/teaching/course_dates/{$this->date->id}/curriculum-files/{$file}/visibility", ['student_visible' => true])->assertOk();
        $attachment = $this->date->materials()->sole()->attachments()->where('explanation_key', $key)->sole();
        $links[$key] = route('student-material-explanations.show', $attachment, false);
        $shares[$key] = $this->postJson('/api/admin/teaching-explanations/'.$key.'/publication', ['access_mode' => 'public'])->assertOk()->json('invitation.url');
    }
    expect(array_unique($links))->toHaveCount(count($explanations))
        ->and(array_unique($shares))->toHaveCount(count($explanations));
    $this->deleteJson('/api/admin/teaching-explanations/binary-to-hexadecimal/publication')->assertOk();
    $this->getJson('/api/explanations/'.basename($shares['binary-to-hexadecimal']))->assertNotFound();
    foreach (array_diff_key($shares, ['binary-to-hexadecimal' => true]) as $key => $url) {
        $this->getJson('/api/explanations/'.basename($url))->assertOk()->assertJsonPath('explanation', $key);
    }
    curriculumExplanationAs($this, $this->student);
    foreach ($links as $key => $url) {
        $this->getJson($url)->assertOk()->assertJsonPath('explanation', $key);
    }
});
