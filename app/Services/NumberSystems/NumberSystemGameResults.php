<?php

namespace App\Services\NumberSystems;

use App\Models\NumberSystemGameRun;
use App\Models\NumberSystemGameSession;
use Illuminate\Validation\Rule;

class NumberSystemGameResults
{
    public static function rules(): array
    {
        return ['timer_mode' => ['sometimes', Rule::in(['none', 'elapsed', 'countdown'])],
            'countdown_minutes' => ['nullable', 'integer', 'between:1,180'],
            'ranking_enabled' => ['sometimes', 'boolean']];
    }

    public function elapsed(NumberSystemGameSession $session): int
    {
        return (int) $session->active_seconds + ($session->status === 'running' && $session->active_since
            ? max(0, now()->getTimestamp() - $session->active_since->getTimestamp()) : 0);
    }

    /** @return array{mode: string, elapsed_seconds: ?int, limit_seconds: ?int, ticking: bool, expired: bool} */
    public function clock(NumberSystemGameSession $session, ?NumberSystemGameRun $run = null): array
    {
        $mode = $session->timer_mode ?? 'none';
        $seconds = $run?->finished_seconds ?? $this->elapsed($session);

        return ['mode' => $mode, 'elapsed_seconds' => $mode === 'none' ? null : $seconds,
            'limit_seconds' => $mode === 'countdown' ? $session->countdown_seconds : null,
            'ticking' => $mode !== 'none' && $session->status === 'running' && ! $run?->completed_at,
            'expired' => $mode === 'countdown' && $seconds >= $session->countdown_seconds];
    }

    public function ranking(NumberSystemGameSession $session, ?int $ownRun = null): ?array
    {
        if (! $session->ranking_enabled) {
            return null;
        }
        $rules = 'Nur diese Runde: abgeschlossene Abenteuer zuerst, danach bestätigte Datenwege.'
            .($session->timer_mode !== 'none' ? ' Bei gleichem Fortschritt zählt die kürzere aktive Zeit seit Rundenstart (ohne Pausen, auf Sekunden genau).' : ' Ohne Uhr zählt keine Geschwindigkeit.')
            .' Gleichstände teilen einen Rang. Hilfen ziehen nichts ab. Späterer Beitritt setzt die gemeinsame Uhr nicht zurück.';
        if ($session->status !== 'ended') {
            return ['final' => false, 'rules' => $rules, 'entries' => []];
        }
        $rows = $session->runs()->orderBy('id')->get()->map(fn (NumberSystemGameRun $run, int $index): array => [
            'id' => $run->id, 'label' => $run->team_name ?: 'Spielweg '.($index + 1), 'own' => $run->id === $ownRun,
            'completed' => $run->completed_at !== null, 'solved' => count($run->history ?? []), 'total' => count($run->puzzles),
            'seconds' => $session->timer_mode === 'none' ? null : ($run->finished_seconds ?? $this->elapsed($session)),
        ])->all();
        usort($rows, fn (array $a, array $b): int => ($b['completed'] <=> $a['completed']) ?: ($b['solved'] <=> $a['solved']) ?: ($a['seconds'] <=> $b['seconds']));
        $previous = null;
        $rank = 0;
        foreach ($rows as $index => &$row) {
            $score = [$row['completed'], $row['solved'], $row['seconds']];
            if ($score !== $previous) {
                $rank = $index + 1;
            }
            $row['rank'] = $rank;
            $previous = $score;
        }
        unset($row);

        return ['final' => true, 'rules' => $rules, 'entries' => $rows];
    }
}
