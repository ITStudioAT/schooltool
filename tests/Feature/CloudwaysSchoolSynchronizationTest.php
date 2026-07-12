<?php

use App\Models\School;
use App\Models\Schoolyear;
use App\Models\User;
use App\Services\CloudwaysSchoolSynchronizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->school = School::factory()->create([
        'long_name' => 'Cloudways Testschule',
        'short_name' => 'CTS',
    ]);
    $schoolyear = Schoolyear::factory()->create(['school_id' => $this->school->id]);

    $superAdminRole = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

    $this->superAdmin = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $schoolyear->id,
    ]);
    $this->superAdmin->assignRole($superAdminRole);

    $this->admin = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $schoolyear->id,
    ]);
    $this->admin->assignRole($adminRole);
});

test('guest cannot preview a Cloudways school synchronization', function () {
    $this->postJson("/api/admin/schools/{$this->school->id}/cloudways-sync/preview")
        ->assertUnauthorized();
});

test('non super admin cannot preview a Cloudways school synchronization', function () {
    $this->actingAs($this->admin, 'sanctum')
        ->postJson("/api/admin/schools/{$this->school->id}/cloudways-sync/preview")
        ->assertForbidden();
});

test('super admin can preview a Cloudways school synchronization', function () {
    $preview = [
        'school' => ['id' => $this->school->id, 'long_name' => $this->school->long_name, 'short_name' => $this->school->short_name],
        'tables' => 4,
        'rows' => 25,
        'counts' => ['schools' => 1, 'users' => 24],
        'excluded_tables' => ['sessions'],
        'files_included' => false,
    ];

    $this->mock(CloudwaysSchoolSynchronizationService::class)
        ->shouldReceive('preview')
        ->once()
        ->withArgs(fn (School $school): bool => $school->is($this->school))
        ->andReturn($preview);

    $this->actingAs($this->superAdmin, 'sanctum')
        ->postJson("/api/admin/schools/{$this->school->id}/cloudways-sync/preview")
        ->assertSuccessful()
        ->assertJsonPath('data.rows', 25)
        ->assertJsonPath('data.files_included', false);
});

test('synchronization requires the exact school name as confirmation', function () {
    $this->actingAs($this->superAdmin, 'sanctum')
        ->postJson("/api/admin/schools/{$this->school->id}/cloudways-sync", [
            'confirmation' => 'wrong school',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('confirmation');
});

test('super admin can start an exact Cloudways school synchronization', function () {
    $result = [
        'backup_path' => 'cloudways-school-sync-backups/1/backup.enc',
        'tables' => 8,
        'rows' => 120,
        'counts' => ['schools' => 1, 'users' => 119],
        'files_included' => false,
    ];

    $this->mock(CloudwaysSchoolSynchronizationService::class)
        ->shouldReceive('synchronize')
        ->once()
        ->withArgs(fn (School $school, User $actor): bool => $school->is($this->school) && $actor->is($this->superAdmin))
        ->andReturn($result);

    $this->actingAs($this->superAdmin, 'sanctum')
        ->postJson("/api/admin/schools/{$this->school->id}/cloudways-sync", [
            'confirmation' => $this->school->long_name,
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.rows', 120)
        ->assertJsonPath('data.files_included', false);
});

test('service refuses an incomplete Cloudways database configuration before connecting', function () {
    config([
        'database.connections.cloudways.host' => null,
        'database.connections.cloudways.database' => null,
        'database.connections.cloudways.username' => null,
        'database.connections.cloudways.password' => null,
    ]);

    expect(fn () => app(CloudwaysSchoolSynchronizationService::class)->preview($this->school))
        ->toThrow(HttpException::class, 'nicht vollständig konfiguriert');
});

test('service refuses to synchronize when source and target identify the same database', function () {
    $defaultConnection = config('database.default');
    $targetConfiguration = config("database.connections.{$defaultConnection}");
    $targetConfiguration['username'] = $targetConfiguration['username'] ?: 'testing';
    $targetConfiguration['password'] = $targetConfiguration['password'] ?: 'testing';
    config(['database.connections.cloudways' => $targetConfiguration]);
    DB::purge('cloudways');

    expect(fn () => app(CloudwaysSchoolSynchronizationService::class)->preview($this->school))
        ->toThrow(HttpException::class, 'dürfen nicht identisch sein');
});

test('shared reference comparison ignores timezone differences in timestamps', function () {
    $method = new ReflectionMethod(CloudwaysSchoolSynchronizationService::class, 'normalizeSharedReferenceRow');
    $service = app(CloudwaysSchoolSynchronizationService::class);

    $cloudwaysRole = [
        'id' => 1,
        'name' => 'super_admin',
        'guard_name' => 'web',
        'is_admin' => 0,
        'created_at' => '2025-11-03 23:32:20',
        'updated_at' => '2025-11-03 23:32:20',
    ];
    $localRole = [
        'updated_at' => '2025-11-04 00:32:20',
        'created_at' => '2025-11-04 00:32:20',
        'is_admin' => 0,
        'guard_name' => 'web',
        'name' => 'super_admin',
        'id' => 1,
    ];

    expect($method->invoke($service, $cloudwaysRole))
        ->toBe($method->invoke($service, $localRole))
        ->not->toBe($method->invoke($service, [...$localRole, 'is_admin' => 1]));
});
