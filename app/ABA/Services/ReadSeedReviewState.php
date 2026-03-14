<?php

namespace App\ABA\Services;

use Illuminate\Support\Facades\File;

/**
 * Liest und aggregiert seed-review-state.json.
 *
 * @return array{
 *   found: bool,
 *   last_rebuilt_at: string|null,
 *   total_claims: int,
 *   by_status: array<string, int>,
 *   high_risk_claims: array<int, array<string, mixed>>,
 *   error: string|null
 * }
 */
class ReadSeedReviewState
{
    private string $filePath;

    public function __construct()
    {
        $this->filePath = base_path('ai/knowledge/aba/sources/seed-review-state.json');
    }

    public function read(): array
    {
        if (! File::exists($this->filePath)) {
            return $this->notFound();
        }

        try {
            $data = json_decode(File::get($this->filePath), associative: true, flags: JSON_THROW_ON_ERROR);
            $claims = $data['claims'] ?? [];

            $byStatus = [];
            $highRisk = [];

            foreach ($claims as $claim) {
                $status = $claim['review_status'] ?? 'unknown';
                $byStatus[$status] = ($byStatus[$status] ?? 0) + 1;

                if (($claim['change_risk'] ?? '') === 'high' && $status !== 'verified') {
                    $highRisk[] = [
                        'claim_key' => $claim['claim_key'],
                        'review_status' => $claim['review_status'],
                        'change_risk' => $claim['change_risk'],
                        'last_verified_at' => $claim['last_verified_at'] ?? null,
                    ];
                }
            }

            return [
                'found' => true,
                'last_rebuilt_at' => $data['last_rebuilt_at'] ?? null,
                'total_claims' => count($claims),
                'by_status' => $byStatus,
                'high_risk_claims' => $highRisk,
                'error' => null,
            ];
        } catch (\Throwable $e) {
            return [
                'found' => true,
                'last_rebuilt_at' => null,
                'total_claims' => 0,
                'by_status' => [],
                'high_risk_claims' => [],
                'error' => 'seed-review-state.json konnte nicht gelesen werden: '.$e->getMessage(),
            ];
        }
    }

    private function notFound(): array
    {
        return [
            'found' => false,
            'last_rebuilt_at' => null,
            'total_claims' => 0,
            'by_status' => [],
            'high_risk_claims' => [],
            'error' => 'seed-review-state.json nicht gefunden.',
        ];
    }
}
