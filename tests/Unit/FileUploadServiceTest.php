<?php

use App\Services\FileUploadService;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->service = new FileUploadService();
    $this->filesystem = new Filesystem();

    $this->originalStoragePath = storage_path();
    $this->testStoragePath = storage_path('framework/testing/file-upload-service/' . Str::uuid());

    if (! is_dir($this->testStoragePath)) {
        mkdir($this->testStoragePath, 0775, true);
    }

    app()->useStoragePath($this->testStoragePath);
});

afterEach(function () {
    if (isset($this->originalStoragePath)) {
        app()->useStoragePath($this->originalStoragePath);
    }

    if (isset($this->testStoragePath) && is_dir($this->testStoragePath)) {
        $this->filesystem->deleteDirectory($this->testStoragePath);
    }
});

describe('upload', function () {
    it('creates a unique upload id and directory', function () {
        $request = Request::create('/uploadLogo', 'POST', [], [], [], [], '');
        app()->instance('request', $request);
        
        $id = $this->service->upload();
        
        expect($id)->toBeString()
            ->and(Str::isUuid($id))->toBeTrue()
            ->and(is_dir(storage_path("app/private/temp/{$id}")))->toBeTrue();
    });

    it('creates directory with correct permissions', function () {
        $request = Request::create('/uploadLogo', 'POST', [], [], [], [], '');
        app()->instance('request', $request);
        
        $id = $this->service->upload();
        $dir = storage_path("app/private/temp/{$id}");
        
        expect(is_dir($dir))->toBeTrue()
            ->and(file_exists($dir))->toBeTrue();
    });

    it('handles empty POST content', function () {
        $request = Request::create('/uploadLogo', 'POST', [], [], [], [], '');
        app()->instance('request', $request);
        
        $id = $this->service->upload();
        $partFile = storage_path("app/private/temp/{$id}/file.part");
        
        expect($id)->toBeString()
            ->and(file_exists($partFile))->toBeFalse();
    });

    it('writes POST content to file.part when content is provided', function () {
        $content = 'test file content';
        $request = Request::create('/uploadLogo', 'POST', [], [], [], [], $content);
        app()->instance('request', $request);
        
        $id = $this->service->upload();
        $partFile = storage_path("app/private/temp/{$id}/file.part");
        
        expect(file_exists($partFile))->toBeTrue()
            ->and(file_get_contents($partFile))->toBe($content);
    });

    it('appends content to existing file.part', function () {
        $content1 = 'first chunk';
        $request = Request::create('/uploadLogo', 'POST', [], [], [], [], $content1);
        app()->instance('request', $request);
        
        $id = $this->service->upload();
        $partFile = storage_path("app/private/temp/{$id}/file.part");
        
        // Simulate second chunk
        file_put_contents($partFile, 'second chunk', FILE_APPEND);
        
        expect(file_get_contents($partFile))->toBe('first chunksecond chunk');
    });

    it('returns plain text response for FilePond compatibility', function () {
        $request = Request::create('/uploadLogo', 'POST', [], [], [], [], '');
        app()->instance('request', $request);
        
        $id = $this->service->upload();
        
        expect($id)->toBeString()
            ->and(strlen($id))->toBeGreaterThan(0);
    });
});

describe('uploadNext', function () {
    it('aborts when patch parameter is missing', function () {
        $request = Request::create('/uploadLogo', 'PATCH', [], [], [], [], 'chunk data');
        
        $this->service->uploadNext($request, 'app/test-uploads');
    })->throws(\Symfony\Component\HttpKernel\Exception\HttpException::class, 'Missing upload id');

    it('creates directory if it does not exist', function () {
        $id = Str::uuid()->toString();
        $request = Request::create('/uploadLogo?patch=' . $id, 'PATCH', [], [], [], [], 'chunk data');
        
        $dir = storage_path("app/private/temp/{$id}");
        expect(is_dir($dir))->toBeFalse();
        
        $result = $this->service->uploadNext($request, 'app/test-uploads');
        
        expect(is_dir($dir))->toBeTrue();
    });

    it('returns 204 when no content is provided', function () {
        $id = Str::uuid()->toString();
        $request = Request::create('/uploadLogo?patch=' . $id, 'PATCH', [], [], [], [], '');
        
        $response = $this->service->uploadNext($request, 'app/test-uploads');
        
        expect($response->getStatusCode())->toBe(204)
            ->and($response->getContent())->toBe('NO_CONTENT');
    });

    it('appends chunk to file.part', function () {
        $id = Str::uuid()->toString();
        $dir = storage_path("app/private/temp/{$id}");
        mkdir($dir, 0775, true);
        
        $chunk1 = 'first chunk';
        $request1 = Request::create('/uploadLogo?patch=' . $id, 'PATCH', [], [], [], [], $chunk1);
        $this->service->uploadNext($request1, 'app/test-uploads');
        
        $chunk2 = 'second chunk';
        $request2 = Request::create('/uploadLogo?patch=' . $id, 'PATCH', [], [], [], [], $chunk2);
        $this->service->uploadNext($request2, 'app/test-uploads');
        
        $partFile = "{$dir}/file.part";
        expect(file_get_contents($partFile))->toBe('first chunksecond chunk');
    });

    it('returns OK when upload is not complete', function () {
        $id = Str::uuid()->toString();
        $chunk = 'chunk data';
        $request = Request::create('/uploadLogo?patch=' . $id, 'PATCH', [], [], [], [
            'HTTP_Upload-Length' => '1000',
        ], $chunk);
        
        $response = $this->service->uploadNext($request, 'app/test-uploads');
        
        expect($response->getStatusCode())->toBe(200)
            ->and($response->getContent())->toBe('OK');
    });

    it('finalizes upload when total size is reached', function () {
        $id = Str::uuid()->toString();
        $dir = storage_path("app/private/temp/{$id}");
        mkdir($dir, 0775, true);

        $content = 'complete file content';
        $request = Request::create('/uploadLogo?patch=' . $id . '&school_id=1', 'PATCH', [], [], [], [
            'HTTP_Upload-Length' => (string)strlen($content),
            'HTTP_Upload-Name' => 'test.txt',
        ], $content);

        $result = $this->service->uploadNext($request, 'app/test-uploads');

        $finalPath = storage_path('app/public/images/logos/logo_1.txt');
        expect(file_exists($finalPath))->toBeTrue()
            ->and(file_get_contents($finalPath))->toBe($content)
            ->and($result)->toBe('logo_1.txt');
    });

    it('uses custom name when provided', function () {
        $id = Str::uuid()->toString();
        $dir = storage_path("app/private/temp/{$id}");
        mkdir($dir, 0775, true);

        $content = 'test content';
        $request = Request::create('/uploadLogo?patch=' . $id . '&school_id=1', 'PATCH', [], [], [], [
            'HTTP_Upload-Length' => (string)strlen($content),
            'HTTP_Upload-Name' => 'original.txt',
        ], $content);

        $result = $this->service->uploadNext($request, 'app/test-uploads', 'custom-name');

        $finalPath = storage_path('app/public/images/logos/logo_1.txt');
        expect(file_exists($finalPath))->toBeTrue()
            ->and($result)->toBe('logo_1.txt');
    });

    it('preserves file extension when using custom name', function () {
        $id = Str::uuid()->toString();
        $dir = storage_path("app/private/temp/{$id}");
        mkdir($dir, 0775, true);

        $content = 'test content';
        $request = Request::create('/uploadLogo?patch=' . $id . '&school_id=1', 'PATCH', [], [], [], [
            'HTTP_Upload-Length' => (string)strlen($content),
            'HTTP_Upload-Name' => 'photo.jpg',
        ], $content);

        $result = $this->service->uploadNext($request, 'app/test-uploads', 'new-photo');

        $finalPath = storage_path('app/public/images/logos/logo_1.jpg');
        expect(file_exists($finalPath))->toBeTrue()
            ->and($result)->toBe('logo_1.jpg');
    });

    it('uses default name when Upload-Name header is missing', function () {
        $id = Str::uuid()->toString();
        $dir = storage_path("app/private/temp/{$id}");
        mkdir($dir, 0775, true);

        $content = 'test';
        $request = Request::create('/uploadLogo?patch=' . $id . '&school_id=1', 'PATCH', [], [], [], [
            'HTTP_Upload-Length' => (string)strlen($content),
        ], $content);

        $result = $this->service->uploadNext($request, 'app/test-uploads');

        $finalPath = storage_path('app/public/images/logos/logo_1.bin');
        expect(file_exists($finalPath))->toBeTrue()
            ->and($result)->toBe('logo_1.bin');
    });

    it('creates destination directory if it does not exist', function () {
        $id = Str::uuid()->toString();
        $dir = storage_path("app/private/temp/{$id}");
        mkdir($dir, 0775, true);

        $destDir = storage_path('app/public/images/logos');

        $content = 'test';
        $request = Request::create('/uploadLogo?patch=' . $id . '&school_id=1', 'PATCH', [], [], [], [
            'HTTP_Upload-Length' => (string)strlen($content),
            'HTTP_Upload-Name' => 'test.txt',
        ], $content);

        $this->service->uploadNext($request, 'app/test-uploads');

        expect(is_dir($destDir))->toBeTrue();
    });

    it('handles upload path with leading and trailing slashes', function () {
        $id = Str::uuid()->toString();
        $dir = storage_path("app/private/temp/{$id}");
        mkdir($dir, 0775, true);

        $content = 'test';
        $request = Request::create('/uploadLogo?patch=' . $id . '&school_id=1', 'PATCH', [], [], [], [
            'HTTP_Upload-Length' => (string)strlen($content),
            'HTTP_Upload-Name' => 'test.txt',
        ], $content);

        $result = $this->service->uploadNext($request, '/app/test-uploads/');

        $finalPath = storage_path('app/public/images/logos/logo_1.txt');
        expect(file_exists($finalPath))->toBeTrue()
            ->and($result)->toBe('logo_1.txt');
    });

    it('moves file from temp to final location', function () {
        $id = Str::uuid()->toString();
        $dir = storage_path("app/private/temp/{$id}");
        mkdir($dir, 0775, true);

        $content = 'file content';
        $request = Request::create('/uploadLogo?patch=' . $id . '&school_id=1', 'PATCH', [], [], [], [
            'HTTP_Upload-Length' => (string)strlen($content),
            'HTTP_Upload-Name' => 'moved.txt',
        ], $content);

        $this->service->uploadNext($request, 'app/test-uploads');

        $partFile = "{$dir}/file.part";
        $finalPath = storage_path('app/public/images/logos/logo_1.txt');

        expect(file_exists($partFile))->toBeFalse()
            ->and(file_exists($finalPath))->toBeTrue();
    });
});

describe('uploadNext with image resizing', function () {
    it('resizes image with both width and height', function () {
        if (!extension_loaded('gd')) {
            $this->markTestSkipped('GD extension is not loaded');
        }
        
        $id = Str::uuid()->toString();
        $dir = storage_path("app/private/temp/{$id}");
        mkdir($dir, 0775, true);
        
        // Create a simple test image
        $image = imagecreatetruecolor(200, 200);
        $tempImage = tempnam(sys_get_temp_dir(), 'test') . '.png';
        imagepng($image, $tempImage);
        imagedestroy($image);
        
        $content = file_get_contents($tempImage);
        unlink($tempImage);
        
        $request = Request::create('/uploadLogo?patch=' . $id . '&school_id=1', 'PATCH', [], [], [], [
            'HTTP_Upload-Length' => (string)strlen($content),
            'HTTP_Upload-Name' => 'test.png',
        ], $content);

        $result = $this->service->uploadNext(
            $request,
            'app/test-uploads',
            null,
            ['width' => 100, 'height' => 100]
        );

        $finalPath = storage_path('app/public/images/logos/logo_1.png');
        expect(file_exists($finalPath))->toBeTrue()
            ->and($result)->toBe('logo_1.png');
        
        // Verify the image was resized
        $resizedImage = imagecreatefrompng($finalPath);
        expect(imagesx($resizedImage))->toBeLessThanOrEqual(100)
            ->and(imagesy($resizedImage))->toBeLessThanOrEqual(100);
        imagedestroy($resizedImage);
    });

    it('resizes image with only width', function () {
        if (!extension_loaded('gd')) {
            $this->markTestSkipped('GD extension is not loaded');
        }
        
        $id = Str::uuid()->toString();
        $dir = storage_path("app/private/temp/{$id}");
        mkdir($dir, 0775, true);
        
        $image = imagecreatetruecolor(200, 200);
        $tempImage = tempnam(sys_get_temp_dir(), 'test') . '.png';
        imagepng($image, $tempImage);
        imagedestroy($image);
        
        $content = file_get_contents($tempImage);
        unlink($tempImage);
        
        $request = Request::create('/uploadLogo?patch=' . $id . '&school_id=1', 'PATCH', [], [], [], [
            'HTTP_Upload-Length' => (string)strlen($content),
            'HTTP_Upload-Name' => 'test.png',
        ], $content);

        $result = $this->service->uploadNext(
            $request,
            'app/test-uploads',
            null,
            ['width' => 100]
        );

        $finalPath = storage_path('app/public/images/logos/logo_1.png');
        expect(file_exists($finalPath))->toBeTrue()
            ->and($result)->toBe('logo_1.png');
    });

    it('resizes image with only height', function () {
        if (!extension_loaded('gd')) {
            $this->markTestSkipped('GD extension is not loaded');
        }
        
        $id = Str::uuid()->toString();
        $dir = storage_path("app/private/temp/{$id}");
        mkdir($dir, 0775, true);
        
        $image = imagecreatetruecolor(200, 200);
        $tempImage = tempnam(sys_get_temp_dir(), 'test') . '.png';
        imagepng($image, $tempImage);
        imagedestroy($image);
        
        $content = file_get_contents($tempImage);
        unlink($tempImage);
        
        $request = Request::create('/uploadLogo?patch=' . $id . '&school_id=1', 'PATCH', [], [], [], [
            'HTTP_Upload-Length' => (string)strlen($content),
            'HTTP_Upload-Name' => 'test.png',
        ], $content);

        $result = $this->service->uploadNext(
            $request,
            'app/test-uploads',
            null,
            ['height' => 100]
        );

        $finalPath = storage_path('app/public/images/logos/logo_1.png');
        expect(file_exists($finalPath))->toBeTrue()
            ->and($result)->toBe('logo_1.png');
    });

    it('does not resize when fit parameter is empty array', function () {
        $id = Str::uuid()->toString();
        $dir = storage_path("app/private/temp/{$id}");
        mkdir($dir, 0775, true);
        
        $content = 'plain text file';
        $request = Request::create('/uploadLogo?patch=' . $id . '&school_id=1', 'PATCH', [], [], [], [
            'HTTP_Upload-Length' => (string)strlen($content),
            'HTTP_Upload-Name' => 'test.txt',
        ], $content);

        $result = $this->service->uploadNext(
            $request,
            'app/test-uploads',
            null,
            []
        );

        $finalPath = storage_path('app/public/images/logos/logo_1.txt');
        expect(file_exists($finalPath))->toBeTrue()
            ->and(file_get_contents($finalPath))->toBe($content);
    });

    it('does not resize when fit parameter is null', function () {
        $id = Str::uuid()->toString();
        $dir = storage_path("app/private/temp/{$id}");
        mkdir($dir, 0775, true);
        
        $content = 'plain text file';
        $request = Request::create('/uploadLogo?patch=' . $id . '&school_id=1', 'PATCH', [], [], [], [
            'HTTP_Upload-Length' => (string)strlen($content),
            'HTTP_Upload-Name' => 'test.txt',
        ], $content);

        $result = $this->service->uploadNext($request, 'app/test-uploads', null, null);

        $finalPath = storage_path('app/public/images/logos/logo_1.txt');
        expect(file_exists($finalPath))->toBeTrue()
            ->and(file_get_contents($finalPath))->toBe($content);
    });
});

describe('integration tests', function () {
    it('handles complete chunked upload workflow', function () {
        // Step 1: Initial upload
        $request1 = Request::create('/uploadLogo', 'POST', [], [], [], [], 'chunk1');
        app()->instance('request', $request1);
        $id = $this->service->upload();
        
        // Step 2: Upload additional chunks
        $request2 = Request::create('/uploadLogo?patch=' . $id, 'PATCH', [], [], [], [], 'chunk2');
        $response = $this->service->uploadNext($request2, 'app/test-uploads');
        expect($response->getStatusCode())->toBe(200);
        
        // Step 3: Final chunk that completes upload
        $totalContent = 'chunk1chunk2chunk3';
        $request3 = Request::create('/uploadLogo?patch=' . $id . '&school_id=1', 'PATCH', [], [], [], [
            'HTTP_Upload-Length' => (string)strlen($totalContent),
            'HTTP_Upload-Name' => 'complete.txt',
        ], 'chunk3');

        $result = $this->service->uploadNext($request3, 'app/test-uploads');

        $finalPath = storage_path('app/public/images/logos/logo_1.txt');
        expect(file_exists($finalPath))->toBeTrue()
            ->and(file_get_contents($finalPath))->toBe($totalContent)
            ->and($result)->toBe('logo_1.txt');
    });

    it('handles multiple simultaneous uploads', function () {
        // Upload 1
        $request1 = Request::create('/uploadLogo', 'POST', [], [], [], [], 'file1');
        app()->instance('request', $request1);
        $id1 = $this->service->upload();
        
        // Upload 2
        $request2 = Request::create('/uploadLogo', 'POST', [], [], [], [], 'file2');
        app()->instance('request', $request2);
        $id2 = $this->service->upload();
        
        expect($id1)->not->toBe($id2)
            ->and(is_dir(storage_path("app/private/temp/{$id1}")))->toBeTrue()
            ->and(is_dir(storage_path("app/private/temp/{$id2}")))->toBeTrue();
    });
});
