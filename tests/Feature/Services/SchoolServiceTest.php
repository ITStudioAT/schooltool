<?php

use App\Models\Licence;
use App\Models\School;
use App\Models\Schoolyear;
use App\Models\User;
use App\Models\Register;
use App\Services\SchoolService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = new SchoolService();

    // Create roles
    Role::create(['name' => 'super_admin']);
    Role::create(['name' => 'admin']);
    Role::create(['name' => 'register_admin']);

    // Create a dummy school and user with ID 1 to ensure they are protected from deletion
    $this->dummySchool = School::factory()->create(['id' => 1]);
    $dummySchoolyear = Schoolyear::factory()->create(['school_id' => $this->dummySchool->id]);
    User::factory()->create([
        'id' => 1,
        'school_id' => $this->dummySchool->id,
        'schoolyear_id' => $dummySchoolyear->id,
    ]);
});

describe('create', function () {
    it('creates a school with super admin and default schoolyear', function () {
        Storage::fake('public');

        $data = [
            'short_name' => 'TS',
            'long_name' => 'Test School',
            'is_selectable' => true,
        ];

        $school = $this->service->create($data);

        expect($school)->toBeInstanceOf(School::class)
            ->short_name->toBe('TS')
            ->long_name->toBe('Test School')
            ->is_selectable->toBeTrue();

        // Verify schoolyear was created
        expect($school->schoolyears()->count())->toBe(1);
        $schoolyear = $school->schoolyears()->first();
        expect($schoolyear->name)->toBe('Schuljahr');

        // Verify super admin was created
        $user = $school->users()->first();
        expect($user)->not->toBeNull()
            ->last_name->toBe(env('SA_LAST_NAME'))
            ->first_name->toBe(env('SA_FIRST_NAME'))
            ->email->toBe(env('SA_EMAIL'))
            ->email_verified_at->not->toBeNull()
            ->confirmed_at->not->toBeNull();

        expect($user->hasRole('super_admin'))->toBeTrue();

        // Verify folders were created for the school
        expect(Storage::exists("{$school->id}/temp"))->toBeTrue()
            ->and(Storage::exists("{$school->id}/excel"))->toBeTrue()
            ->and(Storage::exists("{$school->id}/pdf"))->toBeTrue();
    });

    it('creates a school and moves logo when upload_file is provided', function () {
        Storage::fake('public');

        // Create a test image in temp location
        $tempPath = 'temp/1/test-logo.jpg';
        Storage::disk('public')->put($tempPath, 'test image content');

        $data = [
            'short_name' => 'TS',
            'long_name' => 'Test School',
            'is_selectable' => true,
            'upload_file' => '/storage/' . $tempPath,
        ];

        File::shouldReceive('ensureDirectoryExists')->once();
        File::shouldReceive('copy')->once()->andReturn(true);

        $school = $this->service->create($data);

        expect($school)->toBeInstanceOf(School::class);

        // Verify folders were created for the school
        expect(Storage::exists("{$school->id}/temp"))->toBeTrue()
            ->and(Storage::exists("{$school->id}/excel"))->toBeTrue()
            ->and(Storage::exists("{$school->id}/pdf"))->toBeTrue();
    });
});

describe('update', function () {
    it('updates school data', function () {
        $school = School::factory()->create([
            'short_name' => 'OLD',
            'long_name' => 'Old School',
        ]);

        $data = [
            'short_name' => 'NEW',
            'long_name' => 'New School',
        ];

        $updatedSchool = $this->service->update($school, $data);

        expect($updatedSchool->short_name)->toBe('NEW')
            ->and($updatedSchool->long_name)->toBe('New School');
    });

    it('updates school and moves logo when upload_file is provided', function () {
        $school = School::factory()->create();
        
        Storage::fake('public');
        $tempPath = 'temp/1/updated-logo.jpg';
        Storage::disk('public')->put($tempPath, 'test image content');

        File::shouldReceive('ensureDirectoryExists')->once();
        File::shouldReceive('copy')->once()->andReturn(true);

        $data = [
            'short_name' => 'UPDATED',
            'upload_file' => '/storage/' . $tempPath,
        ];

        $updatedSchool = $this->service->update($school, $data);

        expect($updatedSchool->short_name)->toBe('UPDATED');
    });
});

describe('deleteSchools', function () {
    it('skips deletion of school with ID 1', function () {
        Storage::fake('public');

        $this->service->deleteSchools([1]);

        // School 1 should still exist
        expect(School::find(1))->not->toBeNull();
    });

    it('skips deletion of school with registers', function () {
        Storage::fake('public');

        // Create school with ID != 1
        $school = School::factory()->create();
        $schoolyear = Schoolyear::factory()->create(['school_id' => $school->id]);
        $user = User::factory()->create([
            'school_id' => $school->id,
            'schoolyear_id' => $schoolyear->id,
        ]);
        Register::factory()->create(['school_id' => $school->id]);

        $this->service->deleteSchools([$school->id]);

        // School should still exist
        expect(School::find($school->id))->not->toBeNull();
    });

    it('skips deletion of school with multiple users', function () {
        Storage::fake('public');

        // Create school with ID != 1
        $school = School::factory()->create();
        $schoolyear = Schoolyear::factory()->create(['school_id' => $school->id]);

        // Create more than 1 user
        User::factory()->count(2)->create([
            'school_id' => $school->id,
            'schoolyear_id' => $schoolyear->id,
        ]);

        $this->service->deleteSchools([$school->id]);

        // School should still exist
        expect(School::find($school->id))->not->toBeNull();
    });

    it('successfully deletes school with only super admin', function () {
        Storage::fake('public');

        // Create school with ID != 1
        $school = School::factory()->create();
        $schoolyear = Schoolyear::factory()->create(['school_id' => $school->id]);

        // Create only 1 user (super admin)
        $user = User::factory()->create([
            'school_id' => $school->id,
            'schoolyear_id' => $schoolyear->id,
        ]);
        $user->assignRole('super_admin');

        $this->service->deleteSchools([$school->id]);

        expect(School::find($school->id))->toBeNull();
        expect(User::find($user->id))->toBeNull();
        expect(Schoolyear::find($schoolyear->id))->toBeNull();
    });

    it('detaches all licences when deleting school', function () {
        Storage::fake('public');

        // Create school with ID != 1
        $school = School::factory()->create();
        $schoolyear = Schoolyear::factory()->create(['school_id' => $school->id]);
        $user = User::factory()->create([
            'school_id' => $school->id,
            'schoolyear_id' => $schoolyear->id,
        ]);

        // Create licence manually since no factory exists
        $licence = Licence::create([
            'name' => 'Test Licence',
            'long_name' => 'Test Licence Long Name',
            'is_selectable' => true,
        ]);
        $school->licences()->attach($licence->id);

        expect($school->licences()->count())->toBe(1);

        $this->service->deleteSchools([$school->id]);

        expect(School::find($school->id))->toBeNull();
    });

    it('deletes school logo when deleting school', function () {
        Storage::fake('public');

        $school = School::factory()->create(['logo' => 'test-logo.jpg']);
        $schoolyear = Schoolyear::factory()->create(['school_id' => $school->id]);
        $user = User::factory()->create([
            'school_id' => $school->id,
            'schoolyear_id' => $schoolyear->id,
        ]);

        // Create logo file
        Storage::disk('public')->put('images/test-logo.jpg', 'logo content');

        expect(Storage::disk('public')->exists('images/test-logo.jpg'))->toBeTrue();

        $this->service->deleteSchools([$school->id]);

        expect(School::find($school->id))->toBeNull();
        expect(Storage::disk('public')->exists('images/test-logo.jpg'))->toBeFalse();
    });

    it('deletes school storage directory when deleting school', function () {
        Storage::fake(); // Fake default storage disk
        Storage::fake('public'); // Also fake public disk

        $school = School::factory()->create();
        $schoolyear = Schoolyear::factory()->create(['school_id' => $school->id]);
        $user = User::factory()->create([
            'school_id' => $school->id,
            'schoolyear_id' => $schoolyear->id,
        ]);

        // Create school directories with files in the default storage disk
        Storage::makeDirectory("{$school->id}/temp");
        Storage::makeDirectory("{$school->id}/excel");
        Storage::put("{$school->id}/temp/test.txt", 'test');
        Storage::put("{$school->id}/excel/test.xlsx", 'test');

        expect(Storage::exists("{$school->id}/temp"))->toBeTrue();
        expect(Storage::exists("{$school->id}/excel"))->toBeTrue();

        $this->service->deleteSchools([$school->id]);

        expect(School::find($school->id))->toBeNull();
        expect(Storage::exists("{$school->id}"))->toBeFalse();
    });

    it('deletes schoolyears when deleting school', function () {
        Storage::fake('public');

        $school = School::factory()->create();
        $schoolyear1 = Schoolyear::factory()->create(['school_id' => $school->id, 'name' => 'Year 1']);
        $schoolyear2 = Schoolyear::factory()->create(['school_id' => $school->id, 'name' => 'Year 2']);
        $user = User::factory()->create([
            'school_id' => $school->id,
            'schoolyear_id' => $schoolyear1->id,
        ]);

        expect(Schoolyear::where('school_id', $school->id)->count())->toBe(2);

        $this->service->deleteSchools([$school->id]);

        expect(School::find($school->id))->toBeNull();
        expect(Schoolyear::where('school_id', $school->id)->count())->toBe(0);
    });

    it('removes all roles from super admin when deleting school', function () {
        Storage::fake('public');

        $school = School::factory()->create();
        $schoolyear = Schoolyear::factory()->create(['school_id' => $school->id]);
        $user = User::factory()->create([
            'school_id' => $school->id,
            'schoolyear_id' => $schoolyear->id,
        ]);
        $user->assignRole('super_admin');
        $user->assignRole('admin');

        expect($user->roles()->count())->toBe(2);

        $this->service->deleteSchools([$school->id]);

        expect(School::find($school->id))->toBeNull();
        expect(User::find($user->id))->toBeNull();
    });

    it('handles multiple schools in deleteSchools array', function () {
        Storage::fake('public');

        $school1 = School::factory()->create();
        $schoolyear1 = Schoolyear::factory()->create(['school_id' => $school1->id]);
        $user1 = User::factory()->create([
            'school_id' => $school1->id,
            'schoolyear_id' => $schoolyear1->id,
        ]);

        $school2 = School::factory()->create();
        $schoolyear2 = Schoolyear::factory()->create(['school_id' => $school2->id]);
        $user2 = User::factory()->create([
            'school_id' => $school2->id,
            'schoolyear_id' => $schoolyear2->id,
        ]);

        $this->service->deleteSchools([$school1->id, $school2->id]);

        expect(School::find($school1->id))->toBeNull();
        expect(School::find($school2->id))->toBeNull();
    });

    it('skips some schools and deletes others based on validation', function () {
        Storage::fake('public');

        // School 1 already exists from beforeEach and cannot be deleted

        // School 2 can be deleted
        $school2 = School::factory()->create();
        $schoolyear2 = Schoolyear::factory()->create(['school_id' => $school2->id]);
        $user2 = User::factory()->create([
            'school_id' => $school2->id,
            'schoolyear_id' => $schoolyear2->id,
        ]);

        $this->service->deleteSchools([1, $school2->id]);

        // School 1 should still exist
        expect(School::find(1))->not->toBeNull();
        // School 2 should be deleted
        expect(School::find($school2->id))->toBeNull();
    });
});

describe('schoolInfos', function () {
    it('returns licences and admins for a school', function () {
        $school = School::factory()->create();
        $schoolyear = Schoolyear::factory()->create(['school_id' => $school->id]);
        
        // Create licences manually since no factory exists
        $licence1 = Licence::create([
            'name' => 'Licence A',
            'long_name' => 'Licence A Long Name',
            'is_selectable' => true,
        ]);
        $licence2 = Licence::create([
            'name' => 'Licence B',
            'long_name' => 'Licence B Long Name',
            'is_selectable' => true,
        ]);
        $school->licences()->attach([$licence1->id, $licence2->id]);

        // Create admin users
        $admin = User::factory()->create([
            'school_id' => $school->id,
            'schoolyear_id' => $schoolyear->id,
            'last_name' => 'Admin',
        ]);
        $admin->assignRole('admin');

        $superAdmin = User::factory()->create([
            'school_id' => $school->id,
            'schoolyear_id' => $schoolyear->id,
            'last_name' => 'Super',
        ]);
        $superAdmin->assignRole('super_admin');

        // Create a regular user (should not be in result)
        $regularUser = User::factory()->create([
            'school_id' => $school->id,
            'schoolyear_id' => $schoolyear->id,
        ]);

        $result = $this->service->schoolInfos($school->id);

        expect($result)->toBeArray()
            ->toHaveKey('licences')
            ->toHaveKey('admins');

        expect($result['licences'])->toHaveCount(2);
        expect($result['admins'])->toHaveCount(2);
    });
});

describe('loadSwitchableSchools', function () {
    it('returns all schools where user has accounts', function () {
        $school1 = School::factory()->create(['long_name' => 'School A']);
        $school2 = School::factory()->create(['long_name' => 'School B']);
        $schoolyear1 = Schoolyear::factory()->create(['school_id' => $school1->id]);
        $schoolyear2 = Schoolyear::factory()->create(['school_id' => $school2->id]);

        // Create user in both schools with same email
        $user1 = User::factory()->create([
            'email' => 'user@example.com',
            'school_id' => $school1->id,
            'schoolyear_id' => $schoolyear1->id,
        ]);

        $user2 = User::factory()->create([
            'email' => 'user@example.com',
            'school_id' => $school2->id,
            'schoolyear_id' => $schoolyear2->id,
        ]);

        $schools = $this->service->loadSwitchableSchools($user1);

        expect($schools)->toHaveCount(2);
        expect($schools->pluck('id'))->toContain($school1->id, $school2->id);
    });

    it('returns unique schools sorted by long_name', function () {
        $school1 = School::factory()->create(['long_name' => 'Zebra School']);
        $school2 = School::factory()->create(['long_name' => 'Alpha School']);
        $schoolyear1 = Schoolyear::factory()->create(['school_id' => $school1->id]);
        $schoolyear2 = Schoolyear::factory()->create(['school_id' => $school2->id]);

        $user1 = User::factory()->create([
            'email' => 'user@example.com',
            'school_id' => $school1->id,
            'schoolyear_id' => $schoolyear1->id,
        ]);

        $user2 = User::factory()->create([
            'email' => 'user@example.com',
            'school_id' => $school2->id,
            'schoolyear_id' => $schoolyear2->id,
        ]);

        $schools = $this->service->loadSwitchableSchools($user1);

        expect($schools->first()->long_name)->toBe('Alpha School');
        expect($schools->last()->long_name)->toBe('Zebra School');
    });
});

describe('switchSchool', function () {
    it('switches user to different school', function () {
        $school1 = School::factory()->create();
        $school2 = School::factory()->create();
        $schoolyear1 = Schoolyear::factory()->create(['school_id' => $school1->id]);
        $schoolyear2 = Schoolyear::factory()->create(['school_id' => $school2->id]);

        $user1 = User::factory()->create([
            'email' => 'user@example.com',
            'school_id' => $school1->id,
            'schoolyear_id' => $schoolyear1->id,
        ]);

        $user2 = User::factory()->create([
            'email' => 'user@example.com',
            'school_id' => $school2->id,
            'schoolyear_id' => $schoolyear2->id,
        ]);

        // Act as the user for actual authentication
        $this->actingAs($user1);
        
        $result = $this->service->switchSchool($user1, $school2->id);

        expect($result->id)->toBe($user2->id)
            ->and($result->school_id)->toBe($school2->id);
    });

    it('throws exception when user does not exist in target school', function () {
        $school1 = School::factory()->create();
        $school2 = School::factory()->create();
        $schoolyear1 = Schoolyear::factory()->create(['school_id' => $school1->id]);

        $user = User::factory()->create([
            'email' => 'user@example.com',
            'school_id' => $school1->id,
            'schoolyear_id' => $schoolyear1->id,
        ]);

        $this->service->switchSchool($user, $school2->id);
    })->throws(Exception::class, 'Wechsel zu der Schule nicht möglich.');
});

describe('addAdmin', function () {
    it('creates a new admin user', function () {
        $school = School::factory()->create();
        $schoolyear = Schoolyear::factory()->create(['school_id' => $school->id]);

        $data = [
            'email' => 'newadmin@example.com',
            'first_name' => 'John',
            'last_name' => 'Admin',
        ];

        $user = $this->service->addAdmin($school->id, $schoolyear->id, $data, ['admin']);

        expect($user)->toBeInstanceOf(User::class)
            ->email->toBe('newadmin@example.com')
            ->first_name->toBe('John')
            ->last_name->toBe('Admin')
            ->school_id->toBe($school->id)
            ->schoolyear_id->toBe($schoolyear->id)
            ->email_verified_at->not->toBeNull()
            ->confirmed_at->not->toBeNull();

        expect($user->hasRole('admin'))->toBeTrue();
    });

    it('prevents creating admin with duplicate email in same school', function () {
        $school = School::factory()->create();
        $schoolyear = Schoolyear::factory()->create(['school_id' => $school->id]);

        User::factory()->create([
            'email' => 'existing@example.com',
            'school_id' => $school->id,
            'schoolyear_id' => $schoolyear->id,
        ]);

        $data = [
            'email' => 'existing@example.com',
            'first_name' => 'John',
            'last_name' => 'Duplicate',
        ];

        $this->service->addAdmin($school->id, $schoolyear->id, $data, ['admin']);
    })->throws(Exception::class, 'Dieser Admin existiert bereits und kann daher nicht angelegt werden.');

    it('prevents creating second super_admin for a school', function () {
        $school = School::factory()->create();
        $schoolyear = Schoolyear::factory()->create(['school_id' => $school->id]);

        // Create existing super admin
        $existingSuperAdmin = User::factory()->create([
            'school_id' => $school->id,
            'schoolyear_id' => $schoolyear->id,
        ]);
        $existingSuperAdmin->assignRole('super_admin');

        $data = [
            'email' => 'newsuperadmin@example.com',
            'first_name' => 'John',
            'last_name' => 'SuperAdmin',
        ];

        $this->service->addAdmin($school->id, $schoolyear->id, $data, ['super_admin']);
    })->throws(Exception::class, 'Für diese Schule existiert bereits ein Super-Admin.');

    it('assigns multiple roles to user', function () {
        $school = School::factory()->create();
        $schoolyear = Schoolyear::factory()->create(['school_id' => $school->id]);

        $data = [
            'email' => 'multirole@example.com',
            'first_name' => 'Multi',
            'last_name' => 'Role',
        ];

        $user = $this->service->addAdmin($school->id, $schoolyear->id, $data, ['admin', 'register_admin']);

        expect($user->hasRole('admin'))->toBeTrue();
        expect($user->hasRole('register_admin'))->toBeTrue();
    });
});

describe('deleteAdmin', function () {
    it('prevents deletion of user with ID 1', function () {
        $this->service->deleteAdmin(1, true);
    })->throws(Exception::class, 'Der Big-Boss-User kann nicht gelöscht werden.');

    it('removes admin roles from user without complete deletion', function () {
        $school = School::factory()->create();
        $schoolyear = Schoolyear::factory()->create(['school_id' => $school->id]);
        
        $user = User::factory()->create([
            'school_id' => $school->id,
            'schoolyear_id' => $schoolyear->id,
        ]);
        $user->assignRole('admin');
        $user->assignRole('register_admin');

        $this->service->deleteAdmin($user->id, false);

        $user->refresh();
        expect($user->hasRole('admin'))->toBeFalse();
        expect($user->hasRole('register_admin'))->toBeFalse();
        expect(User::find($user->id))->not->toBeNull();
    });

    it('completely deletes user when no bookings exist', function () {
        $school = School::factory()->create();
        $schoolyear = Schoolyear::factory()->create(['school_id' => $school->id]);
        
        $user = User::factory()->create([
            'school_id' => $school->id,
            'schoolyear_id' => $schoolyear->id,
        ]);
        $user->assignRole('admin');

        $userId = $user->id;

        $this->service->deleteAdmin($userId, true);

        expect(User::find($userId))->toBeNull();
    });
});
