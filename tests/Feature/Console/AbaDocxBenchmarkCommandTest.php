<?php

use App\Models\Aba;
use App\Models\AbaAnalysisResult;
use App\Models\AbaAnalysisRun;
use App\Models\AbaAttachment;
use App\Models\School;
use App\Models\Schoolyear;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

/**
 * @return array{aba:Aba,attachment:AbaAttachment,user:User}
 */
function createAbaBenchmarkContext(): array
{
    $school = School::factory()->create();
    $schoolyear = Schoolyear::factory()->create([
        'school_id' => $school->id,
    ]);
    $user = User::factory()->create([
        'school_id' => $school->id,
        'schoolyear_id' => $schoolyear->id,
    ]);

    $aba = Aba::factory()->create([
        'school_id' => $school->id,
        'schoolyear_id' => $schoolyear->id,
        'user_id' => $user->id,
    ]);

    $attachment = AbaAttachment::factory()->create([
        'aba_id' => $aba->id,
        'document_kind' => AbaAttachment::DOCUMENT_KIND_MAIN,
        'original_name' => 'benchmark.docx',
        'stored_name' => 'benchmark.docx',
        'path' => 'aba/test-analysis/'.$aba->id.'/benchmark.docx',
        'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'disk' => 'local',
        'uploaded_by_user_id' => $user->id,
    ]);

    return [
        'aba' => $aba,
        'attachment' => $attachment,
        'user' => $user,
    ];
}

/**
 * @param  array<string,mixed>  $analysisStats
 */
function createCompletedBenchmarkRun(Aba $aba, AbaAttachment $attachment, User $user, array $analysisStats): AbaAnalysisRun
{
    $run = AbaAnalysisRun::query()->create([
        'aba_id' => $aba->id,
        'aba_attachment_id' => $attachment->id,
        'created_by_user_id' => $user->id,
        'status' => AbaAnalysisRun::STATUS_COMPLETED,
        'status_message' => 'Benchmark run completed.',
        'source_original_name' => $attachment->original_name,
        'source_path' => $attachment->path,
        'source_mime_type' => $attachment->mime_type,
        'started_at' => now()->subMinute(),
        'running_at' => now()->subSeconds(30),
        'completed_at' => now(),
        'summary' => [
            'analysis_stats' => $analysisStats,
        ],
    ]);

    AbaAnalysisResult::query()->create([
        'aba_id' => $aba->id,
        'aba_analysis_run_id' => $run->id,
        'aba_attachment_id' => $attachment->id,
        'section_type' => 'title_page',
        'section_title' => 'Titelblatt',
        'extracted_text' => 'Titelblatt Inhalte',
        'sort_order' => 1,
        'hierarchy_level' => 1,
        'start_line' => 1,
        'end_line' => 6,
    ]);

    AbaAnalysisResult::query()->create([
        'aba_id' => $aba->id,
        'aba_analysis_run_id' => $run->id,
        'aba_attachment_id' => $attachment->id,
        'section_type' => 'chapter',
        'section_title' => '1. Einleitung',
        'extracted_text' => 'Einleitender Inhalt.',
        'sort_order' => 2,
        'hierarchy_level' => 1,
        'start_line' => 7,
        'end_line' => 12,
    ]);

    AbaAnalysisResult::query()->create([
        'aba_id' => $aba->id,
        'aba_analysis_run_id' => $run->id,
        'aba_attachment_id' => $attachment->id,
        'section_type' => 'subchapter',
        'section_title' => '1.1 Hintergrund',
        'extracted_text' => 'Unterkapitel Inhalt.',
        'sort_order' => 3,
        'hierarchy_level' => 2,
        'start_line' => 13,
        'end_line' => 20,
    ]);

    return $run;
}

/**
 * @return array<string,mixed>
 */
function benchmarkStats(float $analysisQuality, float $structureQuality, float $headingConfidence, float $hierarchyConfidence): array
{
    return [
        'analysis_quality_score' => $analysisQuality,
        'structure_quality_score' => $structureQuality,
        'heading_assignment_confidence' => $headingConfidence,
        'hierarchy_confidence' => $hierarchyConfidence,
        'frontmatter_boundary_confidence' => 0.97,
        'body_reentry_confidence' => 0.95,
        'title_page_detected' => true,
        'abstract_detected' => true,
        'bibliography_detected' => false,
        'figure_index_detected' => false,
        'consent_declaration_detected' => false,
        'chapter_count' => 1,
        'subchapter_count' => 1,
        'table_of_contents_count' => 1,
        'toc_special_entries_count' => 1,
        'title_page_year' => '2026',
        'title_page_advisor' => 'Dipl.-Ing. Günther Kron',
        'hierarchy_wrong_parent_attachment_count' => 0,
        'hierarchy_missing_parent_count' => 0,
        'hierarchy_numbering_mismatch_count' => 0,
        'hierarchy_flattened_count' => 0,
        'hierarchy_uncertain_parent_count' => 0,
        'hierarchy_impossible_level_jump_count' => 0,
        'hierarchy_boundary_detachment_count' => 0,
    ];
}

it('fails when no benchmark documents are configured', function () {
    config()->set('aba_docx_benchmark.documents', []);

    $this->artisan('aba:benchmark-docx')
        ->expectsOutputToContain('No benchmark documents configured')
        ->assertExitCode(1);
});

it('writes a benchmark report with metric deltas for configured docx cases', function () {
    $context = createAbaBenchmarkContext();
    $aba = $context['aba'];
    $attachment = $context['attachment'];
    $user = $context['user'];

    createCompletedBenchmarkRun(
        $aba,
        $attachment,
        $user,
        benchmarkStats(0.80, 0.72, 0.78, 0.70)
    );

    createCompletedBenchmarkRun(
        $aba,
        $attachment,
        $user,
        benchmarkStats(0.84, 0.76, 0.82, 0.74)
    );

    config()->set('aba_docx_benchmark.documents', [[
        'key' => 'test_doc',
        'label' => 'Test DOCX',
        'aba_id' => $aba->id,
        'attachment_id' => $attachment->id,
        'characteristics' => ['title_page_metadata', 'numbered_chapters_subchapters'],
        'known_strengths' => ['Stable title page extraction'],
        'known_weaknesses' => ['None'],
    ]]);
    config()->set('aba_docx_benchmark.taxonomy', [
        'title_page_metadata' => 'Title page metadata',
        'numbered_chapters_subchapters' => 'Numbered headings',
        'appendix_section' => 'Appendix section',
    ]);
    config()->set('aba_docx_benchmark.regression_threshold', 0.01);

    $outputPath = storage_path('app/aba-benchmarks/test-docx-benchmark.json');
    File::delete($outputPath);

    $this->artisan('aba:benchmark-docx', ['--output' => $outputPath])
        ->assertSuccessful();

    expect(File::exists($outputPath))->toBeTrue();

    $report = json_decode((string) file_get_contents($outputPath), true, 512, JSON_THROW_ON_ERROR);
    expect(is_array($report))->toBeTrue()
        ->and((int) ($report['summary']['document_count'] ?? 0))->toBe(1)
        ->and((int) ($report['summary']['ok_document_count'] ?? 0))->toBe(1)
        ->and((int) ($report['summary']['changed_document_count'] ?? 0))->toBe(1)
        ->and((int) ($report['summary']['regression_document_count'] ?? 0))->toBe(0)
        ->and((float) ($report['documents'][0]['metric_deltas']['hierarchy_confidence'] ?? 0.0))->toBe(0.04)
        ->and((bool) ($report['documents'][0]['has_regression'] ?? true))->toBeFalse()
        ->and($report['coverage']['covered_classes']['title_page_metadata'] ?? [])->toBe(['test_doc'])
        ->and($report['coverage']['missing_classes'] ?? [])->toContain('appendix_section');
});

it('fails with fail-on-regression when hierarchy confidence drops beyond threshold', function () {
    $context = createAbaBenchmarkContext();
    $aba = $context['aba'];
    $attachment = $context['attachment'];
    $user = $context['user'];

    createCompletedBenchmarkRun(
        $aba,
        $attachment,
        $user,
        benchmarkStats(0.90, 0.85, 0.88, 0.86)
    );

    createCompletedBenchmarkRun(
        $aba,
        $attachment,
        $user,
        benchmarkStats(0.87, 0.82, 0.84, 0.80)
    );

    config()->set('aba_docx_benchmark.documents', [[
        'key' => 'regression_doc',
        'label' => 'Regression DOCX',
        'aba_id' => $aba->id,
        'attachment_id' => $attachment->id,
        'characteristics' => ['numbered_chapters_subchapters'],
    ]]);
    config()->set('aba_docx_benchmark.taxonomy', [
        'numbered_chapters_subchapters' => 'Numbered headings',
    ]);
    config()->set('aba_docx_benchmark.regression_threshold', 0.01);

    $outputPath = storage_path('app/aba-benchmarks/test-docx-benchmark-regression.json');
    File::delete($outputPath);

    $this->artisan('aba:benchmark-docx', [
        '--output' => $outputPath,
        '--fail-on-regression' => true,
    ])->assertExitCode(1);

    expect(File::exists($outputPath))->toBeTrue();

    $report = json_decode((string) file_get_contents($outputPath), true, 512, JSON_THROW_ON_ERROR);
    expect((int) ($report['summary']['regression_document_count'] ?? 0))->toBe(1)
        ->and($report['documents'][0]['regression_metrics'] ?? [])->toContain('hierarchy_confidence');
});
