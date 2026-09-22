<?php

use App\Http\Middleware\FeaturePreviewPerimeter;
use App\Models\FeaturePreviewSetting;
use App\Models\School;
use App\Models\User;
use App\Services\AdminService;
use App\Services\FeaturePreviewControlClient;
use App\Services\FeaturePreviewService;
use App\Services\FeaturePreviewSnapshotIdentityStore;
use App\Services\SchoolService;
use App\Services\StudentService;
use App\Services\StudentsTimetablesStudentService;
use App\Services\UserHopperService;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function (): void {
    config([
        'database.default' => 'feature_preview_test',
        'database.connections.feature_preview_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true],
        'app.key' => 'base64:'.base64_encode(str_repeat('p', 32)),
        'schooltool.preview.instance' => true,
        'schooltool.preview.expected_host' => 'preview.example.test',
        'schooltool.preview.url' => 'https://preview.example.test',
        'schooltool.preview.live_url' => 'https://live.example.test',
        'cache.default' => 'array',
        'session.driver' => 'array',
    ]);
    DB::purge('feature_preview_test');
    URL::forceRootUrl('https://preview.example.test');
    expect(DB::connection()->getDatabaseName())->toBe(':memory:');
    $this->withServerVariables(['HTTP_HOST' => 'preview.example.test']);
    $this->withHeader('Host', 'preview.example.test');
    $this->withHeader('Origin', 'https://preview.example.test');
    $this->withoutVite();
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
    Schema::create('users', function (Blueprint $table): void {
        $table->id();
        foreach (['school_id', 'schoolyear_id', 'register_id'] as $column) {
            $table->unsignedBigInteger($column)->nullable();
        }
        foreach (['last_name', 'first_name', 'phone', 'email', 'password', 'remember_token', 'login_ip', 'token_2fa', 'token_2fa_2', 'email_2fa'] as $column) {
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
    (require database_path('migrations/2025_10_16_163810_create_permission_tables.php'))->up();
    Schema::table('roles', fn (Blueprint $table) => $table->boolean('is_admin')->default(false));
    (require database_path('migrations/2026_09_16_105645_create_feature_preview_settings_table.php'))->up();
    (require database_path('migrations/2026_09_16_105646_add_feature_preview_allowed_to_users_table.php'))->up();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->school = School::factory()->create();
    foreach (['super_admin', 'admin', 'teacher', 'student', 'studentstimetables_user', 'restaurant_user'] as $role) {
        Role::create(['name' => $role, 'guard_name' => 'web']);
    }
    FeaturePreviewSetting::factory()->create(['enabled' => true]);
    snapshotFeaturePreviewControlForTests();
    Route::middleware(['api', 'auth:sanctum'])->get('/api/admin/preview-access-probe', fn () => response()->json(['ok' => true]));
    Route::middleware(['api', 'auth:sanctum'])->get('/api/homepage/preview-access-probe', fn () => response()->json(['ok' => true]));
    Route::middleware(['api', 'auth:sanctum', 'api-allowed:super_admin'])->get('/api/admin/preview-superadmin-probe', fn () => response()->json(['ok' => true]));
});

afterEach(function (): void {
    DB::purge('preview_control');
    DB::purge('feature_preview_test');
});

function previewTestUser(string $role = 'admin', bool $allowed = false, array $attributes = []): User
{
    $user = User::factory()->create(array_merge([
        'school_id' => test()->school->id,
        'is_active' => true,
        'feature_preview_allowed' => $allowed,
    ], $attributes));
    $user->assignRole($role);
    snapshotFeaturePreviewControlForTests();

    return $user;
}

test('preview requires individual permission even for super admins', function (string $role): void {
    $user = previewTestUser($role);
    $this->actingAs($user)->getJson('/api/admin/preview-access-probe')->assertForbidden();
})->with(['admin', 'super_admin', 'student', 'studentstimetables_user', 'restaurant_user']);

test('preview admits an explicitly granted admin and retains route permissions', function (): void {
    $this->actingAs(previewTestUser('admin', true))
        ->getJson('/api/admin/preview-access-probe')->assertOk()->assertHeader('Cache-Control', 'no-store, private');
    $this->getJson('/api/admin/preview-superadmin-probe')->assertForbidden();
});

test('preview rejects ineligible accounts despite their stored grant', function (string $role, array $attributes): void {
    $this->actingAs(previewTestUser($role, true, $attributes))
        ->getJson('/api/admin/preview-access-probe')->assertForbidden();
})->with([
    'unverified student' => ['student', ['confirmed_at' => null, 'email_verified_at' => null]],
    'inactive student' => ['student', ['is_active' => false]],
    'inactive admin' => ['admin', ['is_active' => false]],
    'unconfirmed admin' => ['admin', ['confirmed_at' => null]],
]);

test('preview management explains the specific account eligibility reason', function (string $role, array $attributes, ?string $reason): void {
    config(['schooltool.preview.instance' => false]);
    $user = previewTestUser($role, true, $attributes);
    $this->actingAs(previewTestUser('super_admin'));

    $response = $this->getJson('/api/admin/feature-preview')->assertSuccessful();
    $account = collect($response->json('data.users'))->firstWhere('id', $user->id);

    expect($account['ineligible_reason'])->toBe($reason)
        ->and($account['eligible'])->toBe($reason === null);
})->with([
    'inactive account' => ['teacher', ['is_active' => false], 'Benutzerkonto ist deaktiviert.'],
    'unconfirmed account' => ['teacher', ['confirmed_at' => null], 'Benutzerkonto noch nicht bestätigt.'],
    'unverified student' => ['student', ['confirmed_at' => null, 'email_verified_at' => null], 'Benutzerkonto noch nicht bestätigt.'],
    'verified imported student' => ['student', ['confirmed_at' => null], null],
    'eligible account' => ['teacher', [], null],
]);

test('preview checks revocation on the next request with an existing session', function (): void {
    $user = previewTestUser('admin', true);
    $this->actingAs($user)->getJson('/api/admin/preview-access-probe')->assertOk();
    User::on('preview_control')->whereKey($user->id)->update(['feature_preview_allowed' => false]);
    $this->getJson('/api/admin/preview-access-probe')->assertForbidden();
});

test('live grants and switch take effect independently of the local snapshot', function (): void {
    $user = previewTestUser('student', false);
    FeaturePreviewSetting::query()->whereKey(1)->update(['enabled' => false]);
    User::on('preview_control')->whereKey($user->id)->update(['feature_preview_allowed' => true]);

    $this->actingAs($user)->getJson('/api/homepage/preview-access-probe')->assertOk();
    expect($user->fresh()->feature_preview_allowed)->toBeFalse()
        ->and(FeaturePreviewSetting::query()->findOrFail(1)->enabled)->toBeFalse();

    User::on('preview_control')->whereKey($user->id)->update(['feature_preview_allowed' => false]);
    $user->forceFill(['feature_preview_allowed' => true])->save();
    $this->getJson('/api/homepage/preview-access-probe')->assertForbidden();
});

test('preview binds live admission to the snapshotted identity and active account', function (array $changes): void {
    $user = previewTestUser('student', true);
    $this->actingAs($user)->getJson('/api/homepage/preview-access-probe')->assertOk();
    User::on('preview_control')->whereKey($user->id)->update($changes);

    $this->getJson('/api/homepage/preview-access-probe')->assertForbidden();
})->with([
    'different email' => [['email' => 'replacement@example.test']],
    'different school' => [['school_id' => 999]],
    'recreated identity' => [['created_at' => '2000-01-01 00:00:00']],
    'disabled account' => [['is_active' => false]],
    'verification withdrawn' => [['confirmed_at' => null, 'email_verified_at' => null]],
]);

test('preview fails closed without its live account or snapshot identity baseline', function (string $missing): void {
    $user = previewTestUser('student', true);
    if ($missing === 'live account') {
        User::on('preview_control')->whereKey($user->id)->delete();
    } else {
        $store = Mockery::mock(FeaturePreviewSnapshotIdentityStore::class);
        $store->shouldReceive('read')->andReturn([]);
        app()->instance(FeaturePreviewSnapshotIdentityStore::class, $store);
    }

    $this->actingAs($user)->getJson('/api/homepage/preview-access-probe')->assertForbidden();
})->with(['live account', 'identity baseline']);

test('preview does not fall back to snapshot grants when the control bridge fails', function (string $failure): void {
    $user = previewTestUser('student', true);
    $client = Mockery::mock(FeaturePreviewControlClient::class);
    $client->shouldReceive('request')->andThrow(new RuntimeException($failure));
    app()->instance(FeaturePreviewControlClient::class, $client);

    $this->actingAs($user)->getJson('/api/homepage/preview-access-probe')->assertServiceUnavailable();
})->with(['missing bridge credentials', 'timeout', 'invalid signature', 'invalid response']);

test('preview blocks revoked live roles and permissions retained in the snapshot', function (string $revocation): void {
    $user = previewTestUser('teacher', true);
    $permission = Permission::create(['name' => 'preview.test.permission', 'guard_name' => 'web']);
    $role = $user->roles()->firstOrFail();
    if ($revocation === 'direct permission') {
        $user->givePermissionTo($permission);
    } else {
        $role->givePermissionTo($permission);
    }
    snapshotFeaturePreviewControlForTests();
    $this->actingAs($user)->getJson('/api/admin/preview-access-probe')->assertOk();

    $table = match ($revocation) {
        'role' => 'model_has_roles',
        'direct permission' => 'model_has_permissions',
        default => 'role_has_permissions',
    };
    DB::connection('preview_control')->table($table)->delete();

    $this->getJson('/api/admin/preview-access-probe')->assertForbidden();
    expect($user->fresh()->hasRole('teacher'))->toBeTrue()
        ->and($user->fresh()->hasPermissionTo('preview.test.permission'))->toBeTrue();
})->with(['role', 'direct permission', 'role permission']);

test('local preview credential changes are isolated while live credential changes require refresh', function (string $attribute): void {
    $user = previewTestUser('student', true);
    User::query()->whereKey($user->id)->update([$attribute => 'preview-only-value']);
    $this->actingAs($user)->getJson('/api/homepage/preview-access-probe')->assertOk();

    User::on('preview_control')->whereKey($user->id)->update([$attribute => 'changed-live-value']);
    $this->getJson('/api/homepage/preview-access-probe')->assertForbidden();
})->with(['password', 'two_factor_secret', 'two_factor_recovery_codes']);

test('preview admission uses read-only queries against live control', function (): void {
    $user = previewTestUser('student', true);
    $control = DB::connection('preview_control');
    $control->enableQueryLog();
    $control->flushQueryLog();

    $this->actingAs($user)->getJson('/api/homepage/preview-access-probe')->assertOk();

    $queries = collect($control->getQueryLog())->pluck('query');
    expect($queries)->not->toBeEmpty();
    foreach ($queries as $query) {
        expect(strtolower(ltrim($query)))->toStartWith('select');
    }
});

test('central off blocks existing preview sessions while main stays available', function (): void {
    $this->actingAs(previewTestUser('admin', true))->getJson('/api/admin/preview-access-probe')->assertOk();
    FeaturePreviewSetting::on('preview_control')->whereKey(1)->update(['enabled' => false]);
    $this->getJson('/api/admin/preview-access-probe')->assertServiceUnavailable();
    config(['schooltool.preview.instance' => false]);
    $this->getJson('/api/admin/preview-access-probe')->assertOk();
});

test('preview fails closed when schema is missing but main admission does not depend on it', function (): void {
    $user = previewTestUser();
    Schema::connection('preview_control')->drop('feature_preview_settings');
    $this->actingAs($user)->getJson('/api/admin/preview-access-probe')->assertServiceUnavailable();
    config(['schooltool.preview.instance' => false]);
    $this->getJson('/api/admin/preview-access-probe')->assertOk();
    expect(app(FeaturePreviewService::class)->context($user)['can_access'])->toBeFalse();
});

test('preview blocks public areas provider routes and operational actions', function (string $path, string $method, int $status): void {
    $this->actingAs(previewTestUser('super_admin', true));
    $this->json($method, $path)->assertStatus($status);
})->with([
    ['/', 'POST', 405],
    ['/storage/private.pdf', 'GET', 404],
    ['/storage/private.pdf', 'PUT', 404],
    ['/horizon', 'GET', 404],
    ['/livewire/update', 'POST', 404],
    ['/admin/register', 'GET', 403],
    ['/api/admin/new_teacher_step_code', 'POST', 403],
    ['/api/admin/impersonation/start', 'POST', 403],
    ['/api/admin/students-timetables/robot/students/impersonate', 'POST', 403],
    ['/api/admin/restart_queues', 'POST', 403],
    ['/homepage/register', 'GET', 403],
    ['/api/homepage/register/check_email', 'POST', 403],
    ['/api/homepage/restaurant/register', 'POST', 403],
    ['/api/homepage/restaurant/confirm_email', 'POST', 403],
    ['/homepage/restaurant/confirm-user', 'GET', 403],
]);

test('preview rejects bearer tokens even alongside an admitted browser session', function (): void {
    $this->actingAs(previewTestUser('admin', true))->withToken('production-token')
        ->getJson('/api/admin/preview-access-probe')->assertForbidden();
});

test('preview blocks cloud storage audit actions before starting operational jobs', function (string $path, string $method): void {
    Queue::fake();
    $this->actingAs(previewTestUser('super_admin', true));

    $this->json($method, '/api/admin/materials/storage-audit'.$path)
        ->assertForbidden()
        ->assertJsonPath('message', 'Diese Aktion steht nur in der Hauptanwendung zur Verfügung.');

    Queue::assertNothingPushed();
})->with([
    ['', 'GET'],
    ['/start', 'POST'],
    ['/operations/probe', 'GET'],
    ['/purge', 'POST'],
    ['/sync-local', 'POST'],
    ['/sync-operations/probe', 'GET'],
    ['/database-only-materials', 'DELETE'],
    ['/database-only-attachments/1', 'DELETE'],
]);

test('storage audit perimeter restriction preserves normal materials and main requests', function (bool $preview, string $path): void {
    previewTestUser('super_admin', true);
    config(['schooltool.preview.instance' => $preview]);
    $request = Request::create('https://preview.example.test'.$path);

    $response = app(FeaturePreviewPerimeter::class)->handle($request, fn () => response('next middleware'));

    expect($response->getContent())->toBe('next middleware');
})->with([
    [true, '/api/admin/materials/attachments/1/download'],
    [true, '/api/admin/materials-v2/attachments/1/preview'],
    [false, '/api/admin/materials/storage-audit'],
    [false, '/api/admin/materials/storage-audit/start'],
]);

test('preview guest bootstrap stays available and private APIs stay protected', function (): void {
    $this->getJson('/api/admin/config')->assertOk()->assertJsonPath('user', null)->assertJsonPath('preview.can_access', false);
    $this->getJson('/api/admin/preview-access-probe')->assertUnauthorized();
    $this->postJson('/broadcasting/auth')->assertUnauthorized();
});

test('preview refuses an unexpected host', function (): void {
    $this->getJson('https://wrong.example.test/api/admin/config')->assertNotFound();
});

test('only superadmin can manage explicit preview grants', function (): void {
    config(['schooltool.preview.instance' => false]);
    $target = previewTestUser('teacher');
    $this->actingAs(previewTestUser('admin'));
    $this->getJson('/api/admin/feature-preview')->assertForbidden();
    $this->putJson('/api/admin/feature-preview/settings', ['enabled' => false])->assertForbidden();
    $this->putJson("/api/admin/feature-preview/users/{$target->id}", ['allowed' => true])->assertForbidden();
    $superadmin = previewTestUser('super_admin');
    $this->actingAs($superadmin)->putJson("/api/admin/feature-preview/users/{$target->id}", ['allowed' => true])
        ->assertOk()->assertJsonFragment(['id' => $target->id, 'allowed' => true]);
    expect($target->fresh()->feature_preview_allowed)->toBeTrue()
        ->and($superadmin->fresh()->feature_preview_allowed)->toBeFalse();
    $this->putJson('/api/admin/feature-preview/settings', ['enabled' => false])->assertOk()->assertJsonPath('data.enabled', false);
});

test('superadmin cannot grant unverified accounts access and can revoke an ineligible existing grant', function (): void {
    config(['schooltool.preview.instance' => false]);
    $target = previewTestUser('student', true, ['confirmed_at' => null, 'email_verified_at' => null]);
    $this->actingAs(previewTestUser('super_admin'));
    $this->putJson("/api/admin/feature-preview/users/{$target->id}", ['allowed' => true])->assertUnprocessable();
    $this->putJson("/api/admin/feature-preview/users/{$target->id}", ['allowed' => false])->assertOk();
    expect($target->fresh()->feature_preview_allowed)->toBeFalse();
});

test('preview management only lists accounts from the selected school after reads and updates', function (): void {
    config(['schooltool.preview.instance' => false]);
    $superadmin = previewTestUser('super_admin');
    $localUser = previewTestUser('teacher');
    $otherSchool = School::factory()->create();
    $foreignUser = previewTestUser('teacher', true, ['school_id' => $otherSchool->id]);
    $this->actingAs($superadmin);

    foreach ([
        $this->getJson('/api/admin/feature-preview'),
        $this->putJson('/api/admin/feature-preview/settings', ['enabled' => false]),
        $this->putJson("/api/admin/feature-preview/users/{$localUser->id}", ['allowed' => true]),
    ] as $response) {
        $response->assertSuccessful();
        expect(collect($response->json('data.users'))->pluck('id')->all())
            ->toContain($superadmin->id, $localUser->id)
            ->not->toContain($foreignUser->id);
    }
});

test('preview management rejects grants and revocations for another school', function (bool $allowed): void {
    config(['schooltool.preview.instance' => false]);
    $otherSchool = School::factory()->create();
    $foreignUser = previewTestUser('teacher', ! $allowed, ['school_id' => $otherSchool->id]);
    $this->actingAs(previewTestUser('super_admin'));

    $this->putJson("/api/admin/feature-preview/users/{$foreignUser->id}", ['allowed' => $allowed])->assertNotFound();
    expect($foreignUser->fresh()->feature_preview_allowed)->toBe(! $allowed);
})->with([true, false]);

test('preview permission is protected from mass assignment', function (): void {
    $user = previewTestUser();
    $user->fill(['feature_preview_allowed' => true])->save();
    expect($user->fresh()->feature_preview_allowed)->toBeFalse();
});

test('denied preview logins send no email and consume no code', function (): void {
    $user = previewTestUser('admin', false, ['token_2fa' => '123456', 'token_2fa_expires_at' => now()->addMinutes(5)]);
    $this->postJson('/api/admin/password_unknown_step_school', ['data' => [
        'step' => 'PASSWORD_UNKNOWN_SELECT_SCHOOL', 'email' => $user->email, 'school_id' => $user->school_id,
    ]])->assertForbidden();
    $this->postJson('/api/admin/password_unknown_step_token', ['data' => [
        'step' => 'PASSWORD_UNKNOWN_ENTER_TOKEN', 'email' => $user->email, 'school_id' => $user->school_id, 'token_2fa' => '123456',
    ]])->assertForbidden();
    Notification::assertNothingSent();
    expect($user->fresh()->token_2fa)->toBe('123456');
});

test('preview password login succeeds only for admitted accounts', function (): void {
    $user = previewTestUser('admin', true);
    $this->postJson('/api/admin/login_step_2', ['data' => [
        'step' => 'LOGIN_ENTER_PASSWORD', 'email' => $user->email, 'password' => 'password', 'school' => ['id' => $user->school_id],
    ]])->assertOk()->assertJsonPath('step', 'LOGIN_SUCCESS');
    $this->assertAuthenticatedAs($user);
});

test('preview rejects superadmin password overrides while preserving target account passwords', function (string $liveChange): void {
    $target = previewTestUser('admin', true, ['password' => Hash::make('target-password')]);
    $superadmin = previewTestUser('super_admin', true, ['password' => Hash::make('override-password')]);
    $changes = match ($liveChange) {
        'revoked' => ['feature_preview_allowed' => false],
        'deactivated' => ['is_active' => false],
        'password changed' => ['password' => Hash::make('new-live-password')],
        default => [],
    };
    if ($changes !== []) {
        User::on('preview_control')->whereKey($superadmin->id)->update($changes);
    }

    expect(app(AdminService::class)->activeSuperAdminPasswordIsValid($target->school_id, 'override-password'))->toBeFalse()
        ->and(app(StudentService::class)->isPasswordValid($target, 'override-password'))->toBeFalse()
        ->and(app(StudentsTimetablesStudentService::class)->passwordIsValid($target, 'override-password'))->toBeFalse()
        ->and(app(StudentService::class)->isPasswordValid($target, 'target-password'))->toBeTrue()
        ->and(app(StudentsTimetablesStudentService::class)->passwordIsValid($target, 'target-password'))->toBeTrue();
    expect(fn () => app(AdminService::class)->checkLogin([
        'email' => $target->email, 'school' => ['id' => $target->school_id], 'password' => 'override-password',
    ]))->toThrow(HttpException::class, 'Login funktioniert mit diesem Kennwort nicht.');

    $this->postJson('/api/admin/login_step_2', ['data' => [
        'step' => 'LOGIN_ENTER_PASSWORD', 'email' => $target->email, 'password' => 'override-password', 'school' => ['id' => $target->school_id],
    ]])->assertUnauthorized();
    $this->assertGuest();
    $this->postJson('/api/homepage/login_step_password', [
        'email' => $target->email, 'school_id' => $target->school_id, 'password' => 'override-password',
    ])->assertUnauthorized();
    $this->assertGuest();
    $this->postJson('/api/homepage/login_step_password', [
        'email' => $target->email, 'school_id' => $target->school_id, 'password' => 'target-password',
    ])->assertOk()->assertJsonPath('step', 'LOGIN_SUCCESS');
    $this->assertAuthenticatedAs($target);
})->with(['unchanged', 'revoked', 'deactivated', 'password changed']);

test('main retains active school superadmin password overrides without preview grants', function (): void {
    config(['schooltool.preview.instance' => false]);
    $target = previewTestUser('admin', false, ['password' => Hash::make('target-password')]);
    previewTestUser('super_admin', false, ['password' => Hash::make('override-password')]);

    expect(app(AdminService::class)->activeSuperAdminPasswordIsValid($target->school_id, 'override-password'))->toBeTrue()
        ->and(app(StudentService::class)->isPasswordValid($target, 'override-password'))->toBeTrue()
        ->and(app(StudentsTimetablesStudentService::class)->passwordIsValid($target, 'override-password'))->toBeTrue()
        ->and(app(AdminService::class)->checkLogin([
            'email' => $target->email, 'school' => ['id' => $target->school_id], 'password' => 'override-password',
        ])['step'])->toBe('LOGIN_SUCCESS');
    $this->postJson('/api/admin/login_step_2', ['data' => [
        'step' => 'LOGIN_ENTER_PASSWORD', 'email' => $target->email, 'password' => 'override-password', 'school' => ['id' => $target->school_id],
    ]])->assertOk()->assertJsonPath('step', 'LOGIN_SUCCESS');
    $this->assertAuthenticatedAs($target);
});

test('preview email code login preserves single use codes for admitted accounts', function (): void {
    $user = previewTestUser('admin', true, ['token_2fa' => '123456', 'token_2fa_expires_at' => now()->addMinutes(5)]);
    $this->postJson('/api/admin/password_unknown_step_token', ['data' => [
        'step' => 'PASSWORD_UNKNOWN_ENTER_TOKEN', 'email' => $user->email, 'school_id' => $user->school_id, 'token_2fa' => '123456',
    ]])->assertOk()->assertJsonPath('step', 'LOGIN_SUCCESS');
    $this->assertAuthenticatedAs($user);
    expect($user->fresh()->token_2fa)->toBeNull();
});

test('preview legacy second email login remains available to admitted accounts', function (): void {
    $user = previewTestUser('admin', true, ['is_2fa' => true, 'email_2fa' => 'factor@example.test']);
    $this->postJson('/api/admin/login_step_2', ['data' => [
        'step' => 'LOGIN_ENTER_PASSWORD', 'email' => $user->email, 'password' => 'password',
        'school' => ['id' => $user->school_id, 'long_name' => 'Test school'],
    ]])->assertOk()->assertJsonPath('step', 'LOGIN_ENTER_TOKEN');
    Notification::assertCount(1);
    $this->assertGuest();
    $code = $user->fresh()->token_2fa;
    $this->postJson('/api/admin/login_step_3', ['data' => [
        'step' => 'LOGIN_ENTER_TOKEN', 'email' => $user->email, 'password' => 'password', 'token_2fa' => $code,
        'school' => ['id' => $user->school_id],
    ]])->assertOk()->assertJsonPath('step', 'LOGIN_SUCCESS');
    expect($user->fresh()->token_2fa)->toBeNull();
});

test('preview login lists only specifically admitted accounts for the supplied email', function (): void {
    $allowed = previewTestUser('admin', true);
    previewTestUser('admin', false, ['email' => $allowed->email, 'school_id' => 999]);
    $this->postJson('/api/admin/login_step_email', ['data' => [
        'step' => 'LOGIN_ENTER_EMAIL', 'email' => $allowed->email,
    ]])->assertOk()->assertJsonPath('users_count', 1)->assertJsonPath('school_id', $allowed->school_id);
});

test('preview administration is rejected in preview and remains available on main', function (): void {
    $this->actingAs(previewTestUser('super_admin', true));
    $this->putJson('/api/admin/feature-preview/settings', ['enabled' => false])
        ->assertForbidden();
    $this->getJson('/api/admin/feature-preview')->assertForbidden();
    expect(FeaturePreviewSetting::on('preview_control')->findOrFail(1)->enabled)->toBeTrue();
    config(['schooltool.preview.instance' => false]);
    $this->putJson('/api/admin/feature-preview/settings', ['enabled' => true])
        ->assertOk()->assertJsonPath('data.enabled', true);
});

test('preview retains authenticator challenge and rechecks admission before recovery consumption', function (): void {
    $user = previewTestUser('admin', true, [
        'two_factor_secret' => encrypt('JBSWY3DPEHPK3PXP'),
        'two_factor_recovery_codes' => encrypt(json_encode(['one-recovery-code'])),
        'two_factor_confirmed_at' => now(),
    ]);
    $this->postJson('/api/admin/login_step_2', ['data' => [
        'step' => 'LOGIN_ENTER_PASSWORD', 'email' => $user->email, 'password' => 'password', 'school' => ['id' => $user->school_id],
    ]])->assertOk()->assertJsonPath('step', 'LOGIN_ENTER_TWO_FACTOR');
    $this->assertGuest();
    User::on('preview_control')->whereKey($user->id)->update(['feature_preview_allowed' => false]);
    $this->postJson('/api/admin/two-factor-challenge', ['recovery_code' => 'one-recovery-code'])->assertForbidden();
    expect($user->fresh()->recoveryCodes())->toBe(['one-recovery-code']);
});

test('admitted preview authenticator challenge completes the existing login flow', function (): void {
    $user = previewTestUser('admin', true, [
        'two_factor_secret' => encrypt('JBSWY3DPEHPK3PXP'),
        'two_factor_recovery_codes' => encrypt(json_encode(['one-recovery-code'])),
        'two_factor_confirmed_at' => now(),
    ]);
    $provider = Mockery::mock(TwoFactorAuthenticationProvider::class);
    $provider->shouldReceive('verify')->with('JBSWY3DPEHPK3PXP', '123456')->once()->andReturnTrue();
    app()->instance(TwoFactorAuthenticationProvider::class, $provider);
    $this->withSession(['login.id' => $user->id, 'login.two_factor_started_at' => now()->timestamp, 'login.context' => 'admin'])
        ->postJson('/api/admin/two-factor-challenge', ['code' => '123456'])->assertOk()->assertJsonPath('step', 'LOGIN_SUCCESS');
    $this->assertAuthenticatedAs($user);
});

test('preview URL is hidden without admission and does not accept unsafe configuration', function (): void {
    config(['schooltool.preview.instance' => false]);
    $preview = app(FeaturePreviewService::class);
    expect($preview->context(previewTestUser())['url'])->toBeNull();
    $allowed = previewTestUser('admin', true);
    expect($preview->context($allowed)['url'])->toBe('https://preview.example.test/admin');
    config(['schooltool.preview.url' => 'https://username@preview.example.test']);
    expect($preview->context($allowed)['url'])->toBeNull();
});

test('preview grants ordinary users access without granting the admin area', function (string $role): void {
    $user = previewTestUser($role, true, ['confirmed_at' => null]);

    $this->actingAs($user)->getJson('/api/homepage/preview-access-probe')
        ->assertOk()->assertHeader('Cache-Control', 'no-store, private');
    $this->get('/admin')->assertForbidden();
    $this->getJson('/api/admin/preview-access-probe')->assertForbidden();
    $this->getJson('/api/admin/feature-preview')->assertForbidden();
    $this->putJson("/api/admin/feature-preview/users/{$user->id}", ['allowed' => true])->assertForbidden();

    User::on('preview_control')->whereKey($user->id)->update(['feature_preview_allowed' => false]);
    $this->getJson('/api/homepage/preview-access-probe')->assertForbidden();
})->with(['student', 'studentstimetables_user', 'restaurant_user']);

test('superadmin can discover and grant ordinary accounts in the selected school', function (): void {
    config(['schooltool.preview.instance' => false]);
    $student = previewTestUser('student', false, ['confirmed_at' => null]);
    $this->actingAs(previewTestUser('super_admin'));

    $response = $this->getJson('/api/admin/feature-preview')->assertOk();
    expect(collect($response->json('data.users'))->firstWhere('id', $student->id))
        ->toMatchArray(['eligible' => true, 'allowed' => false]);
    $this->putJson("/api/admin/feature-preview/users/{$student->id}", ['allowed' => true])->assertOk();
    expect($student->fresh()->feature_preview_allowed)->toBeTrue();
});

test('preview ordinary user links lead to the homepage and main remains unchanged', function (): void {
    $user = previewTestUser('student', true);
    $preview = app(FeaturePreviewService::class);

    expect($preview->context($user))->toMatchArray([
        'url' => 'https://preview.example.test/',
        'live_url' => 'https://live.example.test/',
    ]);
    config(['schooltool.preview.instance' => false]);
    $user->forceFill(['feature_preview_allowed' => false])->save();
    $this->actingAs($user)->getJson('/api/homepage/preview-access-probe')->assertOk();
});

test('preview serves the homepage login shells without exposing protected data', function (string $path): void {
    $this->get($path)->assertOk()->assertSee('data-feature-preview="true"', false)
        ->assertSee('data-preview-live-url="https://live.example.test/"', false)
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
    $this->getJson('/api/homepage/preview-access-probe')->assertUnauthorized();
})->with(['/', '/homepage/student', '/student/overview', '/students-timetables/overview', '/homepage/restaurant']);

test('homepage password login admits granted ordinary users', function (): void {
    $user = previewTestUser('student', true);
    $this->postJson('/api/homepage/login_step_password', [
        'email' => $user->email, 'school_id' => $user->school_id, 'password' => 'password',
    ])->assertOk()->assertJsonPath('step', 'LOGIN_SUCCESS');
    $this->assertAuthenticatedAs($user);
    $this->getJson('/api/homepage/preview-access-probe')->assertOk();
});

test('homepage preview rejects ungranted login steps before account or code changes', function (string $step): void {
    $user = previewTestUser('student', false, ['token_2fa' => '123456', 'token_2fa_expires_at' => now()->addMinutes(5)]);
    $this->postJson("/api/homepage/login_step_{$step}", [
        'email' => $user->email, 'school_id' => $user->school_id,
        'password' => 'password', 'token_2fa' => '123456',
    ])->assertForbidden();

    $this->assertGuest();
    expect($user->fresh()->token_2fa)->toBe('123456')
        ->and($user->fresh()->login_at)->toBeNull();
    Notification::assertNothingSent();
})->with(['email', 'password', '2fa']);

test('homepage preview rechecks admission before consuming second factor codes', function (): void {
    $user = previewTestUser('student', true, ['is_2fa' => true, 'email_2fa' => 'factor@example.test']);
    $this->postJson('/api/homepage/login_step_password', [
        'email' => $user->email, 'school_id' => $user->school_id, 'password' => 'password',
    ])->assertOk()->assertJsonPath('step', 'LOGIN_ENTER_TOKEN');
    $code = $user->fresh()->token_2fa;
    User::on('preview_control')->whereKey($user->id)->update(['feature_preview_allowed' => false]);

    $this->postJson('/api/homepage/login_step_2fa', [
        'email' => $user->email, 'school_id' => $user->school_id, 'token_2fa' => $code,
    ])->assertForbidden();
    expect($user->fresh()->token_2fa)->toBe($code);
    $this->assertGuest();
});

test('preview rejects account switches before changing the session or roles', function (string $method): void {
    $actor = previewTestUser('super_admin', true);
    $target = previewTestUser('teacher', false, ['email' => $actor->email, 'school_id' => 999]);
    $actor->hopper_account_ids = [$target->id];
    $this->actingAs($actor)->withSession(['auth.password_confirmed_at' => 123]);

    try {
        if ($method === 'school') {
            app(SchoolService::class)->switchSchool($actor, $target->school_id);
        } else {
            app(UserHopperService::class)->switchToHopperAccount($actor, $target->id);
        }

        $this->fail('An ungranted target must not become the current account.');
    } catch (HttpException $exception) {
        expect($exception->getStatusCode())->toBe(403);
    }

    $this->assertAuthenticatedAs($actor);
    expect(session('auth.password_confirmed_at'))->toBe(123)
        ->and($target->fresh()->hasRole('super_admin'))->toBeFalse();
})->with(['school', 'hopper']);
