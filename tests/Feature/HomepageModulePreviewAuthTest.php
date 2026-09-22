<?php

use App\Http\Middleware\RequireFeaturePreviewAccess;
use App\Http\Middleware\RestrictRestaurantParentSession;
use App\Models\FeaturePreviewSetting;
use App\Models\School;
use App\Models\User;
use App\Notifications\StandardEmail;
use App\Services\RestaurantHomepageAuthService;
use Illuminate\Auth\Events\Login;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function (): void {
    config([
        'database.default' => 'homepage_module_preview_test',
        'database.connections.homepage_module_preview_test' => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ],
        'schooltool.preview.instance' => true,
        'cache.default' => 'array',
        'session.driver' => 'array',
    ]);
    DB::purge('homepage_module_preview_test');
    expect(DB::connection()->getDatabaseName())->toBe(':memory:');

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
        foreach (['school_id', 'schoolyear_id', 'import116_id'] as $column) {
            $table->unsignedBigInteger($column)->nullable();
        }
        foreach (['last_name', 'first_name', 'phone', 'email', 'password', 'remember_token', 'login_ip', 'token_2fa', 'schoolclass'] as $column) {
            $table->string($column)->nullable();
        }
        foreach (['email_verified_at', 'confirmed_at', 'login_at', 'token_2fa_expires_at', 'two_factor_confirmed_at', 'sepa_at'] as $column) {
            $table->timestamp($column)->nullable();
        }
        $table->text('two_factor_secret')->nullable();
        $table->boolean('is_active')->default(true);
        $table->boolean('use_school_color_for_admin_ui')->default(true);
        $table->timestamps();
    });
    Schema::create('import116', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('school_id');
        $table->unsignedBigInteger('user_id');
        foreach (['class', 'first_name', 'last_name', 'email', 'mother_email', 'father_email'] as $column) {
            $table->string($column)->nullable();
        }
    });
    Schema::create('school_tools', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('school_id');
    });
    (require database_path('migrations/2025_10_16_163810_create_permission_tables.php'))->up();
    Schema::table('roles', fn (Blueprint $table) => $table->boolean('is_admin')->default(false));
    (require database_path('migrations/2026_09_16_105645_create_feature_preview_settings_table.php'))->up();
    (require database_path('migrations/2026_09_16_105646_add_feature_preview_allowed_to_users_table.php'))->up();
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->school = School::factory()->create();
    DB::table('school_tools')->insert(['school_id' => $this->school->id]);
    foreach (['lunch_user', 'lunch_candidate'] as $role) {
        Role::create(['name' => $role, 'guard_name' => 'web']);
    }
    FeaturePreviewSetting::factory()->create(['enabled' => true]);
    snapshotFeaturePreviewControlForTests();
    Notification::fake();
    Event::fake([Login::class]);
});

afterEach(function (): void {
    DB::purge('preview_control');
    DB::purge('homepage_module_preview_test');
});

function homepageModulePreviewUser(string $role, bool $allowed = true): User
{
    $user = User::factory()->create([
        'school_id' => test()->school->id,
        'is_active' => true,
        'confirmed_at' => null,
        'feature_preview_allowed' => $allowed,
        'token_2fa' => '123456',
        'token_2fa_expires_at' => now()->addMinutes(10),
        'sepa_at' => now(),
    ]);
    $user->assignRole($role);
    snapshotFeaturePreviewControlForTests();

    return $user;
}

/** @return array{school_id: int, email: string, user_id: int, password: string, token_2fa: string} */
function homepageModulePreviewLoginData(User $user): array
{
    return [
        'school_id' => $user->school_id,
        'email' => $user->email,
        'user_id' => $user->id,
        'password' => 'password',
        'token_2fa' => '123456',
    ];
}

test('restaurant preview rejects ungranted accounts before login side effects', function (string $method): void {
    $user = homepageModulePreviewUser('lunch_user', false);

    expect(fn () => app(RestaurantHomepageAuthService::class)->{$method}(homepageModulePreviewLoginData($user)))
        ->toThrow(HttpException::class, 'Für dieses Konto ist die Vorschau nicht freigegeben.');

    expect($user->fresh()->token_2fa)->toBe('123456')
        ->and($user->fresh()->login_at)->toBeNull();
    Notification::assertNothingSent();
    $this->assertGuest();
})->with(['checkEmail', 'sendLoginCode', 'loginWithCode', 'loginWithPassword']);

test('restaurant preview admits granted ordinary accounts with verified email', function (string $method): void {
    $user = homepageModulePreviewUser('lunch_user');

    $result = app(RestaurantHomepageAuthService::class)->{$method}(homepageModulePreviewLoginData($user));

    expect($result['status'])->toBe('LOGGED_IN');
    $this->assertAuthenticatedAs($user);
})->with(['loginWithCode', 'loginWithPassword']);

test('restaurant preview filters parent children to individually granted accounts', function (): void {
    $grantedChild = homepageModulePreviewUser('lunch_user');
    $ungrantedChild = homepageModulePreviewUser('lunch_user', false);
    foreach ([$grantedChild, $ungrantedChild] as $child) {
        DB::table('import116')->insert([
            'school_id' => $this->school->id,
            'user_id' => $child->id,
            'first_name' => $child->first_name,
            'last_name' => $child->last_name,
            'mother_email' => 'parent@example.test',
        ]);
    }

    snapshotFeaturePreviewControlForTests();
    $service = app(RestaurantHomepageAuthService::class);
    $result = $service->checkEmail(['school_id' => $this->school->id, 'email' => 'parent@example.test']);

    expect($result['match_source'])->toBe('parent')
        ->and(array_column($result['matched_users'], 'id'))->toBe([$grantedChild->id]);

    $result = $service->loginWithPassword([
        ...homepageModulePreviewLoginData($grantedChild),
        'email' => 'parent@example.test',
    ]);

    expect($result['status'])->toBe('LOGGED_IN')
        ->and(session(RestrictRestaurantParentSession::SESSION_KEY))->toBe($grantedChild->id);
    $this->assertAuthenticatedAs($grantedChild);
});

test('restaurant preview rechecks a parent grant before consuming the emailed challenge', function (): void {
    $child = homepageModulePreviewUser('lunch_user');
    DB::table('import116')->insert([
        'school_id' => $this->school->id,
        'user_id' => $child->id,
        'mother_email' => 'parent@example.test',
    ]);
    $data = [...homepageModulePreviewLoginData($child), 'email' => 'parent@example.test'];
    snapshotFeaturePreviewControlForTests();
    $service = app(RestaurantHomepageAuthService::class);
    $service->sendLoginCode($data);
    Notification::assertSentOnDemand(StandardEmail::class, function (StandardEmail $notification) use (&$data): bool {
        $data['token_2fa'] = $notification->data['token_2fa'];

        return true;
    });
    $challenge = session('restaurant.parent_login_challenge');
    User::on('preview_control')->whereKey($child->id)->update(['feature_preview_allowed' => false]);

    expect(fn () => $service->loginWithCode($data))
        ->toThrow(HttpException::class, 'Für dieses Konto ist die Vorschau nicht freigegeben.');

    expect(session('restaurant.parent_login_challenge'))->toBe($challenge);
    $this->assertGuest();
});

test('restaurant preview rechecks live parent contact on an existing session', function (): void {
    $child = homepageModulePreviewUser('lunch_user');
    DB::table('import116')->insert([
        'school_id' => $this->school->id,
        'user_id' => $child->id,
        'mother_email' => 'parent@example.test',
    ]);
    snapshotFeaturePreviewControlForTests();
    app(RestaurantHomepageAuthService::class)->loginWithPassword([
        ...homepageModulePreviewLoginData($child), 'email' => 'parent@example.test',
    ]);

    $request = Request::create('/api/homepage/restaurant/user');
    $request->setLaravelSession(session()->driver());
    $middleware = app(RequireFeaturePreviewAccess::class);
    expect($middleware->handle($request, fn () => response('allowed'))->getStatusCode())->toBe(200)
        ->and(session('restaurant.parent_email'))->toBe('parent@example.test');

    DB::connection('preview_control')->table('import116')->where('user_id', $child->id)
        ->update(['mother_email' => 'other@example.test']);

    expect(fn () => $middleware->handle($request, fn () => response('must not be reached')))->toThrow(HttpException::class)
        ->and(DB::table('import116')->where('user_id', $child->id)->value('mother_email'))->toBe('parent@example.test');
});

test('restaurant preview refuses a stale live parent contact before consuming its challenge', function (): void {
    $child = homepageModulePreviewUser('lunch_user');
    DB::table('import116')->insert([
        'school_id' => $this->school->id,
        'user_id' => $child->id,
        'mother_email' => 'parent@example.test',
    ]);
    snapshotFeaturePreviewControlForTests();
    $data = [...homepageModulePreviewLoginData($child), 'email' => 'parent@example.test'];
    $service = app(RestaurantHomepageAuthService::class);
    $service->sendLoginCode($data);
    Notification::assertSentOnDemand(StandardEmail::class, function (StandardEmail $notification) use (&$data): bool {
        $data['token_2fa'] = $notification->data['token_2fa'];

        return true;
    });
    $challenge = session('restaurant.parent_login_challenge');
    DB::connection('preview_control')->table('import116')->where('user_id', $child->id)
        ->update(['mother_email' => 'other@example.test']);

    expect(fn () => $service->loginWithCode($data))->toThrow(HttpException::class)
        ->and(session('restaurant.parent_login_challenge'))->toBe($challenge);
    $this->assertGuest();
});

test('restaurant preview refuses registration lookup for unknown and pending accounts', function (bool $pending): void {
    $email = 'unknown@example.test';
    if ($pending) {
        $email = homepageModulePreviewUser('lunch_candidate')->email;
    }
    $count = User::query()->count();

    expect(fn () => app(RestaurantHomepageAuthService::class)->checkEmail([
        'school_id' => $this->school->id,
        'email' => $email,
    ]))->toThrow(HttpException::class, 'Für dieses Konto ist die Vorschau nicht freigegeben.');

    expect(User::query()->count())->toBe($count);
    Notification::assertNothingSent();
})->with([false, true]);

test('removed tutoring preview endpoints have no authentication side effects for ungranted accounts', function (string $endpoint): void {
    $user = homepageModulePreviewUser('lunch_user', false);

    $this->postJson('/api/homepage/tutoring/'.$endpoint, ['data' => homepageModulePreviewLoginData($user)])
        ->assertNotFound();

    expect($user->fresh()->token_2fa)->toBe('123456')
        ->and($user->fresh()->login_at)->toBeNull()
        ->and($user->fresh()->confirmed_at)->toBeNull();
    Notification::assertNothingSent();
    $this->assertGuest();
})->with(['check_email', 'unknown_password', 'confirm_email', 'login_with_token', 'login_with_password']);

test('preview grants cannot restore removed tutoring login endpoints', function (string $endpoint): void {
    $user = homepageModulePreviewUser('lunch_user');

    $this->postJson('/api/homepage/tutoring/'.$endpoint, ['data' => homepageModulePreviewLoginData($user)])
        ->assertNotFound();

    expect($user->fresh()->login_at)->toBeNull();
    Notification::assertNothingSent();
    $this->assertGuest();
})->with(['login_with_token', 'login_with_password']);

test('removed tutoring preview endpoints cannot provision unknown accounts', function (string $endpoint): void {
    $count = User::query()->count();

    $this->postJson('/api/homepage/tutoring/'.$endpoint, ['data' => [
        'school_id' => $this->school->id,
        'email' => 'unknown@example.test',
    ]])->assertNotFound();

    expect(User::query()->count())->toBe($count);
    Notification::assertNothingSent();
})->with(['check_email', 'create_user']);

test('module password overrides stay main only and preview still accepts the own password', function (string $serviceClass, string $role, bool $preview): void {
    config(['schooltool.preview.instance' => $preview]);
    $user = homepageModulePreviewUser($role, $preview);
    Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $superadmin = User::factory()->create([
        'school_id' => $user->school_id,
        'is_active' => true,
        'feature_preview_allowed' => false,
        'password' => Hash::make('override-password'),
    ]);
    $superadmin->assignRole('super_admin');
    snapshotFeaturePreviewControlForTests();
    $service = app($serviceClass);
    $data = homepageModulePreviewLoginData($user);

    $result = $service->loginWithPassword([...$data, 'password' => 'override-password']);

    expect($result['status'])->toBe($preview ? 'RETRY_PASSWORD' : 'LOGGED_IN')
        ->and($result)->not->toHaveKey('password');
    if ($preview) {
        $this->assertGuest();
        expect($user->fresh()->login_at)->toBeNull()
            ->and($user->fresh()->token_2fa)->toBe('123456');
        Notification::assertNothingSent();
        expect($service->loginWithPassword($data)['status'])->toBe('LOGGED_IN');
    }
    $this->assertAuthenticatedAs($user);
})->with([
    'restaurant preview' => [RestaurantHomepageAuthService::class, 'lunch_user', true],
    'restaurant main' => [RestaurantHomepageAuthService::class, 'lunch_user', false],
]);

test('ordinary module login on main does not require preview grants', function (string $serviceClass, string $role): void {
    config(['schooltool.preview.instance' => false]);
    $user = homepageModulePreviewUser($role, false);

    $result = app($serviceClass)->loginWithPassword(homepageModulePreviewLoginData($user));

    expect($result['status'])->toBe('LOGGED_IN');
    $this->assertAuthenticatedAs($user);
})->with([
    'restaurant' => [RestaurantHomepageAuthService::class, 'lunch_user'],
]);
