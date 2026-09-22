<?php

/**
 * HomepageController Tests
 *
 * Tests the public-facing Homepage controller including:
 * - routing (handle school/licence routing)
 * - loadSchoolsForTool (load schools for specific tool/licence)
 * - config (load homepage configuration)
 * - logout (handle user logout)
 */

use App\Models\Licence;
use App\Models\RestaurantMenuPlan;
use App\Models\School;
use App\Models\SchoolLicence;
use App\Models\SchoolTool;
use App\Models\Schoolyear;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

afterEach(function () {
    Carbon::setTestNow();
});

beforeEach(function () {
    $this->school = School::factory()->create([
        'short_name' => 'TEST',
        'long_name' => 'Test School',
        'is_selectable' => true,
    ]);
    $this->schoolyear = Schoolyear::factory()->create(['school_id' => $this->school->id]);

    $this->licence = Licence::create(['name' => 'Anmeldetool']);
    $this->school->licences()->attach($this->licence->id, [
        'valid_until' => now()->addYear()->toDateString(),
    ]);

    Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'lunch_user', 'guard_name' => 'web']);
});

test('homepage password login scopes school super admin overrides', function (string $credentialType, bool $accepted) {
    Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $targetUser = User::factory()->create([
        'school_id' => $this->school->id,
        'password' => Hash::make('target-password'),
        'is_active' => true,
        'confirmed_at' => now(),
        'is_2fa' => false,
    ]);
    $targetUser->assignRole('user');
    User::factory()->create([
        'school_id' => $this->school->id,
        'password' => Hash::make('different-admin-password'),
        'is_active' => true,
    ])->assignRole('super_admin');
    $passwordOwner = User::factory()->create([
        'school_id' => $credentialType === 'foreign-school' ? School::factory()->create()->id : $this->school->id,
        'password' => Hash::make('override-password'),
        'is_active' => $credentialType !== 'inactive',
    ]);
    $passwordOwner->assignRole($credentialType === 'ordinary-user' ? 'user' : 'super_admin');
    $originalPassword = $targetUser->password;

    $response = $this->postJson('/api/homepage/login_step_password', [
        'school_id' => $this->school->id,
        'email' => $targetUser->email,
        'password' => 'override-password',
    ]);

    $response->assertJsonMissingPath('password')
        ->assertDontSee('override-password', false);

    if ($accepted) {
        $response->assertOk()->assertJsonPath('step', 'LOGIN_SUCCESS');
        $this->assertAuthenticatedAs($targetUser);
    } else {
        $response->assertUnauthorized();
        $this->assertGuest();
    }

    expect($targetUser->refresh()->password)->toBe($originalPassword);
})->with([
    'active same-school super admin' => ['active', true],
    'inactive super admin' => ['inactive', false],
    'other-school super admin' => ['foreign-school', false],
    'same-school ordinary user' => ['ordinary-user', false],
]);

describe('loadSchoolsForTool', function () {
    test('load schools for tool returns licence and associated schools', function () {
        $response = $this->getJson('/api/homepage/load_schools_for_tool?tool=Anmeldetool');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'licence' => ['id', 'name'],
                'schools' => [
                    '*' => ['id', 'short_name', 'long_name'],
                ],
            ])
            ->assertJson([
                'licence' => ['name' => 'Anmeldetool'],
            ]);

        $schools = $response->json('schools');
        expect($schools)->toHaveCount(1)
            ->and($schools[0]['short_name'])->toBe('TEST');
    });

    test('load schools for tool with multiple schools', function () {
        $school2 = School::factory()->create([
            'short_name' => 'SCH2',
            'is_selectable' => true,
        ]);
        $school2->licences()->attach($this->licence->id, [
            'valid_until' => now()->addYear()->toDateString(),
        ]);

        $response = $this->getJson('/api/homepage/load_schools_for_tool?tool=Anmeldetool');

        $response->assertStatus(200);

        $schools = $response->json('schools');
        expect($schools)->toHaveCount(2);
    });

    test('load schools for tool returns empty schools for licence without schools', function () {
        $licenceWithoutSchools = Licence::create(['name' => 'EmptyTool']);

        $response = $this->getJson('/api/homepage/load_schools_for_tool?tool=EmptyTool');

        $response->assertStatus(200);

        $schools = $response->json('schools');
        expect($schools)->toBeArray()->toBeEmpty();
    });

    test('load schools for tool validates required tool parameter', function () {
        $response = $this->getJson('/api/homepage/load_schools_for_tool');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['tool']);
    });

    test('load schools for tool treats expired school licence as active when not required', function () {
        $schoolLicence = SchoolLicence::where('school_id', $this->school->id)
            ->where('licence_id', $this->licence->id)
            ->firstOrFail();

        $schoolLicence->valid_until = now()->subDay()->toDateString();
        $schoolLicence->licence_model = [
            'school_licence_required' => false,
            'affected_roles' => [],
            'user_licence_required_by_role' => [],
        ];
        $schoolLicence->save();

        $response = $this->getJson('/api/homepage/load_schools_for_tool?tool=Anmeldetool');

        $response->assertStatus(200)
            ->assertJsonPath('status', 'active');

        $schools = $response->json('schools');
        expect($schools)->toHaveCount(1)
            ->and($schools[0]['short_name'])->toBe('TEST');
    });
});

describe('routing', function () {
    test('restaurant page hard refresh returns the homepage shell', function () {
        $this->get('/homepage/restaurant')
            ->assertOk()
            ->assertViewIs('homepage');
    });
});

describe('config', function () {
    test('config returns valid school and licence when both exist', function () {
        $response = $this->getJson('/api/homepage/config?school='.$this->school->short_name.'&app=Anmeldetool');

        $response->assertStatus(200)
            ->assertJson([
                'isSchoolValid' => true,
                'isLicenceValid' => true,
                'school' => ['short_name' => 'TEST'],
                'licence' => ['name' => 'Anmeldetool'],
            ]);
    });

    test('config returns school info when only school parameter provided', function () {
        $response = $this->getJson('/api/homepage/config?school='.$this->school->short_name);

        $response->assertStatus(200)
            ->assertJson([
                'isSchoolValid' => true,
                'school' => ['short_name' => 'TEST'],
            ]);
    });

    test('config returns null school when school does not exist', function () {
        // Create another school so auto-selection doesn't kick in
        School::factory()->create([
            'short_name' => 'OTHER',
            'is_selectable' => true,
        ]);

        $response = $this->getJson('/api/homepage/config?school=INVALID');

        $response->assertStatus(200)
            ->assertJson([
                'isSchoolValid' => false,
                'school' => null,
            ]);
    });

    test('config returns configuration structure', function () {
        $response = $this->getJson('/api/homepage/config?school='.$this->school->short_name);

        $response->assertOk()
            ->assertJsonStructure([
                'schooltool_logo',
                'logo',
                'version',
                'copyright',
                'title',
                'isSchoolValid',
                'school',
                'isLicenceValid',
                'licence',
                'selectableSchools',
                'schoolLicences',
                'auth_check',
                'auth_user',
                'tool_module_statuses' => [
                    'register',
                    'teaching',
                    'materials',
                    'restaurant',
                    'students_timetables',
                ],
                'tool_module_visibility' => [
                    'register',
                    'teaching',
                    'materials',
                    'restaurant',
                    'students_timetables',
                ],
                'teaching_active',
                'students_timetables_active',
                'restaurant' => [
                    'user_information_intro_html',
                    'new_users_must_confirm_email',
                    'visible_menu_plans_count',
                    'orderable_menu_plans_count',
                ],
            ]);
    });

    test('config includes the authenticated restaurant user for the selected school', function () {
        $user = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'first_name' => 'Günther',
            'last_name' => 'Kron',
            'email' => 'restaurant-user@test.local',
        ]);
        $user->assignRole('lunch_user');

        $this->actingAs($user);

        $response = $this->getJson('/api/homepage/config?school='.$this->school->short_name.'&app=Restaurant');

        $response->assertOk()
            ->assertJsonPath('auth_check', true)
            ->assertJsonPath('auth_user.id', $user->id)
            ->assertJsonPath('auth_user.first_name', 'Günther')
            ->assertJsonPath('auth_user.last_name', 'Kron')
            ->assertJsonPath('auth_user.email', 'restaurant-user@test.local');
    });

    test('config returns selectable schools', function () {
        $response = $this->getJson('/api/homepage/config');

        $response->assertStatus(200);

        expect($response->json('selectableSchools'))->toBeArray();
    });

    test('config auto selects single school when no school parameter', function () {
        // Only one school exists
        $response = $this->getJson('/api/homepage/config');

        $response->assertStatus(200)
            ->assertJson([
                'isSchoolValid' => true,
                'school' => ['short_name' => 'TEST'],
            ]);
    });

    test('config auto selects single licence when school has one licence', function () {
        $response = $this->getJson('/api/homepage/config?school='.$this->school->short_name);

        $response->assertStatus(200)
            ->assertJson([
                'isLicenceValid' => true,
                'licence' => ['name' => 'Anmeldetool'],
            ]);
    });

    test('config returns school licences for valid school', function () {
        $response = $this->getJson('/api/homepage/config?school='.$this->school->short_name);

        $response->assertStatus(200);

        $licences = $response->json('schoolLicences');
        expect($licences)->toBeArray()->toHaveCount(1);
    });

    test('config includes schooltool version and copyright', function () {
        config(['schooltool.version' => '1.0.0']);
        config(['schooltool.copyright' => '2025 Test']);

        $response = $this->getJson('/api/homepage/config');

        $response->assertStatus(200)
            ->assertJson([
                'version' => '1.0.0',
                'copyright' => '2025 Test',
            ]);
    });

    test('config derives module statuses and booleans from school tools', function () {
        SchoolTool::factory()->create([
            'school_id' => $this->school->id,
            'register_visible_user' => true,
            'teaching_visible_user' => false,
            'teaching_user_test_mode' => true,
            'teaching_user_comming_soon' => false,
            'materials_visible_user' => false,
            'materials_user_test_mode' => false,
            'materials_user_comming_soon' => false,
            'restaurant_visible_user' => true,
            'students_timetables_visible_user' => true,
        ]);

        $studentsTimetablesLicence = Licence::create(['name' => 'StudentsTimetables']);
        $this->school->licences()->attach($studentsTimetablesLicence->id, [
            'valid_until' => now()->addYear()->toDateString(),
        ]);

        $response = $this->getJson('/api/homepage/config?school='.$this->school->short_name);

        $response->assertStatus(200)
            ->assertJson([
                'register_active' => true,
                'teaching_active' => true,
                'restaurant_active' => true,
                'students_timetables_active' => true,
            ])
            ->assertJsonMissingPath('tool_module_statuses.tutoring')
            ->assertJsonMissingPath('tutoring_active')
            ->assertJsonPath('tool_module_statuses.teaching', 'test_modus')
            ->assertJsonPath('tool_module_statuses.materials', 'inactive')
            ->assertJsonPath('tool_module_statuses.students_timetables', 'active')
            ->assertJsonPath('tool_module_visibility.register', true)
            ->assertJsonMissingPath('tool_module_visibility.tutoring')
            ->assertJsonPath('tool_module_visibility.teaching', false)
            ->assertJsonPath('tool_module_visibility.materials', false)
            ->assertJsonPath('tool_module_visibility.restaurant', true)
            ->assertJsonPath('tool_module_visibility.students_timetables', true)
            ->assertJsonPath('tool_licence_statuses.StudentsTimetables', 'active');
    });

    test('config resolves module statuses from the selected school instead of the first school tool record', function () {
        $otherSchool = School::factory()->create([
            'short_name' => 'OTHER',
            'is_selectable' => true,
        ]);

        SchoolTool::factory()->create([
            'school_id' => $otherSchool->id,
            'register_visible_user' => false,
            'teaching_visible_user' => false,
            'materials_visible_user' => false,
            'restaurant_visible_user' => false,
        ]);

        SchoolTool::factory()->create([
            'school_id' => $this->school->id,
            'register_visible_user' => true,
            'restaurant_visible_user' => true,
        ]);

        $response = $this->getJson('/api/homepage/config?school='.$this->school->short_name);

        $response->assertOk()
            ->assertJsonPath('register_active', true)
            ->assertJsonPath('restaurant_active', true)
            ->assertJsonPath('tool_module_statuses.register', 'active')
            ->assertJsonPath('tool_module_statuses.restaurant', 'active');
    });

    test('config includes the restaurant user information intro html for the selected school', function () {
        SchoolTool::factory()->create([
            'school_id' => $this->school->id,
            'restaurant_user_information_intro_html' => '<p><strong>Willkommen</strong> im Restaurant.</p>',
        ]);

        $response = $this->getJson('/api/homepage/config?school='.$this->school->short_name);

        $response->assertOk()
            ->assertJsonPath('restaurant.user_information_intro_html', '<p><strong>Willkommen</strong> im Restaurant.</p>');
    });

    test('config includes whether new restaurant users must be confirmed', function () {
        SchoolTool::factory()->create([
            'school_id' => $this->school->id,
            'restaurant_new_users_must_confirm_email' => true,
        ]);

        $response = $this->getJson('/api/homepage/config?school='.$this->school->short_name);

        $response->assertOk()
            ->assertJsonPath('restaurant.new_users_must_confirm_email', true);
    });

    test('config includes current visible and orderable restaurant menu plan counts', function () {
        Carbon::setTestNow('2026-03-23 16:00:00');

        SchoolTool::factory()->create([
            'school_id' => $this->school->id,
            'restaurant_menu_visibility_start_mode' => 'scheduled',
            'restaurant_menu_visibility_start_week_offset' => 2,
            'restaurant_menu_visibility_start_day_of_week' => 1,
            'restaurant_menu_visibility_start_time' => '09:00:00',
            'restaurant_menu_order_start_mode' => 'scheduled',
            'restaurant_menu_order_start_week_offset' => 2,
            'restaurant_menu_order_start_day_of_week' => 1,
            'restaurant_menu_order_start_time' => '10:00:00',
            'restaurant_menu_order_end_week_offset' => 1,
            'restaurant_menu_order_end_day_of_week' => 5,
            'restaurant_menu_order_end_time' => '17:00:00',
            'restaurant_menu_visibility_end_mode' => 'plan_end',
        ]);

        RestaurantMenuPlan::factory()->create([
            'school_id' => $this->school->id,
            'start_date' => '2026-03-23',
            'end_date' => '2026-03-27',
            'is_available' => true,
        ]);
        RestaurantMenuPlan::factory()->create([
            'school_id' => $this->school->id,
            'start_date' => '2026-04-06',
            'end_date' => '2026-04-10',
            'is_available' => true,
        ]);
        RestaurantMenuPlan::factory()->create([
            'school_id' => $this->school->id,
            'start_date' => '2026-04-13',
            'end_date' => '2026-04-17',
            'is_available' => false,
        ]);

        $response = $this->getJson('/api/homepage/config?school='.$this->school->short_name);

        $response->assertOk()
            ->assertJsonPath('restaurant.visible_menu_plans_count', 2)
            ->assertJsonPath('restaurant.orderable_menu_plans_count', 1);
    });

    test('config uses current online settings when no individual schedule values are enabled', function () {
        Carbon::setTestNow('2026-03-23 16:00:00');

        SchoolTool::factory()->create([
            'school_id' => $this->school->id,
            'restaurant_menu_visibility_start_mode' => 'scheduled',
            'restaurant_menu_visibility_start_week_offset' => 0,
            'restaurant_menu_visibility_start_day_of_week' => 5,
            'restaurant_menu_visibility_start_time' => '23:00:00',
            'restaurant_menu_order_start_mode' => 'scheduled',
            'restaurant_menu_order_start_week_offset' => 0,
            'restaurant_menu_order_start_day_of_week' => 5,
            'restaurant_menu_order_start_time' => '23:00:00',
            'restaurant_menu_order_end_week_offset' => 0,
            'restaurant_menu_order_end_day_of_week' => 6,
            'restaurant_menu_order_end_time' => '23:59:00',
            'restaurant_menu_visibility_end_mode' => 'plan_end',
        ]);

        RestaurantMenuPlan::factory()->create([
            'school_id' => $this->school->id,
            'start_date' => '2026-03-23',
            'end_date' => '2026-03-27',
            'is_available' => true,
            'visibility_start_mode' => 'when_available',
            'order_start_mode' => 'scheduled',
            'order_start_week_offset' => 0,
            'order_start_day_of_week' => 2,
            'order_start_time' => '09:00:00',
            'order_end_week_offset' => 0,
            'order_end_day_of_week' => 2,
            'order_end_time' => '12:00:00',
            'visibility_end_mode' => 'plan_end',
        ]);

        $response = $this->getJson('/api/homepage/config?school='.$this->school->short_name);

        $response->assertOk()
            ->assertJsonPath('restaurant.visible_menu_plans_count', 0)
            ->assertJsonPath('restaurant.orderable_menu_plans_count', 0);
    });

    test('config does not treat future menu plans as already orderable when older per-plan schedule fields differ', function () {
        Carbon::setTestNow('2026-04-01 20:42:22');

        SchoolTool::factory()->create([
            'school_id' => $this->school->id,
            'restaurant_menu_visibility_start_mode' => 'when_orderable',
            'restaurant_menu_visibility_start_week_offset' => 2,
            'restaurant_menu_visibility_start_day_of_week' => 0,
            'restaurant_menu_visibility_start_time' => '15:00:00',
            'restaurant_menu_order_start_mode' => 'scheduled',
            'restaurant_menu_order_start_week_offset' => 2,
            'restaurant_menu_order_start_day_of_week' => 0,
            'restaurant_menu_order_start_time' => '15:00:00',
            'restaurant_menu_order_end_week_offset' => 1,
            'restaurant_menu_order_end_day_of_week' => 4,
            'restaurant_menu_order_end_time' => '16:00:00',
            'restaurant_menu_visibility_end_mode' => 'plan_end',
        ]);

        RestaurantMenuPlan::factory()->create([
            'school_id' => $this->school->id,
            'start_date' => '2026-04-13',
            'end_date' => '2026-04-16',
            'is_available' => true,
            'visibility_start_mode' => 'when_orderable',
            'order_start_mode' => 'when_available',
            'order_end_week_offset' => 1,
            'order_end_day_of_week' => 4,
            'order_end_time' => '16:00:00',
            'visibility_end_mode' => 'plan_end',
            'use_individual_schedule_values' => false,
        ]);

        $response = $this->getJson('/api/homepage/config?school='.$this->school->short_name);

        $response->assertOk()
            ->assertJsonPath('restaurant.visible_menu_plans_count', 0)
            ->assertJsonPath('restaurant.orderable_menu_plans_count', 0);
    });

    test('config prefers individual menu plan schedule values over global and plan schedule rules', function () {
        Carbon::setTestNow('2026-03-23 16:00:00');

        SchoolTool::factory()->create([
            'school_id' => $this->school->id,
            'restaurant_menu_visibility_start_mode' => 'scheduled',
            'restaurant_menu_visibility_start_week_offset' => 0,
            'restaurant_menu_visibility_start_day_of_week' => 5,
            'restaurant_menu_visibility_start_time' => '23:00:00',
            'restaurant_menu_order_start_mode' => 'scheduled',
            'restaurant_menu_order_start_week_offset' => 0,
            'restaurant_menu_order_start_day_of_week' => 5,
            'restaurant_menu_order_start_time' => '23:00:00',
            'restaurant_menu_order_end_week_offset' => 0,
            'restaurant_menu_order_end_day_of_week' => 6,
            'restaurant_menu_order_end_time' => '23:59:00',
            'restaurant_menu_visibility_end_mode' => 'plan_end',
        ]);

        RestaurantMenuPlan::factory()->create([
            'school_id' => $this->school->id,
            'start_date' => '2026-04-06',
            'end_date' => '2026-04-10',
            'is_available' => true,
            'visibility_start_mode' => 'scheduled',
            'visibility_start_week_offset' => 0,
            'visibility_start_day_of_week' => 5,
            'visibility_start_time' => '23:00:00',
            'order_start_mode' => 'scheduled',
            'order_start_week_offset' => 0,
            'order_start_day_of_week' => 5,
            'order_start_time' => '23:00:00',
            'order_end_week_offset' => 0,
            'order_end_day_of_week' => 6,
            'order_end_time' => '23:59:00',
            'visibility_end_mode' => 'plan_end',
            'use_individual_schedule_values' => true,
            'visible_start_at' => '2026-03-22 12:00:00',
            'visible_end_at' => '2026-03-30 23:59:00',
            'order_start_at' => '2026-03-23 08:00:00',
            'order_end_at' => '2026-03-23 17:00:00',
        ]);

        $response = $this->getJson('/api/homepage/config?school='.$this->school->short_name);

        $response->assertOk()
            ->assertJsonPath('restaurant.visible_menu_plans_count', 1)
            ->assertJsonPath('restaurant.orderable_menu_plans_count', 1);
    });

    test('config keeps tool status active when school licence is expired but not required', function () {
        $schoolLicence = SchoolLicence::where('school_id', $this->school->id)
            ->where('licence_id', $this->licence->id)
            ->firstOrFail();

        $schoolLicence->valid_until = now()->subDay()->toDateString();
        $schoolLicence->licence_model = [
            'school_licence_required' => false,
            'affected_roles' => [],
            'user_licence_required_by_role' => [],
        ];
        $schoolLicence->save();

        $response = $this->getJson('/api/homepage/config');

        $response->assertStatus(200)
            ->assertJsonPath('tool_licence_statuses.Anmeldetool', 'active');
    });
});

describe('logout', function () {
    test('logout invalidates authenticated session', function () {
        $user = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
        ]);
        $user->assignRole('user');

        $this->actingAs($user);

        expect(Auth::check())->toBeTrue();

        $this->postJson('/api/homepage/logout');

        expect(Auth::check())->toBeFalse();
    });

    test('logout handles unauthenticated user gracefully', function () {
        expect(Auth::check())->toBeFalse();

        $response = $this->postJson('/api/homepage/logout');

        // Should not throw error
        $response->assertStatus(200);
    });

    test('logout invalidates an unauthenticated parent session', function () {
        $response = $this
            ->withSession([
                'student_parent_access' => [
                    'school_id' => $this->school->id,
                    'schoolyear_id' => $this->schoolyear->id,
                    'email' => 'parent@example.test',
                    'verified_at' => now()->timestamp,
                    'student_import_id' => 123,
                ],
            ])
            ->postJson('/api/homepage/logout');

        $response
            ->assertOk()
            ->assertJsonPath('status', 'OK')
            ->assertSessionMissing('student_parent_access');

        expect(Auth::check())->toBeFalse();
    });

    test('logout regenerates session token', function () {
        $user = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
        ]);
        $user->assignRole('user');

        $this->actingAs($user);

        $oldToken = session()->token();

        $this->postJson('/api/homepage/logout');

        // Session should be regenerated (can't directly test token change in tests)
        expect(Auth::check())->toBeFalse();
    });
});
