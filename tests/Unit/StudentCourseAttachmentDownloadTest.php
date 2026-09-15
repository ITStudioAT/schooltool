<?php

use App\Http\Controllers\Student\CourseController;
use App\Models\TeachingCourseDateMaterialAttachment;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    config(['filesystems.default' => 'local']);
    Storage::fake('local');
    Storage::fake('s3');
    $this->controller = new CourseController;
    $this->attachment = new TeachingCourseDateMaterialAttachment([
        'name' => 'Vorstellung_Beurteilung.pdf',
        'file_path' => 'teaching/course_date_materials/test/document.pdf',
        'mime_type' => 'application/pdf',
        'student_visible' => true,
    ]);
});

test('student attachment responses preserve binary bytes across chunks and private headers', function (string $disposition, string $disk) {
    $content = "%PDF-1.7\n".str_repeat("\x00\xff\x80\r\n", 5000)."\n%%EOF";
    Storage::disk($disk)->put($this->attachment->file_path, $content);

    $response = TestResponse::fromBaseResponse(
        (new ReflectionMethod($this->controller, 'serveAdoptedAttachment'))
            ->invoke($this->controller, $this->attachment, $disposition),
    );

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Content-Length', (string) strlen($content))
        ->assertHeader('Content-Disposition', $disposition.'; filename="Vorstellung_Beurteilung.pdf"')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertStreamedContent($content);
    expect($response->headers->get('Cache-Control'))->toContain('private', 'no-store', 'max-age=0');
})->with(['inline', 'attachment'])->with(['local', 's3']);

test('student attachment streams preserve active content sandboxing', function () {
    $content = '<script>alert(document.domain)</script>';
    $this->attachment->name = 'Arbeitsblatt';
    $this->attachment->file_path = 'teaching/course_date_materials/test/document.html';
    $this->attachment->mime_type = 'text/html';
    Storage::disk('local')->put($this->attachment->file_path, $content);

    $response = TestResponse::fromBaseResponse(
        (new ReflectionMethod($this->controller, 'serveAdoptedAttachment'))
            ->invoke($this->controller, $this->attachment, 'inline'),
    );

    $response->assertOk()
        ->assertHeader('Content-Disposition', 'inline; filename="Arbeitsblatt.html"')
        ->assertHeader('Content-Security-Policy', "sandbox; default-src 'none'; base-uri 'none'; form-action 'none'")
        ->assertStreamedContent($content);
});

test('student attachment streams return not found for missing files', function (string $path) {
    $this->attachment->file_path = $path;

    try {
        (new ReflectionMethod($this->controller, 'serveAdoptedAttachment'))
            ->invoke($this->controller, $this->attachment, 'attachment');
        $this->fail('A missing attachment did not return 404.');
    } catch (HttpException $exception) {
        expect($exception->getStatusCode())->toBe(404);
    }
})->with(['', 'teaching/course_date_materials/test/missing.pdf']);
