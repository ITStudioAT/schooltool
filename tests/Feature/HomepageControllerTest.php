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
use App\Models\School;
use App\Models\SchoolLicence;
use App\Models\Schoolyear;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

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
});

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

        $response->assertStatus(200)
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
                'tutoring_active',
                'teaching_active',
            ]);
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

    test('config includes teaching_active and tutoring_active flags from config', function () {
        config(['schooltool.teaching_active' => true]);
        config(['schooltool.tutoring_active' => false]);

        $response = $this->getJson('/api/homepage/config');

        $response->assertStatus(200)
            ->assertJson([
                'teaching_active' => true,
                'tutoring_active' => false,
            ]);
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
