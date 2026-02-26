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

test('get_log allows admin role', function () {
    $this->actingAs($this->admin, 'sanctum');

    $this->getJson('/api/admin/get_log')
        ->assertStatus(200);
});

test('get_log returns 404 when log is missing', function () {
    $movedLogs = [];
    foreach ((glob(storage_path('logs/laravel*.log')) ?: []) as $path) {
        $tempPath = $path . '.pest-hidden-' . uniqid();
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

test('delete_log clears file and writes backup', function () {
    $content = "first\nsecond\n";
    file_put_contents($this->logPath, $content);

    $this->actingAs($this->superAdmin, 'sanctum');

    $this->postJson('/api/admin/delete_log')
        ->assertStatus(204);

    expect(file_get_contents($this->logPath))->toBe('');
    expect(file_get_contents($this->backupPath))->toBe($content);
});
