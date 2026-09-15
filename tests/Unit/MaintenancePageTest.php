<?php

use GuzzleHttp\Client;
use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Process\Process;
use Tests\TestCase;

uses(TestCase::class);

test('maintenance view renders the standalone page without frontend assets', function () {
    $html = view('maintenance')->render();

    expect(trim($html))->toBe(trim(file_get_contents(public_path('maintenance.html'))))
        ->toContain('Wir aktualisieren SchoolTool.', '/deployment-status.php', "getJson('/up')")
        ->not->toContain('/build/', '@vite', 'location.reload');
});

test('apache serves maintenance without PHP and preserves static assets and status access', function () {
    $apacheRoot = 'C:/laragon/bin/apache/httpd-2.4.62-240904-win64-VS17';
    $executable = $apacheRoot.'/bin/httpd.exe';
    if (PHP_OS_FAMILY !== 'Windows' || ! is_file($executable)) {
        $this->markTestSkipped('The local Apache runtime is unavailable.');
    }

    $filesystem = new Filesystem;
    $directory = sys_get_temp_dir().'/schooltool-maintenance-'.bin2hex(random_bytes(8));
    $filesystem->ensureDirectoryExists($directory.'/public/assets');
    $filesystem->ensureDirectoryExists($directory.'/storage/framework');
    $root = str_replace('\\', '/', $directory);
    $socket = stream_socket_server('tcp://127.0.0.1:0');
    $port = (int) substr(strrchr(stream_socket_get_name($socket, false), ':'), 1);
    fclose($socket);
    $filesystem->copy(public_path('.htaccess'), $directory.'/public/.htaccess');
    $filesystem->copy(public_path('maintenance.html'), $directory.'/public/maintenance.html');
    file_put_contents($directory.'/public/assets/example.css', 'body {}');
    file_put_contents($directory.'/public/deployment-status.php', '{"state":"maintenance","id":null}');
    file_put_contents($directory.'/public/index.php', 'Application entry point');
    file_put_contents($directory.'/storage/framework/down', '{}');
    file_put_contents($directory.'/httpd.conf', <<<CONF
ServerRoot "{$apacheRoot}"
Listen 127.0.0.1:{$port}
ServerName localhost
LoadModule authz_core_module modules/mod_authz_core.so
LoadModule rewrite_module modules/mod_rewrite.so
LoadModule headers_module modules/mod_headers.so
LoadModule dir_module modules/mod_dir.so
DocumentRoot "{$root}/public"
PidFile "{$root}/httpd.pid"
ErrorLog "{$root}/error.log"
<Directory "{$root}/public">
    AllowOverride All
    Require all granted
    DirectoryIndex index.php
</Directory>
CONF);
    $process = new Process([$executable, '-X', '-f', $directory.'/httpd.conf']);

    try {
        $process->start();
        $ready = false;
        for ($attempt = 0; $attempt < 50; $attempt++) {
            $connection = @stream_socket_client("tcp://127.0.0.1:{$port}", $errorCode, $errorMessage, 0.1);
            if (is_resource($connection)) {
                fclose($connection);
                $ready = true;
                break;
            }
            usleep(100000);
        }
        expect($ready)->toBeTrue($process->getErrorOutput());

        $client = new Client(['base_uri' => "http://127.0.0.1:{$port}", 'http_errors' => false]);
        foreach (['/', '/student/course/17', '/api/homepage/student/courses/17'] as $path) {
            $response = $client->get($path);
            expect($response->getStatusCode())->toBe(503)
                ->and((string) $response->getBody())->toContain('Wir aktualisieren SchoolTool.')
                ->and($response->getHeaderLine('Cache-Control'))->toContain('no-store')
                ->and($response->getHeaderLine('Retry-After'))->toBe('15');
        }
        expect($client->get('/assets/example.css')->getStatusCode())->toBe(200)
            ->and($client->get('/deployment-status.php')->getStatusCode())->toBe(200)
            ->and($client->get('/maintenance.html')->getStatusCode())->toBe(200);

        unlink($directory.'/storage/framework/down');
        $response = $client->get('/student/course/17');
        expect($response->getStatusCode())->toBe(200)
            ->and((string) $response->getBody())->toBe('Application entry point');
    } finally {
        $process->stop();
        expect(str_replace('\\', '/', realpath($directory)))->toStartWith(str_replace('\\', '/', realpath(sys_get_temp_dir())).'/schooltool-maintenance-');
        $filesystem->deleteDirectory($directory);
    }
});
