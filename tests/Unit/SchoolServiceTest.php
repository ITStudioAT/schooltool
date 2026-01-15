<?php

use App\Models\Licence;
use App\Models\Register;
use App\Models\School;
use App\Models\SchoolTool;
use App\Models\Schoolyear;
use App\Models\Teacher;
use App\Models\User;
use App\Services\SchoolService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->service = new SchoolService();

    // Create required roles
    Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'register_admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'tutoring_admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']);

    // Set environment variables for super admin creation
    config(['app.env' => [
        'SA_LAST_NAME' => 'Admin',
        'SA_FIRST_NAME' => 'Super',
        'SA_EMAIL' => 'superadmin@example.com',
        'SA_PW' => Hash::make('password123'),
    ]]);

    // Fake storage
    Storage::fake('public');
    Storage::fake('private');
});

describe('create', function () {
    it('creates a school with basic data', function () {
        $data = [
            'long_name' => 'Test School',
            'short_name' => 'TS',
            'email' => 'school@example.com',
        ];

        $school = $this->service->create($data);

        expect($school)->toBeInstanceOf(School::class)
            ->and($school->long_name)->toBe('Test School')
            ->and($school->short_name)->toBe('TS')
            ->and($school->email)->toBe('school@example.com')
            ->and($school->exists)->toBeTrue();
    });

    it('creates default schoolyear when creating school', function () {
        $data = [
            'long_name' => 'Test School',
            'short_name' => 'TS',
        ];

        $school = $this->service->create($data);

        expect(Schoolyear::where('school_id', $school->id)->count())->toBe(1);

        $schoolyear = Schoolyear::where('school_id', $school->id)->first();
        expect($schoolyear->name)->toBe('Schuljahr');
    });

    it('creates super admin user when creating school', function () {
        $data = [
            'long_name' => 'Test School',
            'short_name' => 'TS',
        ];

        $school = $this->service->create($data);

        $user = User::where('school_id', $school->id)->first();

        expect($user)->not->toBeNull()
            ->and($user->email)->toBe(env('SA_EMAIL'))
            ->and($user->last_name)->toBe(env('SA_LAST_NAME'))
            ->and($user->first_name)->toBe(env('SA_FIRST_NAME'))
            ->and($user->email_verified_at)->not->toBeNull()
            ->and($user->confirmed_at)->not->toBeNull()
            ->and($user->hasRole('super_admin'))->toBeTrue();
    });

    it('creates SchoolTool record when creating school', function () {
        $data = [
            'long_name' => 'Test School',
            'short_name' => 'TS',
        ];

        $school = $this->service->create($data);

        $schoolTool = SchoolTool::where('school_id', $school->id)->first();

        expect($schoolTool)->not->toBeNull()
            ->and((bool) $schoolTool->tutoring_student_must_be_confirmed)->toBeFalse()
            ->and($schoolTool->tutoring_confirmer_email)->toBe('');
    });

    it('creates required storage directories', function () {
        $data = [
            'long_name' => 'Test School',
            'short_name' => 'TS',
        ];

        $school = $this->service->create($data);

        Storage::assertExists("{$school->id}/temp");
        Storage::assertExists("{$school->id}/excel");
        Storage::assertExists("{$school->id}/pdf");
    });

    it('handles logo upload when upload_file is provided', function () {
        // Create a temporary test file
        $tempPath = storage_path('app/private/temp/1/logo.jpg');
        File::ensureDirectoryExists(dirname($tempPath));
        File::put($tempPath, 'test logo content');

        $data = [
            'long_name' => 'Test School',
            'short_name' => 'TS',
            'upload_file' => $tempPath,
        ];

        $school = $this->service->create($data);

        expect($school->logo)->toContain('logo_')
            ->and($school->logo)->toContain('.jpg');

        // Cleanup
        File::delete($tempPath);
    });
});

describe('update', function () {
    it('updates school basic data', function () {
        $school = School::factory()->create([
            'long_name' => 'Old Name',
            'short_name' => 'ON',
        ]);

        $data = [
            'long_name' => 'New Name',
            'short_name' => 'NN',
            'email' => 'newemail@example.com',
        ];

        $updated = $this->service->update($school, $data);

        expect($updated->long_name)->toBe('New Name')
            ->and($updated->short_name)->toBe('NN')
            ->and($updated->email)->toBe('newemail@example.com');
    });

    it('handles logo upload when updating', function () {
        $school = School::factory()->create();

        // Create a temporary test file
        $tempPath = storage_path('app/private/temp/1/newlogo.png');
        File::ensureDirectoryExists(dirname($tempPath));
        File::put($tempPath, 'new logo content');

        $data = [
            'long_name' => 'Updated School',
            'upload_file' => $tempPath,
        ];

        $updated = $this->service->update($school, $data);

        expect($updated->logo)->toContain('logo_')
            ->and($updated->logo)->toContain('.png');

        // Cleanup
        File::delete($tempPath);
    });

    it('removes upload_file from data array', function () {
        $school = School::factory()->create(['long_name' => 'Test']);

        $data = [
            'long_name' => 'Updated',
        ];

        $this->service->update($school, $data);

        // Ensure the school was updated
        expect($school->fresh()->long_name)->toBe('Updated');
    });
});

describe('deleteSchools', function () {
    it('deletes multiple schools by ids', function () {
        $school1 = School::factory()->create();
        $school2 = School::factory()->create();
        $school3 = School::factory()->create();

        // Create minimal data to allow deletion
        Schoolyear::factory()->create(['school_id' => $school1->id]);
        Schoolyear::factory()->create(['school_id' => $school2->id]);
        Schoolyear::factory()->create(['school_id' => $school3->id]);

        User::factory()->create(['school_id' => $school1->id]);
        User::factory()->create(['school_id' => $school2->id]);
        User::factory()->create(['school_id' => $school3->id]);

        SchoolTool::create(['school_id' => $school1->id]);
        SchoolTool::create(['school_id' => $school2->id]);
        SchoolTool::create(['school_id' => $school3->id]);

        $this->service->deleteSchools([$school1->id, $school2->id]);

        expect(School::find($school1->id))->toBeNull()
            ->and(School::find($school2->id))->toBeNull()
            ->and(School::find($school3->id))->not->toBeNull();
    });
});

describe('deleteSchool', function () {
    it('cannot delete school with id 1', function () {
        $school = School::factory()->create(['id' => 1]);

        $result = $this->service->deleteSchools([1]);

        expect(School::find(1))->not->toBeNull();
    });

    it('cannot delete school with registers', function () {
        $school = School::factory()->create();
        $schoolyear = Schoolyear::factory()->create(['school_id' => $school->id]);
        Register::factory()->create(['school_id' => $school->id]);

        $this->service->deleteSchools([$school->id]);

        expect(School::find($school->id))->not->toBeNull();
    });

    it('cannot delete school with more than one user', function () {
        $school = School::factory()->create();
        $schoolyear = Schoolyear::factory()->create(['school_id' => $school->id]);

        User::factory()->count(2)->create(['school_id' => $school->id]);

        $this->service->deleteSchools([$school->id]);

        expect(School::find($school->id))->not->toBeNull();
    });

    it('deletes school with single user successfully', function () {
        $school = School::factory()->create();
        $schoolyear = Schoolyear::factory()->create(['school_id' => $school->id]);
        $user = User::factory()->create(['school_id' => $school->id]);
        SchoolTool::create(['school_id' => $school->id]);

        $this->service->deleteSchools([$school->id]);

        expect(School::find($school->id))->toBeNull()
            ->and(User::find($user->id))->toBeNull()
            ->and(Schoolyear::where('school_id', $school->id)->count())->toBe(0)
            ->and(SchoolTool::where('school_id', $school->id)->count())->toBe(0);
    });

    it('detaches licences when deleting school', function () {
        $school = School::factory()->create();
        $schoolyear = Schoolyear::factory()->create(['school_id' => $school->id]);
        $user = User::factory()->create(['school_id' => $school->id]);
        SchoolTool::create(['school_id' => $school->id]);

        $licence = Licence::create(['name' => 'Test Licence', 'long_name' => 'Test Licence Long']);
        $school->licences()->attach($licence->id);

        expect($school->licences()->count())->toBe(1);

        $this->service->deleteSchools([$school->id]);

        expect(School::find($school->id))->toBeNull();
    });

    it('removes user roles when deleting school', function () {
        $school = School::factory()->create();
        $schoolyear = Schoolyear::factory()->create(['school_id' => $school->id]);
        $user = User::factory()->create(['school_id' => $school->id]);
        $user->assignRole('admin');
        SchoolTool::create(['school_id' => $school->id]);

        expect($user->roles)->toHaveCount(1);

        $this->service->deleteSchools([$school->id]);

        expect(School::find($school->id))->toBeNull();
    });

    it('deletes storage directories when deleting school', function () {
        $school = School::factory()->create();
        $schoolyear = Schoolyear::factory()->create(['school_id' => $school->id]);
        $user = User::factory()->create(['school_id' => $school->id]);
        SchoolTool::create(['school_id' => $school->id]);

        // Create directories
        Storage::makeDirectory("{$school->id}/temp");
        Storage::makeDirectory("{$school->id}/excel");

        $this->service->deleteSchools([$school->id]);

        Storage::assertMissing("{$school->id}/temp");
        Storage::assertMissing("{$school->id}/excel");
    });
});

describe('schoolInfos', function () {
    it('returns licences for school', function () {
        $school = School::factory()->create();
        $licence1 = Licence::create(['name' => 'B Licence', 'long_name' => 'B Licence Long']);
        $licence2 = Licence::create(['name' => 'A Licence', 'long_name' => 'A Licence Long']);

        $school->licences()->attach([$licence1->id, $licence2->id]);

        $result = $this->service->schoolInfos($school->id);
        $licencesData = $result['licences']->resolve();

        expect($licencesData)->toBeArray()
            ->and($licencesData)->toHaveCount(2)
            // Should be sorted by name
            ->and($licencesData[0]['name'])->toBe('A Licence')
            ->and($licencesData[1]['name'])->toBe('B Licence');
    });

    it('returns admin users for school', function () {
        $school = School::factory()->create();
        $schoolyear = Schoolyear::factory()->create(['school_id' => $school->id]);

        $admin = User::factory()->create([
            'school_id' => $school->id,
            'last_name' => 'Admin',
        ]);
        $admin->assignRole('admin');

        $regularUser = User::factory()->create([
            'school_id' => $school->id,
            'last_name' => 'User',
        ]);

        $result = $this->service->schoolInfos($school->id);
        $adminsData = $result['admins']->resolve();

        expect($adminsData)->toBeArray()
            ->and($adminsData)->toHaveCount(1)
            ->and($adminsData[0]['last_name'])->toBe('Admin');
    });

    it('returns teacher counts', function () {
        $school = School::factory()->create();
        $schoolyear = Schoolyear::factory()->create(['school_id' => $school->id]);

        $teacher1 = User::factory()->create(['school_id' => $school->id]);
        $teacher1->assignRole('teacher');

        $teacher2 = User::factory()->create(['school_id' => $school->id]);
        $teacher2->assignRole('teacher');

        Teacher::create(['school_id' => $school->id, 'short' => 'T1', 'last_name' => 'Teacher1', 'email' => 'teacher1@example.com']);
        Teacher::create(['school_id' => $school->id, 'short' => 'T2', 'last_name' => 'Teacher2', 'email' => 'teacher2@example.com']);
        Teacher::create(['school_id' => $school->id, 'short' => 'T3', 'last_name' => 'Teacher3', 'email' => 'teacher3@example.com']);

        $result = $this->service->schoolInfos($school->id);

        expect($result['teachers']['count_active'])->toBe(2)
            ->and($result['teachers']['count'])->toBe(3);
    });

    it('includes all admin role types', function () {
        $school = School::factory()->create();
        $schoolyear = Schoolyear::factory()->create(['school_id' => $school->id]);

        $superAdmin = User::factory()->create(['school_id' => $school->id]);
        $superAdmin->assignRole('super_admin');

        $registerAdmin = User::factory()->create(['school_id' => $school->id]);
        $registerAdmin->assignRole('register_admin');

        $tutoringAdmin = User::factory()->create(['school_id' => $school->id]);
        $tutoringAdmin->assignRole('tutoring_admin');

        $result = $this->service->schoolInfos($school->id);
        $adminsData = $result['admins']->resolve();

        expect($adminsData)->toHaveCount(3);
    });
});

describe('loadSwitchableSchools', function () {
    it('returns schools where user has accounts', function () {
        $school1 = School::factory()->create(['long_name' => 'School A']);
        $school2 = School::factory()->create(['long_name' => 'School B']);
        $school3 = School::factory()->create(['long_name' => 'School C']);

        $user1 = User::factory()->create(['email' => 'test@example.com', 'school_id' => $school1->id]);
        $user2 = User::factory()->create(['email' => 'test@example.com', 'school_id' => $school2->id]);

        $schools = $this->service->loadSwitchableSchools($user1);

        expect($schools)->toHaveCount(2);

        $schoolIds = $schools->pluck('id')->toArray();
        expect($schoolIds)->toContain($school1->id)
            ->and($schoolIds)->toContain($school2->id)
            ->and($schoolIds)->not->toContain($school3->id);
    });

    it('returns schools sorted by long_name', function () {
        $school1 = School::factory()->create(['long_name' => 'Z School']);
        $school2 = School::factory()->create(['long_name' => 'A School']);
        $school3 = School::factory()->create(['long_name' => 'M School']);

        $user1 = User::factory()->create(['email' => 'test@example.com', 'school_id' => $school1->id]);
        $user2 = User::factory()->create(['email' => 'test@example.com', 'school_id' => $school2->id]);
        $user3 = User::factory()->create(['email' => 'test@example.com', 'school_id' => $school3->id]);

        $schools = $this->service->loadSwitchableSchools($user1);

        expect($schools->first()->long_name)->toBe('A School')
            ->and($schools->last()->long_name)->toBe('Z School');
    });

    it('returns unique schools only', function () {
        $school1 = School::factory()->create();
        $school2 = School::factory()->create();

        // Same email, same school - should appear only once
        $user1 = User::factory()->create(['email' => 'test@example.com', 'school_id' => $school1->id]);
        $user2 = User::factory()->create(['email' => 'test@example.com', 'school_id' => $school2->id]);

        $schools = $this->service->loadSwitchableSchools($user1);

        expect($schools)->toHaveCount(2);
    });
});

describe('switchSchool', function () {
    it('switches user to different school successfully', function () {
        $school1 = School::factory()->create();
        $school2 = School::factory()->create();

        $user1 = User::factory()->create(['email' => 'test@example.com', 'school_id' => $school1->id]);
        $user2 = User::factory()->create(['email' => 'test@example.com', 'school_id' => $school2->id]);

        Auth::login($user1);

        $targetUser = $this->service->switchSchool($user1, $school2->id);

        expect($targetUser->id)->toBe($user2->id)
            ->and($targetUser->school_id)->toBe($school2->id)
            ->and(Auth::id())->toBe($user2->id);
    });

    it('aborts when user does not exist in target school', function () {
        $school1 = School::factory()->create();
        $school2 = School::factory()->create();

        $user1 = User::factory()->create(['email' => 'test@example.com', 'school_id' => $school1->id]);
        // No user with this email in school2

        $this->service->switchSchool($user1, $school2->id);
    })->throws(\Symfony\Component\HttpKernel\Exception\HttpException::class, 'Wechsel zu der Schule nicht möglich.');

    it('logs out current user before switching', function () {
        $school1 = School::factory()->create();
        $school2 = School::factory()->create();

        $user1 = User::factory()->create(['email' => 'test@example.com', 'school_id' => $school1->id]);
        $user2 = User::factory()->create(['email' => 'test@example.com', 'school_id' => $school2->id]);

        Auth::login($user1);
        expect(Auth::id())->toBe($user1->id);

        $this->service->switchSchool($user1, $school2->id);

        expect(Auth::id())->toBe($user2->id);
    });
});

describe('addAdmin', function () {
    it('creates admin user with valid data', function () {
        $school = School::factory()->create();
        $schoolyear = Schoolyear::factory()->create(['school_id' => $school->id]);

        $data = [
            'email' => 'admin@example.com',
            'first_name' => 'John',
            'last_name' => 'Admin',
        ];

        $user = $this->service->addAdmin($school->id, $schoolyear->id, $data, ['admin']);

        expect($user)->toBeInstanceOf(User::class)
            ->and($user->email)->toBe('admin@example.com')
            ->and($user->first_name)->toBe('John')
            ->and($user->last_name)->toBe('Admin')
            ->and($user->school_id)->toBe($school->id)
            ->and($user->schoolyear_id)->toBe($schoolyear->id)
            ->and($user->email_verified_at)->not->toBeNull()
            ->and($user->confirmed_at)->not->toBeNull()
            ->and($user->hasRole('admin'))->toBeTrue();
    });

    it('assigns multiple roles to admin', function () {
        $school = School::factory()->create();
        $schoolyear = Schoolyear::factory()->create(['school_id' => $school->id]);

        $data = [
            'email' => 'admin@example.com',
            'first_name' => 'John',
            'last_name' => 'Admin',
        ];

        $user = $this->service->addAdmin($school->id, $schoolyear->id, $data, ['admin', 'register_admin']);

        expect($user->hasRole('admin'))->toBeTrue()
            ->and($user->hasRole('register_admin'))->toBeTrue();
    });

    it('aborts when admin email already exists in school', function () {
        $school = School::factory()->create();
        $schoolyear = Schoolyear::factory()->create(['school_id' => $school->id]);

        User::factory()->create([
            'email' => 'admin@example.com',
            'school_id' => $school->id,
        ]);

        $data = [
            'email' => 'admin@example.com',
            'first_name' => 'John',
            'last_name' => 'Admin',
        ];

        $this->service->addAdmin($school->id, $schoolyear->id, $data, ['admin']);
    })->throws(\Symfony\Component\HttpKernel\Exception\HttpException::class, 'Dieser Admin existiert bereits');

    it('aborts when adding super_admin but one already exists', function () {
        $school = School::factory()->create();
        $schoolyear = Schoolyear::factory()->create(['school_id' => $school->id]);

        $existingSuperAdmin = User::factory()->create(['school_id' => $school->id]);
        $existingSuperAdmin->assignRole('super_admin');

        $data = [
            'email' => 'newsuperadmin@example.com',
            'first_name' => 'New',
            'last_name' => 'SuperAdmin',
        ];

        $this->service->addAdmin($school->id, $schoolyear->id, $data, ['super_admin']);
    })->throws(\Symfony\Component\HttpKernel\Exception\HttpException::class, 'existiert bereits ein Super-Admin');

    it('generates password for new admin', function () {
        $school = School::factory()->create();
        $schoolyear = Schoolyear::factory()->create(['school_id' => $school->id]);

        $data = [
            'email' => 'admin@example.com',
            'first_name' => 'John',
            'last_name' => 'Admin',
        ];

        $user = $this->service->addAdmin($school->id, $schoolyear->id, $data, ['admin']);

        expect($user->password)->not->toBeNull()
            ->and($user->password)->not->toBeEmpty();
    });
});

describe('deleteAdmin', function () {
    it('removes admin and register_admin roles', function () {
        $school = School::factory()->create();
        $user = User::factory()->create(['school_id' => $school->id]);
        $user->assignRole(['admin', 'register_admin']);

        expect($user->hasRole('admin'))->toBeTrue()
            ->and($user->hasRole('register_admin'))->toBeTrue();

        $this->service->deleteAdmin($user->id, false);

        $user->refresh();
        expect($user->hasRole('admin'))->toBeFalse()
            ->and($user->hasRole('register_admin'))->toBeFalse();
    });

    it('deletes user completely when is_delete_complete is true', function () {
        $school = School::factory()->create();
        $user = User::factory()->create(['school_id' => $school->id]);
        $user->assignRole('admin');

        $userId = $user->id;

        $this->service->deleteAdmin($user->id, true);

        expect(User::find($userId))->toBeNull();
    });

    it('only removes roles when is_delete_complete is false', function () {
        $school = School::factory()->create();
        $user = User::factory()->create(['school_id' => $school->id]);
        $user->assignRole('admin');

        $userId = $user->id;

        $this->service->deleteAdmin($user->id, false);

        expect(User::find($userId))->not->toBeNull();
    });

    it('cannot delete user id 1', function () {
        $this->service->deleteAdmin(1, true);
    })->throws(\Symfony\Component\HttpKernel\Exception\HttpException::class, 'Big-Boss-User kann nicht gelöscht werden');

    it('aborts when user has bookings and delete_complete is true', function () {
        $school = School::factory()->create();
        $schoolyear = Schoolyear::factory()->create(['school_id' => $school->id]);
        $register = Register::factory()->create(['school_id' => $school->id]);

        $user = User::factory()->create(['school_id' => $school->id]);
        $user->assignRole('admin');

        // Create a booking
        $registerDate = \App\Models\RegisterDate::factory()->create(['register_id' => $register->id]);
        \App\Models\RegisterDateBooking::factory()->create([
            'register_date_id' => $registerDate->id,
            'user_id' => $user->id,
        ]);

        $this->service->deleteAdmin($user->id, true);
    })->throws(\Symfony\Component\HttpKernel\Exception\HttpException::class, 'hat noch gebuchte Anmeldungen');
});

