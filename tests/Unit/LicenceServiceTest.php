<?php

use App\Models\Licence;
use App\Models\School;
use App\Models\SchoolLicence;
use App\Models\SchoolUserLicence;
use App\Models\User;
use App\Services\LicenceService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->service = new LicenceService;
});

describe('selectableSchoolLicences', function () {
    it('returns all licences for a given school', function () {
        $school = School::factory()->create();
        $licence1 = Licence::create(['name' => 'app1', 'long_name' => 'Application 1']);
        $licence2 = Licence::create(['name' => 'app2', 'long_name' => 'Application 2']);

        SchoolLicence::create([
            'school_id' => $school->id,
            'licence_id' => $licence1->id,
            'valid_until' => now()->addYear(),
        ]);

        SchoolLicence::create([
            'school_id' => $school->id,
            'licence_id' => $licence2->id,
            'valid_until' => now()->addMonths(6),
        ]);

        $result = $this->service->selectableSchoolLicences($school);

        expect($result)->toHaveCount(2);
    });

    it('returns empty collection when school has no licences', function () {
        $school = School::factory()->create();

        $result = $this->service->selectableSchoolLicences($school);

        expect($result)->toBeEmpty();
    });

    it('only returns licences for the specified school', function () {
        $school1 = School::factory()->create();
        $school2 = School::factory()->create();
        $licence = Licence::create(['name' => 'app1', 'long_name' => 'Application 1']);

        SchoolLicence::create([
            'school_id' => $school1->id,
            'licence_id' => $licence->id,
            'valid_until' => now()->addYear(),
        ]);

        SchoolLicence::create([
            'school_id' => $school2->id,
            'licence_id' => $licence->id,
            'valid_until' => now()->addYear(),
        ]);

        $result = $this->service->selectableSchoolLicences($school1);

        expect($result)->toHaveCount(1)
            ->and($result->first()->school_id)->toBe($school1->id);
    });
});

describe('isLicenceValid', function () {
    it('returns true when licence is valid and has no expiration date', function () {
        $school = School::factory()->create();
        $licence = Licence::create(['name' => 'app1', 'long_name' => 'Application 1']);

        SchoolLicence::create([
            'school_id' => $school->id,
            'licence_id' => $licence->id,
            'valid_until' => null,
        ]);

        $result = $this->service->isLicenceValid($school, 'app1');

        expect($result)->toBeTrue();
    });

    it('returns true when licence is valid and expires in the future', function () {
        $school = School::factory()->create();
        $licence = Licence::create(['name' => 'app1', 'long_name' => 'Application 1']);

        SchoolLicence::create([
            'school_id' => $school->id,
            'licence_id' => $licence->id,
            'valid_until' => now()->addYear(),
        ]);

        $result = $this->service->isLicenceValid($school, 'app1');

        expect($result)->toBeTrue();
    });

    it('returns false when school does not exist', function () {
        $result = $this->service->isLicenceValid(null, 'app1');

        expect($result)->toBeFalse();
    });

    it('returns false when licence does not exist', function () {
        $school = School::factory()->create();

        $result = $this->service->isLicenceValid($school, 'nonexistent_app');

        expect($result)->toBeFalse();
    });

    it('returns false when school does not have the licence', function () {
        $school = School::factory()->create();
        $licence = Licence::create(['name' => 'app1', 'long_name' => 'Application 1']);

        $result = $this->service->isLicenceValid($school, 'app1');

        expect($result)->toBeFalse();
    });

    it('returns false when licence has expired', function () {
        $school = School::factory()->create();
        $licence = Licence::create(['name' => 'app1', 'long_name' => 'Application 1']);

        // Create a licence that expired 2 days ago to avoid any edge cases
        SchoolLicence::create([
            'school_id' => $school->id,
            'licence_id' => $licence->id,
            'valid_until' => Carbon::now()->subDays(2),
        ]);

        $result = $this->service->isLicenceValid($school, 'app1');

        expect($result)->toBeFalse();
    });

    it('returns true when licence expires today but in the future', function () {
        $school = School::factory()->create();
        $licence = Licence::create(['name' => 'app1', 'long_name' => 'Application 1']);

        // Create a licence that expires at end of today (still in future)
        SchoolLicence::create([
            'school_id' => $school->id,
            'licence_id' => $licence->id,
            'valid_until' => Carbon::today()->endOfDay(),
        ]);

        $result = $this->service->isLicenceValid($school, 'app1');

        expect($result)->toBeTrue();
    });

    it('returns true when licence expires tomorrow', function () {
        $school = School::factory()->create();
        $licence = Licence::create(['name' => 'app1', 'long_name' => 'Application 1']);

        SchoolLicence::create([
            'school_id' => $school->id,
            'licence_id' => $licence->id,
            'valid_until' => now()->addDay(),
        ]);

        $result = $this->service->isLicenceValid($school, 'app1');

        expect($result)->toBeTrue();
    });
});

describe('licenceStatus with school_licence_required', function () {
    it('returns active when school licence is expired but not required in school model', function () {
        $school = School::factory()->create();
        $licence = Licence::create(['name' => 'app1', 'long_name' => 'Application 1']);

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

        $result = $this->service->licenceStatus($school, 'app1');

        expect($result)->toBe('active');
    });

    it('returns active when school licence model is stored as json string and school licence is not required', function () {
        $school = School::factory()->create();
        $licence = Licence::create(['name' => 'app1', 'long_name' => 'Application 1']);

        SchoolLicence::create([
            'school_id' => $school->id,
            'licence_id' => $licence->id,
            'valid_until' => now()->subDay(),
            'licence_model' => json_encode([
                'school_licence_required' => false,
                'affected_roles' => [],
                'user_licence_required_by_role' => [],
            ]),
        ]);

        $result = $this->service->licenceStatus($school, 'app1');

        expect($result)->toBe('active');
    });

    it('uses template model when school licence model is null', function () {
        $school = School::factory()->create();
        $licence = Licence::create([
            'name' => 'app1',
            'long_name' => 'Application 1',
            'licence_model' => [
                'school_licence_required' => false,
                'affected_roles' => [],
                'user_licence_required_by_role' => [],
            ],
        ]);

        SchoolLicence::create([
            'school_id' => $school->id,
            'licence_id' => $licence->id,
            'valid_until' => now()->subDay(),
            'licence_model' => null,
        ]);

        $result = $this->service->licenceStatus($school, 'app1');

        expect($result)->toBe('active');
    });
});

describe('toolAccessStatusForUser with per-user licences', function () {
    it('returns active when user only has roles without per-user licence requirement', function () {
        $school = School::factory()->create();
        $user = User::factory()->create();

        Role::findOrCreate('student', 'web');
        $user->assignRole('student');

        $licence = Licence::create([
            'name' => 'Lehrertool',
            'long_name' => 'Lehrertool',
            'licence_model' => [
                'school_licence_required' => true,
                'affected_roles' => ['teacher', 'teaching_admin', 'student'],
                'user_licence_required_by_role' => [
                    'teacher' => true,
                    'teaching_admin' => true,
                    'student' => false,
                ],
                'user_licence_plans_by_role' => [],
            ],
        ]);

        SchoolLicence::create([
            'school_id' => $school->id,
            'licence_id' => $licence->id,
            'valid_until' => now()->addYear(),
            'licence_model' => [
                'school_licence_required' => true,
                'affected_roles' => ['teacher', 'teaching_admin', 'student'],
                'user_licence_required_by_role' => [
                    'teacher' => true,
                    'teaching_admin' => true,
                    'student' => false,
                ],
                'user_licence_plans_by_role' => [],
            ],
        ]);

        $result = $this->service->toolAccessStatusForUser($user, $school, 'Lehrertool');

        expect($result)->toBe('active');
    });

    it('returns active when school model disables template user licence requirement for the role', function () {
        $school = School::factory()->create();
        $user = User::factory()->create(['school_id' => $school->id]);

        Role::findOrCreate('teacher', 'web');
        $user->assignRole('teacher');

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
            'valid_until' => now()->addYear(),
            'licence_model' => [
                'school_licence_required' => true,
                'affected_roles' => ['teacher'],
                'user_licence_required_by_role' => [
                    'teacher' => false,
                ],
            ],
            'user_licence_assignments' => [],
        ]);

        $result = $this->service->toolAccessStatusForUser($user, $school, 'Lehrertool', ['teacher']);

        expect($result)->toBe('active');
    });

    it('returns active when a valid legacy user assignment exists even if it is not activated', function () {
        $school = School::factory()->create();
        $user = User::factory()->create(['school_id' => $school->id]);

        Role::findOrCreate('teacher', 'web');
        $user->assignRole('teacher');

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
            'valid_until' => now()->addYear(),
            'licence_model' => [
                'school_licence_required' => true,
                'affected_roles' => ['teacher'],
                'user_licence_required_by_role' => [
                    'teacher' => true,
                ],
            ],
            'user_licence_assignments' => [
                (string) $user->id => [
                    'teacher' => [
                        'valid_until' => now()->addMonth()->toDateString(),
                        'is_activated' => false,
                    ],
                ],
            ],
        ]);

        $result = $this->service->toolAccessStatusForUser($user, $school, 'Lehrertool', ['teacher']);

        expect($result)->toBe('active');
    });
});

describe('structured licence model', function () {
    it('returns active when structured school layer is disabled and the user has an active user assignment', function () {
        $school = School::factory()->create();
        $user = User::factory()->create(['school_id' => $school->id]);

        Role::findOrCreate('teacher', 'web');
        $user->assignRole('teacher');

        $licence = Licence::create([
            'name' => 'Lehrertool',
            'long_name' => 'Lehrertool',
            'licence_schema_version' => 2,
            'school_licence_enabled' => false,
            'school_price_per_year' => '199',
            'school_included_storage_gb' => 10,
            'school_extra_storage_step_gb' => 100,
            'school_extra_storage_step_price' => '5',
            'admin_licence_enabled' => false,
            'user_licence_enabled' => true,
            'user_price_per_year' => '29',
            'user_included_storage_gb' => 10,
            'user_extra_storage_step_gb' => 100,
            'user_extra_storage_step_price' => '5',
            'user_role_names' => ['teacher'],
        ]);

        SchoolUserLicence::create([
            'school_id' => $school->id,
            'licence_id' => $licence->id,
            'user_id' => $user->id,
            'assignment_type' => 'user',
            'valid_from' => now()->subMonth()->toDateString(),
            'valid_until' => now()->addMonth()->toDateString(),
            'base_price_per_year' => '29',
            'charged_price' => '14.50',
            'is_active' => true,
        ]);

        $result = $this->service->toolAccessStatusForUser($user, $school, 'Lehrertool', ['teacher']);

        expect($result)->toBe('active');
    });

    it('normalizes structured storage tariff fields for editable configuration', function () {
        $licence = Licence::create([
            'name' => 'Materialientool',
            'long_name' => 'Materialientool',
            'licence_schema_version' => 2,
            'school_licence_enabled' => true,
            'school_price_per_year' => '199',
            'school_included_storage_gb' => 10,
            'school_extra_storage_step_gb' => 100,
            'school_extra_storage_step_price' => '5.00',
            'admin_licence_enabled' => true,
            'admin_price_per_year' => '5',
            'admin_role_names' => ['materials_admin'],
            'admin_included_storage_gb' => 10,
            'admin_extra_storage_step_gb' => 100,
            'admin_extra_storage_step_price' => '5.00',
            'user_licence_enabled' => true,
            'user_price_per_year' => '5',
            'user_role_names' => ['materials_moderator'],
            'user_included_storage_gb' => 10,
            'user_extra_storage_step_gb' => 100,
            'user_extra_storage_step_price' => '5.00',
        ]);

        $configuration = $this->service->editableLicenceConfiguration($licence);

        expect($configuration['school_included_storage_gb'])->toBe('10')
            ->and($configuration['school_extra_storage_step_gb'])->toBe('100')
            ->and($configuration['school_extra_storage_step_price'])->toBe('5')
            ->and($configuration['admin_included_storage_gb'])->toBe('10')
            ->and($configuration['admin_extra_storage_step_gb'])->toBe('100')
            ->and($configuration['admin_extra_storage_step_price'])->toBe('5')
            ->and($configuration['user_included_storage_gb'])->toBe('10')
            ->and($configuration['user_extra_storage_step_gb'])->toBe('100')
            ->and($configuration['user_extra_storage_step_price'])->toBe('5');
    });

    it('returns missing when structured user licence is required for the role but no assignment exists', function () {
        $school = School::factory()->create();
        $user = User::factory()->create(['school_id' => $school->id]);

        Role::findOrCreate('teacher', 'web');
        $user->assignRole('teacher');

        Licence::create([
            'name' => 'Lehrertool',
            'long_name' => 'Lehrertool',
            'licence_schema_version' => 2,
            'school_licence_enabled' => false,
            'admin_licence_enabled' => false,
            'user_licence_enabled' => true,
            'user_role_names' => ['teacher'],
        ]);

        $result = $this->service->toolAccessStatusForUser($user, $school, 'Lehrertool', ['teacher']);

        expect($result)->toBe('missing');
    });

    it('returns expired when the matching structured user assignment has passed its valid until date', function () {
        $school = School::factory()->create();
        $user = User::factory()->create(['school_id' => $school->id]);

        Role::findOrCreate('teacher', 'web');
        $user->assignRole('teacher');

        $licence = Licence::create([
            'name' => 'Lehrertool',
            'long_name' => 'Lehrertool',
            'licence_schema_version' => 2,
            'school_licence_enabled' => false,
            'admin_licence_enabled' => false,
            'user_licence_enabled' => true,
            'user_role_names' => ['teacher'],
        ]);

        SchoolUserLicence::create([
            'school_id' => $school->id,
            'licence_id' => $licence->id,
            'user_id' => $user->id,
            'assignment_type' => 'user',
            'valid_from' => now()->subYear()->toDateString(),
            'valid_until' => now()->subDay()->toDateString(),
            'base_price_per_year' => '29',
            'charged_price' => '29.00',
            'is_active' => true,
        ]);

        $result = $this->service->toolAccessStatusForUser($user, $school, 'Lehrertool', ['teacher']);

        expect($result)->toBe('expired');
    });

    it('returns active when a matching admin assignment exists for an allowed admin role even if it is marked inactive', function () {
        $school = School::factory()->create();
        $user = User::factory()->create(['school_id' => $school->id]);

        Role::findOrCreate('teaching_admin', 'web');
        $user->assignRole('teaching_admin');

        $licence = Licence::create([
            'name' => 'Lehrertool',
            'long_name' => 'Lehrertool',
            'licence_schema_version' => 2,
            'school_licence_enabled' => false,
            'admin_licence_enabled' => true,
            'admin_role_names' => ['teaching_admin'],
            'user_licence_enabled' => false,
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

        $result = $this->service->toolAccessStatusForUser($user, $school, 'Lehrertool', ['teaching_admin']);

        expect($result)->toBe('active');
    });

    it('returns active when a matching legacy admin assignment exists in school licence assignments even if it is not activated', function () {
        $school = School::factory()->create();
        $user = User::factory()->create(['school_id' => $school->id]);

        Role::findOrCreate('teaching_admin', 'web');
        $user->assignRole('teaching_admin');

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
            'user_licence_assignments' => [
                (string) $user->id => [
                    'teaching_admin' => [
                        'valid_until' => now()->addMonth()->toDateString(),
                        'is_activated' => false,
                        'charged_price' => 5,
                    ],
                ],
            ],
        ]);

        $result = $this->service->toolAccessStatusForUser($user, $school, 'Lehrertool', ['teaching_admin']);

        expect($result)->toBe('active');
    });

    it('returns active when wildcard user roles are configured and the user has an active assignment', function () {
        $school = School::factory()->create();
        $user = User::factory()->create(['school_id' => $school->id]);

        Role::findOrCreate('student', 'web');
        $user->assignRole('student');

        $licence = Licence::create([
            'name' => 'Lehrertool',
            'long_name' => 'Lehrertool',
            'licence_schema_version' => 2,
            'school_licence_enabled' => false,
            'admin_licence_enabled' => false,
            'user_licence_enabled' => true,
            'user_role_names' => ['*'],
        ]);

        SchoolUserLicence::create([
            'school_id' => $school->id,
            'licence_id' => $licence->id,
            'user_id' => $user->id,
            'assignment_type' => 'user',
            'valid_from' => now()->subMonth()->toDateString(),
            'valid_until' => now()->addMonth()->toDateString(),
            'base_price_per_year' => '29',
            'charged_price' => '29.00',
            'is_active' => true,
        ]);

        $result = $this->service->toolAccessStatusForUser($user, $school, 'Lehrertool', ['student']);

        expect($result)->toBe('active');
    });

    it('returns active for school-level status when structured school layer is disabled', function () {
        $school = School::factory()->create();

        Licence::create([
            'name' => 'Materialientool',
            'long_name' => 'Materialientool',
            'licence_schema_version' => 2,
            'school_licence_enabled' => false,
            'admin_licence_enabled' => false,
            'user_licence_enabled' => false,
        ]);

        $result = $this->service->licenceStatus($school, 'Materialientool');

        expect($result)->toBe('active');
    });

    it('returns missing for school-level status when structured school layer is enabled and no school assignment exists', function () {
        $school = School::factory()->create();

        Licence::create([
            'name' => 'Materialientool',
            'long_name' => 'Materialientool',
            'licence_schema_version' => 2,
            'school_licence_enabled' => true,
            'admin_licence_enabled' => false,
            'user_licence_enabled' => false,
        ]);

        $result = $this->service->licenceStatus($school, 'Materialientool');

        expect($result)->toBe('missing');
    });
});

describe('schoolAddLicence', function () {
    it('creates a new school licence when it does not exist', function () {
        $school = School::factory()->create();
        $licence = Licence::create(['name' => 'app1', 'long_name' => 'Application 1']);

        $data = [
            'licence_id' => $licence->id,
            'valid_until' => now()->addYear(),
        ];

        $result = $this->service->schoolAddLicence($school, $data);

        expect($result)->toBeInstanceOf(SchoolLicence::class)
            ->and($result->school_id)->toBe($school->id)
            ->and($result->licence_id)->toBe($licence->id)
            ->and($result->valid_until)->not->toBeNull();
    });

    it('updates existing school licence when it already exists', function () {
        $school = School::factory()->create();
        $licence = Licence::create(['name' => 'app1', 'long_name' => 'Application 1']);

        $schoolLicence = SchoolLicence::create([
            'school_id' => $school->id,
            'licence_id' => $licence->id,
            'valid_until' => now()->addMonth(),
        ]);

        $data = [
            'licence_id' => $licence->id,
            'valid_until' => now()->addYear(),
        ];

        $result = $this->service->schoolAddLicence($school, $data);

        expect($result->id)->toBe($schoolLicence->id)
            ->and(SchoolLicence::count())->toBe(1)
            ->and($result->valid_until->format('Y-m-d'))->toBe(now()->addYear()->format('Y-m-d'));
    });

    it('handles null valid_until date', function () {
        $school = School::factory()->create();
        $licence = Licence::create(['name' => 'app1', 'long_name' => 'Application 1']);

        $data = [
            'licence_id' => $licence->id,
            'valid_until' => null,
        ];

        $result = $this->service->schoolAddLicence($school, $data);

        expect($result->valid_until)->toBeNull();
    });

    it('accepts school array with id', function () {
        $school = School::factory()->create();
        $licence = Licence::create(['name' => 'app1', 'long_name' => 'Application 1']);

        $data = [
            'licence_id' => $licence->id,
            'valid_until' => now()->addYear(),
        ];

        $result = $this->service->schoolAddLicence(['id' => $school->id], $data);

        expect($result)->toBeInstanceOf(SchoolLicence::class)
            ->and($result->school_id)->toBe($school->id);
    });
});

describe('deleteLicences', function () {
    it('deletes licences when they are not assigned to any school', function () {
        $licence1 = Licence::create(['name' => 'app1', 'long_name' => 'Application 1']);
        $licence2 = Licence::create(['name' => 'app2', 'long_name' => 'Application 2']);

        $this->service->deleteLicences([$licence1->id, $licence2->id]);

        expect(Licence::count())->toBe(0);
    });

    it('deletes single licence by id', function () {
        $licence = Licence::create(['name' => 'app1', 'long_name' => 'Application 1']);

        $this->service->deleteLicences([$licence->id]);

        expect(Licence::find($licence->id))->toBeNull();
    });

    it('aborts when trying to delete licence assigned to a school', function () {
        $school = School::factory()->create();
        $licence = Licence::create(['name' => 'app1', 'long_name' => 'Application 1']);

        SchoolLicence::create([
            'school_id' => $school->id,
            'licence_id' => $licence->id,
            'valid_until' => now()->addYear(),
        ]);

        $this->service->deleteLicences([$licence->id]);
    })->throws(HttpException::class, 'Mindestens eine Lizenz ist noch einer Schule zugeordnet');

    it('aborts when at least one licence in the array is assigned to a school', function () {
        $school = School::factory()->create();
        $licence1 = Licence::create(['name' => 'app1', 'long_name' => 'Application 1']);
        $licence2 = Licence::create(['name' => 'app2', 'long_name' => 'Application 2']);

        SchoolLicence::create([
            'school_id' => $school->id,
            'licence_id' => $licence1->id,
            'valid_until' => now()->addYear(),
        ]);

        $this->service->deleteLicences([$licence1->id, $licence2->id]);
    })->throws(HttpException::class);

    it('does not delete any licences when one is assigned', function () {
        $school = School::factory()->create();
        $licence1 = Licence::create(['name' => 'app1', 'long_name' => 'Application 1']);
        $licence2 = Licence::create(['name' => 'app2', 'long_name' => 'Application 2']);

        SchoolLicence::create([
            'school_id' => $school->id,
            'licence_id' => $licence1->id,
            'valid_until' => now()->addYear(),
        ]);

        try {
            $this->service->deleteLicences([$licence1->id, $licence2->id]);
        } catch (Exception $e) {
            // Expected to throw
        }

        expect(Licence::count())->toBe(2);
    });
});

describe('checkLicence', function () {
    it('returns success status when licence is valid', function () {
        $school = School::factory()->create();
        $licence = Licence::create(['name' => 'app1', 'long_name' => 'Application 1']);

        SchoolLicence::create([
            'school_id' => $school->id,
            'licence_id' => $licence->id,
            'valid_until' => now()->addYear(),
        ]);

        $result = $this->service->checkLicence($school, 'app1');

        expect($result['status'])->toBe('ok')
            ->and($result['redirect'])->toBe('&licence=app1');
    });

    it('returns success when licence has no expiration date set', function () {
        $school = School::factory()->create();
        $licence = Licence::create(['name' => 'app1', 'long_name' => 'Application 1']);

        SchoolLicence::create([
            'school_id' => $school->id,
            'licence_id' => $licence->id,
            'valid_until' => null,
        ]);

        $result = $this->service->checkLicence($school, 'app1');

        expect($result['status'])->toBe('ok')
            ->and($result['redirect'])->toBe('&licence=app1');
    });

    it('returns error when licence does not exist', function () {
        $school = School::factory()->create();

        $result = $this->service->checkLicence($school, 'nonexistent');

        expect($result['status'])->toBe('error')
            ->and($result['msg'])->toBe('Die Lizenz konnte nicht gefunden werden.');
    });

    it('returns error when school does not have the licence', function () {
        $school = School::factory()->create();
        $licence = Licence::create(['name' => 'app1', 'long_name' => 'Application 1']);

        $result = $this->service->checkLicence($school, 'app1');

        expect($result['status'])->toBe('error')
            ->and($result['msg'])->toBe('Die Schule hat für die App keine Lizenz.');
    });

    it('returns error when licence has expired', function () {
        $school = School::factory()->create();
        $licence = Licence::create(['name' => 'app1', 'long_name' => 'Application 1']);

        SchoolLicence::create([
            'school_id' => $school->id,
            'licence_id' => $licence->id,
            'valid_until' => now()->subDay(),
        ]);

        $result = $this->service->checkLicence($school, 'app1');

        expect($result['status'])->toBe('error')
            ->and($result['msg'])->toBe('Die Lizenz für die App ist abgelaufen.');
    });

    it('returns success when licence expires today', function () {
        $school = School::factory()->create();
        $licence = Licence::create(['name' => 'app1', 'long_name' => 'Application 1']);

        SchoolLicence::create([
            'school_id' => $school->id,
            'licence_id' => $licence->id,
            'valid_until' => now()->startOfDay(),
        ]);

        $result = $this->service->checkLicence($school, 'app1');

        expect($result['status'])->toBe('ok')
            ->and($result['redirect'])->toBe('&licence=app1');
    });

    it('includes licence name in redirect parameter', function () {
        $school = School::factory()->create();
        $licence = Licence::create(['name' => 'custom_app', 'long_name' => 'Custom Application']);

        SchoolLicence::create([
            'school_id' => $school->id,
            'licence_id' => $licence->id,
            'valid_until' => now()->addYear(),
        ]);

        $result = $this->service->checkLicence($school, 'custom_app');

        expect($result['redirect'])->toBe('&licence=custom_app');
    });

    it('handles edge case where valid_until is shortly in future', function () {
        $school = School::factory()->create();
        $licence = Licence::create(['name' => 'app1', 'long_name' => 'Application 1']);

        SchoolLicence::create([
            'school_id' => $school->id,
            'licence_id' => $licence->id,
            'valid_until' => now()->addDay(),
        ]);

        $result = $this->service->checkLicence($school, 'app1');

        expect($result['status'])->toBe('ok');
    });
});
