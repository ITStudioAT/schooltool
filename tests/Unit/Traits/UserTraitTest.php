<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('setToken2Fa sets primary token with expiry', function () {
    $user = User::factory()->create();

    $token = $user->setToken2Fa(10, 1);

    $user->refresh();

    expect($token)->toBeString()
        ->and(strlen($token))->toBe(6)
        ->and($user->token_2fa)->toBe($token)
        ->and($user->token_2fa_expires_at)->not->toBeNull();
});

test('setToken2Fa sets secondary token with expiry', function () {
    $user = User::factory()->create();

    $token = $user->setToken2Fa(5, 2);

    $user->refresh();

    expect($token)->toBeString()
        ->and(strlen($token))->toBe(6)
        ->and($user->token_2fa_2)->toBe($token)
        ->and($user->token_2fa_2_expires_at)->not->toBeNull();
});

test('checkToken2Fa returns true for valid token', function () {
    $user = User::factory()->create([
        'token_2fa' => '123456',
        'token_2fa_expires_at' => now()->addMinutes(5),
    ]);

    expect($user->checkToken2Fa('123456'))->toBeTruthy();
});

test('checkToken2Fa returns false for expired token', function () {
    $user = User::factory()->create([
        'token_2fa' => '123456',
        'token_2fa_expires_at' => now()->subMinutes(1),
    ]);

    expect($user->checkToken2Fa('123456'))->toBeFalsy();
});

test('consumeToken2Fa accepts a valid token only once', function () {
    $user = User::factory()->create([
        'token_2fa' => '123456',
        'token_2fa_expires_at' => now()->addMinutes(5),
    ]);

    expect($user->consumeToken2Fa('123456'))->toBeTrue()
        ->and($user->consumeToken2Fa('123456'))->toBeFalse()
        ->and($user->fresh()->token_2fa)->toBeNull()
        ->and($user->fresh()->token_2fa_expires_at)->toBeNull();
});

test('checkToken2Fa_2 returns true for valid secondary token', function () {
    $user = User::factory()->create([
        'token_2fa_2' => '654321',
        'token_2fa_2_expires_at' => now()->addMinutes(5),
    ]);

    expect($user->checkToken2Fa_2('654321'))->toBeTruthy();
});

test('checkToken2Fa_2 returns false for expired secondary token', function () {
    $user = User::factory()->create([
        'token_2fa_2' => '654321',
        'token_2fa_2_expires_at' => now()->subMinutes(1),
    ]);

    expect($user->checkToken2Fa_2('654321'))->toBeFalsy();
});

test('consumeToken2Fa2 accepts a valid secondary token only once', function () {
    $user = User::factory()->create([
        'token_2fa_2' => '654321',
        'token_2fa_2_expires_at' => now()->addMinutes(5),
    ]);

    expect($user->consumeToken2Fa2('654321'))->toBeTrue()
        ->and($user->consumeToken2Fa2('654321'))->toBeFalse()
        ->and($user->fresh()->token_2fa_2)->toBeNull()
        ->and($user->fresh()->token_2fa_2_expires_at)->toBeNull();
});

test('rememberLogin stores timestamp and ip address', function () {
    $user = User::factory()->create([
        'login_at' => null,
        'login_ip' => null,
    ]);

    $this->app['request']->server->set('REMOTE_ADDR', '127.0.0.1');

    $user->rememberLogin();
    $user->refresh();

    expect($user->login_at)->not->toBeNull()
        ->and($user->login_ip)->toBe('127.0.0.1');
});

test('setPassword hashes and stores the password', function () {
    $user = User::factory()->create();

    $user->setPassword('secret');
    $user->refresh();

    expect(Hash::check('secret', $user->password))->toBeTrue();
});
