<?php

use App\Models\School;
use App\Models\SchoolTool;
use App\Models\Schoolyear;
use App\Models\TeachingCourse;
use App\Models\TeachingExplanationShare;
use App\Models\User;
use App\Models\UserGroup;
use App\Models\UserGroupMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

require_once dirname(__DIR__).'/Support/MultipleChoiceTestDatabase.php';
requireMultipleChoiceTestDatabase();
uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['app.key' => 'base64:'.base64_encode(str_repeat('e', 32)), 'schooltool.preview.instance' => false]);
    Role::findOrCreate('teacher', 'web');
    Role::findOrCreate('student', 'web');
    $this->school = School::factory()->create();
    $this->year = Schoolyear::factory()->create(['school_id' => $this->school->id]);
    SchoolTool::factory()->create(['school_id' => $this->school->id, 'teaching_visible_admin' => true, 'teaching_visible_user' => true]);
    grantSchoolToolLicenceForTests($this->school, 'Lehrertool');
    $this->teacher = User::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->year->id, 'is_active' => true]);
    $this->teacher->assignRole('teacher');
    $this->base = '/api/admin/teaching-explanations/decimal-to-binary';
});

function explanationAs(TestCase $test, ?User $user): void
{
    app('auth')->forgetGuards();
    auth()->shouldUse('web');
    if ($user) {
        $test->actingAs($user, 'web');
    } else {
        auth()->guard('web')->logout();
    }
}

test('unpublished explanations and invalid links do not deliver the student view', function (): void {
    $share = TeachingExplanationShare::factory()->create(['school_id' => $this->school->id, 'user_id' => $this->teacher->id]);
    $this->get('/explanations/'.$share->token)->assertNotFound();
    $this->getJson('/api/explanations/'.$share->token)->assertNotFound();
    $this->get('/explanations/'.str_repeat('x', 48))->assertNotFound();
    explanationAs($this, $this->teacher);
    $this->getJson($this->base)->assertJsonPath('published', false)->assertJsonPath('invitation', null);
});

test('public publication is reachable without login and revocation invalidates the old link', function (): void {
    explanationAs($this, $this->teacher);
    $published = $this->postJson($this->base.'/publication', ['access_mode' => 'public'])->assertOk()->assertJsonPath('published', true)->assertJsonPath('access_mode', 'public');
    $url = $published->json('invitation.url');
    expect($published->json('invitation.qr_data_url'))->toStartWith('data:image/svg+xml;base64,');
    $this->assertDatabaseHas('teaching_explanation_shares', ['user_id' => $this->teacher->id, 'access_mode' => 'public', 'is_active' => true]);
    explanationAs($this, null);
    $this->get($url)->assertOk()->assertViewIs('homepage');
    $this->getJson('/api/explanations/'.basename($url))->assertOk()->assertJsonPath('explanation', 'decimal-to-binary');
    explanationAs($this, $this->teacher);
    $this->deleteJson($this->base.'/publication')->assertOk()->assertJsonPath('published', false);
    explanationAs($this, null);
    $this->get($url)->assertNotFound();
    $this->getJson('/api/explanations/'.basename($url))->assertNotFound();
    explanationAs($this, $this->teacher);
    $newUrl = $this->postJson($this->base.'/publication', ['access_mode' => 'public'])->assertOk()->json('invitation.url');
    expect($newUrl)->not->toBe($url);
    $this->get($url)->assertNotFound();
});

test('network fundamentals can be shared and revoked using the existing access rules', function (): void {
    explanationAs($this, $this->teacher);
    $url = $this->postJson('/api/admin/teaching-explanations/network-fundamentals/publication', ['access_mode' => 'public'])
        ->assertOk()->assertJsonPath('published', true)->json('invitation.url');

    explanationAs($this, null);
    $this->getJson('/api/explanations/'.basename($url))->assertOk()->assertJsonPath('explanation', 'network-fundamentals');

    explanationAs($this, $this->teacher);
    $this->deleteJson('/api/admin/teaching-explanations/network-fundamentals/publication')->assertOk()->assertJsonPath('published', false);
    explanationAs($this, null);
    $this->getJson('/api/explanations/'.basename($url))->assertNotFound();
});

test('login-required publication rejects guests and inactive accounts but admits an active Schooltool account', function (): void {
    explanationAs($this, $this->teacher);
    $url = $this->postJson($this->base.'/publication', ['access_mode' => 'authenticated'])->assertOk()->json('invitation.url');
    explanationAs($this, null);
    $this->get($url)->assertUnauthorized()->assertSee('Schooltool-Anmeldung erforderlich');
    $this->getJson('/api/explanations/'.basename($url))->assertUnauthorized();
    $student = User::factory()->create(['school_id' => $this->school->id, 'is_active' => true]);
    $student->assignRole('student');
    explanationAs($this, $student);
    $this->get($url)->assertOk()->assertViewIs('homepage');
    $this->getJson('/api/explanations/'.basename($url))->assertOk();
    $student->forceFill(['is_active' => false])->save();
    explanationAs($this, $student->fresh());
    $this->get($url)->assertUnauthorized();
});

test('changing access mode invalidates the previous public link', function (): void {
    explanationAs($this, $this->teacher);
    $url = $this->postJson($this->base.'/publication', ['access_mode' => 'public'])->assertOk()->json('invitation.url');
    $newUrl = $this->postJson($this->base.'/publication', ['access_mode' => 'authenticated'])->assertOk()->json('invitation.url');
    explanationAs($this, null);
    $this->get($url)->assertNotFound();
    $this->get($newUrl)->assertUnauthorized();
});

test('management requires teaching rights and scopes publication to its owner and school', function (): void {
    $this->postJson($this->base.'/publication', ['access_mode' => 'public'])->assertUnauthorized();
    $student = User::factory()->create(['school_id' => $this->school->id, 'is_active' => true]);
    $student->assignRole('student');
    explanationAs($this, $student);
    $this->postJson($this->base.'/publication', ['access_mode' => 'public'])->assertForbidden();
    explanationAs($this, $this->teacher);
    $this->postJson($this->base.'/publication', ['access_mode' => 'invalid'])->assertUnprocessable();
    $this->assertDatabaseCount('teaching_explanation_shares', 0);
    $this->postJson($this->base.'/publication', ['access_mode' => 'public'])->assertOk();
    $otherTeacher = User::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->year->id, 'is_active' => true]);
    $otherTeacher->assignRole('teacher');
    explanationAs($this, $otherTeacher);
    $this->getJson($this->base)->assertOk()->assertJsonPath('published', false);
    $this->deleteJson($this->base.'/publication')->assertOk();
    $this->assertDatabaseHas('teaching_explanation_shares', ['user_id' => $this->teacher->id, 'is_active' => true]);
});

test('email preparation uses only owned audiences, does not send and does not change course data', function (): void {
    Mail::fake();
    $course = TeachingCourse::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->year->id, 'user_id' => $this->teacher->id]);
    $student = User::factory()->create(['school_id' => $this->school->id, 'is_active' => true]);
    $course->teachingCourseStudents()->create(['user_id' => $student->id]);
    $group = UserGroup::query()->create(['school_id' => $this->school->id, 'type' => 'own', 'name' => 'Eigene Gruppe', 'created_by_user_id' => $this->teacher->id]);
    UserGroupMember::query()->create(['school_id' => $this->school->id, 'user_group_id' => $group->id, 'linked_user_id' => $student->id, 'member_provider' => 'user', 'member_ref' => (string) $student->id, 'display_name' => 'Kontakt', 'display_email' => $student->email, 'source_status' => 'active', 'linked_user_status' => 'linked', 'added_by_user_id' => $this->teacher->id]);
    explanationAs($this, $this->teacher);
    $this->postJson($this->base.'/email-preview', ['audience_type' => 'course', 'audience_id' => $course->id])->assertNotFound();
    $this->postJson($this->base.'/publication', ['access_mode' => 'public'])->assertOk();
    foreach ([['course', $course->id], ['group', $group->id]] as [$type, $id]) {
        $this->postJson($this->base.'/email-preview', ['audience_type' => $type, 'audience_id' => $id])->assertOk()
            ->assertJsonPath('sent', false)->assertJsonPath('recipients.0.email', $student->email)->assertJsonCount(1, 'recipients');
    }
    $outsider = User::factory()->create(['school_id' => $this->school->id, 'is_active' => true]);
    $foreignCourse = TeachingCourse::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->year->id, 'user_id' => $outsider->id]);
    $foreignGroup = UserGroup::query()->create(['school_id' => $this->school->id, 'type' => 'own', 'name' => 'Andere Gruppe', 'created_by_user_id' => $outsider->id]);
    foreach ([['course', $foreignCourse->id], ['group', $foreignGroup->id]] as [$type, $id]) {
        $this->postJson($this->base.'/email-preview', ['audience_type' => $type, 'audience_id' => $id])->assertNotFound();
    }
    $this->assertDatabaseCount('teaching_course_students', 1);
    Mail::assertNothingSent();
});
