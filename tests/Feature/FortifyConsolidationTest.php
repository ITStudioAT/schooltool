<?php

use App\Actions\Fortify\ResetUserPassword;
use App\Models\School;
use App\Models\Schoolyear;
use App\Models\User;
use App\Services\AdminService;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->school = School::factory()->create();
    $this->schoolyear = Schoolyear::factory()->create(['school_id' => $this->school->id]);
});

it('binds the Fortify password reset contract to the shared action', function (): void {
    expect(app(ResetsUserPasswords::class))->toBeInstanceOf(ResetUserPassword::class);
});

it('validates hashes and announces password resets through the shared action', function (): void {
    $user = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'password' => Hash::make('old-password'),
        'remember_token' => 'old-token',
    ]);

    expect(fn () => app(ResetUserPassword::class)->reset($user, [
        'password' => 'short',
        'password_confirmation' => 'short',
    ]))->toThrow(ValidationException::class);

    Event::fake([PasswordReset::class]);

    app(ResetUserPassword::class)->reset($user, [
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ]);

    $user->refresh();

    expect(Hash::check('new-password', $user->password))->toBeTrue()
        ->and($user->remember_token)->not->toBe('old-token');

    Event::assertDispatched(PasswordReset::class, fn (PasswordReset $event): bool => $event->user->is($user));
});

it('uses the shared reset action in the existing school scoped forgot password flow', function (): void {
    $sameEmail = 'shared@example.test';
    $target = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => $sameEmail,
        'password' => Hash::make('old-password'),
        'token_2fa' => '123456',
        'token_2fa_expires_at' => now()->addMinutes(10),
        'is_2fa' => false,
    ]);
    $otherSchool = School::factory()->create();
    $otherSchoolyear = Schoolyear::factory()->create(['school_id' => $otherSchool->id]);
    $other = User::factory()->create([
        'school_id' => $otherSchool->id,
        'schoolyear_id' => $otherSchoolyear->id,
        'email' => $sameEmail,
        'password' => Hash::make('other-password'),
    ]);

    app(AdminService::class)->passwordUnkownSetPassword([
        'email' => $sameEmail,
        'school_id' => $this->school->id,
        'token_2fa' => '123456',
        'password' => 'new-password',
    ]);

    expect(Hash::check('new-password', $target->refresh()->password))->toBeTrue()
        ->and($target->token_2fa)->toBeNull()
        ->and(Hash::check('other-password', $other->refresh()->password))->toBeTrue();
});

it('keeps the User model verification contract compatible with legacy UUID links', function (): void {
    $user = User::factory()->unverified()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'uuid' => fake()->uuid(),
        'uuid_at' => now(),
    ]);

    expect($user)->toBeInstanceOf(MustVerifyEmail::class)
        ->and($user->hasVerifiedEmail())->toBeFalse()
        ->and($user->emailVerified())->toBeTrue();

    $user->refresh();

    expect($user->hasVerifiedEmail())->toBeTrue()
        ->and($user->uuid)->toBeNull()
        ->and($user->uuid_at)->toBeNull();
});

it('verifies the matching UUID when an email exists in multiple schools', function (): void {
    $email = 'duplicate@example.test';
    $first = User::factory()->unverified()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => $email,
        'uuid' => fake()->uuid(),
        'uuid_at' => now(),
    ]);
    $otherSchool = School::factory()->create();
    $otherSchoolyear = Schoolyear::factory()->create(['school_id' => $otherSchool->id]);
    $second = User::factory()->unverified()->create([
        'school_id' => $otherSchool->id,
        'schoolyear_id' => $otherSchoolyear->id,
        'email' => $email,
        'uuid' => fake()->uuid(),
        'uuid_at' => now(),
    ]);

    $this->postJson('/api/admin/users/email_verification', [
        'email' => $email,
        'uuid' => $second->uuid,
    ])->assertOk();

    expect($first->refresh()->hasVerifiedEmail())->toBeFalse()
        ->and($second->refresh()->hasVerifiedEmail())->toBeTrue();
});

it('keeps stock Fortify password routes disabled for multi school compatibility', function (): void {
    $uris = collect(Route::getRoutes())->map->uri();

    expect($uris)->not->toContain('forgot-password')
        ->not->toContain('reset-password/{token}')
        ->not->toContain('reset-password');
});
