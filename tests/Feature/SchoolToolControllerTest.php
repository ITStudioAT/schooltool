<?php

/**
 * SchoolToolController Tests
 *
 * Tests the SchoolTool management controller including:
 * - loadConfig (load school tool configuration)
 * - saveTutoringSettings (save tutoring-specific settings)
 * - saveModuleStatuses (save school module visibility states)
 *
 * Endpoints require admin, tutoring_admin, or register_admin roles
 */

use App\Models\Licence;
use App\Models\School;
use App\Models\SchoolTool;
use App\Models\Schoolyear;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        'tutoring_visible_admin',
        'tutoring_visible_user',
        'tutoring_user_test_mode',
        'tutoring_user_comming_soon',
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
        ['name' => 'Nachhilfetool', 'long_name' => 'Nachhilfetool'],
        ['name' => 'Lehrertool', 'long_name' => 'Lehrertool'],
        ['name' => 'Materialientool', 'long_name' => 'Materialientool'],
        ['name' => 'Restaurant', 'long_name' => 'Restaurant'],
    ] as $licenceData) {
        Licence::firstOrCreate(
            ['name' => $licenceData['name']],
            ['long_name' => $licenceData['long_name'], 'is_selectable' => true]
        );
    }

    $this->tutoringLicence = Licence::firstOrCreate(
        ['name' => 'Nachhilfetool'],
        ['long_name' => 'Nachhilfetool', 'is_selectable' => true]
    );
    $this->school->licences()->attach($this->tutoringLicence->id, [
        'valid_until' => now()->addYear()->toDateString(),
    ]);

    // Create roles
    Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'tutoring_admin', 'guard_name' => 'web']);
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
        'tutoring_visible_admin' => true,
        'tutoring_visible_user' => true,
        'tutoring_user_test_mode' => false,
        'tutoring_user_comming_soon' => false,
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
        'tutoring_student_must_be_confirmed' => 0,
        'tutoring_confirmer_email' => 'admin@test.com',
        'tutoring_max_offers_per_student' => 0,
        'may_visible_for_other_schools' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->schoolTool = SchoolTool::find(1);

    $this->admin = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
    ]);
    $this->admin->assignRole('admin');

    $this->tutoringAdmin = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
    ]);
    $this->tutoringAdmin->assignRole('tutoring_admin');

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
                'tutoring_student_must_be_confirmed',
                'tutoring_confirmer_email',
                'may_visible_for_other_schools',
            ], moduleVisibilityKeys()))
            ->assertJson([
                'id' => 1,
            ]);

        expect(collect($response->json('module_rows'))->pluck('key')->all())
            ->toBe(['aba', 'register', 'teaching', 'materials', 'tutoring', 'restaurant']);
    });

    test('tutoring admin can load school tool config', function () {
        $this->actingAs($this->tutoringAdmin);

        $response = $this->getJson('/api/admin/school_tools/load_config');

        $response->assertStatus(200)
            ->assertJsonStructure(array_merge([
                'id',
                'tutoring_student_must_be_confirmed',
                'tutoring_confirmer_email',
                'may_visible_for_other_schools',
            ], moduleVisibilityKeys()));
    });

    test('register admin can load school tool config', function () {
        $this->actingAs($this->registerAdmin);

        $response = $this->getJson('/api/admin/school_tools/load_config');

        $response->assertStatus(200)
            ->assertJsonStructure(array_merge([
                'id',
                'tutoring_student_must_be_confirmed',
                'tutoring_confirmer_email',
                'may_visible_for_other_schools',
            ], moduleVisibilityKeys()));
    });

    test('load config returns school tool of authenticated users school', function () {
        $otherSchool = School::factory()->create();
        $otherSchool->licences()->attach($this->tutoringLicence->id, [
            'valid_until' => now()->addYear()->toDateString(),
        ]);
        $otherSchoolyear = Schoolyear::factory()->create(['school_id' => $otherSchool->id]);
        $otherSchoolTool = SchoolTool::create([
            'school_id' => $otherSchool->id,
            'tutoring_student_must_be_confirmed' => true,
            'tutoring_confirmer_email' => 'other@test.com',
            'tutoring_max_offers_per_student' => 3,
            'may_visible_for_other_schools' => true,
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
                'tutoring_confirmer_email' => 'other@test.com',
            ]);
    });

    test('load config returns correct data structure', function () {
        $this->actingAs($this->admin);

        $response = $this->getJson('/api/admin/school_tools/load_config');

        $response->assertStatus(200);

        $data = $response->json();

        expect($data)->toHaveKeys([
            'id',
            'tutoring_student_must_be_confirmed',
            'tutoring_confirmer_email',
            'may_visible_for_other_schools',
            ...moduleVisibilityKeys(),
        ]);
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
                'tutoring_student_must_be_confirmed',
                'tutoring_confirmer_email',
                'may_visible_for_other_schools',
            ], moduleVisibilityKeys()))
            ->assertJson([
                'aba_visible_admin' => true,
                'aba_visible_user' => true,
                'register_visible_admin' => true,
                'register_visible_user' => true,
                'tutoring_visible_admin' => false,
                'tutoring_visible_user' => false,
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
            'tutoring_visible_admin' => false,
            'tutoring_visible_user' => false,
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
                'tutoring_visible_admin' => true,
                'tutoring_visible_user' => false,
                'tutoring_user_test_mode' => true,
                'tutoring_user_comming_soon' => false,
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
            ],
        ];

        $response = $this->postJson('/api/admin/school_tools/save_module_statuses', $payload);

        $response->assertOk()
            ->assertJsonPath('register_visible_user', false)
            ->assertJsonPath('register_user_comming_soon', true)
            ->assertJsonPath('tutoring_user_test_mode', true)
            ->assertJsonPath('restaurant_visible_admin', true)
            ->assertJsonPath('restaurant_visible_user', true);

        $this->assertDatabaseHas('school_tools', [
            'id' => 1,
            'register_visible_admin' => true,
            'register_visible_user' => false,
            'register_user_test_mode' => false,
            'register_user_comming_soon' => true,
            'tutoring_visible_admin' => true,
            'tutoring_visible_user' => false,
            'tutoring_user_test_mode' => true,
            'tutoring_user_comming_soon' => false,
            'teaching_visible_admin' => true,
            'teaching_visible_user' => true,
            'materials_visible_admin' => false,
            'restaurant_visible_admin' => true,
            'restaurant_visible_user' => true,
            'aba_visible_admin' => true,
            'aba_visible_user' => true,
        ]);
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
                'tutoring_visible_admin' => false,
                'tutoring_visible_user' => false,
                'tutoring_user_test_mode' => false,
                'tutoring_user_comming_soon' => false,
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

    test('save module statuses denies tutoring admin', function () {
        $this->actingAs($this->tutoringAdmin);

        $response = $this->postJson('/api/admin/school_tools/save_module_statuses', [
            'data' => [
                'id' => 1,
                'register_visible_admin' => true,
                'register_visible_user' => true,
                'register_user_test_mode' => false,
                'register_user_comming_soon' => false,
                'tutoring_visible_admin' => true,
                'tutoring_visible_user' => true,
                'tutoring_user_test_mode' => false,
                'tutoring_user_comming_soon' => false,
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
                'tutoring_visible_admin' => true,
                'tutoring_visible_user' => true,
                'tutoring_user_test_mode' => false,
                'tutoring_user_comming_soon' => false,
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
                'tutoring_visible_admin' => true,
                'tutoring_visible_user' => true,
                'tutoring_user_test_mode' => false,
                'tutoring_user_comming_soon' => false,
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

describe('saveTutoringSettings', function () {
    test('admin can save tutoring settings', function () {
        $this->actingAs($this->admin);

        $updateData = [
            'data' => [
                'id' => 1,
                'tutoring_student_must_be_confirmed' => true,
                'tutoring_confirmer_email' => 'newemail@test.com',
                'may_visible_for_other_schools' => true,
            ],
        ];

        $response = $this->postJson('/api/admin/school_tools/save_tutoring_settings', $updateData);

        $response->assertStatus(200)
            ->assertJson([
                'id' => 1,
                'tutoring_student_must_be_confirmed' => true,
                'tutoring_confirmer_email' => 'newemail@test.com',
                'may_visible_for_other_schools' => true,
            ]);

        $this->assertDatabaseHas('school_tools', [
            'id' => 1,
            'tutoring_student_must_be_confirmed' => true,
            'tutoring_confirmer_email' => 'newemail@test.com',
            'may_visible_for_other_schools' => true,
        ]);
    });

    test('tutoring admin can save tutoring settings', function () {
        $this->actingAs($this->tutoringAdmin);

        $updateData = [
            'data' => [
                'id' => 1,
                'tutoring_student_must_be_confirmed' => true,
                'tutoring_confirmer_email' => 'tutoring@test.com',
                'may_visible_for_other_schools' => false,
            ],
        ];

        $response = $this->postJson('/api/admin/school_tools/save_tutoring_settings', $updateData);

        $response->assertStatus(200);

        $this->assertDatabaseHas('school_tools', [
            'id' => 1,
            'tutoring_confirmer_email' => 'tutoring@test.com',
            'may_visible_for_other_schools' => false,
        ]);
    });

    test('save tutoring settings updates existing record', function () {
        $this->actingAs($this->admin);

        $originalValue = $this->schoolTool->tutoring_student_must_be_confirmed;

        $updateData = [
            'data' => [
                'id' => 1,
                'tutoring_student_must_be_confirmed' => ! $originalValue,
                'tutoring_confirmer_email' => 'updated@test.com',
                'may_visible_for_other_schools' => true,
            ],
        ];

        $response = $this->postJson('/api/admin/school_tools/save_tutoring_settings', $updateData);

        $response->assertStatus(200);

        $this->schoolTool->refresh();

        expect((bool) $this->schoolTool->tutoring_student_must_be_confirmed)->toBe(! $originalValue);
    });

    test('save tutoring settings denies access for register admin', function () {
        $this->actingAs($this->registerAdmin);

        $updateData = [
            'data' => [
                'id' => 1,
                'tutoring_student_must_be_confirmed' => true,
                'tutoring_confirmer_email' => 'test@test.com',
                'may_visible_for_other_schools' => true,
            ],
        ];

        $response = $this->postJson('/api/admin/school_tools/save_tutoring_settings', $updateData);

        $response->assertStatus(403);
    });

    test('save tutoring settings denies access for regular user', function () {
        $this->actingAs($this->regularUser);

        $updateData = [
            'data' => [
                'id' => 1,
                'tutoring_student_must_be_confirmed' => true,
                'tutoring_confirmer_email' => 'test@test.com',
                'may_visible_for_other_schools' => true,
            ],
        ];

        $response = $this->postJson('/api/admin/school_tools/save_tutoring_settings', $updateData);

        $response->assertStatus(403);
    });

    test('save tutoring settings requires authentication', function () {
        $updateData = [
            'data' => [
                'id' => 1,
                'tutoring_student_must_be_confirmed' => true,
                'tutoring_confirmer_email' => 'test@test.com',
                'may_visible_for_other_schools' => true,
            ],
        ];

        $response = $this->postJson('/api/admin/school_tools/save_tutoring_settings', $updateData);

        $response->assertStatus(401);
    });

    test('save tutoring settings validates required data field', function () {
        $this->actingAs($this->admin);

        $response = $this->postJson('/api/admin/school_tools/save_tutoring_settings', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'data.id',
                'data.tutoring_student_must_be_confirmed',
                'data.may_visible_for_other_schools',
            ]);
    });

    test('save tutoring settings validates required id in data', function () {
        $this->actingAs($this->admin);

        $updateData = [
            'data' => [
                'tutoring_student_must_be_confirmed' => true,
                'tutoring_confirmer_email' => 'test@test.com',
                'may_visible_for_other_schools' => true,
            ],
        ];

        $response = $this->postJson('/api/admin/school_tools/save_tutoring_settings', $updateData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['data.id']);
    });

    test('save tutoring settings returns 422 for non existent school tool', function () {
        $this->actingAs($this->admin);

        $updateData = [
            'data' => [
                'id' => 99999,
                'tutoring_student_must_be_confirmed' => true,
                'tutoring_confirmer_email' => 'test@test.com',
                'may_visible_for_other_schools' => true,
            ],
        ];

        $response = $this->postJson('/api/admin/school_tools/save_tutoring_settings', $updateData);

        // Validation fails before findOrFail is called
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['data.id']);
    });

    test('save tutoring settings validates email format', function () {
        $this->actingAs($this->admin);

        $updateData = [
            'data' => [
                'id' => 1,
                'tutoring_student_must_be_confirmed' => true,
                'tutoring_confirmer_email' => 'invalid-email',
                'may_visible_for_other_schools' => true,
            ],
        ];

        $response = $this->postJson('/api/admin/school_tools/save_tutoring_settings', $updateData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['data.tutoring_confirmer_email']);
    });

    test('save tutoring settings allows nullable email', function () {
        $this->actingAs($this->admin);

        $updateData = [
            'data' => [
                'id' => 1,
                'tutoring_student_must_be_confirmed' => false,
                'tutoring_confirmer_email' => null,
                'may_visible_for_other_schools' => false,
            ],
        ];

        $response = $this->postJson('/api/admin/school_tools/save_tutoring_settings', $updateData);

        $response->assertStatus(200);

        $this->assertDatabaseHas('school_tools', [
            'id' => 1,
            'tutoring_confirmer_email' => null,
            'may_visible_for_other_schools' => false,
        ]);
    });

    test('save tutoring settings rejects a school tool from another school', function () {
        $otherSchool = School::factory()->create();
        $otherSchoolTool = SchoolTool::factory()->create([
            'school_id' => $otherSchool->id,
            'tutoring_student_must_be_confirmed' => false,
            'tutoring_confirmer_email' => 'other@test.com',
            'may_visible_for_other_schools' => false,
        ]);

        $this->actingAs($this->tutoringAdmin);

        $this->postJson('/api/admin/school_tools/save_tutoring_settings', [
            'data' => [
                'id' => $otherSchoolTool->id,
                'tutoring_student_must_be_confirmed' => true,
                'tutoring_confirmer_email' => 'attacker@test.com',
                'may_visible_for_other_schools' => true,
            ],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['data.id']);

        $this->assertDatabaseHas('school_tools', [
            'id' => $otherSchoolTool->id,
            'tutoring_student_must_be_confirmed' => false,
            'tutoring_confirmer_email' => 'other@test.com',
            'may_visible_for_other_schools' => false,
        ]);
    });
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
