<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

beforeEach(function (): void {
    config([
        'services.cloudways.deployment.base_url' => 'https://api.cloudways.test/api/v2',
        'services.cloudways.deployment.access_token' => 'test-access-token',
        'services.cloudways.deployment.server_id' => 123,
        'services.cloudways.deployment.app_id' => 456,
        'services.cloudways.deployment.branch' => 'main',
        'services.cloudways.deployment.deploy_path' => null,
        'services.cloudways.deployment.operation_timeout' => 60,
        'services.cloudways.deployment.poll_interval' => 1,
    ]);

    Http::preventStrayRequests();
});

function schooltoolCloudwaysDeployment(
    string $datetime,
    int $result,
    string $description,
    ?string $path = null,
): array {
    return [
        'git_url' => 'git@github.com:ITStudioAT/schooltool.git',
        'branch_name' => 'main',
        'customer_id' => 5,
        'path' => $path,
        'result' => $result,
        'datetime' => $datetime,
        'description' => $description,
    ];
}

it('reports missing Cloudways pull configuration', function (): void {
    config([
        'services.cloudways.deployment.access_token' => null,
        'services.cloudways.deployment.server_id' => null,
        'services.cloudways.deployment.app_id' => null,
    ]);

    $this->artisan('cloudways:pull --check')
        ->expectsOutputToContain('Cloudways platform Pull is not configured.')
        ->expectsOutputToContain('CLOUDWAYS_API_ACCESS_TOKEN')
        ->expectsOutputToContain('CLOUDWAYS_SERVER_ID')
        ->expectsOutputToContain('CLOUDWAYS_APP_ID')
        ->assertFailed();

    Http::assertNothingSent();
});

it('verifies Git history access without starting a pull', function (): void {
    Http::fake([
        'api.cloudways.test/api/v2/git/history*' => Http::response(['logs' => []]),
    ]);

    $this->artisan('cloudways:pull --check')
        ->expectsOutputToContain('Cloudways Git Pull and History API access is working.')
        ->assertSuccessful();

    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request): bool => str_starts_with($request->url(), 'https://api.cloudways.test/api/v2/git/history?')
        && $request->method() === 'GET'
        && $request->hasHeader('Authorization', 'Bearer test-access-token')
        && $request['server_id'] === 123
        && $request['app_id'] === 456);
});

it('requests and waits for a new Cloudways pull', function (): void {
    Sleep::fake(syncWithCarbon: true);
    $previousDeployment = schooltoolCloudwaysDeployment('07, 08, 2026 - 09:00', 1, 'completed');
    $pendingDeployment = schooltoolCloudwaysDeployment('07, 08, 2026 - 10:00', 0, '');
    $completedDeployment = schooltoolCloudwaysDeployment('07, 08, 2026 - 10:00', 1, 'completed successfully');

    Http::fake([
        'api.cloudways.test/api/v2/git/pull' => Http::response(['status' => true]),
        'api.cloudways.test/api/v2/git/history*' => Http::sequence()
            ->push(['logs' => [$previousDeployment]])
            ->push(['logs' => [$pendingDeployment, $previousDeployment]])
            ->push(['logs' => [$completedDeployment, $previousDeployment]]),
    ]);

    $this->artisan('cloudways:pull')
        ->expectsOutputToContain('Requesting Cloudways platform Pull')
        ->expectsOutputToContain('Cloudways: Git deployment is pending.')
        ->expectsOutputToContain('Cloudways platform Pull completed successfully.')
        ->assertSuccessful();

    Sleep::assertSleptTimes(1);
    Http::assertSentCount(4);
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.cloudways.test/api/v2/git/pull'
        && $request->method() === 'POST'
        && $request->hasHeader('Authorization', 'Bearer test-access-token')
        && $request['server_id'] === 123
        && $request['app_id'] === 456
        && $request['branch_name'] === 'main');
});

it('fails for an explicit Cloudways pull error', function (): void {
    Http::fake([
        'api.cloudways.test/api/v2/git/pull' => Http::response(['status' => true]),
        'api.cloudways.test/api/v2/git/history*' => Http::sequence()
            ->push(['logs' => []])
            ->push(['logs' => [schooltoolCloudwaysDeployment(
                '07, 08, 2026 - 10:00',
                0,
                'Repository authentication failed',
            )]]),
    ]);

    $this->artisan('cloudways:pull')
        ->expectsOutputToContain('Cloudways: Repository authentication failed')
        ->expectsOutputToContain('Cloudways reported that the platform Pull failed.')
        ->assertFailed();
});

it('keeps an empty result-zero history entry pending until timeout', function (): void {
    config([
        'services.cloudways.deployment.operation_timeout' => 2,
        'services.cloudways.deployment.poll_interval' => 1,
    ]);
    Sleep::fake(syncWithCarbon: true);
    $pendingDeployment = schooltoolCloudwaysDeployment('07, 08, 2026 - 10:00', 0, '');

    Http::fake([
        'api.cloudways.test/api/v2/git/pull' => Http::response(['status' => true]),
        'api.cloudways.test/api/v2/git/history*' => Http::sequence()
            ->push(['logs' => []])
            ->push(['logs' => [$pendingDeployment]])
            ->whenEmpty(Http::response(['logs' => [$pendingDeployment]])),
    ]);

    $this->artisan('cloudways:pull')
        ->doesntExpectOutputToContain('Cloudways reported that the platform Pull failed.')
        ->expectsOutputToContain('Cloudways platform Pull did not finish within 2 seconds.')
        ->assertFailed();

    Sleep::assertSleptTimes(2);
});

it('refuses to start while a matching pull is running', function (): void {
    Http::fake([
        'api.cloudways.test/api/v2/git/history*' => Http::response([
            'logs' => [schooltoolCloudwaysDeployment('07, 08, 2026 - 10:00', 1, 'Deployment is running')],
        ]),
    ]);

    $this->artisan('cloudways:pull')
        ->expectsOutputToContain('A Cloudways Git deployment is already running')
        ->assertFailed();

    Http::assertNotSent(fn (Request $request): bool => $request->method() === 'POST');
});

it('fails safely when multiple new matching deployments appear', function (): void {
    Http::fake([
        'api.cloudways.test/api/v2/git/pull' => Http::response(['status' => true]),
        'api.cloudways.test/api/v2/git/history*' => Http::sequence()
            ->push(['logs' => []])
            ->push(['logs' => [
                schooltoolCloudwaysDeployment('07, 08, 2026 - 10:00', 0, ''),
                schooltoolCloudwaysDeployment('07, 08, 2026 - 10:01', 0, ''),
            ]]),
    ]);

    $this->artisan('cloudways:pull')
        ->expectsOutputToContain('Multiple new Cloudways Git deployments appeared')
        ->assertFailed();
});

it('sanitizes upstream API errors', function (): void {
    Http::fake([
        'api.cloudways.test/api/v2/git/history*' => Http::response(
            '<html>secret upstream failure page</html>',
            403,
        ),
    ]);

    $this->artisan('cloudways:pull --check')
        ->expectsOutputToContain('Cloudways API request failed with HTTP status 403.')
        ->doesntExpectOutputToContain('secret upstream failure page')
        ->doesntExpectOutputToContain('test-access-token')
        ->assertFailed();
});
