<?php

use App\Http\Controllers\FeaturePreviewControlController;
use App\Models\FeaturePreviewSetting;
use App\Models\User;
use App\Services\FeaturePreviewControlDecision;
use App\Services\FeaturePreviewDatabaseGuard;
use App\Services\FeaturePreviewService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function (): void {
    config([
        'schooltool.preview.instance' => false,
        'database.default' => 'preview_decision_test',
        'database.connections.preview_decision_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
        'cache.default' => 'array',
    ]);
    DB::purge('preview_decision_test');
    expect(DB::connection()->getDatabaseName())->toBe(':memory:');
    Schema::create('users', function (Blueprint $table): void {
        $table->id();
        foreach (['school_id', 'schoolyear_id', 'import116_id'] as $column) {
            $table->unsignedBigInteger($column)->nullable();
        }
        foreach (['email', 'password', 'last_name', 'first_name', 'phone', 'remember_token', 'email_2fa', 'two_factor_secret', 'two_factor_recovery_codes'] as $column) {
            $table->string($column)->nullable();
        }
        foreach (['confirmed_at', 'email_verified_at', 'email_2fa_verified_at', 'two_factor_confirmed_at'] as $column) {
            $table->timestamp($column)->nullable();
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
    FeaturePreviewSetting::factory()->create(['enabled' => true]);
    $this->user = User::factory()->create(['feature_preview_allowed' => true, 'confirmed_at' => null]);
    Role::create(['name' => 'student', 'guard_name' => 'web']);
    $this->user->assignRole('student');
    $this->user->refresh();
    $this->payload = [
        'source_identity' => [
            'database' => 'main_test', 'server_fingerprint' => str_repeat('a', 64), 'app_key_fingerprint' => str_repeat('b', 64),
        ],
        'identity' => [
            'id' => $this->user->id, 'school_id' => $this->user->school_id,
            'email' => $this->user->email, 'created_at' => $this->user->getRawOriginal('created_at'),
        ],
        'auth_fingerprint' => FeaturePreviewService::authenticationFingerprint($this->user),
        'roles' => [['name' => 'student', 'guard_name' => 'web', 'is_admin' => false]],
        'permissions' => [],
    ];
    $guard = Mockery::mock(FeaturePreviewDatabaseGuard::class);
    $guard->shouldReceive('withMainReadOnlyConnection')->andReturnUsing(fn (callable $callback): mixed => $callback(DB::connection()));
    $guard->shouldReceive('connectionIdentity')->andReturn([
        'database' => 'main_test', 'server_fingerprint' => str_repeat('a', 64), 'app_key_fingerprint' => str_repeat('b', 64),
    ]);
    $this->decision = new FeaturePreviewControlDecision($guard);
});

afterEach(function (): void {
    DB::purge('preview_decision_test');
});

test('main control returns only decisions and reads accounts without Eloquent retrieval events', function (): void {
    Event::listen('eloquent.retrieved: '.User::class, function (): never {
        throw new RuntimeException('Account retrieval observers must not run in the bridge.');
    });
    DB::connection()->enableQueryLog();
    DB::connection()->flushQueryLog();

    expect($this->decision->decide('admission', $this->payload))->toBe(['allowed' => true]);
    foreach (DB::connection()->getQueryLog() as $query) {
        expect(strtolower(ltrim($query['query'])))->toStartWith('select');
    }
});

test('main control status is independent of a snapshot and reveals only source fingerprints', function (): void {
    expect($this->decision->decide('status', []))->toBe([
        'schema_ready' => true,
        'enabled' => true,
        'source' => ['database' => 'main_test', 'server_fingerprint' => str_repeat('a', 64), 'app_key_fingerprint' => str_repeat('b', 64)],
    ]);
});

test('main admission itself checks the current enabled switch on every call', function (): void {
    expect($this->decision->decide('admission', $this->payload))->toBe(['allowed' => true]);
    DB::table('feature_preview_settings')->where('id', 1)->update(['enabled' => false]);
    expect($this->decision->decide('admission', $this->payload))->toBe(['allowed' => false]);
});

test('main control rejects a snapshot from a different source', function (string $key): void {
    expect($this->decision->decide('admission', $this->payload))->toBe(['allowed' => true]);
    $this->payload['source_identity'][$key] = $key === 'database' ? 'other_main' : str_repeat('c', 64);

    expect($this->decision->decide('admission', $this->payload))->toBe(['allowed' => false]);
})->with(['database', 'server_fingerprint', 'app_key_fingerprint']);

test('main control refuses malformed or oversized admission claims', function (string $change): void {
    $payload = $this->payload;
    match ($change) {
        'missing baseline' => $payload['auth_fingerprint'] = '',
        'unknown key' => $payload['sql'] = 'SELECT * FROM users',
        'oversized roles' => $payload['roles'] = array_fill(0, 129, $payload['roles'][0]),
        'oversized permissions' => $payload['permissions'] = array_fill(0, 513, ['name' => 'x', 'guard_name' => 'web']),
        'unknown role attribute' => $payload['roles'][0]['allow'] = true,
    };

    expect($this->decision->decide('admission', $payload))->toBe(['allowed' => false]);
})->with(['missing baseline', 'unknown key', 'oversized roles', 'oversized permissions', 'unknown role attribute']);

test('main recipient checks account and confirmed second factor addresses without disclosing them', function (): void {
    $payload = [...$this->payload, 'recipient' => $this->user->email, 'recipient_kind' => 'account', 'schoolyear_id' => null];
    expect($this->decision->decide('recipient', $payload))->toBe(['allowed' => true]);
    $payload['recipient'] = 'attacker@example.test';
    expect($this->decision->decide('recipient', $payload))->toBe(['allowed' => false]);

    DB::table('users')->where('id', $this->user->id)->update([
        'is_2fa' => true, 'email_2fa' => 'second@example.test', 'email_2fa_verified_at' => now(),
    ]);
    $payload['auth_fingerprint'] = FeaturePreviewService::authenticationFingerprintFromAttributes((array) DB::table('users')->first());
    $payload['recipient'] = 'SECOND@example.test';
    $payload['recipient_kind'] = 'second_factor';
    expect($this->decision->decide('recipient', $payload))->toBe(['allowed' => true]);
    DB::table('users')->where('id', $this->user->id)->update(['email_2fa_verified_at' => null]);
    expect($this->decision->decide('recipient', $payload))->toBe(['allowed' => false]);
});

test('control controller only uses the signed request attributes', function (): void {
    $request = Request::create('/api/feature-preview/control', 'POST', ['operation' => 'status', 'payload' => []]);
    $request->attributes->set('feature_preview_control_operation', 'admission');
    $request->attributes->set('feature_preview_control_payload', [...$this->payload, 'auth_fingerprint' => str_repeat('0', 64)]);
    $response = (new FeaturePreviewControlController)($request, $this->decision);

    expect($response->getData(true))->toBe(['allowed' => false]);
});

test('control controller refuses unsigned input and hides decision failures', function (): void {
    $request = Request::create('/api/feature-preview/control', 'POST', ['operation' => 'admission', 'payload' => $this->payload]);
    expect(fn () => (new FeaturePreviewControlController)($request, $this->decision))->toThrow(HttpException::class);

    $request->attributes->set('feature_preview_control_operation', 'admission');
    $request->attributes->set('feature_preview_control_payload', $this->payload);
    $decision = Mockery::mock(FeaturePreviewControlDecision::class);
    $decision->shouldReceive('decide')->once()->andThrow(new RuntimeException('Private database diagnostics'));
    $response = (new FeaturePreviewControlController)($request, $decision);

    expect($response->getStatusCode())->toBe(503)
        ->and($response->getData(true))->toBe(['error' => 'control_unavailable']);
});

test('main decision service refuses use on the preview instance', function (): void {
    config(['schooltool.preview.instance' => true]);
    $this->decision->decide('admission', $this->payload);
})->throws(HttpException::class);

test('raw snapshot fingerprints stay compatible with model fingerprints without retrieval events', function (): void {
    Event::fake(['eloquent.retrieved: '.User::class]);
    expect(FeaturePreviewService::authenticationFingerprintFromAttributes($this->user->getRawOriginal()))
        ->toBe(FeaturePreviewService::authenticationFingerprint($this->user));
    Event::assertNotDispatched('eloquent.retrieved: '.User::class);
});
