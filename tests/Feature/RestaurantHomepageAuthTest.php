<?php

use App\Models\Import116;
use App\Models\Licence;
use App\Models\School;
use App\Models\SchoolTool;
use App\Models\Schoolyear;
use App\Models\Teacher;
use App\Models\User;
use App\Notifications\StandardEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Notification::fake();

    $this->school = School::factory()->create([
        'short_name' => 'REST',
        'is_selectable' => true,
    ]);
    $this->schoolyear = Schoolyear::factory()->create([
        'school_id' => $this->school->id,
        'is_active' => true,
    ]);

    $this->restaurantLicence = Licence::create(['name' => 'Restaurant']);
    $this->school->licences()->attach($this->restaurantLicence->id, [
        'valid_until' => now()->addYear()->toDateString(),
    ]);

    SchoolTool::factory()->create([
        'school_id' => $this->school->id,
        'restaurant_visible_user' => true,
        'restaurant_visible_admin' => true,
        'active_schoolyear_id' => $this->schoolyear->id,
    ]);

    Role::firstOrCreate(['name' => 'lunch_user', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);
});

it('finds a direct lunch user by email', function () {
    $user = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'lunch@test.local',
        'first_name' => 'Lena',
        'last_name' => 'Lunch',
    ]);
    $user->assignRole('lunch_user');

    $this->postJson('/api/homepage/restaurant/check_email', [
        'data' => [
            'school_id' => $this->school->id,
            'email' => 'Lunch@Test.local',
        ],
    ])
        ->assertOk()
        ->assertJsonPath('status', 'USER_FOUND')
        ->assertJsonPath('match_source', 'user')
        ->assertJsonPath('matched_users_count', 1)
        ->assertJsonPath('matched_users.0.email', 'lunch@test.local');
});

it('finds lunch users through import116 parent email', function () {
    $lunchUser = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'student@test.local',
        'first_name' => 'Susi',
        'last_name' => 'Student',
        'schoolclass' => '2A',
    ]);
    $lunchUser->assignRole('lunch_user');

    Import116::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $lunchUser->id,
        'first_name' => 'Susi',
        'last_name' => 'Student',
        'email' => 'student@test.local',
        'mother_email' => 'parent@test.local',
        'father_email' => null,
        'import_user_id' => User::factory()->create(['school_id' => $this->school->id])->id,
    ]);

    $this->postJson('/api/homepage/restaurant/check_email', [
        'data' => [
            'school_id' => $this->school->id,
            'email' => 'parent@test.local',
        ],
    ])
        ->assertOk()
        ->assertJsonPath('status', 'USER_FOUND')
        ->assertJsonPath('match_source', 'parent')
        ->assertJsonPath('matched_users_count', 1)
        ->assertJsonPath('matched_users.0.email', 'student@test.local')
        ->assertJsonPath('matched_users.0.matched_children.0', 'Susi Student');
});

it('offers registration for an existing same-school user without lunch role', function () {
    $user = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'plain@test.local',
        'first_name' => 'Paula',
        'last_name' => 'Plain',
    ]);
    $user->assignRole('user');

    $this->postJson('/api/homepage/restaurant/check_email', [
        'data' => [
            'school_id' => $this->school->id,
            'email' => 'plain@test.local',
        ],
    ])
        ->assertOk()
        ->assertJsonPath('status', 'REGISTER_REQUIRED')
        ->assertJsonPath('registration_source', 'existing_user')
        ->assertJsonPath('existing_user.email', 'plain@test.local');
});

it('offers registration when the email exists in the teaching teacher list', function () {
    Teacher::query()->create([
        'school_id' => $this->school->id,
        'email' => 'teacher-list@test.local',
        'first_name' => 'Tina',
        'last_name' => 'Teacher',
        'short' => 'TT',
    ]);

    $this->postJson('/api/homepage/restaurant/check_email', [
        'data' => [
            'school_id' => $this->school->id,
            'email' => 'teacher-list@test.local',
        ],
    ])
        ->assertOk()
        ->assertJsonPath('status', 'REGISTER_REQUIRED')
        ->assertJsonPath('match_source', 'teacher_list')
        ->assertJsonPath('teacher.name', 'Tina Teacher')
        ->assertJsonPath('teacher.short', 'TT');
});

it('offers registration when the email belongs to an import116 student', function () {
    Import116::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'first_name' => 'Sina',
        'last_name' => 'Schueler',
        'class' => '3B',
        'email' => 'student-import@test.local',
        'import_user_id' => User::factory()->create(['school_id' => $this->school->id])->id,
    ]);

    $this->postJson('/api/homepage/restaurant/check_email', [
        'data' => [
            'school_id' => $this->school->id,
            'email' => 'student-import@test.local',
        ],
    ])
        ->assertOk()
        ->assertJsonPath('status', 'REGISTER_REQUIRED')
        ->assertJsonPath('registration_source', 'import116_student')
        ->assertJsonPath('student.name', 'Sina Schueler')
        ->assertJsonPath('student.schoolclass', '3B');
});

it('offers registration when the email belongs to an import116 parent contact', function () {
    $importingUser = User::factory()->create(['school_id' => $this->school->id]);

    Import116::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'first_name' => 'Kind',
        'last_name' => 'Eins',
        'mother_name' => 'Eva Muster',
        'mother_email' => 'parent-import@test.local',
        'import_user_id' => $importingUser->id,
    ]);

    Import116::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'first_name' => 'Kind',
        'last_name' => 'Zwei',
        'mother_name' => 'Eva Muster',
        'mother_email' => 'parent-import@test.local',
        'import_user_id' => $importingUser->id,
    ]);

    $response = $this->postJson('/api/homepage/restaurant/check_email', [
        'data' => [
            'school_id' => $this->school->id,
            'email' => 'parent-import@test.local',
        ],
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('status', 'REGISTER_REQUIRED')
        ->assertJsonPath('registration_source', 'import116_parent')
        ->assertJsonPath('parent_contact.name', 'Eva Muster');

    expect(collect($response->json('parent_contact.children'))->sort()->values()->all())
        ->toBe(['Kind Eins', 'Kind Zwei']);
});

it('returns all lunch users linked to one parent email', function () {
    $firstUser = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'child-one@test.local',
        'first_name' => 'Anna',
        'last_name' => 'Eins',
    ]);
    $firstUser->assignRole('lunch_user');

    $secondUser = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'child-two@test.local',
        'first_name' => 'Ben',
        'last_name' => 'Zwei',
    ]);
    $secondUser->assignRole('lunch_user');

    $importingUser = User::factory()->create(['school_id' => $this->school->id]);

    Import116::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $firstUser->id,
        'first_name' => 'Anna',
        'last_name' => 'Eins',
        'email' => 'child-one@test.local',
        'mother_email' => 'family@test.local',
        'father_email' => null,
        'import_user_id' => $importingUser->id,
    ]);

    Import116::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $secondUser->id,
        'first_name' => 'Ben',
        'last_name' => 'Zwei',
        'email' => 'child-two@test.local',
        'mother_email' => 'family@test.local',
        'father_email' => null,
        'import_user_id' => $importingUser->id,
    ]);

    $this->postJson('/api/homepage/restaurant/check_email', [
        'data' => [
            'school_id' => $this->school->id,
            'email' => 'family@test.local',
        ],
    ])
        ->assertOk()
        ->assertJsonPath('status', 'USER_FOUND')
        ->assertJsonPath('match_source', 'parent')
        ->assertJsonPath('matched_users_count', 2);
});

it('adds lunch_user to an existing same-school user during registration', function () {
    $user = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'existing-role@test.local',
    ]);
    $user->assignRole('user');

    $this->postJson('/api/homepage/restaurant/register', [
        'data' => [
            'school_id' => $this->school->id,
            'email' => 'existing-role@test.local',
        ],
    ])
        ->assertOk()
        ->assertJsonPath('status', 'REGISTERED')
        ->assertJsonPath('registration_source', 'existing_user')
        ->assertJsonPath('requires_email_confirmation', false);

    expect($user->fresh()->hasRole('lunch_user'))->toBeTrue()
        ->and($user->fresh()->restaurant_confirmed_at)->not->toBeNull();
    Notification::assertNothingSent();
});

it('creates a lunch user from the teacher list when registration is confirmed immediately', function () {
    Teacher::query()->create([
        'school_id' => $this->school->id,
        'email' => 'teacher-create@test.local',
        'first_name' => 'Tina',
        'last_name' => 'Teacher',
        'short' => 'TT',
    ]);

    $this->postJson('/api/homepage/restaurant/register', [
        'data' => [
            'school_id' => $this->school->id,
            'email' => 'teacher-create@test.local',
        ],
    ])
        ->assertOk()
        ->assertJsonPath('status', 'REGISTERED')
        ->assertJsonPath('registration_source', 'teacher_list');

    $user = User::query()->where('school_id', $this->school->id)->where('email', 'teacher-create@test.local')->first();

    expect($user)->not->toBeNull()
        ->and($user->hasRole('lunch_user'))->toBeTrue()
        ->and($user->short)->toBe('TT')
        ->and($user->confirmed_at)->not->toBeNull()
        ->and($user->restaurant_confirmed_at)->not->toBeNull();
});

it('creates a lunch user from import116 student data when registration is confirmed immediately', function () {
    $import = Import116::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'first_name' => 'Sina',
        'last_name' => 'Schueler',
        'class' => '3B',
        'email' => 'student-create@test.local',
        'import_user_id' => User::factory()->create(['school_id' => $this->school->id])->id,
    ]);

    $this->postJson('/api/homepage/restaurant/register', [
        'data' => [
            'school_id' => $this->school->id,
            'email' => 'student-create@test.local',
        ],
    ])
        ->assertOk()
        ->assertJsonPath('status', 'REGISTERED')
        ->assertJsonPath('registration_source', 'import116_student');

    $user = User::query()->where('school_id', $this->school->id)->where('email', 'student-create@test.local')->first();

    expect($user)->not->toBeNull()
        ->and($user->hasRole('lunch_user'))->toBeTrue()
        ->and((int) $user->import116_id)->toBe($import->id)
        ->and($user->schoolclass)->toBe('3B')
        ->and($user->restaurant_confirmed_at)->not->toBeNull();
});

it('creates a dedicated parent lunch user from import116 contact data', function () {
    $importingUser = User::factory()->create(['school_id' => $this->school->id]);

    Import116::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'first_name' => 'Kind',
        'last_name' => 'Eins',
        'mother_name' => 'Eva Muster',
        'mother_email' => 'parent-create@test.local',
        'import_user_id' => $importingUser->id,
    ]);

    Import116::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'first_name' => 'Kind',
        'last_name' => 'Zwei',
        'mother_name' => 'Eva Muster',
        'mother_email' => 'parent-create@test.local',
        'import_user_id' => $importingUser->id,
    ]);

    $this->postJson('/api/homepage/restaurant/register', [
        'data' => [
            'school_id' => $this->school->id,
            'email' => 'parent-create@test.local',
        ],
    ])
        ->assertOk()
        ->assertJsonPath('status', 'REGISTERED')
        ->assertJsonPath('registration_source', 'import116_parent');

    $user = User::query()->where('school_id', $this->school->id)->where('email', 'parent-create@test.local')->first();

    expect($user)->not->toBeNull()
        ->and($user->hasRole('lunch_user'))->toBeTrue()
        ->and($user->first_name)->toBe('Eva')
        ->and($user->last_name)->toBe('Muster')
        ->and($user->import116_id)->toBeNull()
        ->and($user->restaurant_confirmed_at)->not->toBeNull();
});

it('creates a manual lunch user when the email is not known anywhere', function () {
    $this->postJson('/api/homepage/restaurant/register', [
        'data' => [
            'school_id' => $this->school->id,
            'email' => 'manual-create@test.local',
            'first_name' => 'Mia',
            'last_name' => 'Muster',
        ],
    ])
        ->assertOk()
        ->assertJsonPath('status', 'REGISTERED')
        ->assertJsonPath('registration_source', 'new_user');

    $user = User::query()->where('school_id', $this->school->id)->where('email', 'manual-create@test.local')->first();

    expect($user)->not->toBeNull()
        ->and($user->hasRole('lunch_user'))->toBeTrue()
        ->and($user->first_name)->toBe('Mia')
        ->and($user->last_name)->toBe('Muster')
        ->and((int) $user->schoolyear_id)->toBe($this->schoolyear->id)
        ->and($user->restaurant_confirmed_at)->not->toBeNull();
});

it('requires first and last name for manual restaurant registration', function () {
    $this->postJson('/api/homepage/restaurant/register', [
        'data' => [
            'school_id' => $this->school->id,
            'email' => 'manual-missing@test.local',
        ],
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['data.first_name', 'data.last_name']);
});

it('keeps newly created restaurant users pending when email confirmation is required', function () {
    $schoolTool = SchoolTool::query()->where('school_id', $this->school->id)->firstOrFail();
    $schoolTool->restaurant_new_users_must_confirm_email = true;
    $schoolTool->save();

    $this->postJson('/api/homepage/restaurant/register', [
        'data' => [
            'school_id' => $this->school->id,
            'email' => 'confirm-create@test.local',
            'first_name' => 'Klara',
            'last_name' => 'Kontrolle',
        ],
    ])
        ->assertOk()
        ->assertJsonPath('status', 'CONFIRM_EMAIL')
        ->assertJsonPath('registration_source', 'new_user')
        ->assertJsonPath('requires_email_confirmation', true);

    $user = User::query()->where('school_id', $this->school->id)->where('email', 'confirm-create@test.local')->first();

    expect($user)->not->toBeNull()
        ->and($user->hasRole('lunch_user'))->toBeTrue()
        ->and($user->email_verified_at)->toBeNull()
        ->and($user->confirmed_at)->toBeNull()
        ->and($user->restaurant_confirmed_at)->toBeNull();

    Notification::assertSentOnDemand(StandardEmail::class);
});
