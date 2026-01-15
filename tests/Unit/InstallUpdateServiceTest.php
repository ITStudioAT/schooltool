<?php

use App\Models\School;
use App\Models\User;
use App\Services\InstallUpdateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    Storage::fake('public');

    $this->service = new InstallUpdateService();
});

describe('createRoles', function () {
    it('creates a single role', function () {
        $roles = ['admin'];
        
        $this->service->createRoles($roles);
        
        expect(Role::where('name', 'admin')->where('guard_name', 'web')->exists())->toBeTrue();
    });

    it('creates multiple roles', function () {
        $roles = ['admin', 'teacher', 'student'];
        
        $this->service->createRoles($roles);
        
        expect(Role::where('name', 'admin')->exists())->toBeTrue()
            ->and(Role::where('name', 'teacher')->exists())->toBeTrue()
            ->and(Role::where('name', 'student')->exists())->toBeTrue();
    });

    it('sets web as guard name for all roles', function () {
        $roles = ['admin', 'teacher'];
        
        $this->service->createRoles($roles);
        
        $adminRole = Role::where('name', 'admin')->first();
        $teacherRole = Role::where('name', 'teacher')->first();
        
        expect($adminRole->guard_name)->toBe('web')
            ->and($teacherRole->guard_name)->toBe('web');
    });

    it('does not duplicate roles if they already exist', function () {
        $roles = ['admin'];
        
        // Create role first time
        $this->service->createRoles($roles);
        $countAfterFirst = Role::where('name', 'admin')->count();
        
        // Try to create same role again
        $this->service->createRoles($roles);
        $countAfterSecond = Role::where('name', 'admin')->count();
        
        expect($countAfterFirst)->toBe(1)
            ->and($countAfterSecond)->toBe(1);
    });

    it('handles empty array gracefully', function () {
        $roles = [];
        
        $this->service->createRoles($roles);
        
        expect(Role::count())->toBe(0);
    });

    it('creates roles with special characters in name', function () {
        $roles = ['super_admin', 'content-editor'];
        
        $this->service->createRoles($roles);
        
        expect(Role::where('name', 'super_admin')->exists())->toBeTrue()
            ->and(Role::where('name', 'content-editor')->exists())->toBeTrue();
    });

    it('creates many roles efficiently', function () {
        $roles = [];
        for ($i = 1; $i <= 10; $i++) {
            $roles[] = "role_{$i}";
        }
        
        $this->service->createRoles($roles);
        
        expect(Role::count())->toBe(10);
    });

    it('maintains existing roles when creating new ones', function () {
        // Create initial roles
        $initialRoles = ['admin', 'teacher'];
        $this->service->createRoles($initialRoles);
        
        // Create additional roles
        $additionalRoles = ['student', 'parent'];
        $this->service->createRoles($additionalRoles);
        
        expect(Role::count())->toBe(4)
            ->and(Role::where('name', 'admin')->exists())->toBeTrue()
            ->and(Role::where('name', 'teacher')->exists())->toBeTrue()
            ->and(Role::where('name', 'student')->exists())->toBeTrue()
            ->and(Role::where('name', 'parent')->exists())->toBeTrue();
    });
});

describe('checkSuperAdmins', function () {
    beforeEach(function () {
        // Create super_admin role for tests
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    });

    it('creates super admin for school without one', function () {
        $school = School::factory()->create();
        
        $this->service->checkSuperAdmins();
        
        $superAdmins = $school->users()->whereHas('roles', function ($q) {
            $q->where('name', 'super_admin');
        })->count();
        
        expect($superAdmins)->toBe(1);
    });

    it('creates super admin with correct attributes', function () {
        $school = School::factory()->create();
        
        $this->service->checkSuperAdmins();
        
        $superAdmin = $school->users()->whereHas('roles', function ($q) {
            $q->where('name', 'super_admin');
        })->first();
        
        expect($superAdmin)->not->toBeNull()
            ->and($superAdmin->last_name)->toBe('Kron')
            ->and($superAdmin->first_name)->toBe('Günther')
            ->and($superAdmin->email)->toBe('kron@naturwelt.at');
    });

    it('uses default password when SA_PW is not set', function () {
        $school = School::factory()->create();
        
        $this->service->checkSuperAdmins();
        
        $superAdmin = $school->users()->whereHas('roles', function ($q) {
            $q->where('name', 'super_admin');
        })->first();
        
        expect($superAdmin)->not->toBeNull()
            ->and($superAdmin->password)->not->toBeEmpty()
            ->and($superAdmin->password)->not->toBeNull();
    });

    it('does not create duplicate super admin if one exists', function () {
        $school = School::factory()->create();
        
        // Create existing super admin
        $existingAdmin = $school->users()->create([
            'last_name' => 'Test',
            'first_name' => 'Admin',
            'email' => 'test@example.com',
            'password' => bcrypt('password123'),
        ]);
        $existingAdmin->assignRole('super_admin');
        
        config(['app.env.SA_PW' => 'TestPassword123!']);
        
        $this->service->checkSuperAdmins();
        
        $superAdminCount = $school->users()->whereHas('roles', function ($q) {
            $q->where('name', 'super_admin');
        })->count();
        
        expect($superAdminCount)->toBe(1);
    });

    it('handles multiple schools correctly', function () {
        $school1 = School::factory()->create();
        $school2 = School::factory()->create();
        $school3 = School::factory()->create();
        
        $this->service->checkSuperAdmins();
        
        $school1Admins = $school1->users()->whereHas('roles', function ($q) {
            $q->where('name', 'super_admin');
        })->count();
        
        $school2Admins = $school2->users()->whereHas('roles', function ($q) {
            $q->where('name', 'super_admin');
        })->count();
        
        $school3Admins = $school3->users()->whereHas('roles', function ($q) {
            $q->where('name', 'super_admin');
        })->count();
        
        expect($school1Admins)->toBe(1)
            ->and($school2Admins)->toBe(1)
            ->and($school3Admins)->toBe(1);
    });

    it('creates super admin only for schools missing one', function () {
        $school1 = School::factory()->create();
        $school2 = School::factory()->create();
        
        // School1 already has super admin
        $existingAdmin = $school1->users()->create([
            'last_name' => 'Existing',
            'first_name' => 'Admin',
            'email' => 'existing@example.com',
            'password' => bcrypt('HashedPassword123!'),
        ]);
        $existingAdmin->assignRole('super_admin');
        
        $this->service->checkSuperAdmins();
        
        // School1 should still have 1, School2 should now have 1
        $school1Admins = $school1->users()->whereHas('roles', function ($q) {
            $q->where('name', 'super_admin');
        })->count();
        
        $school2Admins = $school2->users()->whereHas('roles', function ($q) {
            $q->where('name', 'super_admin');
        })->count();
        
        expect($school1Admins)->toBe(1)
            ->and($school2Admins)->toBe(1)
            ->and($school1->users()->count())->toBe(1) // Only the existing one
            ->and($school2->users()->count())->toBe(1); // Only the new one
    });

    it('does nothing when no schools exist', function () {
        $this->service->checkSuperAdmins();
        
        expect(User::count())->toBe(0);
    });

    it('assigns super_admin role to created user', function () {
        $school = School::factory()->create();
        
        $this->service->checkSuperAdmins();
        
        $superAdmin = $school->users()->first();
        
        expect($superAdmin->hasRole('super_admin'))->toBeTrue();
    });

    it('creates super admin with school relationship', function () {
        $school = School::factory()->create(['short_name' => 'TEST']);
        
        $this->service->checkSuperAdmins();
        
        $superAdmin = User::where('email', 'kron@naturwelt.at')->first();
        
        expect($superAdmin)->not->toBeNull()
            ->and($superAdmin->school_id)->toBe($school->id);
        
        // Reload to get the relationship
        $superAdmin->refresh();
        $loadedSchool = School::find($superAdmin->school_id);
        expect($loadedSchool->short_name)->toBe('TEST');
    });
});

describe('findOrCreateFolders', function () {
    it('creates per-school temp directories for all schools', function () {
        $school1 = School::factory()->create();
        $school2 = School::factory()->create();

        $this->service->findOrCreateFolders();

        expect(Storage::exists("{$school1->id}/temp"))->toBeTrue()
            ->and(Storage::exists("{$school2->id}/temp"))->toBeTrue();
    });

    it('creates per-school excel directories for all schools', function () {
        $school1 = School::factory()->create();
        $school2 = School::factory()->create();

        $this->service->findOrCreateFolders();

        expect(Storage::exists("{$school1->id}/excel"))->toBeTrue()
            ->and(Storage::exists("{$school2->id}/excel"))->toBeTrue();
    });

    it('creates per-school pdf directories for all schools', function () {
        $school1 = School::factory()->create();
        $school2 = School::factory()->create();

        $this->service->findOrCreateFolders();

        expect(Storage::exists("{$school1->id}/pdf"))->toBeTrue()
            ->and(Storage::exists("{$school2->id}/pdf"))->toBeTrue();
    });

    it('creates images directory in public disk if it does not exist', function () {
        School::factory()->create();

        expect(Storage::disk('public')->exists('images'))->toBeFalse();

        $this->service->findOrCreateFolders();

        expect(Storage::disk('public')->exists('images'))->toBeTrue();
    });

    it('creates all required directories for each school', function () {
        $school = School::factory()->create();

        $this->service->findOrCreateFolders();

        expect(Storage::exists("{$school->id}/temp"))->toBeTrue()
            ->and(Storage::exists("{$school->id}/excel"))->toBeTrue()
            ->and(Storage::exists("{$school->id}/pdf"))->toBeTrue()
            ->and(Storage::disk('public')->exists('images'))->toBeTrue();
    });

    it('cleans existing temp directory for each school', function () {
        $school = School::factory()->create();

        // Create directory with files
        Storage::makeDirectory("{$school->id}/temp");
        Storage::put("{$school->id}/temp/test.txt", 'test content');
        Storage::put("{$school->id}/temp/test2.txt", 'test content 2');

        expect(Storage::exists("{$school->id}/temp/test.txt"))->toBeTrue();

        $this->service->findOrCreateFolders();

        expect(Storage::exists("{$school->id}/temp"))->toBeTrue()
            ->and(Storage::exists("{$school->id}/temp/test.txt"))->toBeFalse()
            ->and(Storage::exists("{$school->id}/temp/test2.txt"))->toBeFalse();
    });

    it('cleans existing excel directory for each school', function () {
        $school = School::factory()->create();

        Storage::makeDirectory("{$school->id}/excel");
        Storage::put("{$school->id}/excel/report.xlsx", 'excel content');

        $this->service->findOrCreateFolders();

        expect(Storage::exists("{$school->id}/excel"))->toBeTrue()
            ->and(Storage::exists("{$school->id}/excel/report.xlsx"))->toBeFalse();
    });

    it('cleans existing pdf directory for each school', function () {
        $school = School::factory()->create();

        Storage::makeDirectory("{$school->id}/pdf");
        Storage::put("{$school->id}/pdf/document.pdf", 'pdf content');

        $this->service->findOrCreateFolders();

        expect(Storage::exists("{$school->id}/pdf"))->toBeTrue()
            ->and(Storage::exists("{$school->id}/pdf/document.pdf"))->toBeFalse();
    });

    it('does not clean images directory in public disk', function () {
        School::factory()->create();

        // Create images directory with file
        Storage::disk('public')->makeDirectory('images');
        Storage::disk('public')->put('images/logo.png', 'image content');

        $this->service->findOrCreateFolders();

        expect(Storage::disk('public')->exists('images'))->toBeTrue()
            ->and(Storage::disk('public')->exists('images/logo.png'))->toBeTrue();
    });

    it('removes subdirectories in temp directory for each school', function () {
        $school = School::factory()->create();

        Storage::makeDirectory("{$school->id}/temp/subdir1");
        Storage::makeDirectory("{$school->id}/temp/subdir2");
        Storage::put("{$school->id}/temp/subdir1/file.txt", 'content');

        $this->service->findOrCreateFolders();

        expect(Storage::exists("{$school->id}/temp"))->toBeTrue()
            ->and(Storage::exists("{$school->id}/temp/subdir1"))->toBeFalse()
            ->and(Storage::exists("{$school->id}/temp/subdir2"))->toBeFalse();
    });

    it('removes subdirectories in excel directory for each school', function () {
        $school = School::factory()->create();

        Storage::makeDirectory("{$school->id}/excel/archive");
        Storage::put("{$school->id}/excel/archive/old.xlsx", 'content');

        $this->service->findOrCreateFolders();

        expect(Storage::exists("{$school->id}/excel"))->toBeTrue()
            ->and(Storage::exists("{$school->id}/excel/archive"))->toBeFalse();
    });

    it('removes subdirectories in pdf directory for each school', function () {
        $school = School::factory()->create();

        Storage::makeDirectory("{$school->id}/pdf/reports");
        Storage::put("{$school->id}/pdf/reports/report.pdf", 'content');

        $this->service->findOrCreateFolders();

        expect(Storage::exists("{$school->id}/pdf"))->toBeTrue()
            ->and(Storage::exists("{$school->id}/pdf/reports"))->toBeFalse();
    });

    it('handles multiple files in directories for each school', function () {
        $school = School::factory()->create();

        Storage::makeDirectory("{$school->id}/temp");
        for ($i = 1; $i <= 10; $i++) {
            Storage::put("{$school->id}/temp/file{$i}.txt", "content {$i}");
        }

        $this->service->findOrCreateFolders();

        $files = Storage::allFiles("{$school->id}/temp");
        expect(count($files))->toBe(0);
    });

    it('handles nested subdirectories for each school', function () {
        $school = School::factory()->create();

        Storage::makeDirectory("{$school->id}/temp/level1/level2/level3");
        Storage::put("{$school->id}/temp/level1/level2/level3/deep.txt", 'deep content');

        $this->service->findOrCreateFolders();

        expect(Storage::exists("{$school->id}/temp"))->toBeTrue()
            ->and(Storage::exists("{$school->id}/temp/level1"))->toBeFalse();
    });

    it('can be called multiple times safely', function () {
        $school = School::factory()->create();

        $this->service->findOrCreateFolders();
        $this->service->findOrCreateFolders();
        $this->service->findOrCreateFolders();

        expect(Storage::exists("{$school->id}/temp"))->toBeTrue()
            ->and(Storage::exists("{$school->id}/excel"))->toBeTrue()
            ->and(Storage::exists("{$school->id}/pdf"))->toBeTrue()
            ->and(Storage::disk('public')->exists('images'))->toBeTrue();
    });

    it('handles when no schools exist', function () {
        $this->service->findOrCreateFolders();

        // Should only create images directory
        expect(Storage::disk('public')->exists('images'))->toBeTrue();
    });

    it('creates folders for multiple schools independently', function () {
        $school1 = School::factory()->create();
        $school2 = School::factory()->create();
        $school3 = School::factory()->create();

        $this->service->findOrCreateFolders();

        expect(Storage::exists("{$school1->id}/temp"))->toBeTrue()
            ->and(Storage::exists("{$school1->id}/excel"))->toBeTrue()
            ->and(Storage::exists("{$school1->id}/pdf"))->toBeTrue()
            ->and(Storage::exists("{$school2->id}/temp"))->toBeTrue()
            ->and(Storage::exists("{$school2->id}/excel"))->toBeTrue()
            ->and(Storage::exists("{$school2->id}/pdf"))->toBeTrue()
            ->and(Storage::exists("{$school3->id}/temp"))->toBeTrue()
            ->and(Storage::exists("{$school3->id}/excel"))->toBeTrue()
            ->and(Storage::exists("{$school3->id}/pdf"))->toBeTrue();
    });
});

describe('createOrCleanDirectory (private method testing)', function () {
    it('creates directory when it does not exist', function () {
        // Test indirectly through findOrCreateFolders
        $school = School::factory()->create();

        // Delete entire school directory to ensure clean state
        Storage::deleteDirectory("{$school->id}");

        $this->service->findOrCreateFolders();

        expect(Storage::exists("{$school->id}/temp"))->toBeTrue();
    });

    it('cleans directory when it exists', function () {
        // Test indirectly through findOrCreateFolders
        $school = School::factory()->create();
        Storage::makeDirectory("{$school->id}/temp");
        Storage::put("{$school->id}/temp/old-file.txt", 'old content');

        $this->service->findOrCreateFolders();

        expect(Storage::exists("{$school->id}/temp"))->toBeTrue()
            ->and(Storage::allFiles("{$school->id}/temp"))->toBeEmpty();
    });

    it('preserves directory structure while cleaning content', function () {
        $school = School::factory()->create();
        Storage::makeDirectory("{$school->id}/excel/subfolder");
        Storage::put("{$school->id}/excel/file.xlsx", 'content');
        Storage::put("{$school->id}/excel/subfolder/nested.xlsx", 'nested content');

        $this->service->findOrCreateFolders();

        expect(Storage::exists("{$school->id}/excel"))->toBeTrue()
            ->and(Storage::allFiles("{$school->id}/excel"))->toBeEmpty()
            ->and(Storage::allDirectories("{$school->id}/excel"))->toBeEmpty();
    });
});

describe('integration tests', function () {
    it('sets up complete installation environment', function () {
        $school = School::factory()->create();

        // Run all setup methods
        $this->service->createRoles(['super_admin', 'admin', 'teacher']);
        $this->service->checkSuperAdmins();
        $this->service->findOrCreateFolders();

        // Verify roles are created
        expect(Role::count())->toBeGreaterThanOrEqual(3);

        // Verify super admin exists
        $superAdmins = $school->users()->whereHas('roles', function ($q) {
            $q->where('name', 'super_admin');
        })->count();
        expect($superAdmins)->toBe(1);

        // Verify directories exist for the school
        expect(Storage::exists("{$school->id}/temp"))->toBeTrue()
            ->and(Storage::exists("{$school->id}/excel"))->toBeTrue()
            ->and(Storage::exists("{$school->id}/pdf"))->toBeTrue()
            ->and(Storage::disk('public')->exists('images'))->toBeTrue();
    });

    it('handles multiple schools in installation', function () {
        $schools = School::factory()->count(3)->create();

        $this->service->createRoles(['super_admin']);
        $this->service->checkSuperAdmins();
        $this->service->findOrCreateFolders();

        // Each school should have a super admin
        foreach ($schools as $school) {
            $admins = $school->users()->whereHas('roles', function ($q) {
                $q->where('name', 'super_admin');
            })->count();
            expect($admins)->toBe(1);

            // Each school should have its own directories
            expect(Storage::exists("{$school->id}/temp"))->toBeTrue()
                ->and(Storage::exists("{$school->id}/excel"))->toBeTrue()
                ->and(Storage::exists("{$school->id}/pdf"))->toBeTrue();
        }

        // Total users should equal number of schools
        expect(User::count())->toBe(3);
    });

    it('can safely rerun installation process', function () {
        $school = School::factory()->create();

        // First run
        $this->service->createRoles(['super_admin', 'admin']);
        $this->service->checkSuperAdmins();
        $this->service->findOrCreateFolders();

        $firstRunRoleCount = Role::count();
        $firstRunUserCount = User::count();

        // Second run
        $this->service->createRoles(['super_admin', 'admin']);
        $this->service->checkSuperAdmins();
        $this->service->findOrCreateFolders();

        // Should not duplicate
        expect(Role::count())->toBe($firstRunRoleCount)
            ->and(User::count())->toBe($firstRunUserCount);
    });

    it('cleans temporary directories while preserving system state', function () {
        $school = School::factory()->create();

        // Create initial state
        $this->service->createRoles(['super_admin', 'admin']);  // Create super_admin role first
        $this->service->checkSuperAdmins();
        $this->service->findOrCreateFolders();

        // Add some temporary files
        Storage::put("{$school->id}/temp/temp1.txt", 'temp');
        Storage::put("{$school->id}/excel/excel1.xlsx", 'excel');
        Storage::put("{$school->id}/pdf/pdf1.pdf", 'pdf');

        // Run again - should clean directories but not affect roles/users
        $this->service->findOrCreateFolders();

        expect(Role::count())->toBeGreaterThan(0)
            ->and(User::count())->toBeGreaterThan(0)
            ->and(Storage::allFiles("{$school->id}/temp"))->toBeEmpty()
            ->and(Storage::allFiles("{$school->id}/excel"))->toBeEmpty()
            ->and(Storage::allFiles("{$school->id}/pdf"))->toBeEmpty();
    });
});

describe('edge cases and error handling', function () {
    it('handles schools without users relationship', function () {
        $school = School::factory()->create();
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        
        $this->service->checkSuperAdmins();
        
        expect(User::count())->toBe(1);
    });

    it('handles empty role array', function () {
        $this->service->createRoles([]);
        
        expect(Role::count())->toBe(0);
    });

    it('handles role names with spaces', function () {
        $roles = ['Super Admin'];
        
        $this->service->createRoles($roles);
        
        expect(Role::where('name', 'Super Admin')->exists())->toBeTrue();
    });

    it('handles concurrent directory operations', function () {
        $school = School::factory()->create();

        // Simulate multiple calls
        $this->service->findOrCreateFolders();
        Storage::put("{$school->id}/temp/file1.txt", 'content1');

        $this->service->findOrCreateFolders();
        Storage::put("{$school->id}/temp/file2.txt", 'content2');

        $this->service->findOrCreateFolders();

        expect(Storage::exists("{$school->id}/temp"))->toBeTrue()
            ->and(Storage::allFiles("{$school->id}/temp"))->toBeEmpty();
    });
});

