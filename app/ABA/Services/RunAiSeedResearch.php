<?php

namespace App\ABA\Services;

use App\Ai\Agents\AhsSeedResearchAgent;
use Illuminate\Support\Facades\File;
use Throwable;

/**
 * Führt den AHS-Seed-Recherche-Agenten für alle offenen Issues durch.
 *
 * Liest: seed-open-issues.json + source-registry.json (für Kontext)
 * Schreibt: ai/knowledge/aba/sources/proposals/ai-seed-research-YYYY-MM-DD.json
 *
 * Governance: Keine autonome Seed-Änderung. Nur Entwurf für manuelle Review.
 */
class RunAiSeedResearch
{
    private string $issuesPath;

    private string $registryPath;

    private string $proposalsDir;

    public function __construct()
    {
        $this->issuesPath = base_path('ai/knowledge/aba/sources/seed-open-issues.json');
        $this->registryPath = base_path('ai/knowledge/aba/sources/source-registry.json');
        $this->proposalsDir = base_path('ai/knowledge/aba/sources/proposals');
    }

    /**
     * @return array{
     *   success: bool,
     *   output_file: string|null,
     *   issues_processed: int,
     *   results: array<int, array<string, mixed>>,
     *   error: string|null
     * }
     */
    public function run(): array
    {
        if (! File::exists($this->issuesPath)) {
            return $this->fail('seed-open-issues.json nicht gefunden. Bitte zuerst FindSeedOpenIssues ausführen.');
        }

        try {
            $issues = json_decode(File::get($this->issuesPath), associative: true, flags: JSON_THROW_ON_ERROR);
        } catch (Throwable $e) {
            return $this->fail('seed-open-issues.json konnte nicht gelesen werden: '.$e->getMessage());
        }

        $openIssues = array_filter(
            $issues['issues'] ?? [],
            fn (array $issue) => $issue['resolution'] === null,
        );

        if (empty($openIssues)) {
            return [
                'success' => true,
                'output_file' => null,
                'issues_processed' => 0,
                'results' => [],
                'error' => null,
            ];
        }

        $sourceContext = $this->buildSourceContext();
        $results = [];

        foreach (array_values($openIssues) as $issue) {
            $results[] = $this->processIssue($issue, $sourceContext);
        }

        $outputPath = $this->writeProposal($results, $issues['seed_file'] ?? '');

        return [
            'success' => true,
            'output_file' => $outputPath,
            'issues_processed' => count($results),
            'results' => $results,
            'error' => null,
        ];
    }

    /** @param array<string, mixed> $issue */
    private function processIssue(array $issue, string $sourceContext): array
    {
        $prompt = $this->buildPrompt($issue, $sourceContext);

        try {
            $response = (new AhsSeedResearchAgent)->prompt($prompt);

            return [
                'issue_id' => $issue['id'],
                'issue_type' => $issue['type'],
                'issue_severity' => $issue['severity'],
                'line_number' => $issue['line_number'],
                'original_text' => $issue['line_text'],
                'section' => $issue['section'],
                'agent_response' => (string) $response,
                'status' => 'researched',
                'researched_at' => now()->toIso8601String(),
                'error' => null,
            ];
        } catch (Throwable $e) {
            return [
                'issue_id' => $issue['id'],
                'issue_type' => $issue['type'],
                'issue_severity' => $issue['severity'],
                'line_number' => $issue['line_number'],
                'original_text' => $issue['line_text'],
                'section' => $issue['section'],
                'agent_response' => null,
                'status' => 'error',
                'researched_at' => now()->toIso8601String(),
                'error' => $e->getMessage(),
            ];
        }
    }

    /** @param array<string, mixed> $issue */
    private function buildPrompt(array $issue, string $sourceContext): string
    {
        return <<<PROMPT
        OFFENES ISSUE AUS DEM ABA-SEED-DOKUMENT:
        ID: {$issue['id']}
        Typ: {$issue['type']}
        Abschnitt: {$issue['section']}
        Zeile {$issue['line_number']}: {$issue['line_text']}
        Erkanntes Muster: {$issue['matched_pattern']}

        VERFÜGBARER QUELLENINHALT (ausschließlich diese Quellen verwenden):
        {$sourceContext}

        AUFGABE:
        Analysiere das Issue und formuliere eine faktisch belegte Antwort gemäß deinen Anweisungen.
        PROMPT;
    }

    private function buildSourceContext(): string
    {
        if (! File::exists($this->registryPath)) {
            return 'Keine Quellen-Registry verfügbar.';
        }

        try {
            $registry = json_decode(File::get($this->registryPath), associative: true, flags: JSON_THROW_ON_ERROR);
            $lines = ['Autorisierte Quellen für AHS-ABA (nur diese verwenden):'];

            foreach ($registry['sources'] ?? [] as $source) {
                $lines[] = sprintf(
                    '- %s: %s (URL: %s)',
                    $source['source_id'],
                    $source['title'],
                    $source['url'] ?? 'noch nicht verfügbar',
                );
            }

            return implode("\n", $lines);
        } catch (Throwable) {
            return 'Quellen-Registry konnte nicht gelesen werden.';
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $results
     */
    private function writeProposal(array $results, string $seedFile): string
    {
        File::ensureDirectoryExists($this->proposalsDir);

        $filename = 'ai-seed-research-'.now()->toDateString().'.json';
        $outputPath = $this->proposalsDir.'/'.$filename;

        $data = [
            'version' => '1.0',
            'generated_at' => now()->toIso8601String(),
            'seed_file' => $seedFile,
            'stage' => 'research',
            'governance' => [
                'scope' => 'AHS only',
                'auto_apply' => false,
                'requires_review' => true,
            ],
            'summary' => [
                'total' => count($results),
                'researched' => count(array_filter($results, fn ($r) => $r['status'] === 'researched')),
                'errors' => count(array_filter($results, fn ($r) => $r['status'] === 'error')),
            ],
            'results' => $results,
        ];

        File::put(
            $outputPath,
            json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n",
        );

        return $outputPath;
    }

    private function fail(string $message): array
    {
        return [
            'success' => false,
            'output_file' => null,
            'issues_processed' => 0,
            'results' => [],
            'error' => $message,
        ];
    }
}
