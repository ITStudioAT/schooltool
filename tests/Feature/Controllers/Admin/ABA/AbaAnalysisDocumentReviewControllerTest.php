<?php

use App\Models\Aba;
use App\Models\AbaAttachment;
use App\Models\Licence;
use App\Models\School;
use App\Models\Schoolyear;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'aba_teacher', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

    $this->school = School::factory()->create();
    $this->schoolyear = Schoolyear::factory()->create([
        'school_id' => $this->school->id,
    ]);

    $abaLicence = Licence::firstOrCreate(
        ['name' => 'ABA'],
        ['long_name' => 'ABA', 'is_selectable' => true]
    );
    $this->school->licences()->syncWithoutDetaching([
        $abaLicence->id => ['valid_until' => now()->addYear()->toDateString()],
    ]);

    $this->tempFiles = [];
});

afterEach(function () {
    foreach ($this->tempFiles as $path) {
        if (is_string($path) && is_file($path)) {
            @unlink($path);
        }
    }
});

function createDocumentReviewUser(School $school, Schoolyear $schoolyear): User
{
    $user = User::factory()->create([
        'school_id' => $school->id,
        'schoolyear_id' => $schoolyear->id,
        'confirmed_at' => now(),
        'email_verified_at' => now(),
        'is_active' => true,
    ]);
    $user->assignRole('aba_teacher');

    return $user;
}

function rememberDocumentReviewTempPath(string $path): string
{
    $current = is_array(test()->tempFiles ?? null) ? test()->tempFiles : [];
    $current[] = $path;
    test()->tempFiles = $current;

    return $path;
}

function createDocumentReviewFakePandocBinary(): string
{
    $isWindows = PHP_OS_FAMILY === 'Windows';
    $tmp = tempnam(sys_get_temp_dir(), 'aba_pandoc_review_');
    if (! is_string($tmp) || $tmp === '') {
        throw new RuntimeException('Konnte temporäre Pandoc-Testdatei nicht erstellen.');
    }

    $path = $isWindows ? $tmp.'.bat' : $tmp.'.sh';
    @rename($tmp, $path);

    if ($isWindows) {
        $script = "@echo off\r\n"
            .'if "%1"=="--version" ('."\r\n"
            ."  echo pandoc 3.1.1\r\n"
            ."  exit /b 0\r\n"
            .")\r\n"
            .'echo {"pandoc-api-version":[1,23,0],"meta":{},"blocks":[{"t":"Header","c":[1,["",[],[]],[{"t":"Str","c":"Inhaltsverzeichnis"}]]},{"t":"Header","c":[1,["",[],[]],[{"t":"Str","c":"1. Einleitung 5"}]]},{"t":"Header","c":[1,["",[],[]],[{"t":"Str","c":"1. Einleitung"}]]},{"t":"Header","c":[1,["",[],[]],[{"t":"Str","c":"Literaturverzeichnis"}]]},{"t":"Header","c":[1,["",[],[]],[{"t":"Str","c":"Eigenständigkeitserklärung"}]]}]}'."\r\n"
            ."exit /b 0\r\n";
    } else {
        $script = "#!/usr/bin/env sh\n"
            ."if [ \"$1\" = \"--version\" ]; then\n"
            ."  echo \"pandoc 3.1.1\"\n"
            ."  exit 0\n"
            ."fi\n"
            ."echo '{\"pandoc-api-version\":[1,23,0],\"meta\":{},\"blocks\":[{\"t\":\"Header\",\"c\":[1,[\"\",[],[]],[{\"t\":\"Str\",\"c\":\"Inhaltsverzeichnis\"}]]},{\"t\":\"Header\",\"c\":[1,[\"\",[],[]],[{\"t\":\"Str\",\"c\":\"1. Einleitung 5\"}]]},{\"t\":\"Header\",\"c\":[1,[\"\",[],[]],[{\"t\":\"Str\",\"c\":\"1. Einleitung\"}]]},{\"t\":\"Header\",\"c\":[1,[\"\",[],[]],[{\"t\":\"Str\",\"c\":\"Literaturverzeichnis\"}]]},{\"t\":\"Header\",\"c\":[1,[\"\",[],[]],[{\"t\":\"Str\",\"c\":\"Eigenständigkeitserklärung\"}]]}]}'\n"
            ."exit 0\n";
    }

    file_put_contents($path, $script);
    if (! $isWindows) {
        @chmod($path, 0755);
    }

    return rememberDocumentReviewTempPath($path);
}

function createMainAttachment(Aba $aba, string $fileName, string $mimeType, string $content): AbaAttachment
{
    $path = 'aba/test-document-review/'.$aba->id.'/'.Str::uuid()->toString().'-'.$fileName;
    Storage::disk('local')->put($path, $content);

    return AbaAttachment::factory()->create([
        'aba_id' => $aba->id,
        'document_kind' => AbaAttachment::DOCUMENT_KIND_MAIN,
        'original_name' => $fileName,
        'stored_name' => $fileName,
        'path' => $path,
        'mime_type' => $mimeType,
        'disk' => 'local',
    ]);
}

test('aba teacher can load integrated document review for main docx', function () {
    $user = createDocumentReviewUser($this->school, $this->schoolyear);
    $aba = Aba::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $user->id,
    ]);
    createMainAttachment(
        $aba,
        'analyse.docx',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'fake-docx-content'
    );

    $fakePandoc = createDocumentReviewFakePandocBinary();
    config()->set('aba_pandoc.enabled', true);
    config()->set('aba_pandoc.binary', $fakePandoc);
    config()->set('aba_pandoc.timeout_seconds', 10);

    $this->actingAs($user, 'sanctum')
        ->getJson("/api/admin/abas/{$aba->id}/analysis/document-review")
        ->assertSuccessful()
        ->assertJsonPath('data.available', true)
        ->assertJsonPath('data.summary.normalized_block_count', fn (mixed $value): bool => is_int($value) && $value >= 5)
        ->assertJsonPath('data.review.counts.main_sections_count', fn (mixed $value): bool => is_int($value) && $value >= 1)
        ->assertJsonPath('data.review.special_sections', fn (mixed $value): bool => is_array($value))
        ->assertJsonPath('data.review.figure_index_entries', fn (mixed $value): bool => is_array($value))
        ->assertJsonPath('data.review.title_page_details', fn (mixed $value): bool => is_array($value))
        ->assertJsonPath('data.review.title_page_processing', fn (mixed $value): bool => is_array($value))
        ->assertJsonPath('data.review.title_page_processing.source_extraction', fn (mixed $value): bool => is_array($value))
        ->assertJsonPath('data.review.title_page_processing.normalized_output', fn (mixed $value): bool => is_array($value))
        ->assertJsonPath('data.review.title_page_processing.normalized_output.additional_properties', fn (mixed $value): bool => is_array($value))
        ->assertJsonPath('data.review.title_page_processing.logo', fn (mixed $value): bool => is_array($value))
        ->assertJsonPath('data.review.title_page_processing.logos', fn (mixed $value): bool => is_array($value))
        ->assertJsonPath('data.review.title_page_processing.ui_model', fn (mixed $value): bool => is_array($value))
        ->assertJsonPath('data.review.title_page_processing.ui_model.additional_properties', fn (mixed $value): bool => is_array($value))
        ->assertJsonPath('data.review.title_page_processing.ui_model.logo_assets', fn (mixed $value): bool => is_array($value))
        ->assertJsonPath('data.review.outline.frontmatter_sections', fn (mixed $value): bool => is_array($value))
        ->assertJsonPath('data.review.outline.main_content_outline', fn (mixed $value): bool => is_array($value))
        ->assertJsonPath('data.review.outline.main_content_orphan_figures', fn (mixed $value): bool => is_array($value))
        ->assertJsonPath('data.review.outline.endmatter_sections', fn (mixed $value): bool => is_array($value))
        ->assertJsonPath('data.comparison.format', 'aba_path_compare_v1')
        ->assertJsonPath('data.comparison.paths.pandoc.status', 'ok')
        ->assertJsonPath('data.comparison.paths.openai_pdf.supported', false);
});

test('document review reports unavailable for non-docx main document', function () {
    $user = createDocumentReviewUser($this->school, $this->schoolyear);
    $aba = Aba::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $user->id,
    ]);
    createMainAttachment($aba, 'analyse.pdf', 'application/pdf', 'fake-pdf-content');

    $this->actingAs($user, 'sanctum')
        ->getJson("/api/admin/abas/{$aba->id}/analysis/document-review")
        ->assertSuccessful()
        ->assertJsonPath('data.available', false)
        ->assertJsonPath('data.message', fn (mixed $value): bool => is_string($value) && str_contains($value, 'DOCX'));
});

test('aba teacher can stream title page logo asset from document review endpoint', function () {
    $user = createDocumentReviewUser($this->school, $this->schoolyear);
    $aba = Aba::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $user->id,
    ]);

    $assetPath = 'aba/titlepage-assets/test-logo.png';
    Storage::disk('local')->put($assetPath, "\x89PNG\r\n\x1a\nfake-logo-binary");

    $this->actingAs($user, 'sanctum')
        ->get('/api/admin/abas/'.$aba->id.'/analysis/document-review/logo-asset?path='.rawurlencode($assetPath).'&disk=local')
        ->assertSuccessful()
        ->assertHeaderContains('Content-Type', 'image/')
        ->assertStreamedContent("\x89PNG\r\n\x1a\nfake-logo-binary");
});

test('analysis results page includes integrated document review section and endpoint', function () {
    $content = file_get_contents(resource_path('js/pages/admin/aba/AbaAnalysisResults.vue'));

    expect($content)
        ->toContain('/api/admin/abas/${this.abaId}/analysis/document-review')
        ->toContain('Dokumentprüfung')
        ->toContain('Prüfbericht kopieren')
        ->toContain('Kapitelvergleich kopieren')
        ->toContain('AHS-ABA · Kapitelvergleich')
        ->toContain('Pfadvergleich')
        ->toContain('Erkannte Kapitel / Abschnitte (lokal)')
        ->toContain('Erkannte Kapitel / Abschnitte (Pandoc)')
        ->toContain('Strukturansicht (Pandoc)')
        ->toContain('Unterstruktur innerhalb des Hauptabschnitts')
        ->toContain('Keine Unterstruktur erkannt.')
        ->toContain('sicher gematcht')
        ->toContain('wahrscheinlich gematcht')
        ->toContain('strukturell ähnlich')
        ->toContain('nur Pandoc')
        ->toContain('nur lokal')
        ->not->toContain('<div class="text-caption font-weight-medium mb-1">Weitere Eigenschaften</div>')
        ->toContain('Kein Logo erkannt.')
        ->toContain('Logo erkannt, aber kein renderbares Asset verfügbar.')
        ->toContain('Logo-Asset vorhanden, aber Rendering fehlgeschlagen.')
        ->toContain('Wahrscheinliche TOC-Artefakte')
        ->toContain('Leere Überschriften');
});
