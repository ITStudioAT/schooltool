<?php

use App\Models\Licence;
use App\Models\School;
use App\Models\SchoolTool;
use App\Services\FeaturePreviewControlClient;
use App\Services\FeaturePreviewControlDecision;
use App\Services\FeaturePreviewDatabaseGuard;
use App\Services\FeaturePreviewService;
use App\Services\FeaturePreviewSnapshotIdentityStore;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    // ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature');

/**
 * Enable a licensed module explicitly for feature tests exercising its routes.
 */
function enableSchoolToolModuleForTests(School $school, string $module): void
{
    SchoolTool::query()->updateOrCreate(
        ['school_id' => $school->id],
        [
            "{$module}_visible_admin" => true,
            "{$module}_visible_user" => true,
        ],
    );
}

/**
 * Attach an active legacy school licence for route-level feature tests.
 */
function grantSchoolToolLicenceForTests(School $school, string $licenceName): void
{
    $licence = Licence::query()->firstOrCreate(
        ['name' => $licenceName],
        [
            'long_name' => $licenceName,
            'is_selectable' => true,
        ],
    );

    $school->licences()->syncWithoutDetaching([
        $licence->id => ['valid_until' => now()->addYear()->toDateString()],
    ]);
}

/** Create an independent live-control fixture from an explicitly isolated SQLite snapshot. */
function snapshotFeaturePreviewControlForTests(): void
{
    $snapshot = DB::connection();
    if ($snapshot->getDriverName() !== 'sqlite' || $snapshot->getDatabaseName() !== ':memory:') {
        throw new RuntimeException('Preview control fixtures require an isolated SQLite memory database.');
    }

    foreach (['import116_id', 'is_2fa', 'email_2fa', 'email_2fa_verified_at', 'two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at'] as $column) {
        if (! Schema::hasColumn('users', $column)) {
            Schema::table('users', fn (Blueprint $table) => $table->text($column)->nullable());
        }
    }

    config(['database.connections.preview_control' => [
        'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => false,
    ]]);
    DB::purge('preview_control');
    $control = DB::connection('preview_control');
    foreach ($snapshot->select("SELECT name, sql FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'") as $table) {
        $control->statement($table->sql);
        $rows = $snapshot->table($table->name)->get()->map(fn ($row): array => (array) $row)->all();
        if ($rows !== []) {
            $control->table($table->name)->insert($rows);
        }
    }

    $baselines = $control->table('users')->get()->mapWithKeys(fn ($row): array => [
        $row->id => FeaturePreviewService::authenticationFingerprintFromAttributes((array) $row),
    ])->all();
    $identityStore = Mockery::mock(FeaturePreviewSnapshotIdentityStore::class);
    $source = ['database' => 'live_fixture', 'server_fingerprint' => str_repeat('a', 64), 'app_key_fingerprint' => str_repeat('b', 64)];
    $identityStore->shouldReceive('read')->andReturn(['users' => $baselines, 'source_identity' => $source]);
    app()->instance(FeaturePreviewSnapshotIdentityStore::class, $identityStore);

    $guard = Mockery::mock(FeaturePreviewDatabaseGuard::class);
    $guard->shouldReceive('withMainReadOnlyConnection')->andReturnUsing(fn (callable $callback): mixed => $callback($control));
    $guard->shouldReceive('connectionIdentity')->andReturn($source);
    $decision = new FeaturePreviewControlDecision($guard);
    $client = Mockery::mock(FeaturePreviewControlClient::class);
    $client->shouldReceive('request')->andReturnUsing(function (string $operation, array $payload = []) use ($decision): array {
        $preview = config('schooltool.preview.instance');
        config(['schooltool.preview.instance' => false]);
        try {
            return $decision->decide($operation, $payload);
        } finally {
            config(['schooltool.preview.instance' => $preview]);
        }
    });
    app()->instance(FeaturePreviewControlClient::class, $client);
}

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}
