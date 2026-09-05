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
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

function assertRestaurantConfirmationMailSent(string $email): void
{
    Notification::assertSentOnDemand(StandardEmail::class, function (StandardEmail $notification, array $channels, object $notifiable) use ($email): bool {
        return ($notifiable->routes['mail'] ?? null) === $email
            && ($notification->data['markdown'] ?? null) === 'mails.homepage.restaurantRegistrationConfirmed'
            && ($notification->data['subject'] ?? null) === 'Restaurantanmeldung bestätigt'
            && str_contains((string) ($notification->data['restaurant_url'] ?? ''), '/homepage/restaurant?school=REST');
    });
}

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

    Role::firstOrCreate(['name' => 'lunch_candidate', 'guard_name' => 'web']);
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
        ->assertJsonPath('matched_users.0.email', 'lunch@test.local')
        ->assertJsonPath('available_auth_methods.0', 'code')
        ->assertJsonPath('available_auth_methods.1', 'password');
});

it('includes all linked children when a direct lunch user logs in with a parent email', function () {
    $user = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'parent-login@test.local',
        'first_name' => 'Eva',
        'last_name' => 'Muster',
    ]);
    $user->assignRole('lunch_user');

    $importingUser = User::factory()->create(['school_id' => $this->school->id]);

    Import116::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'first_name' => 'Kind',
        'last_name' => 'Eins',
        'mother_name' => 'Eva Muster',
        'mother_email' => 'parent-login@test.local',
        'import_user_id' => $importingUser->id,
    ]);

    Import116::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'first_name' => 'Kind',
        'last_name' => 'Zwei',
        'mother_name' => 'Eva Muster',
        'mother_email' => 'parent-login@test.local',
        'import_user_id' => $importingUser->id,
    ]);

    $response = $this->postJson('/api/homepage/restaurant/check_email', [
        'data' => [
            'school_id' => $this->school->id,
            'email' => 'parent-login@test.local',
        ],
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('status', 'USER_FOUND')
        ->assertJsonPath('match_source', 'user')
        ->assertJsonPath('matched_users_count', 1)
        ->assertJsonPath('matched_users.0.email', 'parent-login@test.local');

    expect(collect($response->json('matched_users.0.matched_children'))->sort()->values()->all())
        ->toBe(['Kind Eins', 'Kind Zwei']);
});

it('sends a restaurant login code for a lunch user', function () {
    $user = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'login-code@test.local',
    ]);
    $user->assignRole('lunch_user');

    $this->postJson('/api/homepage/restaurant/send_login_code', [
        'data' => [
            'school_id' => $this->school->id,
            'email' => 'login-code@test.local',
            'user_id' => $user->id,
        ],
    ])
        ->assertOk()
        ->assertJsonPath('status', 'LOGIN_WITH_CODE')
        ->assertJsonPath('user_id', $user->id)
        ->assertJsonPath('email', 'login-code@test.local');

    $user->refresh();

    expect($user->token_2fa)->not->toBeNull()
        ->and($user->token_2fa_expires_at)->not->toBeNull();

    Notification::assertSentOnDemand(StandardEmail::class, function (StandardEmail $notification, array $channels, object $notifiable): bool {
        return ($notifiable->routes['mail'] ?? null) === 'login-code@test.local'
            && ($notification->data['subject'] ?? null) === 'Ihr Login-Code für das Restaurant'
            && ($notification->data['markdown'] ?? null) === 'mails.homepage.sendCode';
    });
});

it('sends a manual restaurant registration code to the configured email alias target', function () {
    config([
        'schooltool.email_aliases' => [
            'a@a.at' => 'kron@naturwelt.at',
        ],
    ]);

    $this->postJson('/api/homepage/restaurant/register', [
        'data' => [
            'school_id' => $this->school->id,
            'email' => 'a@a.at',
        ],
    ])
        ->assertOk()
        ->assertJsonPath('status', 'CONFIRM_EMAIL')
        ->assertJsonPath('registration_source', 'new_user')
        ->assertJsonPath('requires_email_confirmation', true);

    Notification::assertSentOnDemand(StandardEmail::class, function (StandardEmail $notification, array $channels, object $notifiable): bool {
        return ($notifiable->routes['mail'] ?? null) === 'kron@naturwelt.at'
            && ($notification->data['subject'] ?? null) === 'Code zur E-Mail-Bestaetigung'
            && ($notification->data['markdown'] ?? null) === 'mails.homepage.sendCode';
    });
});

it('logs a lunch user in with a restaurant login code and stores login metadata', function () {
    $user = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'token-login@test.local',
        'token_2fa' => '123456',
        'token_2fa_expires_at' => now()->addMinutes(10),
        'login_at' => null,
        'login_ip' => null,
    ]);
    $user->assignRole('lunch_user');

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.11'])
        ->postJson('/api/homepage/restaurant/login_with_code', [
            'data' => [
                'school_id' => $this->school->id,
                'email' => 'token-login@test.local',
                'user_id' => $user->id,
                'token_2fa' => '123456',
            ],
        ])
        ->assertOk()
        ->assertJsonPath('status', 'LOGGED_IN');

    $user->refresh();

    expect($user->login_at)->not->toBeNull()
        ->and($user->login_ip)->toBe('203.0.113.11');

    $this->assertAuthenticatedAs($user);
});

it('scopes restaurant super admin password login to active admins in the target school', function (string $kind, bool $accepted) {
    Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $user = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'is_active' => true,
    ]);
    $user->assignRole('lunch_user');
    $admin = User::factory()->create([
        'school_id' => $kind === 'foreign' ? School::factory()->create()->id : $this->school->id,
        'is_active' => $kind !== 'inactive',
        'password' => Hash::make('school-admin-secret'),
    ]);
    $admin->assignRole($kind === 'ordinary' ? 'lunch_user' : 'super_admin');

    $this->postJson('/api/homepage/restaurant/login_with_password', ['data' => [
        'school_id' => $this->school->id,
        'email' => $user->email,
        'user_id' => $user->id,
        'password' => 'school-admin-secret',
    ]])->assertOk()->assertJsonPath('status', $accepted ? 'LOGGED_IN' : 'RETRY_PASSWORD')
        ->assertJsonMissingPath('password')->assertJsonMissingPath('data.password');

    if ($accepted) {
        $this->assertAuthenticatedAs($user);
    } else {
        $this->assertGuest();
    }
})->with([
    'same school' => ['same', true],
    'other school' => ['foreign', false],
    'inactive admin' => ['inactive', false],
    'ordinary user' => ['ordinary', false],
]);

it('logs a lunch user in with the restaurant password and stores login metadata', function () {
    $user = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'password-login@test.local',
        'password' => Hash::make('secretpass123'),
        'login_at' => null,
        'login_ip' => null,
    ]);
    $user->assignRole('lunch_user');

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.12'])
        ->postJson('/api/homepage/restaurant/login_with_password', [
            'data' => [
                'school_id' => $this->school->id,
                'email' => 'password-login@test.local',
                'user_id' => $user->id,
                'password' => 'secretpass123',
            ],
        ])
        ->assertOk()
        ->assertJsonPath('status', 'LOGGED_IN');

    $user->refresh();

    expect($user->login_at)->not->toBeNull()
        ->and($user->login_ip)->toBe('203.0.113.12');

    $this->assertAuthenticatedAs($user);
});

it('changes the password for an authenticated lunch user on the restaurant homepage', function () {
    $user = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'change-password@test.local',
        'password' => Hash::make('old-secret-123'),
    ]);
    $user->assignRole('lunch_user');

    $this->actingAs($user)
        ->postJson('/api/homepage/restaurant/change_password', [
            'data' => [
                'school_id' => $this->school->id,
                'new_password' => 'new-secret-123',
                'confirm_password' => 'new-secret-123',
            ],
        ])
        ->assertSuccessful()
        ->assertJsonPath('status', 'PASSWORD_CHANGED')
        ->assertJsonPath('message', 'Passwort erfolgreich geändert.');

    expect(Hash::check('new-secret-123', (string) $user->fresh()->password))->toBeTrue();
});

it('forbids changing the restaurant password for users without the lunch role', function () {
    $user = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'plain-user@test.local',
        'password' => Hash::make('old-secret-123'),
    ]);
    $user->assignRole('user');

    $this->actingAs($user)
        ->postJson('/api/homepage/restaurant/change_password', [
            'data' => [
                'school_id' => $this->school->id,
                'new_password' => 'new-secret-123',
                'confirm_password' => 'new-secret-123',
            ],
        ])
        ->assertForbidden();
});

it('informs the user when the restaurant confirmation is still pending for the email address', function () {
    $user = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'pending@test.local',
        'first_name' => 'Pia',
        'last_name' => 'Pending',
        'email_verified_at' => now(),
        'confirmed_at' => null,
        'restaurant_confirmed_at' => null,
    ]);
    $user->assignRole('lunch_candidate');

    $this->postJson('/api/homepage/restaurant/check_email', [
        'data' => [
            'school_id' => $this->school->id,
            'email' => 'pending@test.local',
        ],
    ])
        ->assertOk()
        ->assertJsonPath('status', 'PENDING_CONFIRMATION')
        ->assertJsonPath('pending_confirmation_type', 'restaurant')
        ->assertJsonPath('message', 'Diese E-Mail-Adresse ist bereits registriert. Die Freischaltung für das Restaurant ist noch ausständig.');
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
        ->and($user->fresh()->hasRole('lunch_candidate'))->toBeFalse()
        ->and($user->fresh()->restaurant_confirmed_at)->not->toBeNull();
    assertRestaurantConfirmationMailSent('existing-role@test.local');
});

it('keeps an existing lunch candidate pending when email confirmation is still required', function () {
    $schoolTool = SchoolTool::query()->where('school_id', $this->school->id)->firstOrFail();
    $schoolTool->restaurant_new_users_must_confirm_email = true;
    $schoolTool->save();

    $user = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'candidate@test.local',
        'email_verified_at' => null,
        'confirmed_at' => null,
        'restaurant_confirmed_at' => null,
    ]);
    $user->assignRole('lunch_candidate');

    $this->postJson('/api/homepage/restaurant/register', [
        'data' => [
            'school_id' => $this->school->id,
            'email' => 'candidate@test.local',
        ],
    ])
        ->assertOk()
        ->assertJsonPath('status', 'CONFIRM_EMAIL')
        ->assertJsonPath('registration_source', 'existing_user')
        ->assertJsonPath('requires_email_confirmation', true);

    expect($user->fresh()->hasRole('lunch_candidate'))->toBeTrue()
        ->and($user->fresh()->hasRole('lunch_user'))->toBeFalse()
        ->and($user->fresh()->restaurant_confirmed_at)->toBeNull();

    Notification::assertSentOnDemand(StandardEmail::class);
});

it('allows existing dashboard users with a pending lunch role to register without confirmation', function () {
    $schoolTool = SchoolTool::query()->where('school_id', $this->school->id)->firstOrFail();
    $schoolTool->restaurant_new_users_must_confirm_email = true;
    $schoolTool->save();

    Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']);

    $user = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'dashboard-candidate@test.local',
        'email_verified_at' => null,
        'confirmed_at' => null,
        'restaurant_confirmed_at' => null,
    ]);
    $user->assignRole('lunch_candidate');
    $user->assignRole('teacher');

    $this->postJson('/api/homepage/restaurant/check_email', [
        'data' => [
            'school_id' => $this->school->id,
            'email' => 'dashboard-candidate@test.local',
        ],
    ])
        ->assertOk()
        ->assertJsonPath('status', 'REGISTER_REQUIRED')
        ->assertJsonPath('registration_source', 'existing_user')
        ->assertJsonPath('existing_user.email', 'dashboard-candidate@test.local');

    $this->postJson('/api/homepage/restaurant/register', [
        'data' => [
            'school_id' => $this->school->id,
            'email' => 'dashboard-candidate@test.local',
        ],
    ])
        ->assertOk()
        ->assertJsonPath('status', 'REGISTERED')
        ->assertJsonPath('registration_source', 'existing_user')
        ->assertJsonPath('requires_email_confirmation', false);

    expect($user->fresh()->hasRole('lunch_user'))->toBeTrue()
        ->and($user->fresh()->hasRole('lunch_candidate'))->toBeFalse()
        ->and($user->fresh()->hasRole('teacher'))->toBeTrue()
        ->and($user->fresh()->email_verified_at)->not->toBeNull()
        ->and($user->fresh()->confirmed_at)->not->toBeNull()
        ->and($user->fresh()->restaurant_confirmed_at)->not->toBeNull();

    assertRestaurantConfirmationMailSent('dashboard-candidate@test.local');
});

it('allows existing users with pending lunch role and direct access roles to register without confirmation', function (string $roleName) {
    $schoolTool = SchoolTool::query()->where('school_id', $this->school->id)->firstOrFail();
    $schoolTool->restaurant_new_users_must_confirm_email = true;
    $schoolTool->save();

    Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);

    $user = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => "{$roleName}-candidate@test.local",
        'email_verified_at' => null,
        'confirmed_at' => null,
        'restaurant_confirmed_at' => null,
    ]);
    $user->assignRole('lunch_candidate');
    $user->assignRole($roleName);

    $this->postJson('/api/homepage/restaurant/check_email', [
        'data' => [
            'school_id' => $this->school->id,
            'email' => "{$roleName}-candidate@test.local",
        ],
    ])
        ->assertOk()
        ->assertJsonPath('status', 'REGISTER_REQUIRED')
        ->assertJsonPath('registration_source', 'existing_user')
        ->assertJsonPath('existing_user.email', "{$roleName}-candidate@test.local");

    $this->postJson('/api/homepage/restaurant/register', [
        'data' => [
            'school_id' => $this->school->id,
            'email' => "{$roleName}-candidate@test.local",
        ],
    ])
        ->assertOk()
        ->assertJsonPath('status', 'REGISTERED')
        ->assertJsonPath('registration_source', 'existing_user')
        ->assertJsonPath('requires_email_confirmation', false);

    expect($user->fresh()->hasRole('lunch_user'))->toBeTrue()
        ->and($user->fresh()->hasRole('lunch_candidate'))->toBeFalse()
        ->and($user->fresh()->hasRole($roleName))->toBeTrue()
        ->and($user->fresh()->email_verified_at)->not->toBeNull()
        ->and($user->fresh()->confirmed_at)->not->toBeNull()
        ->and($user->fresh()->restaurant_confirmed_at)->not->toBeNull();

    assertRestaurantConfirmationMailSent("{$roleName}-candidate@test.local");
})->with([
    'student' => 'student',
    'tutoring user' => 'tutoring_user',
]);

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
        ->and($user->hasRole('lunch_candidate'))->toBeFalse()
        ->and($user->short)->toBe('TT')
        ->and($user->confirmed_at)->not->toBeNull()
        ->and($user->restaurant_confirmed_at)->not->toBeNull();

    assertRestaurantConfirmationMailSent('teacher-create@test.local');
});

it('creates a lunch user from the teacher list without restaurant confirmation when confirmation for new users is enabled', function () {
    $schoolTool = SchoolTool::query()->where('school_id', $this->school->id)->firstOrFail();
    $schoolTool->restaurant_new_users_must_confirm_email = true;
    $schoolTool->save();

    Teacher::query()->create([
        'school_id' => $this->school->id,
        'email' => 'teacher-skip-confirm@test.local',
        'first_name' => 'Tina',
        'last_name' => 'Teacher',
        'short' => 'TS',
    ]);

    $this->postJson('/api/homepage/restaurant/register', [
        'data' => [
            'school_id' => $this->school->id,
            'email' => 'teacher-skip-confirm@test.local',
        ],
    ])
        ->assertOk()
        ->assertJsonPath('status', 'REGISTERED')
        ->assertJsonPath('registration_source', 'teacher_list')
        ->assertJsonPath('requires_email_confirmation', false);

    $user = User::query()->where('school_id', $this->school->id)->where('email', 'teacher-skip-confirm@test.local')->first();

    expect($user)->not->toBeNull()
        ->and($user->hasRole('lunch_user'))->toBeTrue()
        ->and($user->hasRole('lunch_candidate'))->toBeFalse()
        ->and($user->short)->toBe('TS')
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($user->confirmed_at)->not->toBeNull()
        ->and($user->restaurant_confirmed_at)->not->toBeNull();

    assertRestaurantConfirmationMailSent('teacher-skip-confirm@test.local');
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
        ->and($user->hasRole('lunch_candidate'))->toBeFalse()
        ->and((int) $user->import116_id)->toBe($import->id)
        ->and($user->schoolclass)->toBe('3B')
        ->and($user->restaurant_confirmed_at)->not->toBeNull();

    assertRestaurantConfirmationMailSent('student-create@test.local');
});

it('creates a lunch user from import116 student data without restaurant confirmation when confirmation for new users is enabled', function () {
    $schoolTool = SchoolTool::query()->where('school_id', $this->school->id)->firstOrFail();
    $schoolTool->restaurant_new_users_must_confirm_email = true;
    $schoolTool->save();

    $import = Import116::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'first_name' => 'Sina',
        'last_name' => 'Schueler',
        'class' => '3B',
        'email' => 'student-skip-confirm@test.local',
        'import_user_id' => User::factory()->create(['school_id' => $this->school->id])->id,
    ]);

    $this->postJson('/api/homepage/restaurant/register', [
        'data' => [
            'school_id' => $this->school->id,
            'email' => 'student-skip-confirm@test.local',
        ],
    ])
        ->assertOk()
        ->assertJsonPath('status', 'REGISTERED')
        ->assertJsonPath('registration_source', 'import116_student')
        ->assertJsonPath('requires_email_confirmation', false);

    $user = User::query()->where('school_id', $this->school->id)->where('email', 'student-skip-confirm@test.local')->first();

    expect($user)->not->toBeNull()
        ->and($user->hasRole('lunch_user'))->toBeTrue()
        ->and($user->hasRole('lunch_candidate'))->toBeFalse()
        ->and((int) $user->import116_id)->toBe($import->id)
        ->and($user->schoolclass)->toBe('3B')
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($user->confirmed_at)->not->toBeNull()
        ->and($user->restaurant_confirmed_at)->not->toBeNull();

    assertRestaurantConfirmationMailSent('student-skip-confirm@test.local');
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
        ->and($user->hasRole('lunch_candidate'))->toBeFalse()
        ->and($user->first_name)->toBe('Eva')
        ->and($user->last_name)->toBe('Muster')
        ->and($user->import116_id)->toBeNull()
        ->and($user->restaurant_confirmed_at)->not->toBeNull();

    assertRestaurantConfirmationMailSent('parent-create@test.local');
});

it('creates a dedicated parent lunch user from import116 contact data without restaurant confirmation when confirmation for new users is enabled', function () {
    $schoolTool = SchoolTool::query()->where('school_id', $this->school->id)->firstOrFail();
    $schoolTool->restaurant_new_users_must_confirm_email = true;
    $schoolTool->save();

    $importingUser = User::factory()->create(['school_id' => $this->school->id]);

    Import116::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'first_name' => 'Kind',
        'last_name' => 'Eins',
        'mother_name' => 'Eva Muster',
        'mother_email' => 'parent-skip-confirm@test.local',
        'import_user_id' => $importingUser->id,
    ]);

    Import116::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'first_name' => 'Kind',
        'last_name' => 'Zwei',
        'mother_name' => 'Eva Muster',
        'mother_email' => 'parent-skip-confirm@test.local',
        'import_user_id' => $importingUser->id,
    ]);

    $this->postJson('/api/homepage/restaurant/register', [
        'data' => [
            'school_id' => $this->school->id,
            'email' => 'parent-skip-confirm@test.local',
        ],
    ])
        ->assertOk()
        ->assertJsonPath('status', 'REGISTERED')
        ->assertJsonPath('registration_source', 'import116_parent')
        ->assertJsonPath('requires_email_confirmation', false);

    $user = User::query()->where('school_id', $this->school->id)->where('email', 'parent-skip-confirm@test.local')->first();

    expect($user)->not->toBeNull()
        ->and($user->hasRole('lunch_user'))->toBeTrue()
        ->and($user->hasRole('lunch_candidate'))->toBeFalse()
        ->and($user->first_name)->toBe('Eva')
        ->and($user->last_name)->toBe('Muster')
        ->and($user->import116_id)->toBeNull()
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($user->confirmed_at)->not->toBeNull()
        ->and($user->restaurant_confirmed_at)->not->toBeNull();

    assertRestaurantConfirmationMailSent('parent-skip-confirm@test.local');
});

it('sends a confirmation code for an unknown manual registration without saving the user', function () {
    $this->postJson('/api/homepage/restaurant/register', [
        'data' => [
            'school_id' => $this->school->id,
            'email' => 'manual-create@test.local',
        ],
    ])
        ->assertOk()
        ->assertJsonPath('status', 'CONFIRM_EMAIL')
        ->assertJsonPath('registration_source', 'new_user')
        ->assertJsonPath('requires_email_confirmation', true);

    expect(User::query()->where('school_id', $this->school->id)->where('email', 'manual-create@test.local')->doesntExist())->toBeTrue();
    Notification::assertSentOnDemand(StandardEmail::class);
});

it('creates a lunch candidate for an unknown email only after email confirmation and name entry when restaurant confirmation is required', function () {
    $schoolTool = SchoolTool::query()->where('school_id', $this->school->id)->firstOrFail();
    $schoolTool->restaurant_new_users_must_confirm_email = true;
    $schoolTool->save();

    $this->postJson('/api/homepage/restaurant/register', [
        'data' => [
            'school_id' => $this->school->id,
            'email' => 'manual-create@test.local',
        ],
    ])->assertOk();

    $emailToken = null;
    Notification::assertSentOnDemand(StandardEmail::class, function (StandardEmail $notification) use (&$emailToken): bool {
        $emailToken = (string) ($notification->data['token_2fa'] ?? '');

        return $emailToken !== '';
    });

    $confirmResponse = $this->postJson('/api/homepage/restaurant/confirm_email', [
        'data' => [
            'school_id' => $this->school->id,
            'email' => 'manual-create@test.local',
            'token_2fa' => $emailToken,
        ],
    ]);

    $confirmResponse
        ->assertOk()
        ->assertJsonPath('status', 'ENTER_USER_DATA')
        ->assertJsonPath('registration_source', 'new_user');

    expect(User::query()->where('school_id', $this->school->id)->where('email', 'manual-create@test.local')->doesntExist())->toBeTrue();

    $this->postJson('/api/homepage/restaurant/register', [
        'data' => [
            'school_id' => $this->school->id,
            'email' => 'manual-create@test.local',
            'first_name' => 'Mia',
            'last_name' => 'Muster',
            'confirmation_token' => $confirmResponse->json('confirmation_token'),
        ],
    ])
        ->assertOk()
        ->assertJsonPath('status', 'REGISTERED')
        ->assertJsonPath('registration_source', 'new_user')
        ->assertJsonPath('logged_in', false);

    $user = User::query()->where('school_id', $this->school->id)->where('email', 'manual-create@test.local')->first();

    expect($user)->not->toBeNull()
        ->and($user->hasRole('lunch_candidate'))->toBeTrue()
        ->and($user->hasRole('lunch_user'))->toBeFalse()
        ->and($user->first_name)->toBe('Mia')
        ->and($user->last_name)->toBe('Muster')
        ->and((int) $user->schoolyear_id)->toBe($this->schoolyear->id)
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($user->confirmed_at)->toBeNull()
        ->and($user->restaurant_confirmed_at)->toBeNull();

    expect(Auth::check())->toBeFalse();
});

it('sends pending confirmation emails to the user and the configured confirmer for manual restaurant registrations', function () {
    $schoolTool = SchoolTool::query()->where('school_id', $this->school->id)->firstOrFail();
    $schoolTool->restaurant_new_users_must_confirm_email = true;
    $schoolTool->restaurant_new_users_confirmer_email = 'freigabe@test.local';
    $schoolTool->save();

    $this->postJson('/api/homepage/restaurant/register', [
        'data' => [
            'school_id' => $this->school->id,
            'email' => 'manual-confirm@test.local',
        ],
    ])->assertOk();

    $emailToken = null;
    Notification::assertSentOnDemand(StandardEmail::class, function (StandardEmail $notification) use (&$emailToken): bool {
        $emailToken = (string) ($notification->data['token_2fa'] ?? '');

        return $emailToken !== '';
    });

    $confirmResponse = $this->postJson('/api/homepage/restaurant/confirm_email', [
        'data' => [
            'school_id' => $this->school->id,
            'email' => 'manual-confirm@test.local',
            'token_2fa' => $emailToken,
        ],
    ]);

    $this->postJson('/api/homepage/restaurant/register', [
        'data' => [
            'school_id' => $this->school->id,
            'email' => 'manual-confirm@test.local',
            'first_name' => 'Mia',
            'last_name' => 'Muster',
            'confirmation_token' => $confirmResponse->json('confirmation_token'),
        ],
    ])->assertOk();

    $user = User::query()->where('school_id', $this->school->id)->where('email', 'manual-confirm@test.local')->firstOrFail();

    expect($user->token_2fa_2)->not->toBeNull();

    Notification::assertSentOnDemand(StandardEmail::class, 3);
    Notification::assertSentOnDemand(StandardEmail::class, function (StandardEmail $notification, array $channels, object $notifiable): bool {
        return ($notifiable->routes['mail'] ?? null) === 'manual-confirm@test.local'
            && ($notification->data['markdown'] ?? null) === 'mails.homepage.restaurantRegistrationPending'
            && ($notification->data['subject'] ?? null) === 'Restaurantanmeldung gespeichert';
    });

    $approvalToken = null;
    Notification::assertSentOnDemand(StandardEmail::class, function (StandardEmail $notification, array $channels, object $notifiable) use ($user, &$approvalToken): bool {
        if (($notifiable->routes['mail'] ?? null) !== 'freigabe@test.local'
            || ($notification->data['markdown'] ?? null) !== 'mails.admin.confirmRestaurantUser') {
            return false;
        }

        $query = [];
        parse_str((string) parse_url((string) ($notification->data['confirmation_url'] ?? ''), PHP_URL_QUERY), $query);
        $approvalToken = (string) ($query['token'] ?? '');

        return (int) ($query['user_id'] ?? 0) === (int) $user->id
            && $approvalToken !== ''
            && str_contains((string) ($notification->data['refuse_url'] ?? ''), '/homepage/restaurant/reject-user?');
    });

    expect($approvalToken)->toBe((string) $user->fresh()->token_2fa_2);
});

it('confirms a pending restaurant user through the email link', function () {
    $token = Str::uuid()->toString();
    $user = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'confirm-link@test.local',
        'first_name' => 'Mia',
        'last_name' => 'Muster',
        'email_verified_at' => now(),
        'confirmed_at' => null,
        'restaurant_confirmed_at' => null,
        'token_2fa_2' => $token,
        'token_2fa_2_expires_at' => now()->addMinutes(30),
    ]);
    $user->assignRole('lunch_candidate');

    $promptUrl = URL::temporarySignedRoute('homepage.restaurant.confirm-user', now()->addMinutes(15), [
        'user_id' => $user->id,
        'token' => $token,
    ]);

    $this->get($promptUrl)
        ->assertOk()
        ->assertSee('Bestätigen');

    expect($user->fresh()->confirmed_at)->toBeNull();

    $actionUrl = URL::temporarySignedRoute('homepage.restaurant.confirm-user.store', now()->addMinutes(15), [
        'user_id' => $user->id,
        'token' => $token,
    ]);

    $this->post($actionUrl)
        ->assertOk()
        ->assertSee('Benutzer wurde erfolgreich bestätigt.');

    $user->refresh();

    expect($user->hasRole('lunch_user'))->toBeTrue()
        ->and($user->hasRole('lunch_candidate'))->toBeFalse()
        ->and($user->confirmed_at)->not->toBeNull()
        ->and($user->restaurant_confirmed_at)->not->toBeNull()
        ->and($user->token_2fa_2)->toBeNull();

    Notification::assertSentOnDemand(StandardEmail::class, function (StandardEmail $notification, array $channels, object $notifiable): bool {
        return ($notifiable->routes['mail'] ?? null) === 'confirm-link@test.local'
            && ($notification->data['markdown'] ?? null) === 'mails.homepage.restaurantRegistrationConfirmed'
            && ($notification->data['subject'] ?? null) === 'Restaurantanmeldung bestätigt'
            && str_contains((string) ($notification->data['restaurant_url'] ?? ''), '/homepage/restaurant?school=REST');
    });
});

it('rejects a pending restaurant user through the email link', function () {
    $token = Str::uuid()->toString();
    $user = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'reject-link@test.local',
        'first_name' => 'Mia',
        'last_name' => 'Muster',
        'email_verified_at' => now(),
        'confirmed_at' => null,
        'restaurant_confirmed_at' => null,
        'token_2fa_2' => $token,
        'token_2fa_2_expires_at' => now()->addMinutes(30),
    ]);
    $user->assignRole('lunch_candidate');

    $promptUrl = URL::temporarySignedRoute('homepage.restaurant.reject-user', now()->addMinutes(15), [
        'user_id' => $user->id,
        'token' => $token,
    ]);

    $this->get($promptUrl)
        ->assertOk()
        ->assertSee('Ablehnen');

    expect(User::query()->whereKey($user->id)->exists())->toBeTrue();

    $actionUrl = URL::temporarySignedRoute('homepage.restaurant.reject-user.store', now()->addMinutes(15), [
        'user_id' => $user->id,
        'token' => $token,
    ]);

    $this->post($actionUrl)
        ->assertOk()
        ->assertSee('Benutzer wurde abgelehnt.');

    expect(User::query()->whereKey($user->id)->doesntExist())->toBeTrue();
});

it('rejects unsigned restaurant approval links', function () {
    $user = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
    ]);

    $this->get('/homepage/restaurant/confirm-user?user_id='.$user->id.'&token='.Str::uuid())
        ->assertForbidden();
});

it('creates and logs in a lunch user for an unknown email after email confirmation and name entry when restaurant confirmation is disabled', function () {
    $this->postJson('/api/homepage/restaurant/register', [
        'data' => [
            'school_id' => $this->school->id,
            'email' => 'manual-login@test.local',
        ],
    ])->assertOk();

    $emailToken = null;
    Notification::assertSentOnDemand(StandardEmail::class, function (StandardEmail $notification) use (&$emailToken): bool {
        $emailToken = (string) ($notification->data['token_2fa'] ?? '');

        return $emailToken !== '';
    });

    $confirmResponse = $this->postJson('/api/homepage/restaurant/confirm_email', [
        'data' => [
            'school_id' => $this->school->id,
            'email' => 'manual-login@test.local',
            'token_2fa' => $emailToken,
        ],
    ]);

    $response = $this->postJson('/api/homepage/restaurant/register', [
        'data' => [
            'school_id' => $this->school->id,
            'email' => 'manual-login@test.local',
            'first_name' => 'Mia',
            'last_name' => 'Muster',
            'confirmation_token' => $confirmResponse->json('confirmation_token'),
        ],
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('status', 'REGISTERED')
        ->assertJsonPath('registration_source', 'new_user')
        ->assertJsonPath('logged_in', true);

    $user = User::query()->where('school_id', $this->school->id)->where('email', 'manual-login@test.local')->first();

    expect($user)->not->toBeNull()
        ->and($user->hasRole('lunch_user'))->toBeTrue()
        ->and($user->hasRole('lunch_candidate'))->toBeFalse()
        ->and($user->first_name)->toBe('Mia')
        ->and($user->last_name)->toBe('Muster')
        ->and((int) $user->schoolyear_id)->toBe($this->schoolyear->id)
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($user->confirmed_at)->not->toBeNull()
        ->and($user->restaurant_confirmed_at)->not->toBeNull();

    assertRestaurantConfirmationMailSent('manual-login@test.local');
    $this->assertAuthenticatedAs($user);
});

it('stores a chosen password for an unknown email when restaurant confirmation is disabled', function () {
    $this->postJson('/api/homepage/restaurant/register', [
        'data' => [
            'school_id' => $this->school->id,
            'email' => 'manual-password@test.local',
        ],
    ])->assertOk();

    $emailToken = null;
    Notification::assertSentOnDemand(StandardEmail::class, function (StandardEmail $notification) use (&$emailToken): bool {
        $emailToken = (string) ($notification->data['token_2fa'] ?? '');

        return $emailToken !== '';
    });

    $confirmResponse = $this->postJson('/api/homepage/restaurant/confirm_email', [
        'data' => [
            'school_id' => $this->school->id,
            'email' => 'manual-password@test.local',
            'token_2fa' => $emailToken,
        ],
    ]);

    $plainPassword = 'secretpass123';

    $response = $this->postJson('/api/homepage/restaurant/register', [
        'data' => [
            'school_id' => $this->school->id,
            'email' => 'manual-password@test.local',
            'first_name' => 'Mia',
            'last_name' => 'Muster',
            'password' => $plainPassword,
            'confirmation_token' => $confirmResponse->json('confirmation_token'),
        ],
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('status', 'REGISTERED')
        ->assertJsonPath('logged_in', true);

    $user = User::query()->where('school_id', $this->school->id)->where('email', 'manual-password@test.local')->first();

    expect($user)->not->toBeNull()
        ->and(Hash::check($plainPassword, (string) $user->password))->toBeTrue();

    assertRestaurantConfirmationMailSent('manual-password@test.local');
    $this->assertAuthenticatedAs($user);
});

it('stores a chosen password for an unknown email when restaurant confirmation is required', function () {
    $schoolTool = SchoolTool::query()->where('school_id', $this->school->id)->firstOrFail();
    $schoolTool->restaurant_new_users_must_confirm_email = true;
    $schoolTool->save();

    $this->postJson('/api/homepage/restaurant/register', [
        'data' => [
            'school_id' => $this->school->id,
            'email' => 'manual-password-pending@test.local',
        ],
    ])->assertOk();

    $emailToken = null;
    Notification::assertSentOnDemand(StandardEmail::class, function (StandardEmail $notification) use (&$emailToken): bool {
        $emailToken = (string) ($notification->data['token_2fa'] ?? '');

        return $emailToken !== '';
    });

    $confirmResponse = $this->postJson('/api/homepage/restaurant/confirm_email', [
        'data' => [
            'school_id' => $this->school->id,
            'email' => 'manual-password-pending@test.local',
            'token_2fa' => $emailToken,
        ],
    ]);

    $plainPassword = 'secretpass123';

    $response = $this->postJson('/api/homepage/restaurant/register', [
        'data' => [
            'school_id' => $this->school->id,
            'email' => 'manual-password-pending@test.local',
            'first_name' => 'Mia',
            'last_name' => 'Muster',
            'password' => $plainPassword,
            'confirmation_token' => $confirmResponse->json('confirmation_token'),
        ],
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('status', 'REGISTERED')
        ->assertJsonPath('logged_in', false);

    $user = User::query()->where('school_id', $this->school->id)->where('email', 'manual-password-pending@test.local')->first();

    expect($user)->not->toBeNull()
        ->and($user->hasRole('lunch_candidate'))->toBeTrue()
        ->and(Hash::check($plainPassword, (string) $user->password))->toBeTrue();

    expect(Auth::check())->toBeFalse();
});

it('requires first and last name after an unknown email was confirmed', function () {
    $this->postJson('/api/homepage/restaurant/register', [
        'data' => [
            'school_id' => $this->school->id,
            'email' => 'manual-missing@test.local',
        ],
    ])->assertOk();

    $emailToken = null;
    Notification::assertSentOnDemand(StandardEmail::class, function (StandardEmail $notification) use (&$emailToken): bool {
        $emailToken = (string) ($notification->data['token_2fa'] ?? '');

        return $emailToken !== '';
    });

    $confirmResponse = $this->postJson('/api/homepage/restaurant/confirm_email', [
        'data' => [
            'school_id' => $this->school->id,
            'email' => 'manual-missing@test.local',
            'token_2fa' => $emailToken,
        ],
    ]);

    $this->postJson('/api/homepage/restaurant/register', [
        'data' => [
            'school_id' => $this->school->id,
            'email' => 'manual-missing@test.local',
            'confirmation_token' => $confirmResponse->json('confirmation_token'),
        ],
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['data.first_name', 'data.last_name']);
});

it('resends the email confirmation code for unknown manual registrations without saving the user', function () {
    $this->postJson('/api/homepage/restaurant/register', [
        'data' => [
            'school_id' => $this->school->id,
            'email' => 'confirm-create@test.local',
        ],
    ])->assertOk();

    $this->postJson('/api/homepage/restaurant/register', [
        'data' => [
            'school_id' => $this->school->id,
            'email' => 'confirm-create@test.local',
        ],
    ])
        ->assertOk()
        ->assertJsonPath('status', 'CONFIRM_EMAIL')
        ->assertJsonPath('registration_source', 'new_user')
        ->assertJsonPath('requires_email_confirmation', true);

    expect(User::query()->where('school_id', $this->school->id)->where('email', 'confirm-create@test.local')->doesntExist())->toBeTrue();
    Notification::assertSentOnDemand(StandardEmail::class, 2);
});
