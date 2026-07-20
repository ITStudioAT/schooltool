<?php

use App\Models\School;
use App\Models\Schoolyear;
use App\Models\TeachingCurriculum;
use App\Models\User;
use App\Services\Teaching\CurriculumExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\LaravelPdf\Facades\Pdf;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->school = School::factory()->create([
        'short_name' => 'CURR',
        'long_name' => 'Curriculum Test School',
    ]);

    $this->schoolyear = Schoolyear::factory()->create([
        'school_id' => $this->school->id,
    ]);

    $this->curriculumOwner = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'first_name' => 'Anna',
        'last_name' => 'Lehrerin',
        'email' => 'anna.lehrerin@example.test',
    ]);

    $this->loggedInUser = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'first_name' => 'Bernd',
        'last_name' => 'Admin',
        'email' => 'bernd.admin@example.test',
    ]);

    $this->curriculum = TeachingCurriculum::query()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->curriculumOwner->id,
        'title' => 'Deutsch 5A',
        'description' => 'Jahresplanung',
        'semester_count' => 2,
        'topics' => [
            [
                'id' => 'topic-1',
                'title' => 'Lesen',
                'assignment_type' => 'weeks',
                'week_keys' => ['2026-02-16'],
                'units' => [
                    [
                        'id' => 'unit-1',
                        'title' => 'Texte verstehen',
                        'assignment_type' => 'none',
                        'is_exam' => false,
                    ],
                    [
                        'id' => 'unit-2',
                        'title' => 'Lesekompetenz überprüfen',
                        'assignment_type' => 'none',
                        'is_exam' => true,
                    ],
                ],
            ],
        ],
    ]);
});

afterEach(function () {
    foreach (glob(storage_path('app/private/curriculum_export_*')) ?: [] as $file) {
        @unlink($file);
    }

    foreach (glob(storage_path('app/pdf-fonts/*')) ?: [] as $file) {
        @unlink($file);
    }
});

it('uses the curriculum owner name and school when saving the pdf export', function () {
    $fontDirectory = storage_path('app/pdf-fonts');
    if (! is_dir($fontDirectory)) {
        mkdir($fontDirectory, 0777, true);
    }

    file_put_contents($fontDirectory.'/arial.ttf', 'fake-arial-regular');
    file_put_contents($fontDirectory.'/arialbd.ttf', 'fake-arial-bold');

    Pdf::fake();

    $path = app(CurriculumExportService::class)->toPdf($this->curriculum->fresh(), $this->loggedInUser);

    expect($path)->toBe(storage_path('app/private/curriculum_export_'.$this->curriculum->id.'.pdf'));

    Pdf::assertSaved(function ($pdf, string $savedPath): bool {
        expect($savedPath)->toBe(storage_path('app/private/curriculum_export_'.$this->curriculum->id.'.pdf'))
            ->and($pdf->viewName)->toBe('pdfs.curriculum-export')
            ->and($pdf->viewData['userName'])->toBe($this->curriculumOwner->full_name)
            ->and($pdf->viewData['schoolName'])->toBe('Curriculum Test School')
            ->and($pdf->viewData['unitCount'])->toBe(2)
            ->and($pdf->viewData['assessmentCount'])->toBe(1)
            ->and($pdf->margins)->toBe(['top' => 8.0, 'right' => 9.0, 'bottom' => 9.0, 'left' => 9.0, 'unit' => 'mm'])
            ->and($pdf->html)->toContain('Lehrperson: '.$this->curriculumOwner->full_name)
            ->and($pdf->html)->toContain('Leistungsfeststellung')
            ->and($pdf->html)->toContain('<span class="summary-value">2</span>')
            ->and($pdf->html)->toContain('page-break-inside: avoid;')
            ->and($pdf->html)->toContain("@font-face {\n    font-family: 'CurriculumPdfArial';")
            ->and($pdf->html)->toContain(base64_encode('fake-arial-regular'))
            ->and($pdf->html)->toContain(base64_encode('fake-arial-bold'))
            ->and($pdf->html)->not->toContain('.topic-assignment {')
            ->and($pdf->html)->toContain("font-family: 'CurriculumPdfArial', Arial, Helvetica, sans-serif !important;")
            ->and($pdf->html)->toContain('font-weight: 400;')
            ->and($pdf->html)->toContain('line-height: 1.35;')
            ->and($pdf->html)->toContain('padding: 3px 1px;')
            ->and($pdf->html)->toContain('.unit-exam {')
            ->and($pdf->html)->not->toContain('font-weight: 600;')
            ->and($pdf->html)->not->toContain(".topic-assignment {\n            font-family: 'CurriculumPdfArial', Arial, Helvetica, sans-serif !important;\n            color: #6366f1;\n            font-size: 10pt;\n            font-weight: 600;")
            ->and($pdf->html)->not->toContain(".unit-assignment {\n            font-family: 'CurriculumPdfArial', Arial, Helvetica, sans-serif !important;\n            color: #6366f1;\n            font-size: 10pt;\n            font-weight: 500;")
            ->and($pdf->html)->toContain('font-size: 10pt;')
            ->and($pdf->html)->not->toContain('font-size: 8px;')
            ->and($pdf->html)->not->toContain('font-size: 9px;')
            ->and($pdf->html)->not->toContain('Lehrperson: '.$this->loggedInUser->full_name);

        return true;
    });
});

it('uses the curriculum owner name and school in the word export', function () {
    if (! class_exists(ZipArchive::class)) {
        $this->markTestSkipped('ZipArchive extension is required for this test.');
    }

    $path = app(CurriculumExportService::class)->toWord($this->curriculum->fresh(), $this->loggedInUser);

    expect($path)->toBe(storage_path('app/private/curriculum_export_'.$this->curriculum->id.'.docx'))
        ->and(is_file($path))->toBeTrue();

    $zip = new ZipArchive;
    expect($zip->open($path))->toBeTrue();

    $documentXml = $zip->getFromName('word/document.xml');
    $zip->close();

    expect($documentXml)->toBeString()
        ->and($documentXml)->toContain('Lehrperson: '.$this->curriculumOwner->full_name)
        ->and($documentXml)->toContain('Schule: Curriculum Test School')
        ->and($documentXml)->toContain('Leistungsfeststellung')
        ->and($documentXml)->not->toContain('(Prüfung)')
        ->and($documentXml)->not->toContain('2026-02-16')
        ->and($documentXml)->not->toContain('Lehrperson: '.$this->loggedInUser->full_name);
});
