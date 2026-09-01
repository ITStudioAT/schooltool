<?php

use App\Models\Licence;
use App\Models\School;
use App\Models\SchoolLicence;
use App\Models\SchoolTool;
use App\Models\Teacher;
use App\Models\TeachingCourse;
use App\Models\User;
use App\Models\UserGroup;
use App\Models\UserGroupMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

it('removes an imported teacher without dependencies', function () {
    $manager = createTeacherRosterRemoveManager();
    $teacher = Teacher::query()->create([
        'school_id' => $manager->school_id,
        'short' => 'IMP',
        'last_name' => 'Importiert',
        'first_name' => 'Ines',
        'email' => 'imported.remove@example.test',
    ]);

    $this->actingAs($manager)
        ->deleteJson("/api/admin/students-timetables/teacher-list/{$teacher->id}")
        ->assertSuccessful()
        ->assertJsonPath('message', 'Die Lehrkraft wurde aus der Lehrerliste entfernt.');

    $this->assertDatabaseMissing('teachers', ['id' => $teacher->id]);
});

it('blocks removing an imported teacher used in a group', function () {
    $manager = createTeacherRosterRemoveManager();
    $teacher = Teacher::query()->create([
        'school_id' => $manager->school_id,
        'short' => 'GRP',
        'last_name' => 'Gruppe',
        'first_name' => 'Gina',
        'email' => 'group.dependency@example.test',
    ]);
    $group = UserGroup::query()->create([
        'school_id' => $manager->school_id,
        'type' => UserGroup::TYPE_OWN,
        'name' => 'Lehrkräfte',
        'created_by_user_id' => $manager->id,
    ]);
    $membership = UserGroupMember::query()->create([
        'user_group_id' => $group->id,
        'school_id' => $manager->school_id,
        'member_provider' => UserGroupMember::PROVIDER_TEACHER_LIST_TEACHER,
        'member_ref' => "teacher_list.teacher:{$teacher->id}",
        'display_name' => 'Gruppe Gina',
        'display_email' => $teacher->email,
        'member_type_label' => 'Lehrer:in',
        'source_status' => UserGroupMember::SOURCE_STATUS_ACTIVE,
        'linked_user_status' => UserGroupMember::LINKED_USER_STATUS_NOT_APPLICABLE,
        'added_by_user_id' => $manager->id,
    ]);

    $this->actingAs($manager)
        ->deleteJson("/api/admin/students-timetables/teacher-list/{$teacher->id}")
        ->assertConflict()
        ->assertJsonPath(
            'message',
            'Die Lehrkraft kann nicht entfernt werden. Abhängigkeiten: 1 Gruppenmitgliedschaft.',
        );

    $this->assertDatabaseHas('teachers', ['id' => $teacher->id]);
    $this->assertDatabaseHas('user_group_members', ['id' => $membership->id]);
});

it('removes a registered teacher from the roster without deleting the account', function () {
    $manager = createTeacherRosterRemoveManager();
    $teacher = User::factory()->create([
        'school_id' => $manager->school_id,
        'email' => 'registered.remove@example.test',
        'is_active' => false,
        'students_timetables_teacher_listed' => true,
    ]);
    $teacher->assignRole([
        Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']),
        Role::firstOrCreate(['name' => 'studentstimetables_moderator', 'guard_name' => 'web']),
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']),
        Role::firstOrCreate(['name' => 'materials_moderator', 'guard_name' => 'web']),
    ]);
    $matchingImportedTeacher = Teacher::query()->create([
        'school_id' => $manager->school_id,
        'short' => 'REG',
        'last_name' => $teacher->last_name,
        'first_name' => $teacher->first_name,
        'email' => strtoupper($teacher->email),
    ]);

    $this->actingAs($manager)
        ->deleteJson("/api/admin/students-timetables/teacher-list/users/{$teacher->id}")
        ->assertSuccessful()
        ->assertJsonPath(
            'message',
            'Die Lehrkraft wurde aus der Lehrerliste entfernt. Das Benutzerkonto bleibt erhalten.',
        );

    $teacher->refresh();

    expect((bool) $teacher->is_active)->toBeFalse()
        ->and((bool) $teacher->students_timetables_teacher_listed)->toBeFalse()
        ->and($teacher->hasRole('teacher'))->toBeFalse()
        ->and($teacher->hasRole('studentstimetables_moderator'))->toBeFalse()
        ->and($teacher->hasRole('admin'))->toBeTrue()
        ->and($teacher->hasRole('materials_moderator'))->toBeTrue();

    $this->assertDatabaseHas('users', ['id' => $teacher->id]);
    $this->assertDatabaseMissing('teachers', ['id' => $matchingImportedTeacher->id]);

    $this->actingAs($manager)
        ->getJson('/api/admin/students-timetables/teacher-list')
        ->assertSuccessful()
        ->assertJsonMissing(['id' => "user-{$teacher->id}"]);
});

it('blocks removing a registered teacher used by a teaching course', function () {
    $manager = createTeacherRosterRemoveManager();
    $teacher = User::factory()->create([
        'school_id' => $manager->school_id,
        'email' => 'course.dependency@example.test',
        'students_timetables_teacher_listed' => true,
    ]);
    $teacher->assignRole([
        Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']),
        Role::firstOrCreate(['name' => 'studentstimetables_moderator', 'guard_name' => 'web']),
    ]);
    $course = TeachingCourse::factory()->create([
        'school_id' => $manager->school_id,
        'schoolyear_id' => null,
        'user_id' => $teacher->id,
    ]);

    $this->actingAs($manager)
        ->deleteJson("/api/admin/students-timetables/teacher-list/users/{$teacher->id}")
        ->assertConflict()
        ->assertJsonPath(
            'message',
            'Die Lehrkraft kann nicht entfernt werden. Abhängigkeiten: 1 Unterrichtskurs.',
        );

    $teacher->refresh();

    expect((bool) $teacher->students_timetables_teacher_listed)->toBeTrue()
        ->and($teacher->hasRole('teacher'))->toBeTrue()
        ->and($teacher->hasRole('studentstimetables_moderator'))->toBeTrue();
    $this->assertDatabaseHas('teaching_courses', ['id' => $course->id]);
});

it('exposes whether each roster row can be removed', function () {
    $manager = createTeacherRosterRemoveManager();
    $manager->students_timetables_teacher_listed = true;
    $manager->save();
    $otherTeacher = User::factory()->create([
        'school_id' => $manager->school_id,
        'students_timetables_teacher_listed' => true,
    ]);
    $otherTeacher->assignRole(Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']));
    $importedTeacher = Teacher::query()->create([
        'school_id' => $manager->school_id,
        'short' => 'IMP',
        'last_name' => 'Importiert',
        'first_name' => 'Iris',
        'email' => 'can.remove@example.test',
    ]);

    $response = $this->actingAs($manager)
        ->getJson('/api/admin/students-timetables/teacher-list')
        ->assertSuccessful();

    $rows = collect($response->json('data'))->keyBy('id');

    expect($rows->get("user-{$manager->id}")['can_remove'])->toBeFalse()
        ->and($rows->get("user-{$otherTeacher->id}")['can_remove'])->toBeTrue()
        ->and($rows->get("teacher-{$importedTeacher->id}")['can_remove'])->toBeTrue();
});

it('forbids removing yourself or a teacher from another school', function () {
    $manager = createTeacherRosterRemoveManager();
    $manager->students_timetables_teacher_listed = true;
    $manager->save();
    $manager->assignRole(Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']));

    $this->actingAs($manager)
        ->deleteJson("/api/admin/students-timetables/teacher-list/users/{$manager->id}")
        ->assertForbidden();

    expect((bool) $manager->fresh()->students_timetables_teacher_listed)->toBeTrue()
        ->and($manager->fresh()->hasRole('teacher'))->toBeTrue()
        ->and($manager->fresh()->hasRole('studentstimetables_admin'))->toBeTrue();

    $otherSchool = School::factory()->create();
    $otherTeacher = Teacher::query()->create([
        'school_id' => $otherSchool->id,
        'short' => 'OTH',
        'last_name' => 'Andere',
        'first_name' => 'Schule',
        'email' => 'other.school.remove@example.test',
    ]);

    $this->actingAs($manager)
        ->deleteJson("/api/admin/students-timetables/teacher-list/{$otherTeacher->id}")
        ->assertForbidden();

    $this->assertDatabaseHas('teachers', ['id' => $otherTeacher->id]);
});

it('forbids non-managers and non-roster targets', function () {
    $manager = createTeacherRosterRemoveManager();
    $nonRosterUser = User::factory()->create([
        'school_id' => $manager->school_id,
        'students_timetables_teacher_listed' => false,
    ]);

    $this->actingAs($manager)
        ->deleteJson("/api/admin/students-timetables/teacher-list/users/{$nonRosterUser->id}")
        ->assertForbidden();

    $ordinaryTeacher = createTeacherRosterRemoveManager('teacher');
    $importedTeacher = Teacher::query()->create([
        'school_id' => $ordinaryTeacher->school_id,
        'short' => 'NO',
        'last_name' => 'Keine',
        'first_name' => 'Berechtigung',
        'email' => 'not.authorized.remove@example.test',
    ]);

    $this->actingAs($ordinaryTeacher)
        ->deleteJson("/api/admin/students-timetables/teacher-list/{$importedTeacher->id}")
        ->assertForbidden();

    $this->assertDatabaseHas('teachers', ['id' => $importedTeacher->id]);
});

function createTeacherRosterRemoveManager(string $roleName = 'studentstimetables_admin'): User
{
    $school = School::factory()->create();

    SchoolTool::factory()->create([
        'school_id' => $school->id,
        'students_timetables_visible_admin' => true,
        'students_timetables_visible_user' => true,
        'students_timetables_user_test_mode' => false,
        'students_timetables_user_comming_soon' => false,
    ]);

    $licence = Licence::query()->firstOrCreate(['name' => 'StudentsTimetables'], [
        'long_name' => 'Tool zum Verwalten von Schülerstundenplänen',
        'price_per_year' => 200,
    ]);

    SchoolLicence::query()->create([
        'school_id' => $school->id,
        'licence_id' => $licence->id,
        'valid_until' => now()->addMonth()->toDateString(),
    ]);

    Role::firstOrCreate([
        'name' => $roleName,
        'guard_name' => 'web',
    ]);

    $manager = User::factory()->create([
        'school_id' => $school->id,
        'is_active' => true,
    ]);
    $manager->assignRole($roleName);

    return $manager;
}
