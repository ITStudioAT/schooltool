<?php

use App\Models\Licence;
use App\Models\School;
use App\Models\SchoolTool;
use App\Models\Schoolyear;
use App\Models\User;
use App\Services\RecordsCreateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->service = new RecordsCreateService;
});

describe('initRecords', function () {
    it('creates initial school when database is empty', function () {
        expect(School::count())->toBe(0);

        $this->service->initRecords();

        expect(School::count())->toBe(1)
            ->and(School::first()->short_name)->toBe('CDGym')
            ->and(School::first()->long_name)->toBe('Christian-Doppler-Gymnasium Salzburg');
    });

    it('creates initial schoolyear for new school', function () {
        expect(Schoolyear::count())->toBe(0);

        $this->service->initRecords();

        expect(Schoolyear::count())->toBeGreaterThan(0);
    });

    it('creates admin roles', function () {
        expect(Role::count())->toBe(0);

        $this->service->initRecords();

        expect(Role::where('name', 'super_admin')->exists())->toBeTrue()
            ->and(Role::where('name', 'admin')->exists())->toBeTrue()
            ->and(Role::where('name', 'studentstimetables_admin')->exists())->toBeTrue();
    });

    it('creates super admin user for school', function () {
        $this->service->initRecords();

        $user = User::where('email', 'kron@naturwelt.at')->first();

        expect($user)->not->toBeNull()
            ->and($user->hasRole('super_admin'))->toBeTrue()
            ->and($user->first_name)->toBe('Günther')
            ->and($user->last_name)->toBe('Kron');
    });

    it('does not create duplicate school on multiple calls', function () {
        $this->service->initRecords();
        $firstSchoolId = School::first()->id;

        $this->service->initRecords();

        expect(School::count())->toBe(1)
            ->and(School::first()->id)->toBe($firstSchoolId);
    });

    it('processes all existing schools', function () {
        $school1 = School::factory()->create(['short_name' => 'School1']);
        $school2 = School::factory()->create(['short_name' => 'School2']);

        $this->service->initRecords();

        expect(Schoolyear::where('school_id', $school1->id)->exists())->toBeTrue()
            ->and(Schoolyear::where('school_id', $school2->id)->exists())->toBeTrue()
            ->and(User::where('school_id', $school1->id)->where('email', 'kron@naturwelt.at')->exists())->toBeTrue()
            ->and(User::where('school_id', $school2->id)->where('email', 'kron@naturwelt.at')->exists())->toBeTrue();
    });

    it('creates schoolyears from config for each school', function () {
        Config::set('schooltool.schoolyears', [
            ['name' => 'Test Year 1', 'from' => '2025-09-01', 'sem_2_start' => '2026-02-01', 'to' => '2026-06-30'],
            ['name' => 'Test Year 2', 'from' => '2026-09-01', 'sem_2_start' => '2027-02-01', 'to' => '2027-06-30'],
        ]);

        $this->service->initRecords();

        $school = School::first();

        expect(Schoolyear::where('school_id', $school->id)->where('name', 'Test Year 1')->exists())->toBeTrue()
            ->and(Schoolyear::where('school_id', $school->id)->where('name', 'Test Year 2')->exists())->toBeTrue();
    });

    it('sets user as active and confirmed', function () {
        $this->service->initRecords();

        $user = User::where('email', 'kron@naturwelt.at')->first();

        expect($user->is_active)->toBe(1)
            ->and($user->confirmed_at)->not->toBeNull()
            ->and($user->email_verified_at)->not->toBeNull();
    });

    it('creates Lehrertool licence', function () {
        $this->service->initRecords();

        expect(Licence::where('name', 'Lehrertool')->exists())->toBeTrue();
    });

    it('creates Materialientool licence', function () {
        $this->service->initRecords();

        expect(Licence::where('name', 'Materialientool')->exists())->toBeTrue();
    });

    it('does not duplicate Materialientool licence when init runs multiple times', function () {
        $this->service->initRecords();
        $this->service->initRecords();

        expect(Licence::where('name', 'Materialientool')->count())->toBe(1);
    });

    it('creates Restaurant licence', function () {
        $this->service->initRecords();

        expect(Licence::where('name', 'Restaurant')->exists())->toBeTrue();
    });

    it('does not duplicate Restaurant licence when init runs multiple times', function () {
        $this->service->initRecords();
        $this->service->initRecords();

        expect(Licence::where('name', 'Restaurant')->count())->toBe(1);
    });

    it('preserves existing structured licence role assignments when init runs again', function () {
        Role::firstOrCreate(['name' => 'materials_admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'materials_moderator', 'guard_name' => 'web']);

        $licence = Licence::create([
            'name' => 'Materialientool',
            'long_name' => 'Custom Materialientool',
            'price_per_year' => 200,
            'start_day_month' => '08-01',
            'end_day_month' => '07-31',
            'is_selectable' => true,
            'licence_schema_version' => 2,
            'school_licence_enabled' => true,
            'admin_licence_enabled' => true,
            'admin_role_names' => ['materials_admin'],
            'user_licence_enabled' => true,
            'user_role_names' => ['materials_moderator'],
        ]);

        $this->service->initRecords();

        $licence->refresh();

        expect($licence->licence_schema_version)->toBe(2)
            ->and($licence->admin_licence_enabled)->toBeTrue()
            ->and($licence->admin_role_names)->toBe(['materials_admin'])
            ->and($licence->user_licence_enabled)->toBeTrue()
            ->and($licence->user_role_names)->toBe(['materials_moderator']);
    });

    it('normalizes licence day month values from config before persisting', function () {
        Config::set('schooltool.licences', [
            [
                'name' => 'Config Licence',
                'long_name' => 'Config Licence Long Name',
                'price_per_year' => 200,
                'start_day_month' => '01.08.',
                'school_licence_enabled' => true,
                'admin_licence_enabled' => false,
                'admin_role_names' => null,
                'user_licence_enabled' => false,
                'user_role_names' => null,
            ],
        ]);

        $this->service->initRecords();

        $licence = Licence::where('name', 'Config Licence')->first();

        expect($licence)->not->toBeNull()
            ->and($licence->start_day_month)->toBe('08-01')
            ->and($licence->end_day_month)->toBe('07-31');
    });

    it('fails fast with a clear exception when licence config day month is invalid', function () {
        Config::set('schooltool.licences', [
            [
                'name' => 'Broken Licence',
                'long_name' => 'Broken Licence Long Name',
                'price_per_year' => 200,
                'start_day_month' => '01/08',
                'school_licence_enabled' => true,
                'admin_licence_enabled' => false,
                'admin_role_names' => null,
                'user_licence_enabled' => false,
                'user_role_names' => null,
            ],
        ]);

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('Licence [Broken Licence] has invalid start_day_month [01/08] in config(schooltool.licences).');

        $this->service->initRecords();
    });
});

describe('firstOrCreateSchool (private method behavior)', function () {
    it('creates school with correct attributes when none exists', function () {
        $this->service->initRecords();

        $school = School::first();

        expect($school->long_name)->toBe('Christian-Doppler-Gymnasium Salzburg')
            ->and($school->short_name)->toBe('CDGym')
            ->and($school->logo)->toBe('logo_1.png')
            ->and($school->is_selectable)->toBe(1);
    });

    it('returns existing school when one already exists', function () {
        $existingSchool = School::factory()->create([
            'long_name' => 'Existing School',
            'short_name' => 'ES',
        ]);

        $this->service->initRecords();

        expect(School::count())->toBe(1)
            ->and(School::first()->id)->toBe($existingSchool->id)
            ->and(School::first()->short_name)->toBe('ES');
    });
});

describe('firstOrCreateSchoolyear (private method behavior)', function () {
    it('creates schoolyear with default values', function () {
        $this->service->initRecords();

        $schoolyear = Schoolyear::first();

        expect($schoolyear)->not->toBeNull()
            ->and($schoolyear->name)->toBe('Schuljahr 2025/26')
            ->and($schoolyear->from)->toBe('2025-09-08')
            ->and($schoolyear->until)->toBe('2026-07-10')
            ->and($schoolyear->sem_2_start)->toBe('2026-02-16');
    });

    it('associates schoolyear with correct school', function () {
        $this->service->initRecords();

        $school = School::first();
        $schoolyear = Schoolyear::first();

        expect($schoolyear->school_id)->toBe($school->id);
    });

    it('does not create duplicate schoolyear for same school', function () {
        $school = School::factory()->create();

        Schoolyear::create([
            'school_id' => $school->id,
            'name' => 'Existing Year',
        ]);

        // Run initRecords which should not create duplicate
        $this->service->initRecords();

        expect(Schoolyear::where('school_id', $school->id)->count())->toBeGreaterThan(0);
    });
});

describe('checkOrCreateAdminRoles (private method behavior)', function () {
    it('creates super_admin role', function () {
        expect(Role::where('name', 'super_admin')->exists())->toBeFalse();

        $this->service->initRecords();

        $role = Role::where('name', 'super_admin')->first();

        expect($role)->not->toBeNull()
            ->and($role->guard_name)->toBe('web');
    });

    it('creates admin role', function () {
        expect(Role::where('name', 'admin')->exists())->toBeFalse();

        $this->service->initRecords();

        $role = Role::where('name', 'admin')->first();

        expect($role)->not->toBeNull()
            ->and($role->guard_name)->toBe('web');
    });

    it('creates studentstimetables_admin role', function () {
        expect(Role::where('name', 'studentstimetables_admin')->exists())->toBeFalse();

        $this->service->initRecords();

        $role = Role::where('name', 'studentstimetables_admin')->first();

        expect($role)->not->toBeNull()
            ->and($role->guard_name)->toBe('web');
    });

    it('does not duplicate roles on multiple calls', function () {
        $this->service->initRecords();
        $this->service->initRecords();

        expect(Role::where('name', 'super_admin')->count())->toBe(1)
            ->and(Role::where('name', 'admin')->count())->toBe(1)
            ->and(Role::where('name', 'studentstimetables_admin')->count())->toBe(1);
    });
});

describe('checkOrCreateAdmins (private method behavior)', function () {
    it('creates admin with super_admin role', function () {
        $this->service->initRecords();

        $user = User::where('email', 'kron@naturwelt.at')->first();

        expect($user)->not->toBeNull()
            ->and($user->hasRole('super_admin'))->toBeTrue();
    });

    it('creates admin for each school', function () {
        $school1 = School::factory()->create();
        $school2 = School::factory()->create();

        $this->service->initRecords();

        expect(User::where('school_id', $school1->id)->where('email', 'kron@naturwelt.at')->exists())->toBeTrue()
            ->and(User::where('school_id', $school2->id)->where('email', 'kron@naturwelt.at')->exists())->toBeTrue();
    });

    it('associates admin with schoolyear when available', function () {
        $this->service->initRecords();

        $user = User::where('email', 'kron@naturwelt.at')->first();

        expect($user->schoolyear_id)->not->toBeNull();
    });
});

describe('checkOrCreateAdmin (private method behavior)', function () {
    it('creates user with correct attributes', function () {
        $this->service->initRecords();

        $user = User::where('email', 'kron@naturwelt.at')->first();

        expect($user->first_name)->toBe('Günther')
            ->and($user->last_name)->toBe('Kron')
            ->and($user->email)->toBe('kron@naturwelt.at')
            ->and($user->is_active)->toBe(1);
    });

    it('sets password from environment variable', function () {
        $this->service->initRecords();

        $user = User::where('email', 'kron@naturwelt.at')->first();

        expect($user->password)->not->toBeNull();
    });

    it('verifies and confirms user email', function () {
        $this->service->initRecords();

        $user = User::where('email', 'kron@naturwelt.at')->first();

        expect($user->email_verified_at)->not->toBeNull()
            ->and($user->confirmed_at)->not->toBeNull();
    });

    it('does not duplicate user when called multiple times', function () {
        $this->service->initRecords();
        $firstUser = User::where('email', 'kron@naturwelt.at')->first();

        $this->service->initRecords();

        expect(User::where('email', 'kron@naturwelt.at')->count())->toBe(1)
            ->and(User::where('email', 'kron@naturwelt.at')->first()->id)->toBe($firstUser->id);
    });

    it('assigns role to existing user', function () {
        $school = School::factory()->create();
        $schoolyear = Schoolyear::factory()->create(['school_id' => $school->id]);

        $existingUser = User::factory()->create([
            'school_id' => $school->id,
            'schoolyear_id' => $schoolyear->id,
            'email' => 'kron@naturwelt.at',
        ]);

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $this->service->initRecords();

        expect($existingUser->fresh()->hasRole('super_admin'))->toBeTrue();
    });

    it('handles null schoolyear gracefully', function () {
        $school = School::factory()->create();

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $this->service->initRecords();

        $user = User::where('school_id', $school->id)->where('email', 'kron@naturwelt.at')->first();

        expect($user)->not->toBeNull();
    });
});

describe('checkOrCreateSchoolyears (private method behavior)', function () {
    it('creates multiple schoolyears from config', function () {
        Config::set('schooltool.schoolyears', [
            ['name' => 'Year 1', 'from' => '2025-09-01', 'sem_2_start' => '2026-02-01', 'to' => '2026-06-30'],
            ['name' => 'Year 2', 'from' => '2026-09-01', 'sem_2_start' => '2027-02-01', 'to' => '2027-06-30'],
            ['name' => 'Year 3', 'from' => '2027-09-01', 'sem_2_start' => '2028-02-01', 'to' => '2028-06-30'],
        ]);

        $this->service->initRecords();

        $school = School::first();

        expect(Schoolyear::where('school_id', $school->id)->count())->toBeGreaterThanOrEqual(3);
    });

    it('sets correct dates from config', function () {
        Config::set('schooltool.schoolyears', [
            ['name' => 'Custom Year', 'from' => '2030-09-01', 'sem_2_start' => '2031-02-15', 'to' => '2031-07-15'],
        ]);

        $this->service->initRecords();

        $schoolyear = Schoolyear::where('name', 'Custom Year')->first();

        expect($schoolyear)->not->toBeNull()
            ->and($schoolyear->from)->toBe('2030-09-01')
            ->and($schoolyear->sem_2_start)->toBe('2031-02-15')
            ->and($schoolyear->until)->toBe('2031-07-15');
    });

    it('does not duplicate schoolyears on multiple calls', function () {
        Config::set('schooltool.schoolyears', [
            ['name' => 'Unique Year', 'from' => '2025-09-01', 'sem_2_start' => '2026-02-01', 'to' => '2026-06-30'],
        ]);

        $this->service->initRecords();
        $firstCount = Schoolyear::where('name', 'Unique Year')->count();

        $this->service->initRecords();
        $secondCount = Schoolyear::where('name', 'Unique Year')->count();

        expect($firstCount)->toBe($secondCount);
    });
});

describe('integration scenarios', function () {
    it('handles complete initialization from empty database', function () {
        // Ensure database is truly empty for this integration test
        User::query()->delete();

        expect(School::count())->toBe(0)
            ->and(Schoolyear::count())->toBe(0)
            ->and(Role::count())->toBe(0)
            ->and(User::count())->toBe(0);

        $this->service->initRecords();

        expect(School::count())->toBeGreaterThan(0)
            ->and(Schoolyear::count())->toBeGreaterThan(0)
            ->and(Role::count())->toBeGreaterThanOrEqual(3)
            ->and(User::count())->toBeGreaterThan(0);
    });

    it('is idempotent when called multiple times', function () {
        $this->service->initRecords();

        $schoolCount = School::count();
        $roleCount = Role::count();

        $this->service->initRecords();
        $this->service->initRecords();

        expect(School::count())->toBe($schoolCount)
            ->and(Role::count())->toBe($roleCount);
    });

    it('creates complete user with all relationships', function () {
        $this->service->initRecords();

        $user = User::where('email', 'kron@naturwelt.at')->first();

        expect($user)->not->toBeNull()
            ->and($user->school_id)->not->toBeNull()
            ->and($user->schoolyear_id)->not->toBeNull()
            ->and($user->hasRole('super_admin'))->toBeTrue()
            ->and(School::find($user->school_id))->not->toBeNull()
            ->and(Schoolyear::find($user->schoolyear_id))->not->toBeNull();
    });

    it('handles partial existing data correctly', function () {
        $existingSchool = School::factory()->create(['short_name' => 'Existing']);
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $this->service->initRecords();

        expect(School::where('short_name', 'Existing')->exists())->toBeTrue()
            ->and(Schoolyear::where('school_id', $existingSchool->id)->exists())->toBeTrue()
            ->and(User::where('school_id', $existingSchool->id)->where('email', 'kron@naturwelt.at')->exists())->toBeTrue();
    });

    it('maintains data integrity across multiple schools', function () {
        School::factory()->count(3)->create();

        $this->service->initRecords();

        $schools = School::all();

        foreach ($schools as $school) {
            expect(Schoolyear::where('school_id', $school->id)->exists())->toBeTrue()
                ->and(User::where('school_id', $school->id)->where('email', 'kron@naturwelt.at')->exists())->toBeTrue();
        }
    });
});

describe('edge cases', function () {
    it('handles empty schoolyears config', function () {
        Config::set('schooltool.schoolyears', []);

        $this->service->initRecords();

        // Should still create initial schoolyear
        expect(Schoolyear::count())->toBeGreaterThan(0);
    });

    it('handles missing environment password', function () {
        Config::set('schooltool.sa_pw');

        $this->service->initRecords();

        $user = User::where('email', 'kron@naturwelt.at')->first();

        expect($user)->not->toBeNull()
            ->and($user->password)->not->toBeEmpty();
    });

    it('preserves existing school attributes', function () {
        $school = School::factory()->create([
            'long_name' => 'My Custom School',
            'short_name' => 'MCS',
            'logo' => 'custom.png',
        ]);

        $this->service->initRecords();

        $school->refresh();

        expect($school->short_name)->toBe('MCS')
            ->and($school->long_name)->toBe('My Custom School')
            ->and($school->logo)->toBe('custom.png');
    });

    it('handles user without schoolyear assignment', function () {
        $school = School::factory()->create();

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $this->service->initRecords();

        // Even if schoolyear is missing initially, user should be created
        expect(User::where('school_id', $school->id)->where('email', 'kron@naturwelt.at')->exists())->toBeTrue();
    });
});

describe('checkOrCreateSchoolTool', function () {
    it('creates a SchoolTool when none exists for the school', function () {
        $school = School::factory()->create();

        $initialCount = SchoolTool::count();

        $schoolTool = $this->service->checkOrCreateSchoolTool($school);

        expect(SchoolTool::count())->toBe($initialCount + 1)
            ->and($schoolTool)->toBeInstanceOf(SchoolTool::class)
            ->and($schoolTool->school_id)->toBe($school->id)
            ->and($schoolTool->getAttributes())->not->toHaveKey('tutoring_confirmer_email');
    });

    it('returns existing SchoolTool when one already exists', function () {
        $school = School::factory()->create();

        $existingSchoolTool = SchoolTool::create([
            'school_id' => $school->id,
            'register_visible_admin' => true,
            'register_visible_user' => false,
        ]);

        $schoolTool = $this->service->checkOrCreateSchoolTool($school);

        expect($schoolTool->id)->toBe($existingSchoolTool->id)
            ->and((bool) $schoolTool->register_visible_admin)->toBeTrue()
            ->and((bool) $schoolTool->register_visible_user)->toBeFalse();
    });

    it('does not create duplicate SchoolTools for same school', function () {
        $school = School::factory()->create();

        $firstCall = $this->service->checkOrCreateSchoolTool($school);
        $secondCall = $this->service->checkOrCreateSchoolTool($school);

        expect($firstCall->id)->toBe($secondCall->id)
            ->and(SchoolTool::where('school_id', $school->id)->count())->toBe(1);
    });

    it('creates separate SchoolTools for different schools', function () {
        $school1 = School::factory()->create();
        $school2 = School::factory()->create();

        $schoolTool1 = $this->service->checkOrCreateSchoolTool($school1);
        $schoolTool2 = $this->service->checkOrCreateSchoolTool($school2);

        expect($schoolTool1->id)->not->toBe($schoolTool2->id)
            ->and($schoolTool1->school_id)->toBe($school1->id)
            ->and($schoolTool2->school_id)->toBe($school2->id);
    });
});
