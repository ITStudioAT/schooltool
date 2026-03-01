<?php

use App\Models\Licence;
use App\Models\School;
use App\Models\Schoolyear;
use App\Models\TeachingCourse;
use App\Models\TeachingCourseBehaviourEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    collect([
        'super_admin',
        'admin',
        'teaching_admin',
        'teacher',
        'student',
        'user',
    ])->each(fn (string $role) => Role::firstOrCreate([
        'name' => $role,
        'guard_name' => 'web',
    ]));

    $this->school = School::factory()->create();
    $this->schoolyear = Schoolyear::factory()->create([
        'school_id' => $this->school->id,
    ]);

    $teachingLicence = Licence::firstOrCreate(
        ['name' => 'Lehrertool'],
        ['long_name' => 'Lehrertool', 'is_selectable' => true]
    );
    $this->school->licences()->attach($teachingLicence->id, [
        'valid_until' => now()->addYear()->toDateString(),
    ]);

    $this->admin = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'teaching_behaviour' => [
            ['short_name' => 'BZ', 'name' => 'Verhalten'],
            ['short_name' => 'ST', 'name' => 'Störung'],
        ],
        'teaching_notifications' => [
            ['short_name' => 'INF', 'name' => 'Info'],
            ['short_name' => 'WARN', 'name' => 'Warnung'],
        ],
    ]);
    $this->admin->assignRole('admin');

    $this->regularUser = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
    ]);
    $this->regularUser->assignRole('user');

    $this->student = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
    ]);
    $this->student->assignRole('student');

    $this->otherSchool = School::factory()->create();
    $this->otherSchoolyear = Schoolyear::factory()->create([
        'school_id' => $this->otherSchool->id,
    ]);
    $this->otherStudent = User::factory()->create([
        'school_id' => $this->otherSchool->id,
        'schoolyear_id' => $this->otherSchoolyear->id,
    ]);
    $this->otherStudent->assignRole('student');

    $this->course = TeachingCourse::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->admin->id,
        'classes' => ['1A'],
    ]);

    $this->otherCourse = TeachingCourse::factory()->create([
        'school_id' => $this->otherSchool->id,
        'schoolyear_id' => $this->otherSchoolyear->id,
        'classes' => ['9Z'],
    ]);
});

describe('authorization and index', function () {
    test('returns 401 when unauthenticated', function () {
        $this->getJson('/api/admin/teaching/course_behaviour_entries?course_id='.$this->course->id)
            ->assertStatus(401);
    });

    test('returns 403 for user without role', function () {
        $this->actingAs($this->regularUser, 'sanctum');

        $this->getJson('/api/admin/teaching/course_behaviour_entries?course_id='.$this->course->id)
            ->assertStatus(403);
    });

    test('validates required course_id', function () {
        $this->actingAs($this->admin, 'sanctum');

        $this->getJson('/api/admin/teaching/course_behaviour_entries')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['course_id']);
    });

    test('returns sorted entries and supports user filter', function () {
        $this->actingAs($this->admin, 'sanctum');

        $older = TeachingCourseBehaviourEntry::query()->create([
            'teaching_course_id' => $this->course->id,
            'user_id' => $this->student->id,
            'kind' => 'behaviour',
            'type' => 'BZ',
            'date' => '2026-03-01',
        ]);

        $newer = TeachingCourseBehaviourEntry::query()->create([
            'teaching_course_id' => $this->course->id,
            'user_id' => $this->admin->id,
            'kind' => 'notification',
            'type' => 'INF',
            'date' => '2026-03-02',
        ]);

        $response = $this->getJson('/api/admin/teaching/course_behaviour_entries?course_id='.$this->course->id);
        $response->assertOk()->assertJsonCount(2, 'data');
        expect($response->json('data.0.id'))->toBe($newer->id)
            ->and($response->json('data.1.id'))->toBe($older->id);

        $this->getJson('/api/admin/teaching/course_behaviour_entries?course_id='.$this->course->id.'&user_id='.$this->student->id)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.user_id', $this->student->id);
    });
});

describe('store update destroy', function () {
    test('store creates behaviour entry with due and done dates', function () {
        $this->actingAs($this->admin, 'sanctum');

        $response = $this->postJson('/api/admin/teaching/course_behaviour_entries', [
            'teaching_course_id' => $this->course->id,
            'user_id' => $this->student->id,
            'kind' => 'behaviour',
            'type' => 'BZ',
            'date' => '2026-03-10',
            'is_due' => true,
            'due_date' => '2026-03-12',
            'is_done' => true,
            'done_date' => '2026-03-13',
            'description' => 'Verhaltenseintrag',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.kind', 'behaviour')
            ->assertJsonPath('data.type', 'BZ');

        $this->assertDatabaseHas('teaching_course_behaviour_entries', [
            'teaching_course_id' => $this->course->id,
            'user_id' => $this->student->id,
            'kind' => 'behaviour',
            'type' => 'BZ',
            'due_date' => '2026-03-12',
            'done_date' => '2026-03-13',
        ]);
    });

    test('store supports notification kind and validates type by kind', function () {
        $this->actingAs($this->admin, 'sanctum');

        $this->postJson('/api/admin/teaching/course_behaviour_entries', [
            'teaching_course_id' => $this->course->id,
            'user_id' => $this->student->id,
            'kind' => 'notification',
            'type' => 'INF',
        ])->assertCreated()->assertJsonPath('data.kind', 'notification');

        $this->postJson('/api/admin/teaching/course_behaviour_entries', [
            'teaching_course_id' => $this->course->id,
            'user_id' => $this->student->id,
            'kind' => 'notification',
            'type' => 'BZ',
        ])->assertStatus(422);
    });

    test('store validates due and done date requirements', function () {
        $this->actingAs($this->admin, 'sanctum');

        $this->postJson('/api/admin/teaching/course_behaviour_entries', [
            'teaching_course_id' => $this->course->id,
            'user_id' => $this->student->id,
            'kind' => 'behaviour',
            'type' => 'BZ',
            'is_due' => true,
        ])->assertStatus(422);

        $this->postJson('/api/admin/teaching/course_behaviour_entries', [
            'teaching_course_id' => $this->course->id,
            'user_id' => $this->student->id,
            'kind' => 'behaviour',
            'type' => 'BZ',
            'is_due' => true,
            'due_date' => '2026-03-10',
            'is_done' => true,
        ])->assertStatus(422);
    });

    test('update can switch kind and clears due/done dates when flags are false', function () {
        $this->actingAs($this->admin, 'sanctum');

        $entry = TeachingCourseBehaviourEntry::query()->create([
            'teaching_course_id' => $this->course->id,
            'user_id' => $this->student->id,
            'kind' => 'behaviour',
            'type' => 'BZ',
            'date' => '2026-03-01',
            'due_date' => '2026-03-02',
            'done_date' => '2026-03-03',
        ]);

        $this->putJson('/api/admin/teaching/course_behaviour_entries/'.$entry->id, [
            'kind' => 'notification',
            'type' => 'INF',
            'date' => '2026-03-11',
            'is_due' => false,
            'is_done' => false,
            'description' => 'Info update',
        ])->assertOk()
            ->assertJsonPath('data.kind', 'notification')
            ->assertJsonPath('data.type', 'INF');

        $entry->refresh();
        expect($entry->kind)->toBe('notification')
            ->and($entry->due_date)->toBeNull()
            ->and($entry->done_date)->toBeNull();
    });

    test('destroy deletes entry', function () {
        $this->actingAs($this->admin, 'sanctum');

        $entry = TeachingCourseBehaviourEntry::query()->create([
            'teaching_course_id' => $this->course->id,
            'user_id' => $this->student->id,
            'kind' => 'behaviour',
            'type' => 'BZ',
            'date' => '2026-03-01',
        ]);

        $this->deleteJson('/api/admin/teaching/course_behaviour_entries/'.$entry->id)->assertNoContent();

        $this->assertDatabaseMissing('teaching_course_behaviour_entries', ['id' => $entry->id]);
    });

    test('enforces school isolation for create and update', function () {
        $this->actingAs($this->admin, 'sanctum');

        $this->postJson('/api/admin/teaching/course_behaviour_entries', [
            'teaching_course_id' => $this->otherCourse->id,
            'user_id' => $this->student->id,
            'kind' => 'behaviour',
            'type' => 'BZ',
        ])->assertStatus(403);

        $entryOther = TeachingCourseBehaviourEntry::query()->create([
            'teaching_course_id' => $this->otherCourse->id,
            'user_id' => $this->otherStudent->id,
            'kind' => 'behaviour',
            'type' => 'BZ',
            'date' => '2026-03-01',
        ]);

        $this->putJson('/api/admin/teaching/course_behaviour_entries/'.$entryOther->id, [
            'kind' => 'behaviour',
            'type' => 'BZ',
        ])->assertStatus(403);
    });
});
