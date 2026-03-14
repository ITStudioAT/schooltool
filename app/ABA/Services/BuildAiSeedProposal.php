<?php

namespace App\ABA\Services;

/**
 * Master-Orchestrator für die KI-gestützte ABA-Seed-Härtungs-Pipeline.
 *
 * Pipeline (sequenziell, kein autonomes Apply):
 *   1. FindSeedOpenIssues   – Issue-Erkennung (pattern-basiert)
 *   2. RunAiSeedResearch    – KI-Recherche pro Issue (AhsSeedResearchAgent)
 *   3. RunAiSeedVerification – KI-Verifikation mit strukturiertem Output (AhsSeedVerificationAgent)
 *   4. RunAiSeedHardeningDraft – KI-Entwurf pro Abschnitt (AhsSeedHardeningAgent)
 *
 * Governance:
 *   - Kein autonomes Überschreiben der Seed-Datei
 *   - Alle Artefakte unter ai/knowledge/aba/sources/proposals/
 *   - Manuelles Review + Apply durch admin erforderlich
 */
class BuildAiSeedProposal
{
    public function __construct(
        private readonly FindSeedOpenIssues $findIssues,
        private readonly RunAiSeedResearch $runResearch,
        private readonly RunAiSeedVerification $runVerification,
        private readonly RunAiSeedHardeningDraft $runHardeningDraft,
    ) {}

    /**
     * @return array{
     *   success: bool,
     *   pipeline_log: array<int, array<string, mixed>>,
     *   proposal_file: string|null,
     *   summary: array<string, mixed>,
     *   error: string|null
     * }
     */
    public function run(): array
    {
        $log = [];
        $startedAt = now();

        // Stage 1: Issue-Erkennung
        $log[] = $this->logStage('issue_detection', 'Erkenne offene Issues im Seed ...');
        $issues = $this->findIssues->find();
        $issueCount = $issues['summary']['total_issues'] ?? 0;

        $log[] = $this->logStageResult('issue_detection', [
            'total_issues' => $issueCount,
            'by_type' => $issues['summary']['by_type'] ?? [],
        ]);

        if ($issueCount === 0) {
            return $this->done($log, null, $startedAt, 'Keine offenen Issues gefunden – Pipeline abgebrochen.');
        }

        // Stage 2: KI-Recherche
        $log[] = $this->logStage('ai_research', "Starte KI-Recherche für {$issueCount} Issues ...");
        $research = $this->runResearch->run();

        if (! $research['success']) {
            return $this->fail($log, 'KI-Recherche fehlgeschlagen: '.($research['error'] ?? 'Unbekannter Fehler'));
        }

        $log[] = $this->logStageResult('ai_research', [
            'issues_processed' => $research['issues_processed'],
            'output_file' => basename($research['output_file'] ?? ''),
        ]);

        // Stage 3: KI-Verifikation
        $log[] = $this->logStage('ai_verification', 'Starte KI-Verifikation ...');
        $verification = $this->runVerification->run($research['output_file']);

        if (! $verification['success']) {
            return $this->fail($log, 'KI-Verifikation fehlgeschlagen: '.($verification['error'] ?? 'Unbekannter Fehler'));
        }

        if ($verification['scope_violations'] > 0) {
            $log[] = $this->logWarning('ai_verification', "⚠ {$verification['scope_violations']} Scope-Verletzung(en) erkannt und herausgefiltert.");
        }

        $log[] = $this->logStageResult('ai_verification', [
            'items_verified' => $verification['items_verified'],
            'scope_violations' => $verification['scope_violations'],
            'output_file' => basename($verification['output_file'] ?? ''),
        ]);

        // Stage 4: KI-Entwurf
        $log[] = $this->logStage('ai_hardening_draft', 'Generiere gehärteten Seed-Entwurf ...');
        $draft = $this->runHardeningDraft->run($verification['output_file']);

        if (! $draft['success']) {
            return $this->fail($log, 'Entwurf-Generierung fehlgeschlagen: '.($draft['error'] ?? 'Unbekannter Fehler'));
        }

        $log[] = $this->logStageResult('ai_hardening_draft', [
            'sections_generated' => $draft['sections_generated'],
            'items_applied' => $draft['items_applied'],
            'items_skipped' => $draft['items_skipped'],
            'output_file' => basename($draft['output_file'] ?? ''),
        ]);

        $summary = [
            'total_issues_found' => $issueCount,
            'issues_researched' => $research['issues_processed'],
            'items_verified' => $verification['items_verified'],
            'scope_violations_filtered' => $verification['scope_violations'],
            'sections_in_draft' => $draft['sections_generated'],
            'items_applied_to_draft' => $draft['items_applied'],
            'items_unresolved' => $draft['items_skipped'],
            'duration_seconds' => now()->diffInSeconds($startedAt),
            'proposal_file' => $draft['output_file'],
        ];

        return $this->done($log, $draft['output_file'], $startedAt, null, $summary);
    }

    /** @param array<string, mixed> $data */
    private function logStage(string $stage, string $message): array
    {
        return ['stage' => $stage, 'event' => 'start', 'message' => $message, 'at' => now()->toIso8601String()];
    }

    /** @param array<string, mixed> $data */
    private function logStageResult(string $stage, array $data): array
    {
        return ['stage' => $stage, 'event' => 'result', 'data' => $data, 'at' => now()->toIso8601String()];
    }

    private function logWarning(string $stage, string $message): array
    {
        return ['stage' => $stage, 'event' => 'warning', 'message' => $message, 'at' => now()->toIso8601String()];
    }

    /**
     * @param  array<int, array<string, mixed>>  $log
     * @param  array<string, mixed>  $summary
     */
    private function done(array $log, ?string $proposalFile, \Carbon\Carbon $startedAt, ?string $note = null, array $summary = []): array
    {
        if (empty($summary)) {
            $summary = ['duration_seconds' => now()->diffInSeconds($startedAt), 'note' => $note];
        }

        return [
            'success' => true,
            'pipeline_log' => $log,
            'proposal_file' => $proposalFile,
            'summary' => $summary,
            'error' => null,
        ];
    }

    /** @param array<int, array<string, mixed>> $log */
    private function fail(array $log, string $message): array
    {
        return [
            'success' => false,
            'pipeline_log' => $log,
            'proposal_file' => null,
            'summary' => [],
            'error' => $message,
        ];
    }
}
