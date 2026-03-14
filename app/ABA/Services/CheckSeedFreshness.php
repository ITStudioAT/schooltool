<?php

namespace App\ABA\Services;

use App\ABA\Knowledge\SeedSourceRegistry;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;

/**
 * Prüft, ob die Claims in seed-review-state.json noch aktuell sind.
 *
 * Logik:
 *  - Claims mit review_status needs_review/stale/draft_update/superseded → direkt auffällig
 *  - Claims mit review_status verified → Fälligkeitsprüfung via source refresh_frequency_days
 *
 * @return array{
 *   checked_at: string,
 *   total_claims: int,
 *   verified_count: int,
 *   needs_review_count: int,
 *   stale_count: int,
 *   verified: array<int, array<string, mixed>>,
 *   needs_review: array<int, array<string, mixed>>,
 *   stale: array<int, array<string, mixed>>
 * }
 */
class CheckSeedFreshness
{
    private const STALE_STATUSES = ['stale', 'superseded', 'draft_update'];

    private const NEEDS_REVIEW_STATUSES = ['needs_review', 'unverifiable'];

    public function __construct(private readonly SeedSourceRegistry $registry) {}

    public function check(): array
    {
        $state = $this->loadReviewState();
        $now = Carbon::now();

        $stale = [];
        $needsReview = [];
        $verified = [];

        foreach ($state['claims'] as $claim) {
            $status = $claim['review_status'];

            if (in_array($status, self::STALE_STATUSES, strict: true)) {
                $stale[] = $claim;

                continue;
            }

            if (in_array($status, self::NEEDS_REVIEW_STATUSES, strict: true)) {
                $needsReview[] = $claim;

                continue;
            }

            // Verified claims: check if any source has exceeded its refresh cycle
            $overdueSource = $this->findOverdueSource($claim['source_refs'], $claim['last_verified_at'], $now);

            if ($overdueSource !== null) {
                $stale[] = array_merge($claim, [
                    'stale_reason' => "source_refresh_overdue:{$overdueSource}",
                ]);
            } else {
                $verified[] = $claim;
            }
        }

        return [
            'checked_at' => $now->toIso8601String(),
            'total_claims' => count($state['claims']),
            'verified_count' => count($verified),
            'needs_review_count' => count($needsReview),
            'stale_count' => count($stale),
            'verified' => $verified,
            'needs_review' => $needsReview,
            'stale' => $stale,
        ];
    }

    private function findOverdueSource(array $sourceRefs, string $lastVerifiedAt, Carbon $now): ?string
    {
        $lastVerified = Carbon::parse($lastVerifiedAt);

        foreach ($sourceRefs as $sourceId) {
            $source = $this->registry->findById($sourceId);
            if ($source === null) {
                continue;
            }

            $dueAt = $lastVerified->copy()->addDays((int) $source['refresh_frequency_days']);
            if ($dueAt->lt($now)) {
                return $sourceId;
            }
        }

        return null;
    }

    private function loadReviewState(): array
    {
        $path = base_path('ai/knowledge/aba/sources/seed-review-state.json');

        return json_decode(File::get($path), associative: true);
    }
}
