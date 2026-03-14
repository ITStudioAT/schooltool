<?php

namespace App\ABA\Services;

use App\Ai\Agents\AhsSeedVerificationAgent;
use Illuminate\Support\Facades\File;
use Throwable;

/**
 * Verifiziert Recherche-Ergebnisse mit dem strukturierten Verifikations-Agenten.
 *
 * Liest: ai-seed-research-YYYY-MM-DD.json (neueste Datei im proposals-Verzeichnis)
 * Schreibt: ai-seed-verification-YYYY-MM-DD.json
 *
 * Governance: Nur Entwurf – keine autonome Seed-Änderung.
 */
class RunAiSeedVerification
{
    private string $proposalsDir;

    public function __construct()
    {
        $this->proposalsDir = base_path('ai/knowledge/aba/sources/proposals');
    }

    /**
     * @return array{
     *   success: bool,
     *   output_file: string|null,
     *   items_verified: int,
     *   scope_violations: int,
     *   results: array<int, array<string, mixed>>,
     *   error: string|null
     * }
     */
    public function run(?string $researchFile = null): array
    {
        $researchFile ??= $this->findLatestResearchFile();

        if ($researchFile === null) {
            return $this->fail('Kein Recherche-Ergebnis gefunden. Bitte zuerst RunAiSeedResearch ausführen.');
        }

        if (! File::exists($researchFile)) {
            return $this->fail("Recherche-Datei nicht gefunden: {$researchFile}");
        }

        try {
            $research = json_decode(File::get($researchFile), associative: true, flags: JSON_THROW_ON_ERROR);
        } catch (Throwable $e) {
            return $this->fail('Recherche-Datei konnte nicht gelesen werden: '.$e->getMessage());
        }

        $items = array_filter(
            $research['results'] ?? [],
            fn (array $r) => $r['status'] === 'researched' && $r['agent_response'] !== null,
        );

        if (empty($items)) {
            return $this->fail('Keine verifizierbaren Recherche-Ergebnisse gefunden.');
        }

        $results = [];
        $scopeViolations = 0;

        foreach (array_values($items) as $item) {
            $result = $this->verifyItem($item);
            $results[] = $result;

            if ($result['scope_violation'] ?? false) {
                $scopeViolations++;
            }
        }

        $outputPath = $this->writeProposal($results, $research['seed_file'] ?? '');

        return [
            'success' => true,
            'output_file' => $outputPath,
            'items_verified' => count($results),
            'scope_violations' => $scopeViolations,
            'results' => $results,
            'error' => null,
        ];
    }

    /** @param array<string, mixed> $item */
    private function verifyItem(array $item): array
    {
        // Editorial meta items (audit blockquotes, correction markers) are not fachliche claims.
        // They do not need AI verification – they are handled by ExtractSeedEditorialNotes.
        if (($item['issue_type'] ?? '') === 'editorial_meta') {
            return [
                'issue_id' => $item['issue_id'],
                'issue_type' => $item['issue_type'],
                'section' => $item['section'],
                'original_text' => $item['original_text'],
                'research_summary' => $item['agent_response'],
                'verification_status' => 'unverified',
                'confidence' => 'high',
                'source_refs' => [],
                'reasoning_summary' => 'Redaktioneller Meta-Inhalt (Audit-Blockquote oder Korrektur-Marker). Keine inhaltliche Verifikation erforderlich – in seed-editorial-notes.json dokumentiert.',
                'recommended_rewrite' => null,
                'unresolved_points' => 'Redaktionelle Bereinigung via ExtractSeedEditorialNotes.',
                'scope_violation' => false,
                'skipped_reason' => 'editorial_meta',
                'verified_at' => now()->toIso8601String(),
                'error' => null,
            ];
        }

        $prompt = $this->buildPrompt($item);

        try {
            $response = (new AhsSeedVerificationAgent)->prompt($prompt);

            return [
                'issue_id' => $item['issue_id'],
                'issue_type' => $item['issue_type'],
                'section' => $item['section'],
                'original_text' => $item['original_text'],
                'research_summary' => $item['agent_response'],
                'verification_status' => $response['verification_status'],
                'confidence' => $response['confidence'],
                'source_refs' => $response['source_refs'],
                'reasoning_summary' => $response['reasoning_summary'],
                'recommended_rewrite' => $response['recommended_rewrite'],
                'unresolved_points' => $response['unresolved_points'],
                'scope_violation' => $response['scope_violation'],
                'verified_at' => now()->toIso8601String(),
                'error' => null,
            ];
        } catch (Throwable $e) {
            return [
                'issue_id' => $item['issue_id'],
                'issue_type' => $item['issue_type'],
                'section' => $item['section'],
                'original_text' => $item['original_text'],
                'research_summary' => $item['agent_response'],
                'verification_status' => null,
                'confidence' => null,
                'source_refs' => [],
                'reasoning_summary' => null,
                'recommended_rewrite' => null,
                'unresolved_points' => null,
                'scope_violation' => false,
                'verified_at' => now()->toIso8601String(),
                'error' => $e->getMessage(),
            ];
        }
    }

    /** @param array<string, mixed> $item */
    private function buildPrompt(array $item): string
    {
        return <<<PROMPT
        ISSUE AUS DEM ABA-SEED:
        ID: {$item['issue_id']}
        Typ: {$item['issue_type']}
        Abschnitt: {$item['section']}
        Originaltext: {$item['original_text']}

        RECHERCHE-ERGEBNIS DES VORHERIGEN AGENTEN:
        {$item['agent_response']}

        AUFGABE:
        Verifiziere den Originaltext und das Recherche-Ergebnis gemäß deinen Anweisungen.
        Gib strukturierte Verifikationsdaten zurück.
        PROMPT;
    }

    private function findLatestResearchFile(): ?string
    {
        if (! File::isDirectory($this->proposalsDir)) {
            return null;
        }

        $files = collect(File::files($this->proposalsDir))
            ->filter(fn ($f) => str_starts_with($f->getFilename(), 'ai-seed-research-'))
            ->sortByDesc(fn ($f) => $f->getMTime())
            ->values();

        return $files->isNotEmpty() ? $files->first()->getPathname() : null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $results
     */
    private function writeProposal(array $results, string $seedFile): string
    {
        File::ensureDirectoryExists($this->proposalsDir);

        $filename = 'ai-seed-verification-'.now()->toDateString().'.json';
        $outputPath = $this->proposalsDir.'/'.$filename;

        $byStatus = array_count_values(
            array_map(fn ($r) => $r['verification_status'] ?? 'error', $results),
        );

        $data = [
            'version' => '1.0',
            'generated_at' => now()->toIso8601String(),
            'seed_file' => $seedFile,
            'stage' => 'verification',
            'governance' => [
                'scope' => 'AHS only',
                'auto_apply' => false,
                'requires_review' => true,
            ],
            'summary' => [
                'total' => count($results),
                'by_status' => $byStatus,
                'scope_violations' => count(array_filter($results, fn ($r) => $r['scope_violation'] ?? false)),
                'errors' => count(array_filter($results, fn ($r) => $r['error'] !== null)),
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
            'items_verified' => 0,
            'scope_violations' => 0,
            'results' => [],
            'error' => $message,
        ];
    }
}
