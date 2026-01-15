<?php

use App\Models\User;
use App\Providers\HorizonServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Laravel\Horizon\Horizon;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('horizon auth allows super admin and denies others', function () {
    Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('super_admin');

    $regularUser = User::factory()->create();

    $provider = new HorizonServiceProvider(app());
    $provider->boot();

    $captured = Horizon::$authUsing;

    $superRequest = Request::create('/horizon');
    $superRequest->setUserResolver(fn () => $superAdmin);

    $regularRequest = Request::create('/horizon');
    $regularRequest->setUserResolver(fn () => $regularUser);

    expect($captured)->not->toBeNull()
        ->and($captured($superRequest))->toBeTrue()
        ->and($captured($regularRequest))->toBeFalse();
});
