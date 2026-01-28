<?php

/**
 * FileUploadController Tests (Teaching Module)
 *
 * Tests the file upload functionality for teaching imports including:
 * - Authorization for admin and teaching_admin roles
 * - Slug validation (only 116 and 166 allowed)
 * - XLSX file type validation
 * - Upload initiation (POST) and continuation (PATCH)
 * - Job dispatch for Import 116
 * - SchoolTool update for Import 166
 */

use App\Jobs\Teaching\Import116Job;
use App\Models\School;
use App\Models\SchoolTool;
use App\Models\Schoolyear;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();

    // Create required roles
    collect([
        'super_admin',
        'admin',
        'teaching_admin',
        'teacher',
        'user',
    ])->each(fn(string $role) => Role::firstOrCreate([
        'name' => $role,
        'guard_name' => 'web',
    ]));

    // Create test school
    $this->school = School::factory()->create([
        'short_name' => 'UPLOAD',
        'long_name' => 'Upload Test School',
    ]);

    $this->schoolyear = Schoolyear::factory()->create([
        'school_id' => $this->school->id,
    ]);

    // Create test users with different roles
    $this->admin = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'admin@upload.test',
    ]);
    $this->admin->assignRole('admin');

    $this->teachingAdmin = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'teachingadmin@upload.test',
    ]);
    $this->teachingAdmin->assignRole('teaching_admin');

    $this->teacher = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'teacher@upload.test',
    ]);
    $this->teacher->assignRole('teacher');

    $this->regularUser = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'user@upload.test',
    ]);
    $this->regularUser->assignRole('user');

    // Clean up temp directory
    $tempDir = storage_path('app/private/temp');
    if (is_dir($tempDir)) {
        $files = glob($tempDir . '/*');
        foreach ($files as $file) {
            if (is_dir($file)) {
                foreach (glob("$file/*.*") as $subFile) {
                    @unlink($subFile);
                }
                @rmdir($file);
            }
        }
    }
});

afterEach(function () {
    // Clean up any created directories - suppress errors as files may be locked
    $schoolDir = storage_path("app/private/{$this->school->id}");
    if (is_dir($schoolDir)) {
        $excelDir = $schoolDir . '/excel';
        if (is_dir($excelDir)) {
            foreach (glob("$excelDir/*.*") as $file) {
                @unlink($file);
            }
            @rmdir($excelDir);
        }
        @rmdir($schoolDir);
    }

    // Clean temp directory
    $tempDir = storage_path('app/private/temp');
    if (is_dir($tempDir)) {
        $files = glob($tempDir . '/*');
        foreach ($files as $file) {
            if (is_dir($file)) {
                foreach (glob("$file/*.*") as $subFile) {
                    @unlink($subFile);
                }
                @rmdir($file);
            }
        }
    }
});

// ============================================================================
// Upload (POST) Authorization Tests
// ============================================================================

describe('upload authorization', function () {
    test('returns 401 when user is not authenticated', function () {
        $response = $this->postJson('/api/admin/teaching_upload/116');

        $response->assertStatus(401);
    });

    test('returns 403 when user has no allowed role', function () {
        $this->actingAs($this->regularUser, 'sanctum');

        $response = $this->postJson('/api/admin/teaching_upload/116');

        $response->assertStatus(403);
    });

    test('returns 403 when teacher tries to upload', function () {
        $this->actingAs($this->teacher, 'sanctum');

        $response = $this->postJson('/api/admin/teaching_upload/116');

        $response->assertStatus(403);
    });

    test('admin can initiate upload', function () {
        $this->actingAs($this->admin, 'sanctum');

        $response = $this->postJson('/api/admin/teaching_upload/116');

        $response->assertStatus(200);

        // Should return a UUID
        $content = $response->getContent();
        expect($content)->toMatch('/^[a-f0-9-]{36}$/');
    });

    test('teaching_admin can initiate upload', function () {
        $this->actingAs($this->teachingAdmin, 'sanctum');

        $response = $this->postJson('/api/admin/teaching_upload/116');

        $response->assertStatus(200);

        $content = $response->getContent();
        expect($content)->toMatch('/^[a-f0-9-]{36}$/');
    });
});

// ============================================================================
// Slug Validation Tests
// ============================================================================

describe('slug validation', function () {
    test('rejects invalid slug', function () {
        $this->actingAs($this->admin, 'sanctum');

        $response = $this->postJson('/api/admin/teaching_upload/999');

        $response->assertStatus(422);
    });

    test('accepts slug 116', function () {
        $this->actingAs($this->admin, 'sanctum');

        $response = $this->postJson('/api/admin/teaching_upload/116');

        $response->assertStatus(200);
    });

    test('accepts slug 166', function () {
        $this->actingAs($this->admin, 'sanctum');

        $response = $this->postJson('/api/admin/teaching_upload/166');

        $response->assertStatus(200);
    });

    test('rejects slug with letters', function () {
        $this->actingAs($this->admin, 'sanctum');

        $response = $this->postJson('/api/admin/teaching_upload/abc');

        $response->assertStatus(422);
    });
});

// ============================================================================
// File Type Validation Tests
// ============================================================================

describe('file type validation', function () {
    test('rejects non-xlsx file on upload', function () {
        $this->actingAs($this->admin, 'sanctum');

        $response = $this->postJson('/api/admin/teaching_upload/116', [], [
            'Upload-Name' => 'test.pdf',
        ]);

        $response->assertStatus(422);
    });

    test('rejects non-xlsx file on uploadNext', function () {
        $this->actingAs($this->admin, 'sanctum');

        // First, initiate upload
        $initResponse = $this->postJson('/api/admin/teaching_upload/116');
        $uploadId = $initResponse->getContent();

        // Try to continue with non-xlsx file
        $response = $this->patchJson('/api/admin/teaching_upload/116?patch=' . $uploadId, [], [
            'Upload-Name' => 'test.pdf',
            'Upload-Length' => '100',
        ]);

        $response->assertStatus(422);
    });

    test('accepts xlsx file', function () {
        $this->actingAs($this->admin, 'sanctum');

        $response = $this->postJson('/api/admin/teaching_upload/116', [], [
            'Upload-Name' => 'import.xlsx',
        ]);

        $response->assertStatus(200);
    });

    test('accepts xls file', function () {
        $this->actingAs($this->admin, 'sanctum');

        $response = $this->postJson('/api/admin/teaching_upload/116', [], [
            'Upload-Name' => 'import.xls',
        ]);

        $response->assertStatus(200);
    });

    test('file extension check is case insensitive', function () {
        $this->actingAs($this->admin, 'sanctum');

        $response = $this->postJson('/api/admin/teaching_upload/116', [], [
            'Upload-Name' => 'import.XLSX',
        ]);

        $response->assertStatus(200);
    });
});

// ============================================================================
// UploadNext (PATCH) Authorization Tests
// ============================================================================

describe('uploadNext authorization', function () {
    test('returns 401 when user is not authenticated', function () {
        $response = $this->patchJson('/api/admin/teaching_upload/116?patch=test-id');

        $response->assertStatus(401);
    });

    test('returns 403 when user has no allowed role', function () {
        $this->actingAs($this->regularUser, 'sanctum');

        $response = $this->patchJson('/api/admin/teaching_upload/116?patch=test-id');

        $response->assertStatus(403);
    });

    test('returns 422 when patch id is missing', function () {
        $this->actingAs($this->admin, 'sanctum');

        $response = $this->patchJson('/api/admin/teaching_upload/116');

        $response->assertStatus(422);
    });
});

// ============================================================================
// Import 116 Job Dispatch Tests
// ============================================================================

describe('import 116 job dispatch', function () {
    test('dispatches Import116Job when 116 upload completes', function () {
        $this->actingAs($this->admin, 'sanctum');

        // Initiate upload
        $initResponse = $this->postJson('/api/admin/teaching_upload/116');
        $uploadId = $initResponse->getContent();

        // Create temp directory
        $tempDir = storage_path("app/private/temp/{$uploadId}");
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0775, true);
        }

        // Create a minimal valid XLSX content (just some bytes for testing)
        $content = 'PK'; // ZIP/XLSX magic bytes - minimal content

        // Complete the upload with proper headers
        $response = $this->call('PATCH', "/api/admin/teaching_upload/116?patch={$uploadId}", [], [], [], [
            'HTTP_Upload-Name' => '116.xlsx',
            'HTTP_Upload-Length' => strlen($content),
            'CONTENT_TYPE' => 'application/octet-stream',
        ], $content);

        Queue::assertPushed(Import116Job::class, function ($job) {
            return $job->user->id === $this->admin->id;
        });
    });

    test('does not dispatch Import116Job for 166 upload', function () {
        $this->actingAs($this->admin, 'sanctum');

        // Initiate upload
        $initResponse = $this->postJson('/api/admin/teaching_upload/166');
        $uploadId = $initResponse->getContent();

        // Create temp directory
        $tempDir = storage_path("app/private/temp/{$uploadId}");
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0775, true);
        }

        $content = 'PK';

        $response = $this->call('PATCH', "/api/admin/teaching_upload/166?patch={$uploadId}", [], [], [], [
            'HTTP_Upload-Name' => '166.xlsx',
            'HTTP_Upload-Length' => strlen($content),
            'CONTENT_TYPE' => 'application/octet-stream',
        ], $content);

        Queue::assertNotPushed(Import116Job::class);
    });
});

// ============================================================================
// Import 166 SchoolTool Update Tests
// ============================================================================

describe('import 166 school tool update', function () {
    test('updates SchoolTool import_166_at when 166 upload completes', function () {
        $this->actingAs($this->admin, 'sanctum');

        // Create SchoolTool record
        $schoolTool = SchoolTool::create([
            'school_id' => $this->school->id,
            'import_166_at' => null,
        ]);

        // Initiate upload
        $initResponse = $this->postJson('/api/admin/teaching_upload/166');
        $uploadId = $initResponse->getContent();

        // Create temp directory
        $tempDir = storage_path("app/private/temp/{$uploadId}");
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0775, true);
        }

        $content = 'PK';

        $response = $this->call('PATCH', "/api/admin/teaching_upload/166?patch={$uploadId}", [], [], [], [
            'HTTP_Upload-Name' => '166.xlsx',
            'HTTP_Upload-Length' => strlen($content),
            'CONTENT_TYPE' => 'application/octet-stream',
        ], $content);

        $schoolTool->refresh();
        expect($schoolTool->import_166_at)->not->toBeNull();
    });

    test('creates SchoolTool record if not exists when 166 upload completes', function () {
        $this->actingAs($this->admin, 'sanctum');

        // Ensure no SchoolTool record exists
        SchoolTool::where('school_id', $this->school->id)->delete();

        // Initiate upload
        $initResponse = $this->postJson('/api/admin/teaching_upload/166');
        $uploadId = $initResponse->getContent();

        // Create temp directory
        $tempDir = storage_path("app/private/temp/{$uploadId}");
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0775, true);
        }

        $content = 'PK';

        $response = $this->call('PATCH', "/api/admin/teaching_upload/166?patch={$uploadId}", [], [], [], [
            'HTTP_Upload-Name' => '166.xlsx',
            'HTTP_Upload-Length' => strlen($content),
            'CONTENT_TYPE' => 'application/octet-stream',
        ], $content);

        $schoolTool = SchoolTool::where('school_id', $this->school->id)->first();
        expect($schoolTool)->not->toBeNull()
            ->and($schoolTool->import_166_at)->not->toBeNull();
    });
});

// ============================================================================
// Upload Flow Tests
// ============================================================================

describe('upload flow', function () {
    test('complete upload flow for 116', function () {
        $this->actingAs($this->admin, 'sanctum');

        // Step 1: Initiate upload
        $initResponse = $this->postJson('/api/admin/teaching_upload/116');
        $initResponse->assertStatus(200);
        $uploadId = $initResponse->getContent();

        expect($uploadId)->toMatch('/^[a-f0-9-]{36}$/');

        // Step 2: Create temp directory (simulating what happens in real upload)
        $tempDir = storage_path("app/private/temp/{$uploadId}");
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0775, true);
        }

        // Step 3: Complete the upload
        $content = 'PK';

        $response = $this->call('PATCH', "/api/admin/teaching_upload/116?patch={$uploadId}", [], [], [], [
            'HTTP_Upload-Name' => '116.xlsx',
            'HTTP_Upload-Length' => strlen($content),
            'CONTENT_TYPE' => 'application/octet-stream',
        ], $content);

        // Verify job was dispatched
        Queue::assertPushed(Import116Job::class);
    });

    test('handles empty content gracefully', function () {
        $this->actingAs($this->admin, 'sanctum');

        // Initiate upload
        $initResponse = $this->postJson('/api/admin/teaching_upload/116');
        $uploadId = $initResponse->getContent();

        // Create temp directory
        $tempDir = storage_path("app/private/temp/{$uploadId}");
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0775, true);
        }

        // Send PATCH with empty content
        $response = $this->call('PATCH', "/api/admin/teaching_upload/116?patch={$uploadId}", [], [], [], [
            'HTTP_Upload-Name' => '116.xlsx',
            'HTTP_Upload-Length' => '0',
            'CONTENT_TYPE' => 'application/octet-stream',
        ], '');

        $response->assertStatus(204);
    });
});
