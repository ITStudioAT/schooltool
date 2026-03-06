<?php

use App\Models\Import116;
use App\Models\Licence;
use App\Models\School;
use App\Models\SchoolLicence;
use App\Models\SchoolTool;
use App\Models\Schoolyear;
use App\Models\Teacher;
use App\Models\TeachingCourse;
use App\Models\TeachingCourseStudent;
use App\Models\User;
use App\Models\UserGroup;
use App\Models\UserGroupMember;
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
    SchoolTool::factory()->create([
        'school_id' => $this->school->id,
        'active_schoolyear_id' => $this->schoolyear->id,
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

test('groups index creates class-based school groups with parent groups plus teacher and all-school members exactly once', function () {
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

    expect($schoolGroups)->toHaveCount(6);
    expect($schoolGroups->pluck('name')->all())->toBe(['1A', '1A Eltern', '2B', '2B Eltern', 'Alle Schulmitglieder', 'Lehrer']);
});

test('school groups only consider import116 rows from the active schoolyear', function () {
    $this->actingAs($this->materialsAdmin, 'sanctum');

    $otherSchoolyear = Schoolyear::factory()->create([
        'school_id' => $this->school->id,
    ]);

    $activeImport = Import116::query()->create([
        'school_id' => (int) $this->school->id,
        'schoolyear_id' => (int) $this->schoolyear->id,
        'class' => '1A',
        'student_code' => '1011',
        'last_name' => 'Aktiv',
        'first_name' => 'Schuljahr',
        'email' => 'active.schoolyear@student.local',
        'mother_name' => 'Mutter Aktiv',
        'mother_email' => 'mother.active.schoolyear@test.local',
        'import_date' => now(),
        'import_user_id' => (int) $this->materialsAdmin->id,
    ]);
    Import116::query()->create([
        'school_id' => (int) $this->school->id,
        'schoolyear_id' => (int) $otherSchoolyear->id,
        'class' => '9Z',
        'student_code' => '1012',
        'last_name' => 'Alt',
        'first_name' => 'Schuljahr',
        'email' => 'other.schoolyear@student.local',
        'mother_name' => 'Mutter Alt',
        'mother_email' => 'mother.other.schoolyear@test.local',
        'import_date' => now(),
        'import_user_id' => (int) $this->materialsAdmin->id,
    ]);

    User::factory()->create([
        'school_id' => (int) $this->school->id,
        'schoolyear_id' => (int) $this->schoolyear->id,
        'email' => 'active.schoolyear@student.local',
        'import116_id' => (int) $activeImport->id,
    ]);

    $response = $this->getJson('/api/admin/groups')->assertSuccessful();
    $groups = collect($response->json('data'));

    expect($groups->pluck('name')->all())->toContain('1A', '1A Eltern', 'Alle Schulmitglieder', 'Lehrer');
    expect($groups->pluck('name')->all())->not->toContain('9Z', '9Z Eltern');

    $allSchoolMembersGroup = $groups->firstWhere('name', 'Alle Schulmitglieder');
    expect($allSchoolMembersGroup)->not->toBeNull();
    expect((int) ($allSchoolMembersGroup['members_count'] ?? 0))->toBe(4);
    expect((int) ($allSchoolMembersGroup['source_users_count'] ?? 0))->toBe(4);

    $allMembersResponse = $this->getJson('/api/admin/groups/'.(int) $allSchoolMembersGroup['id'].'/source-members')
        ->assertSuccessful();

    $allMembers = collect($allMembersResponse->json('data'));
    expect($allMembers->pluck('name')->all())->toContain('Aktiv Schuljahr', 'Mutter Aktiv');
    expect($allMembers->pluck('name')->all())->not->toContain('Alt Schuljahr', 'Mutter Alt');
});

test('active schoolyear import rows still use matching users regardless of the user schoolyear', function () {
    $this->actingAs($this->materialsAdmin, 'sanctum');

    $otherSchoolyear = Schoolyear::factory()->create([
        'school_id' => $this->school->id,
    ]);

    $activeImport = Import116::query()->create([
        'school_id' => (int) $this->school->id,
        'schoolyear_id' => (int) $this->schoolyear->id,
        'class' => '1A',
        'student_code' => '1014',
        'last_name' => 'Quer',
        'first_name' => 'Schuljahr',
        'email' => 'cross.schoolyear@student.local',
        'import_date' => now(),
        'import_user_id' => (int) $this->materialsAdmin->id,
    ]);

    $crossSchoolyearUser = User::factory()->create([
        'school_id' => (int) $this->school->id,
        'schoolyear_id' => (int) $otherSchoolyear->id,
        'email' => 'cross.schoolyear@student.local',
        'import116_id' => null,
    ]);

    $response = $this->getJson('/api/admin/groups')->assertSuccessful();
    $groups = collect($response->json('data'));

    $classGroup = $groups->firstWhere('name', '1A');
    expect($classGroup)->not->toBeNull();
    expect((int) ($classGroup['members_count'] ?? 0))->toBe(1);
    expect((int) ($classGroup['source_users_count'] ?? 0))->toBe(1);

    $groupModel = UserGroup::query()
        ->where('school_id', (int) $this->school->id)
        ->where('type', UserGroup::TYPE_SCHOOL)
        ->where('name', '1A')
        ->firstOrFail();

    $memberIds = $groupModel->members()
        ->pluck('users.id')
        ->map(fn ($id) => (int) $id)
        ->all();

    expect($memberIds)->toContain((int) $crossSchoolyearUser->id);
    expect((int) ($crossSchoolyearUser->fresh()->import116_id ?? 0))->toBe((int) $activeImport->id);
});

test('groups index stays successful when old automatic parent groups exist after changing the active schoolyear', function () {
    $this->actingAs($this->materialsAdmin, 'sanctum');

    Import116::query()->create([
        'school_id' => (int) $this->school->id,
        'schoolyear_id' => (int) $this->schoolyear->id,
        'class' => '1A',
        'student_code' => '1013',
        'last_name' => 'Vorjahr',
        'first_name' => 'Kind',
        'email' => 'old.parent.group@student.local',
        'mother_name' => 'Mutter Vorjahr',
        'mother_email' => 'mother.old.parent.group@test.local',
        'import_date' => now(),
        'import_user_id' => (int) $this->materialsAdmin->id,
    ]);

    $this->getJson('/api/admin/groups')->assertSuccessful();

    $oldParentGroup = UserGroup::query()
        ->where('school_id', (int) $this->school->id)
        ->where('type', UserGroup::TYPE_SCHOOL)
        ->where('name', '1A Eltern')
        ->firstOrFail();

    $newSchoolyear = Schoolyear::factory()->create([
        'school_id' => $this->school->id,
    ]);

    SchoolTool::query()
        ->where('school_id', (int) $this->school->id)
        ->update(['active_schoolyear_id' => (int) $newSchoolyear->id]);

    $response = $this->getJson('/api/admin/groups')->assertSuccessful();
    $groups = collect($response->json('data'));

    $parentGroupPayload = $groups->firstWhere('id', (int) $oldParentGroup->id);
    expect($parentGroupPayload)->not->toBeNull();
    expect((int) ($parentGroupPayload['members_count'] ?? -1))->toBe(0);
    expect((int) ($parentGroupPayload['source_users_count'] ?? -1))->toBe(0);
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

test('manual own groups can be deleted even when they still have members', function () {
    $this->actingAs($this->adminUser, 'sanctum');

    $member = User::factory()->create([
        'school_id' => (int) $this->school->id,
        'schoolyear_id' => (int) $this->schoolyear->id,
        'email' => 'own.group.member@test.local',
    ]);

    $group = UserGroup::query()->create([
        'school_id' => (int) $this->school->id,
        'type' => UserGroup::TYPE_OWN,
        'name' => 'Freie Eigene Gruppe',
        'description' => null,
        'created_by_user_id' => (int) $this->adminUser->id,
    ]);

    $group->groupMembers()->create([
        'school_id' => (int) $this->school->id,
        'member_provider' => UserGroupMember::PROVIDER_USER,
        'member_ref' => 'user:'.$member->id,
        'linked_user_id' => (int) $member->id,
        'display_name' => (string) trim((string) (($member->last_name ?? '').' '.($member->first_name ?? ''))),
        'display_email' => $member->email,
        'member_type_label' => 'Benutzer',
        'source_status' => UserGroupMember::SOURCE_STATUS_ACTIVE,
        'linked_user_status' => UserGroupMember::LINKED_USER_STATUS_LINKED,
        'added_by_user_id' => (int) $this->adminUser->id,
    ]);

    $response = $this->getJson('/api/admin/groups')->assertSuccessful();
    $groupPayload = collect($response->json('data'))->firstWhere('id', (int) $group->id);

    expect($groupPayload)->not->toBeNull();
    expect((int) ($groupPayload['members_count'] ?? 0))->toBe(1);
    expect((bool) ($groupPayload['can_delete'] ?? false))->toBeTrue();

    $this->deleteJson('/api/admin/groups/'.(int) $group->id)
        ->assertSuccessful()
        ->assertJsonPath('message', 'Gruppe gelöscht.');

    $this->assertDatabaseMissing('user_groups', [
        'id' => (int) $group->id,
    ]);
    $this->assertDatabaseMissing('user_group_members', [
        'user_group_id' => (int) $group->id,
        'linked_user_id' => (int) $member->id,
        'member_provider' => UserGroupMember::PROVIDER_USER,
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

    $sourceMembersResponse = $this->getJson('/api/admin/groups/'.(int) $teacherGroup->id.'/source-members')
        ->assertSuccessful();

    $rows = collect($sourceMembersResponse->json('data'));
    expect($rows)->toHaveCount(3);
    expect($rows->firstWhere('email', 'teacher.a@test.local'))->not->toBeNull();
    expect($rows->firstWhere('email', 'TEACHER.B@test.local'))->not->toBeNull();

    $ghostTeacher = $rows->firstWhere('email', 'no.user@test.local');
    expect($ghostTeacher)->not->toBeNull();
    expect((bool) ($ghostTeacher['has_user_account'] ?? true))->toBeFalse();
    expect((bool) ($ghostTeacher['already_member'] ?? false))->toBeTrue();
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
    expect((int) ($teacherGroup['source_users_count'] ?? 0))->toBe(1);

    $teacherGroupModel = UserGroup::query()
        ->where('school_id', (int) $this->school->id)
        ->where('type', UserGroup::TYPE_SCHOOL)
        ->where('name', 'Lehrer')
        ->firstOrFail();

    $sourceMembersResponse = $this->getJson('/api/admin/groups/'.(int) $teacherGroupModel->id.'/source-members')
        ->assertSuccessful();

    $rows = collect($sourceMembersResponse->json('data'));
    expect($rows)->toHaveCount(1);

    $roleEntry = $rows->firstWhere('user_id', (int) $teacherRoleUser->id);
    expect($roleEntry)->not->toBeNull();
    expect((bool) ($roleEntry['has_user_account'] ?? false))->toBeTrue();
    expect((bool) ($roleEntry['already_member'] ?? false))->toBeTrue();
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

    expect($groups->pluck('name')->all())->toContain('6A', '6A Eltern', '6A-M', '6A-M Eltern', '6A-R', '6A-R Eltern', 'Lehrer');

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

test('source members endpoint returns all import116 users for selected class group', function () {
    $this->actingAs($this->adminUser, 'sanctum');

    $importOne = Import116::query()->create([
        'school_id' => (int) $this->school->id,
        'schoolyear_id' => (int) $this->schoolyear->id,
        'class' => '2B',
        'student_code' => '7001',
        'last_name' => 'Source',
        'first_name' => 'One',
        'email' => 'source.one@test.local',
        'import_date' => now(),
        'import_user_id' => (int) $this->adminUser->id,
    ]);
    Import116::query()->create([
        'school_id' => (int) $this->school->id,
        'schoolyear_id' => (int) $this->schoolyear->id,
        'class' => '2B',
        'student_code' => '7002',
        'last_name' => 'Source',
        'first_name' => 'Two',
        'email' => 'source.two@test.local',
        'import_date' => now(),
        'import_user_id' => (int) $this->adminUser->id,
    ]);

    $linkedUser = User::factory()->create([
        'school_id' => (int) $this->school->id,
        'schoolyear_id' => (int) $this->schoolyear->id,
        'email' => 'source.one@test.local',
        'import116_id' => (int) $importOne->id,
    ]);

    $groupsResponse = $this->getJson('/api/admin/groups')->assertSuccessful();
    $groups = collect($groupsResponse->json('data'));
    $group2B = $groups->firstWhere('name', '2B');
    expect($group2B)->not->toBeNull();

    $sourceMembersResponse = $this->getJson('/api/admin/groups/'.(int) $group2B['id'].'/source-members')
        ->assertSuccessful();

    $rows = collect($sourceMembersResponse->json('data'));
    expect($rows)->toHaveCount(2);

    $firstLinked = $rows->firstWhere('import116_id', (int) $importOne->id);
    expect($firstLinked)->not->toBeNull();
    expect((int) ($firstLinked['user_id'] ?? 0))->toBe((int) $linkedUser->id);
    expect((bool) ($firstLinked['has_user_account'] ?? false))->toBeTrue();
    expect((bool) ($firstLinked['already_member'] ?? false))->toBeTrue();
});

test('parent school groups use child registration status and expose registered versus all parent contacts', function () {
    $this->actingAs($this->adminUser, 'sanctum');

    $registeredStudent = Import116::query()->create([
        'school_id' => (int) $this->school->id,
        'schoolyear_id' => (int) $this->schoolyear->id,
        'class' => '1A',
        'student_code' => '7101',
        'last_name' => 'Kind',
        'first_name' => 'Registriert',
        'email' => 'registered.child@test.local',
        'mother_name' => 'Mutter Registriert',
        'mother_email' => 'registered.mother@test.local',
        'mother_phone_1' => '0664 1000001',
        'father_name' => 'Vater Registriert',
        'father_email' => 'registered.father@test.local',
        'father_phone_1' => '0664 1000002',
        'import_date' => now(),
        'import_user_id' => (int) $this->adminUser->id,
    ]);
    Import116::query()->create([
        'school_id' => (int) $this->school->id,
        'schoolyear_id' => (int) $this->schoolyear->id,
        'class' => '1A',
        'student_code' => '7102',
        'last_name' => 'Kind',
        'first_name' => 'Importiert',
        'email' => 'imported.child@test.local',
        'mother_name' => 'Mutter Importiert',
        'mother_email' => 'imported.mother@test.local',
        'mother_phone_1' => '0664 1000003',
        'import_date' => now(),
        'import_user_id' => (int) $this->adminUser->id,
    ]);

    User::factory()->create([
        'school_id' => (int) $this->school->id,
        'schoolyear_id' => (int) $this->schoolyear->id,
        'email' => 'registered.child@test.local',
        'import116_id' => (int) $registeredStudent->id,
    ]);

    $groupsResponse = $this->getJson('/api/admin/groups')->assertSuccessful();
    $groups = collect($groupsResponse->json('data'));

    $parentGroup = $groups->firstWhere('name', '1A Eltern');
    expect($parentGroup)->not->toBeNull();
    expect((bool) ($parentGroup['is_parent_group'] ?? false))->toBeTrue();
    expect((bool) ($parentGroup['can_edit'] ?? true))->toBeFalse();
    expect((bool) ($parentGroup['can_manage_members'] ?? true))->toBeFalse();
    expect((int) ($parentGroup['members_count'] ?? 0))->toBe(2);
    expect((int) ($parentGroup['source_users_count'] ?? 0))->toBe(3);

    $registeredParentsResponse = $this->getJson('/api/admin/groups/'.(int) $parentGroup['id'].'/members')
        ->assertSuccessful();

    $registeredParents = collect($registeredParentsResponse->json('data'));
    expect($registeredParents)->toHaveCount(2);
    expect($registeredParents->pluck('name')->all())->toContain('Mutter Registriert', 'Vater Registriert');
    expect($registeredParents->pluck('name')->all())->not->toContain('Mutter Importiert');
    expect((string) ($registeredParents->firstWhere('name', 'Mutter Registriert')['children_label'] ?? ''))->toBe('Kind Registriert');

    $allParentsResponse = $this->getJson('/api/admin/groups/'.(int) $parentGroup['id'].'/source-members')
        ->assertSuccessful();

    $allParents = collect($allParentsResponse->json('data'));
    expect($allParents)->toHaveCount(3);
    expect($allParents->pluck('name')->all())->toContain('Mutter Registriert', 'Vater Registriert', 'Mutter Importiert');
    expect((string) ($allParents->firstWhere('name', 'Mutter Importiert')['children_label'] ?? ''))->toBe('Kind Importiert');
});

test('all school members group combines students parents teachers and admins', function () {
    $this->actingAs($this->adminUser, 'sanctum');

    $registeredStudent = Import116::query()->create([
        'school_id' => (int) $this->school->id,
        'schoolyear_id' => (int) $this->schoolyear->id,
        'class' => '1A',
        'student_code' => '7301',
        'last_name' => 'Schueler',
        'first_name' => 'Registriert',
        'email' => 'school.member.student.registered@test.local',
        'mother_name' => 'Mutter Registriert',
        'mother_email' => 'school.member.mother.registered@test.local',
        'father_name' => 'Vater Registriert',
        'father_email' => 'school.member.father.registered@test.local',
        'import_date' => now(),
        'import_user_id' => (int) $this->adminUser->id,
    ]);
    $importOnlyStudent = Import116::query()->create([
        'school_id' => (int) $this->school->id,
        'schoolyear_id' => (int) $this->schoolyear->id,
        'class' => '2B',
        'student_code' => '7302',
        'last_name' => 'Schueler',
        'first_name' => 'Importiert',
        'email' => 'school.member.student.import@test.local',
        'mother_name' => 'Mutter Importiert',
        'mother_email' => 'school.member.mother.import@test.local',
        'import_date' => now(),
        'import_user_id' => (int) $this->adminUser->id,
    ]);

    $studentUser = User::factory()->create([
        'school_id' => (int) $this->school->id,
        'schoolyear_id' => (int) $this->schoolyear->id,
        'email' => 'school.member.student.registered@test.local',
        'import116_id' => (int) $registeredStudent->id,
    ]);

    $teacherUser = User::factory()->create([
        'school_id' => (int) $this->school->id,
        'schoolyear_id' => (int) $this->schoolyear->id,
        'email' => 'school.member.teacher@test.local',
    ]);
    $teacherUser->assignRole('teacher');

    $materialsModerator = User::factory()->create([
        'school_id' => (int) $this->school->id,
        'schoolyear_id' => (int) $this->schoolyear->id,
        'email' => 'school.member.materials-moderator@test.local',
    ]);
    $materialsModerator->assignRole('materials_moderator');

    $superAdmin = User::factory()->create([
        'school_id' => (int) $this->school->id,
        'schoolyear_id' => (int) $this->schoolyear->id,
        'email' => 'school.member.super-admin@test.local',
    ]);
    $superAdmin->assignRole('super_admin');

    Teacher::query()->create([
        'school_id' => (int) $this->school->id,
        'last_name' => 'Lehrer',
        'first_name' => 'Mit User',
        'short' => 'LMU',
        'email' => 'school.member.teacher@test.local',
    ]);
    Teacher::query()->create([
        'school_id' => (int) $this->school->id,
        'last_name' => 'Lehrer',
        'first_name' => 'Ohne User',
        'short' => 'LOU',
        'email' => 'school.member.teacher.import@test.local',
    ]);

    $groupsResponse = $this->getJson('/api/admin/groups')->assertSuccessful();
    $groups = collect($groupsResponse->json('data'));

    $allSchoolMembersGroup = $groups->firstWhere('name', 'Alle Schulmitglieder');
    expect($allSchoolMembersGroup)->not->toBeNull();
    expect((bool) ($allSchoolMembersGroup['is_system_default'] ?? false))->toBeTrue();
    expect((bool) ($allSchoolMembersGroup['is_all_school_members_group'] ?? false))->toBeTrue();
    expect((int) ($allSchoolMembersGroup['members_count'] ?? 0))->toBe(8);
    expect((int) ($allSchoolMembersGroup['source_users_count'] ?? 0))->toBe(11);

    $registeredMembersResponse = $this->getJson('/api/admin/groups/'.(int) $allSchoolMembersGroup['id'].'/members')
        ->assertSuccessful();

    $registeredMembers = collect($registeredMembersResponse->json('data'));
    expect($registeredMembers)->toHaveCount(8);
    expect($registeredMembers->pluck('name')->all())->toContain(
        (string) trim((string) (($studentUser->last_name ?? '').' '.($studentUser->first_name ?? ''))),
        'Mutter Registriert',
        'Vater Registriert'
    );
    expect($registeredMembers->pluck('name')->all())->toContain(
        (string) trim((string) (($teacherUser->last_name ?? '').' '.($teacherUser->first_name ?? ''))),
        (string) trim((string) (($this->adminUser->last_name ?? '').' '.($this->adminUser->first_name ?? ''))),
        (string) trim((string) (($this->materialsAdmin->last_name ?? '').' '.($this->materialsAdmin->first_name ?? ''))),
        (string) trim((string) (($materialsModerator->last_name ?? '').' '.($materialsModerator->first_name ?? ''))),
        (string) trim((string) (($superAdmin->last_name ?? '').' '.($superAdmin->first_name ?? '')))
    );
    expect($registeredMembers->pluck('name')->all())->not->toContain('Mutter Importiert', 'Lehrer Ohne User', 'Schueler Importiert');
    expect((string) ($registeredMembers->firstWhere('name', 'Mutter Registriert')['member_type_label'] ?? ''))->toBe('Eltern');

    $allMembersResponse = $this->getJson('/api/admin/groups/'.(int) $allSchoolMembersGroup['id'].'/source-members')
        ->assertSuccessful();

    $allMembers = collect($allMembersResponse->json('data'));
    expect($allMembers)->toHaveCount(11);
    expect($allMembers->pluck('name')->all())->toContain('Mutter Importiert', 'Lehrer Ohne User');
    expect((string) ($allMembers->firstWhere('name', 'Mutter Importiert')['member_type_label'] ?? ''))->toBe('Eltern');
    expect((string) ($allMembers->firstWhere('email', 'school.member.teacher.import@test.local')['member_type_label'] ?? ''))->toBe('Lehrer:in');
    expect((string) ($allMembers->firstWhere('email', 'school.member.materials-moderator@test.local')['member_type_label'] ?? ''))->toBe('Admin');
    expect((string) ($allMembers->firstWhere('import116_id', (int) $importOnlyStudent->id)['member_type_label'] ?? ''))->toBe('Schüler:in');
});

test('groups index creates and syncs automatic own groups for my teaching courses', function () {
    $this->actingAs($this->adminUser, 'sanctum');

    $registeredStudentOneImport = Import116::query()->create([
        'school_id' => (int) $this->school->id,
        'schoolyear_id' => (int) $this->schoolyear->id,
        'class' => '1A',
        'student_code' => '8101',
        'last_name' => 'Course',
        'first_name' => 'Student One',
        'email' => 'course.student.one@test.local',
        'mother_name' => 'Mutter One',
        'mother_email' => 'mother.one@test.local',
        'father_name' => 'Vater One',
        'father_email' => 'father.one@test.local',
        'import_date' => now(),
        'import_user_id' => (int) $this->adminUser->id,
    ]);
    $registeredStudentTwoImport = Import116::query()->create([
        'school_id' => (int) $this->school->id,
        'schoolyear_id' => (int) $this->schoolyear->id,
        'class' => '2B',
        'student_code' => '8102',
        'last_name' => 'Course',
        'first_name' => 'Student Two',
        'email' => 'course.student.two@test.local',
        'mother_name' => 'Mutter Two',
        'mother_email' => 'mother.two@test.local',
        'import_date' => now(),
        'import_user_id' => (int) $this->adminUser->id,
    ]);

    $studentOne = User::factory()->create([
        'school_id' => (int) $this->school->id,
        'schoolyear_id' => (int) $this->schoolyear->id,
        'email' => 'course.student.one@test.local',
        'import116_id' => (int) $registeredStudentOneImport->id,
    ]);
    $studentTwo = User::factory()->create([
        'school_id' => (int) $this->school->id,
        'schoolyear_id' => (int) $this->schoolyear->id,
        'email' => 'course.student.two@test.local',
        'import116_id' => (int) $registeredStudentTwoImport->id,
    ]);
    $importOnlyStudent = Import116::query()->create([
        'school_id' => (int) $this->school->id,
        'schoolyear_id' => (int) $this->schoolyear->id,
        'class' => '2B',
        'student_code' => '8001',
        'last_name' => 'Import',
        'first_name' => 'Only',
        'email' => 'course.import.only@test.local',
        'father_name' => 'Vater Import',
        'father_email' => 'father.import@test.local',
        'import_date' => now(),
        'import_user_id' => (int) $this->adminUser->id,
    ]);

    $course = TeachingCourse::factory()
        ->forSchool($this->school)
        ->forSchoolyear($this->schoolyear)
        ->forTeacher($this->adminUser)
        ->withClasses(['2B', '1A'])
        ->create([
            'title' => 'Mathematik',
        ]);

    TeachingCourseStudent::query()->create([
        'teaching_course_id' => (int) $course->id,
        'user_id' => (int) $studentOne->id,
    ]);
    TeachingCourseStudent::query()->create([
        'teaching_course_id' => (int) $course->id,
        'user_id' => (int) $studentTwo->id,
    ]);
    TeachingCourseStudent::query()->create([
        'teaching_course_id' => (int) $course->id,
        'import116_id' => (int) $importOnlyStudent->id,
    ]);

    $response = $this->getJson('/api/admin/groups')->assertSuccessful();

    $automaticGroup = UserGroup::query()
        ->where('school_id', (int) $this->school->id)
        ->where('type', UserGroup::TYPE_OWN)
        ->where('teaching_course_id', (int) $course->id)
        ->where('teaching_course_group_type', UserGroup::TEACHING_COURSE_GROUP_TYPE_STUDENTS)
        ->firstOrFail();

    expect((string) $automaticGroup->name)->toBe('Mathematik (1A, 2B)');
    expect($automaticGroup->description)->toBeNull();

    $memberIds = $automaticGroup->members()
        ->pluck('users.id')
        ->map(fn ($id) => (int) $id)
        ->sort()
        ->values()
        ->all();

    expect($memberIds)->toBe([
        (int) $studentOne->id,
        (int) $studentTwo->id,
    ]);

    $groupPayload = collect($response->json('data'))
        ->first(fn (array $group) => (int) ($group['teaching_course_id'] ?? 0) === (int) $course->id
            && ($group['teaching_course_group_type'] ?? null) === UserGroup::TEACHING_COURSE_GROUP_TYPE_STUDENTS);

    expect($groupPayload)->not->toBeNull();
    expect((bool) ($groupPayload['can_edit'] ?? true))->toBeFalse();
    expect((bool) ($groupPayload['can_manage_members'] ?? true))->toBeFalse();
    expect((bool) ($groupPayload['can_delete'] ?? true))->toBeFalse();
    expect((int) ($groupPayload['members_count'] ?? 0))->toBe(2);
    expect((int) ($groupPayload['source_users_count'] ?? 0))->toBe(3);

    $sourceMembersResponse = $this->getJson('/api/admin/groups/'.(int) $automaticGroup->id.'/source-members')
        ->assertSuccessful();

    $sourceMembers = collect($sourceMembersResponse->json('data'));
    expect($sourceMembers)->toHaveCount(3);
    expect($sourceMembers->firstWhere('user_id', (int) $studentOne->id))->not->toBeNull();
    expect($sourceMembers->firstWhere('user_id', (int) $studentTwo->id))->not->toBeNull();

    $importOnlyPayload = $sourceMembers->firstWhere('import116_id', (int) $importOnlyStudent->id);
    expect($importOnlyPayload)->not->toBeNull();
    expect((bool) ($importOnlyPayload['has_user_account'] ?? true))->toBeFalse();
    expect((bool) ($importOnlyPayload['already_member'] ?? false))->toBeTrue();

    $parentGroup = UserGroup::query()
        ->where('school_id', (int) $this->school->id)
        ->where('type', UserGroup::TYPE_OWN)
        ->where('teaching_course_id', (int) $course->id)
        ->where('teaching_course_group_type', UserGroup::TEACHING_COURSE_GROUP_TYPE_PARENTS)
        ->firstOrFail();

    expect((string) $parentGroup->name)->toBe('Mathematik (1A, 2B) Eltern');
    expect($parentGroup->description)->toBeNull();

    $parentGroupPayload = collect($response->json('data'))
        ->first(fn (array $group) => (int) ($group['teaching_course_id'] ?? 0) === (int) $course->id
            && ($group['teaching_course_group_type'] ?? null) === UserGroup::TEACHING_COURSE_GROUP_TYPE_PARENTS);

    expect($parentGroupPayload)->not->toBeNull();
    expect((bool) ($parentGroupPayload['is_parent_group'] ?? false))->toBeTrue();
    expect((int) ($parentGroupPayload['members_count'] ?? 0))->toBe(3);
    expect((int) ($parentGroupPayload['source_users_count'] ?? 0))->toBe(4);

    $registeredParentsResponse = $this->getJson('/api/admin/groups/'.(int) $parentGroup->id.'/members')
        ->assertSuccessful();

    $registeredParents = collect($registeredParentsResponse->json('data'));
    expect($registeredParents)->toHaveCount(3);
    expect($registeredParents->pluck('name')->all())->toContain('Mutter One', 'Vater One', 'Mutter Two');
    expect($registeredParents->pluck('name')->all())->not->toContain('Vater Import');

    $allParentsResponse = $this->getJson('/api/admin/groups/'.(int) $parentGroup->id.'/source-members')
        ->assertSuccessful();

    $allParents = collect($allParentsResponse->json('data'));
    expect($allParents)->toHaveCount(4);
    expect($allParents->pluck('name')->all())->toContain('Mutter One', 'Vater One', 'Mutter Two', 'Vater Import');
});

test('manual own groups can assign mixed member providers and show sync status changes', function () {
    $this->actingAs($this->adminUser, 'sanctum');

    $nextSchoolyear = Schoolyear::factory()->create([
        'school_id' => (int) $this->school->id,
    ]);

    $importStudent = Import116::query()->create([
        'school_id' => (int) $this->school->id,
        'schoolyear_id' => (int) $this->schoolyear->id,
        'class' => '3C',
        'student_code' => '9101',
        'last_name' => 'Gemischt',
        'first_name' => 'Schueler',
        'email' => 'mixed.student@test.local',
        'import_date' => now(),
        'import_user_id' => (int) $this->adminUser->id,
    ]);

    $teacher = Teacher::query()->create([
        'school_id' => (int) $this->school->id,
        'last_name' => 'Gemischt',
        'first_name' => 'Lehrer',
        'short' => 'GL',
        'email' => 'mixed.teacher@test.local',
    ]);

    $group = UserGroup::query()->create([
        'school_id' => (int) $this->school->id,
        'type' => UserGroup::TYPE_OWN,
        'name' => 'Gemischte Gruppe',
        'description' => null,
        'created_by_user_id' => (int) $this->adminUser->id,
    ]);

    $searchResponse = $this->getJson('/api/admin/groups/'.$group->id.'/assignable-users?search_string=Gemischt')
        ->assertSuccessful();

    $searchRows = collect($searchResponse->json('data'));
    expect($searchRows->pluck('member_provider')->all())->toContain(
        UserGroupMember::PROVIDER_IMPORT116_STUDENT,
        UserGroupMember::PROVIDER_TEACHER_LIST_TEACHER,
    );

    $this->postJson('/api/admin/groups/'.$group->id.'/assign-users', [
        'members' => [
            [
                'member_provider' => UserGroupMember::PROVIDER_IMPORT116_STUDENT,
                'member_ref' => 'import116.student:'.$importStudent->id,
            ],
            [
                'member_provider' => UserGroupMember::PROVIDER_TEACHER_LIST_TEACHER,
                'member_ref' => 'teacher_list.teacher:'.$teacher->id,
            ],
        ],
    ])
        ->assertSuccessful()
        ->assertJsonPath('meta.new_count', 2)
        ->assertJsonPath('meta.members_count', 2);

    $group->refresh();

    expect($group->groupMembers()->count())->toBe(2);
    expect($group->members()->count())->toBe(0);

    $membersResponse = $this->getJson('/api/admin/groups/'.$group->id.'/members')
        ->assertSuccessful();

    $members = collect($membersResponse->json('data'));
    expect($members)->toHaveCount(2);
    expect($members->firstWhere('member_provider', UserGroupMember::PROVIDER_IMPORT116_STUDENT))->not->toBeNull();
    expect($members->firstWhere('member_provider', UserGroupMember::PROVIDER_TEACHER_LIST_TEACHER))->not->toBeNull();

    SchoolTool::query()
        ->where('school_id', (int) $this->school->id)
        ->update(['active_schoolyear_id' => (int) $nextSchoolyear->id]);

    Teacher::query()->whereKey((int) $teacher->id)->delete();

    $refreshedMembersResponse = $this->getJson('/api/admin/groups/'.$group->id.'/members')
        ->assertSuccessful();

    $refreshedMembers = collect($refreshedMembersResponse->json('data'));

    $studentPayload = $refreshedMembers->firstWhere('member_provider', UserGroupMember::PROVIDER_IMPORT116_STUDENT);
    expect($studentPayload)->not->toBeNull();
    expect((string) ($studentPayload['source_status'] ?? ''))->toBe(UserGroupMember::SOURCE_STATUS_OUT_OF_SCOPE);
    expect((string) ($studentPayload['status_label'] ?? ''))->toBe('Nicht im aktiven Schuljahr');

    $teacherPayload = $refreshedMembers->firstWhere('member_provider', UserGroupMember::PROVIDER_TEACHER_LIST_TEACHER);
    expect($teacherPayload)->not->toBeNull();
    expect((string) ($teacherPayload['source_status'] ?? ''))->toBe(UserGroupMember::SOURCE_STATUS_MISSING);
    expect((string) ($teacherPayload['status_label'] ?? ''))->toBe('Quelle fehlt');
});
