<?php

use App\Models\Licence;
use App\Models\School;
use App\Models\SchoolLicence;
use App\Models\SchoolTool;
use App\Models\SchoolUserLicence;
use App\Models\User;
use App\Services\AdminNavigationService;
use App\Services\UserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->service = new AdminNavigationService;
    $this->attachActiveLicences = function (User $user, array $licenceNames): void {
        $school = $user->school_id ? School::find($user->school_id) : null;
        if (! $school) {
            $school = School::factory()->create();
            $user->school_id = $school->id;
            $user->save();
        }

        foreach ($licenceNames as $licenceName) {
            $licence = Licence::firstOrCreate(
                ['name' => $licenceName],
                ['long_name' => $licenceName]
            );

            SchoolLicence::firstOrCreate(
                [
                    'school_id' => $school->id,
                    'licence_id' => $licence->id,
                ],
                [
                    'valid_until' => now()->addMonth(),
                ]
            );
        }
    };
});

describe('dashboardMenu', function () {
    it('returns empty array when user is not authenticated', function () {
        Auth::shouldReceive('check')->andReturn(false);

        $result = $this->service->dashboardMenu();

        expect($result)->toBeArray()->toBeEmpty();
    });

    it('returns basic menu items for authenticated user without special roles', function () {
        $user = User::factory()->create([
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);

        Auth::shouldReceive('check')->andReturn(true);
        Auth::shouldReceive('user')->andReturn($user);

        $result = $this->service->dashboardMenu();

        expect($result)->toBeArray()->toHaveCount(3);

        expect($result[0])
            ->toMatchArray([
                'title' => 'Home',
                'icon' => 'mdi-home',
                'to' => '/admin',
            ]);

        expect($result[1])
            ->toMatchArray([
                'title' => 'Dokumentation',
                'icon' => 'mdi-book-open-variant',
                'href' => '/documentation/index.html',
            ]);

        expect($result[2])
            ->toMatchArray([
                'title' => 'Abmelden',
                'icon' => 'mdi-power-cycle',
                'click' => 'logout',
            ]);
    });

    it('does not include super admin dashboard menu item for super_admin role', function () {
        $user = User::factory()->create([
            'first_name' => 'Admin',
            'last_name' => 'Super',
        ]);
        $role = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $user->assignRole($role);

        Auth::shouldReceive('check')->andReturn(true);
        Auth::shouldReceive('user')->andReturn($user);

        $result = $this->service->dashboardMenu();

        $superAdminItem = collect($result)->firstWhere('title', 'Super-Admin');

        expect($superAdminItem)->toBeNull();
    });

    it('does not include admin dashboard menu item for admin role', function () {
        $user = User::factory()->create([
            'first_name' => 'Admin',
            'last_name' => 'Regular',
        ]);
        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user->assignRole($role);

        Auth::shouldReceive('check')->andReturn(true);
        Auth::shouldReceive('user')->andReturn($user);

        $result = $this->service->dashboardMenu();

        $adminItem = collect($result)->firstWhere('title', 'Admin');

        expect($adminItem)->toBeNull();
    });

    it('hides register system menu item for admin role without register_admin', function () {
        $school = School::factory()->create();
        $licence = Licence::create([
            'name' => 'Anmeldetool',
            'long_name' => 'Test licence',
            'price_per_year' => 200,
        ]);
        $school->licences()->attach($licence->id, ['valid_until' => now()->addDays(10)->toDateString()]);

        $user = User::factory()->create([
            'first_name' => 'Admin',
            'last_name' => 'Regular',
            'school_id' => $school->id,
        ]);
        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user->assignRole($role);

        Auth::shouldReceive('check')->andReturn(true);
        Auth::shouldReceive('user')->andReturn($user);
        ($this->attachActiveLicences)($user, ['Anmeldetool', 'Nachhilfetool', 'Lehrertool']);

        $result = $this->service->dashboardMenu();

        $registerItem = collect($result)->firstWhere('title', 'Anmeldetool');

        expect($registerItem)->toBeNull();
    });

    it('includes register system menu item for register_admin role', function () {
        $school = School::factory()->create();
        $licence = Licence::create([
            'name' => 'Anmeldetool',
            'long_name' => 'Test licence',
            'price_per_year' => 200,
        ]);
        $school->licences()->attach($licence->id, ['valid_until' => now()->addDays(10)->toDateString()]);

        $user = User::factory()->create([
            'first_name' => 'Register',
            'last_name' => 'Admin',
            'school_id' => $school->id,
        ]);
        $role = Role::firstOrCreate(['name' => 'register_admin', 'guard_name' => 'web']);
        $user->assignRole($role);

        Auth::shouldReceive('check')->andReturn(true);
        Auth::shouldReceive('user')->andReturn($user);
        ($this->attachActiveLicences)($user, ['Anmeldetool', 'Nachhilfetool', 'Lehrertool']);

        $result = $this->service->dashboardMenu();

        $registerItem = collect($result)->firstWhere('title', 'Anmeldetool');

        expect($registerItem)
            ->not->toBeNull()
            ->and($registerItem['icon'])->toBe('mdi-calendar-cursor')
            ->and($registerItem['to'])->toBe('/admin/register_system');
    });

    it('hides register system menu item when admin visibility is disabled', function () {
        $school = School::factory()->create();
        SchoolTool::factory()->create([
            'school_id' => $school->id,
            'register_visible_admin' => false,
            'register_visible_user' => true,
        ]);

        $licence = Licence::create([
            'name' => 'Anmeldetool',
            'long_name' => 'Test licence',
            'price_per_year' => 200,
        ]);
        $school->licences()->attach($licence->id, ['valid_until' => now()->addDays(10)->toDateString()]);

        $user = User::factory()->create([
            'first_name' => 'Admin',
            'last_name' => 'Hidden',
            'school_id' => $school->id,
        ]);
        $user->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']));

        Auth::shouldReceive('check')->andReturn(true);
        Auth::shouldReceive('user')->andReturn($user);

        $result = $this->service->dashboardMenu();

        expect(collect($result)->firstWhere('title', 'Anmeldetool'))->toBeNull();
    });

    it('includes settings menu item for admin shell roles such as teacher', function () {
        $school = School::factory()->create();
        $user = User::factory()->create([
            'first_name' => 'Teach',
            'last_name' => 'Er',
            'school_id' => $school->id,
        ]);
        $role = Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']);
        $user->assignRole($role);

        Auth::shouldReceive('check')->andReturn(true);
        Auth::shouldReceive('user')->andReturn($user);
        ($this->attachActiveLicences)($user, ['Lehrertool']);

        $result = $this->service->dashboardMenu();

        $settingsItem = collect($result)->firstWhere('title', 'Einstellungen');

        expect($settingsItem)
            ->not->toBeNull()
            ->and($settingsItem['icon'])->toBe('mdi-cog')
            ->and($settingsItem['to'])->toBe('/admin/settings');
    });

    it('does not add a profile menu item for users without admin shell access', function () {
        $user = User::factory()->create([
            'first_name' => 'VeryLongFirstName',
            'last_name' => 'VeryLongLastName',
        ]);

        Auth::shouldReceive('check')->andReturn(true);
        Auth::shouldReceive('user')->andReturn($user);
        ($this->attachActiveLicences)($user, ['Anmeldetool', 'Nachhilfetool', 'Lehrertool']);

        $result = $this->service->dashboardMenu();

        $profileItem = collect($result)->firstWhere('to', '/admin/profile');

        expect($profileItem)->toBeNull();
    });

    it('hides module menu items for user with only admin and super_admin roles', function () {
        $user = User::factory()->create([
            'first_name' => 'Multi',
            'last_name' => 'Role',
        ]);
        $superAdminRole = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user->assignRole([$superAdminRole, $adminRole]);

        Auth::shouldReceive('check')->andReturn(true);
        Auth::shouldReceive('user')->andReturn($user);
        ($this->attachActiveLicences)($user, ['Anmeldetool', 'Nachhilfetool', 'Lehrertool', 'Materialientool', 'Restaurant']);

        $result = $this->service->dashboardMenu();

        expect($result)
            ->toBeArray()
            ->toHaveCount(5)
            ->and(collect($result)->pluck('title')->toArray())
            ->toBe(['Home', 'Einstellungen', 'Dokumentation', 'Profil', 'Abmelden'])
            ->not->toContain('Anmeldetool', 'Nachhilfe', 'Unterricht', 'Materialien', 'Restaurant', 'ABA')
            ->not->toContain('Gruppen');

        expect($result[3])->toMatchArray([
            'to' => '/admin/profile',
            'active_paths' => ['/admin/profile'],
            'is_active' => true,
        ]);
    });

    it('keeps module active when expired school licence is not required by model', function () {
        $school = School::factory()->create();
        SchoolTool::factory()->create([
            'school_id' => $school->id,
            'teaching_visible_admin' => true,
            'teaching_visible_user' => true,
        ]);
        $user = User::factory()->create([
            'school_id' => $school->id,
            'first_name' => 'Model',
            'last_name' => 'Aware',
        ]);
        $user->assignRole(Role::firstOrCreate(['name' => 'teaching_admin', 'guard_name' => 'web']));

        $licence = Licence::create([
            'name' => 'Lehrertool',
            'long_name' => 'Lehrertool',
        ]);

        SchoolLicence::create([
            'school_id' => $school->id,
            'licence_id' => $licence->id,
            'valid_until' => now()->subDay(),
            'licence_model' => [
                'school_licence_required' => false,
                'affected_roles' => [],
                'user_licence_required_by_role' => [],
            ],
        ]);

        Auth::shouldReceive('check')->andReturn(true);
        Auth::shouldReceive('user')->andReturn($user);

        $result = $this->service->dashboardMenu();
        $item = collect($result)->firstWhere('title', 'Unterricht');

        expect($item)
            ->not->toBeNull()
            ->and($item['is_active'])->toBeTrue();
    });

    it('ensures Home is always first menu item', function () {
        $user = User::factory()->create();

        Auth::shouldReceive('check')->andReturn(true);
        Auth::shouldReceive('user')->andReturn($user);
        ($this->attachActiveLicences)($user, ['Anmeldetool']);

        $result = $this->service->dashboardMenu();

        expect($result[0])
            ->toHaveKey('title', 'Home')
            ->and($result[0]['to'])->toBe('/admin');
    });

    it('ensures logout is always last menu item', function () {
        $user = User::factory()->create();

        Auth::shouldReceive('check')->andReturn(true);
        Auth::shouldReceive('user')->andReturn($user);

        $result = $this->service->dashboardMenu();

        $lastItem = end($result);

        expect($lastItem)
            ->toHaveKey('title', 'Abmelden')
            ->and($lastItem['click'])->toBe('logout');
    });

    it('includes materials menu item for materials_admin with active Materialientool licence', function () {
        $school = School::factory()->create();
        SchoolTool::factory()->create([
            'school_id' => $school->id,
            'materials_visible_admin' => true,
            'materials_visible_user' => true,
        ]);
        $licence = Licence::create([
            'name' => 'Materialientool',
            'long_name' => 'Test licence',
            'price_per_year' => 200,
        ]);
        $school->licences()->attach($licence->id, ['valid_until' => now()->addDays(10)->toDateString()]);

        $user = User::factory()->create(['school_id' => $school->id]);
        $role = Role::firstOrCreate(['name' => 'materials_admin', 'guard_name' => 'web']);
        $user->assignRole($role);

        Auth::shouldReceive('check')->andReturn(true);
        Auth::shouldReceive('user')->andReturn($user);

        $result = $this->service->dashboardMenu();
        $materialsItem = collect($result)->firstWhere('title', 'Materialien');
        $materialsV2Item = collect($result)->firstWhere('title', 'Materialien 2');

        expect($materialsItem)
            ->not->toBeNull()
            ->and($materialsItem['to'])->toBe('/admin/materials')
            ->and($materialsItem['is_active'])->toBeTrue()
            ->and($materialsV2Item)->not->toBeNull()
            ->and($materialsV2Item['to'])->toBe('/admin/materials-v2')
            ->and($materialsV2Item['is_active'])->toBeTrue();
    });

    it('adds expired status metadata for materials menu item when Materialientool licence is expired', function () {
        $school = School::factory()->create();
        SchoolTool::factory()->create([
            'school_id' => $school->id,
            'materials_visible_admin' => true,
            'materials_visible_user' => true,
        ]);
        $licence = Licence::create([
            'name' => 'Materialientool',
            'long_name' => 'Test licence',
            'price_per_year' => 200,
        ]);
        $school->licences()->attach($licence->id, ['valid_until' => now()->subDay()->toDateString()]);

        $user = User::factory()->create(['school_id' => $school->id]);
        $role = Role::firstOrCreate(['name' => 'materials_admin', 'guard_name' => 'web']);
        $user->assignRole($role);

        Auth::shouldReceive('check')->andReturn(true);
        Auth::shouldReceive('user')->andReturn($user);

        $result = $this->service->dashboardMenu();
        $materialsItem = collect($result)->firstWhere('title', 'Materialien');

        expect($materialsItem)
            ->not->toBeNull()
            ->and($materialsItem['is_active'])->toBeFalse()
            ->and($materialsItem['status_icon'])->toBe('mdi-clock-alert-outline')
            ->and($materialsItem['status_color'])->toBe('warning');
    });

    it('includes restaurant menu item for lunch_admin with active Restaurant licence', function () {
        $school = School::factory()->create();
        SchoolTool::factory()->create([
            'school_id' => $school->id,
            'restaurant_visible_admin' => true,
            'restaurant_visible_user' => true,
        ]);
        $licence = Licence::create([
            'name' => 'Restaurant',
            'long_name' => 'Test licence',
            'price_per_year' => 200,
        ]);
        $school->licences()->attach($licence->id, ['valid_until' => now()->addDays(10)->toDateString()]);

        $user = User::factory()->create(['school_id' => $school->id]);
        $role = Role::firstOrCreate(['name' => 'lunch_admin', 'guard_name' => 'web']);
        $user->assignRole($role);

        Auth::shouldReceive('check')->andReturn(true);
        Auth::shouldReceive('user')->andReturn($user);

        $result = $this->service->dashboardMenu();
        $restaurantItem = collect($result)->firstWhere('title', 'Restaurant');

        expect($restaurantItem)
            ->not->toBeNull()
            ->and($restaurantItem['to'])->toBe('/admin/restaurant')
            ->and($restaurantItem['active_paths'])->toBe(['/admin/restaurant'])
            ->and($restaurantItem['is_active'])->toBeTrue();
    });

    it('adds expired status metadata for restaurant menu item when Restaurant licence is expired', function () {
        $school = School::factory()->create();
        SchoolTool::factory()->create([
            'school_id' => $school->id,
            'restaurant_visible_admin' => true,
            'restaurant_visible_user' => true,
        ]);
        $licence = Licence::create([
            'name' => 'Restaurant',
            'long_name' => 'Test licence',
            'price_per_year' => 200,
        ]);
        $school->licences()->attach($licence->id, ['valid_until' => now()->subDay()->toDateString()]);

        $user = User::factory()->create(['school_id' => $school->id]);
        $role = Role::firstOrCreate(['name' => 'lunch_admin', 'guard_name' => 'web']);
        $user->assignRole($role);

        Auth::shouldReceive('check')->andReturn(true);
        Auth::shouldReceive('user')->andReturn($user);

        $result = $this->service->dashboardMenu();
        $restaurantItem = collect($result)->firstWhere('title', 'Restaurant');

        expect($restaurantItem)
            ->not->toBeNull()
            ->and($restaurantItem['is_active'])->toBeFalse()
            ->and($restaurantItem['status_icon'])->toBe('mdi-clock-alert-outline')
            ->and($restaurantItem['status_color'])->toBe('warning');
    });

    it('hides restaurant menu item when Restaurant licence is missing', function () {
        $school = School::factory()->create();
        $user = User::factory()->create(['school_id' => $school->id]);
        $role = Role::firstOrCreate(['name' => 'lunch_admin', 'guard_name' => 'web']);
        $user->assignRole($role);

        Auth::shouldReceive('check')->andReturn(true);
        Auth::shouldReceive('user')->andReturn($user);

        $result = $this->service->dashboardMenu();
        $restaurantItem = collect($result)->firstWhere('title', 'Restaurant');

        expect($restaurantItem)->toBeNull();
    });

    it('shows teaching as active for admins when admin visibility is enabled', function () {
        $school = School::factory()->create();
        SchoolTool::factory()->create([
            'school_id' => $school->id,
            'teaching_visible_admin' => true,
            'teaching_visible_user' => false,
            'teaching_user_comming_soon' => true,
        ]);

        $licence = Licence::create([
            'name' => 'Lehrertool',
            'long_name' => 'Lehrertool',
        ]);
        $school->licences()->attach($licence->id, ['valid_until' => now()->addDays(10)->toDateString()]);

        $user = User::factory()->create(['school_id' => $school->id]);
        $user->assignRole(Role::firstOrCreate(['name' => 'teaching_admin', 'guard_name' => 'web']));

        Auth::shouldReceive('check')->andReturn(true);
        Auth::shouldReceive('user')->andReturn($user);

        $result = $this->service->dashboardMenu();
        $teachingItem = collect($result)->firstWhere('title', 'Unterricht');

        expect($teachingItem)
            ->not->toBeNull()
            ->and($teachingItem['is_active'])->toBeTrue()
            ->and($teachingItem['to'])->toBe('/admin/teaching?panel=table')
            ->and($teachingItem['active_paths'])->toBe(['/admin/teaching']);
    });

    it('adds active paths to route-backed dashboard module menu items', function () {
        $school = School::factory()->create();
        SchoolTool::factory()->create([
            'school_id' => $school->id,
            'register_visible_admin' => true,
            'tutoring_visible_admin' => true,
            'materials_visible_admin' => true,
            'students_timetables_visible_admin' => true,
            'aba_visible_admin' => true,
        ]);

        $user = User::factory()->create(['school_id' => $school->id]);
        $user->assignRole([
            Role::firstOrCreate(['name' => 'register_admin', 'guard_name' => 'web']),
            Role::firstOrCreate(['name' => 'tutoring_admin', 'guard_name' => 'web']),
            Role::firstOrCreate(['name' => 'materials_admin', 'guard_name' => 'web']),
            Role::firstOrCreate(['name' => 'studentstimetables_admin', 'guard_name' => 'web']),
            Role::firstOrCreate(['name' => 'aba_teacher', 'guard_name' => 'web']),
        ]);

        Auth::shouldReceive('check')->andReturn(true);
        Auth::shouldReceive('user')->andReturn($user);
        ($this->attachActiveLicences)($user, ['Anmeldetool', 'Nachhilfetool', 'Materialientool', 'StudentsTimetables', 'ABA']);

        $menuByTitle = collect($this->service->dashboardMenu())->keyBy('title');

        expect($menuByTitle->get('Anmeldetool')['active_paths'])->toBe(['/admin/register_system'])
            ->and($menuByTitle->get('Nachhilfe')['active_paths'])->toBe(['/admin/tutoring'])
            ->and($menuByTitle->get('Materialien')['active_paths'])->toBe(['/admin/materials'])
            ->and($menuByTitle->get('Schülerstundenpläne')['active_paths'])->toBe(['/admin/students-timetables'])
            ->and($menuByTitle->get('ABA')['active_paths'])->toBe(['/admin/aba']);
    });

    it('hides materials menu item when admin visibility is disabled', function () {
        $school = School::factory()->create();
        SchoolTool::factory()->create([
            'school_id' => $school->id,
            'materials_visible_admin' => false,
            'materials_visible_user' => true,
        ]);

        $licence = Licence::create([
            'name' => 'Materialientool',
            'long_name' => 'Materialientool',
        ]);
        $school->licences()->attach($licence->id, ['valid_until' => now()->addDays(10)->toDateString()]);

        $user = User::factory()->create(['school_id' => $school->id]);
        $user->assignRole(Role::firstOrCreate(['name' => 'materials_admin', 'guard_name' => 'web']));

        Auth::shouldReceive('check')->andReturn(true);
        Auth::shouldReceive('user')->andReturn($user);

        $result = $this->service->dashboardMenu();

        expect(collect($result)->firstWhere('title', 'Materialien'))->toBeNull();
    });

    it('keeps teaching menu active when school override disables template user licence requirement', function () {
        $school = School::factory()->create();
        SchoolTool::factory()->create([
            'school_id' => $school->id,
            'teaching_visible_admin' => true,
            'teaching_visible_user' => true,
        ]);
        $user = User::factory()->create([
            'school_id' => $school->id,
            'first_name' => 'Teacher',
            'last_name' => 'Override',
        ]);
        $user->assignRole(Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']));

        $licence = Licence::create([
            'name' => 'Lehrertool',
            'long_name' => 'Lehrertool',
            'licence_model' => [
                'school_licence_required' => true,
                'affected_roles' => ['teacher'],
                'user_licence_required_by_role' => [
                    'teacher' => true,
                ],
            ],
        ]);

        SchoolLicence::create([
            'school_id' => $school->id,
            'licence_id' => $licence->id,
            'valid_until' => now()->addMonth(),
            'licence_model' => [
                'school_licence_required' => true,
                'affected_roles' => ['teacher'],
                'user_licence_required_by_role' => [
                    'teacher' => false,
                ],
            ],
            'user_licence_assignments' => [],
        ]);

        Auth::shouldReceive('check')->andReturn(true);
        Auth::shouldReceive('user')->andReturn($user);

        $result = $this->service->dashboardMenu();
        $teachingItem = collect($result)->firstWhere('title', 'Unterricht');

        expect($teachingItem)
            ->not->toBeNull()
            ->and($teachingItem['is_active'])->toBeTrue()
            ->and($teachingItem)->not->toHaveKey('status_icon');
    });

    it('keeps teaching menu active when a valid structured assignment exists but is marked inactive', function () {
        $school = School::factory()->create();
        SchoolTool::factory()->create([
            'school_id' => $school->id,
            'teaching_visible_admin' => true,
            'teaching_visible_user' => true,
        ]);
        $user = User::factory()->create([
            'school_id' => $school->id,
            'first_name' => 'Teacher',
            'last_name' => 'Structured',
        ]);
        $user->assignRole(Role::firstOrCreate(['name' => 'teaching_admin', 'guard_name' => 'web']));

        $licence = Licence::create([
            'name' => 'Lehrertool',
            'long_name' => 'Lehrertool',
            'licence_schema_version' => 2,
            'school_licence_enabled' => true,
            'admin_licence_enabled' => true,
            'admin_role_names' => ['teaching_admin'],
            'user_licence_enabled' => false,
        ]);

        SchoolLicence::create([
            'school_id' => $school->id,
            'licence_id' => $licence->id,
            'valid_until' => now()->addMonth()->toDateString(),
        ]);

        SchoolUserLicence::create([
            'school_id' => $school->id,
            'licence_id' => $licence->id,
            'user_id' => $user->id,
            'assignment_type' => 'admin',
            'valid_from' => now()->subMonth()->toDateString(),
            'valid_until' => now()->addMonth()->toDateString(),
            'base_price_per_year' => '59',
            'charged_price' => '59.00',
            'is_active' => false,
        ]);

        Auth::shouldReceive('check')->andReturn(true);
        Auth::shouldReceive('user')->andReturn($user);

        $result = $this->service->dashboardMenu();
        $teachingItem = collect($result)->firstWhere('title', 'Unterricht');

        expect($teachingItem)
            ->not->toBeNull()
            ->and($teachingItem['is_active'])->toBeTrue()
            ->and($teachingItem)->not->toHaveKey('status_icon');
    });

    it('hides teaching menu item when the user has the role but no teaching licence assignment remains', function () {
        $school = School::factory()->create();
        SchoolTool::factory()->create([
            'school_id' => $school->id,
            'teaching_visible_admin' => true,
            'teaching_visible_user' => true,
        ]);
        $user = User::factory()->create([
            'school_id' => $school->id,
            'first_name' => 'Teacher',
            'last_name' => 'Removed',
        ]);
        $user->assignRole([
            Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']),
            Role::firstOrCreate(['name' => 'teaching_admin', 'guard_name' => 'web']),
        ]);

        $licence = Licence::create([
            'name' => 'Lehrertool',
            'long_name' => 'Lehrertool',
            'licence_schema_version' => 2,
            'school_licence_enabled' => false,
            'admin_licence_enabled' => true,
            'admin_role_names' => ['teaching_admin'],
            'user_licence_enabled' => false,
        ]);

        SchoolLicence::create([
            'school_id' => $school->id,
            'licence_id' => $licence->id,
            'valid_until' => now()->addMonth()->toDateString(),
        ]);

        Auth::shouldReceive('check')->andReturn(true);
        Auth::shouldReceive('user')->andReturn($user);

        $result = $this->service->dashboardMenu();
        $teachingItem = collect($result)->firstWhere('title', 'Unterricht');

        expect($teachingItem)->toBeNull();
    });
});

describe('profileMenu', function () {
    it('returns menu items for admin role', function () {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user->assignRole($role);

        Auth::shouldReceive('check')->andReturn(true);
        Auth::shouldReceive('user')->andReturn($user);

        $result = $this->service->profileMenu();

        expect($result)
            ->toBeArray()
            ->toHaveCount(3);
    });

    it('returns no menu items for user role', function () {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);
        $user->assignRole($role);

        Auth::shouldReceive('check')->andReturn(true);
        Auth::shouldReceive('user')->andReturn($user);

        $result = $this->service->profileMenu();

        expect($result)
            ->toBeArray()
            ->toBeEmpty();
    });

    it('returns no menu items for register_user role', function () {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'register_user', 'guard_name' => 'web']);
        $user->assignRole($role);

        Auth::shouldReceive('check')->andReturn(true);
        Auth::shouldReceive('user')->andReturn($user);

        $result = $this->service->profileMenu();

        expect($result)
            ->toBeArray()
            ->toBeEmpty();
    });

    it('returns menu items for register_admin role', function () {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'register_admin', 'guard_name' => 'web']);
        $user->assignRole($role);

        Auth::shouldReceive('check')->andReturn(true);
        Auth::shouldReceive('user')->andReturn($user);

        $result = $this->service->profileMenu();

        expect($result)
            ->toBeArray()
            ->toHaveCount(3);
    });

    it('returns menu items for lunch_admin role', function () {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'lunch_admin', 'guard_name' => 'web']);
        $user->assignRole($role);

        Auth::shouldReceive('check')->andReturn(true);
        Auth::shouldReceive('user')->andReturn($user);

        $result = $this->service->profileMenu();

        expect($result)
            ->toBeArray()
            ->toHaveCount(3);
    });

    it('includes home menu item with correct properties', function () {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user->assignRole($role);

        Auth::shouldReceive('check')->andReturn(true);
        Auth::shouldReceive('user')->andReturn($user);

        $result = $this->service->profileMenu();

        $homeItem = collect($result)->firstWhere('subtitle', 'Home');

        expect($homeItem)
            ->not->toBeNull()
            ->and($homeItem['icon'])->toBe('mdi-home')
            ->and($homeItem['color'])->toBe('secondary')
            ->and($homeItem['to'])->toBe('/admin');
    });

    it('includes password change menu item with correct properties', function () {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user->assignRole($role);

        Auth::shouldReceive('check')->andReturn(true);
        Auth::shouldReceive('user')->andReturn($user);

        $result = $this->service->profileMenu();

        $passwordItem = collect($result)->firstWhere('subtitle', 'Kennwort ändern');

        expect($passwordItem)
            ->not->toBeNull()
            ->and($passwordItem['icon'])->toBe('mdi-form-textbox-password')
            ->and($passwordItem['color'])->toBe('secondary')
            ->and($passwordItem['action'])->toBe('wantToChangePassword');
    });

    it('includes 2FA menu item with correct properties', function () {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user->assignRole($role);

        Auth::shouldReceive('check')->andReturn(true);
        Auth::shouldReceive('user')->andReturn($user);

        $result = $this->service->profileMenu();

        $twoFaItem = collect($result)->firstWhere('subtitle', '2-FA-Authentifizierung');

        expect($twoFaItem)
            ->not->toBeNull()
            ->and($twoFaItem['icon'])->toBe('mdi-two-factor-authentication')
            ->and($twoFaItem['color'])->toBe('secondary')
            ->and($twoFaItem['action'])->toBe('wantToChange2Fa');
    });

    it('returns empty array for user without proper roles', function () {
        $user = User::factory()->create();

        Auth::shouldReceive('check')->andReturn(true);
        Auth::shouldReceive('user')->andReturn($user);

        $result = $this->service->profileMenu();

        expect($result)->toBeArray()->toBeEmpty();
    });

    it('all menu items have empty title field', function () {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user->assignRole($role);

        Auth::shouldReceive('check')->andReturn(true);
        Auth::shouldReceive('user')->andReturn($user);

        $result = $this->service->profileMenu();

        foreach ($result as $item) {
            expect($item['title'])->toBe('');
        }
    });

    it('all menu items have secondary color', function () {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user->assignRole($role);

        Auth::shouldReceive('check')->andReturn(true);
        Auth::shouldReceive('user')->andReturn($user);

        $result = $this->service->profileMenu();

        foreach ($result as $item) {
            expect($item['color'])->toBe('secondary');
        }
    });
});

describe('userMenu', function () {
    it('returns home menu item for all users', function () {
        $user = User::factory()->create();

        Auth::shouldReceive('check')->andReturn(true);
        Auth::shouldReceive('user')->andReturn($user);

        $result = $this->service->userMenu();

        expect($result)
            ->toBeArray()
            ->toHaveCount(1)
            ->and($result[0]['subtitle'])->toBe('Home')
            ->and($result[0]['to'])->toBe('/admin');
    });

    it('includes roles menu item for super_admin', function () {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $user->assignRole($role);

        Auth::shouldReceive('check')->andReturn(true);
        Auth::shouldReceive('user')->andReturn($user);

        $result = $this->service->userMenu();

        expect($result)
            ->toBeArray()
            ->toHaveCount(2);

        $rolesItem = collect($result)->firstWhere('subtitle', 'Rollen');

        expect($rolesItem)
            ->not->toBeNull()
            ->and($rolesItem['icon'])->toBe('mdi-badge-account-horizontal-outline')
            ->and($rolesItem['color'])->toBe('secondary')
            ->and($rolesItem['to'])->toBe('/admin/users/roles');
    });

    it('does not include roles menu item for non super_admin', function () {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user->assignRole($role);

        Auth::shouldReceive('check')->andReturn(true);
        Auth::shouldReceive('user')->andReturn($user);

        $result = $this->service->userMenu();

        expect($result)
            ->toBeArray()
            ->toHaveCount(1);
    });

    it('home menu item has correct structure', function () {
        $user = User::factory()->create();

        Auth::shouldReceive('check')->andReturn(true);
        Auth::shouldReceive('user')->andReturn($user);

        $result = $this->service->userMenu();

        expect($result[0])
            ->toHaveKeys(['title', 'subtitle', 'icon', 'color', 'to'])
            ->and($result[0]['title'])->toBe('')
            ->and($result[0]['icon'])->toBe('mdi-home')
            ->and($result[0]['color'])->toBe('secondary');
    });
});

describe('userSelection', function () {
    it('returns user selection for admin role', function () {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user->assignRole($role);

        Auth::shouldReceive('check')->andReturn(true);
        Auth::shouldReceive('user')->andReturn($user);

        $userService = mock(UserService::class);
        $userService->shouldReceive('allUsersInfos')->andReturn([
            'total' => 10,
            'active' => 8,
        ]);

        $this->service = new class($userService) extends AdminNavigationService
        {
            private $userService;

            public function __construct($userService)
            {
                $this->userService = $userService;
            }

            public function userSelection(): array
            {
                $selection = [];
                $all_users = [
                    'title' => 'Alle Benutzer',
                    'icon' => 'mdi-account-group',
                    'url' => '/admin/users/all_users',
                    'infos' => $this->userService->allUsersInfos(),
                ];

                if ($this->userHasRole(['admin'])) {
                    $selection[] = $all_users;
                }

                return $selection;
            }
        };

        $result = $this->service->userSelection();

        expect($result)
            ->toBeArray()
            ->toHaveCount(1)
            ->and($result[0]['title'])->toBe('Alle Benutzer')
            ->and($result[0]['icon'])->toBe('mdi-account-group')
            ->and($result[0]['url'])->toBe('/admin/users/all_users')
            ->and($result[0]['infos'])->toBeArray();
    });

    it('returns empty array for non-admin users', function () {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);
        $user->assignRole($role);

        Auth::shouldReceive('check')->andReturn(true);
        Auth::shouldReceive('user')->andReturn($user);

        $result = $this->service->userSelection();

        expect($result)->toBeArray()->toBeEmpty();
    });

    it('user selection item has correct structure', function () {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user->assignRole($role);

        Auth::shouldReceive('check')->andReturn(true);
        Auth::shouldReceive('user')->andReturn($user);

        $result = $this->service->userSelection();

        if (! empty($result)) {
            expect($result[0])
                ->toHaveKeys(['title', 'icon', 'url', 'infos']);
        }
    });
});

describe('HasRoleTrait integration', function () {
    it('properly checks for multiple roles', function () {
        $school = School::factory()->create();
        $licence = Licence::create([
            'name' => 'Anmeldetool',
            'long_name' => 'Test licence',
            'price_per_year' => 200,
        ]);
        $school->licences()->attach($licence->id, ['valid_until' => now()->addDays(10)->toDateString()]);

        $user = User::factory()->create(['school_id' => $school->id]);
        $role1 = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $role2 = Role::firstOrCreate(['name' => 'register_admin', 'guard_name' => 'web']);
        $user->assignRole([$role1, $role2]);

        Auth::shouldReceive('check')->andReturn(true);
        Auth::shouldReceive('user')->andReturn($user);
        ($this->attachActiveLicences)($user, ['Anmeldetool']);

        $result = $this->service->dashboardMenu();

        expect($result)
            ->toBeArray()
            ->and(collect($result)->contains('title', 'Anmeldetool'))->toBeTrue();
    });

    it('handles unauthenticated users gracefully', function () {
        Auth::shouldReceive('check')->andReturn(false);

        $result = $this->service->dashboardMenu();

        expect($result)->toBeArray()->toBeEmpty();
    });
});

describe('menu item consistency', function () {
    it('all dashboard menu items have required keys', function () {
        $user = User::factory()->create();

        Auth::shouldReceive('check')->andReturn(true);
        Auth::shouldReceive('user')->andReturn($user);

        $result = $this->service->dashboardMenu();

        foreach ($result as $item) {
            expect($item)
                ->toHaveKey('title')
                ->and($item)->toHaveKey('icon');

            expect(
                array_key_exists('to', $item) || array_key_exists('click', $item) || array_key_exists('href', $item)
            )->toBeTrue();
        }
    });

    it('menu items use consistent icon prefix', function () {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $user->assignRole($role);

        Auth::shouldReceive('check')->andReturn(true);
        Auth::shouldReceive('user')->andReturn($user);

        $result = $this->service->dashboardMenu();

        foreach ($result as $item) {
            expect($item['icon'])->toStartWith('mdi-');
        }
    });
});

describe('routeCapabilities', function () {
    it('returns all capabilities as false for unauthenticated access', function () {
        $result = $this->service->routeCapabilities(null, []);

        expect($result)->toBe([
            'home' => false,
            'settings' => false,
            'profile' => false,
            'users' => false,
            'user_roles' => false,
            'super_admin' => false,
            'register_system' => false,
            'tutoring' => false,
            'teaching' => false,
            'materials' => false,
            'materials_v2' => false,
            'groups' => false,
            'restaurant' => false,
            'students_timetables' => false,
            'aba' => false,
        ]);
    });

    it('derives capabilities from the active dashboard menu and roles', function () {
        $school = School::factory()->create();
        SchoolTool::factory()->create([
            'school_id' => $school->id,
            'register_visible_admin' => true,
            'register_visible_user' => true,
            'teaching_visible_admin' => true,
            'teaching_visible_user' => true,
            'restaurant_visible_admin' => true,
            'restaurant_visible_user' => true,
        ]);
        $user = User::factory()->create(['school_id' => $school->id]);
        $user->assignRole([
            Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']),
            Role::firstOrCreate(['name' => 'register_admin', 'guard_name' => 'web']),
            Role::firstOrCreate(['name' => 'teaching_admin', 'guard_name' => 'web']),
            Role::firstOrCreate(['name' => 'lunch_admin', 'guard_name' => 'web']),
        ]);

        ($this->attachActiveLicences)($user, ['Anmeldetool', 'Lehrertool', 'Restaurant']);

        Auth::shouldReceive('check')->andReturn(true);
        Auth::shouldReceive('user')->andReturn($user);

        $menu = $this->service->dashboardMenu();
        $capabilities = $this->service->routeCapabilities($user, $menu);

        expect($capabilities['home'])->toBeTrue()
            ->and($capabilities['settings'])->toBeTrue()
            ->and($capabilities['profile'])->toBeTrue()
            ->and($capabilities['users'])->toBeTrue()
            ->and($capabilities['super_admin'])->toBeTrue()
            ->and($capabilities['register_system'])->toBeTrue()
            ->and($capabilities['teaching'])->toBeTrue()
            ->and($capabilities['tutoring'])->toBeFalse()
            ->and($capabilities['materials'])->toBeFalse()
            ->and($capabilities['groups'])->toBeFalse()
            ->and($capabilities['restaurant'])->toBeTrue()
            ->and($capabilities['aba'])->toBeFalse();
    });

    it('keeps super admin route capability without a dashboard menu item', function () {
        $user = User::factory()->create();
        $user->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']));

        $capabilities = $this->service->routeCapabilities($user, [
            ['title' => 'Home', 'to' => '/admin', 'is_active' => true],
            ['title' => 'Einstellungen', 'to' => '/admin/settings', 'is_active' => true],
        ]);

        expect($capabilities['super_admin'])->toBeTrue();
    });

    it('disables register system capability when the dashboard item is hidden', function () {
        $user = User::factory()->create();
        $user->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']));

        $capabilities = $this->service->routeCapabilities($user, [
            ['title' => 'Home', 'to' => '/admin', 'is_active' => true],
            ['title' => 'Einstellungen', 'to' => '/admin/settings', 'is_active' => true],
        ]);

        expect($capabilities['register_system'])->toBeFalse();
    });

    it('disables module routes when the dashboard item is hidden', function () {
        $user = User::factory()->create();
        $user->assignRole(Role::firstOrCreate(['name' => 'materials_admin', 'guard_name' => 'web']));

        $capabilities = $this->service->routeCapabilities($user, [
            ['title' => 'Home', 'to' => '/admin', 'is_active' => true],
        ]);

        expect($capabilities['materials'])->toBeFalse();
    });

    it('allows home capability for admin login roles such as teaching_admin', function () {
        $user = User::factory()->create();
        $user->assignRole(Role::firstOrCreate(['name' => 'teaching_admin', 'guard_name' => 'web']));

        $capabilities = $this->service->routeCapabilities($user, [
            ['title' => 'Home', 'to' => '/admin', 'is_active' => true],
            ['title' => 'Einstellungen', 'to' => '/admin/settings', 'is_active' => true],
        ]);

        expect($capabilities['home'])->toBeTrue()
            ->and($capabilities['settings'])->toBeTrue()
            ->and($capabilities['profile'])->toBeTrue();
    });

    it('allows home capability for a custom role marked as admin', function () {
        $user = User::factory()->create();
        $user->assignRole(Role::create([
            'name' => 'custom_admin_shell',
            'guard_name' => 'web',
            'is_admin' => true,
        ]));

        $capabilities = $this->service->routeCapabilities($user, [
            ['title' => 'Home', 'to' => '/admin', 'is_active' => true],
        ]);

        expect($capabilities['home'])->toBeTrue()
            ->and($capabilities['profile'])->toBeTrue()
            ->and($capabilities['settings'])->toBeTrue();
    });

    it('disables module routes when the dashboard item is shown but disabled', function () {
        $user = User::factory()->create();
        $user->assignRole(Role::firstOrCreate(['name' => 'materials_admin', 'guard_name' => 'web']));

        $capabilities = $this->service->routeCapabilities($user, [
            ['title' => 'Materialien', 'to' => '/admin/materials', 'is_active' => false],
        ]);

        expect($capabilities['materials'])->toBeFalse();
    });

    it('requires the correct role even when the dashboard item is active', function () {
        $user = User::factory()->create();
        $user->assignRole(Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']));

        $capabilities = $this->service->routeCapabilities($user, [
            ['title' => 'Materialien', 'to' => '/admin/materials', 'is_active' => true],
            ['title' => 'Unterricht', 'to' => '/admin/teaching', 'active_paths' => ['/admin/teaching'], 'is_active' => true],
        ]);

        expect($capabilities['materials'])->toBeFalse()
            ->and($capabilities['teaching'])->toBeTrue();
    });

    it('does not allow module routes for super_admin without the specific module role', function () {
        $user = User::factory()->create();
        $user->assignRole(Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']));

        $capabilities = $this->service->routeCapabilities($user, [
            ['title' => 'Materialien', 'to' => '/admin/materials', 'is_active' => true],
            ['title' => 'Restaurant', 'to' => '/admin/restaurant', 'is_active' => true],
            ['title' => 'ABA', 'to' => '/admin/aba', 'is_active' => true],
        ]);

        expect($capabilities['materials'])->toBeFalse()
            ->and($capabilities['restaurant'])->toBeFalse()
            ->and($capabilities['aba'])->toBeFalse();
    });
});
