<?php

use App\Models\Aba;
use App\Models\AbaAnalysisResult;
use App\Models\AbaAnalysisRun;
use App\Models\AbaAttachment;
use App\Models\School;
use App\Models\Schoolyear;
use App\Models\User;
use App\Services\AbaDocumentExtractionService;
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
 * @param  array<string,mixed>  $summary
 */
function createCompletedBenchmarkRun(Aba $aba, AbaAttachment $attachment, User $user, array $summary): AbaAnalysisRun
{
    $run = AbaAnalysisRun::query()->create([
        'aba_id' => $aba->id,
        'aba_attachment_id' => $attachment->id,
        'created_by_user_id' => $user->id,
        'status' => AbaAnalysisRun::STATUS_COMPLETED,
        'status_message' => AbaAnalysisRun::EXTRACTION_STATUS_MESSAGE_PREFIX.' abgeschlossen.',
        'source_original_name' => $attachment->original_name,
        'source_path' => $attachment->path,
        'source_mime_type' => $attachment->mime_type,
        'started_at' => now()->subMinute(),
        'running_at' => now()->subSeconds(30),
        'completed_at' => now(),
        'summary' => $summary,
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
        'metadata' => ['matched_rule_keys' => ['title_page']],
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
        'metadata' => ['matched_rule_keys' => ['main_body']],
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
        'metadata' => ['matched_rule_keys' => ['main_body']],
    ]);

    return $run;
}

/**
 * @return array<string,mixed>
 */
function benchmarkSummary(int $foundRequiredCount, int $missingRequiredCount, int $unmatchedBlocksCount = 0, int $uncertainMatchesCount = 0): array
{
    return [
        'found_required_section_keys' => $foundRequiredCount > 0
            ? array_map(fn (int $index): string => 'required_'.$index, range(1, $foundRequiredCount))
            : [],
        'missing_required_section_keys' => $missingRequiredCount > 0
            ? array_map(fn (int $index): string => 'missing_'.$index, range(1, $missingRequiredCount))
            : [],
        'found_optional_section_keys' => ['abstract'],
        'uncertain_matches' => array_fill(0, $uncertainMatchesCount, ['key' => 'main_body']),
        'unmatched_blocks_count' => $unmatchedBlocksCount,
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
        benchmarkSummary(3, 1)
    );

    createCompletedBenchmarkRun(
        $aba,
        $attachment,
        $user,
        benchmarkSummary(4, 0)
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
        ->and((float) ($report['documents'][0]['metric_deltas']['required_section_coverage'] ?? 0.0))->toBe(0.25)
        ->and((bool) ($report['documents'][0]['has_regression'] ?? true))->toBeFalse()
        ->and($report['coverage']['covered_classes']['title_page_metadata'] ?? [])->toBe(['test_doc'])
        ->and($report['coverage']['missing_classes'] ?? [])->toContain('appendix_section');
});

it('fails with fail-on-regression when required section coverage drops beyond threshold', function () {
    $context = createAbaBenchmarkContext();
    $aba = $context['aba'];
    $attachment = $context['attachment'];
    $user = $context['user'];

    createCompletedBenchmarkRun(
        $aba,
        $attachment,
        $user,
        benchmarkSummary(4, 0)
    );

    createCompletedBenchmarkRun(
        $aba,
        $attachment,
        $user,
        benchmarkSummary(3, 1)
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
        ->and($report['documents'][0]['regression_metrics'] ?? [])->toContain('required_section_coverage');
});

it('uses the current document extraction pipeline for fresh benchmark runs', function () {
    $context = createAbaBenchmarkContext();
    $aba = $context['aba'];
    $attachment = $context['attachment'];
    $user = $context['user'];
    $baselineRun = createCompletedBenchmarkRun($aba, $attachment, $user, benchmarkSummary(3, 1));

    $this->mock(AbaDocumentExtractionService::class, function ($mock): void {
        $mock->shouldReceive('processRun')
            ->once()
            ->withArgs(fn (int $runId): bool => $runId > 0)
            ->andReturnUsing(function (int $runId): void {
                $run = AbaAnalysisRun::query()->findOrFail($runId);
                $run->forceFill([
                    'status' => AbaAnalysisRun::STATUS_COMPLETED,
                    'completed_at' => now(),
                    'summary' => benchmarkSummary(4, 0),
                ])->save();

                $run->results()->create([
                    'aba_id' => $run->aba_id,
                    'aba_attachment_id' => $run->aba_attachment_id,
                    'section_type' => 'title_page',
                    'section_title' => 'Titelseite',
                    'extracted_text' => 'Titelblatt Inhalte',
                    'sort_order' => 0,
                    'hierarchy_level' => 1,
                    'start_line' => 1,
                    'end_line' => 6,
                    'metadata' => ['matched_rule_keys' => ['title_page']],
                ]);
            });
    });

    config()->set('aba_docx_benchmark.documents', [[
        'key' => 'fresh_pipeline',
        'label' => 'Fresh Pipeline',
        'aba_id' => $aba->id,
        'attachment_id' => $attachment->id,
        'characteristics' => ['title_page_metadata'],
    ]]);
    config()->set('aba_docx_benchmark.taxonomy', [
        'title_page_metadata' => 'Title page metadata',
    ]);

    $outputPath = storage_path('app/aba-benchmarks/test-docx-benchmark-fresh.json');
    File::delete($outputPath);

    $this->artisan('aba:benchmark-docx', [
        '--run' => true,
        '--output' => $outputPath,
    ])->assertSuccessful();

    $report = json_decode((string) file_get_contents($outputPath), true, 512, JSON_THROW_ON_ERROR);
    expect($report['mode'] ?? null)->toBe('rerun')
        ->and($report['documents'][0]['baseline_run']['run_id'] ?? null)->toBe($baselineRun->id)
        ->and($report['documents'][0]['current_run']['run_id'] ?? null)->not->toBe($baselineRun->id)
        ->and(AbaAnalysisRun::query()->conventionalExtraction()->count())->toBe(2);
});
