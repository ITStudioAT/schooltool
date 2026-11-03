<?php

/**
 * SchoolToolController Tests
 *
 * Tests the SchoolTool management controller including:
 * - loadConfig (load school tool configuration)
 * - saveModuleStatuses (save school module visibility states)
 *
 * Endpoints require admin, teacher, or register_admin roles
 */

use App\Http\Middleware\ToolLicensed;
use App\Models\Licence;
use App\Models\School;
use App\Models\SchoolTool;
use App\Models\Schoolyear;
use App\Models\User;
use App\Services\SchoolToolModuleStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function moduleVisibilityKeys(): array
{
    return [
        'register_visible_admin',
        'register_visible_user',
        'register_user_test_mode',
        'register_user_comming_soon',
        'teaching_visible_admin',
        'teaching_visible_user',
        'teaching_user_test_mode',
        'teaching_user_comming_soon',
        'materials_visible_admin',
        'materials_visible_user',
        'materials_user_test_mode',
        'materials_user_comming_soon',
        'restaurant_visible_admin',
        'restaurant_visible_user',
        'restaurant_user_test_mode',
        'restaurant_user_comming_soon',
        'aba_visible_admin',
        'aba_visible_user',
        'aba_user_test_mode',
        'aba_user_comming_soon',
    ];
}

beforeEach(function () {
    $this->school = School::factory()->create();
    $this->schoolyear = Schoolyear::factory()->create(['school_id' => $this->school->id]);

    foreach ([
        ['name' => 'ABA', 'long_name' => 'ABA'],
        ['name' => 'Anmeldetool', 'long_name' => 'Anmeldetool'],
        ['name' => 'Lehrertool', 'long_name' => 'Lehrertool'],
        ['name' => 'Materialientool', 'long_name' => 'Materialientool'],
        ['name' => 'Restaurant', 'long_name' => 'Restaurant'],
    ] as $licenceData) {
        Licence::firstOrCreate(
            ['name' => $licenceData['name']],
            ['long_name' => $licenceData['long_name'], 'is_selectable' => true]
        );
    }

    $this->teachingLicence = Licence::firstOrCreate(
        ['name' => 'Lehrertool'],
        ['long_name' => 'Lehrertool', 'is_selectable' => true]
    );
    $this->school->licences()->attach($this->teachingLicence->id, [
        'valid_until' => now()->addYear()->toDateString(),
    ]);

    // Create roles
    Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'register_admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);

    // Create school tool with ID 1 using DB insert to force the ID
    DB::table('school_tools')->insert([
        'id' => 1,
        'school_id' => $this->school->id,
        'register_visible_admin' => true,
        'register_visible_user' => true,
        'register_user_test_mode' => false,
        'register_user_comming_soon' => false,
        'teaching_visible_admin' => false,
        'teaching_visible_user' => false,
        'teaching_user_test_mode' => false,
        'teaching_user_comming_soon' => false,
        'materials_visible_admin' => false,
        'materials_visible_user' => false,
        'materials_user_test_mode' => false,
        'materials_user_comming_soon' => false,
        'restaurant_visible_admin' => false,
        'restaurant_visible_user' => false,
        'restaurant_user_test_mode' => false,
        'restaurant_user_comming_soon' => false,
        'aba_visible_admin' => true,
        'aba_visible_user' => true,
        'aba_user_test_mode' => false,
        'aba_user_comming_soon' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->schoolTool = SchoolTool::find(1);

    $this->admin = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
    ]);
    $this->admin->assignRole('admin');

    $this->teacherUser = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
    ]);
    $this->teacherUser->assignRole('teacher');

    $this->registerAdmin = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
    ]);
    $this->registerAdmin->assignRole('register_admin');

    $this->regularUser = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
    ]);
    $this->regularUser->assignRole('user');
});

describe('loadConfig', function () {
    test('admin can load school tool config', function () {
        $this->actingAs($this->admin);

        $response = $this->getJson('/api/admin/school_tools/load_config');

        $response->assertStatus(200)
            ->assertJsonStructure(array_merge([
                'id',
                'module_rows',
            ], moduleVisibilityKeys()))
            ->assertJson([
                'id' => 1,
            ]);

        expect(collect($response->json('module_rows'))->pluck('key')->all())
            ->toBe(['aba', 'register', 'teaching', 'materials', 'restaurant']);
    });

    test('teacher cannot load global school tool config', function () {
        $this->actingAs($this->teacherUser);

        $response = $this->getJson('/api/admin/school_tools/load_config');

        $response->assertForbidden();
    });

    test('register admin can load school tool config', function () {
        $this->actingAs($this->registerAdmin);

        $response = $this->getJson('/api/admin/school_tools/load_config');

        $response->assertStatus(200)
            ->assertJsonStructure(array_merge([
                'id',
            ], moduleVisibilityKeys()));
    });

    test('load config keeps school metadata but returns app-wide module settings', function () {
        $otherSchool = School::factory()->create();
        $otherSchool->licences()->attach($this->teachingLicence->id, [
            'valid_until' => now()->addYear()->toDateString(),
        ]);
        $otherSchoolyear = Schoolyear::factory()->create(['school_id' => $otherSchool->id]);
        $otherSchoolTool = SchoolTool::create([
            'school_id' => $otherSchool->id,
            'register_visible_user' => false,
        ]);

        $otherAdmin = User::factory()->create([
            'school_id' => $otherSchool->id,
            'schoolyear_id' => $otherSchoolyear->id,
        ]);
        $otherAdmin->assignRole('admin');

        $this->actingAs($otherAdmin);

        $response = $this->getJson('/api/admin/school_tools/load_config');

        $response->assertStatus(200)
            ->assertJson([
                'id' => $otherSchoolTool->id,
                'register_visible_user' => true,
            ]);
    });

    test('load config returns correct data structure', function () {
        $this->actingAs($this->admin);

        $response = $this->getJson('/api/admin/school_tools/load_config');

        $response->assertStatus(200);

        $data = $response->json();

        expect($data)->toHaveKeys([
            'id',
            ...moduleVisibilityKeys(),
        ])->not->toHaveKey('may_visible_for_other_schools');
    });

    test('load config denies access for regular user', function () {
        $this->actingAs($this->regularUser);

        $response = $this->getJson('/api/admin/school_tools/load_config');

        $response->assertStatus(403);
    });

    test('load config requires authentication', function () {
        $response = $this->getJson('/api/admin/school_tools/load_config');

        $response->assertStatus(401);
    });

    test('load config creates school tool if it does not exist', function () {
        $this->actingAs($this->admin);

        SchoolTool::where('id', 1)->delete();

        $response = $this->getJson('/api/admin/school_tools/load_config');

        $response->assertStatus(200)
            ->assertJsonStructure(array_merge([
                'id',
            ], moduleVisibilityKeys()))
            ->assertJson([
                'aba_visible_admin' => true,
                'aba_visible_user' => true,
                'register_visible_admin' => true,
                'register_visible_user' => true,
                'teaching_visible_admin' => false,
                'teaching_visible_user' => false,
            ]);

        $this->assertDatabaseHas('school_tools', [
            'id' => $response->json('id'),
            'school_id' => $this->school->id,
            'aba_visible_admin' => true,
            'aba_visible_user' => true,
            'register_visible_admin' => true,
            'register_visible_user' => true,
        ]);
    });
});

describe('saveModuleStatuses', function () {
    test('admin can save module statuses', function () {
        $this->actingAs($this->admin);

        $payload = [
            'data' => [
                'id' => 1,
                'register_visible_admin' => true,
                'register_visible_user' => false,
                'register_user_test_mode' => false,
                'register_user_comming_soon' => true,
                'teaching_visible_admin' => true,
                'teaching_visible_user' => true,
                'teaching_user_test_mode' => false,
                'teaching_user_comming_soon' => false,
                'materials_visible_admin' => false,
                'materials_visible_user' => false,
                'materials_user_test_mode' => false,
                'materials_user_comming_soon' => false,
                'restaurant_visible_admin' => true,
                'restaurant_visible_user' => true,
                'restaurant_user_test_mode' => false,
                'restaurant_user_comming_soon' => false,
                'aba_visible_admin' => true,
                'aba_visible_user' => true,
                'aba_user_test_mode' => false,
                'aba_user_comming_soon' => false,
                'students_timetables_admin_version' => 'v3',
            ],
        ];

        $response = $this->postJson('/api/admin/school_tools/save_module_statuses', $payload);

        $response->assertOk()
            ->assertJsonPath('register_visible_user', false)
            ->assertJsonPath('register_user_comming_soon', true)
            ->assertJsonMissingPath('tutoring_user_test_mode')
            ->assertJsonPath('restaurant_visible_admin', true)
            ->assertJsonPath('restaurant_visible_user', true)
            ->assertJsonPath('students_timetables_admin_version', 'v3');

        $this->assertDatabaseHas('school_tools', [
            'id' => 1,
            'register_visible_admin' => true,
            'register_visible_user' => false,
            'register_user_test_mode' => false,
            'register_user_comming_soon' => true,
            'teaching_visible_admin' => true,
            'teaching_visible_user' => true,
            'materials_visible_admin' => false,
            'restaurant_visible_admin' => true,
            'restaurant_visible_user' => true,
            'aba_visible_admin' => true,
            'aba_visible_user' => true,
            'students_timetables_admin_version' => 'v3',
        ]);

        $this->getJson('/api/admin/config')
            ->assertOk()
            ->assertJsonPath('students_timetables.admin_version', 'v3');
    });

    test('save module statuses only updates the authenticated school', function () {
        $otherSchool = School::factory()->create();
        $otherSchoolTool = SchoolTool::factory()->create([
            'school_id' => $otherSchool->id,
            'register_visible_admin' => false,
            'register_visible_user' => false,
        ]);

        $this->actingAs($this->admin);

        $this->postJson('/api/admin/school_tools/save_module_statuses', [
            'data' => [
                'id' => 1,
                'register_visible_admin' => true,
                'register_visible_user' => true,
                'register_user_test_mode' => false,
                'register_user_comming_soon' => false,
                'teaching_visible_admin' => false,
                'teaching_visible_user' => false,
                'teaching_user_test_mode' => false,
                'teaching_user_comming_soon' => false,
                'materials_visible_admin' => false,
                'materials_visible_user' => false,
                'materials_user_test_mode' => false,
                'materials_user_comming_soon' => false,
                'restaurant_visible_admin' => false,
                'restaurant_visible_user' => false,
                'restaurant_user_test_mode' => false,
                'restaurant_user_comming_soon' => false,
                'aba_visible_admin' => true,
                'aba_visible_user' => true,
                'aba_user_test_mode' => false,
                'aba_user_comming_soon' => false,
            ],
        ])->assertOk();

        $this->assertDatabaseHas('school_tools', [
            'id' => $otherSchoolTool->id,
            'register_visible_admin' => false,
            'register_visible_user' => false,
        ]);
    });

    test('save module statuses denies teacher', function () {
        $this->actingAs($this->teacherUser);

        $response = $this->postJson('/api/admin/school_tools/save_module_statuses', [
            'data' => [
                'id' => 1,
                'register_visible_admin' => true,
                'register_visible_user' => true,
                'register_user_test_mode' => false,
                'register_user_comming_soon' => false,
                'teaching_visible_admin' => true,
                'teaching_visible_user' => true,
                'teaching_user_test_mode' => false,
                'teaching_user_comming_soon' => false,
                'materials_visible_admin' => true,
                'materials_visible_user' => true,
                'materials_user_test_mode' => false,
                'materials_user_comming_soon' => false,
                'restaurant_visible_admin' => true,
                'restaurant_visible_user' => true,
                'restaurant_user_test_mode' => false,
                'restaurant_user_comming_soon' => false,
                'aba_visible_admin' => true,
                'aba_visible_user' => true,
                'aba_user_test_mode' => false,
                'aba_user_comming_soon' => false,
            ],
        ]);

        $response->assertStatus(403);
    });

    test('save module statuses validates booleans', function () {
        $this->actingAs($this->admin);

        $response = $this->postJson('/api/admin/school_tools/save_module_statuses', [
            'data' => [
                'id' => 1,
                'register_visible_admin' => 'beta',
                'register_visible_user' => true,
                'register_user_test_mode' => false,
                'register_user_comming_soon' => false,
                'teaching_visible_admin' => true,
                'teaching_visible_user' => true,
                'teaching_user_test_mode' => false,
                'teaching_user_comming_soon' => false,
                'materials_visible_admin' => true,
                'materials_visible_user' => true,
                'materials_user_test_mode' => false,
                'materials_user_comming_soon' => false,
                'restaurant_visible_admin' => true,
                'restaurant_visible_user' => true,
                'restaurant_user_test_mode' => false,
                'restaurant_user_comming_soon' => false,
                'aba_visible_admin' => true,
                'aba_visible_user' => true,
                'aba_user_test_mode' => false,
                'aba_user_comming_soon' => false,
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['data.register_visible_admin']);
    });

    test('save module statuses forces user visibility off when admin visibility is off', function () {
        $this->actingAs($this->admin);

        $response = $this->postJson('/api/admin/school_tools/save_module_statuses', [
            'data' => [
                'id' => 1,
                'register_visible_admin' => false,
                'register_visible_user' => true,
                'register_user_test_mode' => false,
                'register_user_comming_soon' => false,
                'teaching_visible_admin' => true,
                'teaching_visible_user' => true,
                'teaching_user_test_mode' => false,
                'teaching_user_comming_soon' => false,
                'materials_visible_admin' => true,
                'materials_visible_user' => true,
                'materials_user_test_mode' => false,
                'materials_user_comming_soon' => false,
                'restaurant_visible_admin' => true,
                'restaurant_visible_user' => true,
                'restaurant_user_test_mode' => false,
                'restaurant_user_comming_soon' => false,
                'aba_visible_admin' => false,
                'aba_visible_user' => true,
                'aba_user_test_mode' => false,
                'aba_user_comming_soon' => false,
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('aba_visible_admin', false)
            ->assertJsonPath('aba_visible_user', false)
            ->assertJsonPath('register_visible_admin', false)
            ->assertJsonPath('register_visible_user', false);

        $this->assertDatabaseHas('school_tools', [
            'id' => 1,
            'aba_visible_admin' => false,
            'aba_visible_user' => false,
            'register_visible_admin' => false,
            'register_visible_user' => false,
        ]);
    });
});

describe('removed tutoring settings', function () {
    test('removed settings endpoint cannot mutate either school', function (?string $actor): void {
        $otherSchoolTool = SchoolTool::factory()->create([
            'school_id' => School::factory()->create()->id,
        ]);
        $before = SchoolTool::query()->orderBy('id')->get()->toArray();

        if ($actor !== null) {
            $this->actingAs($this->{$actor});
        }

        foreach ([$this->schoolTool->id, $otherSchoolTool->id] as $id) {
            $this->postJson('/api/admin/school_tools/save_tutoring_settings', [
                'data' => [
                    'id' => $id,
                    'tutoring_student_must_be_confirmed' => true,
                    'tutoring_confirmer_email' => 'obsolete@example.test',
                    'may_visible_for_other_schools' => true,
                    'register_visible_admin' => false,
                ],
            ])->assertNotFound();
        }

        expect(SchoolTool::query()->orderBy('id')->get()->toArray())->toBe($before);
    })->with([
        'admin' => ['admin'],
        'teacher' => ['teacherUser'],
        'register admin' => ['registerAdmin'],
        'regular user' => ['regularUser'],
        'guest' => [null],
    ]);

    test('removed settings endpoint rejects obsolete malformed payloads without changes', function (array $data): void {
        $before = $this->schoolTool->fresh()->toArray();

        $this->actingAs($this->admin)
            ->postJson('/api/admin/school_tools/save_tutoring_settings', $data)
            ->assertNotFound();

        expect($this->schoolTool->fresh()->toArray())->toBe($before);
    })->with([
        'missing data' => [[]],
        'missing id' => [['data' => ['tutoring_student_must_be_confirmed' => true]]],
        'unknown id' => [['data' => ['id' => 99999]]],
        'invalid email' => [['data' => ['id' => 1, 'tutoring_confirmer_email' => 'invalid-email']]],
        'nullable email' => [['data' => ['id' => 1, 'tutoring_confirmer_email' => null]]],
    ]);
});

test('set active schoolyear rejects a schoolyear from another school', function () {
    $otherSchool = School::factory()->create();
    $otherSchoolyear = Schoolyear::factory()->create([
        'school_id' => $otherSchool->id,
    ]);

    $this->actingAs($this->admin);

    $this->postJson('/api/admin/school_tools/set_active_schoolyear', [
        'schoolyear_id' => $otherSchoolyear->id,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['schoolyear_id']);

    expect($this->schoolTool->fresh()->active_schoolyear_id)->toBeNull();
});

test('super admin can set the active schoolyear for their school', function () {
    $superAdmin = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
    ]);
    $superAdmin->assignRole('super_admin');

    $this->actingAs($superAdmin)
        ->postJson('/api/admin/school_tools/set_active_schoolyear', [
            'schoolyear_id' => $this->schoolyear->id,
        ])
        ->assertSuccessful();

    expect($this->schoolTool->fresh()->active_schoolyear_id)->toBe($this->schoolyear->id);
});

test('regular user cannot set the active schoolyear', function () {
    $this->actingAs($this->regularUser)
        ->postJson('/api/admin/school_tools/set_active_schoolyear', [
            'schoolyear_id' => $this->schoolyear->id,
        ])
        ->assertForbidden();

    expect($this->schoolTool->fresh()->active_schoolyear_id)->toBeNull();
});

test('saving from another school applies the SEPP user state app-wide without changing school metadata', function (
    bool $visible,
    bool $testMode,
    bool $comingSoon,
    string $status,
    int $accessStatus,
): void {
    $otherSchool = School::factory()->create(['is_selectable' => true]);
    $otherSchoolyear = Schoolyear::factory()->create(['school_id' => $otherSchool->id]);
    $otherSchoolTool = SchoolTool::factory()->create([
        'school_id' => $otherSchool->id,
        'active_schoolyear_id' => $otherSchoolyear->id,
        'students_timetables_visible_admin' => true,
        'students_timetables_visible_user' => true,
        'students_timetables_admin_version' => 'v2',
        'restaurant_service_email' => 'school@example.test',
    ]);
    $otherAdmin = User::factory()->create([
        'school_id' => $otherSchool->id,
        'schoolyear_id' => $otherSchoolyear->id,
    ]);
    $otherAdmin->assignRole('admin');
    $this->actingAs($otherAdmin);

    $configuration = $this->getJson('/api/admin/school_tools/load_config')
        ->assertOk()
        ->assertJsonPath('id', $otherSchoolTool->id)
        ->assertJsonPath('students_timetables_visible_user', false)
        ->json();
    $payload = collect($configuration)->only(app(SchoolToolModuleStatusService::class)->moduleVisibilityFields())->all();
    $seppFlags = [
        'students_timetables_visible_admin' => true,
        'students_timetables_visible_user' => $visible,
        'students_timetables_user_test_mode' => $testMode,
        'students_timetables_user_comming_soon' => $comingSoon,
    ];

    $this->postJson('/api/admin/school_tools/save_module_statuses', [
        'data' => ['id' => $otherSchoolTool->id, ...$payload, ...$seppFlags],
    ])->assertOk()
        ->assertJsonPath('students_timetables_visible_user', $visible)
        ->assertJsonPath('students_timetables_user_test_mode', $testMode)
        ->assertJsonPath('students_timetables_user_comming_soon', $comingSoon)
        ->assertJsonPath('active_schoolyear_id', $otherSchoolyear->id)
        ->assertJsonPath('students_timetables_admin_version', 'v2');

    $this->assertDatabaseHas('school_tools', ['id' => $this->schoolTool->id, ...$seppFlags]);
    $this->assertDatabaseHas('school_tools', [
        'id' => $otherSchoolTool->id,
        'students_timetables_visible_user' => true,
        'active_schoolyear_id' => $otherSchoolyear->id,
        'students_timetables_admin_version' => 'v2',
        'restaurant_service_email' => 'school@example.test',
    ]);

    foreach ([$this->school, $otherSchool] as $school) {
        grantSchoolToolLicenceForTests($school, 'StudentsTimetables');
        $this->getJson('/api/homepage/config?school='.$school->short_name)
            ->assertOk()
            ->assertJsonPath('tool_module_statuses.students_timetables', $status)
            ->assertJsonPath('students_timetables_active', $accessStatus === 200)
            ->assertJsonPath('tool_module_statuses.register', 'active');

        $request = Request::create('/api/homepage/students-timetables/overview', 'GET', ['school_id' => $school->id]);
        $response = app(ToolLicensed::class)->handle(
            $request,
            fn () => response()->json(['allowed' => true]),
            'StudentsTimetables',
            'auto',
            'studentstimetables_user',
        );
        expect($response->getStatusCode())->toBe($accessStatus);
    }

    $this->actingAs($this->admin)
        ->getJson('/api/admin/school_tools/load_config')
        ->assertOk()
        ->assertJsonPath('students_timetables_visible_user', $visible)
        ->assertJsonPath('students_timetables_user_test_mode', $testMode)
        ->assertJsonPath('students_timetables_user_comming_soon', $comingSoon);
})->with([
    'Aus' => [false, false, false, 'inactive', 403],
    'Anzeige' => [true, false, false, 'active', 200],
    'Testmodus' => [false, true, false, 'test_modus', 200],
    'Kommt bald' => [false, false, true, 'comming_soon', 403],
]);

test('saving a payload without optional SEPP flags preserves the app-wide SEPP setting', function (): void {
    $this->schoolTool->update([
        'students_timetables_visible_admin' => true,
        'students_timetables_visible_user' => true,
    ]);
    $payload = $this->schoolTool->only(moduleVisibilityKeys());

    $this->actingAs($this->admin)
        ->postJson('/api/admin/school_tools/save_module_statuses', ['data' => ['id' => 1, ...$payload]])
        ->assertOk()
        ->assertJsonPath('students_timetables_visible_user', true);

    $this->assertDatabaseHas('school_tools', ['id' => 1, 'students_timetables_visible_user' => true]);
});

test('app-wide SEPP access still requires the selected schools licence', function (bool $testMode): void {
    $this->schoolTool->update([
        'students_timetables_visible_admin' => true,
        'students_timetables_visible_user' => ! $testMode,
        'students_timetables_user_test_mode' => $testMode,
    ]);
    $this->actingAs($this->admin);
    $request = Request::create('/api/homepage/students-timetables/overview', 'GET', ['school_id' => $this->school->id]);

    $response = app(ToolLicensed::class)->handle(
        $request,
        fn () => response()->json(['allowed' => true]),
        'StudentsTimetables',
        'auto',
        'studentstimetables_user',
    );

    expect($response->getStatusCode())->toBe(403);
    expect($response->getContent())->toContain('Lizenz nicht vorhanden.');
})->with(['Anzeige' => false, 'Testmodus' => true]);

test('app-wide module states govern both schools user and admin access independently', function (
    string $module,
    string $licence,
    bool $visible,
    bool $testMode,
    bool $comingSoon,
    string $status,
    int $accessStatus,
): void {
    $otherSchool = School::factory()->create(['is_selectable' => true]);
    $otherSchoolTool = SchoolTool::factory()->create([
        'school_id' => $otherSchool->id,
        "{$module}_visible_admin" => false,
        "{$module}_visible_user" => true,
        "{$module}_user_test_mode" => false,
        "{$module}_user_comming_soon" => false,
    ]);
    $moduleStatusService = app(SchoolToolModuleStatusService::class);
    $payload = $this->schoolTool->only($moduleStatusService->moduleVisibilityFields());
    $flags = [
        "{$module}_visible_admin" => true,
        "{$module}_visible_user" => $visible,
        "{$module}_user_test_mode" => $testMode,
        "{$module}_user_comming_soon" => $comingSoon,
    ];
    $this->actingAs($this->admin)
        ->postJson('/api/admin/school_tools/save_module_statuses', [
            'data' => ['id' => $this->schoolTool->id, ...$payload, ...$flags],
        ])->assertOk()
        ->assertJsonPath("{$module}_visible_user", $visible)
        ->assertJsonPath("{$module}_user_test_mode", $testMode)
        ->assertJsonPath("{$module}_user_comming_soon", $comingSoon);
    $this->assertDatabaseHas('school_tools', ['id' => $this->schoolTool->id, ...$flags]);

    foreach ([$this->school, $otherSchool] as $school) {
        grantSchoolToolLicenceForTests($school, $licence);
        $schoolUser = User::factory()->create(['school_id' => $school->id]);
        $schoolUser->assignRole('admin');
        $this->actingAs($schoolUser);

        $statuses = app(SchoolToolModuleStatusService::class);
        expect($statuses->userStatusForModule($module, $school))->toBe($status)
            ->and($statuses->adminVisibleForModule($module, $school))->toBeTrue();

        $userRequest = Request::create("/api/homepage/{$module}/visibility-check", 'GET', ['school_id' => $school->id]);
        $userResponse = app(ToolLicensed::class)->handle($userRequest, fn () => response('allowed'), $licence);
        expect($userResponse->getStatusCode())->toBe($accessStatus);

        $adminRequest = Request::create("/api/admin/{$module}/visibility-check");
        $adminResponse = app(ToolLicensed::class)->handle($adminRequest, fn () => response('allowed'), $licence, 'auth');
        expect($adminResponse->getStatusCode())->toBe(200);

        if ($module !== 'aba') {
            $this->getJson('/api/homepage/config?school='.$school->short_name)
                ->assertOk()
                ->assertJsonPath("tool_module_statuses.{$module}", $status)
                ->assertJsonPath("{$module}_active", $accessStatus === 200);
        }
    }

    $this->assertDatabaseHas('school_tools', [
        'id' => $otherSchoolTool->id,
        "{$module}_visible_admin" => false,
        "{$module}_visible_user" => true,
    ]);
})->with([
    'Anmeldetool' => ['register', 'Anmeldetool'],
    'Unterricht' => ['teaching', 'Lehrertool'],
    'Materialien' => ['materials', 'Materialientool'],
    'Restaurant' => ['restaurant', 'Restaurant'],
    'ABA' => ['aba', 'ABA'],
    'SEPP' => ['students_timetables', 'StudentsTimetables'],
])->with([
    'Aus' => [false, false, false, 'inactive', 403],
    'Anzeige' => [true, false, false, 'active', 200],
    'Testmodus' => [false, true, false, 'test_modus', 200],
    'Kommt bald' => [false, false, true, 'comming_soon', 403],
]);

test('the app-wide admin switch blocks module access despite conflicting school flags', function (
    string $module,
    string $licence,
): void {
    $this->schoolTool->update(["{$module}_visible_admin" => false, "{$module}_visible_user" => false]);
    $otherSchool = School::factory()->create();
    SchoolTool::factory()->create([
        'school_id' => $otherSchool->id,
        "{$module}_visible_admin" => true,
        "{$module}_visible_user" => true,
    ]);

    foreach ([$this->school, $otherSchool] as $school) {
        grantSchoolToolLicenceForTests($school, $licence);
        $schoolUser = User::factory()->create(['school_id' => $school->id]);
        $schoolUser->assignRole('admin');
        $this->actingAs($schoolUser);

        $apiRequest = Request::create("/api/admin/{$module}/visibility-check");
        $apiResponse = app(ToolLicensed::class)->handle($apiRequest, fn () => response('allowed'), $licence, 'auth');
        expect($apiResponse->getStatusCode())->toBe(403);

        $webRequest = Request::create("/admin/{$module}/visibility-check");
        $webResponse = app(ToolLicensed::class)->handle($webRequest, fn () => response('allowed'), $licence, 'auth');
        expect($webResponse->getStatusCode())->toBe(302)
            ->and($webResponse->headers->get('Location'))->toBe(url('/admin'));
    }
})->with([
    'Anmeldetool' => ['register', 'Anmeldetool'],
    'Unterricht' => ['teaching', 'Lehrertool'],
    'Materialien' => ['materials', 'Materialientool'],
    'Restaurant' => ['restaurant', 'Restaurant'],
    'ABA' => ['aba', 'ABA'],
    'SEPP' => ['students_timetables', 'StudentsTimetables'],
]);

test('module settings expose which modules have an implemented user view', function (): void {
    Licence::create(['name' => 'StudentsTimetables', 'long_name' => 'SEPP']);

    $response = $this->actingAs($this->admin)->getJson('/api/admin/school_tools/load_config')->assertOk();

    expect(collect($response->json('module_rows'))->pluck('hasUserView', 'key')->all())->toEqual([
        'aba' => false,
        'register' => true,
        'teaching' => true,
        'materials' => false,
        'restaurant' => true,
        'students_timetables' => true,
    ]);
});
