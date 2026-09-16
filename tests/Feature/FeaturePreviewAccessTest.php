<?php

use App\Models\FeaturePreviewSetting;
use App\Models\School;
use App\Models\User;
use App\Services\FeaturePreviewService;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

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
        foreach (['two_factor_secret', 'two_factor_recovery_codes', 'tutoring_filter'] as $column) {
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
    foreach (['super_admin', 'admin', 'teacher', 'student'] as $role) {
        Role::create(['name' => $role, 'guard_name' => 'web']);
    }
    FeaturePreviewSetting::factory()->create(['enabled' => true]);
    Route::middleware(['api', 'auth:sanctum'])->get('/api/admin/preview-access-probe', fn () => response()->json(['ok' => true]));
    Route::middleware(['api', 'auth:sanctum', 'api-allowed:super_admin'])->get('/api/admin/preview-superadmin-probe', fn () => response()->json(['ok' => true]));
});

afterEach(function (): void {
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

    return $user;
}

test('preview requires individual permission even for super admins', function (string $role): void {
    $user = previewTestUser($role);
    $this->actingAs($user)->getJson('/api/admin/preview-access-probe')->assertForbidden();
})->with(['admin', 'super_admin']);

test('preview admits an explicitly granted admin and retains route permissions', function (): void {
    $this->actingAs(previewTestUser('admin', true))
        ->getJson('/api/admin/preview-access-probe')->assertOk()->assertHeader('Cache-Control', 'no-store, private');
    $this->getJson('/api/admin/preview-superadmin-probe')->assertForbidden();
});

test('preview rejects ineligible accounts despite their stored grant', function (string $role, array $attributes): void {
    $this->actingAs(previewTestUser($role, true, $attributes))
        ->getJson('/api/admin/preview-access-probe')->assertForbidden();
})->with([
    'student' => ['student', []],
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
    'missing admin access' => ['student', [], 'Keine Berechtigung für den Admin-Bereich.'],
    'eligible account' => ['teacher', [], null],
]);

test('preview checks revocation on the next request with an existing session', function (): void {
    $user = previewTestUser('admin', true);
    $this->actingAs($user)->getJson('/api/admin/preview-access-probe')->assertOk();
    User::query()->whereKey($user->id)->update(['feature_preview_allowed' => false]);
    $this->getJson('/api/admin/preview-access-probe')->assertForbidden();
});

test('central off blocks existing preview sessions while main stays available', function (): void {
    $this->actingAs(previewTestUser('admin', true))->getJson('/api/admin/preview-access-probe')->assertOk();
    FeaturePreviewSetting::query()->whereKey(1)->update(['enabled' => false]);
    $this->getJson('/api/admin/preview-access-probe')->assertServiceUnavailable();
    config(['schooltool.preview.instance' => false]);
    $this->getJson('/api/admin/preview-access-probe')->assertOk();
});

test('preview fails closed when schema is missing but main admission does not depend on it', function (): void {
    $user = previewTestUser();
    Schema::drop('feature_preview_settings');
    $this->actingAs($user)->getJson('/api/admin/preview-access-probe')->assertServiceUnavailable();
    config(['schooltool.preview.instance' => false]);
    $this->getJson('/api/admin/preview-access-probe')->assertOk();
    expect(app(FeaturePreviewService::class)->context($user)['can_access'])->toBeFalse();
});

test('preview blocks public areas provider routes and operational actions', function (string $path, string $method, int $status): void {
    $this->actingAs(previewTestUser('super_admin', true));
    $this->json($method, $path)->assertStatus($status);
})->with([
    ['/', 'POST', 302],
    ['/homepage/restaurant', 'GET', 404],
    ['/api/homepage/config', 'GET', 404],
    ['/student/test', 'GET', 404],
    ['/storage/private.pdf', 'GET', 404],
    ['/storage/private.pdf', 'PUT', 404],
    ['/horizon', 'GET', 404],
    ['/livewire/update', 'POST', 404],
    ['/admin/register', 'GET', 403],
    ['/api/admin/new_teacher_step_code', 'POST', 403],
    ['/api/admin/impersonation/start', 'POST', 403],
    ['/api/admin/students-timetables/robot/students/impersonate', 'POST', 403],
    ['/api/admin/restart_queues', 'POST', 403],
]);

test('preview rejects bearer tokens even alongside an admitted browser session', function (): void {
    $this->actingAs(previewTestUser('admin', true))->withToken('production-token')
        ->getJson('/api/admin/preview-access-probe')->assertForbidden();
});

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

test('superadmin cannot grant student access and can revoke an ineligible existing grant', function (): void {
    config(['schooltool.preview.instance' => false]);
    $target = previewTestUser('student', true);
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

test('an admitted superadmin can disable preview and recover through main', function (): void {
    $this->actingAs(previewTestUser('super_admin', true));
    $this->putJson('/api/admin/feature-preview/settings', ['enabled' => false])
        ->assertOk()->assertJsonPath('data.enabled', false);
    $this->getJson('/api/admin/feature-preview')->assertServiceUnavailable();
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
    User::query()->whereKey($user->id)->update(['feature_preview_allowed' => false]);
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
