<?php

namespace App\ABA\Services;

use Illuminate\Support\Facades\File;

/**
 * Orchestriert die vollständige Seed-Härtungs-Pipeline (pattern-basiert + KI).
 *
 * Wählt zwischen:
 *   - Standard-Modus: Pattern-basierte Issue-Erkennung + manueller Entwurf
 *   - KI-Modus: BuildAiSeedProposal (Research → Verification → Draft)
 *
 * Schreibt: seed-hardening-report.json
 */
class RunSeedHardening
{
    private string $reportPath;

    public function __construct(
        private readonly FindSeedOpenIssues $findIssues,
        private readonly BuildAiSeedProposal $buildAiProposal,
    ) {
        $this->reportPath = base_path('ai/knowledge/aba/sources/seed-hardening-report.json');
    }

    /**
     * @return array{
     *   success: bool,
     *   mode: string,
     *   report_file: string|null,
     *   summary: array<string, mixed>,
     *   error: string|null
     * }
     */
    public function run(bool $useAi = false): array
    {
        if ($useAi) {
            return $this->runAiPipeline();
        }

        return $this->runPatternPipeline();
    }

    private function runAiPipeline(): array
    {
        $result = $this->buildAiProposal->run();

        $report = [
            'version' => '1.0',
            'generated_at' => now()->toIso8601String(),
            'mode' => 'ai',
            'success' => $result['success'],
            'proposal_file' => $result['proposal_file'] ? basename($result['proposal_file']) : null,
            'summary' => $result['summary'],
            'pipeline_log' => $result['pipeline_log'],
            'error' => $result['error'],
        ];

        File::put(
            $this->reportPath,
            json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n",
        );

        return [
            'success' => $result['success'],
            'mode' => 'ai',
            'report_file' => $this->reportPath,
            'summary' => $result['summary'],
            'error' => $result['error'],
        ];
    }

    private function runPatternPipeline(): array
    {
        $issues = $this->findIssues->find();

        $report = [
            'version' => '1.0',
            'generated_at' => now()->toIso8601String(),
            'mode' => 'pattern',
            'success' => true,
            'summary' => $issues['summary'],
            'issues_file' => 'seed-open-issues.json',
            'note' => 'Pattern-Analyse abgeschlossen. Manuelle Überprüfung und KI-Pipeline empfohlen.',
        ];

        File::put(
            $this->reportPath,
            json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n",
        );

        return [
            'success' => true,
            'mode' => 'pattern',
            'report_file' => $this->reportPath,
            'summary' => $issues['summary'],
            'error' => null,
        ];
    }
}
