<?php

use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

use function SchoolTool\DeploymentStatus\readStatus;
use function SchoolTool\DeploymentStatus\writeStatus;

require_once dirname(__DIR__, 2).'/scripts/deployment-status.php';

beforeEach(function (): void {
    $this->deploymentDirectory = sys_get_temp_dir().'/schooltool-status-'.bin2hex(random_bytes(8));
    $filesystem = new Filesystem;

    foreach (['scripts', 'storage/framework', 'storage/logs', 'public', 'bin', 'deployment'] as $path) {
        $filesystem->ensureDirectoryExists($this->deploymentDirectory.'/'.$path);
    }

    foreach (['scripts/deployment-status.php', 'scripts/deploy_cloudways.sh', 'public/deployment-status.php'] as $path) {
        $filesystem->copy(dirname(__DIR__, 2).'/'.$path, $this->deploymentDirectory.'/'.$path);
    }
});

afterEach(function (): void {
    (new Filesystem)->deleteDirectory($this->deploymentDirectory);
});

it('keeps an opaque deployment id across state transitions and replaces it for the next deployment', function (): void {
    expect(readStatus($this->deploymentDirectory))->toBe(['state' => 'idle', 'id' => null]);

    writeStatus($this->deploymentDirectory, 'scheduled');
    $id = readStatus($this->deploymentDirectory)['id'];
    expect($id)->toMatch('/^[a-f0-9]{32}$/');

    touch($this->deploymentDirectory.'/storage/framework/down');
    writeStatus($this->deploymentDirectory, 'maintenance');
    expect(readStatus($this->deploymentDirectory))->toBe(['state' => 'maintenance', 'id' => $id]);

    unlink($this->deploymentDirectory.'/storage/framework/down');
    writeStatus($this->deploymentDirectory, 'completed');
    expect(readStatus($this->deploymentDirectory))->toBe(['state' => 'completed', 'id' => $id]);

    writeStatus($this->deploymentDirectory, 'scheduled');
    expect(readStatus($this->deploymentDirectory)['id'])->not->toBe($id)
        ->and(glob($this->deploymentDirectory.'/storage/framework/*.tmp'))->toBe([]);
});

it('uses the actual maintenance marker even when public status says the site is available', function (string $state, string $expected): void {
    writeStatus($this->deploymentDirectory, $state);
    touch($this->deploymentDirectory.'/storage/framework/down');

    expect(readStatus($this->deploymentDirectory)['state'])->toBe($expected);
})->with([
    ['idle', 'maintenance'],
    ['scheduled', 'maintenance'],
    ['completed', 'maintenance'],
    ['failed', 'failed'],
]);

it('clears stale maintenance status after manual recovery', function (string $state): void {
    writeStatus($this->deploymentDirectory, $state);

    expect(readStatus($this->deploymentDirectory)['state'])->toBe('idle');
})->with(['maintenance', 'failed']);

it('never exposes unexpected status fields or invalid ids', function (): void {
    file_put_contents($this->deploymentDirectory.'/storage/framework/deployment-status.json', json_encode([
        'state' => 'scheduled', 'id' => 'secret', 'token' => 'private-token',
    ]));

    expect(readStatus($this->deploymentDirectory))->toBe(['state' => 'scheduled', 'id' => null]);

    file_put_contents($this->deploymentDirectory.'/storage/framework/deployment-status.json', '{broken');
    touch($this->deploymentDirectory.'/storage/framework/down');

    expect(readStatus($this->deploymentDirectory))->toBe(['state' => 'maintenance', 'id' => null]);
});

it('rejects invalid CLI states without changing status', function (): void {
    writeStatus($this->deploymentDirectory, 'scheduled');
    $before = readStatus($this->deploymentDirectory);
    $process = new Process([PHP_BINARY, 'scripts/deployment-status.php', 'unknown'], $this->deploymentDirectory);
    $process->run();

    expect($process->getExitCode())->toBe(1)
        ->and(readStatus($this->deploymentDirectory))->toBe($before);
});

it('serves uncached status without Laravel or vendor and accepts only read methods', function (): void {
    $socket = stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);
    $address = stream_socket_get_name($socket, false);
    fclose($socket);
    $server = new Process([PHP_BINARY, '-S', $address, '-t', 'public'], $this->deploymentDirectory);
    $server->start();

    try {
        $url = 'http://'.$address.'/deployment-status.php';
        $body = false;

        for ($attempt = 0; $attempt < 100; $attempt++) {
            $body = @file_get_contents($url);

            if ($body !== false) {
                break;
            }

            usleep(20000);
        }

        expect(json_decode($body, true))->toBe(['state' => 'idle', 'id' => null])
            ->and(implode("\n", $http_response_header))->toContain('Cache-Control: no-store', 'X-Content-Type-Options: nosniff');

        touch($this->deploymentDirectory.'/storage/framework/down');
        expect(json_decode(file_get_contents($url), true)['state'])->toBe('maintenance');

        $head = file_get_contents($url, false, stream_context_create(['http' => ['method' => 'HEAD']]));
        expect($head)->toBe('');

        $post = file_get_contents($url, false, stream_context_create(['http' => ['method' => 'POST', 'ignore_errors' => true]]));
        expect($post)->toBe('')
            ->and(implode("\n", $http_response_header))->toContain('405', 'Allow: GET, HEAD');
    } finally {
        $server->stop();
    }
});

function writeStatusFixtureExecutable(string $directory, string $name, string $script): void
{
    $path = $directory.'/bin/'.$name;
    file_put_contents($path, str_replace("\r\n", "\n", $script));
    chmod($path, 0777);
}

function runStatusDeploymentFixture(string $directory, string $failure = '', bool $prepare = false, string $noticeSeconds = '0'): Process
{
    writeStatusFixtureExecutable($directory, 'php', <<<'BASH'
#!/usr/bin/bash
set -e
printf '%s\n' "$*" >> calls.log
if [ "$1" = scripts/deployment-status.php ]; then
    if [ "$TEST_FAILURE" = completed-status ] && [ "$2" = completed ]; then
        exit 1
    fi
    exec "$TEST_PHP_BINARY" "$@"
fi
if [ "$1 $2" = 'artisan down' ]; then
    if [ "$TEST_FAILURE" = down ]; then
        exit 1
    fi
    touch storage/framework/down
fi
if [ "$1 $2" = 'artisan up' ]; then
    if [ "$TEST_FAILURE" = up ]; then
        exit 1
    fi
    rm -f storage/framework/down
fi
if [ "$1" = scripts/source-manifest.php ] && [ "$TEST_FAILURE" = prebackend ]; then
    exit 1
fi
exit 0
BASH);
    writeStatusFixtureExecutable($directory, 'composer', <<<'BASH'
#!/usr/bin/bash
printf 'composer install\n' >> calls.log
if [ "$TEST_FAILURE" = backend ]; then
    exit 1
fi
BASH);
    writeStatusFixtureExecutable($directory, 'flock', "#!/usr/bin/bash\nexit 0\n");
    writeStatusFixtureExecutable($directory, 'pgrep', "#!/usr/bin/bash\nexit 1\n");
    writeStatusFixtureExecutable($directory, 'tar', <<<'BASH'
#!/usr/bin/bash
printf '{}\n' > "$4/manifest.json"
cp deployment/source-commit "$4/deployment-source.txt"
BASH);
    foreach (['frontend-build.tar.gz', 'frontend-build.sha256', 'source-manifest.sha256'] as $path) {
        file_put_contents($directory.'/deployment/'.$path, 'fixture');
    }
    file_put_contents($directory.'/deployment/source-commit', str_repeat('a', 40));
    (new Filesystem)->ensureDirectoryExists($directory.'/public/build');
    file_put_contents($directory.'/public/build/previous-build.txt', 'previous');
    $bash = PHP_OS_FAMILY === 'Windows' ? 'C:\\Program Files\\Git\\bin\\bash.exe' : 'bash';
    $process = new Process([
        $bash, '-c', 'export PATH="$PWD/bin:$PATH"; /usr/bin/bash scripts/deploy_cloudways.sh ${TEST_PREPARE}',
    ], $directory, [
        'TEST_PHP_BINARY' => str_replace('\\', '/', PHP_BINARY),
        'TEST_FAILURE' => $failure,
        'TEST_PREPARE' => $prepare ? '--prepare' : '',
        'DEPLOY_NOTICE_SECONDS' => $noticeSeconds,
    ]);
    $process->run();

    return $process;
}

it('announces before maintenance and reports completion only after the application is up', function (): void {
    $process = runStatusDeploymentFixture($this->deploymentDirectory);
    $calls = file_get_contents($this->deploymentDirectory.'/calls.log');

    expect($process->isSuccessful())->toBeTrue($process->getErrorOutput())
        ->and($calls)->toMatch('/queue:health-check.*deployment-status.php scheduled.*artisan down --render=maintenance --retry=15.*composer install.*artisan up.*deployment-status.php completed/s')
        ->and(readStatus($this->deploymentDirectory)['state'])->toBe('completed')
        ->and(is_file($this->deploymentDirectory.'/storage/framework/down'))->toBeFalse();
});

it('leaves maintenance prepared across the terminal pull handoff', function (): void {
    $process = runStatusDeploymentFixture($this->deploymentDirectory, prepare: true);

    expect($process->isSuccessful())->toBeTrue($process->getErrorOutput())
        ->and(readStatus($this->deploymentDirectory)['state'])->toBe('maintenance')
        ->and(trim(file_get_contents($this->deploymentDirectory.'/storage/framework/cloudways-deploy-maintenance')))->toBe('prepared');
});

it('keeps the deployed frontend online if publishing the completion notice fails', function (): void {
    $process = runStatusDeploymentFixture($this->deploymentDirectory, 'completed-status');

    expect($process->isSuccessful())->toBeTrue($process->getErrorOutput())
        ->and($process->getErrorOutput())->toContain('completion notice could not be published')
        ->and(readStatus($this->deploymentDirectory)['state'])->toBe('idle')
        ->and(is_file($this->deploymentDirectory.'/storage/framework/down'))->toBeFalse()
        ->and(is_file($this->deploymentDirectory.'/public/build/previous-build.txt'))->toBeFalse()
        ->and(file_get_contents($this->deploymentDirectory.'/public/build/deployment-source.txt'))->toBe(str_repeat('a', 40));
});

it('restores service on prebackend failure but stays down after backend changes begin', function (string $failure, string $expected, bool $down): void {
    $process = runStatusDeploymentFixture($this->deploymentDirectory, $failure);

    expect($process->isSuccessful())->toBeFalse()
        ->and(readStatus($this->deploymentDirectory)['state'])->toBe($expected, $process->getOutput().$process->getErrorOutput().file_get_contents($this->deploymentDirectory.'/calls.log'))
        ->and(is_file($this->deploymentDirectory.'/storage/framework/down'))->toBe($down);
})->with([
    ['down', 'idle', false],
    ['prebackend', 'idle', false],
    ['backend', 'failed', true],
    ['up', 'failed', true],
]);

it('rejects invalid announcement durations before changing deployment state', function (string $noticeSeconds): void {
    $process = runStatusDeploymentFixture($this->deploymentDirectory, noticeSeconds: $noticeSeconds);

    expect($process->isSuccessful())->toBeFalse()
        ->and($process->getErrorOutput())->toContain('DEPLOY_NOTICE_SECONDS must be an integer between 0 and 300.')
        ->and(readStatus($this->deploymentDirectory))->toBe(['state' => 'idle', 'id' => null])
        ->and(is_file($this->deploymentDirectory.'/calls.log'))->toBeFalse();
})->with(['-1', '301', '00', '1.5', 'invalid']);
