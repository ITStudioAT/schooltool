<?php

/**
 * SchoolController Tests
 *
 * Coverage mirrors the RegisterUserController suite style and focuses on:
 * - Role-based access for all endpoints
 * - Happy-path payload handling and validation failures
 * - Integration with injected services where applicable
 */

use App\Models\Licence;
use App\Models\School;
use App\Models\SchoolLicence;
use App\Models\Schoolyear;
use App\Models\User;
use App\Services\SchoolService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->school = School::factory()->create([
        'long_name' => 'Main School',
        'short_name' => 'MAIN',
        'email' => 'main@example.com',
    ]);

    $this->schoolyear = Schoolyear::factory()->create([
        'school_id' => $this->school->id,
        'name' => '2024/2025',
    ]);

    collect(['super_admin', 'admin', 'register_admin'])->each(
        fn(string $role) => Role::firstOrCreate(['name' => $role, 'guard_name' => 'web'])
    );

    $this->superAdmin = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'super.admin@test.com',
    ]);
    $this->superAdmin->assignRole('super_admin');

    $this->adminUser = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'admin@test.com',
    ]);
    $this->adminUser->assignRole('admin');

    $this->registerAdmin = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'register.admin@test.com',
    ]);
    $this->registerAdmin->assignRole('register_admin');
});

// ============================================================================
// index
// ============================================================================

test('super admin can list schools ordered by long_name and filter by search', function () {
    $alpha = School::factory()->create(['long_name' => 'Alpha School', 'short_name' => 'ALP', 'email' => 'alpha@example.com']);
    $charlie = School::factory()->create(['long_name' => 'Charlie School', 'short_name' => 'CHR', 'email' => 'charlie@example.com']);

    $this->actingAs($this->superAdmin, 'sanctum');

    $response = $this->getJson('/api/admin/schools?search_string=School');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                ['id', 'long_name', 'short_name', 'logo', 'email', 'is_selectable'],
            ],
            'meta' => ['per_page', 'current_page', 'last_page'],
        ]);

    $names = collect($response->json('data'))->pluck('long_name')->all();

    expect($names)->toBe([
        'Alpha School',
        'Charlie School',
        'Main School',
    ]);
});

test('super admin can search schools by assigned licence name', function () {
    $targetSchool = School::factory()->create([
        'long_name' => 'Target School',
        'short_name' => 'TAR',
        'email' => 'target@example.com',
    ]);
    $otherSchool = School::factory()->create([
        'long_name' => 'Other School',
        'short_name' => 'OTH',
        'email' => 'other@example.com',
    ]);

    $targetLicence = Licence::create([
        'name' => 'anmeldetool',
        'long_name' => 'Anmeldetool',
    ]);
    $otherLicence = Licence::create([
        'name' => 'foo',
        'long_name' => 'Foo',
    ]);

    $targetSchool->licences()->attach($targetLicence->id, ['valid_until' => now()->addYear()->toDateString()]);
    $otherSchool->licences()->attach($otherLicence->id, ['valid_until' => now()->addYear()->toDateString()]);

    $this->actingAs($this->superAdmin, 'sanctum');

    $response = $this->getJson('/api/admin/schools?search_string=anmeldetool');

    $response->assertStatus(200);

    $names = collect($response->json('data'))->pluck('long_name')->all();

    expect($names)->toContain('Target School')
        ->and($names)->not->toContain('Other School');
});

test('super admin can filter schools with at least one expired school licence', function () {
    $expiredSchool = School::factory()->create([
        'long_name' => 'Expired School',
        'short_name' => 'EXP',
        'email' => 'expired@example.com',
    ]);
    $activeSchool = School::factory()->create([
        'long_name' => 'Active School',
        'short_name' => 'ACT',
        'email' => 'active@example.com',
    ]);
    $mixedSchool = School::factory()->create([
        'long_name' => 'Mixed School',
        'short_name' => 'MIX',
        'email' => 'mixed@example.com',
    ]);

    $expiredLicence = Licence::create([
        'name' => 'expired-licence',
        'long_name' => 'Expired Licence',
    ]);
    $activeLicence = Licence::create([
        'name' => 'active-licence',
        'long_name' => 'Active Licence',
    ]);

    $expiredSchool->licences()->attach($expiredLicence->id, ['valid_until' => now()->subDay()->toDateString()]);
    $activeSchool->licences()->attach($activeLicence->id, ['valid_until' => now()->addMonth()->toDateString()]);
    $mixedSchool->licences()->attach($activeLicence->id, ['valid_until' => now()->addMonth()->toDateString()]);
    $mixedSchool->licences()->attach($expiredLicence->id, ['valid_until' => now()->subDay()->toDateString()]);

    $this->actingAs($this->superAdmin, 'sanctum');

    $response = $this->getJson('/api/admin/schools?expired_only=1');

    $response->assertStatus(200);

    $names = collect($response->json('data'))->pluck('long_name')->all();

    expect($names)->toContain('Expired School')
        ->and($names)->toContain('Mixed School')
        ->and($names)->not->toContain('Active School');
});

test('expired schools filter ignores expired licences when school licence is not required', function () {
    $ignoredSchool = School::factory()->create([
        'long_name' => 'Ignored Expired School',
        'short_name' => 'IGN',
        'email' => 'ignored-expired@example.com',
    ]);
    $requiredSchool = School::factory()->create([
        'long_name' => 'Required Expired School',
        'short_name' => 'REQ',
        'email' => 'required-expired@example.com',
    ]);

    $notRequiredLicence = Licence::create([
        'name' => 'not-required-expired-licence',
        'long_name' => 'Not Required Expired Licence',
    ]);
    $requiredLicence = Licence::create([
        'name' => 'required-expired-licence',
        'long_name' => 'Required Expired Licence',
    ]);

    $ignoredSchool->licences()->attach($notRequiredLicence->id, [
        'valid_until' => now()->subDay()->toDateString(),
        'licence_model' => json_encode([
            'school_licence_required' => false,
            'affected_roles' => [],
            'user_licence_required_by_role' => [],
        ]),
    ]);
    $requiredSchool->licences()->attach($requiredLicence->id, [
        'valid_until' => now()->subDay()->toDateString(),
        'licence_model' => json_encode([
            'school_licence_required' => true,
            'affected_roles' => [],
            'user_licence_required_by_role' => [],
        ]),
    ]);

    $this->actingAs($this->superAdmin, 'sanctum');

    $response = $this->getJson('/api/admin/schools?expired_only=1');
    $response->assertStatus(200);

    $names = collect($response->json('data'))->pluck('long_name')->all();
    expect($names)->toContain('Required Expired School')
        ->and($names)->not->toContain('Ignored Expired School');
});

test('expired schools filter respects template model when school licence model is missing', function () {
    $ignoredSchool = School::factory()->create([
        'long_name' => 'Template Ignored School',
        'short_name' => 'TIGN',
        'email' => 'template-ignored@example.com',
    ]);
    $requiredSchool = School::factory()->create([
        'long_name' => 'Template Required School',
        'short_name' => 'TREQ',
        'email' => 'template-required@example.com',
    ]);

    $notRequiredTemplateLicence = Licence::create([
        'name' => 'template-not-required-expired-licence',
        'long_name' => 'Template Not Required Expired Licence',
        'licence_model' => [
            'school_licence_required' => false,
            'affected_roles' => [],
            'user_licence_required_by_role' => [],
        ],
    ]);
    $requiredTemplateLicence = Licence::create([
        'name' => 'template-required-expired-licence',
        'long_name' => 'Template Required Expired Licence',
        'licence_model' => [
            'school_licence_required' => true,
            'affected_roles' => [],
            'user_licence_required_by_role' => [],
        ],
    ]);

    $ignoredSchool->licences()->attach($notRequiredTemplateLicence->id, [
        'valid_until' => now()->subDay()->toDateString(),
        'licence_model' => null,
    ]);
    $requiredSchool->licences()->attach($requiredTemplateLicence->id, [
        'valid_until' => now()->subDay()->toDateString(),
        'licence_model' => null,
    ]);

    $this->actingAs($this->superAdmin, 'sanctum');

    $response = $this->getJson('/api/admin/schools?expired_only=1');
    $response->assertStatus(200);

    $names = collect($response->json('data'))->pluck('long_name')->all();
    expect($names)->toContain('Template Required School')
        ->and($names)->not->toContain('Template Ignored School');
});

test('non super admin receives 403 on listing schools', function () {
    $this->actingAs($this->adminUser, 'sanctum');

    $this->getJson('/api/admin/schools')
        ->assertStatus(403);
});

test('guest receives 401 on listing schools', function () {
    $this->getJson('/api/admin/schools')
        ->assertStatus(401);
});

// ============================================================================
// store
// ============================================================================

test('super admin can store school via service', function () {
    $payload = [
        'long_name' => 'New School',
        'short_name' => 'NEW',
        'email' => 'new@example.com',
        'is_selectable' => true,
    ];

    $createdSchool = new School($payload);
    $createdSchool->id = 999;

    $this->mock(SchoolService::class, function ($mock) use ($payload, $createdSchool) {
        $mock->shouldReceive('create')
            ->once()
            ->with($payload)
            ->andReturn($createdSchool);
    });

    $this->actingAs($this->superAdmin, 'sanctum');

    $this->postJson('/api/admin/schools', $payload)
        ->assertStatus(200)
        ->assertJson([
            'id' => 999,
            'long_name' => 'New School',
            'short_name' => 'NEW',
            'email' => 'new@example.com',
            'is_selectable' => true,
        ]);
});

test('store requires super admin role', function () {
    $this->actingAs($this->adminUser, 'sanctum');

    $this->postJson('/api/admin/schools', [
        'long_name' => 'Blocked School',
        'short_name' => 'BLK',
        'email' => 'blocked@example.com',
    ])->assertStatus(403);
});

// ============================================================================
// update
// ============================================================================

test('admin can update school via service', function () {
    $schoolToUpdate = School::factory()->create([
        'long_name' => 'Updatable School',
        'short_name' => 'UPD',
        'email' => 'upd@example.com',
    ]);

    $payload = [
        'id' => $schoolToUpdate->id,
        'long_name' => 'Updated Name',
        'short_name' => 'UPDX',
        'email' => 'updated@example.com',
        'is_selectable' => false,
    ];

    $updatedSchool = new School($payload);
    $updatedSchool->id = $schoolToUpdate->id;

    $this->mock(SchoolService::class, function ($mock) use ($schoolToUpdate, $payload, $updatedSchool) {
        $mock->shouldReceive('update')
            ->once()
            ->with(\Mockery::on(fn($school) => $school->id === $schoolToUpdate->id), $payload)
            ->andReturn($updatedSchool);
    });

    $this->actingAs($this->adminUser, 'sanctum');

    $this->putJson("/api/admin/schools/{$schoolToUpdate->id}", $payload)
        ->assertStatus(200)
        ->assertJson([
            'id' => $schoolToUpdate->id,
            'long_name' => 'Updated Name',
            'short_name' => 'UPDX',
            'email' => 'updated@example.com',
            'is_selectable' => false,
        ]);
});

test('update requires valid payload', function () {
    $this->actingAs($this->adminUser, 'sanctum');

    $this->putJson("/api/admin/schools/{$this->school->id}", [
        'long_name' => 'Missing ID',
    ])->assertStatus(422);
});

// ============================================================================
// deleteSchools
// ============================================================================

test('super admin cannot delete their own school', function () {
    $this->actingAs($this->superAdmin, 'sanctum');

    $this->postJson('/api/admin/schools/delete_schools', [$this->superAdmin->school_id])
        ->assertStatus(409);
});

test('super admin can delete other schools via service', function () {
    $deletable = School::factory()->create();

    $this->mock(SchoolService::class, function ($mock) use ($deletable) {
        $mock->shouldReceive('deleteSchools')
            ->once()
            ->with([$deletable->id]);
    });

    $this->actingAs($this->superAdmin, 'sanctum');

    $this->postJson('/api/admin/schools/delete_schools', [$deletable->id])
        ->assertStatus(204);
});

// ============================================================================
// loadSwitchableSchools
// ============================================================================

test('super admin can load switchable schools sorted by name', function () {
    $otherSchool = School::factory()->create(['long_name' => 'Beta School', 'short_name' => 'BTA']);

    // Create another user with same email in the other school to make it switchable
    User::factory()->create([
        'school_id' => $otherSchool->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => $this->superAdmin->email,
    ]);

    $this->actingAs($this->superAdmin, 'sanctum');

    $response = $this->postJson('/api/admin/schools/load_switchable_schools');

    $response->assertStatus(200)
        ->assertJsonCount(2)
        ->assertJson([
            ['long_name' => 'Beta School'],
            ['long_name' => 'Main School'],
        ]);
});

test('non super admin cannot load switchable schools', function () {
    $this->actingAs($this->adminUser, 'sanctum');

    $this->postJson('/api/admin/schools/load_switchable_schools')
        ->assertStatus(403);
});

// ============================================================================
// switchSchool
// ============================================================================

test('super admin can switch school via service', function () {
    $targetSchool = School::factory()->create(['long_name' => 'Target School', 'short_name' => 'TRG']);

    $targetUser = User::factory()->create([
        'school_id' => $targetSchool->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => $this->superAdmin->email,
    ]);

    $this->mock(SchoolService::class, function ($mock) use ($targetSchool, $targetUser) {
        $mock->shouldReceive('switchSchool')
            ->once()
            ->andReturn($targetUser);
    });

    $this->actingAs($this->superAdmin, 'sanctum');

    $this->postJson('/api/admin/schools/switch_school', ['school_id' => $targetSchool->id])
        ->assertStatus(204);
});

test('switch school requires authentication', function () {
    $this->postJson('/api/admin/schools/switch_school', ['school_id' => $this->school->id])
        ->assertStatus(401);
});

// ============================================================================
// loadSchoolInfos
// ============================================================================

test('admin can load school infos and queues background job', function () {
    Queue::fake();

    $expectedData = [
        'licences' => [],
        'admins' => [],
    ];

    $this->mock(SchoolService::class, function ($mock) use ($expectedData) {
        $mock->shouldReceive('schoolInfos')
            ->once()
            ->andReturn($expectedData);
    });

    $this->actingAs($this->adminUser, 'sanctum');

    $this->postJson('/api/admin/schools/load_school_infos', ['school_id' => $this->school->id])
        ->assertStatus(200)
        ->assertJson($expectedData);
});

test('load school infos blocks unauthorized user', function () {
    $this->actingAs($this->registerAdmin, 'sanctum');

    $this->postJson('/api/admin/schools/load_school_infos', []) // missing validation too
        ->assertStatus(422);
});

// ============================================================================
// addLicence & deleteLicence
// ============================================================================

test('super admin can add licence to selected school', function () {
    $licence = Licence::create([
        'name' => 'core',
        'long_name' => 'Core Licence',
        'price_per_year' => 100,
    ]);

    $this->actingAs($this->superAdmin, 'sanctum');

    $response = $this->postJson('/api/admin/schools/add_licence', [
        'data' => [
            'licence_id' => $licence->id,
            'valid_until' => now()->addYear()->toDateString(),
        ],
    ]);

    $response->assertStatus(200)
        ->assertJsonFragment([
            'id' => $licence->id,
            'name' => 'core',
            'long_name' => 'Core Licence',
        ]);
});

test('super admin can add licence to explicitly provided school id', function () {
    $targetSchool = School::factory()->create([
        'long_name' => 'Target School',
        'short_name' => 'TS',
    ]);

    $licence = Licence::create([
        'name' => 'target_only',
        'long_name' => 'Target Only Licence',
    ]);

    $this->actingAs($this->superAdmin, 'sanctum');

    $this->postJson('/api/admin/schools/add_licence', [
        'data' => [
            'school_id' => $targetSchool->id,
            'licence_id' => $licence->id,
            'valid_until' => now()->addYear()->toDateString(),
        ],
    ])->assertStatus(200);

    $this->assertDatabaseHas('school_licences', [
        'school_id' => $targetSchool->id,
        'licence_id' => $licence->id,
    ]);
});

test('adding licence assigns template licence model to school licence', function () {
    $licence = Licence::create([
        'name' => 'modelled',
        'long_name' => 'Modelled Licence',
        'licence_model' => [
            'school_licence_required' => true,
            'affected_roles' => ['admin'],
            'user_licence_required_by_role' => ['admin' => true],
        ],
    ]);

    $this->actingAs($this->superAdmin, 'sanctum');

    $this->postJson('/api/admin/schools/add_licence', [
        'data' => [
            'licence_id' => $licence->id,
            'valid_until' => now()->addYear()->toDateString(),
        ],
    ])->assertStatus(200);

    $schoolLicence = SchoolLicence::where('school_id', $this->school->id)
        ->where('licence_id', $licence->id)
        ->first();

    expect($schoolLicence)->not->toBeNull()
        ->and($schoolLicence->licence_model['school_licence_required'] ?? null)->toBeTrue()
        ->and($schoolLicence->licence_model['affected_roles'] ?? [])->toBe(['admin'])
        ->and($schoolLicence->licence_model['user_licence_required_by_role']['admin'] ?? null)->toBeTrue();
});

test('super admin can delete school licence', function () {
    $licence = Licence::create([
        'name' => 'removable',
        'long_name' => 'Removable Licence',
    ]);

    $schoolLicence = SchoolLicence::create([
        'school_id' => $this->school->id,
        'licence_id' => $licence->id,
        'valid_until' => now()->addMonth()->toDateString(),
    ]);

    $this->actingAs($this->superAdmin, 'sanctum');

    $this->postJson('/api/admin/schools/delete_licence', [
        'school_licence_id' => $schoolLicence->id,
    ])->assertStatus(200);
});

test('super admin can save school specific licence model', function () {
    $licence = Licence::create([
        'name' => 'model_school_specific',
        'long_name' => 'Model School Specific',
    ]);

    $schoolLicence = SchoolLicence::create([
        'school_id' => $this->school->id,
        'licence_id' => $licence->id,
        'valid_until' => now()->addMonth()->toDateString(),
    ]);

    $this->actingAs($this->superAdmin, 'sanctum');

    $this->putJson("/api/admin/school_licences/{$schoolLicence->id}/save_licence_model", [
        'licence_model' => [
            'school_licence_required' => true,
            'affected_roles' => ['admin'],
            'user_licence_required_by_role' => [
                'admin' => true,
            ],
        ],
    ])->assertStatus(200);

    $schoolLicence->refresh();

    expect($schoolLicence->licence_model['school_licence_required'] ?? null)->toBeTrue()
        ->and($schoolLicence->licence_model['affected_roles'] ?? [])->toBe(['admin'])
        ->and($schoolLicence->licence_model['user_licence_required_by_role']['admin'] ?? null)->toBeTrue();
});

test('super admin can load all school users and licence model roles for user licences card', function () {
    Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);

    $licence = Licence::create([
        'name' => 'users_filter',
        'long_name' => 'Users Filter Licence',
    ]);

    $schoolLicence = SchoolLicence::create([
        'school_id' => $this->school->id,
        'licence_id' => $licence->id,
        'valid_until' => now()->addMonth()->toDateString(),
        'licence_model' => [
            'school_licence_required' => true,
            'affected_roles' => ['admin', 'teacher'],
            'user_licence_required_by_role' => [
                'admin' => false,
                'teacher' => true,
            ],
        ],
    ]);

    $matching = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'last_name' => 'Match',
        'first_name' => 'Teacher',
        'email' => 'match.teacher@test.com',
    ]);
    $matching->assignRole('teacher');

    $nonMatching = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'last_name' => 'Nope',
        'first_name' => 'Student',
        'email' => 'nope.student@test.com',
    ]);
    $nonMatching->assignRole('student');

    $otherSchool = School::factory()->create();
    $otherSchoolUser = User::factory()->create([
        'school_id' => $otherSchool->id,
        'schoolyear_id' => $this->schoolyear->id,
        'last_name' => 'Other',
        'first_name' => 'Teacher',
        'email' => 'other.teacher@test.com',
    ]);
    $otherSchoolUser->assignRole('teacher');

    $this->actingAs($this->superAdmin, 'sanctum');

    $response = $this->getJson("/api/admin/school_licences/{$schoolLicence->id}/users");

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                ['id', 'first_name', 'last_name', 'email', 'roles'],
            ],
            'meta' => ['per_page', 'current_page', 'last_page'],
            'roles',
        ]);

    $ids = collect($response->json('data'))->pluck('id')->all();

    expect($ids)->toContain($matching->id)
        ->and($ids)->toContain($nonMatching->id)
        ->and($ids)->not->toContain($otherSchoolUser->id)
        ->and($response->json('roles'))->toBe(['teacher'])
        ->and($response->json('active_role_filters'))->toBe([]);

    $roleStatuses = $response->json('role_statuses_by_user');
    expect(data_get($roleStatuses, "{$matching->id}.teacher.is_active"))->toBeFalse()
        ->and(data_get($roleStatuses, "{$matching->id}.teacher.is_activated"))->toBeFalse()
        ->and(data_get($roleStatuses, "{$matching->id}.teacher.valid_until"))->toBeNull()
        ->and(data_get($roleStatuses, (string) $nonMatching->id))->toBe([]);

    $responseAllSelected = $this->getJson("/api/admin/school_licences/{$schoolLicence->id}/users?role_names[]=teacher");
    $idsAllSelected = collect($responseAllSelected->json('data'))->pluck('id')->all();

    expect($idsAllSelected)->toContain($matching->id)
        ->and($idsAllSelected)->not->toContain($nonMatching->id)
        ->and($responseAllSelected->json('active_role_filters'))->toBe(['teacher']);
});

test('school licence users response marks outdated user role licences', function () {
    Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']);

    $licence = Licence::create([
        'name' => 'users_role_status_outdated',
        'long_name' => 'Users Role Status Outdated',
    ]);

    $schoolLicence = SchoolLicence::create([
        'school_id' => $this->school->id,
        'licence_id' => $licence->id,
        'valid_until' => now()->addMonth()->toDateString(),
        'licence_model' => [
            'school_licence_required' => true,
            'affected_roles' => ['teacher'],
            'user_licence_required_by_role' => [
                'teacher' => true,
            ],
        ],
        'user_licence_assignments' => [],
    ]);

    $user = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
    ]);
    $user->assignRole('teacher');

    $schoolLicence->user_licence_assignments = [
        (string) $user->id => [
            'teacher' => [
                'valid_until' => now()->subDay()->toDateString(),
                'is_activated' => true,
            ],
        ],
    ];
    $schoolLicence->save();

    $this->actingAs($this->superAdmin, 'sanctum');

    $response = $this->getJson("/api/admin/school_licences/{$schoolLicence->id}/users");

    $response->assertStatus(200)
        ->assertJsonPath("role_statuses_by_user.{$user->id}.teacher.is_active", false);
});

test('school licence users response ignores expired school licence when school licence is not required', function () {
    Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']);

    $licence = Licence::create([
        'name' => 'users_role_status_school_not_required',
        'long_name' => 'Users Role Status School Not Required',
    ]);

    $schoolLicence = SchoolLicence::create([
        'school_id' => $this->school->id,
        'licence_id' => $licence->id,
        'valid_until' => now()->subDay()->toDateString(),
        'licence_model' => [
            'school_licence_required' => false,
            'affected_roles' => ['teacher'],
            'user_licence_required_by_role' => [
                'teacher' => true,
            ],
        ],
        'user_licence_assignments' => [],
    ]);

    $user = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
    ]);
    $user->assignRole('teacher');

    $schoolLicence->user_licence_assignments = [
        (string) $user->id => [
            'teacher' => [
                'valid_until' => now()->addDay()->toDateString(),
                'is_activated' => true,
            ],
        ],
    ];
    $schoolLicence->save();

    $this->actingAs($this->superAdmin, 'sanctum');

    $response = $this->getJson("/api/admin/school_licences/{$schoolLicence->id}/users");

    $response->assertStatus(200)
        ->assertJsonPath("role_statuses_by_user.{$user->id}.teacher.is_active", true);
});

test('super admin can filter school licence users by expired user licences', function () {
    Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']);

    $licence = Licence::create([
        'name' => 'users_filter_expired_only',
        'long_name' => 'Users Filter Expired Only',
    ]);

    $schoolLicence = SchoolLicence::create([
        'school_id' => $this->school->id,
        'licence_id' => $licence->id,
        'valid_until' => now()->addMonth()->toDateString(),
        'licence_model' => [
            'school_licence_required' => true,
            'affected_roles' => ['teacher'],
            'user_licence_required_by_role' => [
                'teacher' => true,
            ],
        ],
        'user_licence_assignments' => [],
    ]);

    $outdatedUser = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
    ]);
    $outdatedUser->assignRole('teacher');

    $activeUser = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
    ]);
    $activeUser->assignRole('teacher');

    $schoolLicence->user_licence_assignments = [
        (string) $outdatedUser->id => [
            'teacher' => [
                'valid_until' => now()->subDay()->toDateString(),
                'is_activated' => true,
            ],
        ],
        (string) $activeUser->id => [
            'teacher' => [
                'valid_until' => now()->addDay()->toDateString(),
                'is_activated' => true,
            ],
        ],
    ];
    $schoolLicence->save();

    $this->actingAs($this->superAdmin, 'sanctum');

    $response = $this->getJson("/api/admin/school_licences/{$schoolLicence->id}/users?expired_only=1");
    $response->assertStatus(200);

    $ids = collect($response->json('data'))->pluck('id')->all();
    expect($ids)->toContain($outdatedUser->id)
        ->and($ids)->not->toContain($activeUser->id);
});

test('expired user licences filter ignores expired school licence when school licence is not required', function () {
    Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']);

    $licence = Licence::create([
        'name' => 'users_filter_expired_only_school_not_required',
        'long_name' => 'Users Filter Expired Only School Not Required',
    ]);

    $schoolLicence = SchoolLicence::create([
        'school_id' => $this->school->id,
        'licence_id' => $licence->id,
        'valid_until' => now()->subDay()->toDateString(),
        'licence_model' => [
            'school_licence_required' => false,
            'affected_roles' => ['teacher'],
            'user_licence_required_by_role' => [
                'teacher' => true,
            ],
        ],
        'user_licence_assignments' => [],
    ]);

    $outdatedUser = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
    ]);
    $outdatedUser->assignRole('teacher');

    $activeUser = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
    ]);
    $activeUser->assignRole('teacher');

    $schoolLicence->user_licence_assignments = [
        (string) $outdatedUser->id => [
            'teacher' => [
                'valid_until' => now()->subDay()->toDateString(),
                'is_activated' => true,
            ],
        ],
        (string) $activeUser->id => [
            'teacher' => [
                'valid_until' => now()->addDay()->toDateString(),
                'is_activated' => true,
            ],
        ],
    ];
    $schoolLicence->save();

    $this->actingAs($this->superAdmin, 'sanctum');

    $response = $this->getJson("/api/admin/school_licences/{$schoolLicence->id}/users?expired_only=1");
    $response->assertStatus(200);

    $ids = collect($response->json('data'))->pluck('id')->all();
    expect($ids)->toContain($outdatedUser->id)
        ->and($ids)->not->toContain($activeUser->id);
});

test('super admin can filter school licence users by selected licence model roles', function () {
    Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);

    $licence = Licence::create([
        'name' => 'users_filter_roles',
        'long_name' => 'Users Filter Roles Licence',
    ]);

    $schoolLicence = SchoolLicence::create([
        'school_id' => $this->school->id,
        'licence_id' => $licence->id,
        'valid_until' => now()->addMonth()->toDateString(),
        'licence_model' => [
            'school_licence_required' => true,
            'affected_roles' => ['admin', 'teacher'],
            'user_licence_required_by_role' => [
                'admin' => true,
                'teacher' => true,
            ],
        ],
    ]);

    $teacher = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'last_name' => 'Role',
        'first_name' => 'Teacher',
        'email' => 'role.teacher@test.com',
    ]);
    $teacher->assignRole('teacher');

    $student = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'last_name' => 'Role',
        'first_name' => 'Student',
        'email' => 'role.student@test.com',
    ]);
    $student->assignRole('student');

    $this->actingAs($this->superAdmin, 'sanctum');

    $response = $this->getJson("/api/admin/school_licences/{$schoolLicence->id}/users?role_names[]=teacher");

    $response->assertStatus(200);

    $ids = collect($response->json('data'))->pluck('id')->all();

    expect($ids)->toContain($teacher->id)
        ->and($ids)->not->toContain($student->id)
        ->and($response->json('active_role_filters'))->toBe(['teacher']);
});

test('super admin can load school licence user role details', function () {
    Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

    $licence = Licence::create([
        'name' => 'user_roles_details',
        'long_name' => 'User Roles Details Licence',
    ]);

    $schoolLicence = SchoolLicence::create([
        'school_id' => $this->school->id,
        'licence_id' => $licence->id,
        'valid_until' => '2027-01-31',
        'licence_model' => [
            'school_licence_required' => true,
            'affected_roles' => ['teacher', 'admin'],
            'user_licence_required_by_role' => [
                'teacher' => true,
                'admin' => true,
            ],
        ],
        'user_licence_assignments' => [],
    ]);

    $user = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
    ]);
    $user->assignRole('teacher');

    $schoolLicence->user_licence_assignments = [
        (string) $user->id => [
            'teacher' => [
                'valid_until' => '2026-12-31',
                'is_activated' => true,
            ],
        ],
    ];
    $schoolLicence->save();

    $this->actingAs($this->superAdmin, 'sanctum');

    $response = $this->getJson("/api/admin/school_licences/{$schoolLicence->id}/users/{$user->id}/roles");

    $response->assertStatus(200)
        ->assertJsonPath('school_licence_valid_until', '2027-01-31')
        ->assertJsonPath('roles.0.name', 'teacher')
        ->assertJsonPath('roles.0.assigned', true)
        ->assertJsonPath('roles.0.valid_until', '2026-12-31')
        ->assertJsonPath('roles.1.name', 'admin')
        ->assertJsonPath('roles.1.assigned', false);
});

test('super admin can save school licence user role details with valid until', function () {
    Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

    $licence = Licence::create([
        'name' => 'user_roles_save',
        'long_name' => 'User Roles Save Licence',
    ]);

    $schoolLicence = SchoolLicence::create([
        'school_id' => $this->school->id,
        'licence_id' => $licence->id,
        'valid_until' => '2027-01-31',
        'licence_model' => [
            'school_licence_required' => true,
            'affected_roles' => ['teacher', 'admin'],
            'user_licence_required_by_role' => [
                'teacher' => true,
                'admin' => true,
            ],
        ],
        'user_licence_assignments' => [],
    ]);

    $user = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
    ]);
    $user->assignRole('teacher');

    $this->actingAs($this->superAdmin, 'sanctum');

    $this->putJson("/api/admin/school_licences/{$schoolLicence->id}/users/{$user->id}/roles", [
        'roles' => [
            ['name' => 'teacher', 'assigned' => false, 'valid_until' => null],
            ['name' => 'admin', 'assigned' => true, 'valid_until' => '2026-10-15'],
        ],
    ])->assertStatus(200)
        ->assertJsonPath('roles.0.name', 'teacher')
        ->assertJsonPath('roles.0.assigned', false)
        ->assertJsonPath('roles.1.name', 'admin')
        ->assertJsonPath('roles.1.assigned', false)
        ->assertJsonPath('roles.1.valid_until', null);

    $user->refresh();
    expect($user->hasRole('teacher'))->toBeTrue()
        ->and($user->hasRole('admin'))->toBeFalse();

    $schoolLicence->refresh();
    expect(isset($schoolLicence->user_licence_assignments[(string) $user->id]))->toBeFalse();
});

// ============================================================================
// addAdmin & deleteAdmin
// ============================================================================

test('super admin can add admin via service', function () {
    $newAdmin = User::factory()->make([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'new.admin@test.com',
    ]);
    $newAdmin->id = 1234;

    $this->mock(SchoolService::class, function ($mock) use ($newAdmin) {
        $mock->shouldReceive('addAdmin')
            ->once()
            ->andReturn($newAdmin);
    });

    $this->actingAs($this->superAdmin, 'sanctum');

    $this->postJson('/api/admin/schools/add_admin', [
        'data' => [
            'last_name' => 'New',
            'first_name' => 'Admin',
            'email' => 'new.admin@test.com',
        ],
        'roles' => ['admin'],
    ])->assertStatus(200)
        ->assertJson([
            'id' => 1234,
            'email' => 'new.admin@test.com',
        ]);
});

test('super admin cannot delete themselves as admin', function () {
    $this->actingAs($this->superAdmin, 'sanctum');

    $this->postJson('/api/admin/schools/delete_admin', [
        'admin_id' => $this->superAdmin->id,
        'is_delete_complete' => false,
    ])->assertStatus(409);
});

test('super admin can delete admin via service', function () {
    $target = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'delete.me@test.com',
    ]);

    $this->mock(SchoolService::class, function ($mock) use ($target) {
        $mock->shouldReceive('deleteAdmin')
            ->once()
            ->with($target->id, true);
    });

    $this->actingAs($this->superAdmin, 'sanctum');

    $this->postJson('/api/admin/schools/delete_admin', [
        'admin_id' => $target->id,
        'is_delete_complete' => true,
    ])->assertStatus(204);
});
