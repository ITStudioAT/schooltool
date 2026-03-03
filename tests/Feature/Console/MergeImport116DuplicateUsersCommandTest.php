<?php

use App\Models\Import116;
use App\Models\School;
use App\Models\Schoolyear;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']);
});

test('dry run does not modify duplicate users', function () {
    $school = School::factory()->create();
    $schoolyear = Schoolyear::factory()->create(['school_id' => $school->id]);

    $importRow = Import116::factory()->create([
        'school_id' => $school->id,
        'schoolyear_id' => $schoolyear->id,
        'email' => 'target@student.test',
    ]);

    $duplicate = User::factory()->create([
        'school_id' => $school->id,
        'schoolyear_id' => $schoolyear->id,
        'email' => 'old@student.test',
        'import116_id' => $importRow->id,
    ]);
    $duplicate->assignRole('teacher');

    $keeper = User::factory()->create([
        'school_id' => $school->id,
        'schoolyear_id' => $schoolyear->id,
        'email' => 'target@student.test',
        'import116_id' => $importRow->id,
    ]);

    DB::table('queue_tests')->insert([
        'id' => (string) Str::uuid(),
        'user_id' => $duplicate->id,
        'status' => 'dispatched',
        'dispatched_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->artisan('users:merge-import116-duplicates')
        ->expectsOutputToContain('Dry-run only')
        ->assertExitCode(0);

    expect(User::query()->where('id', $duplicate->id)->exists())->toBeTrue()
        ->and(DB::table('queue_tests')->where('user_id', $duplicate->id)->count())->toBe(1);
});

test('apply merges duplicate users for same import116 and re-links references', function () {
    $school = School::factory()->create();
    $schoolyear = Schoolyear::factory()->create(['school_id' => $school->id]);

    $importRow = Import116::factory()->create([
        'school_id' => $school->id,
        'schoolyear_id' => $schoolyear->id,
        'email' => 'target@student.test',
    ]);

    $duplicate = User::factory()->create([
        'school_id' => $school->id,
        'schoolyear_id' => $schoolyear->id,
        'email' => 'old@student.test',
        'import116_id' => $importRow->id,
    ]);
    $duplicate->assignRole('teacher');

    $keeper = User::factory()->create([
        'school_id' => $school->id,
        'schoolyear_id' => $schoolyear->id,
        'email' => 'target@student.test',
        'import116_id' => $importRow->id,
    ]);
    $keeper->assignRole('student');

    $importRow->user_id = $duplicate->id;
    $importRow->save();

    DB::table('queue_tests')->insert([
        'id' => (string) Str::uuid(),
        'user_id' => $duplicate->id,
        'status' => 'dispatched',
        'dispatched_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->artisan('users:merge-import116-duplicates --apply')
        ->assertExitCode(0);

    $keeper->refresh();
    $importRow->refresh();

    expect(User::query()->where('id', $duplicate->id)->exists())->toBeFalse()
        ->and(DB::table('queue_tests')->where('user_id', $keeper->id)->count())->toBe(1)
        ->and((int) $importRow->user_id)->toBe($keeper->id)
        ->and($keeper->hasRole('teacher'))->toBeTrue()
        ->and($keeper->hasRole('student'))->toBeTrue();
});
