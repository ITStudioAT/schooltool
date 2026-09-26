<?php

/**
 * InstallUpdateController Tests
 *
 * Covers initial setup behavior for role creation and super_admin assignment.
 */

use App\Http\Controllers\Spa\InstallUpdateController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(RefreshDatabase::class);

test('install update aborts when no user exists', function () {
    User::query()->delete();
    expect(User::count())->toBe(0);

    $controller = new InstallUpdateController;

    $this->expectException(HttpException::class);
    $this->expectExceptionMessage('1. Benutzer wurde nicht gefunden');

    $controller->index(Request::create('/install', 'GET'));
});

test('install update creates roles and assigns super admin', function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    User::query()->delete();

    $user = User::factory()->create([
        'email' => 'setup@test.com',
    ]);

    $controller = new InstallUpdateController;
    $controller->index(Request::create('/install', 'GET'));

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    expect(User::role('super_admin')->whereKey($user->id)->exists())->toBeTrue();
    expect(Role::whereIn('name', ['super_admin', 'admin', 'user', 'Director'])->count())->toBe(4);
    expect(Role::where('name', 'Director')->where('guard_name', 'web')->exists())->toBeTrue();
});
