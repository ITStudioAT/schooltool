<?php

namespace App\ABA\Services;

use Illuminate\Support\Facades\File;

/**
 * Gibt alle Claims zurück, die eine Aktion erfordern (needs_review, stale etc.),
 * angereichert mit Claim-Inhalt aus ExtractKnowledgeClaims.
 *
 * @return array{
 *   found: bool,
 *   total: int,
 *   claims: array<int, array<string, mixed>>,
 *   error: string|null
 * }
 */
class ReadOpenClaims
{
    private string $statePath;

    public function __construct(private readonly ExtractKnowledgeClaims $extractor)
    {
        $this->statePath = base_path('ai/knowledge/aba/sources/seed-review-state.json');
    }

    public function read(): array
    {
        if (! File::exists($this->statePath)) {
            return [
                'found' => false,
                'total' => 0,
                'claims' => [],
                'error' => 'seed-review-state.json nicht gefunden.',
            ];
        }

        try {
            $stateData = json_decode(File::get($this->statePath), associative: true, flags: JSON_THROW_ON_ERROR);
            $stateByKey = collect($stateData['claims'] ?? [])->keyBy('claim_key');

            $allClaims = $this->extractor->fromKnowledgeSeedReport();
            $openClaims = [];

            foreach ($allClaims as $claim) {
                $state = $stateByKey->get($claim->claim_key, []);
                $status = $state['review_status'] ?? 'unknown';
                $risk = $state['change_risk'] ?? 'low';

                if (! in_array($status, ['needs_review', 'stale', 'draft_update', 'unverifiable'])) {
                    continue;
                }

                $openClaims[] = [
                    'claim_key' => $claim->claim_key,
                    'topic' => $claim->topic,
                    'statement' => $claim->statement,
                    'classification' => $claim->classification,
                    'applies_to' => $claim->applies_to,
                    'is_uncertain' => $claim->is_uncertain,
                    'review_status' => $status,
                    'change_risk' => $risk,
                    'last_verified_at' => $state['last_verified_at'] ?? null,
                    'notes' => $state['notes'] ?? null,
                    'source_refs' => $claim->source_refs,
                ];
            }

            usort($openClaims, function (array $a, array $b): int {
                $riskOrder = ['high' => 0, 'medium' => 1, 'low' => 2];
                $statusOrder = ['stale' => 0, 'needs_review' => 1, 'unverifiable' => 2, 'draft_update' => 3];

                $riskDiff = ($riskOrder[$a['change_risk']] ?? 2) - ($riskOrder[$b['change_risk']] ?? 2);
                if ($riskDiff !== 0) {
                    return $riskDiff;
                }

                return ($statusOrder[$a['review_status']] ?? 9) - ($statusOrder[$b['review_status']] ?? 9);
            });

            return [
                'found' => true,
                'total' => count($openClaims),
                'claims' => $openClaims,
                'error' => null,
            ];
        } catch (\Throwable $e) {
            return [
                'found' => true,
                'total' => 0,
                'claims' => [],
                'error' => 'Fehler beim Laden der offenen Claims: '.$e->getMessage(),
            ];
        }
    }
}
