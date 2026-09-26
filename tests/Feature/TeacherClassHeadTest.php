<?php

use App\Models\Import116;
use App\Models\School;
use App\Models\Schoolyear;
use App\Models\TeachingClassHead;
use App\Models\TeachingClassHeadEmail;
use App\Models\User;
use App\Services\TeachingClassHeadEmailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->school = School::factory()->create();
    $this->schoolyear = Schoolyear::factory()->create(['school_id' => $this->school->id]);
    $this->admin = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
    ]);
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']);
    $this->admin->assignRole('admin');
    $this->teachers = collect(range(1, 3))->map(function (int $number): User {
        $teacher = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'email' => "teacher{$number}@example.test",
        ]);
        $teacher->assignRole('teacher');

        return $teacher;
    });
    Import116::factory()->forSchool($this->school)->importedBy($this->admin)->create([
        'schoolyear_id' => $this->schoolyear->id,
        'class' => '1A',
    ]);
    Import116::factory()->forSchool($this->school)->importedBy($this->admin)->create([
        'schoolyear_id' => $this->schoolyear->id,
        'class' => '1B',
    ]);
    $this->actingAs($this->admin, 'sanctum');
});

test('teachers can head multiple classes and classes can have multiple teachers', function () {
    $first = $this->teachers[0];

    $this->putJson("/api/admin/teachers/{$first->id}/class-head", ['class_names' => ['1A', '1B']])
        ->assertOk()
        ->assertJsonPath('class_names', ['1A', '1B']);
    foreach ($this->teachers->skip(1) as $teacher) {
        $this->putJson("/api/admin/teachers/{$teacher->id}/class-head", ['class_names' => ['1A']])
            ->assertOk();
    }

    $this->assertDatabaseHas('teaching_class_heads', [
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $first->id,
        'class_name' => '1A',
    ]);
    $response = $this->getJson('/api/admin/teachers')
        ->assertOk()
        ->assertJsonPath('classes', ['1A', '1B']);
    $listedTeachers = collect($response->json('data'))->keyBy('id');
    expect($listedTeachers[$first->id]['class_head_classes'])->toBe(['1A', '1B'])
        ->and($listedTeachers[$this->teachers[2]->id]['class_head_classes'])->toBe(['1A'])
        ->and(TeachingClassHead::query()->where('class_name', '1A')->whereNotNull('user_id')->count())->toBe(3);
    expect(app(TeachingClassHeadEmailService::class)->listForUser($this->admin)[0]['teacher_ids'])->toHaveCount(3);

    $this->putJson("/api/admin/teachers/{$first->id}/class-head", ['class_names' => ['1B']])
        ->assertOk()
        ->assertJsonPath('class_names', ['1B']);
    $this->assertDatabaseMissing('teaching_class_heads', ['user_id' => $first->id, 'class_name' => '1A']);
    $this->assertDatabaseHas('teaching_class_heads', ['user_id' => $first->id, 'class_name' => '1B']);
});

test('class head assignment enforces role school year and imported class boundaries', function () {
    $teacher = $this->teachers[0];
    $otherSchool = School::factory()->create();
    $otherYear = Schoolyear::factory()->create(['school_id' => $otherSchool->id]);
    $otherTeacher = User::factory()->create([
        'school_id' => $otherSchool->id,
        'schoolyear_id' => $otherYear->id,
    ]);
    $otherTeacher->assignRole('teacher');

    $this->putJson("/api/admin/teachers/{$teacher->id}/class-head", ['class_names' => ['9Z']])
        ->assertUnprocessable();
    $this->putJson("/api/admin/teachers/{$teacher->id}/class-head", ['class_names' => ['1A', '1A']])
        ->assertUnprocessable();
    $this->putJson("/api/admin/teachers/{$otherTeacher->id}/class-head", ['class_names' => ['1A']])
        ->assertNotFound();
    $this->putJson("/api/admin/teachers/{$this->admin->id}/class-head", ['class_names' => ['1A']])
        ->assertNotFound();

    $otherSchoolyear = Schoolyear::factory()->create(['school_id' => $this->school->id]);
    $this->admin->update(['schoolyear_id' => $otherSchoolyear->id]);
    $this->putJson("/api/admin/teachers/{$teacher->id}/class-head", ['class_names' => ['1A']])
        ->assertUnprocessable();
    expect(TeachingClassHead::query()->exists())->toBeFalse();

    $this->actingAs($teacher, 'sanctum')
        ->putJson("/api/admin/teachers/{$teacher->id}/class-head", ['class_names' => []])
        ->assertForbidden();
});

test('central class head assignment takes precedence over legacy course email routing', function () {
    $teacher = $this->teachers[0];
    TeachingClassHeadEmail::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->admin->id,
        'class_name' => '1A',
        'email_1' => $this->teachers[1]->email,
    ]);
    expect(app(TeachingClassHeadEmailService::class)->listForUser($this->admin)[0]['source'])->toBe('course');

    $this->putJson("/api/admin/teachers/{$teacher->id}/class-head", ['class_names' => ['1A']])
        ->assertOk();

    $rows = app(TeachingClassHeadEmailService::class)->listForUser($this->admin);
    expect($rows)->toHaveCount(1)
        ->and($rows[0]['teacher_1_id'])->toBe($teacher->id)
        ->and($rows[0]['teacher_2_id'])->toBeNull()
        ->and($rows[0]['teacher_ids'])->toBe([$teacher->id])
        ->and($rows[0]['source'])->toBe('school');

    $this->putJson("/api/admin/teachers/{$teacher->id}/class-head", ['class_names' => []])
        ->assertOk();

    $rows = app(TeachingClassHeadEmailService::class)->listForUser($this->admin);
    expect($rows[0]['teacher_1_id'])->toBeNull()
        ->and($rows[0]['teacher_ids'])->toBe([])
        ->and($rows[0]['source'])->toBe('school')
        ->and(TeachingClassHead::query()->whereNull('user_id')->where('class_name', '1A')->exists())->toBeTrue();
});

test('deleting the last assigned teacher does not reactivate legacy course emails', function () {
    $teacher = $this->teachers[0];
    TeachingClassHeadEmail::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->admin->id,
        'class_name' => '1A',
        'email_1' => $this->teachers[1]->email,
    ]);
    $this->putJson("/api/admin/teachers/{$teacher->id}/class-head", ['class_names' => ['1A']])
        ->assertOk();

    $teacher->delete();

    expect(TeachingClassHead::query()->where('class_name', '1A')->whereNull('user_id')->exists())->toBeTrue()
        ->and(app(TeachingClassHeadEmailService::class)->listForUser($this->admin)[0]['teacher_ids'])->toBe([]);
});
