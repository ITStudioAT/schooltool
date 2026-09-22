<?php

use App\Http\Middleware\ToolLicensed;
use App\Models\FeaturePreviewSetting;
use App\Models\Import116;
use App\Models\School;
use App\Models\SchoolTool;
use App\Models\Schoolyear;
use App\Models\TeachingCourse;
use App\Models\TeachingCourseStudent;
use App\Models\User;
use App\Notifications\StandardEmail;
use App\Services\ParentStudentAccessService;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    config([
        'database.default' => 'feature_preview_student_test',
        'database.connections.feature_preview_student_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true],
        'app.key' => 'base64:'.base64_encode(str_repeat('s', 32)),
        'schooltool.preview.instance' => true,
        'schooltool.preview.expected_host' => 'preview.example.test',
        'schooltool.preview.url' => 'https://preview.example.test',
        'schooltool.preview.live_url' => 'https://live.example.test',
        'cache.default' => 'array',
        'session.driver' => 'array',
    ]);
    DB::purge('feature_preview_student_test');
    expect(DB::connection()->getDatabaseName())->toBe(':memory:');
    URL::forceRootUrl('https://preview.example.test');
    $this->withServerVariables(['HTTP_HOST' => 'preview.example.test']);
    $this->withHeader('Host', 'preview.example.test');
    $this->withHeader('Origin', 'https://preview.example.test');
    $this->withoutVite();
    $this->withoutMiddleware(ToolLicensed::class);
    Notification::fake();
    Event::fake([Login::class, Logout::class]);

    Schema::create('schools', function (Blueprint $table): void {
        $table->id();
        foreach (['long_name', 'short_name', 'email', 'logo', 'color'] as $column) {
            $table->string($column)->nullable();
        }
        $table->boolean('is_selectable')->default(true);
        $table->timestamps();
    });
    Schema::create('schoolyears', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('school_id');
        $table->string('name');
        $table->boolean('is_active')->default(true);
        $table->timestamps();
    });
    Schema::create('school_tools', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('school_id');
        $table->unsignedBigInteger('active_schoolyear_id');
        $table->timestamps();
    });
    Schema::create('users', function (Blueprint $table): void {
        $table->id();
        foreach (['school_id', 'schoolyear_id', 'register_id', 'import116_id'] as $column) {
            $table->unsignedBigInteger($column)->nullable();
        }
        foreach (['last_name', 'first_name', 'phone', 'email', 'password', 'remember_token', 'login_ip', 'token_2fa', 'token_2fa_2', 'email_2fa', 'sex', 'schoolclass'] as $column) {
            $table->string($column)->nullable();
        }
        foreach (['email_verified_at', 'confirmed_at', 'login_at', 'token_2fa_expires_at', 'token_2fa_2_expires_at', 'two_factor_confirmed_at'] as $column) {
            $table->timestamp($column)->nullable();
        }
        foreach (['two_factor_secret', 'two_factor_recovery_codes'] as $column) {
            $table->text($column)->nullable();
        }
        $table->boolean('is_active')->default(true);
        $table->boolean('is_2fa')->default(false);
        $table->boolean('use_school_color_for_admin_ui')->default(true);
        $table->timestamps();
    });
    Schema::create('import116', function (Blueprint $table): void {
        $table->id();
        foreach (['school_id', 'schoolyear_id', 'user_id', 'import_user_id'] as $column) {
            $table->unsignedBigInteger($column)->nullable();
        }
        foreach (['class', 'school_level', 'attendance_year', 'religion', 'student_code', 'last_name', 'first_name', 'email', 'phone_1', 'phone_2', 'sex', 'mother_name', 'mother_email', 'mother_phone_1', 'mother_phone_2', 'father_name', 'father_email', 'father_phone_1', 'father_phone_2'] as $column) {
            $table->string($column)->nullable();
        }
        $table->date('birth_date')->nullable();
        $table->dateTime('exists_date')->nullable();
        $table->dateTime('import_date')->nullable();
        $table->timestamps();
    });
    Schema::create('teaching_courses', function (Blueprint $table): void {
        $table->id();
        foreach (['school_id', 'schoolyear_id', 'user_id'] as $column) {
            $table->unsignedBigInteger($column)->nullable();
        }
        $table->string('title');
        $table->text('classes')->nullable();
        $table->boolean('teaching_show_student_age')->default(false);
        $table->boolean('teaching_show_student_last_login')->default(false);
        $table->timestamps();
    });
    Schema::create('teaching_course_students', function (Blueprint $table): void {
        $table->id();
        foreach (['teaching_course_id', 'user_id', 'import116_id'] as $column) {
            $table->unsignedBigInteger($column)->nullable();
        }
        $table->dateTime('canceled_at')->nullable();
        $table->softDeletes();
        $table->timestamps();
    });
    Schema::create('teaching_schemas', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('user_id');
        $table->unsignedBigInteger('schoolyear_id');
    });
    (require database_path('migrations/2025_10_16_163810_create_permission_tables.php'))->up();
    Schema::table('roles', fn (Blueprint $table) => $table->boolean('is_admin')->default(false));
    (require database_path('migrations/2026_09_16_105645_create_feature_preview_settings_table.php'))->up();
    (require database_path('migrations/2026_09_16_105646_add_feature_preview_allowed_to_users_table.php'))->up();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    foreach (['student', 'studentstimetables_user'] as $role) {
        Role::create(['name' => $role, 'guard_name' => 'web']);
    }
    $this->school = School::factory()->create();
    $this->schoolyear = Schoolyear::factory()->create(['school_id' => $this->school->id]);
    SchoolTool::create(['school_id' => $this->school->id, 'active_schoolyear_id' => $this->schoolyear->id]);
    FeaturePreviewSetting::factory()->create(['enabled' => true]);
    snapshotFeaturePreviewControlForTests();
});

afterEach(function (): void {
    DB::purge('preview_control');
    DB::purge('feature_preview_student_test');
});

function previewStudentAccount(string $role = 'student', bool $allowed = true): User
{
    $user = User::factory()->create([
        'school_id' => test()->school->id,
        'schoolyear_id' => test()->schoolyear->id,
        'confirmed_at' => null,
        'email_verified_at' => now(),
        'is_active' => true,
        'feature_preview_allowed' => $allowed,
        'password' => Hash::make('student-password'),
    ]);
    $user->assignRole($role);
    snapshotFeaturePreviewControlForTests();

    return $user;
}

function previewStudentImport(?User $user = null, string $parentEmail = 'parent@example.test'): Import116
{
    return Import116::factory()->create([
        'school_id' => test()->school->id,
        'schoolyear_id' => test()->schoolyear->id,
        'user_id' => $user?->id,
        'import_user_id' => $user?->id,
        'email' => $user?->email ?? fake()->unique()->safeEmail(),
        'birth_date' => today()->subYears(15),
        'mother_email' => $parentEmail,
        'father_email' => null,
    ]);
}

function previewParentChild(?User $user = null, string $parentEmail = 'parent@example.test'): Import116
{
    $student = previewStudentImport($user, $parentEmail);
    $course = TeachingCourse::factory()->create([
        'school_id' => test()->school->id,
        'schoolyear_id' => test()->schoolyear->id,
        'user_id' => $user?->id,
    ]);
    TeachingCourseStudent::create(['teaching_course_id' => $course->id, 'import116_id' => $student->id]);

    return $student;
}

function previewParentLoginCode(): string
{
    snapshotFeaturePreviewControlForTests();
    test()->postJson('/api/homepage/student/login_step_email', [
        'type' => 'login_without_password',
        'school_id' => test()->school->id,
        'email' => 'parent@example.test',
    ])->assertOk()->assertJsonPath('login_context', 'parent');

    $code = '';
    Notification::assertSentOnDemand(StandardEmail::class, function (StandardEmail $notification) use (&$code): bool {
        $code = (string) $notification->data['token_2fa'];

        return true;
    });

    return $code;
}

function previewParentCodePayload(string $code): array
{
    return [
        'type' => 'login_without_password',
        'school_id' => test()->school->id,
        'schoolyear_id' => test()->schoolyear->id,
        'email' => 'parent@example.test',
        'login_context' => 'parent',
        'login_code' => $code,
    ];
}

test('preview admits an explicitly granted student through the existing password login', function (string $area, string $role): void {
    $user = previewStudentAccount($role);
    previewStudentImport($user);

    $this->postJson("/api/homepage/{$area}/login_step_email", [
        'type' => 'login_with_password',
        'school_id' => $this->school->id,
        'email' => $user->email,
    ])->assertOk()->assertJsonPath('status', 'enter_password');

    $this->postJson("/api/homepage/{$area}/login_step_password", [
        'type' => 'login_with_password',
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => $user->email,
        'password' => 'student-password',
    ])->assertOk()->assertJsonPath('status', 'login_ok')->assertJsonPath('user.id', $user->id);

    $this->assertAuthenticatedAs($user);
    $this->getJson("/api/homepage/{$area}/user")->assertOk()->assertJsonPath('user.id', $user->id);
})->with(['teaching' => ['student', 'student'], 'timetables' => ['students-timetables', 'studentstimetables_user']]);

test('student password overrides stay main only and preview still accepts the own password', function (string $area, string $role, bool $preview): void {
    config(['schooltool.preview.instance' => $preview]);
    $user = previewStudentAccount($role, $preview);
    previewStudentImport($user);
    Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $superadmin = User::factory()->create([
        'school_id' => $user->school_id,
        'is_active' => true,
        'feature_preview_allowed' => false,
        'password' => Hash::make('override-password'),
    ]);
    $superadmin->assignRole('super_admin');
    snapshotFeaturePreviewControlForTests();
    $data = [
        'type' => 'login_with_password',
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => $user->email,
        'password' => 'override-password',
    ];

    $this->postJson("/api/homepage/{$area}/login_step_password", $data)
        ->assertOk()->assertJsonPath('status', $preview ? 'password_not_valid' : 'login_ok')->assertJsonMissingPath('password');

    if ($preview) {
        $this->assertGuest();
        expect($user->fresh()->login_at)->toBeNull();
        Notification::assertNothingSent();
        $this->postJson("/api/homepage/{$area}/login_step_password", [...$data, 'password' => 'student-password'])
            ->assertOk()->assertJsonPath('status', 'login_ok')->assertJsonPath('user.id', $user->id);
    }
    $this->assertAuthenticatedAs($user);
})->with([
    'teaching preview' => ['student', 'student', true],
    'teaching main' => ['student', 'student', false],
    'timetables preview' => ['students-timetables', 'studentstimetables_user', true],
    'timetables main' => ['students-timetables', 'studentstimetables_user', false],
]);

test('preview refuses unprovisioned imports without creating student accounts', function (string $area): void {
    $student = previewStudentImport();
    $count = User::query()->count();

    $this->postJson("/api/homepage/{$area}/login_step_email", [
        'type' => 'login_without_password',
        'school_id' => $this->school->id,
        'email' => $student->email,
    ])->assertForbidden();

    expect(User::query()->count())->toBe($count)
        ->and($student->fresh()->user_id)->toBeNull();
    Notification::assertNothingSent();
})->with(['student', 'students-timetables']);

test('preview refuses import synchronization and role assignment for an ungranted account', function (string $area, string $existingRole): void {
    $user = previewStudentAccount($existingRole, allowed: false);
    $student = previewStudentImport($user);
    $student->update(['first_name' => 'Must not synchronize']);
    $originalFirstName = $user->first_name;

    $this->postJson("/api/homepage/{$area}/login_step_email", [
        'type' => 'login_without_password',
        'school_id' => $this->school->id,
        'email' => $student->email,
    ])->assertForbidden();

    expect($user->fresh()->first_name)->toBe($originalFirstName)
        ->and($user->fresh()->roles->pluck('name')->all())->toBe([$existingRole])
        ->and($user->fresh()->token_2fa)->toBeNull();
    Notification::assertNothingSent();
})->with(['teaching' => ['student', 'studentstimetables_user'], 'timetables' => ['students-timetables', 'student']]);

test('preview rejects student code issuance and consumption after permission is revoked', function (string $area, string $role): void {
    $user = previewStudentAccount($role);
    previewStudentImport($user);
    $this->postJson("/api/homepage/{$area}/login_step_email", [
        'type' => 'login_without_password',
        'school_id' => $this->school->id,
        'email' => $user->email,
    ])->assertOk()->assertJsonPath('status', 'code_sent');
    $user->refresh();
    $code = $user->token_2fa;
    User::on('preview_control')->whereKey($user->id)->update(['feature_preview_allowed' => false]);
    Notification::fake();

    $this->postJson("/api/homepage/{$area}/login_step_email", [
        'type' => 'login_without_password',
        'school_id' => $this->school->id,
        'email' => $user->email,
    ])->assertForbidden();
    $this->postJson("/api/homepage/{$area}/login_step_code", [
        'type' => 'login_without_password',
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => $user->email,
        'login_code' => $code,
    ])->assertForbidden();

    expect($user->fresh()->token_2fa)->toBe($code)
        ->and($user->fresh()->login_at)->toBeNull();
    Notification::assertNothingSent();
    $this->assertGuest();
})->with(['teaching' => ['student', 'student'], 'timetables' => ['students-timetables', 'studentstimetables_user']]);

test('preview parent access only exposes granted existing children and remains a restricted parent session', function (): void {
    $allowed = previewStudentAccount();
    $child = previewParentChild($allowed);
    $blocked = previewParentChild(previewStudentAccount(allowed: false));
    $unprovisioned = previewParentChild();
    $otherParentChild = previewParentChild(previewStudentAccount(), 'other-parent@example.test');

    $this->postJson('/api/homepage/student/login_step_code', previewParentCodePayload(previewParentLoginCode()))
        ->assertOk()->assertJsonCount(1, 'students')->assertJsonPath('students.0.id', $child->id);

    foreach ([$blocked, $unprovisioned, $otherParentChild] as $unavailableChild) {
        $this->postJson('/api/homepage/student/login_step_parent_student', ['student_import_id' => $unavailableChild->id])->assertForbidden();
    }
    $this->postJson('/api/homepage/student/login_step_parent_student', ['student_import_id' => $child->id])
        ->assertOk()->assertJsonPath('viewer_type', 'parent')->assertJsonPath('user.id', $allowed->id);
    $this->assertGuest();
    $this->getJson('/api/homepage/student/user')->assertOk()->assertJsonPath('user.id', $allowed->id);
    $this->postJson('/api/homepage/student/change_password', [
        'new_password' => 'unauthorized-change',
        'confirm_password' => 'unauthorized-change',
    ])->assertUnauthorized();
    $this->getJson('/api/admin/feature-preview')->assertUnauthorized();
    $this->getJson('/api/homepage/students-timetables/user')->assertOk()->assertJsonPath('user', null);
    $this->getJson('/api/homepage/students-timetables/overview')->assertUnauthorized();

    User::on('preview_control')->whereKey($allowed->id)->update(['feature_preview_allowed' => false]);
    $this->getJson('/api/homepage/student/parent_students')->assertUnauthorized();
    expect(app(ParentStudentAccessService::class)->currentStudent())->toBeNull()
        ->and($unprovisioned->fresh()->user_id)->toBeNull()
        ->and(Hash::check('student-password', $allowed->fresh()->password))->toBeTrue();
});

test('preview rechecks parent eligibility before consuming the challenge', function (): void {
    $user = previewStudentAccount();
    previewParentChild($user);
    $code = previewParentLoginCode();
    $challenge = session('student_parent_access');
    User::on('preview_control')->whereKey($user->id)->update(['feature_preview_allowed' => false]);

    $this->postJson('/api/homepage/student/login_step_code', previewParentCodePayload($code))->assertForbidden();

    expect(session('student_parent_access.token_hash'))->toBe($challenge['token_hash'])
        ->and(session('student_parent_access.verified_at'))->toBeNull();
    $this->assertGuest();
});

test('preview parent sessions lose access when the live contact or teaching scope changes', function (string $change): void {
    $user = previewStudentAccount();
    $child = previewParentChild($user);
    $this->postJson('/api/homepage/student/login_step_code', previewParentCodePayload(previewParentLoginCode()))->assertOk();
    $this->postJson('/api/homepage/student/login_step_parent_student', ['student_import_id' => $child->id])->assertOk();
    $this->getJson('/api/homepage/student/user')->assertOk()->assertJsonPath('user.id', $user->id);

    $control = DB::connection('preview_control');
    match ($change) {
        'contact' => $control->table('import116')->where('id', $child->id)->update(['mother_email' => 'other@example.test']),
        'adult' => $control->table('import116')->where('id', $child->id)->update(['birth_date' => today()->subYearsNoOverflow(18)->toDateString()]),
        'course canceled' => $control->table('teaching_course_students')->update(['canceled_at' => now()]),
        'year changed' => $control->table('school_tools')->update(['active_schoolyear_id' => $this->schoolyear->id + 1]),
    };

    $this->getJson('/api/homepage/student/parent_students')->assertUnauthorized();
    expect(app(ParentStudentAccessService::class)->currentStudent())->toBeNull()
        ->and($child->fresh()->mother_email)->toBe('parent@example.test');
})->with(['contact', 'adult', 'course canceled', 'year changed']);

test('preview refuses a stale live parent contact before consuming its challenge', function (): void {
    $child = previewParentChild(previewStudentAccount());
    $code = previewParentLoginCode();
    $challengeHash = session('student_parent_access.token_hash');
    DB::connection('preview_control')->table('import116')->where('id', $child->id)->update(['mother_email' => 'other@example.test']);

    $this->postJson('/api/homepage/student/login_step_code', previewParentCodePayload($code))->assertForbidden();

    expect(session('student_parent_access.token_hash'))->toBe($challengeHash)
        ->and(session('student_parent_access.verified_at'))->toBeNull();
});

test('preview prevents parent challenges without any granted child', function (): void {
    previewParentChild(previewStudentAccount(allowed: false));
    previewParentChild();

    $this->postJson('/api/homepage/student/login_step_email', [
        'type' => 'login_without_password',
        'school_id' => $this->school->id,
        'email' => 'parent@example.test',
    ])->assertForbidden();

    expect(session()->has('student_parent_access'))->toBeFalse();
    Notification::assertNothingSent();
});

test('preview allows a granted student to consume a valid code', function (string $area, string $role): void {
    $user = previewStudentAccount($role);
    previewStudentImport($user);
    $this->postJson("/api/homepage/{$area}/login_step_email", [
        'type' => 'login_without_password',
        'school_id' => $this->school->id,
        'email' => $user->email,
    ])->assertOk();

    $this->postJson("/api/homepage/{$area}/login_step_code", [
        'type' => 'login_without_password',
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => $user->email,
        'login_code' => $user->fresh()->token_2fa,
    ])->assertOk()->assertJsonPath('status', 'login_ok');

    $this->assertAuthenticatedAs($user);
    expect($user->fresh()->token_2fa)->toBeNull();
})->with(['teaching' => ['student', 'student'], 'timetables' => ['students-timetables', 'studentstimetables_user']]);

test('preview rejects a revoked child at selection without synchronizing its account', function (): void {
    $user = previewStudentAccount();
    $child = previewParentChild($user);
    $originalFirstName = $user->first_name;
    $this->postJson('/api/homepage/student/login_step_code', previewParentCodePayload(previewParentLoginCode()))->assertOk();
    $child->update(['first_name' => 'Must not synchronize']);
    User::on('preview_control')->whereKey($user->id)->update(['feature_preview_allowed' => false]);

    $this->postJson('/api/homepage/student/login_step_parent_student', ['student_import_id' => $child->id])->assertForbidden();

    expect($user->fresh()->first_name)->toBe($originalFirstName)
        ->and(session('student_parent_access.student_import_id'))->toBeNull();
    $this->assertGuest();
});

test('main still provisions imported student accounts without a preview grant', function (string $area, string $role): void {
    config(['schooltool.preview.instance' => false]);
    $student = previewStudentImport();

    $this->postJson("/api/homepage/{$area}/login_step_email", [
        'type' => 'login_without_password',
        'school_id' => $this->school->id,
        'email' => $student->email,
    ])->assertOk()->assertJsonPath('status', 'code_sent');

    $user = User::query()->findOrFail($student->fresh()->user_id);
    expect($user->hasRole($role))->toBeTrue()
        ->and($user->feature_preview_allowed)->toBeFalse();
    Notification::assertSentOnDemand(StandardEmail::class);
})->with(['teaching' => ['student', 'student'], 'timetables' => ['students-timetables', 'studentstimetables_user']]);

test('main still allows parents to select an unprovisioned child without a preview grant', function (): void {
    config(['schooltool.preview.instance' => false]);
    $child = previewParentChild();

    $this->postJson('/api/homepage/student/login_step_code', previewParentCodePayload(previewParentLoginCode()))
        ->assertOk()->assertJsonCount(1, 'students');
    $this->postJson('/api/homepage/student/login_step_parent_student', ['student_import_id' => $child->id])
        ->assertOk()->assertJsonPath('viewer_type', 'parent');

    $this->assertGuest();
    expect($child->fresh()->user_id)->not->toBeNull();
});
