<?php

namespace App\Services;

use App\Jobs\ABA\ProcessAbaParselExtractionRunJob;
use App\Models\Aba;
use App\Models\AbaAnalysisRun;
use App\Models\User;

class AbaParselExtractionService
{
    private const EXTRACTION_OPTIONS_KEY = '_extraction_options';

    public function __construct(
        private readonly AbaSectionRuleProvider $sectionRuleProvider,
        private readonly AbaParselDocumentExtractor $documentExtractor,
        private readonly AbaSectionMatcher $sectionMatcher,
        private readonly AbaExtractionResultBuilder $resultBuilder,
        private readonly AbaExtractionPersister $persister,
    ) {}

    public function latest(Aba $aba): ?AbaAnalysisRun
    {
        return AbaAnalysisRun::query()
            ->parselExtraction()
            ->where('aba_id', $aba->id)
            ->latest('id')
            ->first();
    }

    public function start(User $user, Aba $aba): AbaAnalysisRun
    {
        $activeRun = AbaAnalysisRun::query()
            ->parselExtraction()
            ->where('aba_id', $aba->id)
            ->whereIn('status', [AbaAnalysisRun::STATUS_STARTED, AbaAnalysisRun::STATUS_RUNNING])
            ->latest('id')
            ->first();

        if ($activeRun) {
            return $activeRun->fresh();
        }

        $attachment = $aba->mainDocument()->first();
        if (! $attachment) {
            $run = $this->persister->startRun(
                $user,
                $aba,
                null,
                $this->withParselOptions([]),
                AbaAnalysisRun::PARSEL_EXTRACTION_STATUS_MESSAGE_PREFIX.' wurde gestartet.',
            );

            return $this->persister->markFailed($run, 'Kein Hauptdokument vorhanden.', $this->withParselOptions([
                'sections' => [],
                'missing_required_section_keys' => $this->sectionRuleProvider->requiredSectionKeys(),
                'found_optional_section_keys' => [],
                'uncertain_matches' => [],
                'unmatched_blocks_count' => 0,
                'warnings' => [],
                'errors' => ['Kein Hauptdokument vorhanden.'],
            ]));
        }

        $run = $this->persister->startRun(
            $user,
            $aba,
            $attachment,
            $this->withParselOptions([]),
            AbaAnalysisRun::PARSEL_EXTRACTION_STATUS_MESSAGE_PREFIX.' wurde gestartet.',
        );

        if ($this->shouldRunSynchronously()) {
            $this->processRun($run->id);

            return $run->fresh('results');
        }

        ProcessAbaParselExtractionRunJob::dispatch($run->id);

        return $run;
    }

    public function processRun(int $runId): void
    {
        $run = AbaAnalysisRun::query()
            ->with(['aba.mainDocument', 'attachment'])
            ->find($runId);

        if (! $run) {
            return;
        }

        if (! in_array($run->status, [AbaAnalysisRun::STATUS_STARTED, AbaAnalysisRun::STATUS_RUNNING], true)) {
            return;
        }

        $this->persister->markRunning($run);

        try {
            $attachment = $run->attachment ?? $run->aba?->mainDocument()->first();
            if (! $attachment) {
                $this->persister->markFailed($run, 'Das Hauptdokument ist nicht mehr verfuegbar.', $this->withParselOptions([
                    'sections' => [],
                    'missing_required_section_keys' => $this->sectionRuleProvider->requiredSectionKeys(),
                    'found_optional_section_keys' => [],
                    'uncertain_matches' => [],
                    'unmatched_blocks_count' => 0,
                    'warnings' => [],
                    'errors' => ['Das Hauptdokument ist nicht mehr verfuegbar.'],
                ]));

                return;
            }

            $ruleDefinition = $this->sectionRuleProvider->definition();
            $documentExtraction = $this->documentExtractor->extract($attachment);
            $matchedSections = $this->sectionMatcher->match(
                is_array($ruleDefinition['sections'] ?? null) ? $ruleDefinition['sections'] : [],
                is_array($documentExtraction['sections'] ?? null) ? $documentExtraction['sections'] : [],
            );
            $summary = $this->withParselOptions(
                $this->resultBuilder->build($ruleDefinition, $documentExtraction, $matchedSections),
            );

            $this->persister->markCompleted($run, $documentExtraction, $summary);
        } catch (\Throwable $exception) {
            $message = trim($exception->getMessage()) !== '' ? $exception->getMessage() : 'Extraktion 2 fehlgeschlagen.';

            $this->persister->markFailed($run, $message, $this->withParselOptions([
                'sections' => [],
                'missing_required_section_keys' => $this->sectionRuleProvider->requiredSectionKeys(),
                'found_optional_section_keys' => [],
                'uncertain_matches' => [],
                'unmatched_blocks_count' => 0,
                'warnings' => [],
                'errors' => [$message],
            ]));
        }
    }

    private function shouldRunSynchronously(): bool
    {
        return trim((string) config('queue.default', 'sync')) === 'sync';
    }

    /**
     * @param  array<string,mixed>  $summary
     * @return array<string,mixed>
     */
    private function withParselOptions(array $summary): array
    {
        $summary[self::EXTRACTION_OPTIONS_KEY] = [
            'engine' => 'parsel',
            'label' => 'Extraktion 2',
            'package' => 'shipfastlabs/parsel',
            'overwrite_existing_fields' => false,
        ];

        return $summary;
    }
}
