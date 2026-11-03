<?php

namespace App\Services\MultipleChoice;

use App\Models\McAttempt;
use App\Models\McTest;
use App\Models\School;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class McTestExchange
{
    private const array RESULT_FIELDS = ['answers', 'started_at', 'submitted_at', 'completed_at',
        'completion_reason', 'correct_count', 'score', 'percentage'];

    public function identity(McTest $test): string
    {
        $school = School::findOrFail($test->school_id);
        $teacher = User::findOrFail($test->owner_id);

        return $this->hash([$school->short_name, $school->long_name, mb_strtolower($teacher->email),
            $test->created_at?->toISOString(), $test->snapshot, $test->total_points,
            $test->attempts()->orderBy('access_token_hash')->pluck('access_token_hash')->all()]);
    }

    public function export(McTest $test, ?array $previous = null): array
    {
        $identity = $this->identity($test);
        if ($previous !== null && (($previous['identity'] ?? null) !== $identity || ! is_array($previous['results'] ?? null))) {
            $this->fail('Die vorhandene Datei gehört zu einem anderen Test. Bitte den zugeordneten Ordner prüfen.');
        }
        foreach ($previous['results'] ?? [] as $row) {
            if (! is_array($row) || ! is_string($row['participant'] ?? null) || ! is_string($row['revision'] ?? null)
                || ! is_array($row['ancestors'] ?? null)) {
                $this->fail('Die bisherige Testdatei ist unvollständig. Bitte zuerst die Datei prüfen.');
            }
        }
        $previousRows = array_column($previous['results'] ?? [], null, 'participant');
        $results = [];
        foreach ($test->attempts()->with('student')->orderBy('id')->get() as $attempt) {
            abort_unless($attempt->student && (int) $attempt->student->school_id === (int) $test->school_id, 422);
            $participant = mb_strtolower(trim($attempt->student->email));
            if ($participant === '' || strcasecmp($participant, trim($attempt->email)) !== 0) {
                $this->fail('Die Teilnehmer-E-Mail stimmt nicht mehr mit der Testzuordnung überein.');
            }
            $result = $this->result($attempt);
            $old = $previousRows[$participant] ?? null;
            $ancestors = $old ? array_slice(array_values(array_unique([...($old['ancestors'] ?? []), $old['revision']])), -30) : [];
            $results[] = ['participant' => $participant, 'student_id' => $attempt->student_id, 'attempt_id' => $attempt->id,
                'name' => trim($attempt->student->first_name.' '.$attempt->student->last_name),
                'questions_hash' => $this->hash($attempt->questions), 'questions' => $attempt->questions,
                'revision' => $this->hash($result), 'ancestors' => $ancestors, 'result' => $result];
        }
        if (count(array_unique(array_column($results, 'participant'))) !== count($results)) {
            $this->fail('Teilnehmer sind nicht eindeutig zugeordnet.');
        }

        return ['kind' => 'schooltool.multiple_choice_exchange', 'schema_version' => 1, 'identity' => $identity,
            'exported_at' => now()->toISOString(), 'source' => ['url' => url('/'), 'school_id' => $test->school_id, 'teacher_id' => $test->owner_id],
            'test' => ['id' => $test->id, 'title' => $test->title, 'schoolyear_id' => $test->schoolyear_id,
                'total_points' => $test->total_points, 'snapshot' => $test->snapshot, 'status' => $test->status,
                'starts_at' => $test->starts_at?->toISOString(), 'ends_at' => $test->ends_at?->toISOString(),
                'closes_at' => $test->closes_at?->toISOString(), 'results_released_at' => $test->results_released_at?->toISOString(),
                'solutions_released_at' => $test->solutions_released_at?->toISOString()], 'results' => $results];
    }

    public function preview(McTest $test, array $package): array
    {
        $document = $package['exchange'] ?? $package;
        if (($document['kind'] ?? null) !== 'schooltool.multiple_choice_exchange' || ($document['schema_version'] ?? null) !== 1
            || ($document['identity'] ?? null) !== $this->identity($test) || ! is_array($document['results'] ?? null)) {
            $this->fail('Die Datei enthält keinen passenden Teststand mit Ergebnissen. Reine Zugangslink-Exporte sind nicht wieder einlesbar.');
        }
        $attempts = $test->attempts()->with('student')->orderBy('id')->get();
        $byEmail = $attempts->keyBy(fn (McAttempt $attempt): string => mb_strtolower(trim((string) $attempt->student?->email)));
        $changes = [];
        $conflicts = [];
        $unchanged = 0;
        $pending = 0;
        $seen = [];
        foreach ($document['results'] as $row) {
            if (! is_array($row) || ! is_array($row['ancestors'] ?? null)
                || count($row['ancestors']) > 30 || count(array_filter($row['ancestors'],
                    fn (mixed $revision): bool => is_string($revision) && preg_match('/\A[a-f0-9]{64}\z/', $revision))) !== count($row['ancestors'])) {
                $conflicts[] = 'Die Herkunft eines Ergebnisstands ist unvollständig.';

                continue;
            }
            $email = $row['participant'] ?? '';
            $attempt = is_string($email) ? $byEmail->get($email) : null;
            if (! $attempt || isset($seen[$email]) || (int) $attempt->student->school_id !== (int) $test->school_id
                || strcasecmp($attempt->email, $email) !== 0 || ($row['questions_hash'] ?? null) !== $this->hash($attempt->questions)) {
                $conflicts[] = 'Eine Teilnahme oder ihre zugeteilten Fragen stimmen nicht eindeutig mit diesem Test überein.';

                continue;
            }
            $seen[$email] = true;
            $incoming = $row['result'] ?? null;
            if (! is_array($incoming) || array_diff(array_keys($incoming), self::RESULT_FIELDS) !== []
                || array_diff(self::RESULT_FIELDS, array_keys($incoming)) !== [] || ($row['revision'] ?? null) !== $this->hash($incoming)) {
                $conflicts[] = "{$email}: Der Ergebnisstand ist unvollständig oder seine Prüfsumme stimmt nicht.";

                continue;
            }
            $current = $this->result($attempt);
            if ($this->hash($current) === $row['revision']) {
                $unchanged++;

                continue;
            }
            if ($incoming['completed_at'] === null) {
                $pending++;

                continue;
            }
            try {
                $this->validateResult($test, $attempt, $incoming);
                $pristine = $current['started_at'] === null && $current['completed_at'] === null && $current['answers'] === [];
                if (! $pristine && ! in_array($this->hash($current), $row['ancestors'] ?? [], true)) {
                    $this->fail('Beide Seiten haben unterschiedliche Änderungen. Bitte die Ergebnisse vergleichen; es wird nichts überschrieben.');
                }
                if (($current['started_at'] !== null && $current['completed_at'] === null)
                    || ($current['completed_at'] !== null && CarbonImmutable::parse($current['completed_at'])->greaterThan(CarbonImmutable::parse($incoming['completed_at'])))) {
                    $this->fail('Die lokale Teilnahme läuft noch oder ist neuer als die Datei.');
                }
                $changes[] = ['attempt_id' => $attempt->id, 'participant' => $email, 'before' => $current, 'after' => $incoming];
            } catch (ValidationException $exception) {
                $conflicts[] = $email.': '.collect($exception->errors())->flatten()->implode(' ');
            }
        }
        if (count($seen) !== $attempts->count()) {
            $conflicts[] = 'Die Datei enthält nicht genau alle zugeordneten Teilnahmen dieses Tests.';
        }

        return ['hash' => $this->hash([array_diff_key($this->export($test), ['exported_at' => true]), $document]), 'changes' => $changes,
            'unchanged' => $unchanged, 'pending' => $pending, 'conflicts' => array_values(array_unique($conflicts))];
    }

    public function apply(McTest $test, User $actor, array $package, string $hash): array
    {
        return DB::transaction(function () use ($test, $actor, $package, $hash): array {
            $locked = McTest::query()->lockForUpdate()->findOrFail($test->id);
            $locked->attempts()->orderBy('id')->lockForUpdate()->get();
            $plan = $this->preview($locked, $package);
            if (! hash_equals($plan['hash'], $hash) || $plan['conflicts'] !== []) {
                $this->fail('Test oder Datei wurden verändert oder enthalten Konflikte. Bitte erneut prüfen.');
            }
            if ($plan['changes'] === []) {
                return ['imported' => 0, 'unchanged' => $plan['unchanged']];
            }
            $path = "multiple-choice/exchange-history/{$actor->school_id}/{$actor->id}/{$test->id}/".Str::uuid().'.json.enc';
            $backup = encrypt(['actor' => $actor->id, 'created_at' => now()->toISOString(), 'package' => $package, 'changes' => $plan['changes']]);
            if (! Storage::disk('local')->put($path, $backup) || Storage::disk('local')->get($path) !== $backup) {
                $this->fail('Der bisherige Ergebnisstand konnte nicht sicher gesichert werden.');
            }
            foreach ($plan['changes'] as $change) {
                $values = $change['after'];
                $values['answers'] = json_encode($values['answers'], JSON_THROW_ON_ERROR);
                foreach (['started_at', 'submitted_at', 'completed_at'] as $column) {
                    $values[$column] = $values[$column] === null ? null : CarbonImmutable::parse($values[$column])->setTimezone(config('app.timezone'))->format('Y-m-d H:i:s');
                }
                McAttempt::query()->where('test_id', $locked->id)->whereKey($change['attempt_id'])->update($values);
            }

            return ['imported' => count($plan['changes']), 'unchanged' => $plan['unchanged']];
        });
    }

    public function hash(mixed $value): string
    {
        if (is_array($value)) {
            if (! array_is_list($value)) {
                ksort($value);
            }
            $value = array_map(fn (mixed $item): string => $this->hash($item), $value);
        }

        return hash('sha256', json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }

    private function result(McAttempt $attempt): array
    {
        return ['answers' => $attempt->answers ?? [], 'started_at' => $attempt->started_at?->utc()->toIso8601String(),
            'submitted_at' => $attempt->submitted_at?->utc()->toIso8601String(), 'completed_at' => $attempt->completed_at?->utc()->toIso8601String(),
            'completion_reason' => $attempt->completion_reason, 'correct_count' => $attempt->correct_count,
            'score' => $attempt->score, 'percentage' => $attempt->percentage];
    }

    private function validateResult(McTest $test, McAttempt $attempt, array $result): void
    {
        foreach (['started_at', 'submitted_at', 'completed_at'] as $field) {
            if (($result[$field] !== null || $field === 'completed_at')
                && (! is_string($result[$field]) || ! preg_match('/\A\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:\d{2})\z/', $result[$field]))) {
                $this->fail('Der zeitliche Prüfungsnachweis ist unvollständig.');
            }
        }
        try {
            $end = CarbonImmutable::parse($result['completed_at']);
            $start = $result['started_at'] === null ? null : CarbonImmutable::parse($result['started_at']);
            $submitted = $result['submitted_at'] === null ? null : CarbonImmutable::parse($result['submitted_at']);
        } catch (\Throwable) {
            $this->fail('Der zeitliche Prüfungsnachweis ist ungültig.');
        }
        if (! in_array($result['completion_reason'], ['submitted', 'timeout'], true) || ! is_array($result['answers'])
            || ($start && $end->lessThan($start)) || ($submitted && ($submitted->greaterThan($end) || ! $start || $submitted->lessThan($start)))
            || ($result['completion_reason'] === 'submitted' && $submitted === null)) {
            $this->fail('Abgabezeit, Abschluss oder Antworten sind nicht konsistent.');
        }
        $questions = array_column($attempt->questions, null, 'id');
        foreach ($result['answers'] as $id => $answers) {
            $question = $questions[$id] ?? null;
            if (! $question || ! is_array($answers) || ! array_is_list($answers) || count(array_filter($answers, 'is_string')) !== count($answers)
                || count($answers) !== count(array_unique($answers))
                || array_diff($answers, array_column($question['options'], 'id')) !== [] || ($question['type'] === 'single' && count($answers) > 1)) {
                $this->fail('Antworten verweisen auf unbekannte oder mehrdeutige Optionen.');
            }
        }
        if ($questions === []) {
            $this->fail('Ohne zugeteilte Fragen kann kein Ergebnis importiert werden.');
        }
        if ($start === null) {
            if ($result['completion_reason'] !== 'timeout' || $result['answers'] !== []
                || $result['correct_count'] !== null || $result['score'] !== null || $result['percentage'] !== null) {
                $this->fail('Ohne Prüfungsstart darf kein bewertetes Ergebnis importiert werden.');
            }

            return;
        }
        $evaluation = app(McTestService::class)->evaluate($test, $attempt->questions, $result['answers']);
        if ($result['correct_count'] !== $evaluation['correct_count'] || ! is_numeric($result['score']) || ! is_numeric($result['percentage'])
            || abs((float) $result['score'] - $evaluation['score']) > 0.001 || abs((float) $result['percentage'] - $evaluation['percentage']) > 0.001) {
            $this->fail('Die Punkte stimmen nicht mit den gespeicherten Antworten und den zugeteilten Fragen überein.');
        }
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['exchange' => $message]);
    }
}
