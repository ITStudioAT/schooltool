<?php

use App\Http\Controllers\Admin\Materials\MaterialController;
use App\Http\Controllers\Admin\MaterialsV2\MaterialV2ItemController;
use App\Models\MaterialCardAttachment;
use App\Models\MaterialV2Attachment;
use App\Models\MaterialV2Item;
use App\Models\User;
use App\Services\Materials\MaterialAttachmentPreviewService;
use App\Services\Materials\MaterialService;
use App\Services\MaterialsV2\MaterialV2DocumentTextExtractor;
use App\Services\MaterialsV2\MaterialV2StorageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    config(['schooltool.preview.instance' => true, 'filesystems.default' => 'local']);
    Storage::fake('local');
    Storage::fake('public');
    Storage::extend('s3', fn () => throw new RuntimeException('Remote storage is blocked in preview.'));
});

test('preview material lookup reads local copies without probing remote fallback disks', function (string $class, ?string $diskName): void {
    $path = 'materials/copied.txt';
    if ($diskName !== null) {
        Storage::disk($diskName)->put($path, "copied bytes\0");
    }

    $method = new ReflectionMethod($class, 'resolveAttachmentStorageDisk');
    $resolved = $method->invoke(app($class), $path, true);

    if ($diskName === null) {
        expect($resolved)->toBeNull();

        return;
    }

    expect($resolved->get($path))->toBe("copied bytes\0");
})->with([MaterialService::class, MaterialController::class])->with(['local', 'public', null]);

test('preview missing editable material keeps its validation error instead of probing S3', function (): void {
    $attachment = new MaterialCardAttachment([
        'attachment_type' => MaterialCardAttachment::TYPE_FILE,
        'file_path' => 'materials/missing.html',
        'name' => 'missing.html',
        'mime_type' => 'text/html',
    ]);

    expect(fn () => app(MaterialService::class)->readEditableTextAttachmentContent($attachment))
        ->toThrow(ValidationException::class, 'Datei wurde nicht gefunden.');
});

test('editable HTML reads bytes from the validated storage disk', function (bool $preview, string $disk): void {
    config(['schooltool.preview.instance' => $preview]);
    if (! $preview) {
        Storage::fake('s3');
    }
    $html = '<h1>Übung</h1><p>Gespeicherter Inhalt.</p>';
    Storage::disk($disk)->put('materials/editable.html', $html);
    $attachment = new MaterialCardAttachment([
        'attachment_type' => MaterialCardAttachment::TYPE_FILE,
        'file_path' => 'materials/editable.html',
        'name' => 'editable.html',
        'mime_type' => 'text/html',
    ]);

    expect(app(MaterialService::class)->readEditableTextAttachmentContent($attachment))->toBe($html);
})->with([true, false])->with(['local', 'public']);

test('editable HTML resolution retains attachment type and format validation', function (string $type, string $name, string $mime, string $message): void {
    Storage::disk('public')->put('materials/invalid', 'must not be returned');
    $attachment = new MaterialCardAttachment([
        'attachment_type' => $type,
        'file_path' => 'materials/invalid',
        'name' => $name,
        'mime_type' => $mime,
    ]);

    expect(fn () => app(MaterialService::class)->readEditableTextAttachmentContent($attachment))
        ->toThrow(ValidationException::class, $message);
})->with([
    ['link', 'document.html', 'text/html', 'Nur Datei-Anhänge können bearbeitet werden.'],
    [MaterialCardAttachment::TYPE_FILE, 'document.pdf', 'application/pdf', 'Dieser Anhangstyp kann nicht als Text bearbeitet werden.'],
]);

test('copied S3 materials support preview download extraction and deletion entirely locally', function (): void {
    $path = 'materials-v2/copied.txt';
    $contents = str_repeat('Local snapshot content. ', 1200);
    Storage::disk('local')->put($path, $contents);
    $attachment = new MaterialV2Attachment([
        'disk' => 's3', 'path' => $path, 'original_name' => 'copied.txt', 'mime_type' => 'text/plain',
    ]);
    $attachment->setRelation('item', new MaterialV2Item(['user_id' => 71, 'school_id' => 23]));
    $user = new User(['school_id' => 23]);
    $user->id = 71;
    $request = Request::create('/');
    $request->setUserResolver(fn () => $user);
    $controller = app(MaterialV2ItemController::class);

    $response = $controller->downloadAttachment($request, $attachment);
    ob_start();
    try {
        $response->sendContent();
        $downloaded = ob_get_contents();
    } finally {
        ob_end_clean();
    }
    expect($downloaded)->toBe($contents);
    expect($controller->previewAttachment($request, $attachment, app(MaterialAttachmentPreviewService::class))->getContent())
        ->toContain('Local snapshot content.');
    expect(app(MaterialV2DocumentTextExtractor::class)->extract($attachment))->toBe(trim($contents));
    app(MaterialV2StorageService::class)->deleteFiles([$attachment]);
    Storage::disk('local')->assertMissing($path);
    expect($attachment->disk)->toBe('s3');

    try {
        $controller->downloadAttachment($request, $attachment);
        $this->fail('Missing local copy must be reported as not found.');
    } catch (HttpException $exception) {
        expect($exception->getStatusCode())->toBe(404);
    }
});

test('material V2 disk resolution preserves main and unknown disk identities', function (bool $preview, string $disk, string $expected): void {
    config(['schooltool.preview.instance' => $preview]);
    $attachment = new MaterialV2Attachment(['disk' => $disk]);

    expect($attachment->storageDiskName())->toBe($expected)
        ->and($attachment->disk)->toBe($disk);
})->with([
    [true, 's3', 'local'],
    [false, 's3', 's3'],
    [true, 'other-cloud', 'other-cloud'],
    [true, 'public', 'public'],
    [true, 'local', 'local'],
]);

test('main material resolution retains its configured S3 fallback', function (): void {
    config(['schooltool.preview.instance' => false]);
    Storage::fake('s3');
    Storage::disk('s3')->put('materials/main.txt', 'main file');

    $method = new ReflectionMethod(MaterialService::class, 'resolveAttachmentStorageDisk');
    expect($method->invoke(app(MaterialService::class), 'materials/main.txt', true)->get('materials/main.txt'))->toBe('main file');
});

test('preview does not redirect unknown remote disks to a same-named local file', function (): void {
    config(['filesystems.disks.other-cloud' => ['driver' => 's3']]);
    Storage::disk('local')->put('materials-v2/copied.txt', 'must survive');
    $attachment = new MaterialV2Attachment(['disk' => 'other-cloud', 'path' => 'materials-v2/copied.txt']);

    expect(fn () => app(MaterialV2StorageService::class)->deleteFiles([$attachment]))
        ->toThrow(RuntimeException::class, 'Remote storage is blocked in preview.');
    Storage::disk('local')->assertExists('materials-v2/copied.txt');
});

test('preview copied material download still requires the same owner and school', function (int $owner, int $school): void {
    $attachment = new MaterialV2Attachment(['disk' => 's3', 'path' => 'materials-v2/copied.txt']);
    $attachment->setRelation('item', new MaterialV2Item(['user_id' => $owner, 'school_id' => $school]));
    $user = new User(['school_id' => 23]);
    $user->id = 71;
    $request = Request::create('/');
    $request->setUserResolver(fn () => $user);

    try {
        app(MaterialV2ItemController::class)->downloadAttachment($request, $attachment);
        $this->fail('An unrelated owner or school must be denied.');
    } catch (HttpException $exception) {
        expect($exception->getStatusCode())->toBe(403);
    }
})->with([[72, 23], [71, 24]]);
