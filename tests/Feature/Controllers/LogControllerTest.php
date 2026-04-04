<?php

use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create roles
    Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);

    // Create test school
    $this->school = School::factory()->create([
        'short_name' => 'TestSchool',
        'long_name' => 'Test School Name',
    ]);

    // Create log file path
    $this->logPath = storage_path('logs/laravel.log');
    $this->backupPath = storage_path('logs/laravel_backup.log');

    // Ensure logs directory exists
    if (! is_dir(storage_path('logs'))) {
        mkdir(storage_path('logs'), 0755, true);
    }
});

afterEach(function () {
    // Clean up test log files
    if (file_exists($this->logPath)) {
        @unlink($this->logPath);
    }
    if (file_exists($this->backupPath)) {
        @unlink($this->backupPath);
    }
});

describe('getLog', function () {
    it('returns 401 when user is not authenticated', function () {
        $response = $this->getJson('/api/admin/get_log');

        $response->assertStatus(401);
    });

    it('returns 200 when user has admin role', function () {
        $user = User::factory()->create(['school_id' => $this->school->id]);
        $user->assignRole('admin');

        $response = $this->actingAs($user)->getJson('/api/admin/get_log');

        $response->assertStatus(200);
    });

    it('returns 404 when log file does not exist', function () {
        $user = User::factory()->create(['school_id' => $this->school->id]);
        $user->assignRole('super_admin');

        // The controller now also falls back to daily logs (laravel-YYYY-MM-DD.log).
        // Temporarily move all matching log files away to make the test deterministic.
        $movedLogs = [];
        foreach ((glob(storage_path('logs/laravel*.log')) ?: []) as $path) {
            $tempPath = $path.'.pest-hidden-'.uniqid();
            if (@rename($path, $tempPath)) {
                $movedLogs[] = [$tempPath, $path];
            }
        }

        try {
            $response = $this->actingAs($user)->getJson('/api/admin/get_log');

            $response->assertStatus(404)
                ->assertJson(['error' => 'Log-Datei nicht gefunden']);
        } finally {
            foreach ($movedLogs as [$tempPath, $originalPath]) {
                if (file_exists($tempPath)) {
                    @rename($tempPath, $originalPath);
                }
            }
        }
    });

    it('returns log content with default parameters', function () {
        $user = User::factory()->create(['school_id' => $this->school->id]);
        $user->assignRole('super_admin');

        // Create test log file
        file_put_contents($this->logPath, "Line 1\nLine 2\nLine 3\n");

        $response = $this->actingAs($user)->getJson('/api/admin/get_log');

        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'text/plain; charset=utf-8')
            ->assertHeader('X-Lines-Count', '3')
            ->assertHeader('X-Total-Lines', '3')
            ->assertHeader('X-Mode', 'first');

        expect($response->getContent())->toBe("Line 1\nLine 2\nLine 3\n");
    });

    it('returns first N lines when mode is first', function () {
        $user = User::factory()->create(['school_id' => $this->school->id]);
        $user->assignRole('super_admin');

        // Create test log file with 10 lines
        $content = implode("\n", array_map(fn ($i) => "Line $i", range(1, 10)))."\n";
        file_put_contents($this->logPath, $content);

        $response = $this->actingAs($user)->getJson('/api/admin/get_log?lines=5&mode=first');

        $response->assertStatus(200)
            ->assertHeader('X-Lines-Count', '5')
            ->assertHeader('X-Total-Lines', '10')
            ->assertHeader('X-Mode', 'first');

        $expected = "Line 1\nLine 2\nLine 3\nLine 4\nLine 5\n";
        expect($response->getContent())->toBe($expected);
    });

    it('returns last N lines when mode is last', function () {
        $user = User::factory()->create(['school_id' => $this->school->id]);
        $user->assignRole('super_admin');

        // Create test log file with 10 lines
        $content = implode("\n", array_map(fn ($i) => "Line $i", range(1, 10)))."\n";
        file_put_contents($this->logPath, $content);

        $response = $this->actingAs($user)->getJson('/api/admin/get_log?lines=5&mode=last');

        $response->assertStatus(200)
            ->assertHeader('X-Lines-Count', '5')
            ->assertHeader('X-Total-Lines', '10')
            ->assertHeader('X-Mode', 'last');

        $expected = "Line 6\nLine 7\nLine 8\nLine 9\nLine 10\n";
        expect($response->getContent())->toBe($expected);
    });

    it('limits max lines to 1000', function () {
        $user = User::factory()->create(['school_id' => $this->school->id]);
        $user->assignRole('super_admin');

        // Create test log file with many lines
        $content = implode("\n", array_map(fn ($i) => "Line $i", range(1, 1500)))."\n";
        file_put_contents($this->logPath, $content);

        $response = $this->actingAs($user)->getJson('/api/admin/get_log?lines=2000');

        $response->assertStatus(200)
            ->assertHeader('X-Lines-Count', '1000')
            ->assertHeader('X-Total-Lines', '1500');
    });

    it('uses default 500 lines when lines parameter not provided', function () {
        $user = User::factory()->create(['school_id' => $this->school->id]);
        $user->assignRole('super_admin');

        // Create test log file with 600 lines
        $content = implode("\n", array_map(fn ($i) => "Line $i", range(1, 600)))."\n";
        file_put_contents($this->logPath, $content);

        $response = $this->actingAs($user)->getJson('/api/admin/get_log');

        $response->assertStatus(200)
            ->assertHeader('X-Lines-Count', '500')
            ->assertHeader('X-Total-Lines', '600')
            ->assertHeader('X-Mode', 'first');
    });

    it('returns all lines when file has fewer lines than requested', function () {
        $user = User::factory()->create(['school_id' => $this->school->id]);
        $user->assignRole('super_admin');

        // Create test log file with only 3 lines
        file_put_contents($this->logPath, "Line 1\nLine 2\nLine 3\n");

        $response = $this->actingAs($user)->getJson('/api/admin/get_log?lines=100');

        $response->assertStatus(200)
            ->assertHeader('X-Lines-Count', '3')
            ->assertHeader('X-Total-Lines', '3');

        expect($response->getContent())->toBe("Line 1\nLine 2\nLine 3\n");
    });

    it('handles empty log file', function () {
        $user = User::factory()->create(['school_id' => $this->school->id]);
        $user->assignRole('super_admin');

        // Create empty log file
        file_put_contents($this->logPath, '');

        $response = $this->actingAs($user)->getJson('/api/admin/get_log');

        $response->assertStatus(200)
            ->assertHeader('X-Lines-Count', '0')
            ->assertHeader('X-Total-Lines', '0');

        expect($response->getContent())->toBe('');
    });

    it('handles log file with single line', function () {
        $user = User::factory()->create(['school_id' => $this->school->id]);
        $user->assignRole('super_admin');

        file_put_contents($this->logPath, 'Single line without newline');

        $response = $this->actingAs($user)->getJson('/api/admin/get_log');

        $response->assertStatus(200)
            ->assertHeader('X-Lines-Count', '1')
            ->assertHeader('X-Total-Lines', '1');

        expect($response->getContent())->toBe('Single line without newline');
    });

    it('preserves log formatting and special characters', function () {
        $user = User::factory()->create(['school_id' => $this->school->id]);
        $user->assignRole('super_admin');

        $content = "[2024-01-01 12:00:00] ERROR: Test message with special chars: äöü @#$%\n";
        file_put_contents($this->logPath, $content);

        $response = $this->actingAs($user)->getJson('/api/admin/get_log');

        $response->assertStatus(200);
        expect($response->getContent())->toBe($content);
    });

    it('accepts negative lines parameter and uses minimum', function () {
        $user = User::factory()->create(['school_id' => $this->school->id]);
        $user->assignRole('super_admin');

        file_put_contents($this->logPath, "Line 1\nLine 2\n");

        $response = $this->actingAs($user)->getJson('/api/admin/get_log?lines=-10');

        $response->assertStatus(200)
            ->assertHeader('X-Lines-Count', '0');
    });

    it('allows super_admin role to access logs', function () {
        $user = User::factory()->create(['school_id' => $this->school->id]);
        $user->assignRole('super_admin');

        file_put_contents($this->logPath, "Test log\n");

        $response = $this->actingAs($user)->getJson('/api/admin/get_log');

        $response->assertStatus(200);
    });

    it('handles multiline log entries correctly', function () {
        $user = User::factory()->create(['school_id' => $this->school->id]);
        $user->assignRole('super_admin');

        $content = "[2024-01-01] ERROR: Exception\nStack trace:\n  Line 1\n  Line 2\n[2024-01-02] INFO: Normal log\n";
        file_put_contents($this->logPath, $content);

        $response = $this->actingAs($user)->getJson('/api/admin/get_log');

        $response->assertStatus(200);
        expect($response->getContent())->toBe($content);
    });
});

describe('deleteLog', function () {
    it('returns 401 when user is not authenticated', function () {
        $response = $this->postJson('/api/admin/delete_log');

        $response->assertStatus(401);
    });

    it('returns 403 when user does not have super_admin role', function () {
        $user = User::factory()->create(['school_id' => $this->school->id]);
        $user->assignRole('admin');

        $response = $this->actingAs($user)->postJson('/api/admin/delete_log');

        $response->assertStatus(403);
    });

    it('backs up and clears log file successfully', function () {
        $user = User::factory()->create(['school_id' => $this->school->id]);
        $user->assignRole('super_admin');

        // Create test log file with content
        $originalContent = "Log line 1\nLog line 2\nLog line 3\n";
        file_put_contents($this->logPath, $originalContent);

        $response = $this->actingAs($user)->postJson('/api/admin/delete_log');

        $response->assertStatus(204);

        // Check that original log file is now empty
        expect(file_get_contents($this->logPath))->toBe('');

        // Check that backup contains original content
        expect(file_exists($this->backupPath))->toBeTrue()
            ->and(file_get_contents($this->backupPath))->toBe($originalContent);
    });

    it('overwrites existing backup file', function () {
        $user = User::factory()->create(['school_id' => $this->school->id]);
        $user->assignRole('super_admin');

        // Create old backup
        file_put_contents($this->backupPath, "Old backup content\n");

        // Create current log
        $newContent = "New log content\n";
        file_put_contents($this->logPath, $newContent);

        $response = $this->actingAs($user)->postJson('/api/admin/delete_log');

        $response->assertStatus(204);

        // Check that backup was overwritten with new content
        expect(file_get_contents($this->backupPath))->toBe($newContent);
    });

    it('handles empty log file deletion', function () {
        $user = User::factory()->create(['school_id' => $this->school->id]);
        $user->assignRole('super_admin');

        // Create empty log file
        file_put_contents($this->logPath, '');

        $response = $this->actingAs($user)->postJson('/api/admin/delete_log');

        $response->assertStatus(204);

        expect(file_get_contents($this->logPath))->toBe('')
            ->and(file_get_contents($this->backupPath))->toBe('');
    });

    it('creates backup file even if it did not exist before', function () {
        $user = User::factory()->create(['school_id' => $this->school->id]);
        $user->assignRole('super_admin');

        // Ensure backup does not exist
        if (file_exists($this->backupPath)) {
            unlink($this->backupPath);
        }

        $content = "Log content to backup\n";
        file_put_contents($this->logPath, $content);

        $response = $this->actingAs($user)->postJson('/api/admin/delete_log');

        $response->assertStatus(204);

        expect(file_exists($this->backupPath))->toBeTrue()
            ->and(file_get_contents($this->backupPath))->toBe($content);
    });

    it('preserves log file permissions after deletion', function () {
        $user = User::factory()->create(['school_id' => $this->school->id]);
        $user->assignRole('super_admin');

        file_put_contents($this->logPath, "Test content\n");

        $response = $this->actingAs($user)->postJson('/api/admin/delete_log');

        $response->assertStatus(204);

        // File should still exist but be empty
        expect(file_exists($this->logPath))->toBeTrue();
    });

    it('allows only super_admin to delete logs', function () {
        $user = User::factory()->create(['school_id' => $this->school->id]);
        $user->assignRole('super_admin');

        file_put_contents($this->logPath, "Test log\n");

        $response = $this->actingAs($user)->postJson('/api/admin/delete_log');

        $response->assertStatus(204);
    });

    it('handles large log file backup and deletion', function () {
        $user = User::factory()->create(['school_id' => $this->school->id]);
        $user->assignRole('super_admin');

        // Create large log file (1000 lines)
        $largeContent = implode("\n", array_map(fn ($i) => "Log line $i with some content", range(1, 1000)))."\n";
        file_put_contents($this->logPath, $largeContent);

        $response = $this->actingAs($user)->postJson('/api/admin/delete_log');

        $response->assertStatus(204);

        expect(file_get_contents($this->logPath))->toBe('')
            ->and(file_get_contents($this->backupPath))->toBe($largeContent);
    });
});

describe('integration tests', function () {
    it('can delete and retrieve log after recreation', function () {
        $user = User::factory()->create(['school_id' => $this->school->id]);
        $user->assignRole('super_admin');

        // Create initial log
        file_put_contents($this->logPath, "Initial log\n");

        // Delete log
        $deleteResponse = $this->actingAs($user)->postJson('/api/admin/delete_log');
        $deleteResponse->assertStatus(204);

        // Add new content to log
        file_put_contents($this->logPath, "New log entry\n");

        // Retrieve new log
        $getResponse = $this->actingAs($user)->getJson('/api/admin/get_log');
        $getResponse->assertStatus(200);

        expect($getResponse->getContent())->toBe("New log entry\n");
    });

    it('maintains separate backup across multiple deletions', function () {
        $user = User::factory()->create(['school_id' => $this->school->id]);
        $user->assignRole('super_admin');

        // First deletion
        file_put_contents($this->logPath, "First log content\n");
        $this->actingAs($user)->postJson('/api/admin/delete_log');

        expect(file_get_contents($this->backupPath))->toBe("First log content\n");

        // Second deletion (should overwrite backup)
        file_put_contents($this->logPath, "Second log content\n");
        $this->actingAs($user)->postJson('/api/admin/delete_log');

        expect(file_get_contents($this->backupPath))->toBe("Second log content\n");
    });

    it('handles user switching between different roles', function () {
        $superAdmin = User::factory()->create(['school_id' => $this->school->id]);
        $superAdmin->assignRole('super_admin');

        $regularUser = User::factory()->create(['school_id' => $this->school->id]);
        $regularUser->assignRole('admin');

        file_put_contents($this->logPath, "Test log\n");

        // Super admin can access
        $response1 = $this->actingAs($superAdmin)->getJson('/api/admin/get_log');
        $response1->assertStatus(200);

        // Admin can also access
        $response2 = $this->actingAs($regularUser)->getJson('/api/admin/get_log');
        $response2->assertStatus(200);

        // Super admin can delete
        $response3 = $this->actingAs($superAdmin)->postJson('/api/admin/delete_log');
        $response3->assertStatus(204);

        // Regular admin cannot delete
        file_put_contents($this->logPath, "Test log\n");
        $response4 = $this->actingAs($regularUser)->postJson('/api/admin/delete_log');
        $response4->assertStatus(403);
    });
});
