<?php

/**
 * LogController Tests
 *
 * Covers log access and deletion for super_admin only.
 */

use App\Models\School;
use App\Models\Schoolyear;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->school = School::factory()->create();
    $this->schoolyear = Schoolyear::factory()->create(['school_id' => $this->school->id]);

    Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

    $this->superAdmin = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
    ]);
    $this->superAdmin->assignRole('super_admin');

    $this->admin = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
    ]);
    $this->admin->assignRole('admin');

    $this->logPath = storage_path('logs/laravel.log');
    $this->backupPath = storage_path('logs/laravel_backup.log');

    $this->hadLog = file_exists($this->logPath);
    $this->originalLog = $this->hadLog ? file_get_contents($this->logPath) : null;
    $this->hadBackup = file_exists($this->backupPath);
    $this->originalBackup = $this->hadBackup ? file_get_contents($this->backupPath) : null;
});

afterEach(function () {
    if ($this->hadLog) {
        file_put_contents($this->logPath, $this->originalLog);
    } elseif (file_exists($this->logPath)) {
        @unlink($this->logPath);
    }

    if ($this->hadBackup) {
        file_put_contents($this->backupPath, $this->originalBackup);
    } elseif (file_exists($this->backupPath)) {
        @unlink($this->backupPath);
    }
});

test('log administration endpoints require authentication', function (string $method, string $uri) {
    $this->json($method, $uri)->assertUnauthorized();
})->with([
    'read log' => ['GET', '/api/admin/get_log'],
    'list logs' => ['GET', '/api/admin/list_logs'],
    'delete log' => ['POST', '/api/admin/delete_log'],
    'restart queues' => ['POST', '/api/admin/restart_queues'],
]);

test('log administration endpoints forbid the admin role', function (string $method, string $uri) {
    $this->actingAs($this->admin, 'sanctum')
        ->json($method, $uri)
        ->assertForbidden();
})->with([
    'read log' => ['GET', '/api/admin/get_log'],
    'list logs' => ['GET', '/api/admin/list_logs'],
    'delete log' => ['POST', '/api/admin/delete_log'],
    'restart queues' => ['POST', '/api/admin/restart_queues'],
]);

test('list_logs allows super administrators to list contained log files', function () {
    file_put_contents($this->logPath, "example\n");

    $this->actingAs($this->superAdmin, 'sanctum')
        ->getJson('/api/admin/list_logs')
        ->assertSuccessful()
        ->assertJsonFragment([
            'name' => 'laravel.log',
        ]);
});

test('get_log returns 404 when log is missing', function () {
    $movedLogs = [];
    foreach ((glob(storage_path('logs/laravel*.log')) ?: []) as $path) {
        $tempPath = $path.'.pest-hidden-'.uniqid();
        if (@rename($path, $tempPath)) {
            $movedLogs[] = [$tempPath, $path];
        }
    }

    try {
        $this->actingAs($this->superAdmin, 'sanctum');

        $this->getJson('/api/admin/get_log')
            ->assertStatus(404)
            ->assertJsonFragment(['error' => 'Log-Datei nicht gefunden']);
    } finally {
        foreach ($movedLogs as [$tempPath, $originalPath]) {
            if (file_exists($tempPath)) {
                @rename($tempPath, $originalPath);
            }
        }
    }
});

test('get_log returns content and headers', function () {
    $lines = [
        "line-1\n",
        "line-2\n",
        "line-3\n",
    ];
    file_put_contents($this->logPath, implode('', $lines));

    $this->actingAs($this->superAdmin, 'sanctum');

    $response = $this->get('/api/admin/get_log?lines=2&mode=last');

    $response->assertStatus(200)
        ->assertHeader('Content-Type', 'text/plain; charset=utf-8')
        ->assertHeader('X-Lines-Count', '2')
        ->assertHeader('X-Total-Lines', '3')
        ->assertHeader('X-Mode', 'last');

    expect($response->getContent())->toBe("line-2\nline-3\n");
});

test('get_log rejects unsafe or unbounded parameters', function (array $query, string $field) {
    file_put_contents($this->logPath, "line-1\n");

    $this->actingAs($this->superAdmin, 'sanctum')
        ->getJson('/api/admin/get_log?'.http_build_query($query))
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    'path traversal' => [['filename' => '../laravel.log'], 'filename'],
    'non-log file' => [['filename' => 'laravel.txt'], 'filename'],
    'zero lines' => [['lines' => 0], 'lines'],
    'too many lines' => [['lines' => 1001], 'lines'],
    'non-integer lines' => [['lines' => 'many'], 'lines'],
    'unknown mode' => [['mode' => 'all'], 'mode'],
]);

test('delete_log rejects unsafe filenames without changing the current log', function () {
    $content = "must remain\n";
    file_put_contents($this->logPath, $content);

    $this->actingAs($this->superAdmin, 'sanctum')
        ->postJson('/api/admin/delete_log', [
            'filename' => '../laravel.log',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('filename');

    expect(file_get_contents($this->logPath))->toBe($content);
});

test('delete_log clears file and writes backup', function () {
    $content = "first\nsecond\n";
    file_put_contents($this->logPath, $content);

    $this->actingAs($this->superAdmin, 'sanctum');

    $this->postJson('/api/admin/delete_log')
        ->assertStatus(204);

    expect(file_get_contents($this->logPath))->toBe('');
    expect(file_get_contents($this->backupPath))->toBe($content);
});

test('restart_queues restarts workers and terminates Horizon without clearing application cache', function () {
    Artisan::shouldReceive('call')->once()->with('queue:restart')->andReturn(0);
    Artisan::shouldReceive('call')->once()->with('horizon:terminate')->andReturn(0);

    $this->actingAs($this->superAdmin, 'sanctum')
        ->postJson('/api/admin/restart_queues')
        ->assertNoContent();
});

test('restart_queues still returns success when Horizon termination fails', function () {
    Artisan::shouldReceive('call')->once()->with('queue:restart')->andReturn(0);
    Artisan::shouldReceive('call')
        ->once()
        ->with('horizon:terminate')
        ->andThrow(new RuntimeException('Horizon unavailable'));

    $this->actingAs($this->superAdmin, 'sanctum')
        ->postJson('/api/admin/restart_queues')
        ->assertNoContent();
});
