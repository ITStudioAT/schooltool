<?php

namespace App\Services\MultipleChoice;

use App\Models\McAttempt;
use App\Models\McTest;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class McAttemptActivityService
{
    /** @param list<array<string, mixed>> $events */
    public function record(McAttempt $attempt, string $session, array $events, ?int $version = null): void
    {
        DB::transaction(function () use ($attempt, $session, $events, $version): void {
            $test = McTest::query()->lockForUpdate()->findOrFail($attempt->test_id);
            $attempt = $test->attempts()->lockForUpdate()->findOrFail($attempt->id);
            abort_if((int) ($attempt->control()['version'] ?? 0) !== ($version ?? 0), 409, 'Dieser Versuch wurde zurückgesetzt. Bitte lade den Test erneut.');
            if ($test->status !== 'published') {
                throw ValidationException::withMessages(['activity' => 'Der Test ist derzeit nicht freigegeben.']);
            }
            if (! $test->exam_mode || $attempt->started_at === null) {
                throw ValidationException::withMessages(['activity' => 'Das Protokoll ist nur während eines begonnenen Tests im Prüfungsmodus verfügbar.']);
            }
            if ($test->paused_at !== null || $attempt->individualPausedAt() !== null || $attempt->completed_at !== null) {
                return;
            }
            $end = $attempt->effectiveDeadline($test);
            if ($end === null || ! $end->isFuture()) {
                $this->finish($attempt, $end ?? CarbonImmutable::now());

                return;
            }
            $state = $attempt->activity_state ?? [];
            if (! isset($state['sessions'][$session]) && count($state['sessions'] ?? []) >= 50) {
                throw ValidationException::withMessages(['activity' => 'Zu viele geöffnete Testfenster. Bitte schließen Sie überzählige Fenster.']);
            }
            usort($events, fn (array $left, array $right): int => $left['sequence'] <=> $right['sequence']);
            foreach ($events as $event) {
                $previous = $state['sessions'][$session] ?? [];
                if ($event['sequence'] <= ($previous['sequence'] ?? 0)) {
                    continue;
                }
                $at = CarbonImmutable::parse($event['at'])->min(CarbonImmutable::now())->max($attempt->started_at);
                $resumed = collect($test->pause_history ?? [])->last()['resumed_at'] ?? null;
                $reopened = collect($attempt->reopen_history ?? [])->last()['reopened_at'] ?? null;
                if (($resumed !== null && $at->lessThan(CarbonImmutable::parse($resumed))) || ($reopened !== null && $at->lessThan(CarbonImmutable::parse($reopened)))) {
                    continue;
                }
                if (isset($state['last_event_at'])) {
                    $at = $at->max(CarbonImmutable::parse($state['last_event_at']));
                }
                $away = ! $event['visible'] || ! $event['focused'];
                if (($previous['fullscreen'] ?? false) && ! $event['fullscreen']) {
                    $state['fullscreen_exit_count'] = ($state['fullscreen_exit_count'] ?? 0) + 1;
                    $state['fullscreen_exits'][] = $at->toIso8601String();
                    $state['fullscreen_exits'] = array_slice($state['fullscreen_exits'], -2000);
                }
                $state['sessions'][$session] = [
                    'sequence' => $event['sequence'], 'away' => $away,
                    'fullscreen' => $event['fullscreen'], 'at' => $at->toIso8601String(),
                ];
                $present = collect($state['sessions'])->contains(fn (array $window): bool => ! $window['away'] && CarbonImmutable::parse($window['at'])->greaterThanOrEqualTo($at->subSeconds(30)));
                if (! $present && $away && ! isset($state['away_since'])) {
                    $state['away_since'] = $at->toIso8601String();
                    $state['away_count'] = ($state['away_count'] ?? 0) + 1;
                    $state['reason'] = ! $event['visible'] ? 'hidden' : 'unfocused';
                }
                if ($present && isset($state['away_since'])) {
                    $state = $this->closePeriod($state, $at, 'returned');
                }
                $state['last_event_at'] = $at->toIso8601String();
                $state['last_seen_at'] = CarbonImmutable::now()->toIso8601String();
            }
            $this->persist($attempt, $state);
        }, 3);
    }

    public function finish(McAttempt $attempt, CarbonImmutable $at): void
    {
        if ($attempt->activity_state === null) {
            return;
        }
        $state = $this->closePeriod($attempt->activity_state, $at, 'stopped');
        $state['sessions'] = [];
        $this->persist($attempt, $state);
    }

    /** @return array<string, mixed> */
    public function summary(McAttempt $attempt, McTest $test): array
    {
        $state = $attempt->activity_state ?? [];
        $seconds = (float) ($state['away_seconds'] ?? 0);
        $end = CarbonImmutable::now();
        if ($test->paused_at !== null) {
            $end = $end->min($test->paused_at);
        }
        if ($attempt->individualPausedAt() !== null) {
            $end = $end->min($attempt->individualPausedAt());
        }
        if ($attempt->effectiveDeadline($test) !== null) {
            $end = $end->min($attempt->effectiveDeadline($test));
        }
        if ($attempt->completed_at !== null) {
            $end = $end->min($attempt->completed_at);
        }
        $periods = $state['periods'] ?? [];
        if (isset($state['away_since'])) {
            $ongoing = max(0, CarbonImmutable::parse($state['away_since'])->diffInSeconds($end, false));
            $seconds += $ongoing;
            $stopped = $attempt->completed_at !== null || $test->paused_at !== null || $attempt->individualPausedAt() !== null || ($attempt->effectiveDeadline($test) !== null && ! $attempt->effectiveDeadline($test)->isFuture());
            $periods[] = ['left_at' => $state['away_since'], 'returned_at' => null, 'ended_at' => $stopped ? $end->toIso8601String() : null, 'seconds' => (int) floor($ongoing), 'reason' => $state['reason'] ?? 'hidden'];
        }
        $status = 'not_reported';
        if ($state !== []) {
            $status = isset($state['away_since']) ? 'away' : (isset($state['last_seen_at']) && CarbonImmutable::parse($state['last_seen_at'])->greaterThanOrEqualTo(CarbonImmutable::now()->subSeconds(30)) ? 'present' : 'unknown');
        }
        if ($attempt->completed_at !== null) {
            $status = 'finished';
        } elseif ($test->paused_at !== null || $attempt->individualPausedAt() !== null) {
            $status = 'paused';
        } elseif ($attempt->effectiveDeadline($test) !== null && ! $attempt->effectiveDeadline($test)->isFuture()) {
            $status = 'expired';
        }

        return ['state' => $status, 'away_count' => (int) ($state['away_count'] ?? 0),
            'away_seconds' => (int) floor($seconds), 'fullscreen_exit_count' => (int) ($state['fullscreen_exit_count'] ?? 0),
            'fullscreen' => collect($state['sessions'] ?? [])->contains(fn (array $window): bool => ! $window['away'] && $window['fullscreen']),
            'periods' => $periods, 'fullscreen_exits' => $state['fullscreen_exits'] ?? [],
            'last_seen_at' => $state['last_seen_at'] ?? null, 'truncated' => ($state['away_count'] ?? 0) > 2000];
    }

    /** @param array<string, mixed> $state
     * @return array<string, mixed>
     */
    private function closePeriod(array $state, CarbonImmutable $at, string $reason): array
    {
        if (! isset($state['away_since'])) {
            return $state;
        }
        $start = CarbonImmutable::parse($state['away_since']);
        $at = $at->max($start);
        $seconds = max(0, $start->diffInSeconds($at, false));
        $state['away_seconds'] = ($state['away_seconds'] ?? 0) + $seconds;
        $state['periods'][] = ['left_at' => $start->toIso8601String(), 'returned_at' => $at->toIso8601String(), 'seconds' => (int) floor($seconds), 'reason' => $state['reason'] ?? 'hidden', 'ended_by' => $reason];
        $state['periods'] = array_slice($state['periods'], -2000);
        unset($state['away_since'], $state['reason']);

        return $state;
    }

    /** @param array<string, mixed> $state */
    private function persist(McAttempt $attempt, array $state): void
    {
        McAttempt::query()->whereKey($attempt->id)->update(['activity_state' => json_encode($state, JSON_THROW_ON_ERROR)]);
        $attempt->setAttribute('activity_state', $state);
        $attempt->syncOriginalAttribute('activity_state');
    }
}
