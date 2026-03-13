<?php

use App\Jobs\ABA\ProcessAbaAnalysisRunJob;
use App\Models\Aba;
use App\Models\AbaAnalysisResult;
use App\Models\AbaAnalysisRun;
use App\Models\AbaAttachment;
use App\Models\Licence;
use App\Models\School;
use App\Models\Schoolyear;
use App\Models\User;
use App\Services\AbaAnalysisService;
use App\Services\AbaExtractionValidationService;
use App\Services\AbaOpenAiNormalizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->school = School::factory()->create([
        'long_name' => 'ABA Analyse Schule',
        'short_name' => 'AAS',
    ]);
    $this->schoolyear = Schoolyear::factory()->create([
        'school_id' => $this->school->id,
        'name' => '2025/2026',
    ]);

    Role::firstOrCreate(['name' => 'aba_teacher', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

    $abaLicence = Licence::firstOrCreate(
        ['name' => 'ABA'],
        ['long_name' => 'ABA', 'is_selectable' => true]
    );

    $this->school->licences()->syncWithoutDetaching([
        $abaLicence->id => ['valid_until' => now()->addYear()->toDateString()],
    ]);
});

function createAbaTeacher(School $school, Schoolyear $schoolyear): User
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

function createMainDocumentAttachment(Aba $aba, string $fileName, string $content, string $mimeType = 'text/plain'): AbaAttachment
{
    $path = 'aba/test-analysis/'.$aba->id.'/'.Str::uuid()->toString().'-'.$fileName;
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

/**
 * @param  array<int, array{text:string,style?:string,outline?:int|null}>  $paragraphs
 */
function createMainDocxAttachment(Aba $aba, string $fileName, array $paragraphs): AbaAttachment
{
    if (! class_exists(ZipArchive::class)) {
        throw new RuntimeException('ZipArchive extension is required for DOCX test fixtures.');
    }

    $tempFile = tempnam(sys_get_temp_dir(), 'aba-docx-');
    if (! is_string($tempFile) || $tempFile === '') {
        throw new RuntimeException('Could not create temporary DOCX fixture file.');
    }

    $zip = new ZipArchive;
    if ($zip->open($tempFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('Could not open temporary DOCX fixture archive.');
    }

    $zip->addFromString('[Content_Types].xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
    <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
    <Default Extension="xml" ContentType="application/xml"/>
    <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
    <Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>
</Types>
XML
    );
    $zip->addFromString('_rels/.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>
XML
    );
    $zip->addFromString('word/styles.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
    <w:style w:type="paragraph" w:styleId="Normal"><w:name w:val="Normal"/></w:style>
    <w:style w:type="paragraph" w:styleId="Heading1"><w:name w:val="heading 1"/></w:style>
    <w:style w:type="paragraph" w:styleId="Heading2"><w:name w:val="heading 2"/></w:style>
    <w:style w:type="paragraph" w:styleId="TOC1"><w:name w:val="toc 1"/></w:style>
</w:styles>
XML
    );
    $zip->addFromString('word/document.xml', buildDocxDocumentXml($paragraphs));
    $zip->close();

    $binary = file_get_contents($tempFile);
    @unlink($tempFile);
    if (! is_string($binary) || $binary === '') {
        throw new RuntimeException('Could not read DOCX fixture content.');
    }

    $path = 'aba/test-analysis/'.$aba->id.'/'.Str::uuid()->toString().'-'.$fileName;
    Storage::disk('local')->put($path, $binary);

    return AbaAttachment::factory()->create([
        'aba_id' => $aba->id,
        'document_kind' => AbaAttachment::DOCUMENT_KIND_MAIN,
        'original_name' => $fileName,
        'stored_name' => $fileName,
        'path' => $path,
        'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'disk' => 'local',
    ]);
}

/**
 * @param  array<int, array{text?:string,style?:string,outline?:int|null,page_break?:bool}>  $paragraphs
 */
function buildDocxDocumentXml(array $paragraphs): string
{
    $parts = [];
    foreach ($paragraphs as $paragraph) {
        // A page-break-only paragraph: empty text, just a <w:br w:type="page"/>
        if (($paragraph['page_break'] ?? false) === true) {
            $parts[] = '<w:p><w:r><w:br w:type="page"/></w:r></w:p>';

            continue;
        }

        $text = docxXmlEscape((string) ($paragraph['text'] ?? ''));
        $style = trim((string) ($paragraph['style'] ?? ''));
        $outline = $paragraph['outline'] ?? null;

        $pPr = '';
        if ($style !== '' || is_numeric($outline)) {
            $pPr .= '<w:pPr>';
            if ($style !== '') {
                $pPr .= '<w:pStyle w:val="'.docxXmlEscape($style).'"/>';
            }
            if (is_numeric($outline)) {
                $pPr .= '<w:outlineLvl w:val="'.(int) $outline.'"/>';
            }
            $pPr .= '</w:pPr>';
        }

        $parts[] = '<w:p>'.$pPr.'<w:r><w:t xml:space="preserve">'.$text.'</w:t></w:r></w:p>';
    }

    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        .'<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
        .'<w:body>'.implode('', $parts).'</w:body>'
        .'</w:document>';
}

function docxXmlEscape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
}

test('analyse endpoint starts an analysis run and dispatches queue job', function () {
    Queue::fake();
    $user = createAbaTeacher($this->school, $this->schoolyear);
    $this->actingAs($user, 'sanctum');

    $aba = Aba::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $user->id,
    ]);
    $mainAttachment = createMainDocumentAttachment($aba, 'hauptdokument.txt', "Deckblatt\n\nAbstract\nKurzfassung.");

    $response = $this->postJson("/api/admin/abas/{$aba->id}/analysis")
        ->assertStatus(202)
        ->assertJsonPath('data.status', AbaAnalysisRun::STATUS_STARTED);

    $runId = (int) $response->json('data.id');
    $run = AbaAnalysisRun::query()->findOrFail($runId);

    expect((int) $run->aba_id)->toBe((int) $aba->id)
        ->and((int) $run->aba_attachment_id)->toBe((int) $mainAttachment->id);

    Queue::assertPushed(ProcessAbaAnalysisRunJob::class, function (ProcessAbaAnalysisRunJob $job) use ($runId): bool {
        return $job->runId === $runId;
    });
});

test('analyse endpoint uses the main document even if additional documents exist', function () {
    Queue::fake();
    $user = createAbaTeacher($this->school, $this->schoolyear);
    $this->actingAs($user, 'sanctum');

    $aba = Aba::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $user->id,
    ]);
    $mainAttachment = createMainDocumentAttachment($aba, 'hauptdokument.txt', "Deckblatt\n\nKapitel 1");

    AbaAttachment::factory()->create([
        'aba_id' => $aba->id,
        'document_kind' => AbaAttachment::DOCUMENT_KIND_ADDITIONAL,
        'original_name' => 'zusatz.pdf',
        'stored_name' => 'zusatz.pdf',
        'path' => 'aba/test-analysis/'.$aba->id.'/zusatz.pdf',
        'mime_type' => 'application/pdf',
        'disk' => 'local',
    ]);

    $response = $this->postJson("/api/admin/abas/{$aba->id}/analysis")->assertStatus(202);
    $runId = (int) $response->json('data.id');

    $this->assertDatabaseHas('aba_analysis_runs', [
        'id' => $runId,
        'aba_attachment_id' => $mainAttachment->id,
    ]);
});

test('starting a new analysis deletes previous analysis results for the aba', function () {
    Queue::fake();
    $user = createAbaTeacher($this->school, $this->schoolyear);
    $this->actingAs($user, 'sanctum');

    $aba = Aba::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $user->id,
    ]);
    $mainAttachment = createMainDocumentAttachment($aba, 'hauptdokument.txt', "Deckblatt\n\n1 Einleitung");

    $previousRun = AbaAnalysisRun::query()->create([
        'aba_id' => $aba->id,
        'aba_attachment_id' => $mainAttachment->id,
        'created_by_user_id' => $user->id,
        'status' => AbaAnalysisRun::STATUS_COMPLETED,
        'status_message' => 'Analyse abgeschlossen.',
        'started_at' => now()->subMinutes(5),
        'running_at' => now()->subMinutes(4),
        'completed_at' => now()->subMinutes(3),
    ]);

    $previousResult = AbaAnalysisResult::query()->create([
        'aba_id' => $aba->id,
        'aba_analysis_run_id' => $previousRun->id,
        'aba_attachment_id' => $mainAttachment->id,
        'section_type' => 'chapter',
        'section_title' => '1 Einleitung',
        'extracted_text' => 'Alter Kapiteltext',
        'sort_order' => 1,
        'hierarchy_level' => 1,
    ]);

    expect(AbaAnalysisResult::query()->where('aba_id', $aba->id)->count())->toBe(1);

    $this->postJson("/api/admin/abas/{$aba->id}/analysis")
        ->assertStatus(202)
        ->assertJsonPath('data.status', AbaAnalysisRun::STATUS_STARTED);

    $this->assertDatabaseMissing('aba_analysis_results', [
        'id' => $previousResult->id,
    ]);
    expect(AbaAnalysisResult::query()->where('aba_id', $aba->id)->count())->toBe(0);
});

test('missing main document is handled as aborted and visible', function () {
    Queue::fake();
    $user = createAbaTeacher($this->school, $this->schoolyear);
    $this->actingAs($user, 'sanctum');

    $aba = Aba::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $user->id,
    ]);

    $response = $this->postJson("/api/admin/abas/{$aba->id}/analysis")
        ->assertStatus(422)
        ->assertJsonPath('data.status', AbaAnalysisRun::STATUS_ABORTED)
        ->assertJsonPath('data.status_label', 'abgebrochen');

    $this->assertDatabaseHas('aba_analysis_runs', [
        'id' => (int) $response->json('data.id'),
        'aba_id' => $aba->id,
        'status' => AbaAnalysisRun::STATUS_ABORTED,
    ]);

    Queue::assertNothingPushed();
});

test('existing active analysis run is returned and not duplicated', function () {
    Queue::fake();
    $user = createAbaTeacher($this->school, $this->schoolyear);
    $this->actingAs($user, 'sanctum');

    $aba = Aba::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $user->id,
    ]);
    $mainAttachment = createMainDocumentAttachment($aba, 'hauptdokument.txt', "Deckblatt\n\nKapitel 1");

    $existingRun = AbaAnalysisRun::query()->create([
        'aba_id' => $aba->id,
        'aba_attachment_id' => $mainAttachment->id,
        'created_by_user_id' => $user->id,
        'status' => AbaAnalysisRun::STATUS_RUNNING,
        'status_message' => 'Analyse läuft.',
        'started_at' => now()->subMinute(),
        'running_at' => now()->subSeconds(50),
    ]);

    $this->postJson("/api/admin/abas/{$aba->id}/analysis")
        ->assertSuccessful()
        ->assertJsonPath('data.id', $existingRun->id);

    expect(AbaAnalysisRun::query()->where('aba_id', $aba->id)->count())->toBe(1);
    Queue::assertNothingPushed();
});

test('processing run stores extracted sections and marks run completed', function () {
    config()->set('aba_analysis.openai_normalization_enabled', false);

    $user = createAbaTeacher($this->school, $this->schoolyear);
    $aba = Aba::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $user->id,
    ]);

    $content = implode("\n", [
        'Meine ABA',
        'Schülerin Mustermann',
        '',
        'Abstract',
        'Kurze Zusammenfassung.',
        '',
        'Vorwort',
        'Einleitender Kontext.',
        '',
        'Inhaltsverzeichnis',
        '1 Einleitung',
        '',
        '1 Einleitung',
        'Einleitungstext',
        '',
        '1.1 Zielsetzung',
        'Unterkapitel-Text',
        '',
        'Literaturverzeichnis',
        'Quelle A',
        '',
        'Abbildungsverzeichnis',
        'Abbildung 1: Systembild',
        '',
        'Einverständniserklärung',
        'Hiermit bestätige ich ...',
        '',
        'Anhang',
        'Weitere Hinweise',
        '',
        'Abbildung 1: Systembild',
        'Bildbeschreibung',
    ]);

    $mainAttachment = createMainDocumentAttachment($aba, 'hauptdokument.txt', $content);

    $run = AbaAnalysisRun::query()->create([
        'aba_id' => $aba->id,
        'aba_attachment_id' => $mainAttachment->id,
        'created_by_user_id' => $user->id,
        'status' => AbaAnalysisRun::STATUS_STARTED,
        'status_message' => 'Analyselauf wurde gestartet.',
        'source_original_name' => 'hauptdokument.txt',
        'source_path' => $mainAttachment->path,
        'source_mime_type' => 'text/plain',
        'started_at' => now(),
    ]);

    app(AbaAnalysisService::class)->processRun($run->id);

    $run->refresh();
    expect($run->status)->toBe(AbaAnalysisRun::STATUS_COMPLETED)
        ->and($run->completed_at)->not->toBeNull()
        ->and((int) $run->extracted_sections_count)->toBeGreaterThan(0);

    $summary = is_array($run->summary) ? $run->summary : [];
    expect($summary)
        ->toHaveKey('analysis_stats')
        ->toHaveKey('record_counts')
        ->toHaveKey('canonical_json')
        ->toHaveKey('normalized_json')
        ->toHaveKey('validation')
        ->toHaveKey('review');

    $canonical = is_array($summary['canonical_json'] ?? null) ? $summary['canonical_json'] : [];
    $normalized = is_array($summary['normalized_json'] ?? null) ? $summary['normalized_json'] : [];

    expect($canonical)
        ->toHaveKey('document_version_id')
        ->toHaveKey('extractor_candidates')
        ->toHaveKey('selected_candidate')
        ->toHaveKey('blocks')
        ->toHaveKey('frontmatter')
        ->toHaveKey('toc_ranges')
        ->toHaveKey('body_start_line')
        ->toHaveKey('section_candidates');

    expect($canonical['selected_candidate'] ?? null)->not->toBeNull();
    expect(is_array($canonical['blocks'] ?? null))->toBeTrue();
    expect(is_array($canonical['section_candidates'] ?? null))->toBeTrue();

    expect($normalized['document_type'] ?? null)->toBe('aba');
    expect(is_array($normalized['sections'] ?? null))->toBeTrue();
    expect(($normalized['normalization_source'] ?? null))->toBe('local_deterministic');

    $normalizedSections = is_array($normalized['sections'] ?? null) ? $normalized['sections'] : [];
    foreach ($normalizedSections as $section) {
        expect(is_array($section['source_block_ids'] ?? null))->toBeTrue();
    }

    $analysisStats = is_array($summary['analysis_stats'] ?? null) ? $summary['analysis_stats'] : [];
    $recordCounts = is_array($summary['record_counts'] ?? null) ? $summary['record_counts'] : [];
    $persistedCount = AbaAnalysisResult::query()->where('aba_analysis_run_id', $run->id)->count();

    expect($analysisStats)
        ->toHaveKey('detected_record_count')
        ->toHaveKey('normalized_record_count')
        ->toHaveKey('validated_record_count')
        ->toHaveKey('persisted_record_count')
        ->toHaveKey('count_mismatch_detected')
        ->toHaveKey('title_page_detected')
        ->toHaveKey('abstract_detected')
        ->toHaveKey('abstract_de_detected')
        ->toHaveKey('abstract_en_detected')
        ->toHaveKey('abstract_missing_languages')
        ->toHaveKey('foreword_detected')
        ->toHaveKey('table_of_contents_detected')
        ->toHaveKey('bibliography_detected')
        ->toHaveKey('figure_index_detected')
        ->toHaveKey('consent_declaration_detected')
        ->toHaveKey('chapter_count')
        ->toHaveKey('subchapter_count')
        ->toHaveKey('analysis_quality_score')
        ->toHaveKey('structure_quality_score')
        ->toHaveKey('extraction_consistency_score')
        ->toHaveKey('persistence_consistency_score');

    expect((int) ($analysisStats['persisted_record_count'] ?? 0))->toBe($persistedCount)
        ->and((int) ($recordCounts['persisted_record_count'] ?? 0))->toBe($persistedCount)
        ->and((bool) ($analysisStats['count_mismatch_detected'] ?? true))->toBeFalse()
        ->and((bool) ($analysisStats['title_page_detected'] ?? false))->toBeTrue()
        ->and((bool) ($analysisStats['abstract_detected'] ?? false))->toBeTrue()
        ->and((bool) ($analysisStats['foreword_detected'] ?? false))->toBeTrue()
        ->and((bool) ($analysisStats['table_of_contents_detected'] ?? false))->toBeTrue()
        ->and((bool) ($analysisStats['bibliography_detected'] ?? false))->toBeTrue()
        ->and((bool) ($analysisStats['figure_index_detected'] ?? false))->toBeTrue()
        ->and((bool) ($analysisStats['consent_declaration_detected'] ?? false))->toBeTrue()
        ->and((int) ($analysisStats['chapter_count'] ?? 0))->toBeGreaterThanOrEqual(1)
        ->and((int) ($analysisStats['subchapter_count'] ?? 0))->toBeGreaterThanOrEqual(1)
        ->and((float) ($analysisStats['analysis_quality_score'] ?? -1))->toBeGreaterThanOrEqual(0.0)
        ->and((float) ($analysisStats['analysis_quality_score'] ?? 2))->toBeLessThanOrEqual(1.0)
        ->and((float) ($analysisStats['structure_quality_score'] ?? -1))->toBeGreaterThanOrEqual(0.0)
        ->and((float) ($analysisStats['structure_quality_score'] ?? 2))->toBeLessThanOrEqual(1.0)
        ->and((float) ($analysisStats['extraction_consistency_score'] ?? -1))->toBeGreaterThanOrEqual(0.0)
        ->and((float) ($analysisStats['extraction_consistency_score'] ?? 2))->toBeLessThanOrEqual(1.0)
        ->and((float) ($analysisStats['persistence_consistency_score'] ?? -1))->toBeGreaterThanOrEqual(0.0)
        ->and((float) ($analysisStats['persistence_consistency_score'] ?? 2))->toBeLessThanOrEqual(1.0);

    expect(($summary['validation']['is_valid'] ?? null))->toBeTrue();
    expect(($summary['review']['state'] ?? null))->toBeString();

    $types = AbaAnalysisResult::query()
        ->where('aba_analysis_run_id', $run->id)
        ->pluck('section_type')
        ->all();

    expect($types)
        ->toContain('abstract')
        ->toContain('foreword')
        ->toContain('table_of_contents')
        ->toContain('chapter')
        ->toContain('subchapter')
        ->toContain('bibliography')
        ->toContain('figure_index')
        ->toContain('consent_declaration')
        ->toContain('figure')
        ->toContain('other_section');

    $this->assertDatabaseHas('aba_analysis_results', [
        'aba_id' => $aba->id,
        'aba_analysis_run_id' => $run->id,
        'aba_attachment_id' => $mainAttachment->id,
    ]);
});

test('analysis summary reports count mismatches when normalized records differ from persisted records', function () {
    $user = createAbaTeacher($this->school, $this->schoolyear);
    $aba = Aba::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $user->id,
    ]);

    $normalizationMock = \Mockery::mock(AbaOpenAiNormalizationService::class);
    $normalizationMock->shouldReceive('normalize')
        ->once()
        ->andReturnUsing(function (array $canonical): array {
            $candidates = is_array($canonical['section_candidates'] ?? null)
                ? array_values($canonical['section_candidates'])
                : [];
            if (count($candidates) > 1) {
                array_pop($candidates);
            }

            $normalizedSections = [];
            foreach ($candidates as $index => $candidate) {
                if (! is_array($candidate)) {
                    continue;
                }

                $normalizedSections[] = [
                    'section_type' => (string) ($candidate['section_type'] ?? 'other_section'),
                    'title' => $candidate['title'] ?? null,
                    'order' => $index + 1,
                    'parent_order' => null,
                    'text' => (string) ($candidate['text'] ?? ''),
                    'confidence' => 0.8,
                    'warnings' => [],
                    'missing_fields' => [],
                    'source_block_ids' => is_array($candidate['source_block_ids'] ?? null)
                        ? array_values($candidate['source_block_ids'])
                        : [],
                ];
            }

            return [
                'document_type' => 'aba',
                'sections' => $normalizedSections,
                'confidence' => 0.8,
                'warnings' => [],
                'missing_fields' => [],
                'normalization_source' => 'local_deterministic',
            ];
        });
    app()->instance(AbaOpenAiNormalizationService::class, $normalizationMock);

    $mainAttachment = createMainDocumentAttachment($aba, 'hauptdokument.txt', implode("\n", [
        'Titelseite',
        'Abstract',
        'Kurzfassung.',
        'Vorwort',
        'Einleitung zum Thema.',
        'Inhaltsverzeichnis',
        '1 Einleitung .... 3',
        '1 Einleitung',
        'Einleitungstext',
        'Literaturverzeichnis',
        'Autor A (2024). Quelle X.',
    ]));

    $run = AbaAnalysisRun::query()->create([
        'aba_id' => $aba->id,
        'aba_attachment_id' => $mainAttachment->id,
        'created_by_user_id' => $user->id,
        'status' => AbaAnalysisRun::STATUS_STARTED,
        'status_message' => 'Analyselauf wurde gestartet.',
        'source_original_name' => 'hauptdokument.txt',
        'source_path' => $mainAttachment->path,
        'source_mime_type' => 'text/plain',
        'started_at' => now(),
    ]);

    app(AbaAnalysisService::class)->processRun($run->id);
    $run->refresh();

    expect($run->status)->toBe(AbaAnalysisRun::STATUS_COMPLETED);

    $summary = is_array($run->summary) ? $run->summary : [];
    $analysisStats = is_array($summary['analysis_stats'] ?? null) ? $summary['analysis_stats'] : [];
    file_put_contents(storage_path('logs/aba-test-debug.json'), json_encode($analysisStats, JSON_PRETTY_PRINT));

    expect((bool) ($analysisStats['count_mismatch_detected'] ?? false))->toBeTrue()
        ->and((int) ($analysisStats['count_delta_normalized_vs_persisted'] ?? 0))->not->toBe(0)
        ->and((string) ($analysisStats['count_mismatch_reason'] ?? ''))->not->toBe('');
});

test('openai normalization service falls back to deterministic output when openai is disabled', function () {
    config()->set('aba_analysis.openai_normalization_enabled', false);

    $canonical = [
        'document_type' => 'aba',
        'section_candidates' => [
            [
                'candidate_key' => 'section-1',
                'parent_key' => null,
                'section_type' => 'chapter',
                'title' => 'Einleitung',
                'order' => 1,
                'source_block_ids' => ['line-1', 'line-2'],
                'quality_score' => 0.83,
                'text' => 'Einleitungstext',
            ],
        ],
        'warnings' => [],
    ];

    $normalized = app(AbaOpenAiNormalizationService::class)->normalize($canonical);

    expect($normalized['document_type'])->toBe('aba')
        ->and($normalized['normalization_source'])->toBe('local_deterministic')
        ->and($normalized['sections'][0]['section_type'])->toBe('chapter')
        ->and($normalized['sections'][0]['source_block_ids'])->toBe(['line-1', 'line-2']);
});

test('validation rejects normalized sections with unknown source blocks', function () {
    $canonical = [
        'blocks' => [
            ['block_id' => 'line-1'],
        ],
        'metrics' => [
            'chapter_count' => 1,
            'toc_count' => 1,
        ],
        'warnings' => [],
    ];

    $normalized = [
        'document_type' => 'aba',
        'sections' => [
            [
                'section_type' => 'chapter',
                'title' => 'Einleitung',
                'order' => 1,
                'parent_order' => null,
                'text' => 'Body',
                'confidence' => 0.9,
                'warnings' => [],
                'missing_fields' => [],
                'source_block_ids' => ['line-999'],
            ],
        ],
        'confidence' => 0.9,
        'warnings' => [],
        'missing_fields' => [],
    ];

    $validation = app(AbaExtractionValidationService::class)->validate($canonical, $normalized);

    expect($validation['is_valid'])->toBeFalse()
        ->and($validation['errors'])->toContain('unknown_source_block_ids:1');
});

test('processing run detects german and english abstract variants separately', function () {
    config()->set('aba_analysis.openai_normalization_enabled', false);

    $user = createAbaTeacher($this->school, $this->schoolyear);
    $aba = Aba::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $user->id,
    ]);

    $mainAttachment = createMainDocumentAttachment($aba, 'hauptdokument.txt', implode("\n", [
        'Titel der Arbeit',
        '',
        'Zusammenfassung',
        'Die Zusammenfassung beschreibt der und die wichtigsten Ergebnisse.',
        '',
        'Abstract',
        'The abstract summarizes the key findings and context of this work.',
        '',
        'Vorwort',
        'Vorwortstext.',
        '',
        'Inhaltsverzeichnis',
        '1 Einleitung .... 3',
        '',
        '1 Einleitung',
        'Body Text.',
    ]));

    $run = AbaAnalysisRun::query()->create([
        'aba_id' => $aba->id,
        'aba_attachment_id' => $mainAttachment->id,
        'created_by_user_id' => $user->id,
        'status' => AbaAnalysisRun::STATUS_STARTED,
        'status_message' => 'Analyselauf wurde gestartet.',
        'source_original_name' => 'hauptdokument.txt',
        'source_path' => $mainAttachment->path,
        'source_mime_type' => 'text/plain',
        'started_at' => now(),
    ]);

    app(AbaAnalysisService::class)->processRun($run->id);
    $run->refresh();

    $summary = is_array($run->summary) ? $run->summary : [];
    $analysisStats = is_array($summary['analysis_stats'] ?? null) ? $summary['analysis_stats'] : [];

    expect($run->status)->toBe(AbaAnalysisRun::STATUS_COMPLETED)
        ->and((bool) ($analysisStats['abstract_detected'] ?? false))->toBeTrue()
        ->and((bool) ($analysisStats['abstract_de_detected'] ?? false))->toBeTrue()
        ->and((bool) ($analysisStats['abstract_en_detected'] ?? false))->toBeTrue()
        ->and((array) ($analysisStats['abstract_missing_languages'] ?? []))->toBe([])
        ->and((int) ($analysisStats['abstract_count'] ?? 0))->toBeGreaterThanOrEqual(2);
});

test('processing run marks english abstract as missing when only german abstract exists', function () {
    config()->set('aba_analysis.openai_normalization_enabled', false);

    $user = createAbaTeacher($this->school, $this->schoolyear);
    $aba = Aba::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $user->id,
    ]);

    $mainAttachment = createMainDocumentAttachment($aba, 'hauptdokument.txt', implode("\n", [
        'Titel der Arbeit',
        '',
        'Zusammenfassung',
        'Die Zusammenfassung enthält der und die wesentlichen Inhalte.',
        '',
        'Vorwort',
        'Vorwortstext.',
        '',
        'Inhaltsverzeichnis',
        '1 Einleitung .... 3',
        '',
        '1 Einleitung',
        'Body Text.',
    ]));

    $run = AbaAnalysisRun::query()->create([
        'aba_id' => $aba->id,
        'aba_attachment_id' => $mainAttachment->id,
        'created_by_user_id' => $user->id,
        'status' => AbaAnalysisRun::STATUS_STARTED,
        'status_message' => 'Analyselauf wurde gestartet.',
        'source_original_name' => 'hauptdokument.txt',
        'source_path' => $mainAttachment->path,
        'source_mime_type' => 'text/plain',
        'started_at' => now(),
    ]);

    app(AbaAnalysisService::class)->processRun($run->id);
    $run->refresh();

    $summary = is_array($run->summary) ? $run->summary : [];
    $analysisStats = is_array($summary['analysis_stats'] ?? null) ? $summary['analysis_stats'] : [];

    expect($run->status)->toBe(AbaAnalysisRun::STATUS_COMPLETED)
        ->and((bool) ($analysisStats['abstract_de_detected'] ?? false))->toBeTrue()
        ->and((bool) ($analysisStats['abstract_en_detected'] ?? true))->toBeFalse()
        ->and((array) ($analysisStats['abstract_missing_languages'] ?? []))->toContain('en');
});

test('processing run marks german abstract as missing when only english abstract exists', function () {
    config()->set('aba_analysis.openai_normalization_enabled', false);

    $user = createAbaTeacher($this->school, $this->schoolyear);
    $aba = Aba::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $user->id,
    ]);

    $mainAttachment = createMainDocumentAttachment($aba, 'hauptdokument.txt', implode("\n", [
        'Title Page',
        '',
        'Abstract',
        'The abstract explains the scope, goals, and methods of the paper.',
        '',
        'Foreword',
        'Foreword text.',
        '',
        'Table of Contents',
        '1 Introduction .... 3',
        '',
        '1 Introduction',
        'Body Text.',
    ]));

    $run = AbaAnalysisRun::query()->create([
        'aba_id' => $aba->id,
        'aba_attachment_id' => $mainAttachment->id,
        'created_by_user_id' => $user->id,
        'status' => AbaAnalysisRun::STATUS_STARTED,
        'status_message' => 'Analyselauf wurde gestartet.',
        'source_original_name' => 'hauptdokument.txt',
        'source_path' => $mainAttachment->path,
        'source_mime_type' => 'text/plain',
        'started_at' => now(),
    ]);

    app(AbaAnalysisService::class)->processRun($run->id);
    $run->refresh();

    $summary = is_array($run->summary) ? $run->summary : [];
    $analysisStats = is_array($summary['analysis_stats'] ?? null) ? $summary['analysis_stats'] : [];

    expect($run->status)->toBe(AbaAnalysisRun::STATUS_COMPLETED)
        ->and((bool) ($analysisStats['abstract_de_detected'] ?? true))->toBeFalse()
        ->and((bool) ($analysisStats['abstract_en_detected'] ?? false))->toBeTrue()
        ->and((array) ($analysisStats['abstract_missing_languages'] ?? []))->toContain('de');
});

test('toc-only bibliography and figure index entries are not persisted as body sections', function () {
    if (! class_exists(ZipArchive::class)) {
        $this->markTestSkipped('ZipArchive extension missing.');
    }

    $user = createAbaTeacher($this->school, $this->schoolyear);
    $aba = Aba::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $user->id,
    ]);

    $mainAttachment = createMainDocxAttachment($aba, 'hauptdokument.docx', [
        ['text' => 'ABA-Arbeit', 'style' => 'Normal'],
        ['text' => 'Inhaltsverzeichnis', 'style' => 'Heading1', 'outline' => 0],
        ['text' => 'Einleitung ........ 4', 'style' => 'TOC1'],
        ['text' => 'Literaturverzeichnis………………………………………32-34', 'style' => 'TOC1'],
        ['text' => 'Abbildungsverzeichnis…………………………………35', 'style' => 'TOC1'],
        ['text' => 'Fazit ........ 36', 'style' => 'TOC1'],
        ['text' => 'Einleitung', 'style' => 'Heading1', 'outline' => 0],
        ['text' => 'Einleitungstext aus dem Body.', 'style' => 'Normal'],
        ['text' => 'Fazit', 'style' => 'Heading1', 'outline' => 0],
        ['text' => 'Abschließender Text.', 'style' => 'Normal'],
    ]);

    $run = AbaAnalysisRun::query()->create([
        'aba_id' => $aba->id,
        'aba_attachment_id' => $mainAttachment->id,
        'created_by_user_id' => $user->id,
        'status' => AbaAnalysisRun::STATUS_STARTED,
        'status_message' => 'Analyselauf wurde gestartet.',
        'source_original_name' => 'hauptdokument.docx',
        'source_path' => $mainAttachment->path,
        'source_mime_type' => $mainAttachment->mime_type,
        'started_at' => now(),
    ]);

    app(AbaAnalysisService::class)->processRun($run->id);
    $run->refresh();

    expect($run->status)->toBe(AbaAnalysisRun::STATUS_COMPLETED);

    $summary = is_array($run->summary) ? $run->summary : [];
    $analysisStats = is_array($summary['analysis_stats'] ?? null) ? $summary['analysis_stats'] : [];

    $results = AbaAnalysisResult::query()
        ->where('aba_analysis_run_id', $run->id)
        ->orderBy('sort_order')
        ->get();

    expect((int) ($analysisStats['chapter_count'] ?? -1))
        ->toBe((int) $results->where('section_type', 'chapter')->count())
        ->and((int) ($analysisStats['subchapter_count'] ?? -1))
        ->toBe((int) $results->where('section_type', 'subchapter')->count());

    $titles = $results->pluck('section_title')->filter()->values()->all();
    expect($titles)
        ->toContain('Einleitung')
        ->toContain('Fazit')
        ->not->toContain('Literaturverzeichnis………………………………………32-34')
        ->not->toContain('Abbildungsverzeichnis…………………………………35');

    $types = $results->pluck('section_type')->values()->all();
    expect($types)->not->toContain('bibliography')
        ->and($types)->not->toContain('figure_index');
});

test('bibliography entries stay within bibliography section and do not create other_section', function () {
    if (! class_exists(ZipArchive::class)) {
        $this->markTestSkipped('ZipArchive extension missing.');
    }

    $user = createAbaTeacher($this->school, $this->schoolyear);
    $aba = Aba::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $user->id,
    ]);

    $mainAttachment = createMainDocxAttachment($aba, 'hauptdokument.docx', [
        ['text' => 'ABA-Arbeit', 'style' => 'Normal'],
        ['text' => 'Einleitung', 'style' => 'Heading1', 'outline' => 0],
        ['text' => 'Einleitender Inhalt.', 'style' => 'Normal'],
        ['text' => 'Literaturverzeichnis', 'style' => 'Heading1', 'outline' => 0],
        ['text' => 'Cote, B. (2017). Platform Capitalism. Polity Press.', 'style' => 'Normal'],
        ['text' => 'ORF. (2024). Medienbericht. https://orf.at/', 'style' => 'Normal'],
        ['text' => 'Abbildungsverzeichnis', 'style' => 'Heading1', 'outline' => 0],
        ['text' => 'Abb. 1 Mediennutzung', 'style' => 'Normal'],
    ]);

    $run = AbaAnalysisRun::query()->create([
        'aba_id' => $aba->id,
        'aba_attachment_id' => $mainAttachment->id,
        'created_by_user_id' => $user->id,
        'status' => AbaAnalysisRun::STATUS_STARTED,
        'status_message' => 'Analyselauf wurde gestartet.',
        'source_original_name' => 'hauptdokument.docx',
        'source_path' => $mainAttachment->path,
        'source_mime_type' => $mainAttachment->mime_type,
        'started_at' => now(),
    ]);

    app(AbaAnalysisService::class)->processRun($run->id);
    $run->refresh();

    expect($run->status)->toBe(AbaAnalysisRun::STATUS_COMPLETED);

    $results = AbaAnalysisResult::query()
        ->where('aba_analysis_run_id', $run->id)
        ->orderBy('sort_order')
        ->get();

    $bibliography = $results->firstWhere('section_type', 'bibliography');
    expect($bibliography)->not->toBeNull()
        ->and((string) ($bibliography->extracted_text ?? ''))->toContain('Cote, B. (2017)')
        ->and((string) ($bibliography->extracted_text ?? ''))->toContain('https://orf.at/');

    $otherSections = $results->where('section_type', 'other_section');
    $otherTitles = $otherSections->pluck('section_title')->filter()->values()->all();
    expect($otherTitles)->not->toContain('Cote, B. (2017). Platform Capitalism. Polity Press.')
        ->and($otherTitles)->not->toContain('ORF. (2024). Medienbericht. https://orf.at/');
});

test('figure index entries are persisted as figure children of figure index', function () {
    if (! class_exists(ZipArchive::class)) {
        $this->markTestSkipped('ZipArchive extension missing.');
    }

    $user = createAbaTeacher($this->school, $this->schoolyear);
    $aba = Aba::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $user->id,
    ]);

    $mainAttachment = createMainDocxAttachment($aba, 'hauptdokument.docx', [
        ['text' => 'ABA-Arbeit', 'style' => 'Normal'],
        ['text' => 'Einleitung', 'style' => 'Heading1', 'outline' => 0],
        ['text' => 'Body-Inhalt.', 'style' => 'Normal'],
        ['text' => 'Abbildungsverzeichnis', 'style' => 'Heading1', 'outline' => 0],
        ['text' => 'Abb. 1 Mediennutzung', 'style' => 'Normal'],
        ['text' => 'Abb. 2 Plattformvergleich', 'style' => 'Normal'],
        ['text' => 'Eigenständigkeitserklärung', 'style' => 'Heading1', 'outline' => 0],
        ['text' => 'Hiermit bestätige ich ...', 'style' => 'Normal'],
    ]);

    $run = AbaAnalysisRun::query()->create([
        'aba_id' => $aba->id,
        'aba_attachment_id' => $mainAttachment->id,
        'created_by_user_id' => $user->id,
        'status' => AbaAnalysisRun::STATUS_STARTED,
        'status_message' => 'Analyselauf wurde gestartet.',
        'source_original_name' => 'hauptdokument.docx',
        'source_path' => $mainAttachment->path,
        'source_mime_type' => $mainAttachment->mime_type,
        'started_at' => now(),
    ]);

    app(AbaAnalysisService::class)->processRun($run->id);
    $run->refresh();

    expect($run->status)->toBe(AbaAnalysisRun::STATUS_COMPLETED);

    $results = AbaAnalysisResult::query()
        ->where('aba_analysis_run_id', $run->id)
        ->orderBy('sort_order')
        ->get();

    $figureIndex = $results->firstWhere('section_type', 'figure_index');
    expect($figureIndex)->not->toBeNull();

    $figureOne = $results->firstWhere('section_title', 'Abb. 1 Mediennutzung');
    $figureTwo = $results->firstWhere('section_title', 'Abb. 2 Plattformvergleich');
    expect($figureOne)->not->toBeNull()
        ->and($figureTwo)->not->toBeNull()
        ->and((int) ($figureOne->parent_result_id ?? 0))->toBe((int) ($figureIndex->id ?? 0))
        ->and((int) ($figureTwo->parent_result_id ?? 0))->toBe((int) ($figureIndex->id ?? 0));
});

test('docx extraction keeps early chapters, filters toc headings and stores extraction diagnostics', function () {
    if (! class_exists(ZipArchive::class)) {
        $this->markTestSkipped('ZipArchive extension missing.');
    }

    $user = createAbaTeacher($this->school, $this->schoolyear);
    $aba = Aba::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $user->id,
    ]);

    $mainAttachment = createMainDocxAttachment($aba, 'hauptdokument.docx', [
        ['text' => 'Meine ABA', 'style' => 'Normal'],
        ['text' => 'Schülerin Mustermann', 'style' => 'Normal'],
        ['text' => 'Abstract', 'style' => 'Heading1', 'outline' => 0],
        ['text' => 'Kurze Zusammenfassung', 'style' => 'Normal'],
        ['text' => 'Vorwort', 'style' => 'Heading1', 'outline' => 0],
        ['text' => 'Einleitender Kontext', 'style' => 'Normal'],
        ['text' => 'Inhaltsverzeichnis', 'style' => 'Heading1', 'outline' => 0],
        ['text' => '1 Einleitung ........ 3', 'style' => 'TOC1'],
        ['text' => '1.1 Zielsetzung .... 4', 'style' => 'TOC1'],
        ['text' => '2 Methode .......... 5', 'style' => 'TOC1'],
        ['text' => '1 Einleitung', 'style' => 'Heading1', 'outline' => 0],
        ['text' => 'Einleitungstext aus dem Body', 'style' => 'Normal'],
        ['text' => '1.1 Zielsetzung', 'style' => 'Heading2', 'outline' => 1],
        ['text' => 'Unterkapiteltext aus dem Body', 'style' => 'Normal'],
        ['text' => '2 Methode', 'style' => 'Heading1', 'outline' => 0],
        ['text' => 'Methodentext aus dem Body', 'style' => 'Normal'],
        ['text' => 'Literaturverzeichnis', 'style' => 'Heading1', 'outline' => 0],
        ['text' => 'Quelle A', 'style' => 'Normal'],
    ]);

    $run = AbaAnalysisRun::query()->create([
        'aba_id' => $aba->id,
        'aba_attachment_id' => $mainAttachment->id,
        'created_by_user_id' => $user->id,
        'status' => AbaAnalysisRun::STATUS_STARTED,
        'status_message' => 'Analyselauf wurde gestartet.',
        'source_original_name' => 'hauptdokument.docx',
        'source_path' => $mainAttachment->path,
        'source_mime_type' => $mainAttachment->mime_type,
        'started_at' => now(),
    ]);

    app(AbaAnalysisService::class)->processRun($run->id);
    $run->refresh();

    expect($run->status)->toBe(AbaAnalysisRun::STATUS_COMPLETED)
        ->and($run->summary)->toBeArray()
        ->and((int) ($run->summary['extraction']['candidate_count'] ?? 0))->toBeGreaterThanOrEqual(3)
        ->and((string) ($run->summary['extraction']['selected_candidate'] ?? ''))->not->toBe('');

    $candidateIds = collect($run->summary['extraction']['candidates'] ?? [])
        ->pluck('id')
        ->filter()
        ->values()
        ->all();

    expect($candidateIds)
        ->toContain('docx_xml')
        ->toContain('phpword_html')
        ->toContain('mammoth_markdown');

    $chapterSections = AbaAnalysisResult::query()
        ->where('aba_analysis_run_id', $run->id)
        ->where('section_type', 'chapter')
        ->orderBy('sort_order')
        ->get();

    $chapterTitles = $chapterSections
        ->pluck('section_title')
        ->filter()
        ->values()
        ->all();

    expect($chapterTitles)
        ->toContain('1 Einleitung')
        ->toContain('2 Methode');

    expect($chapterSections->count())->toBeGreaterThanOrEqual(2);

    $firstChapter = $chapterSections->firstWhere('section_title', '1 Einleitung');
    $secondChapter = $chapterSections->firstWhere('section_title', '2 Methode');

    expect($firstChapter)->not->toBeNull()
        ->and($secondChapter)->not->toBeNull()
        ->and((int) ($firstChapter->sort_order ?? 0))->toBeLessThan((int) ($secondChapter->sort_order ?? 0))
        ->and((string) ($firstChapter->extracted_text ?? ''))->toContain('Einleitungstext aus dem Body')
        ->and((string) ($firstChapter->extracted_text ?? ''))->not->toContain('........');
});

test('docx extraction isolates frontmatter and keeps soziale medien chapter text from body instead of toc fragments', function () {
    if (! class_exists(ZipArchive::class)) {
        $this->markTestSkipped('ZipArchive extension missing.');
    }

    $user = createAbaTeacher($this->school, $this->schoolyear);
    $aba = Aba::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $user->id,
    ]);

    $mainAttachment = createMainDocxAttachment($aba, 'hauptdokument.docx', [
        ['text' => 'Meine ABA', 'style' => 'Normal'],
        ['text' => 'Schülerin Mustermann', 'style' => 'Normal'],
        ['text' => 'Abstract', 'style' => 'Heading1', 'outline' => 0],
        ['text' => 'Kurzfassung der Arbeit als eigener Frontmatter-Bereich.', 'style' => 'Normal'],
        ['text' => 'Vorwort', 'style' => 'Heading1', 'outline' => 0],
        ['text' => 'Persönlicher Kontext zur Entstehung der Arbeit.', 'style' => 'Normal'],
        ['text' => 'Inhaltsverzeichnis', 'style' => 'Heading1', 'outline' => 0],
        ['text' => '1 Einleitung ........ 4', 'style' => 'TOC1'],
        ['text' => '2 Soziale Medien .... 7', 'style' => 'TOC1'],
        ['text' => '2.1 Plattformen (TikTok, X, Instagram) .... 8', 'style' => 'TOC1'],
        ['text' => '1 Einleitung', 'style' => 'Heading1', 'outline' => 0],
        ['text' => 'Diese Einleitung enthält den ersten echten Body-Absatz der Arbeit.', 'style' => 'Normal'],
        ['text' => '2 Soziale Medien', 'style' => 'Heading1', 'outline' => 0],
        ['text' => 'Im Alltag von Jugendlichen prägen soziale Medien Kommunikation und Informationsverhalten deutlich.', 'style' => 'Normal'],
        ['text' => 'Zusätzlich beeinflussen algorithmische Feeds die Wahrnehmung gesellschaftlicher Themen stark.', 'style' => 'Normal'],
        ['text' => '2.1 Plattformen (TikTok, X, Instagram)', 'style' => 'Heading2', 'outline' => 1],
        ['text' => 'TikTok, X und Instagram werden für unterschiedliche Kommunikationsziele eingesetzt.', 'style' => 'Normal'],
        ['text' => 'Literaturverzeichnis', 'style' => 'Heading1', 'outline' => 0],
        ['text' => 'Quelle A', 'style' => 'Normal'],
    ]);

    $run = AbaAnalysisRun::query()->create([
        'aba_id' => $aba->id,
        'aba_attachment_id' => $mainAttachment->id,
        'created_by_user_id' => $user->id,
        'status' => AbaAnalysisRun::STATUS_STARTED,
        'status_message' => 'Analyselauf wurde gestartet.',
        'source_original_name' => 'hauptdokument.docx',
        'source_path' => $mainAttachment->path,
        'source_mime_type' => $mainAttachment->mime_type,
        'started_at' => now(),
    ]);

    app(AbaAnalysisService::class)->processRun($run->id);
    $run->refresh();

    expect($run->status)->toBe(AbaAnalysisRun::STATUS_COMPLETED)
        ->and((string) ($run->summary['extraction']['selected_candidate'] ?? ''))->toBe('docx_xml');

    $results = AbaAnalysisResult::query()
        ->where('aba_analysis_run_id', $run->id)
        ->orderBy('sort_order')
        ->get();

    $titlePage = $results->firstWhere('section_type', 'title_page');
    $abstracts = $results->where('section_type', 'abstract')->values();
    $foreword = $results->firstWhere('section_type', 'foreword');
    $toc = $results->firstWhere('section_type', 'table_of_contents');
    $abstractWithContent = $abstracts->first(function (AbaAnalysisResult $record): bool {
        return str_contains((string) ($record->extracted_text ?? ''), 'Kurzfassung der Arbeit');
    });

    expect($titlePage)->not->toBeNull()
        ->and($abstracts->count())->toBeGreaterThanOrEqual(1)
        ->and($abstractWithContent)->not->toBeNull()
        ->and($foreword)->not->toBeNull()
        ->and($toc)->not->toBeNull()
        ->and((string) ($titlePage->extracted_text ?? ''))->not->toContain('Abstract')
        ->and((string) ($abstractWithContent?->extracted_text ?? ''))->toContain('Kurzfassung der Arbeit')
        ->and((string) ($abstractWithContent?->extracted_text ?? ''))->not->toContain('Vorwort')
        ->and((string) ($foreword->extracted_text ?? ''))->not->toContain('Inhaltsverzeichnis')
        ->and((string) ($toc->extracted_text ?? ''))->toContain('1 Einleitung ........ 4')
        ->and((string) ($toc->extracted_text ?? ''))->toContain('2 Soziale Medien .... 7');

    $chapterEinleitung = $results->firstWhere('section_title', '1 Einleitung');
    $chapterSozialeMedien = $results->firstWhere('section_title', '2 Soziale Medien');
    $subchapterPlattformen = $results->firstWhere('section_title', '2.1 Plattformen (TikTok, X, Instagram)');

    expect($chapterEinleitung)->not->toBeNull()
        ->and($chapterSozialeMedien)->not->toBeNull()
        ->and($subchapterPlattformen)->not->toBeNull()
        ->and((int) ($chapterEinleitung->start_line ?? 0))->toBeGreaterThan((int) ($toc->end_line ?? 0))
        ->and((int) ($chapterEinleitung->sort_order ?? 0))->toBeLessThan((int) ($chapterSozialeMedien->sort_order ?? 0))
        ->and((string) ($chapterSozialeMedien->extracted_text ?? ''))->toContain('Im Alltag von Jugendlichen prägen soziale Medien')
        ->and((string) ($chapterSozialeMedien->extracted_text ?? ''))->not->toContain('2.1 Plattformen (TikTok, X, Instagram) .... 8')
        ->and(mb_strlen((string) ($chapterSozialeMedien->extracted_text ?? '')))->toBeGreaterThan(80);
});

test('docx toc block is resolved with structured toc lines and body reentry starts after toc end', function () {
    if (! class_exists(ZipArchive::class)) {
        $this->markTestSkipped('ZipArchive extension missing.');
    }

    $user = createAbaTeacher($this->school, $this->schoolyear);
    $aba = Aba::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $user->id,
    ]);

    $mainAttachment = createMainDocxAttachment($aba, 'hauptdokument.docx', [
        ['text' => 'Meine ABA', 'style' => 'Normal'],
        ['text' => 'Abstract', 'style' => 'Heading1', 'outline' => 0],
        ['text' => 'Kurzfassung', 'style' => 'Normal'],
        ['text' => 'Vorwort', 'style' => 'Heading1', 'outline' => 0],
        ['text' => 'Vorworttext', 'style' => 'Normal'],
        ['text' => 'Inhaltsverzeichnis', 'style' => 'Heading1', 'outline' => 0],
        ['text' => '1 Einleitung 4', 'style' => 'Normal'],
        ['text' => '2 Soziale Medien - - 7', 'style' => 'Normal'],
        ['text' => '- 2.1 Plattformen (TikTok, X, Instagram) 8', 'style' => 'Normal'],
        ['text' => '3 Fazit 29-30', 'style' => 'Normal'],
        ['text' => '1 Einleitung', 'style' => 'Heading1', 'outline' => 0],
        ['text' => 'Einleitung als erster echter Body-Absatz.', 'style' => 'Normal'],
        ['text' => '2 Soziale Medien', 'style' => 'Heading1', 'outline' => 0],
        ['text' => 'Hier folgt der echte Fließtext zu sozialen Medien und nicht nur ein TOC-Fragment.', 'style' => 'Normal'],
        ['text' => '2.1 Plattformen (TikTok, X, Instagram)', 'style' => 'Heading2', 'outline' => 1],
        ['text' => 'Die Plattformen werden im Body-Teil differenziert analysiert.', 'style' => 'Normal'],
        ['text' => '3 Fazit', 'style' => 'Heading1', 'outline' => 0],
        ['text' => 'Abschließender Fazittext.', 'style' => 'Normal'],
        ['text' => 'Literaturverzeichnis', 'style' => 'Heading1', 'outline' => 0],
        ['text' => 'Quelle A', 'style' => 'Normal'],
    ]);

    $run = AbaAnalysisRun::query()->create([
        'aba_id' => $aba->id,
        'aba_attachment_id' => $mainAttachment->id,
        'created_by_user_id' => $user->id,
        'status' => AbaAnalysisRun::STATUS_STARTED,
        'status_message' => 'Analyselauf wurde gestartet.',
        'source_original_name' => 'hauptdokument.docx',
        'source_path' => $mainAttachment->path,
        'source_mime_type' => $mainAttachment->mime_type,
        'started_at' => now(),
    ]);

    app(AbaAnalysisService::class)->processRun($run->id);
    $run->refresh();

    expect($run->status)->toBe(AbaAnalysisRun::STATUS_COMPLETED);

    $results = AbaAnalysisResult::query()
        ->where('aba_analysis_run_id', $run->id)
        ->orderBy('sort_order')
        ->get();

    $toc = $results->firstWhere('section_type', 'table_of_contents');
    $chapterEinleitung = $results->firstWhere('section_title', '1 Einleitung');
    $chapterSozialeMedien = $results->firstWhere('section_title', '2 Soziale Medien');
    $chapterFazit = $results->firstWhere('section_title', '3 Fazit');

    expect($toc)->not->toBeNull()
        ->and((string) ($toc->extracted_text ?? ''))->toContain('1 Einleitung 4')
        ->and((string) ($toc->extracted_text ?? ''))->toContain('2 Soziale Medien - - 7')
        ->and((string) ($toc->extracted_text ?? ''))->toContain('3 Fazit 29-30')
        ->and((int) ($toc->end_line ?? 0))->toBeGreaterThan((int) ($toc->start_line ?? 0));

    expect($chapterEinleitung)->not->toBeNull()
        ->and($chapterSozialeMedien)->not->toBeNull()
        ->and($chapterFazit)->not->toBeNull()
        ->and((int) ($chapterEinleitung->start_line ?? 0))->toBeGreaterThan((int) ($toc->end_line ?? 0))
        ->and((string) ($chapterSozialeMedien->extracted_text ?? ''))->toContain('echte Fließtext zu sozialen Medien')
        ->and((string) ($chapterSozialeMedien->extracted_text ?? ''))->not->toContain('2 Soziale Medien - - 7');
});

test('docx reference-like structure with dual toc and numbered chapters is recognized generically', function () {
    if (! class_exists(ZipArchive::class)) {
        $this->markTestSkipped('ZipArchive extension missing.');
    }

    $user = createAbaTeacher($this->school, $this->schoolyear);
    $aba = Aba::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $user->id,
    ]);

    $mainAttachment = createMainDocxAttachment($aba, 'hauptdokument.docx', [
        ['text' => 'ABA-Arbeit', 'style' => 'Normal'],
        ['text' => 'Schülerin Beispiel', 'style' => 'Normal'],
        ['text' => 'Abstract', 'style' => 'Heading1', 'outline' => 0],
        ['text' => 'Kurze Zusammenfassung dieser Arbeit.', 'style' => 'Normal'],
        ['text' => 'Vorwort', 'style' => 'Heading1', 'outline' => 0],
        ['text' => 'Einordnung und Motivation der Themenwahl.', 'style' => 'Normal'],
        ['text' => 'Inhaltsverzeichnis', 'style' => 'Heading1', 'outline' => 0],
        ['text' => 'Einleitung .... 5', 'style' => 'TOC1'],
        ['text' => '1. Arten von Medien .... 7', 'style' => 'TOC1'],
        ['text' => '2. Stellenwert der Medien in der modernen Gesellschaft .... 10', 'style' => 'TOC1'],
        ['text' => '3. Politische Berichterstattung und ihre Auswirkung auf das Wählerverhalten .... 16', 'style' => 'TOC1'],
        ['text' => '4. Fake News und Desinformation .... 22', 'style' => 'TOC1'],
        ['text' => 'Inhaltsverzeichnis', 'style' => 'Heading1', 'outline' => 0],
        ['text' => '5. Zukünftige Entwicklungen und Herausforderungen .... 27', 'style' => 'TOC1'],
        ['text' => 'Fazit .... 31', 'style' => 'TOC1'],
        ['text' => 'Literaturverzeichnis .... 33', 'style' => 'TOC1'],
        ['text' => 'Abbildungsverzeichnis .... 35', 'style' => 'TOC1'],
        ['text' => 'Eigenständigkeitserklärung .... 36', 'style' => 'TOC1'],
        ['text' => 'Einleitung', 'style' => 'Heading1', 'outline' => 0],
        ['text' => 'Einleitender Fließtext mit Kontext und Forschungsfrage.', 'style' => 'Normal'],
        ['text' => '1. Arten von Medien', 'style' => 'Heading1', 'outline' => 0],
        ['text' => 'Klassische und digitale Medien werden systematisch beschrieben.', 'style' => 'Normal'],
        ['text' => '2. Stellenwert der Medien in der modernen Gesellschaft', 'style' => 'Heading1', 'outline' => 0],
        ['text' => 'Der gesellschaftliche Stellenwert wird anhand empirischer Hinweise dargestellt.', 'style' => 'Normal'],
        ['text' => '3. Politische Berichterstattung und ihre Auswirkung auf das Wählerverhalten', 'style' => 'Heading1', 'outline' => 0],
        ['text' => 'Berichterstattung beeinflusst Wahrnehmung, Agenda und Wahlentscheidungen.', 'style' => 'Normal'],
        ['text' => '4. Fake News und Desinformation', 'style' => 'Heading1', 'outline' => 0],
        ['text' => 'Desinformation verbreitet sich über soziale Netzwerke und Messaging-Dienste.', 'style' => 'Normal'],
        ['text' => '5. Zukünftige Entwicklungen und Herausforderungen', 'style' => 'Heading1', 'outline' => 0],
        ['text' => 'Regulierung, Medienkompetenz und technische Maßnahmen werden diskutiert.', 'style' => 'Normal'],
        ['text' => 'Fazit', 'style' => 'Heading1', 'outline' => 0],
        ['text' => 'Zusammenfassende Bewertung und Ausblick auf weitere Forschung.', 'style' => 'Normal'],
        ['text' => 'Literaturverzeichnis', 'style' => 'Heading1', 'outline' => 0],
        ['text' => 'Autor A (2024). Quelle X.', 'style' => 'Normal'],
        ['text' => 'Abbildungsverzeichnis', 'style' => 'Heading1', 'outline' => 0],
        ['text' => 'Abb. 1 Mediennutzung', 'style' => 'Normal'],
        ['text' => 'Eigenständigkeitserklärung', 'style' => 'Heading1', 'outline' => 0],
        ['text' => 'Hiermit erkläre ich die eigenständige Erstellung der Arbeit.', 'style' => 'Normal'],
    ]);

    $run = AbaAnalysisRun::query()->create([
        'aba_id' => $aba->id,
        'aba_attachment_id' => $mainAttachment->id,
        'created_by_user_id' => $user->id,
        'status' => AbaAnalysisRun::STATUS_STARTED,
        'status_message' => 'Analyselauf wurde gestartet.',
        'source_original_name' => 'hauptdokument.docx',
        'source_path' => $mainAttachment->path,
        'source_mime_type' => $mainAttachment->mime_type,
        'started_at' => now(),
    ]);

    app(AbaAnalysisService::class)->processRun($run->id);
    $run->refresh();

    expect($run->status)->toBe(AbaAnalysisRun::STATUS_COMPLETED);

    $results = AbaAnalysisResult::query()
        ->where('aba_analysis_run_id', $run->id)
        ->orderBy('sort_order')
        ->get();

    $tocSections = $results->where('section_type', 'table_of_contents')->values();
    expect($tocSections->count())->toBe(2)
        ->and((string) ($tocSections->get(0)?->extracted_text ?? ''))->toContain('1. Arten von Medien .... 7')
        ->and((string) ($tocSections->get(1)?->extracted_text ?? ''))->toContain('5. Zukünftige Entwicklungen und Herausforderungen .... 27');

    $maxTocEndLine = (int) $tocSections->max('end_line');

    $expectedSectionTitles = [
        'Einleitung',
        '1. Arten von Medien',
        '2. Stellenwert der Medien in der modernen Gesellschaft',
        '3. Politische Berichterstattung und ihre Auswirkung auf das Wählerverhalten',
        '4. Fake News und Desinformation',
        '5. Zukünftige Entwicklungen und Herausforderungen',
        'Fazit',
        'Literaturverzeichnis',
        'Abbildungsverzeichnis',
        'Eigenständigkeitserklärung',
    ];

    $sectionTitles = $results
        ->pluck('section_title')
        ->filter()
        ->values()
        ->all();

    foreach ($expectedSectionTitles as $expectedTitle) {
        expect($sectionTitles)->toContain($expectedTitle);
    }

    $einleitung = $results->firstWhere('section_title', 'Einleitung');
    $kapitelEins = $results->firstWhere('section_title', '1. Arten von Medien');
    $kapitelZwei = $results->firstWhere('section_title', '2. Stellenwert der Medien in der modernen Gesellschaft');

    expect($einleitung)->not->toBeNull()
        ->and($kapitelEins)->not->toBeNull()
        ->and($kapitelZwei)->not->toBeNull()
        ->and((int) ($einleitung->start_line ?? 0))->toBeGreaterThan($maxTocEndLine)
        ->and((string) ($kapitelEins->extracted_text ?? ''))->toContain('Klassische und digitale Medien')
        ->and((string) ($kapitelZwei->extracted_text ?? ''))->toContain('gesellschaftliche Stellenwert');

    $forbiddenTocBodyTitles = [
        'Literaturverzeichnis .... 33',
        'Abbildungsverzeichnis .... 35',
        'Eigenständigkeitserklärung .... 36',
    ];
    foreach ($forbiddenTocBodyTitles as $forbiddenTitle) {
        expect($sectionTitles)->not->toContain($forbiddenTitle);
    }
});

test('parent chapters without direct body text stay accepted when child headings provide hierarchy support', function () {
    if (! class_exists(ZipArchive::class)) {
        $this->markTestSkipped('ZipArchive extension missing.');
    }

    $user = createAbaTeacher($this->school, $this->schoolyear);
    $aba = Aba::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $user->id,
    ]);

    $mainAttachment = createMainDocxAttachment($aba, 'hauptdokument.docx', [
        ['text' => 'ABA-Arbeit', 'style' => 'Normal'],
        ['text' => 'Inhaltsverzeichnis', 'style' => 'Heading1', 'outline' => 0],
        ['text' => '1. Hauptkapitel .... 4', 'style' => 'TOC1'],
        ['text' => '1.1 Unterkapitel A .... 5', 'style' => 'TOC1'],
        ['text' => '2. Zweites Hauptkapitel .... 8', 'style' => 'TOC1'],
        ['text' => '2.1 Unterkapitel B .... 9', 'style' => 'TOC1'],
        ['text' => '1. Hauptkapitel', 'style' => 'Heading1', 'outline' => 0],
        ['text' => '1.1 Unterkapitel A', 'style' => 'Heading2', 'outline' => 1],
        ['text' => 'Konkreter Inhalt für Unterkapitel A.', 'style' => 'Normal'],
        ['text' => '2. Zweites Hauptkapitel', 'style' => 'Heading1', 'outline' => 0],
        ['text' => '2.1 Unterkapitel B', 'style' => 'Heading2', 'outline' => 1],
        ['text' => 'Konkreter Inhalt für Unterkapitel B.', 'style' => 'Normal'],
        ['text' => 'Fazit', 'style' => 'Heading1', 'outline' => 0],
        ['text' => 'Abschließende Zusammenfassung.', 'style' => 'Normal'],
    ]);

    $run = AbaAnalysisRun::query()->create([
        'aba_id' => $aba->id,
        'aba_attachment_id' => $mainAttachment->id,
        'created_by_user_id' => $user->id,
        'status' => AbaAnalysisRun::STATUS_STARTED,
        'status_message' => 'Analyselauf wurde gestartet.',
        'source_original_name' => 'hauptdokument.docx',
        'source_path' => $mainAttachment->path,
        'source_mime_type' => $mainAttachment->mime_type,
        'started_at' => now(),
    ]);

    app(AbaAnalysisService::class)->processRun($run->id);
    $run->refresh();

    expect($run->status)->toBe(AbaAnalysisRun::STATUS_COMPLETED);

    $results = AbaAnalysisResult::query()
        ->where('aba_analysis_run_id', $run->id)
        ->orderBy('sort_order')
        ->get();

    $chapterOne = $results->firstWhere('section_title', '1. Hauptkapitel');
    $chapterTwo = $results->firstWhere('section_title', '2. Zweites Hauptkapitel');
    $subOne = $results->firstWhere('section_title', '1.1 Unterkapitel A');
    $subTwo = $results->firstWhere('section_title', '2.1 Unterkapitel B');

    expect($chapterOne)->not->toBeNull()
        ->and($chapterTwo)->not->toBeNull()
        ->and($subOne)->not->toBeNull()
        ->and($subTwo)->not->toBeNull()
        ->and((int) ($subOne->parent_result_id ?? 0))->toBe((int) ($chapterOne->id ?? 0))
        ->and((int) ($subTwo->parent_result_id ?? 0))->toBe((int) ($chapterTwo->id ?? 0));
});

test('toc duplicates for bibliography figure index and declaration are not accepted as body sections', function () {
    if (! class_exists(ZipArchive::class)) {
        $this->markTestSkipped('ZipArchive extension missing.');
    }

    $user = createAbaTeacher($this->school, $this->schoolyear);
    $aba = Aba::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $user->id,
    ]);

    $mainAttachment = createMainDocxAttachment($aba, 'hauptdokument.docx', [
        ['text' => 'ABA-Arbeit', 'style' => 'Normal'],
        ['text' => 'Inhaltsverzeichnis', 'style' => 'Heading1', 'outline' => 0],
        ['text' => 'Literaturverzeichnis .... 30', 'style' => 'TOC1'],
        ['text' => 'Abbildungsverzeichnis .... 31', 'style' => 'TOC1'],
        ['text' => 'Eigenständigkeitserklärung .... 32', 'style' => 'TOC1'],
        ['text' => 'Einleitung', 'style' => 'Heading1', 'outline' => 0],
        ['text' => 'Einleitungstext.', 'style' => 'Normal'],
        ['text' => 'Literaturverzeichnis', 'style' => 'Heading1', 'outline' => 0],
        ['text' => 'Autor A (2024). Quelle X.', 'style' => 'Normal'],
        ['text' => 'Abbildungsverzeichnis', 'style' => 'Heading1', 'outline' => 0],
        ['text' => 'Abb. 1 Beispiel', 'style' => 'Normal'],
        ['text' => 'Eigenständigkeitserklärung', 'style' => 'Heading1', 'outline' => 0],
        ['text' => 'Hiermit bestätige ich die Eigenständigkeit.', 'style' => 'Normal'],
    ]);

    $run = AbaAnalysisRun::query()->create([
        'aba_id' => $aba->id,
        'aba_attachment_id' => $mainAttachment->id,
        'created_by_user_id' => $user->id,
        'status' => AbaAnalysisRun::STATUS_STARTED,
        'status_message' => 'Analyselauf wurde gestartet.',
        'source_original_name' => 'hauptdokument.docx',
        'source_path' => $mainAttachment->path,
        'source_mime_type' => $mainAttachment->mime_type,
        'started_at' => now(),
    ]);

    app(AbaAnalysisService::class)->processRun($run->id);
    $run->refresh();

    expect($run->status)->toBe(AbaAnalysisRun::STATUS_COMPLETED);

    $results = AbaAnalysisResult::query()
        ->where('aba_analysis_run_id', $run->id)
        ->orderBy('sort_order')
        ->get();

    $titles = $results->pluck('section_title')->filter()->values()->all();

    expect($titles)
        ->toContain('Literaturverzeichnis')
        ->toContain('Abbildungsverzeichnis')
        ->toContain('Eigenständigkeitserklärung')
        ->not->toContain('Literaturverzeichnis .... 30')
        ->not->toContain('Abbildungsverzeichnis .... 31')
        ->not->toContain('Eigenständigkeitserklärung .... 32');
});

test('deep numbered hierarchy keeps parent child chain complete', function () {
    if (! class_exists(ZipArchive::class)) {
        $this->markTestSkipped('ZipArchive extension missing.');
    }

    $user = createAbaTeacher($this->school, $this->schoolyear);
    $aba = Aba::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $user->id,
    ]);

    $mainAttachment = createMainDocxAttachment($aba, 'hauptdokument.docx', [
        ['text' => 'ABA-Arbeit', 'style' => 'Normal'],
        ['text' => 'Inhaltsverzeichnis', 'style' => 'Heading1', 'outline' => 0],
        ['text' => '1. Analyse .... 3', 'style' => 'TOC1'],
        ['text' => '1.1 Datenbasis .... 4', 'style' => 'TOC1'],
        ['text' => '1.1.1 Erhebung .... 5', 'style' => 'TOC1'],
        ['text' => '1. Analyse', 'style' => 'Heading1', 'outline' => 0],
        ['text' => '1.1 Datenbasis', 'style' => 'Heading2', 'outline' => 1],
        ['text' => '1.1.1 Erhebung', 'style' => 'Heading3', 'outline' => 2],
        ['text' => 'Detaillierte Beschreibung der Erhebung.', 'style' => 'Normal'],
        ['text' => 'Fazit', 'style' => 'Heading1', 'outline' => 0],
        ['text' => 'Abschluss.', 'style' => 'Normal'],
    ]);

    $run = AbaAnalysisRun::query()->create([
        'aba_id' => $aba->id,
        'aba_attachment_id' => $mainAttachment->id,
        'created_by_user_id' => $user->id,
        'status' => AbaAnalysisRun::STATUS_STARTED,
        'status_message' => 'Analyselauf wurde gestartet.',
        'source_original_name' => 'hauptdokument.docx',
        'source_path' => $mainAttachment->path,
        'source_mime_type' => $mainAttachment->mime_type,
        'started_at' => now(),
    ]);

    app(AbaAnalysisService::class)->processRun($run->id);
    $run->refresh();

    expect($run->status)->toBe(AbaAnalysisRun::STATUS_COMPLETED);

    $results = AbaAnalysisResult::query()
        ->where('aba_analysis_run_id', $run->id)
        ->orderBy('sort_order')
        ->get();

    $main = $results->firstWhere('section_title', '1. Analyse');
    $sub = $results->firstWhere('section_title', '1.1 Datenbasis');
    $subsub = $results->firstWhere('section_title', '1.1.1 Erhebung');

    expect($main)->not->toBeNull()
        ->and($sub)->not->toBeNull()
        ->and($subsub)->not->toBeNull()
        ->and((int) ($sub->parent_result_id ?? 0))->toBe((int) ($main->id ?? 0))
        ->and((int) ($subsub->parent_result_id ?? 0))->toBe((int) ($sub->id ?? 0));
});

test('processing run marks failure when document file is missing', function () {
    $user = createAbaTeacher($this->school, $this->schoolyear);
    $aba = Aba::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $user->id,
    ]);

    $missingAttachment = AbaAttachment::factory()->create([
        'aba_id' => $aba->id,
        'document_kind' => AbaAttachment::DOCUMENT_KIND_MAIN,
        'original_name' => 'fehlt.pdf',
        'stored_name' => 'fehlt.pdf',
        'path' => 'aba/missing/fehlt.pdf',
        'mime_type' => 'application/pdf',
        'disk' => 'local',
    ]);

    $run = AbaAnalysisRun::query()->create([
        'aba_id' => $aba->id,
        'aba_attachment_id' => $missingAttachment->id,
        'created_by_user_id' => $user->id,
        'status' => AbaAnalysisRun::STATUS_STARTED,
        'status_message' => 'Analyselauf wurde gestartet.',
        'started_at' => now(),
    ]);

    app(AbaAnalysisService::class)->processRun($run->id);

    $run->refresh();
    expect($run->status)->toBe(AbaAnalysisRun::STATUS_FAILED)
        ->and($run->failed_at)->not->toBeNull()
        ->and((string) $run->error_message)->not->toBe('');
});

test('analysis results endpoint returns latest run, main document and sections', function () {
    $user = createAbaTeacher($this->school, $this->schoolyear);
    $this->actingAs($user, 'sanctum');

    $aba = Aba::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $user->id,
    ]);
    $mainAttachment = createMainDocumentAttachment($aba, 'hauptdokument.txt', "Deckblatt\n\n1 Einleitung\nText.");

    $run = AbaAnalysisRun::query()->create([
        'aba_id' => $aba->id,
        'aba_attachment_id' => $mainAttachment->id,
        'created_by_user_id' => $user->id,
        'status' => AbaAnalysisRun::STATUS_COMPLETED,
        'status_message' => 'Analyse abgeschlossen.',
        'source_original_name' => 'hauptdokument.txt',
        'source_path' => $mainAttachment->path,
        'source_mime_type' => 'text/plain',
        'started_at' => now()->subMinutes(3),
        'running_at' => now()->subMinutes(2),
        'completed_at' => now()->subMinute(),
    ]);

    AbaAnalysisResult::query()->create([
        'aba_id' => $aba->id,
        'aba_analysis_run_id' => $run->id,
        'aba_attachment_id' => $mainAttachment->id,
        'section_type' => 'chapter',
        'section_title' => '1 Einleitung',
        'extracted_text' => 'Das ist der Kapiteltext.',
        'sort_order' => 1,
        'hierarchy_level' => 1,
        'start_line' => 3,
        'end_line' => 6,
    ]);

    $this->getJson("/api/admin/abas/{$aba->id}/analysis/results")
        ->assertSuccessful()
        ->assertJsonPath('data.aba.id', $aba->id)
        ->assertJsonPath('data.main_document.id', $mainAttachment->id)
        ->assertJsonPath('data.analysis_run.id', $run->id)
        ->assertJsonPath('data.sections.0.section_title', '1 Einleitung')
        ->assertJsonPath('data.sections.0.extracted_text', 'Das ist der Kapiteltext.');
});

test('analysis results endpoint includes record counts and abstract language stats for ui', function () {
    $user = createAbaTeacher($this->school, $this->schoolyear);
    $this->actingAs($user, 'sanctum');

    $aba = Aba::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $user->id,
    ]);
    $mainAttachment = createMainDocumentAttachment($aba, 'hauptdokument.txt', "Titel\n\nZusammenfassung\nText");

    $run = AbaAnalysisRun::query()->create([
        'aba_id' => $aba->id,
        'aba_attachment_id' => $mainAttachment->id,
        'created_by_user_id' => $user->id,
        'status' => AbaAnalysisRun::STATUS_COMPLETED,
        'status_message' => 'Analyse abgeschlossen.',
        'source_original_name' => 'hauptdokument.txt',
        'source_path' => $mainAttachment->path,
        'source_mime_type' => 'text/plain',
        'started_at' => now()->subMinutes(2),
        'running_at' => now()->subMinute(),
        'completed_at' => now(),
        'summary' => [
            'analysis_stats' => [
                'detected_record_count' => 4,
                'normalized_record_count' => 4,
                'validated_record_count' => 4,
                'persisted_record_count' => 4,
                'count_mismatch_detected' => false,
                'count_mismatch_reason' => null,
                'abstract_detected' => true,
                'abstract_de_detected' => true,
                'abstract_en_detected' => false,
                'abstract_missing_languages' => ['en'],
                'chapter_count' => 1,
                'subchapter_count' => 0,
                'final_confidence' => 0.91,
                'analysis_quality_score' => 0.9,
                'review_state' => 'auto_approved',
                'auto_approved' => true,
            ],
            'record_counts' => [
                'detected_record_count' => 4,
                'normalized_record_count' => 4,
                'validated_record_count' => 4,
                'persisted_record_count' => 4,
            ],
        ],
    ]);

    $this->getJson("/api/admin/abas/{$aba->id}/analysis/results")
        ->assertSuccessful()
        ->assertJsonPath('data.analysis_run.id', $run->id)
        ->assertJsonPath('data.analysis_run.record_counts.detected_record_count', 4)
        ->assertJsonPath('data.analysis_run.record_counts.persisted_record_count', 4)
        ->assertJsonPath('data.analysis_run.analysis_stats.abstract_detected', true)
        ->assertJsonPath('data.analysis_run.analysis_stats.abstract_de_detected', true)
        ->assertJsonPath('data.analysis_run.analysis_stats.abstract_en_detected', false)
        ->assertJsonPath('data.analysis_run.analysis_stats.review_state', 'auto_approved');
});

test('analysis results endpoint handles missing run and missing section text cleanly', function () {
    $user = createAbaTeacher($this->school, $this->schoolyear);
    $this->actingAs($user, 'sanctum');

    $aba = Aba::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $user->id,
    ]);
    createMainDocumentAttachment($aba, 'hauptdokument.txt', 'Nur Inhalt');

    $this->getJson("/api/admin/abas/{$aba->id}/analysis/results")
        ->assertSuccessful()
        ->assertJsonPath('data.aba.id', $aba->id)
        ->assertJsonPath('data.analysis_run', null)
        ->assertJsonCount(0, 'data.sections');
});

test('analysis results endpoint is forbidden for foreign aba', function () {
    $owner = createAbaTeacher($this->school, $this->schoolyear);
    $this->actingAs($owner, 'sanctum');

    $otherSchool = School::factory()->create();
    $otherSchoolyear = Schoolyear::factory()->create(['school_id' => $otherSchool->id]);
    $foreignUser = createAbaTeacher($otherSchool, $otherSchoolyear);

    $foreignAba = Aba::factory()->create([
        'school_id' => $otherSchool->id,
        'schoolyear_id' => $otherSchoolyear->id,
        'user_id' => $foreignUser->id,
    ]);

    $this->getJson("/api/admin/abas/{$foreignAba->id}/analysis/results")
        ->assertForbidden();
});

test('results page route is reachable in aba area', function () {
    $user = createAbaTeacher($this->school, $this->schoolyear);
    $this->actingAs($user);

    $this->get('/admin/aba/results/123')
        ->assertSuccessful()
        ->assertViewIs('spa::admin');
});

test('overview vue contains status date time, analyse and ergebnisse actions', function () {
    $overviewContent = file_get_contents(resource_path('js/pages/admin/aba/components/Overview.vue'));

    expect($overviewContent)
        ->toContain('Analyse')
        ->toContain('Ergebnisse')
        ->toContain('EXTRAKTION:')
        ->toContain('analysisStatusLine(aba)')
        ->toContain("hour: '2-digit'")
        ->toContain("minute: '2-digit'")
        ->toContain('/api/admin/abas/${abaId}/analysis')
        ->toContain('/admin/aba/results/${abaId}')
        ->toContain('openResults(aba)')
        ->toContain('startStatusPolling')
        ->toContain('pollAnalysisStatus')
        ->toContain('setInterval')
        ->toContain('clearInterval');
});

test('results page contains vuetify accordion and section text rendering', function () {
    $resultsPageContent = file_get_contents(resource_path('js/pages/admin/aba/AbaAnalysisResults.vue'));
    $treeComponentContent = file_get_contents(resource_path('js/pages/admin/aba/components/AbaRecordTree.vue'));

    expect($resultsPageContent)
        ->toContain('ItsGridBox')
        ->toContain('variant="overview"')
        ->toContain('v-expansion-panels')
        ->toContain('v-expansion-panel')
        ->toContain('chapterRootRecords')
        ->toContain('AbaRecordTree')
        ->toContain('Erkannte Kapitel / Abschnitte')
        ->toContain('Anzahl Datensätze')
        ->toContain('Keine Analyseergebnisse vorhanden.')
        ->toContain('/api/admin/abas/${this.abaId}/analysis/results');

    expect($treeComponentContent)
        ->toContain('Kein Text für diesen Datensatz gespeichert.')
        ->toContain('Extrahierte Angaben')
        ->toContain('Einreicher (Verfasst von)')
        ->toContain('source_block_ids')
        ->toContain('pageRangeLabel');
});

test('title page details are extracted and persisted when available', function () {
    if (! class_exists(ZipArchive::class)) {
        $this->markTestSkipped('ZipArchive extension missing.');
    }

    config()->set('aba_analysis.openai_normalization_enabled', false);

    $user = createAbaTeacher($this->school, $this->schoolyear);
    $aba = Aba::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $user->id,
    ]);

    $mainAttachment = createMainDocxAttachment($aba, 'titelblatt-metadata.docx', [
        ['text' => 'Christian Doppler-Gymnasium', 'style' => 'Normal'],
        ['text' => 'Franz-Josef-Kai 41', 'style' => 'Normal'],
        ['text' => '5020 Salzburg', 'style' => 'Normal'],
        ['text' => 'Die Rolle der Medien in der politischen Meinungsbildung', 'style' => 'Normal'],
        ['text' => 'Verfasst von', 'style' => 'Normal'],
        ['text' => 'Yvonne Pucher', 'style' => 'Normal'],
        ['text' => 'Betreuer: Dipl.-Ing. Günther Kron', 'style' => 'Normal'],
        ['text' => 'Klasse 8M', 'style' => 'Normal'],
        ['text' => 'Schuljahr 2025/2026', 'style' => 'Normal'],
        ['page_break' => true],
        ['text' => 'Abstract', 'style' => 'Heading1', 'outline' => 0],
        ['text' => 'Kurzfassung der Arbeit', 'style' => 'Normal'],
    ]);

    $run = AbaAnalysisRun::query()->create([
        'aba_id' => $aba->id,
        'aba_attachment_id' => $mainAttachment->id,
        'created_by_user_id' => $user->id,
        'status' => AbaAnalysisRun::STATUS_STARTED,
        'status_message' => 'Analyselauf wurde gestartet.',
        'source_original_name' => 'titelblatt-metadata.docx',
        'source_path' => $mainAttachment->path,
        'source_mime_type' => $mainAttachment->mime_type,
        'started_at' => now(),
    ]);

    app(AbaAnalysisService::class)->processRun($run->id);

    $titlePage = AbaAnalysisResult::query()
        ->where('aba_analysis_run_id', $run->id)
        ->where('section_type', 'title_page')
        ->first();

    expect($titlePage)->not->toBeNull();

    $details = is_array($titlePage?->metadata['title_page_details'] ?? null)
        ? $titlePage->metadata['title_page_details']
        : [];

    expect((string) ($details['title'] ?? ''))->toBe('Die Rolle der Medien in der politischen Meinungsbildung')
        ->and((string) ($details['submitter'] ?? ''))->toBe('Yvonne Pucher')
        ->and((string) ($details['advisor'] ?? ''))->toContain('Günther Kron')
        ->and((string) ($details['class'] ?? ''))->toBe('8M')
        ->and((string) ($details['year'] ?? ''))->toBe('2025/2026');
});

test('docx page mapper assigns real page numbers when page breaks are present', function () {
    if (! class_exists(ZipArchive::class)) {
        $this->markTestSkipped('ZipArchive required.');
    }

    $user = createAbaTeacher($this->school, $this->schoolyear);
    $aba = Aba::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $user->id,
    ]);

    $attachment = createMainDocxAttachment($aba, 'test-pagination.docx', [
        ['text' => 'Deckblatt', 'style' => 'Heading1', 'outline' => 0],
        ['text' => 'Kurzfassung des Dokuments.'],
        ['page_break' => true],
        ['text' => '1. Einleitung', 'style' => 'Heading1', 'outline' => 0],
        ['text' => 'Hier beginnt der eigentliche Inhalt des Dokuments.'],
        ['text' => 'Weiterer Absatz im ersten Kapitel.'],
    ]);

    $run = AbaAnalysisRun::query()->create([
        'aba_id' => $aba->id,
        'aba_attachment_id' => $attachment->id,
        'created_by_user_id' => $user->id,
        'status' => AbaAnalysisRun::STATUS_STARTED,
        'status_message' => 'Analyselauf wurde gestartet.',
        'source_original_name' => $attachment->original_name,
        'source_path' => $attachment->path,
        'source_mime_type' => $attachment->mime_type,
        'started_at' => now(),
    ]);

    app(AbaAnalysisService::class)->processRun($run->id);
    $run->refresh();

    expect($run->status)->toBe(AbaAnalysisRun::STATUS_COMPLETED);

    $stats = $run->summary['analysis_stats'] ?? [];
    expect((bool) ($stats['has_real_pagination'] ?? false))->toBeTrue()
        ->and((int) ($stats['page_count_total'] ?? 0))->toBeGreaterThanOrEqual(2)
        ->and((int) ($stats['records_with_page_mapping_count'] ?? 0))->toBeGreaterThan(0);

    $results = AbaAnalysisResult::query()
        ->where('aba_analysis_run_id', $run->id)
        ->orderBy('sort_order')
        ->get();

    $chapterSection = $results->first(fn ($r) => str_starts_with((string) ($r->section_title ?? ''), '1.'));
    if ($chapterSection !== null) {
        expect((int) ($chapterSection->start_page ?? 0))->toBeGreaterThanOrEqual(2);
    }
});

test('docx without page breaks has no real pagination', function () {
    if (! class_exists(ZipArchive::class)) {
        $this->markTestSkipped('ZipArchive required.');
    }

    $user = createAbaTeacher($this->school, $this->schoolyear);
    $aba = Aba::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $user->id,
    ]);

    $attachment = createMainDocxAttachment($aba, 'no-pagebreaks.docx', [
        ['text' => 'Deckblatt', 'style' => 'Heading1', 'outline' => 0],
        ['text' => '1. Kapitel', 'style' => 'Heading1', 'outline' => 0],
        ['text' => 'Inhalt des Kapitels.'],
    ]);

    $run = AbaAnalysisRun::query()->create([
        'aba_id' => $aba->id,
        'aba_attachment_id' => $attachment->id,
        'created_by_user_id' => $user->id,
        'status' => AbaAnalysisRun::STATUS_STARTED,
        'status_message' => 'Analyselauf wurde gestartet.',
        'source_original_name' => $attachment->original_name,
        'source_path' => $attachment->path,
        'source_mime_type' => $attachment->mime_type,
        'started_at' => now(),
    ]);

    app(AbaAnalysisService::class)->processRun($run->id);
    $run->refresh();

    expect($run->status)->toBe(AbaAnalysisRun::STATUS_COMPLETED);

    $stats = $run->summary['analysis_stats'] ?? [];
    expect((bool) ($stats['has_real_pagination'] ?? false))->toBeFalse()
        ->and((string) ($stats['pagination_source'] ?? ''))->toBe('not_available');

    $results = AbaAnalysisResult::query()
        ->where('aba_analysis_run_id', $run->id)
        ->get();

    foreach ($results as $result) {
        expect($result->start_page)->toBeNull();
    }
});

test('api analysis results resource delivers page_start page_end and page_label for mapped records', function () {
    if (! class_exists(ZipArchive::class)) {
        $this->markTestSkipped('ZipArchive required.');
    }

    $user = createAbaTeacher($this->school, $this->schoolyear);
    $this->actingAs($user, 'sanctum');

    $aba = Aba::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $user->id,
    ]);

    $attachment = createMainDocxAttachment($aba, 'api-pagination.docx', [
        ['text' => 'Deckblatt', 'style' => 'Heading1', 'outline' => 0],
        ['page_break' => true],
        ['text' => '1. Hauptkapitel', 'style' => 'Heading1', 'outline' => 0],
        ['text' => 'Inhalt.'],
    ]);

    $run = AbaAnalysisRun::query()->create([
        'aba_id' => $aba->id,
        'aba_attachment_id' => $attachment->id,
        'created_by_user_id' => $user->id,
        'status' => AbaAnalysisRun::STATUS_STARTED,
        'status_message' => 'Analyselauf wurde gestartet.',
        'source_original_name' => $attachment->original_name,
        'source_path' => $attachment->path,
        'source_mime_type' => $attachment->mime_type,
        'started_at' => now(),
    ]);

    app(AbaAnalysisService::class)->processRun($run->id);

    $response = $this->getJson("/api/admin/abas/{$aba->id}/analysis/results")
        ->assertOk();

    $sections = $response->json('data.sections');
    expect($sections)->toBeArray()->not->toBeEmpty();

    foreach ($sections as $section) {
        expect($section)->toHaveKey('start_page')
            ->toHaveKey('end_page')
            ->toHaveKey('page_label');
    }

    $withLabel = array_filter($sections, fn ($s) => $s['page_label'] !== null);
    expect(count($withLabel))->toBeGreaterThan(0);

    $analysisRun = $response->json('data.analysis_run');
    expect($analysisRun)->toHaveKey('has_real_pagination')
        ->toHaveKey('page_count_total')
        ->toHaveKey('page_mapping_coverage');
});

test('plain text document sections never get a non-null page_label', function () {
    $user = createAbaTeacher($this->school, $this->schoolyear);
    $this->actingAs($user, 'sanctum');

    $aba = Aba::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $user->id,
    ]);

    createMainDocumentAttachment($aba, 'plain.txt', "Deckblatt\n\nInhalt des Dokuments.\n\n1. Kapitel\nText des Kapitels.");

    $response = $this->postJson("/api/admin/abas/{$aba->id}/analysis")
        ->assertStatus(202);

    $runId = (int) $response->json('data.id');
    app(AbaAnalysisService::class)->processRun($runId);

    $response = $this->getJson("/api/admin/abas/{$aba->id}/analysis/results")
        ->assertOk();

    $sections = $response->json('data.sections');
    foreach ($sections as $section) {
        expect($section['page_label'])->toBeNull();
    }
});
