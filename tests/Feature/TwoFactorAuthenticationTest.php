<?php

use App\Models\School;
use App\Models\Schoolyear;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

const TWO_FACTOR_SECRET = 'JBSWY3DPEHPK3PXP';

beforeEach(function (): void {
    $this->withHeader('Origin', config('app.url'));

    $this->school = School::factory()->create();
    $this->schoolyear = Schoolyear::factory()->create(['school_id' => $this->school->id]);
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

    $this->user = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'two-factor@example.test',
        'password' => bcrypt('password'),
        'is_active' => true,
        'confirmed_at' => now(),
    ]);
    $this->user->assignRole('admin');
});

function fakeTwoFactorProvider(bool $valid = true): TwoFactorAuthenticationProvider
{
    $provider = Mockery::mock(TwoFactorAuthenticationProvider::class);
    $provider->shouldReceive('generateSecretKey')->andReturn(TWO_FACTOR_SECRET)->byDefault();
    $provider->shouldReceive('qrCodeUrl')
        ->andReturn('otpauth://totp/SchoolTool:test?secret='.TWO_FACTOR_SECRET)
        ->byDefault();
    $provider->shouldReceive('verify')->andReturn($valid)->byDefault();
    app()->instance(TwoFactorAuthenticationProvider::class, $provider);

    return $provider;
}

function confirmedTwoFactorUser(User $user, array $recoveryCodes = ['recovery-code-one']): User
{
    $user->forceFill([
        'two_factor_secret' => encrypt(TWO_FACTOR_SECRET),
        'two_factor_recovery_codes' => encrypt(json_encode($recoveryCodes)),
        'two_factor_confirmed_at' => now(),
    ])->save();

    return $user->fresh();
}

test('guests cannot access two factor management endpoints', function () {
    $this->getJson('/api/admin/two-factor-authentication')->assertUnauthorized();
    $this->postJson('/api/admin/two-factor-authentication')->assertUnauthorized();
    $this->deleteJson('/api/admin/two-factor-authentication')->assertUnauthorized();
});

test('current password confirmation is required and stored in the session', function () {
    $this->actingAs($this->user, 'web')
        ->postJson('/api/admin/confirm-password', ['password' => 'wrong-password'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('password');

    $this->assertFalse(session()->has('auth.password_confirmed_at'));

    $this->postJson('/api/admin/confirm-password', ['password' => 'password'])
        ->assertOk()
        ->assertJson(['confirmed' => true]);

    $this->assertTrue(session()->has('auth.password_confirmed_at'));
});

test('two factor management explicitly starts a session for API requests', function () {
    $this->withHeader('Origin', 'https://stateless.example')
        ->actingAs($this->user, 'web')
        ->postJson('/api/admin/confirm-password', ['password' => 'password'])
        ->assertOk()
        ->assertJson(['confirmed' => true]);

    $this->assertTrue(session()->has('auth.password_confirmed_at'));
});

test('a user can start setup but two factor remains inactive until confirmation', function () {
    fakeTwoFactorProvider();

    $this->actingAs($this->user, 'web')
        ->withSession(['auth.password_confirmed_at' => now()->timestamp])
        ->postJson('/api/admin/two-factor-authentication')
        ->assertOk()
        ->assertJson([
            'enabled' => false,
            'pending' => true,
            'manual_key' => TWO_FACTOR_SECRET,
        ])
        ->assertJsonMissingPath('url')
        ->assertHeader('Cache-Control', 'no-store, private');

    $this->user->refresh();

    expect($this->user->two_factor_secret)->not->toBe(TWO_FACTOR_SECRET)
        ->and(decrypt($this->user->two_factor_secret))->toBe(TWO_FACTOR_SECRET)
        ->and($this->user->two_factor_confirmed_at)->toBeNull()
        ->and($this->user->hasEnabledTwoFactorAuthentication())->toBeFalse();
});

test('starting an existing pending setup does not rotate its secret', function () {
    fakeTwoFactorProvider();
    $encryptedSecret = encrypt(TWO_FACTOR_SECRET);
    $this->user->forceFill([
        'two_factor_secret' => $encryptedSecret,
        'two_factor_recovery_codes' => encrypt(json_encode(['pending-recovery'])),
    ])->save();

    $this->actingAs($this->user, 'web')
        ->withSession(['auth.password_confirmed_at' => now()->timestamp])
        ->postJson('/api/admin/two-factor-authentication')
        ->assertOk()
        ->assertJson(['pending' => true]);

    expect($this->user->fresh()->two_factor_secret)->toBe($encryptedSecret);
});

test('invalid confirmation is rejected and a spaced valid code confirms setup', function () {
    $this->user->forceFill([
        'two_factor_secret' => encrypt(TWO_FACTOR_SECRET),
        'two_factor_recovery_codes' => encrypt(json_encode(['one', 'two'])),
    ])->save();

    fakeTwoFactorProvider(false)
        ->shouldReceive('verify')
        ->once()
        ->with(TWO_FACTOR_SECRET, '123456')
        ->andReturnFalse();

    $this->actingAs($this->user, 'web')
        ->withSession(['auth.password_confirmed_at' => now()->timestamp])
        ->postJson('/api/admin/two-factor-authentication/confirm', ['code' => '123 456'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('code');

    expect($this->user->fresh()->two_factor_confirmed_at)->toBeNull();

    fakeTwoFactorProvider()
        ->shouldReceive('verify')
        ->once()
        ->with(TWO_FACTOR_SECRET, '123456')
        ->andReturnTrue();

    $this->postJson('/api/admin/two-factor-authentication/confirm', ['code' => ' 123 456 '])
        ->assertOk()
        ->assertJson([
            'enabled' => true,
            'pending' => false,
            'recovery_codes' => ['one', 'two'],
        ]);

    expect($this->user->fresh()->hasEnabledTwoFactorAuthentication())->toBeTrue();
});

test('recovery codes require confirmation can be regenerated and old codes stop working', function () {
    $oldCodes = ['old-code-one', 'old-code-two'];
    confirmedTwoFactorUser($this->user, $oldCodes);

    $this->actingAs($this->user, 'web')
        ->getJson('/api/admin/two-factor-authentication/recovery-codes')
        ->assertStatus(423);

    $this->withSession(['auth.password_confirmed_at' => now()->timestamp])
        ->getJson('/api/admin/two-factor-authentication/recovery-codes')
        ->assertOk()
        ->assertJson(['recovery_codes' => $oldCodes]);

    $response = $this->postJson('/api/admin/two-factor-authentication/recovery-codes')
        ->assertOk();

    $newCodes = $response->json('recovery_codes');

    expect($newCodes)->toHaveCount(8)
        ->and($newCodes)->not->toContain($oldCodes[0])
        ->and($this->user->fresh()->recoveryCodes())->toBe($newCodes);
});

test('a user can cancel pending setup and disable confirmed two factor authentication', function () {
    $this->user->forceFill([
        'two_factor_secret' => encrypt(TWO_FACTOR_SECRET),
        'two_factor_recovery_codes' => encrypt(json_encode(['pending-code'])),
    ])->save();

    $this->actingAs($this->user, 'web')
        ->withSession(['auth.password_confirmed_at' => now()->timestamp])
        ->deleteJson('/api/admin/two-factor-authentication/setup')
        ->assertOk()
        ->assertJson(['enabled' => false, 'pending' => false]);

    expect($this->user->fresh()->two_factor_secret)->toBeNull();

    confirmedTwoFactorUser($this->user);

    $this->deleteJson('/api/admin/two-factor-authentication')
        ->assertOk()
        ->assertJson(['enabled' => false, 'pending' => false]);

    $this->user->refresh();

    expect($this->user->two_factor_secret)->toBeNull()
        ->and($this->user->two_factor_recovery_codes)->toBeNull()
        ->and($this->user->two_factor_confirmed_at)->toBeNull();
});

test('users without confirmed two factor authentication continue to log in normally', function () {
    $response = $this->postJson('/api/admin/login_step_2', [
        'data' => [
            'step' => 'LOGIN_ENTER_PASSWORD',
            'email' => $this->user->email,
            'password' => 'password',
            'remember' => true,
            'school' => ['id' => $this->school->id, 'long_name' => $this->school->long_name],
        ],
    ]);

    $response->assertOk()->assertJson(['step' => 'LOGIN_SUCCESS'])->assertJsonMissingPath('password');
    $this->assertAuthenticatedAs($this->user, 'web');
});

test('unknown password email code still requires the configured authenticator or recovery code', function (string $factor) {
    confirmedTwoFactorUser($this->user);
    $password = $this->user->password;
    $this->user->forceFill(['token_2fa' => '123456', 'token_2fa_expires_at' => now()->addMinutes(10)])->save();
    $data = ['data' => [
        'step' => 'PASSWORD_UNKNOWN_ENTER_TOKEN',
        'email' => $this->user->email,
        'school_id' => $this->school->id,
        'token_2fa' => '123456',
    ]];

    $this->postJson('/api/admin/password_unknown_step_token', $data)
        ->assertOk()
        ->assertExactJson(['step' => 'LOGIN_ENTER_TWO_FACTOR', 'auth' => false])
        ->assertSessionHas('login.id', $this->user->id)
        ->assertSessionHas('login.remember', false)
        ->assertSessionHas('login.context', 'admin');
    $this->assertGuest('web');
    expect($this->user->fresh()->token_2fa)->toBeNull();
    $this->postJson('/api/admin/password_unknown_step_token', $data)->assertUnauthorized();

    fakeTwoFactorProvider(false);
    $this->postJson('/api/admin/two-factor-challenge', ['code' => '111111'])->assertUnprocessable();
    $this->assertGuest('web');

    fakeTwoFactorProvider(true);
    $this->postJson('/api/admin/two-factor-challenge', $factor === 'authenticator'
        ? ['code' => '654321']
        : ['recovery_code' => 'recovery-code-one'])
        ->assertOk()->assertJsonPath('step', 'LOGIN_SUCCESS')
        ->assertSessionMissing('login.id')
        ->assertSessionMissing('auth.password_confirmed_at');
    $this->assertAuthenticatedAs($this->user, 'web');
    expect($this->user->fresh()->password)->toBe($password);
})->with(['authenticator', 'recovery']);

test('confirmed users are staged and must provide a valid authenticator code', function () {
    confirmedTwoFactorUser($this->user);

    $this->postJson('/api/admin/login_step_2', [
        'data' => [
            'step' => 'LOGIN_ENTER_PASSWORD',
            'email' => $this->user->email,
            'password' => 'password',
            'remember' => true,
            'school' => ['id' => $this->school->id, 'long_name' => $this->school->long_name],
        ],
    ])->assertOk()
        ->assertJson(['step' => 'LOGIN_ENTER_TWO_FACTOR'])
        ->assertJsonMissingPath('password')
        ->assertSessionHas('login.id', $this->user->id)
        ->assertSessionHas('login.remember', true);

    $this->assertGuest('web');

    fakeTwoFactorProvider(false);
    $this->postJson('/api/admin/two-factor-challenge', ['code' => '111111'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('code');
    $this->assertGuest('web');

    fakeTwoFactorProvider(true)
        ->shouldReceive('verify')
        ->once()
        ->with(TWO_FACTOR_SECRET, '123456')
        ->andReturnTrue();

    $this->withSession(['url.intended' => url('/admin/teaching?panel=entries')])
        ->postJson('/api/admin/two-factor-challenge', ['code' => '123 456'])
        ->assertOk()
        ->assertJson([
            'step' => 'LOGIN_SUCCESS',
            'auth' => true,
            'redirect_url' => '/admin/teaching?panel=entries',
        ]);

    $this->assertAuthenticatedAs($this->user, 'web');
});

test('a recovery code completes login once and cannot be reused', function () {
    confirmedTwoFactorUser($this->user, ['single-use-recovery']);

    $session = [
        'login.id' => $this->user->id,
        'login.remember' => false,
        'login.two_factor_started_at' => now()->timestamp,
        'login.context' => 'admin',
    ];

    $this->withSession($session)
        ->postJson('/api/admin/two-factor-challenge', ['recovery_code' => 'single-use-recovery'])
        ->assertOk();

    Auth::guard('web')->logout();

    $this->withSession($session)
        ->postJson('/api/admin/two-factor-challenge', ['recovery_code' => 'single-use-recovery'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('recovery_code');
});

test('two factor challenge attempts are rate limited', function () {
    confirmedTwoFactorUser($this->user);
    fakeTwoFactorProvider(false);

    $session = [
        'login.id' => $this->user->id,
        'login.remember' => false,
        'login.two_factor_started_at' => now()->timestamp,
        'login.context' => 'admin',
    ];

    foreach (range(1, 5) as $attempt) {
        $this->withSession($session)
            ->postJson('/api/admin/two-factor-challenge', ['code' => '111111'])
            ->assertUnprocessable();
    }

    $this->withSession($session)
        ->postJson('/api/admin/two-factor-challenge', ['code' => '111111'])
        ->assertTooManyRequests();
});

test('confirmed two factor users cannot bypass the challenge through the generic homepage password login', function () {
    confirmedTwoFactorUser($this->user);

    $this->postJson('/api/homepage/login_step_password', [
        'email' => $this->user->email,
        'school_id' => $this->school->id,
        'password' => 'password',
        'remember' => false,
    ])->assertStatus(423);

    $this->assertGuest('web');
});

test('sensitive two factor attributes are hidden from serialization', function () {
    confirmedTwoFactorUser($this->user);

    expect($this->user->fresh()->toArray())
        ->not->toHaveKeys(['two_factor_secret', 'two_factor_recovery_codes']);
});
