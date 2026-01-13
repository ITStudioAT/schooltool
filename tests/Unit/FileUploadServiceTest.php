<?php

use App\Services\FileUploadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

uses(TestCase::class);

// Helper function to recursively clean directories without deleting existing environment files
function cleanupTempDirectory(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }

    $files = array_diff(scandir($dir), ['.', '..']);

    foreach ($files as $file) {
        $path = $dir . DIRECTORY_SEPARATOR . $file;

        if (is_dir($path)) {
            cleanupTempDirectory($path);
            @rmdir($path);
        } else {
            @unlink($path);
        }
    }
}

beforeEach(function () {
    $this->service = new FileUploadService();

    // Clean up any leftover temp directories from previous test runs
    $tempPath = storage_path('app/private/temp');
    if (is_dir($tempPath)) {
        cleanupTempDirectory($tempPath);
    }
});

afterEach(function () {
    // Clean up temp directories after each test
    $tempPath = storage_path('app/private/temp');
    if (is_dir($tempPath)) {
        cleanupTempDirectory($tempPath);
    }

    // Clean up test upload directories
    $testPaths = [
        storage_path('app/test-uploads'),
        storage_path('app/test-logos'),
    ];

    foreach ($testPaths as $path) {
        if (is_dir($path)) {
            cleanupTempDirectory($path);
        }
    }
});

describe('upload', function () {
    it('creates unique upload id and temp directory', function () {
        $id = $this->service->upload();

        expect($id)->toBeString()
            ->and(Str::isUuid($id))->toBeTrue()
            ->and(is_dir(storage_path("app/private/temp/{$id}")))->toBeTrue();
    });

    it('creates temp directory with correct permissions', function () {
        $id = $this->service->upload();
        $dir = storage_path("app/private/temp/{$id}");

        expect(is_dir($dir))->toBeTrue();

        // Verify directory is writable
        $testFile = "{$dir}/test.txt";
        file_put_contents($testFile, 'test');
        expect(file_exists($testFile))->toBeTrue();
        unlink($testFile);
    });

    it('handles POST request with content', function () {
        $content = 'test file content';

        $request = Request::create('/uploadLogo', 'POST', [], [], [], [], $content);
        $this->app->instance('request', $request);

        $id = $this->service->upload();
        $partFile = storage_path("app/private/temp/{$id}/file.part");

        expect(file_exists($partFile))->toBeTrue()
            ->and(file_get_contents($partFile))->toBe($content);
    });

    it('handles POST request without content', function () {
        $request = Request::create('/uploadLogo', 'POST');
        $this->app->instance('request', $request);

        $id = $this->service->upload();
        $partFile = storage_path("app/private/temp/{$id}/file.part");

        expect($id)->toBeString()
            ->and(file_exists($partFile))->toBeFalse();
    });

    it('creates different ids for multiple uploads', function () {
        $id1 = $this->service->upload();
        $id2 = $this->service->upload();

        expect($id1)->not->toBe($id2)
            ->and(is_dir(storage_path("app/private/temp/{$id1}")))->toBeTrue()
            ->and(is_dir(storage_path("app/private/temp/{$id2}")))->toBeTrue();
    });
});

describe('uploadNext', function () {
    it('aborts with 422 when patch parameter is missing', function () {
        $request = Request::create('/uploadLogo', 'PATCH');

        $this->service->uploadNext($request, 'app/test-uploads');
    })->throws(\Symfony\Component\HttpKernel\Exception\HttpException::class);

    it('creates temp directory if it does not exist', function () {
        $id = Str::uuid()->toString();
        $request = Request::create("/uploadLogo?patch={$id}", 'PATCH', [], [], [], [], 'chunk data');
        $request->headers->set('Upload-Length', '100'); // Set larger than actual content to prevent completion

        $this->service->uploadNext($request, 'app/test-uploads');

        expect(is_dir(storage_path("app/private/temp/{$id}")))->toBeTrue();
    });

    it('appends chunk data to part file', function () {
        $id = Str::uuid()->toString();
        $dir = storage_path("app/private/temp/{$id}");
        mkdir($dir, 0775, true);

        $chunk1 = 'first chunk';
        $chunk2 = 'second chunk';

        $request1 = Request::create("/uploadLogo?patch={$id}", 'PATCH', [], [], [], [], $chunk1);
        $request1->headers->set('Upload-Length', '100'); // Set larger to prevent completion
        $this->service->uploadNext($request1, 'app/test-uploads');

        $request2 = Request::create("/uploadLogo?patch={$id}", 'PATCH', [], [], [], [], $chunk2);
        $request2->headers->set('Upload-Length', '100'); // Set larger to prevent completion
        $this->service->uploadNext($request2, 'app/test-uploads');

        $partFile = "{$dir}/file.part";
        expect(file_get_contents($partFile))->toBe($chunk1 . $chunk2);
    });

    it('returns 204 NO_CONTENT when request body is empty', function () {
        $id = Str::uuid()->toString();
        $request = Request::create("/uploadLogo?patch={$id}", 'PATCH', [], [], [], [], '');

        $response = $this->service->uploadNext($request, 'app/test-uploads');

        expect($response->getStatusCode())->toBe(204)
            ->and($response->getContent())->toBe('NO_CONTENT');
    });

    it('returns OK 200 when upload is not complete', function () {
        $id = Str::uuid()->toString();
        $dir = storage_path("app/private/temp/{$id}");
        mkdir($dir, 0775, true);

        $request = Request::create("/uploadLogo?patch={$id}", 'PATCH', [], [], [], [], 'partial data');
        $request->headers->set('Upload-Length', '1000');

        $response = $this->service->uploadNext($request, 'app/test-uploads');

        expect($response->getStatusCode())->toBe(200)
            ->and($response->getContent())->toBe('OK');
    });

    it('moves file to destination when upload is complete', function () {
        $id = Str::uuid()->toString();
        $dir = storage_path("app/private/temp/{$id}");
        mkdir($dir, 0775, true);

        $content = 'complete file content';

        $request = Request::create("/uploadLogo?patch={$id}", 'PATCH', [], [], [], [], $content);
        $request->headers->set('Upload-Length', (string) strlen($content));
        $request->headers->set('Upload-Name', 'test.txt');

        $result = $this->service->uploadNext($request, 'app/test-uploads', 'myfile');

        $destFile = storage_path('app/test-uploads/myfile.txt');
        expect(file_exists($destFile))->toBeTrue()
            ->and(file_get_contents($destFile))->toBe($content)
            ->and($result)->toBe('myfile.txt');
    });

    it('uses original filename when new_name is not provided', function () {
        $id = Str::uuid()->toString();
        $dir = storage_path("app/private/temp/{$id}");
        mkdir($dir, 0775, true);

        $content = 'test content';

        $request = Request::create("/uploadLogo?patch={$id}", 'PATCH', [], [], [], [], $content);
        $request->headers->set('Upload-Length', (string) strlen($content));
        $request->headers->set('Upload-Name', 'original-name.jpg');

        $result = $this->service->uploadNext($request, 'app/test-uploads');

        $destFile = storage_path('app/test-uploads/original-name.jpg');
        expect(file_exists($destFile))->toBeTrue()
            ->and($result)->toBe('original-name.jpg');
    });

    it('preserves file extension from original upload', function () {
        $id = Str::uuid()->toString();
        $dir = storage_path("app/private/temp/{$id}");
        mkdir($dir, 0775, true);

        $content = 'test content';

        $request = Request::create("/uploadLogo?patch={$id}", 'PATCH', [], [], [], [], $content);
        $request->headers->set('Upload-Length', (string) strlen($content));
        $request->headers->set('Upload-Name', 'document.pdf');

        $result = $this->service->uploadNext($request, 'app/test-uploads', 'myfile');

        expect($result)->toBe('myfile.pdf')
            ->and(file_exists(storage_path('app/test-uploads/myfile.pdf')))->toBeTrue();
    });

    it('handles files without extension', function () {
        $id = Str::uuid()->toString();
        $dir = storage_path("app/private/temp/{$id}");
        mkdir($dir, 0775, true);

        $content = 'test content';

        $request = Request::create("/uploadLogo?patch={$id}", 'PATCH', [], [], [], [], $content);
        $request->headers->set('Upload-Length', (string) strlen($content));
        $request->headers->set('Upload-Name', 'noextension');

        $result = $this->service->uploadNext($request, 'app/test-uploads', 'myfile');

        expect($result)->toBe('myfile')
            ->and(file_exists(storage_path('app/test-uploads/myfile')))->toBeTrue();
    });

    it('creates destination directory if it does not exist', function () {
        $id = Str::uuid()->toString();
        $dir = storage_path("app/private/temp/{$id}");
        mkdir($dir, 0775, true);

        $content = 'test content';
        $destPath = 'app/nested/test/uploads';

        $request = Request::create("/uploadLogo?patch={$id}", 'PATCH', [], [], [], [], $content);
        $request->headers->set('Upload-Length', (string) strlen($content));
        $request->headers->set('Upload-Name', 'test.txt');

        $result = $this->service->uploadNext($request, $destPath, 'file');

        $destFile = storage_path("{$destPath}/file.txt");
        expect(file_exists($destFile))->toBeTrue()
            ->and(is_dir(storage_path($destPath)))->toBeTrue();
    });

    it('cleans up temp directory after successful upload', function () {
        $id = Str::uuid()->toString();
        $dir = storage_path("app/private/temp/{$id}");
        mkdir($dir, 0775, true);

        $content = 'test content';

        $request = Request::create("/uploadLogo?patch={$id}", 'PATCH', [], [], [], [], $content);
        $request->headers->set('Upload-Length', (string) strlen($content));
        $request->headers->set('Upload-Name', 'test.txt');

        $this->service->uploadNext($request, 'app/test-uploads', 'myfile');

        expect(file_exists("{$dir}/file.part"))->toBeFalse();
    });

    it('handles multiple chunks correctly', function () {
        $id = Str::uuid()->toString();
        $dir = storage_path("app/private/temp/{$id}");
        mkdir($dir, 0775, true);

        $chunk1 = str_repeat('A', 100);
        $chunk2 = str_repeat('B', 100);
        $chunk3 = str_repeat('C', 100);
        $totalSize = 300;

        // First chunk
        $request1 = Request::create("/uploadLogo?patch={$id}", 'PATCH', [], [], [], [], $chunk1);
        $request1->headers->set('Upload-Length', (string) $totalSize);
        $request1->headers->set('Upload-Name', 'test.txt');
        $response1 = $this->service->uploadNext($request1, 'app/test-uploads', 'multipart');

        expect($response1->getStatusCode())->toBe(200);

        // Second chunk
        $request2 = Request::create("/uploadLogo?patch={$id}", 'PATCH', [], [], [], [], $chunk2);
        $request2->headers->set('Upload-Length', (string) $totalSize);
        $request2->headers->set('Upload-Name', 'test.txt');
        $response2 = $this->service->uploadNext($request2, 'app/test-uploads', 'multipart');

        expect($response2->getStatusCode())->toBe(200);

        // Final chunk
        $request3 = Request::create("/uploadLogo?patch={$id}", 'PATCH', [], [], [], [], $chunk3);
        $request3->headers->set('Upload-Length', (string) $totalSize);
        $request3->headers->set('Upload-Name', 'test.txt');
        $result = $this->service->uploadNext($request3, 'app/test-uploads', 'multipart');

        $destFile = storage_path('app/test-uploads/multipart.txt');
        expect(file_exists($destFile))->toBeTrue()
            ->and(file_get_contents($destFile))->toBe($chunk1 . $chunk2 . $chunk3)
            ->and($result)->toBe('multipart.txt');
    });

    it('handles upload path with leading and trailing slashes', function () {
        $id = Str::uuid()->toString();
        $dir = storage_path("app/private/temp/{$id}");
        mkdir($dir, 0775, true);

        $content = 'test content';

        $request = Request::create("/uploadLogo?patch={$id}", 'PATCH', [], [], [], [], $content);
        $request->headers->set('Upload-Length', (string) strlen($content));
        $request->headers->set('Upload-Name', 'test.txt');

        $result = $this->service->uploadNext($request, '/app/test-uploads/', 'file');

        $destFile = storage_path('app/test-uploads/file.txt');
        expect(file_exists($destFile))->toBeTrue();
    });
});

describe('uploadNext with image resize', function () {
    it('resizes image with both width and height', function () {
        $id = Str::uuid()->toString();
        $dir = storage_path("app/private/temp/{$id}");
        mkdir($dir, 0775, true);

        // Create a simple test image (1x1 pixel PNG)
        $imageData = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');

        $request = Request::create("/uploadLogo?patch={$id}", 'PATCH', [], [], [], [], $imageData);
        $request->headers->set('Upload-Length', (string) strlen($imageData));
        $request->headers->set('Upload-Name', 'test.png');

        $fit = ['width' => 100, 'height' => 100];
        $result = $this->service->uploadNext($request, 'app/test-uploads', 'resized', $fit);

        $destFile = storage_path('app/test-uploads/resized.png');
        expect(file_exists($destFile))->toBeTrue()
            ->and($result)->toBe('resized.png');

        // Verify it's still a valid image
        $imageSize = getimagesize($destFile);
        expect($imageSize)->not->toBeFalse();
    });

    it('resizes image with width only', function () {
        $id = Str::uuid()->toString();
        $dir = storage_path("app/private/temp/{$id}");
        mkdir($dir, 0775, true);

        $imageData = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');

        $request = Request::create("/uploadLogo?patch={$id}", 'PATCH', [], [], [], [], $imageData);
        $request->headers->set('Upload-Length', (string) strlen($imageData));
        $request->headers->set('Upload-Name', 'test.png');

        $fit = ['width' => 200];
        $result = $this->service->uploadNext($request, 'app/test-uploads', 'width-only', $fit);

        $destFile = storage_path('app/test-uploads/width-only.png');
        expect(file_exists($destFile))->toBeTrue();
    });

    it('resizes image with height only', function () {
        $id = Str::uuid()->toString();
        $dir = storage_path("app/private/temp/{$id}");
        mkdir($dir, 0775, true);

        $imageData = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');

        $request = Request::create("/uploadLogo?patch={$id}", 'PATCH', [], [], [], [], $imageData);
        $request->headers->set('Upload-Length', (string) strlen($imageData));
        $request->headers->set('Upload-Name', 'test.png');

        $fit = ['height' => 150];
        $result = $this->service->uploadNext($request, 'app/test-uploads', 'height-only', $fit);

        $destFile = storage_path('app/test-uploads/height-only.png');
        expect(file_exists($destFile))->toBeTrue();
    });

    it('does not resize when fit parameter is null', function () {
        $id = Str::uuid()->toString();
        $dir = storage_path("app/private/temp/{$id}");
        mkdir($dir, 0775, true);

        $imageData = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');

        $request = Request::create("/uploadLogo?patch={$id}", 'PATCH', [], [], [], [], $imageData);
        $request->headers->set('Upload-Length', (string) strlen($imageData));
        $request->headers->set('Upload-Name', 'test.png');

        $result = $this->service->uploadNext($request, 'app/test-uploads', 'no-resize', null);

        $destFile = storage_path('app/test-uploads/no-resize.png');
        expect(file_exists($destFile))->toBeTrue();
    });
});

describe('edge cases and error handling', function () {
    it('handles very large file uploads in chunks', function () {
        $id = Str::uuid()->toString();
        $dir = storage_path("app/private/temp/{$id}");
        mkdir($dir, 0775, true);

        // Simulate large file with multiple 1KB chunks
        $chunkSize = 1024;
        $numChunks = 10;
        $totalSize = $chunkSize * $numChunks;

        for ($i = 0; $i < $numChunks; $i++) {
            $chunk = str_repeat(chr(65 + ($i % 26)), $chunkSize);

            $request = Request::create("/uploadLogo?patch={$id}", 'PATCH', [], [], [], [], $chunk);
            $request->headers->set('Upload-Length', (string) $totalSize);
            $request->headers->set('Upload-Name', 'large.bin');

            if ($i < $numChunks - 1) {
                $response = $this->service->uploadNext($request, 'app/test-uploads', 'largefile');
                expect($response->getStatusCode())->toBe(200);
            } else {
                $result = $this->service->uploadNext($request, 'app/test-uploads', 'largefile');
                expect($result)->toBe('largefile.bin');
            }
        }

        $destFile = storage_path('app/test-uploads/largefile.bin');
        expect(file_exists($destFile))->toBeTrue()
            ->and(filesize($destFile))->toBe($totalSize);
    });

    it('handles special characters in filenames', function () {
        $id = Str::uuid()->toString();
        $dir = storage_path("app/private/temp/{$id}");
        mkdir($dir, 0775, true);

        $content = 'test content';

        $request = Request::create("/uploadLogo?patch={$id}", 'PATCH', [], [], [], [], $content);
        $request->headers->set('Upload-Length', (string) strlen($content));
        $request->headers->set('Upload-Name', 'file-with-dashes_and_underscores.txt');

        $result = $this->service->uploadNext($request, 'app/test-uploads', 'special-chars_file');

        expect($result)->toBe('special-chars_file.txt')
            ->and(file_exists(storage_path('app/test-uploads/special-chars_file.txt')))->toBeTrue();
    });

    it('handles empty upload name header gracefully', function () {
        $id = Str::uuid()->toString();
        $dir = storage_path("app/private/temp/{$id}");
        mkdir($dir, 0775, true);

        $content = 'test content';

        $request = Request::create("/uploadLogo?patch={$id}", 'PATCH', [], [], [], [], $content);
        $request->headers->set('Upload-Length', (string) strlen($content));
        // No Upload-Name header

        $result = $this->service->uploadNext($request, 'app/test-uploads', 'defaultname');

        expect($result)->toBe('defaultname.bin')
            ->and(file_exists(storage_path('app/test-uploads/defaultname.bin')))->toBeTrue();
    });

    it('handles concurrent uploads with different ids', function () {
        $id1 = Str::uuid()->toString();
        $id2 = Str::uuid()->toString();

        $dir1 = storage_path("app/private/temp/{$id1}");
        $dir2 = storage_path("app/private/temp/{$id2}");

        mkdir($dir1, 0775, true);
        mkdir($dir2, 0775, true);

        $content1 = 'file 1 content';
        $content2 = 'file 2 content';

        $request1 = Request::create("/uploadLogo?patch={$id1}", 'PATCH', [], [], [], [], $content1);
        $request1->headers->set('Upload-Length', (string) strlen($content1));
        $request1->headers->set('Upload-Name', 'file1.txt');

        $request2 = Request::create("/uploadLogo?patch={$id2}", 'PATCH', [], [], [], [], $content2);
        $request2->headers->set('Upload-Length', (string) strlen($content2));
        $request2->headers->set('Upload-Name', 'file2.txt');

        $result1 = $this->service->uploadNext($request1, 'app/test-uploads', 'first');
        $result2 = $this->service->uploadNext($request2, 'app/test-uploads', 'second');

        expect($result1)->toBe('first.txt')
            ->and($result2)->toBe('second.txt')
            ->and(file_get_contents(storage_path('app/test-uploads/first.txt')))->toBe($content1)
            ->and(file_get_contents(storage_path('app/test-uploads/second.txt')))->toBe($content2);
    });

    it('overwrites existing file with same name', function () {
        $id1 = Str::uuid()->toString();
        $id2 = Str::uuid()->toString();

        $dir1 = storage_path("app/private/temp/{$id1}");
        $dir2 = storage_path("app/private/temp/{$id2}");

        mkdir($dir1, 0775, true);
        mkdir($dir2, 0775, true);

        $content1 = 'first version';
        $content2 = 'second version';

        // First upload
        $request1 = Request::create("/uploadLogo?patch={$id1}", 'PATCH', [], [], [], [], $content1);
        $request1->headers->set('Upload-Length', (string) strlen($content1));
        $request1->headers->set('Upload-Name', 'test.txt');
        $this->service->uploadNext($request1, 'app/test-uploads', 'samename');

        // Second upload with same name
        $request2 = Request::create("/uploadLogo?patch={$id2}", 'PATCH', [], [], [], [], $content2);
        $request2->headers->set('Upload-Length', (string) strlen($content2));
        $request2->headers->set('Upload-Name', 'test.txt');
        $this->service->uploadNext($request2, 'app/test-uploads', 'samename');

        $destFile = storage_path('app/test-uploads/samename.txt');
        expect(file_get_contents($destFile))->toBe($content2);
    });

    it('handles zero-byte files', function () {
        $id = Str::uuid()->toString();
        $dir = storage_path("app/private/temp/{$id}");
        mkdir($dir, 0775, true);

        $content = '';

        $request = Request::create("/uploadLogo?patch={$id}", 'PATCH', [], [], [], [], $content);
        $request->headers->set('Upload-Length', '0');
        $request->headers->set('Upload-Name', 'empty.txt');

        $result = $this->service->uploadNext($request, 'app/test-uploads', 'emptyfile');

        // With empty content, it should return NO_CONTENT response
        expect($result->getStatusCode())->toBe(204);
    });
});
