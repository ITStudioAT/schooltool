<?php

/**
 * LicenceController Tests
 *
 * These tests cover the licence management functionality including:
 * - Listing licences with pagination and search
 * - Creating new licences
 * - Updating existing licences
 * - Deleting licences
 * - Loading all licences
 * - Role-based access control
 */

use App\Models\Licence;
use App\Models\School;
use App\Models\SchoolLicence;
use App\Models\Schoolyear;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create test school and schoolyear
    $this->school = School::factory()->create([
        'long_name' => 'Test School',
        'short_name' => 'TS',
    ]);

    $this->schoolyear = Schoolyear::factory()->create([
        'school_id' => $this->school->id,
    ]);

    // Create roles
    if (! Role::where('name', 'super_admin')->exists()) {
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    }
    if (! Role::where('name', 'admin')->exists()) {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    }

    // Create super admin user
    $this->superAdmin = User::factory()->create([
        'email' => 'superadmin@example.com',
        'password' => Hash::make('password123'),
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'confirmed_at' => now(),
        'is_active' => true,
    ]);
    $this->superAdmin->assignRole('super_admin');

    // Create admin user
    $this->admin = User::factory()->create([
        'email' => 'admin@example.com',
        'password' => Hash::make('password123'),
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'confirmed_at' => now(),
        'is_active' => true,
    ]);
    $this->admin->assignRole('admin');

    // Create regular user
    $this->user = User::factory()->create([
        'email' => 'user@example.com',
        'password' => Hash::make('password123'),
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'confirmed_at' => now(),
        'is_active' => true,
    ]);
});

// Index Tests
test('index returns paginated licences for super admin', function () {
    $this->actingAs($this->superAdmin);

    // Create test licences
    Licence::create(['name' => 'test_app', 'long_name' => 'Test Application', 'is_selectable' => true]);
    Licence::create(['name' => 'another_app', 'long_name' => 'Another Application', 'is_selectable' => false]);

    $response = $this->getJson('/api/admin/licences');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'name', 'long_name', 'is_selectable', 'price_per_year', 'start_day_month', 'end_day_month'],
            ],
            'meta' => ['current_page', 'last_page', 'per_page', 'total'],
        ]);

    expect($response->json('data'))->toHaveCount(2);
});

test('index returns paginated licences for admin', function () {
    $this->actingAs($this->admin);

    Licence::create(['name' => 'test_app', 'long_name' => 'Test Application']);

    $response = $this->getJson('/api/admin/licences');

    $response->assertStatus(200)
        ->assertJsonStructure(['data', 'meta']);
});

test('index denies access for regular user', function () {
    $this->actingAs($this->user);

    $response = $this->getJson('/api/admin/licences');

    $response->assertStatus(403);
});

test('index requires authentication', function () {
    $response = $this->getJson('/api/admin/licences');

    $response->assertStatus(401);
});

test('index filters by search string', function () {
    $this->actingAs($this->superAdmin);

    Licence::create(['name' => 'test_app', 'long_name' => 'Test Application']);
    Licence::create(['name' => 'another_app', 'long_name' => 'Another Application']);
    Licence::create(['name' => 'special_app', 'long_name' => 'Special Application']);

    $response = $this->getJson('/api/admin/licences?search_string=Test');

    $response->assertStatus(200);

    $data = $response->json('data');
    expect($data)->toHaveCount(1)
        ->and($data[0]['long_name'])->toBe('Test Application');
});

test('index searches in long_name field', function () {
    $this->actingAs($this->superAdmin);

    Licence::create(['name' => 'app1', 'long_name' => 'Special Application']);
    Licence::create(['name' => 'app2', 'long_name' => 'Regular Application']);

    $response = $this->getJson('/api/admin/licences?search_string=Special');

    $response->assertStatus(200);

    $data = $response->json('data');
    expect($data)->toHaveCount(1)
        ->and($data[0]['long_name'])->toBe('Special Application');
});

test('index searches in long_name and name fields', function () {
    $this->actingAs($this->superAdmin);

    Licence::create(['name' => 'test_app', 'long_name' => 'Contains unique word']);
    Licence::create(['name' => 'other_app', 'long_name' => 'Other']);

    $response = $this->getJson('/api/admin/licences?search_string=unique');

    $response->assertStatus(200);

    $data = $response->json('data');
    expect($data)->toHaveCount(1)
        ->and($data[0]['long_name'])->toBe('Contains unique word');
});

test('index orders results by long_name', function () {
    $this->actingAs($this->superAdmin);

    Licence::create(['name' => 'app_c', 'long_name' => 'Charlie Application']);
    Licence::create(['name' => 'app_a', 'long_name' => 'Alpha Application']);
    Licence::create(['name' => 'app_b', 'long_name' => 'Bravo Application']);

    $response = $this->getJson('/api/admin/licences');

    $response->assertStatus(200);

    $data = $response->json('data');
    expect($data[0]['long_name'])->toBe('Alpha Application')
        ->and($data[1]['long_name'])->toBe('Bravo Application')
        ->and($data[2]['long_name'])->toBe('Charlie Application');
});

test('index returns empty data for no licences', function () {
    $this->actingAs($this->superAdmin);

    $response = $this->getJson('/api/admin/licences');

    $response->assertStatus(200)
        ->assertJson(['data' => []]);
});

// Store Tests
test('store creates new licence for super admin', function () {
    $this->actingAs($this->superAdmin);

    $data = [
        'name' => 'new_app',
        'long_name' => 'New Application',
        'is_selectable' => true,
        'price_per_year' => 1000,
        'start_day_month' => '01.09.',
        'end_day_month' => '31.07.',
    ];

    $response = $this->postJson('/api/admin/licences', $data);

    $response->assertStatus(200)
        ->assertJsonStructure([
            'id',
            'name',
            'long_name',
            'is_selectable',
            'price_per_year',
            'start_day_month',
            'end_day_month',
        ])
        ->assertJson([
            'name' => 'new_app',
            'long_name' => 'New Application',
            'is_selectable' => true,
            'price_per_year' => 1000,
            'start_day_month' => '01.09.',
            'end_day_month' => '31.07.',
        ]);

    $this->assertDatabaseHas('licences', [
        'name' => 'new_app',
        'long_name' => 'New Application',
        'start_day_month' => '09-01',
        'end_day_month' => '07-31',
    ]);
});

test('store denies access for admin user', function () {
    $this->actingAs($this->admin);

    $data = [
        'name' => 'new_app',
        'long_name' => 'New Application',
        'start_day_month' => '01.09.',
        'end_day_month' => '31.07.',
    ];

    $response = $this->postJson('/api/admin/licences', $data);

    $response->assertStatus(403);
});

test('store denies access for regular user', function () {
    $this->actingAs($this->user);

    $data = [
        'name' => 'new_app',
        'long_name' => 'New Application',
        'start_day_month' => '01.09.',
        'end_day_month' => '31.07.',
    ];

    $response = $this->postJson('/api/admin/licences', $data);

    $response->assertStatus(403);
});

test('store requires authentication', function () {
    $data = [
        'name' => 'new_app',
        'long_name' => 'New Application',
    ];

    $response = $this->postJson('/api/admin/licences', $data);

    $response->assertStatus(401);
});

test('store validates required name field', function () {
    $this->actingAs($this->superAdmin);

    $data = [
        'long_name' => 'New Application',
        'start_day_month' => '01.09.',
        'end_day_month' => '31.07.',
    ];

    $response = $this->postJson('/api/admin/licences', $data);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['name']);
});

test('store validates unique name field', function () {
    $this->actingAs($this->superAdmin);

    Licence::create(['name' => 'existing_app', 'long_name' => 'Existing']);

    $data = [
        'name' => 'existing_app',
        'long_name' => 'New Application',
        'start_day_month' => '01.09.',
        'end_day_month' => '31.07.',
    ];

    $response = $this->postJson('/api/admin/licences', $data);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['name']);
});

test('store accepts nullable fields', function () {
    $this->actingAs($this->superAdmin);

    $data = [
        'name' => 'minimal_app',
        'start_day_month' => '01.09.',
        'end_day_month' => '31.07.',
    ];

    $response = $this->postJson('/api/admin/licences', $data);

    $response->assertStatus(200);

    $this->assertDatabaseHas('licences', [
        'name' => 'minimal_app',
        'long_name' => null,
        'price_per_year' => null,
        'start_day_month' => '09-01',
        'end_day_month' => '07-31',
    ]);
});

// Update Tests
test('update modifies existing licence for super admin', function () {
    $this->actingAs($this->superAdmin);

    $licence = Licence::create([
        'name' => 'test_app',
        'long_name' => 'Original Name',
        'price_per_year' => 500,
        'start_day_month' => '09-01',
        'end_day_month' => '07-31',
    ]);

    $data = [
        'id' => $licence->id,
        'name' => 'test_app',
        'long_name' => 'Updated Name',
        'is_selectable' => false,
        'price_per_year' => 1500,
        'start_day_month' => '15.09.',
        'end_day_month' => '30.06.',
    ];

    $response = $this->putJson("/api/admin/licences/{$licence->id}", $data);

    $response->assertStatus(200)
        ->assertJson([
            'id' => $licence->id,
            'name' => 'test_app',
            'long_name' => 'Updated Name',
            'price_per_year' => 1500,
            'start_day_month' => '15.09.',
            'end_day_month' => '30.06.',
        ]);

    $this->assertDatabaseHas('licences', [
        'id' => $licence->id,
        'long_name' => 'Updated Name',
        'price_per_year' => 1500,
        'start_day_month' => '09-15',
        'end_day_month' => '06-30',
    ]);
});

test('update denies access for admin user', function () {
    $this->actingAs($this->admin);

    $licence = Licence::create(['name' => 'test_app', 'long_name' => 'Test']);

    $data = [
        'id' => $licence->id,
        'name' => 'test_app',
        'long_name' => 'Updated',
        'start_day_month' => '01.09.',
        'end_day_month' => '31.07.',
    ];

    $response = $this->putJson("/api/admin/licences/{$licence->id}", $data);

    $response->assertStatus(403);
});

test('update denies access for regular user', function () {
    $this->actingAs($this->user);

    $licence = Licence::create(['name' => 'test_app', 'long_name' => 'Test']);

    $data = [
        'id' => $licence->id,
        'name' => 'test_app',
        'long_name' => 'Updated',
        'start_day_month' => '01.09.',
        'end_day_month' => '31.07.',
    ];

    $response = $this->putJson("/api/admin/licences/{$licence->id}", $data);

    $response->assertStatus(403);
});

test('update requires authentication', function () {
    $licence = Licence::create(['name' => 'test_app', 'long_name' => 'Test']);

    $data = [
        'id' => $licence->id,
        'name' => 'test_app',
        'long_name' => 'Updated',
    ];

    $response = $this->putJson("/api/admin/licences/{$licence->id}", $data);

    $response->assertStatus(401);
});

test('update validates required fields', function () {
    $this->actingAs($this->superAdmin);

    $licence = Licence::create(['name' => 'test_app', 'long_name' => 'Test']);

    $data = [
        'long_name' => 'Updated',
        'start_day_month' => '01.09.',
        'end_day_month' => '31.07.',
    ];

    $response = $this->putJson("/api/admin/licences/{$licence->id}", $data);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['id', 'name']);
});

test('update rejects end day month equal to start day month', function () {
    $this->actingAs($this->superAdmin);

    $licence = Licence::create([
        'name' => 'test_app',
        'long_name' => 'Test',
        'start_day_month' => '09-01',
        'end_day_month' => '07-31',
    ]);

    $response = $this->putJson("/api/admin/licences/{$licence->id}", [
        'id' => $licence->id,
        'name' => 'test_app',
        'long_name' => 'Updated',
        'start_day_month' => '15.09.',
        'end_day_month' => '15.09.',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['end_day_month']);
});

test('update allows same name for same licence', function () {
    $this->actingAs($this->superAdmin);

    $licence = Licence::create(['name' => 'test_app', 'long_name' => 'Test', 'start_day_month' => '09-01', 'end_day_month' => '07-31']);

    $data = [
        'id' => $licence->id,
        'name' => 'test_app',
        'long_name' => 'Updated Name',
        'start_day_month' => '01.09.',
        'end_day_month' => '31.07.',
    ];

    $response = $this->putJson("/api/admin/licences/{$licence->id}", $data);

    $response->assertStatus(200);
});

test('update rejects duplicate name for different licence', function () {
    $this->actingAs($this->superAdmin);

    Licence::create(['name' => 'existing_app', 'long_name' => 'Existing', 'start_day_month' => '09-01', 'end_day_month' => '07-31']);
    $licence = Licence::create(['name' => 'test_app', 'long_name' => 'Test', 'start_day_month' => '09-01', 'end_day_month' => '07-31']);

    $data = [
        'id' => $licence->id,
        'name' => 'existing_app',
        'long_name' => 'Updated',
        'start_day_month' => '01.09.',
        'end_day_month' => '31.07.',
    ];

    $response = $this->putJson("/api/admin/licences/{$licence->id}", $data);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['name']);
});

// Load Licences Tests
test('load licences returns all licences for super admin', function () {
    $this->actingAs($this->superAdmin);

    Licence::create(['name' => 'app1', 'long_name' => 'Application 1']);
    Licence::create(['name' => 'app2', 'long_name' => 'Application 2']);
    Licence::create(['name' => 'app3', 'long_name' => 'Application 3']);

    $response = $this->postJson('/api/admin/licences/load_licences');

    $response->assertStatus(200)
        ->assertJsonCount(3);
});

test('load licences returns all licences for admin', function () {
    $this->actingAs($this->admin);

    Licence::create(['name' => 'app1', 'long_name' => 'Application 1']);

    $response = $this->postJson('/api/admin/licences/load_licences');

    $response->assertStatus(200);
});

test('load licences denies access for regular user', function () {
    $this->actingAs($this->user);

    $response = $this->postJson('/api/admin/licences/load_licences');

    $response->assertStatus(403);
});

test('load licences requires authentication', function () {
    $response = $this->postJson('/api/admin/licences/load_licences');

    $response->assertStatus(401);
});

test('load licences orders by long_name', function () {
    $this->actingAs($this->superAdmin);

    Licence::create(['name' => 'app_z', 'long_name' => 'Zebra']);
    Licence::create(['name' => 'app_a', 'long_name' => 'Alpha']);
    Licence::create(['name' => 'app_m', 'long_name' => 'Middle']);

    $response = $this->postJson('/api/admin/licences/load_licences');

    $response->assertStatus(200);

    $data = $response->json();
    expect($data[0]['long_name'])->toBe('Alpha')
        ->and($data[1]['long_name'])->toBe('Middle')
        ->and($data[2]['long_name'])->toBe('Zebra');
});

test('load licences returns empty array when no licences exist', function () {
    $this->actingAs($this->superAdmin);

    $response = $this->postJson('/api/admin/licences/load_licences');

    $response->assertStatus(200)
        ->assertJson([]);
});

// Delete Licences Tests
test('delete licences removes multiple licences for super admin', function () {
    $this->actingAs($this->superAdmin);

    $licence1 = Licence::create(['name' => 'app1', 'long_name' => 'App 1']);
    $licence2 = Licence::create(['name' => 'app2', 'long_name' => 'App 2']);
    $licence3 = Licence::create(['name' => 'app3', 'long_name' => 'App 3']);

    $data = [$licence1->id, $licence2->id];

    $response = $this->postJson('/api/admin/licences/delete_licences', $data);

    $response->assertStatus(204);

    $this->assertDatabaseMissing('licences', ['id' => $licence1->id]);
    $this->assertDatabaseMissing('licences', ['id' => $licence2->id]);
    $this->assertDatabaseHas('licences', ['id' => $licence3->id]);
});

test('delete licences denies access for admin user', function () {
    $this->actingAs($this->admin);

    $licence = Licence::create(['name' => 'app1', 'long_name' => 'App 1']);

    $response = $this->postJson('/api/admin/licences/delete_licences', [$licence->id]);

    $response->assertStatus(403);
});

test('delete licences denies access for regular user', function () {
    $this->actingAs($this->user);

    $licence = Licence::create(['name' => 'app1', 'long_name' => 'App 1']);

    $response = $this->postJson('/api/admin/licences/delete_licences', [$licence->id]);

    $response->assertStatus(403);
});

test('delete licences requires authentication', function () {
    $licence = Licence::create(['name' => 'app1', 'long_name' => 'App 1']);

    $response = $this->postJson('/api/admin/licences/delete_licences', [$licence->id]);

    $response->assertStatus(401);
});

test('delete licences prevents deletion if assigned to school', function () {
    $this->actingAs($this->superAdmin);

    $licence = Licence::create(['name' => 'app1', 'long_name' => 'App 1']);

    // Assign licence to school
    SchoolLicence::create([
        'school_id' => $this->school->id,
        'licence_id' => $licence->id,
        'valid_until' => now()->addYear(),
    ]);

    $response = $this->postJson('/api/admin/licences/delete_licences', [$licence->id]);

    $response->assertStatus(409);

    $this->assertDatabaseHas('licences', ['id' => $licence->id]);
});

test('delete licences validates licence ids exist', function () {
    $this->actingAs($this->superAdmin);

    $response = $this->postJson('/api/admin/licences/delete_licences', [99999]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['0']);
});

test('delete licences accepts empty array', function () {
    $this->actingAs($this->superAdmin);

    $response = $this->postJson('/api/admin/licences/delete_licences', []);

    $response->assertStatus(204);
});

// Integration Tests
test('full licence crud workflow works correctly', function () {
    $this->actingAs($this->superAdmin);

    // Create
    $createData = [
        'name' => 'full_test_app',
        'long_name' => 'Full Test Application',
        'is_selectable' => true,
        'price_per_year' => 1200,
        'start_day_month' => '01.09.',
        'end_day_month' => '31.07.',
    ];

    $createResponse = $this->postJson('/api/admin/licences', $createData);
    $createResponse->assertStatus(200);
    $licenceId = $createResponse->json('id');

    // Read (Index)
    $indexResponse = $this->getJson('/api/admin/licences?search_string=Full');
    $indexResponse->assertStatus(200);
    expect($indexResponse->json('data'))->toHaveCount(1);

    // Update
    $updateData = [
        'id' => $licenceId,
        'name' => 'full_test_app',
        'long_name' => 'Updated Full Test',
        'is_selectable' => false,
        'price_per_year' => 1500,
        'start_day_month' => '15.09.',
        'end_day_month' => '30.06.',
    ];

    $updateResponse = $this->putJson("/api/admin/licences/{$licenceId}", $updateData);
    $updateResponse->assertStatus(200)
        ->assertJson(['long_name' => 'Updated Full Test']);

    // Load All
    $loadResponse = $this->postJson('/api/admin/licences/load_licences');
    $loadResponse->assertStatus(200);

    // Delete
    $deleteResponse = $this->postJson('/api/admin/licences/delete_licences', [$licenceId]);
    $deleteResponse->assertStatus(204);

    $this->assertDatabaseMissing('licences', ['id' => $licenceId]);
});

test('pagination works correctly with multiple licences', function () {
    $this->actingAs($this->superAdmin);

    // Create 25 licences (assuming pagination is 15 per page)
    for ($i = 1; $i <= 25; $i++) {
        Licence::create([
            'name' => "app_{$i}",
            'long_name' => "Application {$i}",
        ]);
    }

    // Get first page
    $response = $this->getJson('/api/admin/licences?page=1');

    $response->assertStatus(200);

    $meta = $response->json('meta');
    expect($meta['current_page'])->toBe(1)
        ->and($meta['total'])->toBe(25);
});

test('licence model attributes are correctly set', function () {
    $this->actingAs($this->superAdmin);

    $data = [
        'name' => 'attribute_test',
        'long_name' => 'Attribute Test App',
        'is_selectable' => false,
        'price_per_year' => 999,
        'start_day_month' => '01.09.',
        'end_day_month' => '31.07.',
    ];

    $response = $this->postJson('/api/admin/licences', $data);

    $response->assertStatus(200);

    $licence = Licence::where('name', 'attribute_test')->first();

    expect($licence->name)->toBe('attribute_test')
        ->and($licence->long_name)->toBe('Attribute Test App')
        ->and($licence->is_selectable)->toBe(0)
        ->and($licence->price_per_year)->toBe(999)
        ->and($licence->start_day_month)->toBe('09-01')
        ->and($licence->end_day_month)->toBe('07-31');
});

test('index normalizes whole-number decimal prices in the licence payload', function () {
    $this->actingAs($this->superAdmin);

    Licence::create([
        'name' => 'decimal_index_test',
        'long_name' => 'Decimal Index Test',
        'price_per_year' => '200.00',
    ]);

    $response = $this->getJson('/api/admin/licences?search_string=Decimal');

    $response->assertStatus(200)
        ->assertJsonPath('data.0.price_per_year', 200);
});

test('super admin can save licence model template', function () {
    $this->actingAs($this->superAdmin);

    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'register_admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']);

    $licence = Licence::create([
        'name' => 'template_test',
        'long_name' => 'Template Test',
    ]);

    $payload = [
        'licence_model' => [
            'school_licence_enabled' => true,
            'school_price_per_year' => '199',
            'school_included_storage_gb' => '10',
            'school_extra_storage_step_gb' => '100',
            'school_extra_storage_step_price' => '5',
            'admin_licence_enabled' => true,
            'admin_price_per_year' => '59',
            'admin_role_names' => ['admin', 'register_admin'],
            'admin_included_storage_gb' => '10',
            'admin_extra_storage_step_gb' => '100',
            'admin_extra_storage_step_price' => '5',
            'user_licence_enabled' => true,
            'user_price_per_year' => '29',
            'user_role_names' => ['teacher'],
            'user_included_storage_gb' => '10',
            'user_extra_storage_step_gb' => '100',
            'user_extra_storage_step_price' => '5',
        ],
    ];

    $response = $this->putJson("/api/admin/licences/{$licence->id}/save_licence_model", $payload);

    $response->assertStatus(200)
        ->assertJsonPath('licence_schema_version', 2)
        ->assertJsonPath('licence_model.school_licence_enabled', true)
        ->assertJsonPath('licence_model.school_price_per_year', '199')
        ->assertJsonPath('licence_model.school_included_storage_gb', '10')
        ->assertJsonPath('licence_model.school_extra_storage_step_gb', '100')
        ->assertJsonPath('licence_model.school_extra_storage_step_price', '5')
        ->assertJsonPath('licence_model.admin_licence_enabled', true)
        ->assertJsonPath('licence_model.admin_role_names.0', 'admin')
        ->assertJsonPath('licence_model.admin_role_names.1', 'register_admin')
        ->assertJsonPath('licence_model.admin_included_storage_gb', '10')
        ->assertJsonPath('licence_model.admin_extra_storage_step_gb', '100')
        ->assertJsonPath('licence_model.admin_extra_storage_step_price', '5')
        ->assertJsonPath('licence_model.user_licence_enabled', true)
        ->assertJsonPath('licence_model.user_role_names.0', 'teacher')
        ->assertJsonPath('licence_model.user_included_storage_gb', '10')
        ->assertJsonPath('licence_model.user_extra_storage_step_gb', '100')
        ->assertJsonPath('licence_model.user_extra_storage_step_price', '5');

    $this->assertDatabaseHas('licences', [
        'id' => $licence->id,
        'licence_schema_version' => 2,
        'school_licence_enabled' => 1,
        'school_price_per_year' => '199',
        'school_included_storage_gb' => 10,
        'school_extra_storage_step_gb' => 100,
        'school_extra_storage_step_price' => '5',
        'admin_licence_enabled' => 1,
        'admin_price_per_year' => '59',
        'admin_included_storage_gb' => 10,
        'admin_extra_storage_step_gb' => 100,
        'admin_extra_storage_step_price' => '5',
        'user_licence_enabled' => 1,
        'user_price_per_year' => '29',
        'user_included_storage_gb' => 10,
        'user_extra_storage_step_gb' => 100,
        'user_extra_storage_step_price' => '5',
    ]);
});

test('save licence model requires super admin', function () {
    $this->actingAs($this->admin);
    Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']);

    $licence = Licence::create([
        'name' => 'template_permission_test',
        'long_name' => 'Template Permission Test',
    ]);

    $response = $this->putJson("/api/admin/licences/{$licence->id}/save_licence_model", [
        'licence_model' => [
            'school_licence_enabled' => true,
            'school_price_per_year' => '199',
            'admin_licence_enabled' => true,
            'admin_price_per_year' => '59',
            'admin_role_names' => ['admin'],
            'user_licence_enabled' => true,
            'user_price_per_year' => '29',
            'user_role_names' => ['teacher'],
        ],
    ]);

    $response->assertStatus(403);
});

test('save licence model rejects non-integer positive price fields', function () {
    $this->actingAs($this->superAdmin);

    Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']);

    $licence = Licence::create([
        'name' => 'template_invalid_price_test',
        'long_name' => 'Template Invalid Price Test',
    ]);

    $response = $this->putJson("/api/admin/licences/{$licence->id}/save_licence_model", [
        'licence_model' => [
            'school_licence_enabled' => true,
            'school_price_per_year' => '199.00',
            'admin_licence_enabled' => true,
            'admin_price_per_year' => '0',
            'admin_role_names' => ['admin'],
            'user_licence_enabled' => true,
            'user_price_per_year' => '-29',
            'user_role_names' => ['teacher'],
        ],
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors([
            'licence_model.school_price_per_year',
            'licence_model.admin_price_per_year',
            'licence_model.user_price_per_year',
        ]);
});

test('save licence model rejects invalid storage tariff fields', function () {
    $this->actingAs($this->superAdmin);

    Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

    $licence = Licence::create([
        'name' => 'template_invalid_storage_tariff_test',
        'long_name' => 'Template Invalid Storage Tariff Test',
    ]);

    $response = $this->putJson("/api/admin/licences/{$licence->id}/save_licence_model", [
        'licence_model' => [
            'school_licence_enabled' => true,
            'school_price_per_year' => '199',
            'school_included_storage_gb' => '0',
            'school_extra_storage_step_gb' => '100',
            'school_extra_storage_step_price' => '5',
            'admin_licence_enabled' => true,
            'admin_price_per_year' => '59',
            'admin_role_names' => ['admin'],
            'admin_included_storage_gb' => '10',
            'admin_extra_storage_step_gb' => '0',
            'admin_extra_storage_step_price' => '5',
            'user_licence_enabled' => true,
            'user_price_per_year' => '29',
            'user_role_names' => ['teacher'],
            'user_included_storage_gb' => '10',
            'user_extra_storage_step_gb' => '100',
            'user_extra_storage_step_price' => '0',
        ],
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors([
            'licence_model.school_included_storage_gb',
            'licence_model.admin_extra_storage_step_gb',
            'licence_model.user_extra_storage_step_price',
        ]);
});

test('save licence model allows inactive user licence without user roles', function () {
    $this->actingAs($this->superAdmin);

    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

    $licence = Licence::create([
        'name' => 'template_admin_only_test',
        'long_name' => 'Template Admin Only Test',
    ]);

    $response = $this->putJson("/api/admin/licences/{$licence->id}/save_licence_model", [
        'licence_model' => [
            'school_licence_enabled' => false,
            'school_price_per_year' => null,
            'admin_licence_enabled' => true,
            'admin_price_per_year' => '10',
            'admin_role_names' => ['admin'],
            'user_licence_enabled' => false,
            'user_price_per_year' => null,
            'user_role_names' => [],
        ],
    ]);

    $response->assertOk()
        ->assertJsonPath('licence_model.admin_licence_enabled', true)
        ->assertJsonPath('licence_model.admin_price_per_year', '10')
        ->assertJsonPath('licence_model.admin_role_names.0', 'admin')
        ->assertJsonPath('licence_model.user_licence_enabled', false)
        ->assertJsonPath('licence_model.user_role_names', []);
});

test('save licence model stores wildcard user role selection', function () {
    $this->actingAs($this->superAdmin);

    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

    $licence = Licence::create([
        'name' => 'template_user_all_roles_test',
        'long_name' => 'Template User All Roles Test',
    ]);

    $response = $this->putJson("/api/admin/licences/{$licence->id}/save_licence_model", [
        'licence_model' => [
            'school_licence_enabled' => false,
            'school_price_per_year' => null,
            'admin_licence_enabled' => true,
            'admin_price_per_year' => '10',
            'admin_role_names' => ['admin'],
            'user_licence_enabled' => true,
            'user_price_per_year' => '29',
            'user_role_names' => ['*'],
        ],
    ]);

    $response->assertOk()
        ->assertJsonPath('licence_model.user_licence_enabled', true)
        ->assertJsonPath('licence_model.user_role_names.0', '*');
});
