<?php

use App\Models\Import116;
use App\Models\Licence;
use App\Models\School;
use App\Models\SchoolLicence;
use App\Models\Schoolyear;
use App\Models\Teacher;
use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function groupRoutesAvailable(): bool
{
    return collect(app('router')->getRoutes()->getRoutes())
        ->contains(fn ($route) => $route->uri() === 'api/admin/groups');
}

beforeEach(function () {
    if (! groupRoutesAvailable()) {
        $this->markTestSkipped('Groups API is disabled in this reset state.');
    }

    collect([
        'super_admin',
        'admin',
        'materials_admin',
        'materials_moderator',
        'teacher',
        'user',
    ])->each(fn (string $role) => Role::firstOrCreate([
        'name' => $role,
        'guard_name' => 'web',
    ]));

    $this->school = School::factory()->create();
    $this->schoolyear = Schoolyear::factory()->create([
        'school_id' => $this->school->id,
    ]);

    $this->materialsAdmin = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'groups-admin@test.local',
    ]);
    $this->materialsAdmin->assignRole('materials_admin');

    $this->adminUser = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'groups-school-admin@test.local',
    ]);
    $this->adminUser->assignRole('admin');

    $materialsLicence = Licence::firstOrCreate(
        ['name' => 'Materialientool'],
        ['long_name' => 'Materialientool']
    );

    SchoolLicence::create([
        'school_id' => $this->school->id,
        'licence_id' => $materialsLicence->id,
        'valid_until' => now()->addYear(),
    ]);
});

test('groups index creates class-based school groups from import116 plus teacher exactly once', function () {
    $this->actingAs($this->materialsAdmin, 'sanctum');

    Import116::query()->create([
        'school_id' => (int) $this->school->id,
        'schoolyear_id' => (int) $this->schoolyear->id,
        'class' => '1A',
        'student_code' => '1001',
        'last_name' => 'A',
        'first_name' => 'A',
        'email' => 'a@test.local',
        'import_date' => now(),
        'import_user_id' => (int) $this->materialsAdmin->id,
    ]);
    Import116::query()->create([
        'school_id' => (int) $this->school->id,
        'schoolyear_id' => (int) $this->schoolyear->id,
        'class' => '2B',
        'student_code' => '1002',
        'last_name' => 'B',
        'first_name' => 'B',
        'email' => 'b@test.local',
        'import_date' => now(),
        'import_user_id' => (int) $this->materialsAdmin->id,
    ]);
    Import116::query()->create([
        'school_id' => (int) $this->school->id,
        'schoolyear_id' => (int) $this->schoolyear->id,
        'class' => '1A',
        'student_code' => '1003',
        'last_name' => 'C',
        'first_name' => 'C',
        'email' => 'c@test.local',
        'import_date' => now(),
        'import_user_id' => (int) $this->materialsAdmin->id,
    ]);

    expect(UserGroup::query()->count())->toBe(0);

    $this->getJson('/api/admin/groups')->assertSuccessful();
    $this->getJson('/api/admin/groups')->assertSuccessful();

    $schoolGroups = UserGroup::query()
        ->where('school_id', (int) $this->school->id)
        ->where('type', UserGroup::TYPE_SCHOOL)
        ->orderByRaw('LOWER(name)')
        ->get();

    expect($schoolGroups)->toHaveCount(3);
    expect($schoolGroups->pluck('name')->all())->toBe(['1A', '2B', 'Lehrer']);
});

test('default school groups cannot be changed or deleted', function () {
    $this->actingAs($this->adminUser, 'sanctum');

    Import116::query()->create([
        'school_id' => (int) $this->school->id,
        'schoolyear_id' => (int) $this->schoolyear->id,
        'class' => '1A',
        'student_code' => '2001',
        'last_name' => 'D',
        'first_name' => 'D',
        'email' => 'd@test.local',
        'import_date' => now(),
        'import_user_id' => (int) $this->adminUser->id,
    ]);

    $this->getJson('/api/admin/groups')->assertSuccessful();

    $classesGroup = UserGroup::query()
        ->where('school_id', (int) $this->school->id)
        ->where('type', UserGroup::TYPE_SCHOOL)
        ->where('name', '1A')
        ->firstOrFail();

    $memberCandidate = User::factory()->create([
        'school_id' => (int) $this->school->id,
        'schoolyear_id' => (int) $this->schoolyear->id,
        'email' => 'class.member@test.local',
    ]);

    $this->putJson('/api/admin/groups/'.$classesGroup->id, [
        'name' => 'Classes Updated',
        'description' => 'Not allowed',
    ])
        ->assertStatus(409)
        ->assertJsonPath('message', 'Standard-Schulgruppen können nicht geändert oder gelöscht werden.');

    $this->deleteJson('/api/admin/groups/'.$classesGroup->id)
        ->assertStatus(409)
        ->assertJsonPath('message', 'Standard-Schulgruppen können nicht geändert oder gelöscht werden.');

    $this->postJson('/api/admin/groups/'.$classesGroup->id.'/assign-users', [
        'user_ids' => [(int) $memberCandidate->id],
    ])
        ->assertStatus(409)
        ->assertJsonPath('message', 'Standard-Schulgruppen werden automatisch verwaltet und können nicht manuell bearbeitet werden.');

    $this->assertDatabaseHas('user_groups', [
        'id' => (int) $classesGroup->id,
        'school_id' => (int) $this->school->id,
        'type' => UserGroup::TYPE_SCHOOL,
        'name' => '1A',
    ]);
});

test('teacher school group syncs members from lehrerliste emails', function () {
    $this->actingAs($this->adminUser, 'sanctum');

    $teacherUserA = User::factory()->create([
        'school_id' => (int) $this->school->id,
        'schoolyear_id' => (int) $this->schoolyear->id,
        'email' => 'teacher.a@test.local',
    ]);
    $teacherUserB = User::factory()->create([
        'school_id' => (int) $this->school->id,
        'schoolyear_id' => (int) $this->schoolyear->id,
        'email' => 'teacher.b@test.local',
    ]);
    User::factory()->create([
        'school_id' => (int) $this->school->id,
        'schoolyear_id' => (int) $this->schoolyear->id,
        'email' => 'not.in.list@test.local',
    ]);

    Teacher::query()->create([
        'school_id' => (int) $this->school->id,
        'last_name' => 'Alpha',
        'first_name' => 'Teacher',
        'short' => 'TA',
        'email' => 'teacher.a@test.local',
    ]);
    Teacher::query()->create([
        'school_id' => (int) $this->school->id,
        'last_name' => 'Beta',
        'first_name' => 'Teacher',
        'short' => 'TB',
        'email' => 'TEACHER.B@test.local',
    ]);
    Teacher::query()->create([
        'school_id' => (int) $this->school->id,
        'last_name' => 'Ghost',
        'first_name' => 'Teacher',
        'short' => 'TG',
        'email' => 'no.user@test.local',
    ]);

    $this->getJson('/api/admin/groups')->assertSuccessful();

    $teacherGroup = UserGroup::query()
        ->where('school_id', (int) $this->school->id)
        ->where('type', UserGroup::TYPE_SCHOOL)
        ->where('name', 'Lehrer')
        ->firstOrFail();

    $memberIds = $teacherGroup->members()
        ->pluck('users.id')
        ->map(fn ($id) => (int) $id)
        ->sort()
        ->values()
        ->all();

    $expectedIds = collect([(int) $teacherUserA->id, (int) $teacherUserB->id])->sort()->values()->all();
    expect($memberIds)->toBe($expectedIds);
});

test('teacher school group also includes users with teacher role when lehrerliste is empty', function () {
    $this->actingAs($this->adminUser, 'sanctum');

    $teacherRoleUser = User::factory()->create([
        'school_id' => (int) $this->school->id,
        'schoolyear_id' => (int) $this->schoolyear->id,
        'email' => 'teacher.role.only@test.local',
    ]);
    $teacherRoleUser->assignRole('teacher');

    $response = $this->getJson('/api/admin/groups')->assertSuccessful();
    $groups = collect($response->json('data'));

    $teacherGroup = $groups->firstWhere('name', 'Lehrer');
    expect($teacherGroup)->not->toBeNull();
    expect((int) ($teacherGroup['members_count'] ?? 0))->toBe(1);
    expect((int) ($teacherGroup['source_users_count'] ?? 0))->toBe(0);
});

test('class school groups sync members from import116 linked users', function () {
    $this->actingAs($this->adminUser, 'sanctum');

    $importA1 = Import116::query()->create([
        'school_id' => (int) $this->school->id,
        'schoolyear_id' => (int) $this->schoolyear->id,
        'class' => '1A',
        'student_code' => '3001',
        'last_name' => 'Student',
        'first_name' => 'One',
        'email' => 'student.one@test.local',
        'import_date' => now(),
        'import_user_id' => (int) $this->adminUser->id,
    ]);
    $importA2 = Import116::query()->create([
        'school_id' => (int) $this->school->id,
        'schoolyear_id' => (int) $this->schoolyear->id,
        'class' => '1A',
        'student_code' => '3002',
        'last_name' => 'Student',
        'first_name' => 'Two',
        'email' => 'student.two@test.local',
        'import_date' => now(),
        'import_user_id' => (int) $this->adminUser->id,
    ]);
    $importB1 = Import116::query()->create([
        'school_id' => (int) $this->school->id,
        'schoolyear_id' => (int) $this->schoolyear->id,
        'class' => '2B',
        'student_code' => '3003',
        'last_name' => 'Student',
        'first_name' => 'Three',
        'email' => 'student.three@test.local',
        'import_date' => now(),
        'import_user_id' => (int) $this->adminUser->id,
    ]);

    $studentUserA = User::factory()->create([
        'school_id' => (int) $this->school->id,
        'schoolyear_id' => (int) $this->schoolyear->id,
        'email' => 'student.one@test.local',
        'import116_id' => (int) $importA1->id,
    ]);
    $studentUserB = User::factory()->create([
        'school_id' => (int) $this->school->id,
        'schoolyear_id' => (int) $this->schoolyear->id,
        'email' => 'student.two@test.local',
        'import116_id' => (int) $importA2->id,
    ]);
    $studentUserOtherClass = User::factory()->create([
        'school_id' => (int) $this->school->id,
        'schoolyear_id' => (int) $this->schoolyear->id,
        'email' => 'student.three@test.local',
        'import116_id' => (int) $importB1->id,
    ]);
    User::factory()->create([
        'school_id' => (int) $this->school->id,
        'schoolyear_id' => (int) $this->schoolyear->id,
        'email' => 'student.unlinked@test.local',
    ]);

    $this->getJson('/api/admin/groups')->assertSuccessful();

    $group1A = UserGroup::query()
        ->where('school_id', (int) $this->school->id)
        ->where('type', UserGroup::TYPE_SCHOOL)
        ->where('name', '1A')
        ->firstOrFail();

    $memberIds1A = $group1A->members()
        ->pluck('users.id')
        ->map(fn ($id) => (int) $id)
        ->sort()
        ->values()
        ->all();

    $expectedIds1A = collect([(int) $studentUserA->id, (int) $studentUserB->id])->sort()->values()->all();
    expect($memberIds1A)->toBe($expectedIds1A);
    expect($memberIds1A)->not->toContain((int) $studentUserOtherClass->id);
});

test('groups index returns assigned count and users-file count for school groups', function () {
    $this->actingAs($this->adminUser, 'sanctum');

    $importA1 = Import116::query()->create([
        'school_id' => (int) $this->school->id,
        'schoolyear_id' => (int) $this->schoolyear->id,
        'class' => '1A',
        'student_code' => '4001',
        'last_name' => 'Class',
        'first_name' => 'One',
        'email' => 'class.one@test.local',
        'import_date' => now(),
        'import_user_id' => (int) $this->adminUser->id,
    ]);
    Import116::query()->create([
        'school_id' => (int) $this->school->id,
        'schoolyear_id' => (int) $this->schoolyear->id,
        'class' => '1A',
        'student_code' => '4002',
        'last_name' => 'Class',
        'first_name' => 'Two',
        'email' => 'class.two@test.local',
        'import_date' => now(),
        'import_user_id' => (int) $this->adminUser->id,
    ]);
    Import116::query()->create([
        'school_id' => (int) $this->school->id,
        'schoolyear_id' => (int) $this->schoolyear->id,
        'class' => '2B',
        'student_code' => '4003',
        'last_name' => 'Class',
        'first_name' => 'Three',
        'email' => 'class.three@test.local',
        'import_date' => now(),
        'import_user_id' => (int) $this->adminUser->id,
    ]);

    User::factory()->create([
        'school_id' => (int) $this->school->id,
        'schoolyear_id' => (int) $this->schoolyear->id,
        'email' => 'class.one@test.local',
        'import116_id' => (int) $importA1->id,
    ]);

    User::factory()->create([
        'school_id' => (int) $this->school->id,
        'schoolyear_id' => (int) $this->schoolyear->id,
        'email' => 'teacher.match@test.local',
    ]);

    Teacher::query()->create([
        'school_id' => (int) $this->school->id,
        'last_name' => 'Match',
        'first_name' => 'Teacher',
        'short' => 'TM',
        'email' => 'teacher.match@test.local',
    ]);
    Teacher::query()->create([
        'school_id' => (int) $this->school->id,
        'last_name' => 'Ghost',
        'first_name' => 'Teacher',
        'short' => 'TG',
        'email' => 'teacher.ghost@test.local',
    ]);

    $response = $this->getJson('/api/admin/groups')->assertSuccessful();
    $groups = collect($response->json('data'));

    $group1A = $groups->firstWhere('name', '1A');
    expect($group1A)->not->toBeNull();
    expect($group1A['members_count'])->toBe(1);
    expect($group1A['source_users_count'])->toBe(2);

    $teacherGroup = $groups->firstWhere('name', 'Lehrer');
    expect($teacherGroup)->not->toBeNull();
    expect($teacherGroup['members_count'])->toBe(1);
    expect($teacherGroup['source_users_count'])->toBe(2);
});

test('groups index creates and syncs combined class group for recognizable class variants', function () {
    $this->actingAs($this->adminUser, 'sanctum');

    $import6AM1 = Import116::query()->create([
        'school_id' => (int) $this->school->id,
        'schoolyear_id' => (int) $this->schoolyear->id,
        'class' => '6A-M',
        'student_code' => '5001',
        'last_name' => 'Variant',
        'first_name' => 'One',
        'email' => 'variant.one@test.local',
        'import_date' => now(),
        'import_user_id' => (int) $this->adminUser->id,
    ]);
    $import6AM2 = Import116::query()->create([
        'school_id' => (int) $this->school->id,
        'schoolyear_id' => (int) $this->schoolyear->id,
        'class' => '6A-M',
        'student_code' => '5002',
        'last_name' => 'Variant',
        'first_name' => 'Two',
        'email' => 'variant.two@test.local',
        'import_date' => now(),
        'import_user_id' => (int) $this->adminUser->id,
    ]);
    $import6AR1 = Import116::query()->create([
        'school_id' => (int) $this->school->id,
        'schoolyear_id' => (int) $this->schoolyear->id,
        'class' => '6A-R',
        'student_code' => '5003',
        'last_name' => 'Variant',
        'first_name' => 'Three',
        'email' => 'variant.three@test.local',
        'import_date' => now(),
        'import_user_id' => (int) $this->adminUser->id,
    ]);

    $user1 = User::factory()->create([
        'school_id' => (int) $this->school->id,
        'schoolyear_id' => (int) $this->schoolyear->id,
        'email' => 'variant.one@test.local',
        'import116_id' => (int) $import6AM1->id,
    ]);
    $user2 = User::factory()->create([
        'school_id' => (int) $this->school->id,
        'schoolyear_id' => (int) $this->schoolyear->id,
        'email' => 'variant.two@test.local',
        'import116_id' => (int) $import6AM2->id,
    ]);
    $user3 = User::factory()->create([
        'school_id' => (int) $this->school->id,
        'schoolyear_id' => (int) $this->schoolyear->id,
        'email' => 'variant.three@test.local',
        'import116_id' => (int) $import6AR1->id,
    ]);

    $response = $this->getJson('/api/admin/groups')->assertSuccessful();
    $groups = collect($response->json('data'));

    expect($groups->pluck('name')->all())->toContain('6A', '6A-M', '6A-R', 'Lehrer');

    $group6A = $groups->firstWhere('name', '6A');
    expect($group6A)->not->toBeNull();
    expect($group6A['source_users_count'])->toBe(3);
    expect($group6A['can_edit'])->toBeFalse();
    expect($group6A['can_manage_members'])->toBeFalse();

    $combinedGroup = UserGroup::query()
        ->where('school_id', (int) $this->school->id)
        ->where('type', UserGroup::TYPE_SCHOOL)
        ->where('name', '6A')
        ->firstOrFail();

    $memberIds = $combinedGroup->members()
        ->pluck('users.id')
        ->map(fn ($id) => (int) $id)
        ->sort()
        ->values()
        ->all();

    $expectedIds = collect([(int) $user1->id, (int) $user2->id, (int) $user3->id])->sort()->values()->all();
    expect($memberIds)->toBe($expectedIds);
});

test('groups index repairs missing import116 user link by email and assigns class group', function () {
    $this->actingAs($this->adminUser, 'sanctum');

    $importRow = Import116::query()->create([
        'school_id' => (int) $this->school->id,
        'schoolyear_id' => (int) $this->schoolyear->id,
        'class' => '2B',
        'student_code' => '6001',
        'last_name' => 'Repair',
        'first_name' => 'Link',
        'email' => 'repair.link@test.local',
        'import_date' => now(),
        'import_user_id' => (int) $this->adminUser->id,
    ]);

    $unlinkedUser = User::factory()->create([
        'school_id' => (int) $this->school->id,
        'schoolyear_id' => (int) $this->schoolyear->id,
        'email' => 'repair.link@test.local',
        'import116_id' => null,
    ]);

    $this->getJson('/api/admin/groups')->assertSuccessful();

    expect((int) ($unlinkedUser->fresh()->import116_id ?? 0))->toBe((int) $importRow->id);
    expect((int) (Import116::query()->findOrFail((int) $importRow->id)->user_id ?? 0))->toBe((int) $unlinkedUser->id);

    $group2B = UserGroup::query()
        ->where('school_id', (int) $this->school->id)
        ->where('type', UserGroup::TYPE_SCHOOL)
        ->where('name', '2B')
        ->firstOrFail();

    $memberIds = $group2B->members()
        ->pluck('users.id')
        ->map(fn ($id) => (int) $id)
        ->all();

    expect($memberIds)->toContain((int) $unlinkedUser->id);
});
