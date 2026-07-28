<?php

use App\Models\Licence;
use App\Models\School;
use App\Models\SchoolLicence;
use App\Models\SchoolUserLicence;
use App\Models\Schoolyear;
use App\Models\User;
use App\Services\SchoolUserLicenceAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'materials_admin', 'guard_name' => 'web']);

    $this->school = School::factory()->create();
    $this->schoolyear = Schoolyear::factory()->create(['school_id' => $this->school->id]);
    $this->licence = Licence::create([
        'name' => 'Role Level Test',
        'long_name' => 'Role Level Test',
        'licence_schema_version' => 2,
        'admin_licence_enabled' => true,
        'admin_role_names' => ['materials_admin'],
        'user_licence_enabled' => true,
        'user_role_names' => ['teacher'],
        'admin_price_per_year' => 100,
        'user_price_per_year' => 20,
    ]);
    $this->schoolLicence = SchoolLicence::create([
        'school_id' => $this->school->id,
        'licence_id' => $this->licence->id,
        'valid_until' => now()->addYear()->toDateString(),
        'user_licence_assignments' => [],
    ]);
    $this->user = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
    ]);
    $this->user->assignRole('teacher');
});

test('assignment service is shared within the application container', function () {
    $first = app(SchoolUserLicenceAssignmentService::class);
    $second = app(SchoolUserLicenceAssignmentService::class);

    expect($second)->toBe($first);
});

test('preloaded role assignments avoid per-licence database queries', function () {
    $row = SchoolUserLicence::create([
        'school_id' => $this->school->id,
        'licence_id' => $this->licence->id,
        'user_id' => $this->user->id,
        'assignment_type' => 'user',
        'role_name' => 'teacher',
        'valid_until' => '2027-07-31',
        'is_active' => true,
    ]);

    $schoolLicence = $this->schoolLicence->fresh();
    $service = app(SchoolUserLicenceAssignmentService::class);
    $service->supportsRoleAssignments();

    DB::flushQueryLog();
    DB::enableQueryLog();

    $assignments = $service->assignmentsForSchoolLicence(
        $schoolLicence,
        ['teacher'],
        [$this->user->id],
        collect([$row]),
    );

    expect(DB::getQueryLog())->toBeEmpty()
        ->and(data_get($assignments, "{$this->user->id}.teacher.valid_until"))->toBe('2027-07-31');
});

test('role-level assignments override legacy json assignments without deleting json', function () {
    $this->schoolLicence->user_licence_assignments = [
        (string) $this->user->id => [
            'teacher' => [
                'valid_until' => '2026-01-31',
                'is_activated' => false,
                'plan_id' => 1,
            ],
        ],
    ];
    $this->schoolLicence->save();

    SchoolUserLicence::create([
        'school_id' => $this->school->id,
        'licence_id' => $this->licence->id,
        'user_id' => $this->user->id,
        'assignment_type' => 'user',
        'role_name' => 'teacher',
        'valid_until' => '2027-07-31',
        'charged_price' => 12.5,
        'is_active' => true,
        'plan_id' => 7,
        'extra_storage_units' => 3,
        'extra_storage_unit_price' => 5,
    ]);

    $assignments = app(SchoolUserLicenceAssignmentService::class)
        ->assignmentsForSchoolLicence($this->schoolLicence->fresh(), ['teacher'], [$this->user->id]);

    expect(data_get($assignments, "{$this->user->id}.teacher.valid_until"))->toBe('2027-07-31')
        ->and(data_get($assignments, "{$this->user->id}.teacher.is_activated"))->toBeTrue()
        ->and(data_get($assignments, "{$this->user->id}.teacher.plan_id"))->toBe(7)
        ->and(data_get($this->schoolLicence->fresh()->user_licence_assignments, "{$this->user->id}.teacher.valid_until"))->toBe('2026-01-31');
});

test('persist user assignments writes role-level rows and removes stale rows for known roles', function () {
    SchoolUserLicence::create([
        'school_id' => $this->school->id,
        'licence_id' => $this->licence->id,
        'user_id' => $this->user->id,
        'assignment_type' => 'user',
        'role_name' => 'student',
        'valid_until' => '2026-01-31',
        'is_active' => true,
    ]);

    $result = app(SchoolUserLicenceAssignmentService::class)->persistUserAssignments(
        $this->schoolLicence->fresh('licence'),
        $this->user->fresh('roles'),
        [
            'teacher' => [
                'valid_until' => '2027-07-31',
                'is_activated' => true,
                'plan_id' => 2,
                'charged_price' => 13.5,
                'extra_storage_units' => 4,
                'extra_storage_unit_price' => 6,
            ],
        ],
        [
            'affected_roles' => ['teacher', 'student'],
            'user_licence_required_by_role' => [
                'teacher' => true,
                'student' => true,
            ],
        ],
        $this->licence,
    );

    expect($result['deleted'])->toBe(1)
        ->and(SchoolUserLicence::query()->where('role_name', 'student')->exists())->toBeFalse();

    $stored = SchoolUserLicence::query()
        ->where('role_name', 'teacher')
        ->firstOrFail();

    expect($stored->assignment_type)->toBe('user')
        ->and($stored->valid_until?->format('Y-m-d'))->toBe('2027-07-31')
        ->and((int) $stored->plan_id)->toBe(2)
        ->and((float) $stored->charged_price)->toBe(13.5)
        ->and((int) $stored->extra_storage_units)->toBe(4)
        ->and((float) $stored->extra_storage_unit_price)->toBe(6.0);
});

test('backfill command can run as a dry run without writing records', function () {
    $this->schoolLicence->user_licence_assignments = [
        (string) $this->user->id => [
            'teacher' => [
                'valid_until' => '2027-07-31',
                'is_activated' => true,
                'plan_id' => 2,
            ],
        ],
    ];
    $this->schoolLicence->save();

    $this->artisan('schooltool:backfill-school-user-licences --dry-run')
        ->assertSuccessful();

    expect(SchoolUserLicence::query()->where('role_name', 'teacher')->exists())->toBeFalse();
});
