<?php

/**
 * SchoolToolController Tests
 *
 * Tests the SchoolTool management controller including:
 * - loadConfig (load school tool configuration)
 * - saveTutoringSettings (save tutoring-specific settings)
 *
 * Endpoints require admin, tutoring_admin, or register_admin roles
 */

use App\Models\School;
use App\Models\SchoolTool;
use App\Models\Schoolyear;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->school = School::factory()->create();
    $this->schoolyear = Schoolyear::factory()->create(['school_id' => $this->school->id]);

    // Create roles
    Role::create(['name' => 'super_admin', 'guard_name' => 'web']);
    Role::create(['name' => 'admin', 'guard_name' => 'web']);
    Role::create(['name' => 'tutoring_admin', 'guard_name' => 'web']);
    Role::create(['name' => 'register_admin', 'guard_name' => 'web']);
    Role::create(['name' => 'user', 'guard_name' => 'web']);

    // Create school tool
    $this->schoolTool = SchoolTool::create([
        'id' => 1,
        'school_id' => $this->school->id,
        'tutoring_student_must_be_confirmed' => false,
        'tutoring_confirmer_email' => 'admin@test.com',
    ]);

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
            ->assertJsonStructure([
                'id',
                'tutoring_student_must_be_confirmed',
                'tutoring_confirmer_email',
            ])
            ->assertJson([
                'id' => 1,
            ]);
    });

    test('tutoring admin can load school tool config', function () {
        $this->actingAs($this->tutoringAdmin);

        $response = $this->getJson('/api/admin/school_tools/load_config');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'id',
                'tutoring_student_must_be_confirmed',
                'tutoring_confirmer_email',
            ]);
    });

    test('register admin can load school tool config', function () {
        $this->actingAs($this->registerAdmin);

        $response = $this->getJson('/api/admin/school_tools/load_config');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'id',
                'tutoring_student_must_be_confirmed',
                'tutoring_confirmer_email',
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

    test('load config returns 404 if school tool does not exist', function () {
        $this->actingAs($this->admin);

        SchoolTool::where('id', 1)->delete();

        $response = $this->getJson('/api/admin/school_tools/load_config');

        $response->assertStatus(404);
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
            ],
        ];

        $response = $this->postJson('/api/admin/school_tools/save_tutoring_settings', $updateData);

        $response->assertStatus(200)
            ->assertJson([
                'id' => 1,
                'tutoring_student_must_be_confirmed' => true,
                'tutoring_confirmer_email' => 'newemail@test.com',
            ]);

        $this->assertDatabaseHas('school_tools', [
            'id' => 1,
            'tutoring_student_must_be_confirmed' => true,
            'tutoring_confirmer_email' => 'newemail@test.com',
        ]);
    });

    test('tutoring admin can save tutoring settings', function () {
        $this->actingAs($this->tutoringAdmin);

        $updateData = [
            'data' => [
                'id' => 1,
                'tutoring_student_must_be_confirmed' => true,
                'tutoring_confirmer_email' => 'tutoring@test.com',
            ],
        ];

        $response = $this->postJson('/api/admin/school_tools/save_tutoring_settings', $updateData);

        $response->assertStatus(200);

        $this->assertDatabaseHas('school_tools', [
            'id' => 1,
            'tutoring_confirmer_email' => 'tutoring@test.com',
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
            ],
        ];

        $response = $this->postJson('/api/admin/school_tools/save_tutoring_settings', $updateData);

        $response->assertStatus(401);
    });

    test('save tutoring settings validates required data field', function () {
        $this->actingAs($this->admin);

        $response = $this->postJson('/api/admin/school_tools/save_tutoring_settings', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['data.id', 'data.tutoring_student_must_be_confirmed']);
    });

    test('save tutoring settings validates required id in data', function () {
        $this->actingAs($this->admin);

        $updateData = [
            'data' => [
                'tutoring_student_must_be_confirmed' => true,
                'tutoring_confirmer_email' => 'test@test.com',
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
            ],
        ];

        $response = $this->postJson('/api/admin/school_tools/save_tutoring_settings', $updateData);

        $response->assertStatus(200);

        $this->assertDatabaseHas('school_tools', [
            'id' => 1,
            'tutoring_confirmer_email' => null,
        ]);
    });
});
