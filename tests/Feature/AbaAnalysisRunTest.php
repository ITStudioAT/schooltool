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
 * @param  array<int, array{
 *   text?:string,
 *   style?:string,
 *   outline?:int|null,
 *   page_break?:bool,
 *   align?:string,
 *   indent_left?:int|null,
 *   spacing_before?:int|null,
 *   spacing_after?:int|null,
 *   bold?:bool,
 *   font_size_half_points?:int|null,
 *   raw_xml?:string
 * }>  $paragraphs
 */
function buildDocxDocumentXml(array $paragraphs): string
{
    $parts = [];
    foreach ($paragraphs as $paragraph) {
        if (is_string($paragraph['raw_xml'] ?? null) && trim((string) $paragraph['raw_xml']) !== '') {
            $parts[] = trim((string) $paragraph['raw_xml']);

            continue;
        }

        // A page-break-only paragraph: empty text, just a <w:br w:type="page"/>
        if (($paragraph['page_break'] ?? false) === true) {
            $parts[] = '<w:p><w:r><w:br w:type="page"/></w:r></w:p>';

            continue;
        }

        $text = docxXmlEscape((string) ($paragraph['text'] ?? ''));
        $style = trim((string) ($paragraph['style'] ?? ''));
        $outline = $paragraph['outline'] ?? null;
        $alignment = trim((string) ($paragraph['align'] ?? ''));
        $indentLeft = is_numeric($paragraph['indent_left'] ?? null) ? (int) $paragraph['indent_left'] : null;
        $spacingBefore = is_numeric($paragraph['spacing_before'] ?? null) ? (int) $paragraph['spacing_before'] : null;
        $spacingAfter = is_numeric($paragraph['spacing_after'] ?? null) ? (int) $paragraph['spacing_after'] : null;
        $bold = (bool) ($paragraph['bold'] ?? false);
        $fontSizeHalfPoints = is_numeric($paragraph['font_size_half_points'] ?? null) ? (int) $paragraph['font_size_half_points'] : null;

        $pPr = '';
        if ($style !== '' || is_numeric($outline) || $alignment !== '' || $indentLeft !== null || $spacingBefore !== null || $spacingAfter !== null) {
            $pPr .= '<w:pPr>';
            if ($style !== '') {
                $pPr .= '<w:pStyle w:val="'.docxXmlEscape($style).'"/>';
            }
            if (is_numeric($outline)) {
                $pPr .= '<w:outlineLvl w:val="'.(int) $outline.'"/>';
            }
            if ($alignment !== '') {
                $pPr .= '<w:jc w:val="'.docxXmlEscape($alignment).'"/>';
            }
            if ($indentLeft !== null) {
                $pPr .= '<w:ind w:left="'.$indentLeft.'"/>';
            }
            if ($spacingBefore !== null || $spacingAfter !== null) {
                $beforeValue = $spacingBefore !== null ? ' w:before="'.$spacingBefore.'"' : '';
                $afterValue = $spacingAfter !== null ? ' w:after="'.$spacingAfter.'"' : '';
                $pPr .= '<w:spacing'.$beforeValue.$afterValue.'/>';
            }
            $pPr .= '</w:pPr>';
        }

        $rPr = '';
        if ($bold || $fontSizeHalfPoints !== null) {
            $rPr .= '<w:rPr>';
            if ($bold) {
                $rPr .= '<w:b/>';
            }
            if ($fontSizeHalfPoints !== null) {
                $rPr .= '<w:sz w:val="'.$fontSizeHalfPoints.'"/>';
            }
            $rPr .= '</w:rPr>';
        }

        $parts[] = '<w:p>'.$pPr.'<w:r>'.$rPr.'<w:t xml:space="preserve">'.$text.'</w:t></w:r></w:p>';
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
        ->and((int) $run->extracted_sections_count)->toBeGreaterThan(0)
        ->and((int) ($run->text_length ?? 0))->toBeGreaterThan(0)
        ->and((int) ($run->text_length_without_spaces ?? 0))->toBeGreaterThan(0);

    $summary = is_array($run->summary) ? $run->summary : [];
    expect($summary)
        ->toHaveKey('analysis_stats')
        ->toHaveKey('record_counts')
        ->toHaveKey('display_values')
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
    $displayValues = is_array($summary['display_values'] ?? null) ? $summary['display_values'] : [];
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
        ->toHaveKey('abstract_de_range')
        ->toHaveKey('abstract_en_range')
        ->toHaveKey('abstract_missing_languages')
        ->toHaveKey('foreword_detected')
        ->toHaveKey('table_of_contents_detected')
        ->toHaveKey('bibliography_detected')
        ->toHaveKey('figure_index_detected')
        ->toHaveKey('consent_declaration_detected')
        ->toHaveKey('chapter_count')
        ->toHaveKey('subchapter_count')
        ->toHaveKey('text_length')
        ->toHaveKey('text_length_without_spaces')
        ->toHaveKey('analysis_quality_score')
        ->toHaveKey('structure_quality_score')
        ->toHaveKey('extraction_consistency_score')
        ->toHaveKey('persistence_consistency_score')
        ->toHaveKey('frontmatter_boundary_confidence')
        ->toHaveKey('body_reentry_confidence')
        ->toHaveKey('heading_assignment_confidence')
        ->toHaveKey('bibliography_context_confidence')
        ->toHaveKey('figure_mapping_confidence')
        ->toHaveKey('hierarchy_anomaly_count')
        ->toHaveKey('orphan_candidate_count')
        ->toHaveKey('unresolved_heading_candidates_count')
        ->toHaveKey('multi_line_caption_count')
        ->toHaveKey('bibliography_entry_count')
        ->toHaveKey('toc_special_entries_count')
        ->toHaveKey('dataset_boundary_adjustments_count');

    expect((int) ($analysisStats['persisted_record_count'] ?? 0))->toBe($persistedCount)
        ->and((int) ($recordCounts['persisted_record_count'] ?? 0))->toBe($persistedCount)
        ->and((int) ($displayValues['total_record_count'] ?? 0))->toBe($persistedCount)
        ->and((int) ($displayValues['text_length'] ?? 0))->toBeGreaterThan(0)
        ->and((int) ($displayValues['text_length_without_spaces'] ?? 0))->toBeGreaterThan(0)
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
        ->and((int) ($analysisStats['text_length'] ?? 0))->toBeGreaterThan(0)
        ->and((int) ($analysisStats['text_length_without_spaces'] ?? 0))->toBeGreaterThan(0)
        ->and((int) ($analysisStats['text_length_without_spaces'] ?? 0))->toBeLessThan((int) ($analysisStats['text_length'] ?? 0))
        ->and((float) ($analysisStats['analysis_quality_score'] ?? -1))->toBeGreaterThanOrEqual(0.0)
        ->and((float) ($analysisStats['analysis_quality_score'] ?? 2))->toBeLessThanOrEqual(1.0)
        ->and((float) ($analysisStats['structure_quality_score'] ?? -1))->toBeGreaterThanOrEqual(0.0)
        ->and((float) ($analysisStats['structure_quality_score'] ?? 2))->toBeLessThanOrEqual(1.0)
        ->and((float) ($analysisStats['extraction_consistency_score'] ?? -1))->toBeGreaterThanOrEqual(0.0)
        ->and((float) ($analysisStats['extraction_consistency_score'] ?? 2))->toBeLessThanOrEqual(1.0)
        ->and((float) ($analysisStats['persistence_consistency_score'] ?? -1))->toBeGreaterThanOrEqual(0.0)
        ->and((float) ($analysisStats['persistence_consistency_score'] ?? 2))->toBeLessThanOrEqual(1.0)
        ->and((float) ($analysisStats['frontmatter_boundary_confidence'] ?? -1))->toBeGreaterThanOrEqual(0.0)
        ->and((float) ($analysisStats['frontmatter_boundary_confidence'] ?? 2))->toBeLessThanOrEqual(1.0)
        ->and((float) ($analysisStats['body_reentry_confidence'] ?? -1))->toBeGreaterThanOrEqual(0.0)
        ->and((float) ($analysisStats['body_reentry_confidence'] ?? 2))->toBeLessThanOrEqual(1.0)
        ->and((float) ($analysisStats['heading_assignment_confidence'] ?? -1))->toBeGreaterThanOrEqual(0.0)
        ->and((float) ($analysisStats['heading_assignment_confidence'] ?? 2))->toBeLessThanOrEqual(1.0)
        ->and((float) ($analysisStats['bibliography_context_confidence'] ?? -1))->toBeGreaterThanOrEqual(0.0)
        ->and((float) ($analysisStats['bibliography_context_confidence'] ?? 2))->toBeLessThanOrEqual(1.0)
        ->and((float) ($analysisStats['figure_mapping_confidence'] ?? -1))->toBeGreaterThanOrEqual(0.0)
        ->and((float) ($analysisStats['figure_mapping_confidence'] ?? 2))->toBeLessThanOrEqual(1.0)
        ->and((int) ($analysisStats['hierarchy_anomaly_count'] ?? -1))->toBeGreaterThanOrEqual(0)
        ->and((int) ($analysisStats['orphan_candidate_count'] ?? -1))->toBeGreaterThanOrEqual(0)
        ->and((int) ($analysisStats['unresolved_heading_candidates_count'] ?? -1))->toBeGreaterThanOrEqual(0)
        ->and((int) ($analysisStats['multi_line_caption_count'] ?? -1))->toBeGreaterThanOrEqual(0)
        ->and((int) ($analysisStats['bibliography_entry_count'] ?? -1))->toBeGreaterThanOrEqual(0)
        ->and((int) ($analysisStats['toc_special_entries_count'] ?? -1))->toBeGreaterThanOrEqual(0)
        ->and((int) ($analysisStats['dataset_boundary_adjustments_count'] ?? -1))->toBeGreaterThanOrEqual(0);

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
        ->toContain('figure');

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

test('processing run detects abstract variants with language hints in heading labels', function () {
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
        'Zusammenfassung (Deutsch)',
        'Diese Zusammenfassung beschreibt die Methode und die Ergebnisse der Arbeit.',
        '',
        'Executive Summary (English)',
        'This summary outlines the scope, method and key findings.',
        '',
        'Vorwort',
        'Vorwortstext.',
        '',
        'Inhaltsverzeichnis',
        '1 Einleitung .... 4',
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
        ->and((bool) ($analysisStats['abstract_en_detected'] ?? false))->toBeTrue()
        ->and((array) ($analysisStats['abstract_missing_languages'] ?? []))->toBe([]);
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

    $summary = is_array($run->summary) ? $run->summary : [];
    $analysisStats = is_array($summary['analysis_stats'] ?? null) ? $summary['analysis_stats'] : [];

    $results = AbaAnalysisResult::query()
        ->where('aba_analysis_run_id', $run->id)
        ->orderBy('sort_order')
        ->get();

    $bibliography = $results->firstWhere('section_type', 'bibliography');
    expect($bibliography)->not->toBeNull()
        ->and((string) ($bibliography->section_title ?? ''))->toBe('Literaturverzeichnis')
        ->and((string) ($bibliography->extracted_text ?? ''))->toContain('Cote, B. (2017)')
        ->and((string) ($bibliography->extracted_text ?? ''))->toContain('https://orf.at/');

    $otherSections = $results->where('section_type', 'other_section');
    $otherTitles = $otherSections->pluck('section_title')->filter()->values()->all();
    expect($otherTitles)->not->toContain('Cote, B. (2017). Platform Capitalism. Polity Press.')
        ->and($otherTitles)->not->toContain('ORF. (2024). Medienbericht. https://orf.at/')
        ->and((int) ($analysisStats['bibliography_entry_count'] ?? 0))->toBeGreaterThanOrEqual(2);
});

test('quellenverzeichnis umbrella normalizes to one Literaturverzeichnis block with preserved subheadings', function () {
    if (! class_exists(ZipArchive::class)) {
        $this->markTestSkipped('ZipArchive extension missing.');
    }

    $user = createAbaTeacher($this->school, $this->schoolyear);
    $aba = Aba::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $user->id,
    ]);

    $mainAttachment = createMainDocxAttachment($aba, 'backmatter-umbrella-bibliography.docx', [
        ['text' => 'ABA-Arbeit', 'style' => 'Normal'],
        ['text' => '1. Einleitung', 'style' => 'Heading1', 'outline' => 0],
        ['text' => 'Einleitender Inhalt.'],
        ['text' => 'Quellenverzeichnis', 'style' => 'Heading1', 'outline' => 0],
        ['text' => 'Literaturverzeichnis', 'style' => 'Heading1', 'outline' => 0],
        ['text' => 'Cote, B. (2017). Platform Capitalism. Polity Press.'],
        ['text' => 'Rosendahl, W. (2017). Forensische Anthropologie. Springer.'],
        ['text' => 'Internetquellenverzeichnis', 'style' => 'Heading1', 'outline' => 0],
        ['text' => 'Burkhard (2007). Zugriff am 10.01.2026. Verfügbar unter https://example.org/zahnstatus'],
        ['text' => 'ORF (2024). Medienbericht. https://orf.at/'],
        ['text' => 'Abbildungsverzeichnis', 'style' => 'Heading1', 'outline' => 0],
        ['text' => 'Abb. 1 Mediennutzung'],
        ['text' => 'Abb. 2 Plattformvergleich'],
    ]);

    $run = AbaAnalysisRun::query()->create([
        'aba_id' => $aba->id,
        'aba_attachment_id' => $mainAttachment->id,
        'created_by_user_id' => $user->id,
        'status' => AbaAnalysisRun::STATUS_STARTED,
        'status_message' => 'Analyselauf wurde gestartet.',
        'source_original_name' => 'backmatter-umbrella-bibliography.docx',
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

    $bibliographies = $results->where('section_type', 'bibliography')->values();
    $bibliography = $bibliographies->first();
    $figureIndex = $results->firstWhere('section_title', 'Abbildungsverzeichnis');

    expect($bibliographies)->toHaveCount(1)
        ->and($bibliography)->not->toBeNull()
        ->and((string) ($bibliography?->section_title ?? ''))->toBe('Literaturverzeichnis')
        ->and((string) ($bibliography?->section_type ?? ''))->toBe('bibliography')
        ->and((string) ($bibliography?->extracted_text ?? ''))->toContain('Literaturverzeichnis')
        ->and((string) ($bibliography?->extracted_text ?? ''))->toContain('Internetquellenverzeichnis')
        ->and((string) ($bibliography?->extracted_text ?? ''))->toContain('Cote, B. (2017).')
        ->and((string) ($bibliography?->extracted_text ?? ''))->toContain('https://example.org/zahnstatus')
        ->and((string) ($bibliography?->extracted_text ?? ''))->not->toContain('Abbildungsverzeichnis')
        ->and($figureIndex)->not->toBeNull()
        ->and((string) ($figureIndex?->section_type ?? ''))->toBe('figure_index')
        ->and((int) ($bibliography?->end_line ?? 0))->toBeLessThan((int) ($figureIndex?->start_line ?? 0))
        ->and((int) ($analysisStats['bibliography_count'] ?? 0))->toBe(1);
});

test('quellenverzeichnis without subheadings normalizes to Literaturverzeichnis', function () {
    if (! class_exists(ZipArchive::class)) {
        $this->markTestSkipped('ZipArchive extension missing.');
    }

    $user = createAbaTeacher($this->school, $this->schoolyear);
    $aba = Aba::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $user->id,
    ]);

    $mainAttachment = createMainDocxAttachment($aba, 'quellenverzeichnis-standalone.docx', [
        ['text' => 'ABA-Arbeit', 'style' => 'Normal'],
        ['text' => '1. Einleitung', 'style' => 'Heading1', 'outline' => 0],
        ['text' => 'Einleitender Inhalt.'],
        ['text' => 'Quellenverzeichnis', 'style' => 'Heading1', 'outline' => 0],
        ['text' => 'Cote, B. (2017). Platform Capitalism. Polity Press.'],
        ['text' => 'ORF. (2024). Medienbericht. https://orf.at/'],
        ['text' => 'Abbildungsverzeichnis', 'style' => 'Heading1', 'outline' => 0],
        ['text' => 'Abb. 1 Mediennutzung'],
    ]);

    $run = AbaAnalysisRun::query()->create([
        'aba_id' => $aba->id,
        'aba_attachment_id' => $mainAttachment->id,
        'created_by_user_id' => $user->id,
        'status' => AbaAnalysisRun::STATUS_STARTED,
        'status_message' => 'Analyselauf wurde gestartet.',
        'source_original_name' => 'quellenverzeichnis-standalone.docx',
        'source_path' => $mainAttachment->path,
        'source_mime_type' => $mainAttachment->mime_type,
        'started_at' => now(),
    ]);

    app(AbaAnalysisService::class)->processRun($run->id);
    $run->refresh();

    $results = AbaAnalysisResult::query()
        ->where('aba_analysis_run_id', $run->id)
        ->orderBy('sort_order')
        ->get();

    $quellen = $results->firstWhere('section_type', 'bibliography');
    $figureIndex = $results->firstWhere('section_title', 'Abbildungsverzeichnis');

    expect($quellen)->not->toBeNull()
        ->and((string) ($quellen?->section_type ?? ''))->toBe('bibliography')
        ->and((string) ($quellen?->section_title ?? ''))->toBe('Literaturverzeichnis')
        ->and((string) ($quellen?->extracted_text ?? ''))->toContain('Cote, B. (2017).')
        ->and((string) ($quellen?->extracted_text ?? ''))->toContain('https://orf.at/')
        ->and((string) ($quellen?->extracted_text ?? ''))->not->toContain('Abbildungsverzeichnis')
        ->and($figureIndex)->not->toBeNull()
        ->and((string) ($figureIndex?->section_type ?? ''))->toBe('figure_index');
});

test('generic heading Quellen is normalized to Literaturverzeichnis when source entries follow directly', function () {
    if (! class_exists(ZipArchive::class)) {
        $this->markTestSkipped('ZipArchive extension missing.');
    }

    $user = createAbaTeacher($this->school, $this->schoolyear);
    $aba = Aba::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $user->id,
    ]);

    $mainAttachment = createMainDocxAttachment($aba, 'quellen-generic-heading.docx', [
        ['text' => 'ABA-Arbeit', 'style' => 'Normal'],
        ['text' => '1. Einleitung', 'style' => 'Heading1', 'outline' => 0],
        ['text' => 'Einleitender Inhalt.'],
        ['text' => 'Quellen', 'style' => 'Heading1', 'outline' => 0],
        ['text' => 'Rosendahl, W. (2017). Forensische Anthropologie. Springer.'],
        ['text' => 'A. u. (2015). Zugriff am 18.01.2021. Verfügbar unter https://example.org/dna-analyse'],
        ['text' => 'Abbildungsverzeichnis', 'style' => 'Heading1', 'outline' => 0],
        ['text' => 'Abb. 1 Mediennutzung'],
    ]);

    $run = AbaAnalysisRun::query()->create([
        'aba_id' => $aba->id,
        'aba_attachment_id' => $mainAttachment->id,
        'created_by_user_id' => $user->id,
        'status' => AbaAnalysisRun::STATUS_STARTED,
        'status_message' => 'Analyselauf wurde gestartet.',
        'source_original_name' => 'quellen-generic-heading.docx',
        'source_path' => $mainAttachment->path,
        'source_mime_type' => $mainAttachment->mime_type,
        'started_at' => now(),
    ]);

    app(AbaAnalysisService::class)->processRun($run->id);
    $run->refresh();

    $results = AbaAnalysisResult::query()
        ->where('aba_analysis_run_id', $run->id)
        ->orderBy('sort_order')
        ->get();

    $quellen = $results->firstWhere('section_type', 'bibliography');
    $figureIndex = $results->firstWhere('section_title', 'Abbildungsverzeichnis');

    expect($quellen)->not->toBeNull()
        ->and((string) ($quellen?->section_type ?? ''))->toBe('bibliography')
        ->and((string) ($quellen?->section_title ?? ''))->toBe('Literaturverzeichnis')
        ->and((string) ($quellen?->extracted_text ?? ''))->toContain('Quellen')
        ->and((string) ($quellen?->extracted_text ?? ''))->toContain('Rosendahl, W. (2017).')
        ->and((string) ($quellen?->extracted_text ?? ''))->toContain('https://example.org/dna-analyse')
        ->and((string) ($quellen?->extracted_text ?? ''))->not->toContain('Abbildungsverzeichnis')
        ->and($figureIndex)->not->toBeNull()
        ->and((string) ($figureIndex?->section_type ?? ''))->toBe('figure_index');
});

test('literaturverzeichnis remains a standalone bibliography heading when no child bibliography headings follow', function () {
    if (! class_exists(ZipArchive::class)) {
        $this->markTestSkipped('ZipArchive extension missing.');
    }

    $user = createAbaTeacher($this->school, $this->schoolyear);
    $aba = Aba::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $user->id,
    ]);

    $mainAttachment = createMainDocxAttachment($aba, 'literaturverzeichnis-standalone.docx', [
        ['text' => 'ABA-Arbeit', 'style' => 'Normal'],
        ['text' => '1. Einleitung', 'style' => 'Heading1', 'outline' => 0],
        ['text' => 'Einleitender Inhalt.'],
        ['text' => 'Literaturverzeichnis', 'style' => 'Heading1', 'outline' => 0],
        ['text' => 'Cote, B. (2017). Platform Capitalism. Polity Press.'],
        ['text' => 'Rosendahl, W. (2017). Forensische Anthropologie. Springer.'],
        ['text' => 'Abbildungsverzeichnis', 'style' => 'Heading1', 'outline' => 0],
        ['text' => 'Abb. 1 Mediennutzung'],
    ]);

    $run = AbaAnalysisRun::query()->create([
        'aba_id' => $aba->id,
        'aba_attachment_id' => $mainAttachment->id,
        'created_by_user_id' => $user->id,
        'status' => AbaAnalysisRun::STATUS_STARTED,
        'status_message' => 'Analyselauf wurde gestartet.',
        'source_original_name' => 'literaturverzeichnis-standalone.docx',
        'source_path' => $mainAttachment->path,
        'source_mime_type' => $mainAttachment->mime_type,
        'started_at' => now(),
    ]);

    app(AbaAnalysisService::class)->processRun($run->id);
    $run->refresh();

    $results = AbaAnalysisResult::query()
        ->where('aba_analysis_run_id', $run->id)
        ->orderBy('sort_order')
        ->get();

    $literatur = $results->firstWhere('section_title', 'Literaturverzeichnis');
    $figureIndex = $results->firstWhere('section_title', 'Abbildungsverzeichnis');

    expect($literatur)->not->toBeNull()
        ->and((string) ($literatur?->section_type ?? ''))->toBe('bibliography')
        ->and((string) ($literatur?->section_title ?? ''))->toBe('Literaturverzeichnis')
        ->and((string) ($literatur?->extracted_text ?? ''))->toContain('Cote, B. (2017).')
        ->and((string) ($literatur?->extracted_text ?? ''))->not->toContain('Abbildungsverzeichnis')
        ->and($figureIndex)->not->toBeNull()
        ->and((string) ($figureIndex?->section_type ?? ''))->toBe('figure_index');
});

test('quellenangaben and webquellen merge into one normalized Literaturverzeichnis block', function () {
    if (! class_exists(ZipArchive::class)) {
        $this->markTestSkipped('ZipArchive extension missing.');
    }

    $user = createAbaTeacher($this->school, $this->schoolyear);
    $aba = Aba::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $user->id,
    ]);

    $mainAttachment = createMainDocxAttachment($aba, 'quellenangaben-webquellen.docx', [
        ['text' => 'ABA-Arbeit', 'style' => 'Normal'],
        ['text' => '1. Einleitung', 'style' => 'Heading1', 'outline' => 0],
        ['text' => 'Einleitender Inhalt.'],
        ['text' => 'Quellenangaben', 'style' => 'Heading1', 'outline' => 0],
        ['text' => 'Bucher, A. (2014). Grundlagen der Anthropologie.'],
        ['text' => 'Webquellen', 'style' => 'Heading1', 'outline' => 0],
        ['text' => 'Kenhub (2021). https://www.kenhub.com/'],
        ['text' => 'Abbildungsverzeichnis', 'style' => 'Heading1', 'outline' => 0],
        ['text' => 'Abb. 1 Mediennutzung'],
    ]);

    $run = AbaAnalysisRun::query()->create([
        'aba_id' => $aba->id,
        'aba_attachment_id' => $mainAttachment->id,
        'created_by_user_id' => $user->id,
        'status' => AbaAnalysisRun::STATUS_STARTED,
        'status_message' => 'Analyselauf wurde gestartet.',
        'source_original_name' => 'quellenangaben-webquellen.docx',
        'source_path' => $mainAttachment->path,
        'source_mime_type' => $mainAttachment->mime_type,
        'started_at' => now(),
    ]);

    app(AbaAnalysisService::class)->processRun($run->id);
    $run->refresh();

    $summary = is_array($run->summary) ? $run->summary : [];
    $analysisStats = is_array($summary['analysis_stats'] ?? null) ? $summary['analysis_stats'] : [];
    $results = AbaAnalysisResult::query()
        ->where('aba_analysis_run_id', $run->id)
        ->orderBy('sort_order')
        ->get();

    $bibliographies = $results->where('section_type', 'bibliography')->values();
    $bibliography = $bibliographies->first();
    $figureIndex = $results->firstWhere('section_title', 'Abbildungsverzeichnis');

    expect($bibliographies)->toHaveCount(1)
        ->and($bibliography)->not->toBeNull()
        ->and((string) ($bibliography?->section_title ?? ''))->toBe('Literaturverzeichnis')
        ->and((string) ($bibliography?->extracted_text ?? ''))->toContain('Quellenangaben')
        ->and((string) ($bibliography?->extracted_text ?? ''))->toContain('Webquellen')
        ->and((string) ($bibliography?->extracted_text ?? ''))->toContain('Bucher, A. (2014).')
        ->and((string) ($bibliography?->extracted_text ?? ''))->toContain('https://www.kenhub.com/')
        ->and($figureIndex)->not->toBeNull()
        ->and((string) ($figureIndex?->section_type ?? ''))->toBe('figure_index')
        ->and((int) ($bibliography?->end_line ?? 0))->toBeLessThan((int) ($figureIndex?->start_line ?? 0))
        ->and((int) ($analysisStats['bibliography_count'] ?? 0))->toBe(1);
});

test('figure index entries are not emitted as normal figure nodes while body figure captions remain', function () {
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
        ['text' => 'Abb. 7: Knochenstruktur', 'style' => 'Normal'],
        ['text' => 'Quelle: Eigene Darstellung der Ergebnisse', 'style' => 'Normal'],
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

    $summary = is_array($run->summary) ? $run->summary : [];
    $analysisStats = is_array($summary['analysis_stats'] ?? null) ? $summary['analysis_stats'] : [];

    $results = AbaAnalysisResult::query()
        ->where('aba_analysis_run_id', $run->id)
        ->orderBy('sort_order')
        ->get();

    $figureIndex = $results->firstWhere('section_type', 'figure_index');
    expect($figureIndex)->not->toBeNull();

    $figureSections = $results->where('section_type', 'figure')->values();
    $bodyFigure = $figureSections->first(fn ($section) => str_starts_with((string) ($section->section_title ?? ''), 'Abb. 7:'));
    $indexFigureOne = $figureSections->firstWhere('section_title', 'Abb. 1 Mediennutzung');
    $indexFigureTwo = $figureSections->firstWhere('section_title', 'Abb. 2 Plattformvergleich');
    $figuresInIndexRange = $figureSections->filter(function ($section) use ($figureIndex) {
        $line = (int) ($section->start_line ?? 0);
        $start = (int) ($figureIndex->start_line ?? 0);
        $end = (int) ($figureIndex->end_line ?? 0);

        return $line >= $start && $line <= $end;
    });

    expect($bodyFigure)->not->toBeNull()
        ->and((string) ($bodyFigure?->extracted_text ?? ''))->toContain('Quelle: Eigene Darstellung der Ergebnisse')
        ->and($indexFigureOne)->toBeNull()
        ->and($indexFigureTwo)->toBeNull()
        ->and($figuresInIndexRange)->toHaveCount(0)
        ->and($figureSections)->toHaveCount(1)
        ->and((string) ($figureIndex?->extracted_text ?? ''))->toContain('Abb. 1 Mediennutzung')
        ->and((string) ($figureIndex?->extracted_text ?? ''))->toContain('Abb. 2 Plattformvergleich')
        ->and((int) ($analysisStats['multi_line_caption_count'] ?? 0))->toBeGreaterThanOrEqual(1);
});

test('figure index stops before eidesstattliche erklaerung and signature lines stay in declaration', function () {
    if (! class_exists(ZipArchive::class)) {
        $this->markTestSkipped('ZipArchive extension missing.');
    }

    $user = createAbaTeacher($this->school, $this->schoolyear);
    $aba = Aba::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $user->id,
    ]);

    $mainAttachment = createMainDocxAttachment($aba, 'figure-index-declaration-boundary.docx', [
        ['text' => 'ABA-Arbeit', 'style' => 'Normal'],
        ['text' => 'Einleitung', 'style' => 'Heading1', 'outline' => 0],
        ['text' => 'Body-Inhalt.', 'style' => 'Normal'],
        ['text' => 'Abbildungsverzeichnis', 'style' => 'Heading1', 'outline' => 0],
        ['text' => 'Abb. 1 Mediennutzung', 'style' => 'Normal'],
        ['text' => 'Abb. 2 Plattformvergleich', 'style' => 'Normal'],
        ['text' => 'Eidesstattliche Erklärung', 'style' => 'Heading1', 'outline' => 0],
        ['text' => 'Ich erkläre eidesstattlich die eigenständige Erstellung der Arbeit.', 'style' => 'Normal'],
        ['text' => '________________ ___________________', 'style' => 'Normal'],
        ['text' => 'Datum Unterschrift', 'style' => 'Normal'],
    ]);

    $run = AbaAnalysisRun::query()->create([
        'aba_id' => $aba->id,
        'aba_attachment_id' => $mainAttachment->id,
        'created_by_user_id' => $user->id,
        'status' => AbaAnalysisRun::STATUS_STARTED,
        'status_message' => 'Analyselauf wurde gestartet.',
        'source_original_name' => 'figure-index-declaration-boundary.docx',
        'source_path' => $mainAttachment->path,
        'source_mime_type' => $mainAttachment->mime_type,
        'started_at' => now(),
    ]);

    app(AbaAnalysisService::class)->processRun($run->id);
    $run->refresh();

    $results = AbaAnalysisResult::query()
        ->where('aba_analysis_run_id', $run->id)
        ->orderBy('sort_order')
        ->get();

    $summary = is_array($run->summary) ? $run->summary : [];
    $analysisStats = is_array($summary['analysis_stats'] ?? null) ? $summary['analysis_stats'] : [];

    $figureIndex = $results->firstWhere('section_title', 'Abbildungsverzeichnis');
    $declaration = $results->firstWhere('section_title', 'Eidesstattliche Erklärung');

    expect($figureIndex)->not->toBeNull()
        ->and((string) ($figureIndex?->section_type ?? ''))->toBe('figure_index')
        ->and((string) ($figureIndex?->extracted_text ?? ''))->toContain('Abb. 1 Mediennutzung')
        ->and((string) ($figureIndex?->extracted_text ?? ''))->toContain('Abb. 2 Plattformvergleich')
        ->and((string) ($figureIndex?->extracted_text ?? ''))->not->toContain('Eidesstattliche Erklärung')
        ->and((string) ($figureIndex?->extracted_text ?? ''))->not->toContain('Datum Unterschrift')
        ->and($declaration)->not->toBeNull()
        ->and((string) ($declaration?->section_type ?? ''))->toBe('consent_declaration')
        ->and((string) ($declaration?->extracted_text ?? ''))->toContain('Ich erkläre eidesstattlich')
        ->and((string) ($declaration?->extracted_text ?? ''))->toContain('Datum Unterschrift')
        ->and((int) ($figureIndex?->end_line ?? 0))->toBeLessThan((int) ($declaration?->start_line ?? 0))
        ->and((bool) ($analysisStats['consent_declaration_detected'] ?? false))->toBeTrue();
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

test('numbering-compatible parent is preferred over nearest mismatched chapter', function () {
    $user = createAbaTeacher($this->school, $this->schoolyear);
    $aba = Aba::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $user->id,
    ]);

    $mainAttachment = createMainDocumentAttachment($aba, 'hauptdokument.txt', implode("\n", [
        'Titelblatt',
        'Abstract',
        'Kurzfassung',
        'Inhaltsverzeichnis',
        '2 Analyse .... 4',
        '2.1 Kontext .... 5',
        '3 Methode .... 7',
        '2.2 Vertiefung .... 8',
        '2 Analyse',
        'Analyseabschnitt mit ausreichend Fließtext.',
        '2.1 Kontext',
        'Kontextabschnitt mit Detailinformationen.',
        '3 Methode',
        'Methodenabschnitt mit strukturiertem Text.',
        '2.2 Vertiefung',
        'Vertiefungsabschnitt, der zur Analyse gehört.',
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

    $results = AbaAnalysisResult::query()
        ->where('aba_analysis_run_id', $run->id)
        ->orderBy('sort_order')
        ->get();

    $chapterTwo = $results->firstWhere('section_title', '2 Analyse');
    $chapterThree = $results->firstWhere('section_title', '3 Methode');
    $subTwoTwo = $results->firstWhere('section_title', '2.2 Vertiefung');

    expect($chapterTwo)->not->toBeNull()
        ->and($chapterThree)->not->toBeNull()
        ->and($subTwoTwo)->not->toBeNull()
        ->and((int) ($subTwoTwo->parent_result_id ?? 0))->toBe((int) ($chapterTwo->id ?? 0))
        ->and((int) ($subTwoTwo->parent_result_id ?? 0))->not->toBe((int) ($chapterThree->id ?? 0));
});

test('missing intermediate heading level is flattened conservatively instead of forcing a brittle jump', function () {
    $user = createAbaTeacher($this->school, $this->schoolyear);
    $aba = Aba::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $user->id,
    ]);

    $mainAttachment = createMainDocumentAttachment($aba, 'hauptdokument.txt', implode("\n", [
        'Titelblatt',
        'Abstract',
        'Kurzfassung',
        'Inhaltsverzeichnis',
        '1 Analyse .... 3',
        '1.1.1 Erhebung .... 5',
        '1 Analyse',
        'Analyseabschnitt mit Einordnung.',
        '1.1.1 Erhebung',
        'Detailbeschreibung zur Erhebung mit inhaltlicher Tiefe.',
        '2 Ergebnisse',
        'Ergebnisabschnitt mit Zusammenfassung.',
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

    $results = AbaAnalysisResult::query()
        ->where('aba_analysis_run_id', $run->id)
        ->orderBy('sort_order')
        ->get();

    $chapter = $results->firstWhere('section_title', '1 Analyse');
    $deepSub = $results->firstWhere('section_title', '1.1.1 Erhebung');

    expect($chapter)->not->toBeNull()
        ->and($deepSub)->not->toBeNull()
        ->and((int) ($deepSub->parent_result_id ?? 0))->toBe((int) ($chapter->id ?? 0))
        ->and((int) ($deepSub->hierarchy_level ?? 0))->toBe(2);
});

test('subchapter after bibliography boundary does not inherit a parent across structural boundary', function () {
    $user = createAbaTeacher($this->school, $this->schoolyear);
    $aba = Aba::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $user->id,
    ]);

    $mainAttachment = createMainDocumentAttachment($aba, 'hauptdokument.txt', implode("\n", [
        'Titelblatt',
        'Abstract',
        'Kurzfassung',
        'Inhaltsverzeichnis',
        '2 Methode .... 6',
        '2.1 Datengrundlage .... 7',
        '2 Methode',
        'Methodenabschnitt mit Fließtext.',
        'Literaturverzeichnis',
        '1. Mustermann, M. (2020). Quelle.',
        '2.1 Datengrundlage',
        'Datengrundlage mit inhaltlicher Beschreibung.',
        '3 Fazit',
        'Fazitabschnitt mit Schlussfolgerung.',
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

    $results = AbaAnalysisResult::query()
        ->where('aba_analysis_run_id', $run->id)
        ->orderBy('sort_order')
        ->get();

    $subchapter = $results->firstWhere('section_title', '2.1 Datengrundlage');

    expect($subchapter)->not->toBeNull()
        ->and($subchapter->parent_result_id)->toBeNull();
});

test('subchapter does not attach to an unnumbered other section when a chapter ancestor exists', function () {
    $user = createAbaTeacher($this->school, $this->schoolyear);
    $aba = Aba::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $user->id,
    ]);

    $mainAttachment = createMainDocumentAttachment($aba, 'hauptdokument.txt', implode("\n", [
        'Titelblatt',
        'Abstract',
        'Kurzfassung',
        'Inhaltsverzeichnis',
        '1 Analyse .... 3',
        'Theoretischer Rahmen .... 4',
        '1.1 Detail .... 5',
        '1 Analyse',
        'Analyseabschnitt mit Kontext.',
        'Theoretischer Rahmen',
        'Unnummerierter Abschnitt mit genügend Fließtext für eine robuste Erkennung.',
        '1.1 Detail',
        'Detailabschnitt mit Unterkapitelinhalt.',
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

    $results = AbaAnalysisResult::query()
        ->where('aba_analysis_run_id', $run->id)
        ->orderBy('sort_order')
        ->get();

    $chapter = $results->firstWhere('section_title', '1 Analyse');
    $otherSection = $results->firstWhere('section_title', 'Theoretischer Rahmen');
    $subchapter = $results->firstWhere('section_title', '1.1 Detail');

    expect($chapter)->not->toBeNull()
        ->and($otherSection)->not->toBeNull()
        ->and($subchapter)->not->toBeNull()
        ->and((string) ($otherSection->section_type ?? ''))->toBe('other_section')
        ->and((int) ($subchapter->parent_result_id ?? 0))->toBe((int) ($chapter->id ?? 0))
        ->and((int) ($subchapter->parent_result_id ?? 0))->not->toBe((int) ($otherSection->id ?? 0));
});

test('hierarchy confidence is higher for numbering-compatible parents than numbering-mismatched parents', function () {
    config()->set('aba_analysis.openai_normalization_enabled', false);

    $user = createAbaTeacher($this->school, $this->schoolyear);
    $analyze = function (string $fileName, array $lines) use ($user): array {
        $aba = Aba::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $user->id,
        ]);

        $attachment = createMainDocumentAttachment($aba, $fileName, implode("\n", $lines));
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
        $summary = is_array($run->summary) ? $run->summary : [];
        $stats = is_array($summary['analysis_stats'] ?? null) ? $summary['analysis_stats'] : [];

        return ['run' => $run, 'stats' => $stats];
    };

    $compatible = $analyze('hierarchy-compatible.txt', [
        'Titelblatt',
        'Abstract',
        'Kurzfassung',
        'Inhaltsverzeichnis',
        '4 Thema .... 3',
        '4.1 Grundlagen .... 4',
        '4.1.2 Merkmale .... 5',
        '4 Thema',
        'Kontext zum Thema.',
        '4.1 Grundlagen',
        'Grundlagentext.',
        '4.1.2 Merkmale',
        'Vertiefender Detailtext.',
    ]);

    $mismatched = $analyze('hierarchy-mismatched.txt', [
        'Titelblatt',
        'Abstract',
        'Kurzfassung',
        'Inhaltsverzeichnis',
        '4 Thema .... 3',
        '4.1.1 Definition .... 4',
        '4.1.2 Merkmale .... 5',
        '4 Thema',
        'Kontext zum Thema.',
        '4.1.1 Definition',
        'Definitionstext.',
        '4.1.2 Merkmale',
        'Vertiefender Detailtext.',
    ]);

    $compatibleScore = (float) ($compatible['stats']['hierarchy_confidence'] ?? 0.0);
    $mismatchedScore = (float) ($mismatched['stats']['hierarchy_confidence'] ?? 0.0);
    $compatibleMismatchCount = (int) ($compatible['stats']['hierarchy_numbering_mismatch_count'] ?? 0);
    $mismatchedMismatchCount = (int) ($mismatched['stats']['hierarchy_numbering_mismatch_count'] ?? 0);

    expect($compatibleScore)->toBeGreaterThan($mismatchedScore)
        ->and($mismatchedMismatchCount)->toBeGreaterThan($compatibleMismatchCount);
});

test('hierarchy confidence decreases when links become uncertain via boundary detachment', function () {
    config()->set('aba_analysis.openai_normalization_enabled', false);

    $user = createAbaTeacher($this->school, $this->schoolyear);
    $analyze = function (string $fileName, array $lines) use ($user): array {
        $aba = Aba::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $user->id,
        ]);

        $attachment = createMainDocumentAttachment($aba, $fileName, implode("\n", $lines));
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
        $summary = is_array($run->summary) ? $run->summary : [];
        $stats = is_array($summary['analysis_stats'] ?? null) ? $summary['analysis_stats'] : [];

        return ['run' => $run, 'stats' => $stats];
    };

    $confident = $analyze('hierarchy-boundary-clean.txt', [
        'Titelblatt',
        'Abstract',
        'Kurzfassung',
        'Inhaltsverzeichnis',
        '2 Methode .... 3',
        '2.1 Datengrundlage .... 4',
        '2 Methode',
        'Methodiktext.',
        '2.1 Datengrundlage',
        'Datengrundlagentext.',
    ]);

    $detached = $analyze('hierarchy-boundary-detached.txt', [
        'Titelblatt',
        'Abstract',
        'Kurzfassung',
        'Inhaltsverzeichnis',
        '2 Methode .... 3',
        '2.1 Datengrundlage .... 4',
        '2 Methode',
        'Methodiktext.',
        'Literaturverzeichnis',
        '1. Mustermann, M. (2020). Quelle.',
        '2.1 Datengrundlage',
        'Datengrundlagentext.',
    ]);

    $confidentScore = (float) ($confident['stats']['hierarchy_confidence'] ?? 0.0);
    $detachedScore = (float) ($detached['stats']['hierarchy_confidence'] ?? 0.0);
    $detachedMissingParentCount = (int) ($detached['stats']['hierarchy_missing_parent_count'] ?? 0);

    expect($confidentScore)->toBeGreaterThan($detachedScore)
        ->and($detachedMissingParentCount)->toBeGreaterThanOrEqual(1);
});

test('hierarchy confidence drops when missing intermediate numbering triggers flattening', function () {
    config()->set('aba_analysis.openai_normalization_enabled', false);

    $user = createAbaTeacher($this->school, $this->schoolyear);
    $analyze = function (string $fileName, array $lines) use ($user): array {
        $aba = Aba::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $user->id,
        ]);

        $attachment = createMainDocumentAttachment($aba, $fileName, implode("\n", $lines));
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
        $summary = is_array($run->summary) ? $run->summary : [];
        $stats = is_array($summary['analysis_stats'] ?? null) ? $summary['analysis_stats'] : [];

        return ['run' => $run, 'stats' => $stats];
    };

    $clean = $analyze('hierarchy-chain-clean.txt', [
        'Titelblatt',
        'Abstract',
        'Kurzfassung',
        'Inhaltsverzeichnis',
        '4 Fake News .... 3',
        '4.1 Grundlagen .... 4',
        '4.1.1 Definition .... 5',
        '4.1.2 Merkmale .... 6',
        '4 Fake News',
        'Kapiteltext.',
        '4.1 Grundlagen',
        'Grundlagentext.',
        '4.1.1 Definition',
        'Definitionstext.',
        '4.1.2 Merkmale',
        'Merkmaltext.',
    ]);

    $flattened = $analyze('hierarchy-chain-flattened.txt', [
        'Titelblatt',
        'Abstract',
        'Kurzfassung',
        'Inhaltsverzeichnis',
        '4 Fake News .... 3',
        '4.1.1 Definition .... 5',
        '4.1.2 Merkmale .... 6',
        '4 Fake News',
        'Kapiteltext.',
        '4.1.1 Definition',
        'Definitionstext.',
        '4.1.2 Merkmale',
        'Merkmaltext.',
    ]);

    $cleanScore = (float) ($clean['stats']['hierarchy_confidence'] ?? 0.0);
    $flattenedScore = (float) ($flattened['stats']['hierarchy_confidence'] ?? 0.0);
    $flattenedCount = (int) ($flattened['stats']['hierarchy_flattened_count'] ?? 0);

    expect($cleanScore)->toBeGreaterThan($flattenedScore)
        ->and($flattenedCount)->toBeGreaterThanOrEqual(1);
});

test('mixed numbered and unnumbered headings preserve chapter subchapter hierarchy', function () {
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
        ['text' => '1 Grundlagen .... 4', 'style' => 'TOC1'],
        ['text' => 'Begriffe und Rahmen .... 5', 'style' => 'TOC1'],
        ['text' => '1.1 Begriffe .... 6', 'style' => 'TOC1'],
        ['text' => '2 Methode .... 8', 'style' => 'TOC1'],
        ['text' => '1 Grundlagen', 'style' => 'Heading1', 'outline' => 0],
        ['text' => 'Einordnung und Zielsetzung des Kapitels.', 'style' => 'Normal'],
        ['text' => 'Begriffe und Rahmen', 'style' => 'Heading2', 'outline' => 1],
        ['text' => 'Kontext für zentrale Begriffe und deren Abgrenzung.', 'style' => 'Normal'],
        ['text' => '1.1 Begriffe', 'style' => 'Heading2', 'outline' => 1],
        ['text' => 'Vertiefende Definitionen und Beispiele.', 'style' => 'Normal'],
        ['text' => '2 Methode', 'style' => 'Heading1', 'outline' => 0],
        ['text' => 'Beschreibung der methodischen Vorgehensweise.', 'style' => 'Normal'],
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

    $chapter = $results->firstWhere('section_title', '1 Grundlagen');
    $unnumberedSub = $results->firstWhere('section_title', 'Begriffe und Rahmen');
    $numberedSub = $results->firstWhere('section_title', '1.1 Begriffe');

    expect($chapter)->not->toBeNull()
        ->and($unnumberedSub)->not->toBeNull()
        ->and($numberedSub)->not->toBeNull()
        ->and((string) ($unnumberedSub->section_type ?? ''))->toBe('subchapter')
        ->and((int) ($unnumberedSub->parent_result_id ?? 0))->toBe((int) ($chapter->id ?? 0))
        ->and((int) ($numberedSub->parent_result_id ?? 0))->toBe((int) ($chapter->id ?? 0));
});

test('inconsistent heading typography remains classifiable when multiple hierarchy cues align', function () {
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
        ['text' => '1 Analyse .... 4', 'style' => 'TOC1'],
        ['text' => '1.1 Kontext und Grenzen .... 5', 'style' => 'TOC1'],
        ['text' => '1.2 Methodische Grenzen .... 6', 'style' => 'TOC1'],
        ['text' => '1 Analyse', 'style' => 'Heading1', 'outline' => 0, 'align' => 'center', 'bold' => true, 'font_size_half_points' => 30],
        ['text' => 'Einführung in Analyse und Untersuchungsrahmen.', 'style' => 'Normal'],
        ['text' => '1.1 Kontext und Grenzen', 'style' => 'Heading2', 'outline' => 1, 'align' => 'left', 'indent_left' => 360, 'spacing_before' => 240, 'spacing_after' => 80, 'bold' => false, 'font_size_half_points' => 20],
        ['text' => 'Beschreibung des Kontexts sowie der inhaltlichen Grenzen.', 'style' => 'Normal'],
        ['text' => '1.2 Methodische Grenzen', 'style' => 'Heading2', 'outline' => 1, 'align' => 'left', 'indent_left' => 360, 'spacing_before' => 240, 'spacing_after' => 80, 'bold' => false, 'font_size_half_points' => 20],
        ['text' => 'Methodische Limitationen und potentielle Verzerrungen.', 'style' => 'Normal'],
        ['text' => '2 Ergebnisse', 'style' => 'Heading1', 'outline' => 0],
        ['text' => 'Darstellung der wichtigsten Ergebnisse.', 'style' => 'Normal'],
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

    $chapter = $results->firstWhere('section_title', '1 Analyse');
    $subA = $results->firstWhere('section_title', '1.1 Kontext und Grenzen');
    $subB = $results->firstWhere('section_title', '1.2 Methodische Grenzen');

    expect($chapter)->not->toBeNull()
        ->and($subA)->not->toBeNull()
        ->and($subB)->not->toBeNull()
        ->and((string) ($subA->section_type ?? ''))->toBe('subchapter')
        ->and((string) ($subB->section_type ?? ''))->toBe('subchapter')
        ->and((int) ($subA->parent_result_id ?? 0))->toBe((int) ($chapter->id ?? 0))
        ->and((int) ($subB->parent_result_id ?? 0))->toBe((int) ($chapter->id ?? 0));
});

test('bold figure captions that look like headings are not accepted as structural headings', function () {
    if (! class_exists(ZipArchive::class)) {
        $this->markTestSkipped('ZipArchive extension missing.');
    }

    $user = createAbaTeacher($this->school, $this->schoolyear);
    $aba = Aba::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $user->id,
    ]);

    $captionTitle = 'Abb. 1: Nutzung sozialer Medien im Vergleich';
    $mainAttachment = createMainDocxAttachment($aba, 'hauptdokument.docx', [
        ['text' => 'ABA-Arbeit', 'style' => 'Normal'],
        ['text' => 'Inhaltsverzeichnis', 'style' => 'Heading1', 'outline' => 0],
        ['text' => '1 Analyse .... 4', 'style' => 'TOC1'],
        ['text' => '2 Ergebnisse .... 6', 'style' => 'TOC1'],
        ['text' => '1 Analyse', 'style' => 'Heading1', 'outline' => 0],
        ['text' => 'Kurzer Kontext zur folgenden Darstellung.', 'style' => 'Normal'],
        ['text' => $captionTitle, 'style' => 'Heading2', 'outline' => 1, 'bold' => true, 'font_size_half_points' => 24],
        ['text' => 'Quelle: Eigene Darstellung', 'style' => 'Normal'],
        ['text' => '2 Ergebnisse', 'style' => 'Heading1', 'outline' => 0],
        ['text' => 'Einordnung der Ergebnisse in den Gesamtkontext.', 'style' => 'Normal'],
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

    $captionSections = $results->where('section_title', $captionTitle);

    expect($captionSections->where('section_type', 'figure')->count())->toBe(1)
        ->and($captionSections->where('section_type', '!=', 'figure')->count())->toBe(0)
        ->and($results->firstWhere('section_title', '2 Ergebnisse'))->not->toBeNull();
});

test('bibliography entries with heading like numbering are not promoted to structural headings', function () {
    if (! class_exists(ZipArchive::class)) {
        $this->markTestSkipped('ZipArchive extension missing.');
    }

    $user = createAbaTeacher($this->school, $this->schoolyear);
    $aba = Aba::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $user->id,
    ]);

    $entryOne = '1. Mustermann, M. (2020). Medienethik. Verlagshaus.';
    $entryTwo = '[2] Beispiel, A. (2021). Plattformvergleich. https://example.org';
    $mainAttachment = createMainDocxAttachment($aba, 'hauptdokument.docx', [
        ['text' => 'ABA-Arbeit', 'style' => 'Normal'],
        ['text' => 'Inhaltsverzeichnis', 'style' => 'Heading1', 'outline' => 0],
        ['text' => '1 Einleitung .... 4', 'style' => 'TOC1'],
        ['text' => 'Literaturverzeichnis .... 28', 'style' => 'TOC1'],
        ['text' => '1 Einleitung', 'style' => 'Heading1', 'outline' => 0],
        ['text' => 'Einleitender Abschnitt.', 'style' => 'Normal'],
        ['text' => 'Literaturverzeichnis', 'style' => 'Heading1', 'outline' => 0],
        ['text' => $entryOne, 'style' => 'Normal'],
        ['text' => $entryTwo, 'style' => 'Normal'],
        ['text' => 'Anhang', 'style' => 'Heading1', 'outline' => 0],
        ['text' => 'Zusätzliche Materialien.', 'style' => 'Normal'],
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
    $titles = $results->pluck('section_title')->filter()->values()->all();

    expect($bibliography)->not->toBeNull()
        ->and($titles)->not->toContain($entryOne)
        ->and($titles)->not->toContain($entryTwo);
});

test('toc pages that visually resemble body heading pages remain isolated until body reentry', function () {
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
        ['text' => '1 Einleitung 4', 'style' => 'Heading1', 'outline' => 0],
        ['text' => '1.1 Zielsetzung 5', 'style' => 'Heading2', 'outline' => 1],
        ['text' => '2 Methode 8', 'style' => 'Heading1', 'outline' => 0],
        ['text' => '1 Einleitung', 'style' => 'Heading1', 'outline' => 0],
        ['text' => 'Einleitender Fließtext nach dem Inhaltsverzeichnis.', 'style' => 'Normal'],
        ['text' => '1.1 Zielsetzung', 'style' => 'Heading2', 'outline' => 1],
        ['text' => 'Ziele und erwartete Ergebnisse der Arbeit.', 'style' => 'Normal'],
        ['text' => '2 Methode', 'style' => 'Heading1', 'outline' => 0],
        ['text' => 'Methodischer Teil mit Vorgehensbeschreibung.', 'style' => 'Normal'],
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
    $chapter = $results->firstWhere('section_title', '1 Einleitung');
    $titles = $results->pluck('section_title')->filter()->values()->all();

    expect($toc)->not->toBeNull()
        ->and((string) ($toc->extracted_text ?? ''))->toContain('1 Einleitung 4')
        ->and($chapter)->not->toBeNull()
        ->and((int) ($chapter->start_line ?? 0))->toBeGreaterThan((int) ($toc->end_line ?? 0))
        ->and($titles)->not->toContain('1 Einleitung 4')
        ->and($titles)->not->toContain('1.1 Zielsetzung 5');
});

test('chapter followed by 1.1 and 1.2 keeps both numbered subchapters', function () {
    if (! class_exists(ZipArchive::class)) {
        $this->markTestSkipped('ZipArchive extension missing.');
    }

    $user = createAbaTeacher($this->school, $this->schoolyear);
    $aba = Aba::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $user->id,
    ]);

    $mainAttachment = createMainDocxAttachment($aba, 'subchapter-sequence.docx', [
        ['text' => 'Titelblatt', 'style' => 'Normal'],
        ['text' => '1. EINLEITUNG', 'style' => 'Heading1', 'outline' => 0],
        ['text' => '1.1 Wer ist Bong Joon-ho?', 'style' => 'Heading1', 'outline' => 0],
        ['text' => 'Einleitender Absatz zur Person und zur Themenwahl.', 'style' => 'Normal'],
        ['text' => '1.2 Warum als Videobeitrag?', 'style' => 'Heading1', 'outline' => 0],
        ['text' => 'Begründung zur gewählten Beitragsform.', 'style' => 'Normal'],
        ['text' => '2. AUSBLICK', 'style' => 'Heading1', 'outline' => 0],
        ['text' => 'Kurzer Ausblick.', 'style' => 'Normal'],
    ]);

    $run = AbaAnalysisRun::query()->create([
        'aba_id' => $aba->id,
        'aba_attachment_id' => $mainAttachment->id,
        'created_by_user_id' => $user->id,
        'status' => AbaAnalysisRun::STATUS_STARTED,
        'status_message' => 'Analyselauf wurde gestartet.',
        'source_original_name' => 'subchapter-sequence.docx',
        'source_path' => $mainAttachment->path,
        'source_mime_type' => $mainAttachment->mime_type,
        'started_at' => now(),
    ]);

    app(AbaAnalysisService::class)->processRun($run->id);
    $run->refresh();

    $results = AbaAnalysisResult::query()
        ->where('aba_analysis_run_id', $run->id)
        ->orderBy('sort_order')
        ->get();

    $firstSub = $results->firstWhere('section_title', '1.1 Wer ist Bong Joon-ho?');
    $secondSub = $results->firstWhere('section_title', '1.2 Warum als Videobeitrag?');

    expect($firstSub)->not->toBeNull()
        ->and($secondSub)->not->toBeNull()
        ->and((string) ($firstSub->section_type ?? ''))->toBe('subchapter')
        ->and((string) ($secondSub->section_type ?? ''))->toBe('subchapter')
        ->and((int) ($firstSub->sort_order ?? 0))->toBeLessThan((int) ($secondSub->sort_order ?? 0));
});

test('first subchapter is not dropped when body starts with a question sentence', function () {
    if (! class_exists(ZipArchive::class)) {
        $this->markTestSkipped('ZipArchive extension missing.');
    }

    $user = createAbaTeacher($this->school, $this->schoolyear);
    $aba = Aba::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $user->id,
    ]);

    $mainAttachment = createMainDocxAttachment($aba, 'subchapter-question-body.docx', [
        ['text' => 'INHALTSVERZEICHNIS', 'style' => 'Heading1', 'outline' => 0],
        ['text' => '1. EINLEITUNG', 'style' => 'Heading1', 'outline' => 0],
        ['text' => '1.1 Wer ist Bong Joon-ho?', 'style' => 'Heading1', 'outline' => 0],
        ['text' => 'Wieso ich mich für Bong Joon-ho entschieden habe?', 'style' => 'Normal'],
        ['text' => 'Er gehört zu einem meiner Lieblingsregisseure und hat meinen Blick auf Film verändert.', 'style' => 'Normal'],
        ['text' => 'Zusätzlicher Absatz zur Begründung der Themenwahl.', 'style' => 'Normal'],
        ['text' => '1.2 Warum als Videobeitrag?', 'style' => 'Heading1', 'outline' => 0],
        ['text' => 'Weshalb ich mich gegen eine VWA entschieden habe?', 'style' => 'Normal'],
        ['text' => 'Der Videobeitrag eignet sich für mein Thema besser als eine rein schriftliche Darstellung.', 'style' => 'Normal'],
    ]);

    $run = AbaAnalysisRun::query()->create([
        'aba_id' => $aba->id,
        'aba_attachment_id' => $mainAttachment->id,
        'created_by_user_id' => $user->id,
        'status' => AbaAnalysisRun::STATUS_STARTED,
        'status_message' => 'Analyselauf wurde gestartet.',
        'source_original_name' => 'subchapter-question-body.docx',
        'source_path' => $mainAttachment->path,
        'source_mime_type' => $mainAttachment->mime_type,
        'started_at' => now(),
    ]);

    app(AbaAnalysisService::class)->processRun($run->id);
    $run->refresh();

    $results = AbaAnalysisResult::query()
        ->where('aba_analysis_run_id', $run->id)
        ->orderBy('sort_order')
        ->get();

    $titles = $results->pluck('section_title')->filter()->values()->all();
    $firstSub = $results->firstWhere('section_title', '1.1 Wer ist Bong Joon-ho?');
    $secondSub = $results->firstWhere('section_title', '1.2 Warum als Videobeitrag?');

    expect($titles)->toContain('1.1 Wer ist Bong Joon-ho?')
        ->and($titles)->toContain('1.2 Warum als Videobeitrag?')
        ->and($firstSub)->not->toBeNull()
        ->and($secondSub)->not->toBeNull();
});

test('adjacent numbered subchapters with similar typography are both preserved', function () {
    if (! class_exists(ZipArchive::class)) {
        $this->markTestSkipped('ZipArchive extension missing.');
    }

    $user = createAbaTeacher($this->school, $this->schoolyear);
    $aba = Aba::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $user->id,
    ]);

    $mainAttachment = createMainDocxAttachment($aba, 'adjacent-subchapters.docx', [
        ['text' => '1. EINLEITUNG', 'style' => 'Heading1', 'outline' => 0, 'bold' => true, 'font_size_half_points' => 26],
        ['text' => '1.1 Wer ist Bong Joon-ho?', 'style' => 'Heading1', 'outline' => 0, 'bold' => true, 'font_size_half_points' => 24],
        ['text' => 'Kurze Einordnung der Person.', 'style' => 'Normal'],
        ['text' => '1.2 Warum als Videobeitrag?', 'style' => 'Heading1', 'outline' => 0, 'bold' => true, 'font_size_half_points' => 24],
        ['text' => 'Kurze Einordnung zur Form der Arbeit.', 'style' => 'Normal'],
    ]);

    $run = AbaAnalysisRun::query()->create([
        'aba_id' => $aba->id,
        'aba_attachment_id' => $mainAttachment->id,
        'created_by_user_id' => $user->id,
        'status' => AbaAnalysisRun::STATUS_STARTED,
        'status_message' => 'Analyselauf wurde gestartet.',
        'source_original_name' => 'adjacent-subchapters.docx',
        'source_path' => $mainAttachment->path,
        'source_mime_type' => $mainAttachment->mime_type,
        'started_at' => now(),
    ]);

    app(AbaAnalysisService::class)->processRun($run->id);
    $run->refresh();

    $results = AbaAnalysisResult::query()
        ->where('aba_analysis_run_id', $run->id)
        ->orderBy('sort_order')
        ->get();

    expect($results->firstWhere('section_title', '1.1 Wer ist Bong Joon-ho?'))->not->toBeNull()
        ->and($results->firstWhere('section_title', '1.2 Warum als Videobeitrag?'))->not->toBeNull();
});

test('toc duplicate subchapter entries stay isolated and body hierarchy keeps real 1.1 and 1.2', function () {
    if (! class_exists(ZipArchive::class)) {
        $this->markTestSkipped('ZipArchive extension missing.');
    }

    $user = createAbaTeacher($this->school, $this->schoolyear);
    $aba = Aba::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $user->id,
    ]);

    $mainAttachment = createMainDocxAttachment($aba, 'toc-body-duplicate-subchapters.docx', [
        ['text' => 'INHALTSVERZEICHNIS', 'style' => 'Heading1', 'outline' => 0],
        ['text' => '1. EINLEITUNG .... 3', 'style' => 'TOC1'],
        ['text' => '1.1 Wer ist Bong Joon-ho? .... 4', 'style' => 'TOC1'],
        ['text' => '1.2 Warum als Videobeitrag? .... 5', 'style' => 'TOC1'],
        ['page_break' => true],
        ['text' => '1. EINLEITUNG', 'style' => 'Heading1', 'outline' => 0],
        ['text' => '1.1 Wer ist Bong Joon-ho?', 'style' => 'Heading1', 'outline' => 0],
        ['text' => 'Wieso ich mich für Bong Joon-ho entschieden habe?', 'style' => 'Normal'],
        ['text' => 'Einleitender Absatz zur Person und zur Themenwahl.', 'style' => 'Normal'],
        ['text' => '1.2 Warum als Videobeitrag?', 'style' => 'Heading1', 'outline' => 0],
        ['text' => 'Begründung zur gewählten Beitragsform.', 'style' => 'Normal'],
    ]);

    $run = AbaAnalysisRun::query()->create([
        'aba_id' => $aba->id,
        'aba_attachment_id' => $mainAttachment->id,
        'created_by_user_id' => $user->id,
        'status' => AbaAnalysisRun::STATUS_STARTED,
        'status_message' => 'Analyselauf wurde gestartet.',
        'source_original_name' => 'toc-body-duplicate-subchapters.docx',
        'source_path' => $mainAttachment->path,
        'source_mime_type' => $mainAttachment->mime_type,
        'started_at' => now(),
    ]);

    app(AbaAnalysisService::class)->processRun($run->id);
    $run->refresh();

    $results = AbaAnalysisResult::query()
        ->where('aba_analysis_run_id', $run->id)
        ->orderBy('sort_order')
        ->get();

    $toc = $results->firstWhere('section_type', 'table_of_contents');
    $firstSub = $results->firstWhere('section_title', '1.1 Wer ist Bong Joon-ho?');
    $secondSub = $results->firstWhere('section_title', '1.2 Warum als Videobeitrag?');
    $titles = $results->pluck('section_title')->filter()->values()->all();

    expect($toc)->not->toBeNull()
        ->and((string) ($toc->extracted_text ?? ''))->toContain('1.1 Wer ist Bong Joon-ho? .... 4')
        ->and((string) ($toc->extracted_text ?? ''))->toContain('1.2 Warum als Videobeitrag? .... 5')
        ->and((string) ($toc->extracted_text ?? ''))->not->toContain('Wieso ich mich für Bong Joon-ho entschieden habe?')
        ->and($firstSub)->not->toBeNull()
        ->and($secondSub)->not->toBeNull()
        ->and((string) ($firstSub->section_type ?? ''))->toBe('subchapter')
        ->and((string) ($secondSub->section_type ?? ''))->toBe('subchapter')
        ->and((int) ($firstSub->start_line ?? 0))->toBeGreaterThan((int) ($toc->end_line ?? 0))
        ->and((int) ($secondSub->start_line ?? 0))->toBeGreaterThan((int) ($toc->end_line ?? 0))
        ->and($titles)->not->toContain('1.1 Wer ist Bong Joon-ho? .... 4')
        ->and($titles)->not->toContain('1.2 Warum als Videobeitrag? .... 5');
});

test('minimal toc heading does not absorb following body question line', function () {
    if (! class_exists(ZipArchive::class)) {
        $this->markTestSkipped('ZipArchive extension missing.');
    }

    $user = createAbaTeacher($this->school, $this->schoolyear);
    $aba = Aba::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $user->id,
    ]);

    $mainAttachment = createMainDocxAttachment($aba, 'minimal-toc-heading-body-reentry.docx', [
        ['text' => 'INHALTSVERZEICHNIS', 'style' => 'Heading1', 'outline' => 0],
        ['text' => '1. EINLEITUNG', 'style' => 'Heading1', 'outline' => 0],
        ['text' => '1.1 Wer ist Bong Joon-ho?', 'style' => 'Heading1', 'outline' => 0],
        ['text' => 'Wieso ich mich für Bong Joon-ho entschieden habe?', 'style' => 'Normal'],
        ['text' => 'Einleitender Absatz zur Person und zur Themenwahl.', 'style' => 'Normal'],
        ['text' => '1.2 Warum als Videobeitrag?', 'style' => 'Heading1', 'outline' => 0],
        ['text' => 'Begründung zur gewählten Beitragsform.', 'style' => 'Normal'],
    ]);

    $run = AbaAnalysisRun::query()->create([
        'aba_id' => $aba->id,
        'aba_attachment_id' => $mainAttachment->id,
        'created_by_user_id' => $user->id,
        'status' => AbaAnalysisRun::STATUS_STARTED,
        'status_message' => 'Analyselauf wurde gestartet.',
        'source_original_name' => 'minimal-toc-heading-body-reentry.docx',
        'source_path' => $mainAttachment->path,
        'source_mime_type' => $mainAttachment->mime_type,
        'started_at' => now(),
    ]);

    app(AbaAnalysisService::class)->processRun($run->id);
    $run->refresh();

    $results = AbaAnalysisResult::query()
        ->where('aba_analysis_run_id', $run->id)
        ->orderBy('sort_order')
        ->get();

    $toc = $results->firstWhere('section_type', 'table_of_contents');
    $firstSub = $results->firstWhere('section_title', '1.1 Wer ist Bong Joon-ho?');
    $secondSub = $results->firstWhere('section_title', '1.2 Warum als Videobeitrag?');

    expect($toc)->not->toBeNull()
        ->and((string) ($toc->extracted_text ?? ''))->toContain('INHALTSVERZEICHNIS')
        ->and((string) ($toc->extracted_text ?? ''))->not->toContain('Wieso ich mich für Bong Joon-ho entschieden habe?')
        ->and($firstSub)->not->toBeNull()
        ->and($secondSub)->not->toBeNull()
        ->and((string) ($firstSub->section_type ?? ''))->toBe('subchapter')
        ->and((string) ($secondSub->section_type ?? ''))->toBe('subchapter')
        ->and((int) ($firstSub->start_line ?? 0))->toBeGreaterThan((int) ($toc->end_line ?? 0))
        ->and((int) ($secondSub->start_line ?? 0))->toBeGreaterThan((int) ($toc->end_line ?? 0));
});

test('docx toc entries inside content controls are extracted completely and stay isolated', function () {
    if (! class_exists(ZipArchive::class)) {
        $this->markTestSkipped('ZipArchive extension missing.');
    }

    $user = createAbaTeacher($this->school, $this->schoolyear);
    $aba = Aba::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $user->id,
    ]);

    $tocSdtXml = '<w:sdt><w:sdtContent>'
        .'<w:p><w:pPr><w:pStyle w:val="Verzeichnis1"/></w:pPr><w:r><w:t>1. EINLEITUNG .... 1</w:t></w:r></w:p>'
        .'<w:p><w:pPr><w:pStyle w:val="Verzeichnis1"/></w:pPr><w:r><w:t>2. METHODE .... 2</w:t></w:r></w:p>'
        .'</w:sdtContent></w:sdt>';

    $mainAttachment = createMainDocxAttachment($aba, 'toc-in-sdt.docx', [
        ['text' => 'INHALTSVERZEICHNIS', 'style' => 'Heading1', 'outline' => 0],
        ['raw_xml' => $tocSdtXml],
        ['page_break' => true],
        ['text' => '1. EINLEITUNG', 'style' => 'Heading1', 'outline' => 0],
        ['text' => 'Einleitender Abschnitt mit Kontext und Zielsetzung.', 'style' => 'Normal'],
        ['text' => '2. METHODE', 'style' => 'Heading1', 'outline' => 0],
        ['text' => 'Methodischer Abschnitt mit Vorgehensbeschreibung.', 'style' => 'Normal'],
    ]);

    $run = AbaAnalysisRun::query()->create([
        'aba_id' => $aba->id,
        'aba_attachment_id' => $mainAttachment->id,
        'created_by_user_id' => $user->id,
        'status' => AbaAnalysisRun::STATUS_STARTED,
        'status_message' => 'Analyselauf wurde gestartet.',
        'source_original_name' => 'toc-in-sdt.docx',
        'source_path' => $mainAttachment->path,
        'source_mime_type' => $mainAttachment->mime_type,
        'started_at' => now(),
    ]);

    app(AbaAnalysisService::class)->processRun($run->id);
    $run->refresh();

    $results = AbaAnalysisResult::query()
        ->where('aba_analysis_run_id', $run->id)
        ->orderBy('sort_order')
        ->get();

    $toc = $results->firstWhere('section_type', 'table_of_contents');
    $titles = $results->pluck('section_title')->filter()->values()->all();

    expect($toc)->not->toBeNull()
        ->and((string) ($toc->extracted_text ?? ''))->toContain('1. EINLEITUNG .... 1')
        ->and((string) ($toc->extracted_text ?? ''))->toContain('2. METHODE .... 2')
        ->and((string) ($toc->extracted_text ?? ''))->not->toContain('Einleitender Abschnitt mit Kontext und Zielsetzung.')
        ->and($titles)->toContain('1. EINLEITUNG')
        ->and($titles)->toContain('2. METHODE')
        ->and($titles)->not->toContain('1. EINLEITUNG .... 1')
        ->and($titles)->not->toContain('2. METHODE .... 2');
});

test('docx toc field entries with verzeichnis style remain in toc and body starts at real reentry heading', function () {
    if (! class_exists(ZipArchive::class)) {
        $this->markTestSkipped('ZipArchive extension missing.');
    }

    $user = createAbaTeacher($this->school, $this->schoolyear);
    $aba = Aba::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $user->id,
    ]);

    $tocSdtXml = '<w:sdt><w:sdtContent>'
        .'<w:p><w:pPr><w:pStyle w:val="Verzeichnis1"/></w:pPr><w:r><w:t>1. EINLEITUNG1</w:t></w:r></w:p>'
        .'<w:p><w:pPr><w:pStyle w:val="Verzeichnis1"/></w:pPr><w:r><w:t>1.1 Wer ist Bong Joon-ho?1</w:t></w:r></w:p>'
        .'<w:p><w:pPr><w:pStyle w:val="Verzeichnis1"/></w:pPr><w:r><w:t>1.2 Warum als Videobeitrag?1</w:t></w:r></w:p>'
        .'<w:p><w:pPr><w:pStyle w:val="Verzeichnis1"/></w:pPr><w:r><w:t>2. ÜBERLEGUNGEN ZUR DOKU2</w:t></w:r></w:p>'
        .'<w:p><w:pPr><w:pStyle w:val="Verzeichnis1"/></w:pPr><w:r><w:t>2.1 Struktureller Aufbau2</w:t></w:r></w:p>'
        .'</w:sdtContent></w:sdt>';

    $mainAttachment = createMainDocxAttachment($aba, 'toc-verzeichnis-field.docx', [
        ['text' => 'INHALTSVERZEICHNIS', 'style' => 'Heading1', 'outline' => 0],
        ['raw_xml' => $tocSdtXml],
        ['text' => '1. EINLEITUNG', 'style' => 'Heading1', 'outline' => 0],
        ['text' => '1.1 Wer ist Bong Joon-ho?', 'style' => 'Heading2', 'outline' => 1],
        ['text' => 'Wieso ich mich für Bong Joon-ho entschieden habe?', 'style' => 'Normal'],
        ['text' => '1.2 Warum als Videobeitrag?', 'style' => 'Heading2', 'outline' => 1],
        ['text' => 'Weshalb ich mich gegen eine VWA entschieden habe?', 'style' => 'Normal'],
        ['text' => '2. ÜBERLEGUNGEN ZUR DOKU', 'style' => 'Heading1', 'outline' => 0],
        ['text' => '2.1 Struktureller Aufbau', 'style' => 'Heading2', 'outline' => 1],
        ['text' => 'Die Dokumentation ist in drei Kapitel eingeteilt.', 'style' => 'Normal'],
    ]);

    $run = AbaAnalysisRun::query()->create([
        'aba_id' => $aba->id,
        'aba_attachment_id' => $mainAttachment->id,
        'created_by_user_id' => $user->id,
        'status' => AbaAnalysisRun::STATUS_STARTED,
        'status_message' => 'Analyselauf wurde gestartet.',
        'source_original_name' => 'toc-verzeichnis-field.docx',
        'source_path' => $mainAttachment->path,
        'source_mime_type' => $mainAttachment->mime_type,
        'started_at' => now(),
    ]);

    app(AbaAnalysisService::class)->processRun($run->id);
    $run->refresh();

    $results = AbaAnalysisResult::query()
        ->where('aba_analysis_run_id', $run->id)
        ->orderBy('sort_order')
        ->get();

    $toc = $results->firstWhere('section_type', 'table_of_contents');
    $chapter = $results->firstWhere('section_title', '1. EINLEITUNG');
    $titles = $results->pluck('section_title')->filter()->values()->all();

    expect($toc)->not->toBeNull()
        ->and((string) ($toc->extracted_text ?? ''))->toContain('1. EINLEITUNG1')
        ->and((string) ($toc->extracted_text ?? ''))->toContain('1.1 Wer ist Bong Joon-ho?1')
        ->and((string) ($toc->extracted_text ?? ''))->toContain('2. ÜBERLEGUNGEN ZUR DOKU2')
        ->and((string) ($toc->extracted_text ?? ''))->not->toContain('Wieso ich mich für Bong Joon-ho entschieden habe?')
        ->and($chapter)->not->toBeNull()
        ->and((int) ($chapter->start_line ?? 0))->toBeGreaterThan((int) ($toc->end_line ?? 0))
        ->and($titles)->not->toContain('1.1 Wer ist Bong Joon-ho?1')
        ->and($titles)->not->toContain('2.1 Struktureller Aufbau2');
});

test('prose lines and year-start lines are not promoted to headings and do not truncate subsection spans', function () {
    if (! class_exists(ZipArchive::class)) {
        $this->markTestSkipped('ZipArchive extension missing.');
    }

    $user = createAbaTeacher($this->school, $this->schoolyear);
    $aba = Aba::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $user->id,
    ]);

    $mainAttachment = createMainDocxAttachment($aba, 'section-span-regression.docx', [
        ['text' => '2. ÜBERLEGUNGEN ZUR DOKU', 'style' => 'Heading1', 'outline' => 0],
        ['text' => '2.1 Struktureller Aufbau', 'style' => 'Heading2', 'outline' => 1],
        ['text' => 'Die Dokumentation ist in drei Kapitel eingeteilt und baut inhaltlich aufeinander auf.', 'style' => 'Normal'],
        ['text' => 'Kapitel 2 handelt von seiner Filmografie und soll veranschaulichen, wie sich diese im Laufe der Jahre entwickelt hat.', 'style' => 'Normal'],
        ['text' => 'Die behandelten Filme werden zusammengefasst und mit zusätzlichem Kontext eingeordnet.', 'style' => 'Normal'],
        ['text' => '2.2 Recherche', 'style' => 'Heading2', 'outline' => 1],
        ['text' => 'Rechercheabschnitt mit methodischer Reflexion.', 'style' => 'Normal'],
        ['text' => '3. UMSETZUNG', 'style' => 'Heading1', 'outline' => 0],
        ['text' => '3.1 Kapitel I: Biografie', 'style' => 'Heading2', 'outline' => 1],
        ['text' => '1988 begann er sein Studium an der Yonsei-Universität in Seoul.', 'style' => 'Heading1', 'outline' => 0],
        ['text' => 'Biografische Einordnung und Entwicklungsschritte folgen in diesem Abschnitt.', 'style' => 'Normal'],
        ['text' => '3.2 Kapitel II: Filmografie', 'style' => 'Heading2', 'outline' => 1],
        ['text' => 'The Host', 'style' => 'Normal'],
        ['text' => 'Der Film wird inhaltlich eingeordnet und mit gesellschaftlichen Motiven verknüpft.', 'style' => 'Normal'],
        ['text' => '2013 erschien Bong Joon-hos Sci-Fi-Thriller Snowpiercer.', 'style' => 'Heading1', 'outline' => 0],
        ['text' => 'Auch dieser Film wird im Kontext seiner filmischen Entwicklung analysiert.', 'style' => 'Normal'],
        ['text' => '3.3 Kapitel III: Filmanalyse', 'style' => 'Heading2', 'outline' => 1],
        ['text' => 'Filmische Gestaltungsmittel', 'style' => 'Normal'],
        ['text' => 'Die filmische Analyse vertieft Kameraarbeit, Rauminszenierung und sozialen Kontrast.', 'style' => 'Normal'],
    ]);

    $run = AbaAnalysisRun::query()->create([
        'aba_id' => $aba->id,
        'aba_attachment_id' => $mainAttachment->id,
        'created_by_user_id' => $user->id,
        'status' => AbaAnalysisRun::STATUS_STARTED,
        'status_message' => 'Analyselauf wurde gestartet.',
        'source_original_name' => 'section-span-regression.docx',
        'source_path' => $mainAttachment->path,
        'source_mime_type' => $mainAttachment->mime_type,
        'started_at' => now(),
    ]);

    app(AbaAnalysisService::class)->processRun($run->id);
    $run->refresh();

    $results = AbaAnalysisResult::query()
        ->where('aba_analysis_run_id', $run->id)
        ->orderBy('sort_order')
        ->get();

    $titles = $results->pluck('section_title')->filter()->values()->all();
    $sub21 = $results->firstWhere('section_title', '2.1 Struktureller Aufbau');
    $sub31 = $results->firstWhere('section_title', '3.1 Kapitel I: Biografie');
    $sub32 = $results->firstWhere('section_title', '3.2 Kapitel II: Filmografie');
    $sub33 = $results->firstWhere('section_title', '3.3 Kapitel III: Filmanalyse');

    expect($titles)->not->toContain('Kapitel 2 handelt von seiner Filmografie und soll veranschaulichen, wie sich diese im Laufe der Jahre entwickelt hat.')
        ->and($titles)->not->toContain('1988 begann er sein Studium an der Yonsei-Universität in Seoul.')
        ->and($titles)->not->toContain('2013 erschien Bong Joon-hos Sci-Fi-Thriller Snowpiercer.')
        ->and($titles)->not->toContain('The Host')
        ->and($titles)->not->toContain('Filmische Gestaltungsmittel')
        ->and($sub21)->not->toBeNull()
        ->and((string) ($sub21->extracted_text ?? ''))->toContain('Kapitel 2 handelt von seiner Filmografie')
        ->and((string) ($sub21->extracted_text ?? ''))->toContain('Die behandelten Filme werden zusammengefasst')
        ->and($sub31)->not->toBeNull()
        ->and((string) ($sub31->extracted_text ?? ''))->toContain('1988 begann er sein Studium')
        ->and($sub32)->not->toBeNull()
        ->and((string) ($sub32->extracted_text ?? ''))->toContain('The Host')
        ->and((string) ($sub32->extracted_text ?? ''))->toContain('2013 erschien Bong Joon-hos Sci-Fi-Thriller Snowpiercer.')
        ->and($sub33)->not->toBeNull()
        ->and((string) ($sub33->extracted_text ?? ''))->toContain('Filmische Gestaltungsmittel');
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
            'display_values' => [
                'total_record_count' => 4,
                'text_length' => 1234,
                'text_length_without_spaces' => 1001,
                'document_type' => 'aba',
                'selected_candidate' => 'docx_xml',
                'review_state' => 'auto_approved',
                'auto_approved' => true,
                'auto_approve_confidence_threshold' => 0.82,
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
        ->assertJsonPath('data.analysis_run.analysis_stats.review_state', 'auto_approved')
        ->assertJsonPath('data.analysis_run.display_values.total_record_count', 4)
        ->assertJsonPath('data.analysis_run.display_values.text_length', 1234)
        ->assertJsonPath('data.analysis_run.display_values.text_length_without_spaces', 1001)
        ->assertJsonPath('data.analysis_run.display_values.auto_approve_confidence_threshold', 0.82);
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
        ->toContain('Erneute Analyse')
        ->toContain('restartAnalysis')
        ->toContain('/api/admin/abas/${this.abaId}/analysis')
        ->toContain('Anzahl Seiten (echt gerendert)')
        ->toContain('Zeichen gesamt')
        ->toContain('Zeichen ohne Leerzeichen')
        ->toContain('automatisch freigegeben (ab')
        ->toContain('Sicherheit der Frontmatter-Abgrenzung')
        ->toContain('Korrigierte Datensatzgrenzen')
        ->toContain('Keine Analyseergebnisse vorhanden.')
        ->toContain('/api/admin/abas/${this.abaId}/analysis/results');

    expect($treeComponentContent)
        ->toContain('Kein Text für diesen Datensatz gespeichert.')
        ->toContain('Angaben vom Titelblatt')
        ->toContain('Einreicher:in (Verfasst von)')
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
    $run->refresh();

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

    $summary = is_array($run->summary) ? $run->summary : [];
    $analysisStats = is_array($summary['analysis_stats'] ?? null) ? $summary['analysis_stats'] : [];
    $displayValues = is_array($summary['display_values'] ?? null) ? $summary['display_values'] : [];

    expect((string) ($analysisStats['title_page_title'] ?? ''))->toBe('Die Rolle der Medien in der politischen Meinungsbildung')
        ->and((string) ($analysisStats['title_page_submitter'] ?? ''))->toBe('Yvonne Pucher')
        ->and((string) ($analysisStats['title_page_advisor'] ?? ''))->toContain('Günther Kron')
        ->and((string) ($analysisStats['title_page_class'] ?? ''))->toBe('8M')
        ->and((string) ($analysisStats['title_page_year'] ?? ''))->toBe('2025/2026')
        ->and((string) ($displayValues['title_page_title'] ?? ''))->toBe('Die Rolle der Medien in der politischen Meinungsbildung')
        ->and((string) ($displayValues['title_page_submitter'] ?? ''))->toBe('Yvonne Pucher')
        ->and((string) ($displayValues['title_page_advisor'] ?? ''))->toContain('Günther Kron')
        ->and((string) ($displayValues['title_page_class'] ?? ''))->toBe('8M')
        ->and((string) ($displayValues['title_page_year'] ?? ''))->toBe('2025/2026');
});

test('title page year stays empty when no explicit year label is present', function () {
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

    $mainAttachment = createMainDocxAttachment($aba, 'titelblatt-ohne-jahr.docx', [
        ['text' => 'Christian Doppler-Gymnasium', 'style' => 'Normal'],
        ['text' => 'Franz-Josef-Kai 41', 'style' => 'Normal'],
        ['text' => '5020 Salzburg', 'style' => 'Normal'],
        ['text' => 'Die Rolle der Medien in der politischen Meinungsbildung', 'style' => 'Normal'],
        ['text' => 'Verfasst von', 'style' => 'Normal'],
        ['text' => 'Yvonne Pucher', 'style' => 'Normal'],
        ['text' => 'Betreuer: Dipl.-Ing. Günther Kron', 'style' => 'Normal'],
        ['text' => 'Klasse 8M', 'style' => 'Normal'],
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
        'source_original_name' => 'titelblatt-ohne-jahr.docx',
        'source_path' => $mainAttachment->path,
        'source_mime_type' => $mainAttachment->mime_type,
        'started_at' => now(),
    ]);

    app(AbaAnalysisService::class)->processRun($run->id);
    $run->refresh();

    $titlePage = AbaAnalysisResult::query()
        ->where('aba_analysis_run_id', $run->id)
        ->where('section_type', 'title_page')
        ->first();

    expect($titlePage)->not->toBeNull();

    $details = is_array($titlePage?->metadata['title_page_details'] ?? null)
        ? $titlePage->metadata['title_page_details']
        : [];

    $summary = is_array($run->summary) ? $run->summary : [];
    $analysisStats = is_array($summary['analysis_stats'] ?? null) ? $summary['analysis_stats'] : [];
    $displayValues = is_array($summary['display_values'] ?? null) ? $summary['display_values'] : [];

    expect($details['year'] ?? null)->toBeNull()
        ->and($analysisStats['title_page_year'] ?? null)->toBeNull()
        ->and($displayValues['title_page_year'] ?? null)->toBeNull();
});

test('title page year is not inferred from running text mentioning jahr', function () {
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

    $mainAttachment = createMainDocxAttachment($aba, 'titelblatt-jahr-im-fliestext.docx', [
        ['text' => 'Christian Doppler-Gymnasium', 'style' => 'Normal'],
        ['text' => 'Franz-Josef-Kai 41', 'style' => 'Normal'],
        ['text' => '5020 Salzburg', 'style' => 'Normal'],
        ['text' => 'Die Rolle der Medien in der politischen Meinungsbildung', 'style' => 'Normal'],
        ['text' => 'Verfasst von', 'style' => 'Normal'],
        ['text' => 'Yvonne Pucher', 'style' => 'Normal'],
        ['text' => 'Betreuer: Dipl.-Ing. Günther Kron', 'style' => 'Normal'],
        ['text' => 'Klasse 8M', 'style' => 'Normal'],
        ['text' => 'Im Jahr 2019 wurde das Thema erstmals betrachtet.', 'style' => 'Normal'],
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
        'source_original_name' => 'titelblatt-jahr-im-fliestext.docx',
        'source_path' => $mainAttachment->path,
        'source_mime_type' => $mainAttachment->mime_type,
        'started_at' => now(),
    ]);

    app(AbaAnalysisService::class)->processRun($run->id);
    $run->refresh();

    $titlePage = AbaAnalysisResult::query()
        ->where('aba_analysis_run_id', $run->id)
        ->where('section_type', 'title_page')
        ->first();

    expect($titlePage)->not->toBeNull();

    $details = is_array($titlePage?->metadata['title_page_details'] ?? null)
        ? $titlePage->metadata['title_page_details']
        : [];

    $summary = is_array($run->summary) ? $run->summary : [];
    $analysisStats = is_array($summary['analysis_stats'] ?? null) ? $summary['analysis_stats'] : [];
    $displayValues = is_array($summary['display_values'] ?? null) ? $summary['display_values'] : [];

    expect($details['year'] ?? null)->toBeNull()
        ->and($analysisStats['title_page_year'] ?? null)->toBeNull()
        ->and($displayValues['title_page_year'] ?? null)->toBeNull();
});

test('title page year is extracted from german month year expressions', function (string $monthLine) {
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

    $mainAttachment = createMainDocxAttachment($aba, 'titelblatt-monat-jahr-de.docx', [
        ['text' => 'Christian Doppler-Gymnasium', 'style' => 'Normal'],
        ['text' => 'Franz-Josef-Kai 41', 'style' => 'Normal'],
        ['text' => '5020 Salzburg', 'style' => 'Normal'],
        ['text' => 'Die Rolle der Medien in der politischen Meinungsbildung', 'style' => 'Normal'],
        ['text' => 'Verfasst von', 'style' => 'Normal'],
        ['text' => 'Yvonne Pucher', 'style' => 'Normal'],
        ['text' => 'Betreuer: Dipl.-Ing. Günther Kron', 'style' => 'Normal'],
        ['text' => 'Klasse 8M', 'style' => 'Normal'],
        ['text' => $monthLine, 'style' => 'Normal'],
        ['page_break' => true],
        ['text' => 'Abstract', 'style' => 'Heading1', 'outline' => 0],
        ['text' => 'Kurzfassung ohne Jahreszahl im Fließtext.', 'style' => 'Normal'],
    ]);

    $run = AbaAnalysisRun::query()->create([
        'aba_id' => $aba->id,
        'aba_attachment_id' => $mainAttachment->id,
        'created_by_user_id' => $user->id,
        'status' => AbaAnalysisRun::STATUS_STARTED,
        'status_message' => 'Analyselauf wurde gestartet.',
        'source_original_name' => 'titelblatt-monat-jahr-de.docx',
        'source_path' => $mainAttachment->path,
        'source_mime_type' => $mainAttachment->mime_type,
        'started_at' => now(),
    ]);

    app(AbaAnalysisService::class)->processRun($run->id);
    $run->refresh();

    $titlePage = AbaAnalysisResult::query()
        ->where('aba_analysis_run_id', $run->id)
        ->where('section_type', 'title_page')
        ->first();

    expect($titlePage)->not->toBeNull();

    $details = is_array($titlePage?->metadata['title_page_details'] ?? null)
        ? $titlePage->metadata['title_page_details']
        : [];
    $summary = is_array($run->summary) ? $run->summary : [];
    $analysisStats = is_array($summary['analysis_stats'] ?? null) ? $summary['analysis_stats'] : [];
    $displayValues = is_array($summary['display_values'] ?? null) ? $summary['display_values'] : [];

    expect((string) ($details['year'] ?? ''))->toBe('2026')
        ->and((string) ($details['date_context'] ?? ''))->toBe($monthLine)
        ->and((string) ($analysisStats['title_page_year'] ?? ''))->toBe('2026')
        ->and((string) ($analysisStats['title_page_date_context'] ?? ''))->toBe($monthLine)
        ->and((string) ($displayValues['title_page_year'] ?? ''))->toBe('2026')
        ->and((string) ($displayValues['title_page_date_context'] ?? ''))->toBe($monthLine);
})->with([
    'januar' => 'Januar 2026',
    'februar' => 'Februar 2026',
    'märz' => 'März 2026',
    'april' => 'April 2026',
    'mai' => 'Mai 2026',
    'juni' => 'Juni 2026',
    'juli' => 'Juli 2026',
    'august' => 'August 2026',
    'september' => 'September 2026',
    'oktober' => 'Oktober 2026',
    'november' => 'November 2026',
    'dezember' => 'Dezember 2026',
]);

test('title page year is extracted from english abbreviated and numeric month year forms', function (string $dateLine, string $expectedDateContext) {
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

    $mainAttachment = createMainDocxAttachment($aba, 'titelblatt-monat-jahr-varianten.docx', [
        ['text' => 'Christian Doppler-Gymnasium', 'style' => 'Normal'],
        ['text' => 'Franz-Josef-Kai 41', 'style' => 'Normal'],
        ['text' => '5020 Salzburg', 'style' => 'Normal'],
        ['text' => 'Die Rolle der Medien in der politischen Meinungsbildung', 'style' => 'Normal'],
        ['text' => 'Verfasst von', 'style' => 'Normal'],
        ['text' => 'Yvonne Pucher', 'style' => 'Normal'],
        ['text' => 'Betreuer: Dipl.-Ing. Günther Kron', 'style' => 'Normal'],
        ['text' => 'Klasse 8M', 'style' => 'Normal'],
        ['text' => $dateLine, 'style' => 'Normal'],
        ['page_break' => true],
        ['text' => 'Abstract', 'style' => 'Heading1', 'outline' => 0],
        ['text' => 'Kurzfassung ohne Jahreszahl im Fließtext.', 'style' => 'Normal'],
    ]);

    $run = AbaAnalysisRun::query()->create([
        'aba_id' => $aba->id,
        'aba_attachment_id' => $mainAttachment->id,
        'created_by_user_id' => $user->id,
        'status' => AbaAnalysisRun::STATUS_STARTED,
        'status_message' => 'Analyselauf wurde gestartet.',
        'source_original_name' => 'titelblatt-monat-jahr-varianten.docx',
        'source_path' => $mainAttachment->path,
        'source_mime_type' => $mainAttachment->mime_type,
        'started_at' => now(),
    ]);

    app(AbaAnalysisService::class)->processRun($run->id);
    $run->refresh();

    $titlePage = AbaAnalysisResult::query()
        ->where('aba_analysis_run_id', $run->id)
        ->where('section_type', 'title_page')
        ->first();

    expect($titlePage)->not->toBeNull();

    $details = is_array($titlePage?->metadata['title_page_details'] ?? null)
        ? $titlePage->metadata['title_page_details']
        : [];
    $summary = is_array($run->summary) ? $run->summary : [];
    $analysisStats = is_array($summary['analysis_stats'] ?? null) ? $summary['analysis_stats'] : [];
    $displayValues = is_array($summary['display_values'] ?? null) ? $summary['display_values'] : [];

    expect((string) ($details['year'] ?? ''))->toBe('2026')
        ->and((string) ($details['date_context'] ?? ''))->toBe($expectedDateContext)
        ->and((string) ($analysisStats['title_page_year'] ?? ''))->toBe('2026')
        ->and((string) ($analysisStats['title_page_date_context'] ?? ''))->toBe($expectedDateContext)
        ->and((string) ($displayValues['title_page_year'] ?? ''))->toBe('2026')
        ->and((string) ($displayValues['title_page_date_context'] ?? ''))->toBe($expectedDateContext);
})->with([
    'english month' => ['February 2026', 'February 2026'],
    'abbreviated month' => ['Feb. 2026', 'Feb. 2026'],
    'numeric month slash year' => ['02/2026', '02/2026'],
    'year month hyphen' => ['2026-02', '2026-02'],
]);

test('title page year is populated when the month year appears only on the title page', function () {
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

    $mainAttachment = createMainDocxAttachment($aba, 'titelblatt-jahr-nur-auf-titelseite.docx', [
        ['text' => 'Christian Doppler-Gymnasium', 'style' => 'Normal'],
        ['text' => 'Franz-Josef-Kai 41', 'style' => 'Normal'],
        ['text' => '5020 Salzburg', 'style' => 'Normal'],
        ['text' => 'Die Rolle der Medien in der politischen Meinungsbildung', 'style' => 'Normal'],
        ['text' => 'Verfasst von', 'style' => 'Normal'],
        ['text' => 'Yvonne Pucher', 'style' => 'Normal'],
        ['text' => 'Betreuer: Dipl.-Ing. Günther Kron', 'style' => 'Normal'],
        ['text' => 'Klasse 8M', 'style' => 'Normal'],
        ['text' => 'Februar 2026', 'style' => 'Normal'],
        ['page_break' => true],
        ['text' => 'Abstract', 'style' => 'Heading1', 'outline' => 0],
        ['text' => 'Kurzfassung ohne weitere Datums- oder Jahresangaben.', 'style' => 'Normal'],
    ]);

    $run = AbaAnalysisRun::query()->create([
        'aba_id' => $aba->id,
        'aba_attachment_id' => $mainAttachment->id,
        'created_by_user_id' => $user->id,
        'status' => AbaAnalysisRun::STATUS_STARTED,
        'status_message' => 'Analyselauf wurde gestartet.',
        'source_original_name' => 'titelblatt-jahr-nur-auf-titelseite.docx',
        'source_path' => $mainAttachment->path,
        'source_mime_type' => $mainAttachment->mime_type,
        'started_at' => now(),
    ]);

    app(AbaAnalysisService::class)->processRun($run->id);
    $run->refresh();

    $summary = is_array($run->summary) ? $run->summary : [];
    $analysisStats = is_array($summary['analysis_stats'] ?? null) ? $summary['analysis_stats'] : [];
    $displayValues = is_array($summary['display_values'] ?? null) ? $summary['display_values'] : [];

    expect((string) ($analysisStats['title_page_year'] ?? ''))->toBe('2026')
        ->and((string) ($analysisStats['title_page_date_context'] ?? ''))->toBe('Februar 2026')
        ->and((string) ($displayValues['title_page_year'] ?? ''))->toBe('2026')
        ->and((string) ($displayValues['title_page_date_context'] ?? ''))->toBe('Februar 2026');
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

test('title-page advisor gender-star label is parsed without splitting', function () {
    if (! class_exists(ZipArchive::class)) {
        $this->markTestSkipped('ZipArchive required.');
    }

    $user = createAbaTeacher($this->school, $this->schoolyear);
    $aba = Aba::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $user->id,
    ]);

    $attachment = createMainDocxAttachment($aba, 'title-page-gender-star.docx', [
        ['text' => 'Die Rolle der Fotografie in sozialen Medien'],
        ['text' => 'Verfasser*in: Sandra Banu'],
        ['text' => 'Klasse: 8M'],
        ['text' => 'Betreuer*in: Dipl.-Ing. Günther Kron'],
        ['text' => 'Schuljahr: 2025/26'],
        ['text' => 'Inhaltsverzeichnis', 'style' => 'Heading1', 'outline' => 0],
        ['text' => '1. Einleitung 3', 'style' => 'TOC1'],
        ['text' => 'Einleitung', 'style' => 'Heading1', 'outline' => 0],
        ['text' => 'Einleitender Fließtext mit ausreichender Länge.'],
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

    $stats = is_array($run->summary['analysis_stats'] ?? null) ? $run->summary['analysis_stats'] : [];
    $titlePage = AbaAnalysisResult::query()
        ->where('aba_analysis_run_id', $run->id)
        ->where('section_type', 'title_page')
        ->first();

    expect((string) ($stats['title_page_advisor'] ?? ''))
        ->toContain('Günther Kron')
        ->not->toStartWith('*in:')
        ->and((string) ($titlePage?->extracted_text ?? ''))->toContain('Betreuer*in: Dipl.-Ing. Günther Kron');
});

test('docx toc with abstract entry stays complete while real abstract and chapter boundary are preserved', function () {
    if (! class_exists(ZipArchive::class)) {
        $this->markTestSkipped('ZipArchive required.');
    }

    $user = createAbaTeacher($this->school, $this->schoolyear);
    $aba = Aba::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $user->id,
    ]);

    $attachment = createMainDocxAttachment($aba, 'toc-abstract-chapter-boundary.docx', [
        ['text' => 'Die Rolle der Fotografie in sozialen Medien'],
        ['text' => 'Verfasser*in: Sandra Banu'],
        ['text' => 'Klasse: 8M'],
        ['text' => 'Betreuer*in: Dipl.-Ing. Günther Kron'],
        ['text' => 'Schuljahr: 2025/26'],
        ['text' => 'Inhaltsverzeichnis', 'style' => 'Heading1', 'outline' => 0],
        ['text' => 'Abstract'],
        ['text' => 'Einleitung'],
        ['text' => 'Entwicklung der Fotografie im Kontext sozialer Medien'],
        ['text' => 'Literaturverzeichnis'],
        ['text' => 'Abbildungsverzeichnis'],
        ['text' => 'Inhaltsverzeichnis', 'style' => 'Heading1', 'outline' => 0],
        ['text' => 'Abstract 6', 'style' => 'TOC1'],
        ['text' => '1. Einleitung 7', 'style' => 'TOC1'],
        ['text' => '2. Entwicklung der Fotografie im Kontext sozialer Medien 9', 'style' => 'TOC1'],
        ['text' => 'Literaturverzeichnis 34', 'style' => 'TOC1'],
        ['text' => 'Abbildungsverzeichnis 37', 'style' => 'TOC1'],
        ['text' => 'Abstract', 'style' => 'Heading1', 'outline' => 0],
        ['text' => 'Dies ist die echte Abstract-Zusammenfassung und sie gehört in die Abstract-Sektion.'],
        ['text' => 'Einleitung', 'style' => 'Heading1', 'outline' => 0],
        ['text' => 'Einleitender Abschnitt mit Kontext und Zielsetzung.'],
        ['text' => 'Entwicklung der Fotografie im Kontext sozialer Medien'],
        ['text' => 'Hier beginnt das zweite Kapitel mit weiterführenden Inhalten.'],
        ['text' => '2.1 Fotografie vor dem Zeitalter sozialer Medien', 'style' => 'Heading2', 'outline' => 1],
        ['text' => 'Unterkapiteltext mit Details.'],
        ['text' => 'Literaturverzeichnis', 'style' => 'Heading1', 'outline' => 0],
        ['text' => 'Quelle A (2025).'],
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

    $results = AbaAnalysisResult::query()
        ->where('aba_analysis_run_id', $run->id)
        ->orderBy('sort_order')
        ->get();

    $stats = is_array($run->summary['analysis_stats'] ?? null) ? $run->summary['analysis_stats'] : [];
    $toc = $results->firstWhere('section_type', 'table_of_contents');
    $abstract = $results->first(fn ($section) => (string) ($section->section_type ?? '') === 'abstract' && (string) ($section->section_title ?? '') === 'Abstract');
    $chapterOne = $results->firstWhere('section_title', 'Einleitung');
    $chapterTwo = $results->first(fn ($section) => in_array(
        (string) ($section->section_title ?? ''),
        ['Entwicklung der Fotografie im Kontext sozialer Medien', '2. Entwicklung der Fotografie im Kontext sozialer Medien'],
        true
    ));

    expect((int) ($stats['table_of_contents_count'] ?? 0))->toBe(1)
        ->and((bool) ($stats['abstract_detected'] ?? false))->toBeTrue()
        ->and($toc)->not->toBeNull()
        ->and((string) ($toc?->extracted_text ?? ''))->toContain('Abstract 6')
        ->and((string) ($toc?->extracted_text ?? ''))->toContain('2. Entwicklung der Fotografie im Kontext sozialer Medien 9')
        ->and((string) ($toc?->extracted_text ?? ''))->not->toContain('Dies ist die echte Abstract-Zusammenfassung')
        ->and($abstract)->not->toBeNull()
        ->and((string) ($abstract?->extracted_text ?? ''))->toContain('Dies ist die echte Abstract-Zusammenfassung')
        ->and($chapterOne)->not->toBeNull()
        ->and($chapterTwo)->not->toBeNull()
        ->and((int) ($chapterOne?->end_line ?? 0))->toBeLessThan((int) ($chapterTwo?->start_line ?? 0))
        ->and((string) ($chapterOne?->extracted_text ?? ''))->not->toContain('Entwicklung der Fotografie im Kontext sozialer Medien');
});

test('title page year is extracted from full numeric german submission dates', function () {
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

    $mainAttachment = createMainDocxAttachment($aba, 'titelblatt-volldatum-de.docx', [
        ['text' => 'Christian Doppler-Gymnasium', 'style' => 'Normal'],
        ['text' => 'Die Rolle der Medien in der politischen Meinungsbildung', 'style' => 'Normal'],
        ['text' => 'Verfasst von', 'style' => 'Normal'],
        ['text' => 'Yvonne Pucher', 'style' => 'Normal'],
        ['text' => 'Betreuer: Dipl.-Ing. Günther Kron', 'style' => 'Normal'],
        ['text' => 'Klasse 8M', 'style' => 'Normal'],
        ['text' => 'abgegeben am: 04.03.2021', 'style' => 'Normal'],
        ['page_break' => true],
        ['text' => 'Abstract', 'style' => 'Heading1', 'outline' => 0],
        ['text' => 'Kurzfassung der Arbeit.', 'style' => 'Normal'],
    ]);

    $run = AbaAnalysisRun::query()->create([
        'aba_id' => $aba->id,
        'aba_attachment_id' => $mainAttachment->id,
        'created_by_user_id' => $user->id,
        'status' => AbaAnalysisRun::STATUS_STARTED,
        'status_message' => 'Analyselauf wurde gestartet.',
        'source_original_name' => 'titelblatt-volldatum-de.docx',
        'source_path' => $mainAttachment->path,
        'source_mime_type' => $mainAttachment->mime_type,
        'started_at' => now(),
    ]);

    app(AbaAnalysisService::class)->processRun($run->id);
    $run->refresh();

    $titlePage = AbaAnalysisResult::query()
        ->where('aba_analysis_run_id', $run->id)
        ->where('section_type', 'title_page')
        ->first();

    expect($titlePage)->not->toBeNull();

    $details = is_array($titlePage?->metadata['title_page_details'] ?? null)
        ? $titlePage->metadata['title_page_details']
        : [];
    $summary = is_array($run->summary) ? $run->summary : [];
    $analysisStats = is_array($summary['analysis_stats'] ?? null) ? $summary['analysis_stats'] : [];

    expect((string) ($details['year'] ?? ''))->toBe('2021')
        ->and((string) ($details['date_context'] ?? ''))->toBe('04.03.2021')
        ->and((string) ($analysisStats['title_page_year'] ?? ''))->toBe('2021')
        ->and((string) ($analysisStats['title_page_date_context'] ?? ''))->toBe('04.03.2021');
});

test('title page year is extracted from date lines without explicit jahr label', function (string $dateLine, string $expectedYear, string $expectedContext) {
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

    $mainAttachment = createMainDocxAttachment($aba, 'titelblatt-datumsvarianten-ohne-jahr-label.docx', [
        ['text' => 'Christian Doppler-Gymnasium', 'style' => 'Normal'],
        ['text' => 'Die Rolle der Medien in der politischen Meinungsbildung', 'style' => 'Normal'],
        ['text' => 'Verfasst von', 'style' => 'Normal'],
        ['text' => 'Yvonne Pucher', 'style' => 'Normal'],
        ['text' => 'Betreuer: Dipl.-Ing. Günther Kron', 'style' => 'Normal'],
        ['text' => 'Klasse 8M', 'style' => 'Normal'],
        ['text' => $dateLine, 'style' => 'Normal'],
        ['page_break' => true],
        ['text' => 'Abstract', 'style' => 'Heading1', 'outline' => 0],
        ['text' => 'Kurzfassung der Arbeit.', 'style' => 'Normal'],
    ]);

    $run = AbaAnalysisRun::query()->create([
        'aba_id' => $aba->id,
        'aba_attachment_id' => $mainAttachment->id,
        'created_by_user_id' => $user->id,
        'status' => AbaAnalysisRun::STATUS_STARTED,
        'status_message' => 'Analyselauf wurde gestartet.',
        'source_original_name' => 'titelblatt-datumsvarianten-ohne-jahr-label.docx',
        'source_path' => $mainAttachment->path,
        'source_mime_type' => $mainAttachment->mime_type,
        'started_at' => now(),
    ]);

    app(AbaAnalysisService::class)->processRun($run->id);
    $run->refresh();

    $titlePage = AbaAnalysisResult::query()
        ->where('aba_analysis_run_id', $run->id)
        ->where('section_type', 'title_page')
        ->first();

    expect($titlePage)->not->toBeNull();

    $details = is_array($titlePage?->metadata['title_page_details'] ?? null)
        ? $titlePage->metadata['title_page_details']
        : [];
    $summary = is_array($run->summary) ? $run->summary : [];
    $analysisStats = is_array($summary['analysis_stats'] ?? null) ? $summary['analysis_stats'] : [];

    expect((string) ($details['year'] ?? ''))->toBe($expectedYear)
        ->and((string) ($details['date_context'] ?? ''))->toBe($expectedContext)
        ->and((string) ($analysisStats['title_page_year'] ?? ''))->toBe($expectedYear)
        ->and((string) ($analysisStats['title_page_date_context'] ?? ''))->toBe($expectedContext);
})->with([
    'plain numeric dd.mm.yyyy' => ['24.02.2023', '2023', '24.02.2023'],
    'location numeric salzburg' => ['Salzburg, 24.02.2023', '2023', '24.02.2023'],
    'location numeric wien' => ['Wien, 03.05.2022', '2022', '03.05.2022'],
    'labeled numeric date' => ['abgegeben am: 04.03.2021', '2021', '04.03.2021'],
    'slash numeric date' => ['04/03/2021', '2021', '04.03.2021'],
    'month year text form' => ['Februar 2026', '2026', 'Februar 2026'],
]);

test('toc with abstract entry and Inhaltsverzeichnis page line stays one isolated toc block', function () {
    if (! class_exists(ZipArchive::class)) {
        $this->markTestSkipped('ZipArchive extension missing.');
    }

    $user = createAbaTeacher($this->school, $this->schoolyear);
    $aba = Aba::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $user->id,
    ]);

    $attachment = createMainDocxAttachment($aba, 'toc-inhaltsverzeichnis-page-line.docx', [
        ['text' => 'Die Rolle der Fotografie in sozialen Medien'],
        ['text' => 'Inhaltsverzeichnis', 'style' => 'Heading1', 'outline' => 0],
        ['text' => 'Abstract 2', 'style' => 'TOC1'],
        ['text' => 'Inhaltsverzeichnis 3', 'style' => 'Heading1', 'outline' => 0],
        ['text' => '1. Einleitung 4', 'style' => 'TOC1'],
        ['text' => '2. Methodik 6', 'style' => 'TOC1'],
        ['text' => 'Abstract', 'style' => 'Heading1', 'outline' => 0],
        ['text' => 'Dies ist die echte Abstract-Zusammenfassung.'],
        ['text' => '1. Einleitung', 'style' => 'Heading1', 'outline' => 0],
        ['text' => 'Einleitender Fließtext mit Kontext und Zielsetzung.'],
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

    $results = AbaAnalysisResult::query()
        ->where('aba_analysis_run_id', $run->id)
        ->orderBy('sort_order')
        ->get();
    $summary = is_array($run->summary) ? $run->summary : [];
    $analysisStats = is_array($summary['analysis_stats'] ?? null) ? $summary['analysis_stats'] : [];
    $toc = $results->firstWhere('section_type', 'table_of_contents');
    $titles = $results->pluck('section_title')->filter()->values()->all();

    expect((int) ($analysisStats['table_of_contents_count'] ?? 0))->toBe(1)
        ->and($toc)->not->toBeNull()
        ->and((string) ($toc?->extracted_text ?? ''))->toContain('Abstract 2')
        ->and((string) ($toc?->extracted_text ?? ''))->toContain('Inhaltsverzeichnis 3')
        ->and((string) ($toc?->extracted_text ?? ''))->toContain('1. Einleitung 4')
        ->and($titles)->not->toContain('Inhaltsverzeichnis 3');
});

test('unnumbered einleitung does not absorb the first numbered hierarchy recovered from toc', function () {
    if (! class_exists(ZipArchive::class)) {
        $this->markTestSkipped('ZipArchive extension missing.');
    }

    $user = createAbaTeacher($this->school, $this->schoolyear);
    $aba = Aba::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $user->id,
    ]);

    $attachment = createMainDocxAttachment($aba, 'early-main-body-hierarchy-recovery.docx', [
        ['text' => 'Inhaltsverzeichnis', 'style' => 'Heading1', 'outline' => 0],
        ['text' => '1. Was ist Radioaktivität und Strahlung? 6', 'style' => 'TOC1'],
        ['text' => '1.1 Einheiten 6', 'style' => 'TOC1'],
        ['text' => '1.1.1 Becquerel 6', 'style' => 'TOC1'],
        ['text' => '1.1.2 Gray 6', 'style' => 'TOC1'],
        ['text' => '1.1.3 Sievert 7', 'style' => 'TOC1'],
        ['text' => '2. Die Entdeckung der Strahlung 8', 'style' => 'TOC1'],
        ['text' => '3.1 Arten der ionisierenden Strahlung 12', 'style' => 'TOC1'],
        ['text' => 'Einleitung', 'style' => 'Heading1', 'outline' => 0],
        ['text' => 'Einleitender Absatz mit Kontext und Zielsetzung.'],
        ['text' => 'Was ist Radioaktivität und Strahlung?'],
        ['text' => 'Erster Absatz im ersten Kapitel.'],
        ['text' => 'Einheiten'],
        ['text' => 'Becquerel'],
        ['text' => 'Die Einheit Becquerel beschreibt die Aktivität.'],
        ['text' => '1.1.2 Gray', 'style' => 'Heading2', 'outline' => 1],
        ['text' => 'Gray beschreibt die absorbierte Dosis.'],
        ['text' => '1.1.3 Sievert', 'style' => 'Heading2', 'outline' => 1],
        ['text' => 'Sievert gewichtet biologische Wirkung.'],
        ['text' => 'Die Entdeckung der Strahlung'],
        ['text' => 'Historischer Überblick zur Entdeckung.'],
        ['text' => '3.1 Arten der ionisierenden Strahlung', 'style' => 'Heading2', 'outline' => 1],
        ['text' => 'Weitere Unterkapitel folgen.'],
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

    $results = AbaAnalysisResult::query()
        ->where('aba_analysis_run_id', $run->id)
        ->orderBy('sort_order')
        ->get();

    $einleitung = $results->firstWhere('section_title', 'Einleitung');
    $chapterOne = $results->firstWhere('section_title', '1. Was ist Radioaktivität und Strahlung?');
    $subchapterOneOne = $results->firstWhere('section_title', '1.1 Einheiten');
    $subchapterOneOneOne = $results->firstWhere('section_title', '1.1.1 Becquerel');
    $subchapterOneOneTwo = $results->firstWhere('section_title', '1.1.2 Gray');
    $subchapterOneOneThree = $results->firstWhere('section_title', '1.1.3 Sievert');
    $chapterTwo = $results->firstWhere('section_title', '2. Die Entdeckung der Strahlung');
    $chapterThreeOne = $results->firstWhere('section_title', '3.1 Arten der ionisierenden Strahlung');

    expect($einleitung)->not->toBeNull()
        ->and($chapterOne)->not->toBeNull()
        ->and($subchapterOneOne)->not->toBeNull()
        ->and($subchapterOneOneOne)->not->toBeNull()
        ->and($subchapterOneOneTwo)->not->toBeNull()
        ->and($subchapterOneOneThree)->not->toBeNull()
        ->and($chapterTwo)->not->toBeNull()
        ->and($chapterThreeOne)->not->toBeNull()
        ->and((int) ($einleitung?->end_line ?? 0))->toBeLessThan((int) ($chapterOne?->start_line ?? 0))
        ->and((string) ($einleitung?->extracted_text ?? ''))->not->toContain('Was ist Radioaktivität und Strahlung?')
        ->and((int) ($chapterTwo?->start_line ?? 0))->toBeGreaterThan((int) ($subchapterOneOneThree?->start_line ?? 0))
        ->and((int) ($chapterTwo?->start_line ?? 0))->toBeLessThan((int) ($chapterThreeOne?->start_line ?? 0));
});

test('image-heavy subsection between 3.2.1 and 3.2.3 is preserved and figure captions stay narrow', function () {
    if (! class_exists(ZipArchive::class)) {
        $this->markTestSkipped('ZipArchive extension missing.');
    }

    $user = createAbaTeacher($this->school, $this->schoolyear);
    $aba = Aba::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $user->id,
    ]);

    $attachment = createMainDocxAttachment($aba, 'figure-heavy-subsection-recovery.docx', [
        ['text' => '3. Informationen die man von Skeletelementen erhalten kann', 'style' => 'Heading1', 'outline' => 0],
        ['text' => 'Einführender Absatz für Kapitel 3.'],
        ['text' => '3.2. Das Geschlecht', 'style' => 'Heading2', 'outline' => 1],
        ['text' => 'Zur Bestimmung des Geschlechts gibt es mehrere Methoden.'],
        ['text' => '3.2.1. DNA-Analyse', 'style' => 'Heading2', 'outline' => 2],
        ['text' => 'DNA-Analyse eignet sich zur Geschlechtsbestimmung.'],
        ['text' => '3.2.2. Bestimmung anhand des Schädels', 'style' => 'Heading2', 'outline' => 2],
        ['text' => 'Abbildung 9: Schädel mit Beschriftung der wichtigsten KnochenAbbildung 9: Schädel mit Beschriftung der wichtigsten KnochenIn der Abbildung 9 kann man einen Schädel erkennen, bei welchem die wichtigsten Knochen beschriftet sind. Bei Abbildung 10: Unterschiede an männlichen und weiblichen SchädelAbbildung 10: Unterschiede an männlichen und weiblichen SchädelMännern ist der Kieferwinkel deutlich ausgeprägter.'],
        ['text' => '3.2.3. Becken', 'style' => 'Heading2', 'outline' => 2],
        ['text' => 'Am Becken ist klar zu erkennen, um welches Geschlecht es sich handelt.'],
        ['text' => 'Abbildung 11: Geschlechtsmerkmale am BeckenAbbildung 11: Geschlechtsmerkmale am BeckenEs ist auch eindeutig zu erkennen, dass die Incisura ischiadica major bei Männern enger ist.'],
        ['text' => '4. Ausblick', 'style' => 'Heading1', 'outline' => 0],
        ['text' => 'Abschließender Ausblick.'],
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

    $results = AbaAnalysisResult::query()
        ->where('aba_analysis_run_id', $run->id)
        ->orderBy('sort_order')
        ->get();

    $subOne = $results->firstWhere('section_title', '3.2.1. DNA-Analyse');
    $subTwo = $results->firstWhere('section_title', '3.2.2. Bestimmung anhand des Schädels');
    $subThree = $results->firstWhere('section_title', '3.2.3. Becken');
    $figureNine = $results->first(fn ($section) => (string) ($section->section_type ?? '') === 'figure' && str_starts_with((string) ($section->section_title ?? ''), 'Abbildung 9:'));
    $figureEleven = $results->first(fn ($section) => (string) ($section->section_type ?? '') === 'figure' && str_starts_with((string) ($section->section_title ?? ''), 'Abbildung 11:'));

    expect($subOne)->not->toBeNull()
        ->and($subTwo)->not->toBeNull()
        ->and($subThree)->not->toBeNull()
        ->and((string) ($subTwo?->extracted_text ?? ''))->toContain('In der Abbildung 9 kann man einen Schädel erkennen')
        ->and($figureNine)->not->toBeNull()
        ->and((string) ($figureNine?->section_title ?? ''))->not->toContain('In der Abbildung 9 kann man einen Schädel erkennen')
        ->and((string) ($figureNine?->section_title ?? ''))->not->toContain('Abbildung 10:')
        ->and($figureEleven)->not->toBeNull()
        ->and((string) ($figureEleven?->section_title ?? ''))->not->toContain('Es ist auch eindeutig zu erkennen');
});

test('inline anchored caption with alternate-content fallback is deduplicated and kept out of subsection prose', function () {
    if (! class_exists(ZipArchive::class)) {
        $this->markTestSkipped('ZipArchive extension missing.');
    }

    $user = createAbaTeacher($this->school, $this->schoolyear);
    $aba = Aba::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $user->id,
    ]);

    $inlineAnchorParagraph = <<<'XML'
<w:p xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:mc="http://schemas.openxmlformats.org/markup-compatibility/2006" xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing" xmlns:v="urn:schemas-microsoft-com:vml">
  <w:r>
    <w:drawing>
      <wp:anchor>
        <mc:AlternateContent>
          <mc:Choice Requires="wps">
            <w:txbxContent>
              <w:p><w:r><w:t>Abbildung 14: Bruchfläche eine postmortalen Bruchs am Oberarm</w:t></w:r></w:p>
            </w:txbxContent>
          </mc:Choice>
          <mc:Fallback>
            <w:pict>
              <v:shape>
                <v:textbox>
                  <w:txbxContent>
                    <w:p><w:r><w:t>Abbildung 14: Bruchfläche eine postmortalen Bruchs am Oberarm</w:t></w:r></w:p>
                  </w:txbxContent>
                </v:textbox>
              </v:shape>
            </w:pict>
          </mc:Fallback>
        </mc:AlternateContent>
      </wp:anchor>
    </w:drawing>
  </w:r>
  <w:r><w:t>Brüche die postmortal, also nach dem Tot aufgetreten sind, haben meistens unregelmäßige Bruchkannten.</w:t></w:r>
</w:p>
XML;

    $attachment = createMainDocxAttachment($aba, 'inline-anchor-caption-dedup.docx', [
        ['text' => '3. Informationen die man von Skeletelementen erhalten kann', 'style' => 'Heading1', 'outline' => 0],
        ['text' => '3.6.2. Postmortale Brüche', 'style' => 'Heading2', 'outline' => 2],
        ['raw_xml' => $inlineAnchorParagraph],
        ['text' => 'Der Bruchverlauf kann zusätzlich makroskopisch beurteilt werden.'],
        ['text' => '3.6.3. Verbrennungen', 'style' => 'Heading2', 'outline' => 2],
        ['text' => 'Verbrennungsmerkmale ergänzen die Befundlage.'],
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

    $results = AbaAnalysisResult::query()
        ->where('aba_analysis_run_id', $run->id)
        ->orderBy('sort_order')
        ->get();

    $subsection = $results->firstWhere('section_title', '3.6.2. Postmortale Brüche');
    $figure = $results->first(fn ($section) => (string) ($section->section_type ?? '') === 'figure'
        && str_starts_with((string) ($section->section_title ?? ''), 'Abbildung 14:'));

    $subsectionText = (string) ($subsection?->extracted_text ?? '');
    $figureTitle = (string) ($figure?->section_title ?? '');
    $figureText = (string) ($figure?->extracted_text ?? '');

    expect($subsection)->not->toBeNull()
        ->and($figure)->not->toBeNull()
        ->and($subsectionText)->toContain('Brüche die postmortal, also nach dem Tot aufgetreten sind')
        ->and($subsectionText)->toContain('Der Bruchverlauf kann zusätzlich makroskopisch beurteilt werden.')
        ->and($subsectionText)->not->toContain('Abbildung 14:')
        ->and(substr_count($figureTitle, 'Abbildung 14:'))->toBe(1)
        ->and($figureTitle)->toBe('Abbildung 14: Bruchfläche eine postmortalen Bruchs am Oberarm')
        ->and($figureText)->not->toContain('Brüche die postmortal')
        ->and($figureText)->toBe($figureTitle);
});

test('numeric caption line does not absorb the first following prose sentence into figure text', function () {
    if (! class_exists(ZipArchive::class)) {
        $this->markTestSkipped('ZipArchive extension missing.');
    }

    $user = createAbaTeacher($this->school, $this->schoolyear);
    $aba = Aba::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $user->id,
    ]);

    $inlineAnchorParagraph = <<<'XML'
<w:p xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:mc="http://schemas.openxmlformats.org/markup-compatibility/2006" xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing" xmlns:v="urn:schemas-microsoft-com:vml">
  <w:r>
    <w:drawing>
      <wp:anchor>
        <mc:AlternateContent>
          <mc:Choice Requires="wps">
            <w:txbxContent>
              <w:p><w:r><w:t>Abbildung 2: Zahndurchbruch bei Kindern 2</w:t></w:r></w:p>
            </w:txbxContent>
          </mc:Choice>
          <mc:Fallback>
            <w:pict>
              <v:shape>
                <v:textbox>
                  <w:txbxContent>
                    <w:p><w:r><w:t>Abbildung 2: Zahndurchbruch bei Kindern 2</w:t></w:r></w:p>
                  </w:txbxContent>
                </v:textbox>
              </v:shape>
            </w:pict>
          </mc:Fallback>
        </mc:AlternateContent>
      </wp:anchor>
    </w:drawing>
  </w:r>
  <w:r><w:t>Genauere Standartdaten als Vergleich findet man in der Literatur wieder.</w:t></w:r>
  <w:r><w:t> Die Durchbruchszeiten der Zähne werden grundsätzlich genetisch gesteuert.</w:t></w:r>
</w:p>
XML;

    $attachment = createMainDocxAttachment($aba, 'numeric-caption-boundary.docx', [
        ['text' => '3.1. Das Alter', 'style' => 'Heading1', 'outline' => 0],
        ['text' => '3.1.1. Das Sterbealter bei Kindern', 'style' => 'Heading2', 'outline' => 2],
        ['raw_xml' => $inlineAnchorParagraph],
        ['text' => '3.1.2. Die Ossifikation des Schädels', 'style' => 'Heading2', 'outline' => 2],
        ['text' => 'Folgeabschnitt.'],
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

    $results = AbaAnalysisResult::query()
        ->where('aba_analysis_run_id', $run->id)
        ->orderBy('sort_order')
        ->get();

    $subsection = $results->firstWhere('section_title', '3.1.1. Das Sterbealter bei Kindern');
    $figure = $results->first(fn ($section) => (string) ($section->section_type ?? '') === 'figure'
        && str_starts_with((string) ($section->section_title ?? ''), 'Abbildung 2:'));

    $subsectionText = (string) ($subsection?->extracted_text ?? '');
    $figureTitle = (string) ($figure?->section_title ?? '');
    $figureText = (string) ($figure?->extracted_text ?? '');

    expect($subsection)->not->toBeNull()
        ->and($figure)->not->toBeNull()
        ->and($figureTitle)->toBe('Abbildung 2: Zahndurchbruch bei Kindern 2')
        ->and($figureText)->toBe('Abbildung 2: Zahndurchbruch bei Kindern 2')
        ->and($figureText)->not->toContain('Genauere Standartdaten als Vergleich findet man')
        ->and($subsectionText)->toContain('Genauere Standartdaten als Vergleich findet man in der Literatur wieder.')
        ->and($subsectionText)->toContain('Die Durchbruchszeiten der Zähne werden grundsätzlich genetisch gesteuert.')
        ->and($subsectionText)->not->toContain('Abbildung 2: Zahndurchbruch bei Kindern 2Genauere');
});

test('adjacent figure caption clusters recover all captions in stable numeric order and keep surrounding prose in subsection body', function (string $clusterLine, array $expectedOrderedCaptions, array $forbiddenBodyFragments) {
    if (! class_exists(ZipArchive::class)) {
        $this->markTestSkipped('ZipArchive extension missing.');
    }

    $user = createAbaTeacher($this->school, $this->schoolyear);
    $aba = Aba::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $user->id,
    ]);

    $attachment = createMainDocxAttachment($aba, 'adjacent-multi-figure-cluster.docx', [
        ['text' => '2. Die Entdeckung der Strahlung', 'style' => 'Heading1', 'outline' => 0],
        ['text' => '2.1 Historischer Überblick', 'style' => 'Heading2', 'outline' => 1],
        ['text' => 'Einführender Absatz zur historischen Einordnung der Forschenden.'],
        ['text' => $clusterLine],
        ['text' => 'Die Experimente wurden in mehreren Laboren unabhängig voneinander wiederholt.'],
        ['text' => '2.2 Quellenlage', 'style' => 'Heading2', 'outline' => 1],
        ['text' => 'Folgeabschnitt zur Einordnung der Quellenlage.'],
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

    $results = AbaAnalysisResult::query()
        ->where('aba_analysis_run_id', $run->id)
        ->orderBy('sort_order')
        ->get();

    $subsection = $results->firstWhere('section_title', '2.1 Historischer Überblick');
    $figures = $results->where('section_type', 'figure')->values();
    $figureTitles = $figures->pluck('section_title')->map(fn ($value) => (string) $value)->values()->all();
    $subsectionText = (string) ($subsection?->extracted_text ?? '');
    $allFigureText = implode("\n", $figures->pluck('extracted_text')->map(fn ($value) => (string) $value)->all());

    expect($subsection)->not->toBeNull()
        ->and($subsectionText)->toContain('Einführender Absatz zur historischen Einordnung der Forschenden.')
        ->and($subsectionText)->toContain('Die Experimente wurden in mehreren Laboren unabhängig voneinander wiederholt.')
        ->and($figureTitles)->toBe($expectedOrderedCaptions);

    foreach ($expectedOrderedCaptions as $expectedCaption) {
        $figure = $figures->firstWhere('section_title', $expectedCaption);
        $figureText = (string) ($figure?->extracted_text ?? '');

        expect($figure)->not->toBeNull()
            ->and($figureText)->toContain($expectedCaption)
            ->and($figureText)->not->toContain('Die Experimente wurden in mehreren Laboren unabhängig voneinander wiederholt.');

        expect($subsectionText)->not->toContain($expectedCaption);
    }

    foreach ($forbiddenBodyFragments as $fragment) {
        expect($subsectionText)->not->toContain($fragment);
    }

    expect($allFigureText)->not->toContain('Die Experimente wurden in mehreren Laboren unabhängig voneinander wiederholt.');
})->with([
    'two adjacent captions' => [
        'Abb. 2: Marie CurieAbb. 1: Henri Becquerel',
        ['Abb. 1: Henri Becquerel', 'Abb. 2: Marie Curie'],
        [],
    ],
    'three adjacent captions' => [
        'Abb. 2: Marie CurieAbb. 3: Ernest RutherfordAbb. 1: Henri Becquerel',
        ['Abb. 1: Henri Becquerel', 'Abb. 2: Marie Curie', 'Abb. 3: Ernest Rutherford'],
        [],
    ],
    'four adjacent captions with source prefix' => [
        'Kuiper Pieter: Henri Becquerel 1903Abb. 1: Henri BecquerelAbb. 2: Marie CurieAbb. 4: Pierre CurieAbb. 3: Ernest Rutherford',
        ['Abb. 1: Henri Becquerel', 'Abb. 2: Marie Curie', 'Abb. 3: Ernest Rutherford', 'Abb. 4: Pierre Curie'],
        ['Kuiper Pieter: Henri Becquerel 1903'],
    ],
]);

test('local cluster sorting keeps fallback order when not all segments have sortable numeric keys', function () {
    $extractor = app(\App\Services\AbaLocalDocumentStructureExtractor::class);
    $method = new ReflectionMethod(\App\Services\AbaLocalDocumentStructureExtractor::class, 'sortCaptionSegmentsInLocalCluster');
    $method->setAccessible(true);

    $segments = [
        ['raw' => 'Abb. 2: Marie Curie', 'caption' => 'Abb. 2: Marie Curie', 'remainder' => '', 'reason' => 'caption_only', 'is_caption' => true],
        ['raw' => 'Bildquelle Universität Salzburg', 'caption' => 'Bildquelle Universität Salzburg', 'remainder' => '', 'reason' => 'not_caption', 'is_caption' => true],
        ['raw' => 'Abb. 1: Henri Becquerel', 'caption' => 'Abb. 1: Henri Becquerel', 'remainder' => '', 'reason' => 'caption_only', 'is_caption' => true],
    ];

    /** @var array<int, array{caption:string}> $sorted */
    $sorted = $method->invoke($extractor, $segments);
    $sortedCaptions = array_values(array_map(
        fn (array $segment): string => (string) ($segment['caption'] ?? ''),
        $sorted
    ));

    expect($sortedCaptions)->toBe([
        'Abb. 2: Marie Curie',
        'Bildquelle Universität Salzburg',
        'Abb. 1: Henri Becquerel',
    ]);
});

test('numbering continuity recovers 3.5.1 from a 2.5.1 typo and keeps Schluss/Fazit section', function () {
    if (! class_exists(ZipArchive::class)) {
        $this->markTestSkipped('ZipArchive extension missing.');
    }

    $user = createAbaTeacher($this->school, $this->schoolyear);
    $aba = Aba::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $user->id,
    ]);

    $attachment = createMainDocxAttachment($aba, 'numbering-continuity-schluss-fazit.docx', [
        ['text' => '2. Methoden', 'style' => 'Heading1', 'outline' => 0],
        ['text' => 'Methodischer Kontext für die spätere Analyse.'],
        ['text' => '3. Informationen die man von Skeletelementen erhalten kann', 'style' => 'Heading1', 'outline' => 0],
        ['text' => '3.5. Krankheiten und Stressphasen', 'style' => 'Heading2', 'outline' => 1],
        ['text' => 'Einleitung zu Krankheiten und Stressphasen.'],
        ['text' => '2.5.1. Krankheiten', 'style' => 'Heading2', 'outline' => 2],
        ['text' => 'Krankheiten verändern die Oberfläche der Knochen in spezifischer Weise.'],
        ['text' => '3.5.2. Stressphasen', 'style' => 'Heading2', 'outline' => 2],
        ['text' => 'Stressphasen lassen sich an Zähnen und langen Röhrenknochen erkennen.'],
        ['text' => 'Schluss/Fazit', 'style' => 'Heading1', 'outline' => 0],
        ['text' => 'Die wichtigsten Aussagen der Arbeit werden im Schluss/Fazit zusammengeführt.'],
        ['text' => 'Literaturverzeichnis', 'style' => 'Heading1', 'outline' => 0],
        ['text' => 'Quelle A (2025).'],
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

    $results = AbaAnalysisResult::query()
        ->where('aba_analysis_run_id', $run->id)
        ->orderBy('sort_order')
        ->get();

    $titles = $results->pluck('section_title')->filter()->values()->all();
    $sectionThreeFive = $results->firstWhere('section_title', '3.5. Krankheiten und Stressphasen');
    $sectionThreeFiveOne = $results->firstWhere('section_title', '3.5.1. Krankheiten');
    $sectionThreeFiveTwo = $results->firstWhere('section_title', '3.5.2. Stressphasen');
    $schlussFazit = $results->firstWhere('section_title', 'Schluss/Fazit');
    $bibliography = $results->firstWhere('section_type', 'bibliography');

    expect($sectionThreeFive)->not->toBeNull()
        ->and((string) ($sectionThreeFive?->extracted_text ?? ''))->toContain('Einleitung zu Krankheiten und Stressphasen.')
        ->and($sectionThreeFiveOne)->not->toBeNull()
        ->and($sectionThreeFiveTwo)->not->toBeNull()
        ->and($titles)->not->toContain('2.5.1. Krankheiten')
        ->and((int) ($sectionThreeFiveOne?->parent_result_id ?? 0))->toBe((int) ($sectionThreeFive?->id ?? 0))
        ->and((int) ($sectionThreeFiveTwo?->parent_result_id ?? 0))->toBe((int) ($sectionThreeFive?->id ?? 0))
        ->and($schlussFazit)->not->toBeNull()
        ->and((string) ($schlussFazit?->extracted_text ?? ''))->toContain('Die wichtigsten Aussagen der Arbeit werden im Schluss/Fazit zusammengeführt.')
        ->and($bibliography)->not->toBeNull()
        ->and((int) ($schlussFazit?->start_line ?? 0))->toBeLessThan((int) ($bibliography?->start_line ?? 0));
});

test('slash captions, table captions, and mixed backmatter boundaries stay structurally correct', function () {
    if (! class_exists(ZipArchive::class)) {
        $this->markTestSkipped('ZipArchive extension missing.');
    }

    $user = createAbaTeacher($this->school, $this->schoolyear);
    $aba = Aba::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $user->id,
    ]);

    $attachment = createMainDocxAttachment($aba, 'aba6-docx-structure-regression.docx', [
        ['text' => '2. Hautbild', 'style' => 'Heading1', 'outline' => 0],
        ['text' => '2.3 Faktoren', 'style' => 'Heading2', 'outline' => 1],
        ['text' => 'Einleitender Fließtext zu den auslösenden Faktoren.'],
        ['text' => 'Abbildung 2/ Faktoren der Neurodermitis'],
        ['text' => 'Die Faktoren wirken je nach Alter und Alltagsumfeld unterschiedlich stark.'],
        ['text' => '2.4 Symptome und Wunden', 'style' => 'Heading2', 'outline' => 1],
        ['text' => 'Akute Symptome werden im Verlauf der Schübe sichtbar.'],
        ['text' => 'Tab. 1: akute Hauterscheinungen'],
        ['text' => 'Zwischen Tabelle und nächster Abbildung steht normaler Fließtext.'],
        ['text' => 'Abbildung 3/ Bläschen als akutes Symptom'],
        ['text' => 'Die Befundlage muss jeweils mit dem klinischen Verlauf abgeglichen werden.'],
        ['text' => '3. Therapie', 'style' => 'Heading1', 'outline' => 0],
        ['text' => '3.1 Cremenbehandlung/Salbenbehandlung', 'style' => 'Heading2', 'outline' => 1],
        ['text' => 'Cremen und Salben werden stufenweise eingesetzt.'],
        ['text' => 'Abbildung 5/ Stufenschema Cremen & Salben'],
        ['text' => 'Die Intensität wird an Entzündungsgrad und Hautzustand angepasst.'],
        ['text' => '3.2.2 TCM', 'style' => 'Heading2', 'outline' => 2],
        ['text' => 'Tab. 2: Geschmäcker und ihre Wirkung bei TCM'],
        ['text' => 'Textilien, Materialien, Stoffe und Nahrungsmittel'],
        ['text' => 'Vgl. Katherina Ziegelbauer, 2017, Jucken Ade, S.13'],
        ['text' => '3.3 Dupixent/Dupilumab', 'style' => 'Heading2', 'outline' => 1],
        ['text' => 'Abbildung 6/ Dupixent Spritze'],
        ['text' => 'Biologika werden erst nach umfassender klinischer Abklärung eingesetzt.'],
        ['text' => '6 Abbildung- und Tabellenverzeichnis:', 'style' => 'Heading1', 'outline' => 0],
        ['text' => 'Abb. 1: Historische Abbildung'],
        ['text' => 'Abb. 2: Faktoren der Neurodermitis'],
        ['text' => 'Tab. 1: akute Hauterscheinungen'],
        ['text' => 'Tab. 2: Geschmäcker und ihre Wirkung bei TCM'],
        ['text' => 'Literaturverzeichnis', 'style' => 'Heading1', 'outline' => 0],
        ['text' => 'Musterautor, A. (2024): Beispielquelle.'],
        ['text' => 'Eidstaatliche Erklärung', 'style' => 'Heading1', 'outline' => 0],
        ['text' => 'Hiermit erkläre ich die selbstständige Erstellung der Arbeit.'],
        ['text' => 'Salzburg, 01.01.2025'],
        ['text' => 'Unterschrift'],
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

    $results = AbaAnalysisResult::query()
        ->where('aba_analysis_run_id', $run->id)
        ->orderBy('sort_order')
        ->get();

    $figureTwo = $results->firstWhere('section_title', 'Abbildung 2/ Faktoren der Neurodermitis');
    $figureThree = $results->firstWhere('section_title', 'Abbildung 3/ Bläschen als akutes Symptom');
    $figureFive = $results->firstWhere('section_title', 'Abbildung 5/ Stufenschema Cremen & Salben');
    $figureSix = $results->firstWhere('section_title', 'Abbildung 6/ Dupixent Spritze');
    $tableOne = $results->firstWhere('section_title', 'Tab. 1: akute Hauterscheinungen');
    $tableTwo = $results->firstWhere('section_title', 'Tab. 2: Geschmäcker und ihre Wirkung bei TCM');
    $figureIndex = $results->firstWhere('section_type', 'figure_index');
    $declaration = $results->firstWhere('section_type', 'consent_declaration');

    $falseStandaloneTextiles = $results->first(fn ($section) => (string) ($section->section_type ?? '') === 'other_section'
        && (string) ($section->section_title ?? '') === 'Textilien, Materialien, Stoffe und Nahrungsmittel');
    $falseStandaloneCitation = $results->first(fn ($section) => (string) ($section->section_type ?? '') === 'other_section'
        && str_starts_with((string) ($section->section_title ?? ''), 'Vgl. Katherina Ziegelbauer'));
    $wrongChapterIndex = $results->first(fn ($section) => (string) ($section->section_type ?? '') === 'chapter'
        && str_starts_with((string) ($section->section_title ?? ''), '6 Abbildung- und Tabellenverzeichnis'));
    $duplicateIndexFigure = $results->first(fn ($section) => in_array((string) ($section->section_type ?? ''), ['figure', 'table'], true)
        && (string) ($section->section_title ?? '') === 'Abb. 2: Faktoren der Neurodermitis');
    $indexEntryNodeCount = $results
        ->filter(fn ($section) => in_array((string) ($section->section_type ?? ''), ['figure', 'table'], true))
        ->filter(fn ($section) => in_array((string) ($section->section_title ?? ''), [
            'Abb. 1: Historische Abbildung',
            'Abb. 2: Faktoren der Neurodermitis',
        ], true))
        ->count();

    expect($figureTwo)->not->toBeNull()
        ->and((string) ($figureTwo?->section_type ?? ''))->toBe('figure')
        ->and($figureThree)->not->toBeNull()
        ->and((string) ($figureThree?->section_type ?? ''))->toBe('figure')
        ->and($figureFive)->not->toBeNull()
        ->and((string) ($figureFive?->section_type ?? ''))->toBe('figure')
        ->and($figureSix)->not->toBeNull()
        ->and((string) ($figureSix?->section_type ?? ''))->toBe('figure')
        ->and($tableOne)->not->toBeNull()
        ->and((string) ($tableOne?->section_type ?? ''))->toBe('table')
        ->and($tableTwo)->not->toBeNull()
        ->and((string) ($tableTwo?->section_type ?? ''))->toBe('table')
        ->and($figureIndex)->not->toBeNull()
        ->and((string) ($figureIndex?->section_title ?? ''))->toContain('Abbildung- und Tabellenverzeichnis')
        ->and($wrongChapterIndex)->toBeNull()
        ->and($duplicateIndexFigure)->toBeNull()
        ->and($indexEntryNodeCount)->toBe(0)
        ->and($declaration)->not->toBeNull()
        ->and((string) ($declaration?->section_title ?? ''))->toBe('Eidstaatliche Erklärung')
        ->and((string) ($declaration?->extracted_text ?? ''))->toContain('Hiermit erkläre ich die selbstständige Erstellung der Arbeit.')
        ->and((string) ($declaration?->extracted_text ?? ''))->toContain('Salzburg, 01.01.2025')
        ->and((string) ($figureIndex?->extracted_text ?? ''))->not->toContain('Eidstaatliche Erklärung')
        ->and($falseStandaloneTextiles)->toBeNull()
        ->and($falseStandaloneCitation)->toBeNull();
});
