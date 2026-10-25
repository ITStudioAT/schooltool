<?php

use App\Services\TeachingSimulationMailpit;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    $this->app['env'] = 'local';
    config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => '127.0.0.1', 'mail.mailers.smtp.port' => 1025,
        'mail.mailers.smtp.url' => null]);
    Http::preventStrayRequests();
    $this->proofPath = storage_path('app/private/teaching-simulator/mailpit-safety.json');
    File::ensureDirectoryExists(dirname($this->proofPath));
    File::put($this->proofPath, json_encode(['kind' => 'schooltool.mailpit-safety', 'started_at' => date('c', time() - 30),
        'relay_environment_removed' => true, 'forwarding_environment_removed' => true, 'loopback_only' => true]));
});

afterEach(function (): void {
    File::delete($this->proofPath);
});

test('sending requires the owned Mailpit instance without external forwarding', function (string $case): void {
    $ui = ['MessageRelay' => ['Enabled' => false], 'Label' => 'Schooltool-Simulator'];
    $info = ['Database' => storage_path('app/private/teaching-simulator/mailpit.sqlite'), 'RuntimeStats' => ['Uptime' => 30]];
    if ($case === 'relay') {
        $ui['MessageRelay']['Enabled'] = true;
    }
    if ($case === 'forward') {
        $ui['MessageForward']['Enabled'] = true;
    }
    if ($case === 'foreign') {
        $info['Database'] = 'other.sqlite';
    }
    if ($case === 'restarted') {
        $info['RuntimeStats']['Uptime'] = 300;
    }
    if ($case === 'unowned') {
        File::delete($this->proofPath);
    }
    if ($case === 'external_smtp') {
        config(['mail.mailers.smtp.host' => 'smtp.example.test']);
    }
    if ($case === 'production') {
        $this->app['env'] = 'production';
    }
    Http::fake(['127.0.0.1:8025/api/v1/webui' => Http::response($ui),
        '127.0.0.1:8025/api/v1/info' => Http::response($info),
        '127.0.0.1:8025/api/v1/send' => Http::response(['ID' => 'Mailpittest'])]);
    $mailpit = app(TeachingSimulationMailpit::class);
    if ($case === 'safe') {
        expect($mailpit->send(['Subject' => '[Simulation] Test']))->toBe('Mailpittest');
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/send'));
    } else {
        expect(fn () => $mailpit->send(['Subject' => '[Simulation] Test']))->toThrow(HttpException::class);
        Http::assertNotSent(fn ($request) => str_ends_with($request->url(), '/send'));
    }
})->with(['safe', 'relay', 'forward', 'foreign', 'restarted', 'unowned', 'external_smtp', 'production']);
