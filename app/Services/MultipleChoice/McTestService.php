<?php

namespace App\Services\MultipleChoice;

use App\Models\McAttempt;
use App\Models\McCatalog;
use App\Models\McTest;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class McTestService
{
    /** @param list<int|User> $students */
    public function publish(McTest $test, array $students): McTest
    {
        return DB::transaction(function () use ($test, $students): McTest {
            $locked = McTest::query()->lockForUpdate()->findOrFail($test->id);
            if ($locked->status === 'published') {
                return $locked;
            }

            Validator::make($locked->getAttributes(), [
                'title' => ['required', 'string', 'max:255'],
                'question_count' => ['required', 'integer', 'min:1'],
                'total_points' => ['required', 'numeric', 'min:0.01', 'max:99999999.99'],
                'duration_seconds' => ['required', 'integer', 'min:1', 'max:86400'],
                'lead_seconds' => ['required', 'integer', 'min:0', 'max:86400'],
            ])->validate();

            if ($locked->start_mode === 'individual' && ($locked->closes_at === null || ! $locked->closes_at->isFuture())) {
                throw ValidationException::withMessages(['closes_at' => 'Bitte legen Sie eine zukünftige Schlussfrist fest.']);
            }

            $catalog = McCatalog::query()->lockForUpdate()->findOrFail($locked->catalog_id);
            if ((int) $catalog->school_id !== (int) $locked->school_id || (int) $catalog->owner_id !== (int) $locked->owner_id) {
                throw ValidationException::withMessages(['catalog_id' => 'Der Katalog gehört nicht zu diesem Lehrer und dieser Schule.']);
            }
            $questions = array_values(array_filter($catalog->questions ?? [], fn (mixed $question): bool => is_array($question) && ($question['active'] ?? false) === true && ($question['reviewed'] ?? false) === true));
            $this->validateQuestions($questions);
            if (count($questions) < $locked->question_count) {
                throw ValidationException::withMessages(['question_count' => 'Es sind zu wenige aktive, geprüfte Fragen vorhanden.']);
            }

            $ids = array_values(array_unique(array_map(fn (int|User $student): int => $student instanceof User ? (int) $student->id : $student, $students)));
            if ($ids === []) {
                throw ValidationException::withMessages(['students' => 'Mindestens ein zugeordnetes Schülerkonto ist erforderlich.']);
            }
            $users = User::query()->where('school_id', $locked->school_id)
                ->where(fn ($query) => $query->where('is_active', true)->orWhereNull('is_active'))
                ->role('student')->whereIn('id', $ids)->get();
            if ($users->count() !== count($ids)) {
                throw ValidationException::withMessages(['students' => 'Alle Teilnehmer müssen Schülerkonten dieser Schule sein.']);
            }

            $locked->forceFill([
                'schoolyear_id' => $locked->schoolyear_id ?? User::query()->whereKey($locked->owner_id)->where('school_id', $locked->school_id)->value('schoolyear_id'),
                'status' => 'published',
                'snapshot' => [
                    'questions' => $questions,
                    'question_count' => $locked->question_count,
                    'total_points' => $locked->total_points,
                    'duration_seconds' => $locked->duration_seconds,
                    'lead_seconds' => $locked->lead_seconds,
                    'exam_mode' => (bool) $locked->exam_mode,
                    'start_mode' => $locked->start_mode ?? 'common',
                    'closes_at' => $locked->closes_at?->toIso8601String(),
                    'balance_types' => $locked->balance_types,
                    'shuffle_questions' => $locked->shuffle_questions,
                    'shuffle_options' => $locked->shuffle_options,
                    'scoring' => 'equal_exact_selection_final_rounding',
                ],
            ])->save();

            foreach ($users as $student) {
                $token = bin2hex(random_bytes(32));
                $locked->attempts()->create([
                    'student_id' => $student->id,
                    'student_name' => trim("{$student->first_name} {$student->last_name}"),
                    'email' => $student->email,
                    'questions' => $this->selectQuestions($questions, $locked),
                    'answers' => [],
                    'access_token_hash' => hash('sha256', $token),
                    'access_token' => $token,
                ]);
            }

            return $locked;
        }, 3);
    }

    /** @param array<string, mixed> $settings */
    public function updateSettings(McTest $test, array $settings, int $teacherId): McTest
    {
        return DB::transaction(function () use ($test, $settings, $teacherId): McTest {
            $locked = McTest::query()->lockForUpdate()->findOrFail($test->id);
            $this->assertPublished($locked);
            $this->assertNotPaused($locked);
            if ((int) $locked->owner_id !== $teacherId) {
                throw ValidationException::withMessages(['test' => 'Nur die zuständige Lehrkraft kann die Einstellungen ändern.']);
            }
            $attempts = $locked->attempts()->orderBy('id')->lockForUpdate()->get();
            if ($locked->starts_at !== null || $attempts->contains(fn (McAttempt $attempt): bool => $attempt->started_at !== null || $attempt->completed_at !== null || ($attempt->answers ?? []) !== [])) {
                throw ValidationException::withMessages(['test' => 'Der Countdown oder ein Versuch wurde bereits gestartet. Fragen und Bewertungsgrundlagen bleiben erhalten; verwenden Sie die Aktionen für Restzeit, Fristen und Zusatzzeit.']);
            }
            $pool = $locked->snapshot['questions'] ?? [];
            if (count($pool) < $settings['question_count']) {
                throw ValidationException::withMessages(['question_count' => 'Der ursprüngliche Fragenstand enthält zu wenige Fragen.']);
            }
            $settings['title'] = trim($settings['title']);
            $settings['closes_at'] = $settings['start_mode'] === 'individual'
                ? CarbonImmutable::parse($settings['closes_at'])->setTimezone(config('app.timezone')) : null;
            $changed = clone $locked;
            $changed->forceFill($settings);
            if ($changed->isDirty(['question_count', 'balance_types', 'shuffle_questions', 'shuffle_options'])) {
                foreach ($attempts as $attempt) {
                    McAttempt::query()->whereKey($attempt->id)->update(['questions' => json_encode($this->selectQuestions($pool, $changed), JSON_THROW_ON_ERROR)]);
                }
            }
            $snapshot = $locked->snapshot;
            foreach (['question_count', 'total_points', 'duration_seconds', 'lead_seconds', 'balance_types', 'shuffle_questions', 'shuffle_options', 'start_mode', 'exam_mode'] as $field) {
                $snapshot[$field] = $changed->{$field};
            }
            $snapshot['closes_at'] = $changed->closes_at?->toIso8601String();
            McTest::query()->whereKey($locked->id)->update([...$settings, 'snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR)]);

            return $locked->fresh();
        }, 3);
    }

    public function pause(McTest $test, int $teacherId): McTest
    {
        $this->finalizeExpired((int) $test->id);

        return DB::transaction(function () use ($test, $teacherId): McTest {
            $locked = McTest::query()->lockForUpdate()->findOrFail($test->id);
            $this->assertPublished($locked);
            if ((int) $locked->owner_id !== $teacherId) {
                throw ValidationException::withMessages(['test' => 'Nur die zuständige Lehrkraft kann den Test pausieren.']);
            }
            if ($locked->paused_at !== null) {
                return $locked;
            }
            $attempts = $locked->attempts()->whereNull('completed_at')->orderBy('id')->lockForUpdate()->get();
            if ($attempts->isEmpty() || ($locked->start_mode !== 'individual' && $locked->starts_at === null)) {
                throw ValidationException::withMessages(['test' => 'Es gibt keinen laufenden Test zum Pausieren.']);
            }
            $at = CarbonImmutable::now()->floorSecond();
            foreach ($attempts as $attempt) {
                app(McAttemptActivityService::class)->finish($attempt, $at);
            }
            McTest::query()->whereKey($locked->id)->update(['paused_at' => $at]);

            return $locked->fresh();
        }, 3);
    }

    public function resume(McTest $test, int $teacherId): McTest
    {
        return DB::transaction(function () use ($test, $teacherId): McTest {
            $locked = McTest::query()->lockForUpdate()->findOrFail($test->id);
            $this->assertPublished($locked);
            if ((int) $locked->owner_id !== $teacherId) {
                throw ValidationException::withMessages(['test' => 'Nur die zuständige Lehrkraft kann den Test fortsetzen.']);
            }
            if ($locked->paused_at === null) {
                return $locked;
            }
            $until = CarbonImmutable::now()->floorSecond();
            $seconds = max(0, (int) $locked->paused_at->diffInSeconds($until, false));
            $history = $locked->pause_history ?? [];
            $history[] = ['paused_at' => $locked->paused_at->toIso8601String(), 'resumed_at' => $until->toIso8601String(), 'seconds' => $seconds, 'teacher_id' => $teacherId];
            $values = ['paused_at' => null, 'pause_history' => json_encode($history, JSON_THROW_ON_ERROR)];
            foreach (['ends_at', 'closes_at'] as $field) {
                if ($locked->{$field} !== null) {
                    $values[$field] = $locked->{$field}->addSeconds($seconds);
                }
            }
            if ($locked->starts_at?->greaterThan($locked->paused_at)) {
                $values['starts_at'] = $locked->starts_at->addSeconds($seconds);
            }
            foreach ($locked->attempts()->whereNull('completed_at')->orderBy('id')->lockForUpdate()->get() as $attempt) {
                $changes = [];
                if ($attempt->started_at !== null || ($locked->start_mode !== 'individual' && $locked->starts_at?->lessThanOrEqualTo($locked->paused_at))) {
                    $changes['pause_seconds'] = (int) $attempt->pause_seconds + $seconds;
                }
                foreach (['reopened_until', 'closes_at'] as $field) {
                    if ($attempt->{$field} !== null) {
                        $changes[$field] = $attempt->{$field}->addSeconds($seconds);
                    }
                }
                if ($changes !== []) {
                    McAttempt::query()->whereKey($attempt->id)->update($changes);
                }
            }
            McTest::query()->whereKey($locked->id)->update($values);

            return $locked->fresh();
        }, 3);
    }

    private function assertNotPaused(McTest $test): void
    {
        if ($test->paused_at !== null) {
            throw ValidationException::withMessages(['test' => 'Der Test ist pausiert. Bitte warten Sie auf die Fortsetzung durch die Lehrkraft.']);
        }
    }

    public function startCountdown(McTest $test): McTest
    {
        return DB::transaction(function () use ($test): McTest {
            $locked = McTest::query()->lockForUpdate()->findOrFail($test->id);
            $this->assertPublished($locked);
            $this->assertNotPaused($locked);
            if ($locked->start_mode === 'individual') {
                throw ValidationException::withMessages(['test' => 'Dieser Test wird von jeder Person selbst gestartet.']);
            }
            if ($locked->starts_at !== null) {
                return $locked;
            }
            $start = now()->addSeconds($locked->lead_seconds)->ceilSecond();
            $locked->forceFill(['starts_at' => $start, 'ends_at' => $start->copy()->addSeconds($locked->duration_seconds)])->save();

            return $locked;
        }, 3);
    }

    public function changeRemainingTime(McTest $test, int $remainingSeconds): McTest
    {
        return DB::transaction(function () use ($test, $remainingSeconds): McTest {
            $locked = McTest::query()->lockForUpdate()->findOrFail($test->id);
            $this->assertRunning($locked);
            if ($remainingSeconds < 1 || $remainingSeconds > 86400) {
                throw ValidationException::withMessages(['remaining_seconds' => 'Die Restzeit muss zwischen einer Sekunde und 24 Stunden liegen.']);
            }
            McTest::query()->whereKey($locked->id)->update(['ends_at' => now()->addSeconds($remainingSeconds)->ceilSecond()]);

            return $locked->fresh();
        }, 3);
    }

    public function reopenAttempt(McAttempt $attempt, int $remainingSeconds, int $teacherId): McAttempt
    {
        return DB::transaction(function () use ($attempt, $remainingSeconds, $teacherId): McAttempt {
            [$test, $locked] = $this->lockAttempt($attempt);
            $this->assertPublished($test);
            $this->assertNotPaused($test);
            if ((int) $test->owner_id !== $teacherId) {
                throw ValidationException::withMessages(['attempt' => 'Nur die zuständige Lehrkraft kann den Versuch wieder öffnen.']);
            }
            if ($test->start_mode === 'individual' ? $locked->started_at === null || ($locked->completed_at === null && $locked->effectiveDeadline($test)?->isFuture()) : ($test->starts_at === null || $test->starts_at->isFuture() || $test->ends_at === null || $test->ends_at->isFuture())) {
                throw ValidationException::withMessages(['attempt' => $test->start_mode === 'individual' ? 'Nur bereits gestartete, abgeschlossene oder abgelaufene Versuche können wieder geöffnet werden.' : 'Einzelne Versuche können erst nach Ende der gemeinsamen Bearbeitungszeit wieder geöffnet werden.']);
            }
            if ($remainingSeconds < 1 || $remainingSeconds > 86400) {
                throw ValidationException::withMessages(['remaining_seconds' => 'Die Zusatzzeit muss zwischen einer Sekunde und 24 Stunden liegen.']);
            }
            $previousEnd = $locked->effectiveDeadline($test);
            if ($locked->completed_at === null && ! $previousEnd->isFuture()) {
                $locked = $this->complete($test, $locked, 'timeout');
            }
            $until = now()->addSeconds($remainingSeconds)->ceilSecond();
            if ($test->start_mode === 'individual') {
                $closing = $locked->closingDeadline($test);
                if ($closing === null || ! $closing->isFuture()) {
                    throw ValidationException::withMessages(['closes_at' => 'Bitte verlängern Sie zuerst die persönliche Schlussfrist.']);
                }
                $until = $until->min($closing);
            }
            $history = $locked->reopen_history ?? [];
            $history[] = [
                'reopened_at' => now()->toIso8601String(), 'until' => $until->toIso8601String(), 'teacher_id' => $teacherId,
                'previous' => [
                    'ends_at' => $previousEnd->toIso8601String(), 'completed_at' => $locked->completed_at?->toIso8601String(),
                    'submitted_at' => $locked->submitted_at?->toIso8601String(), 'completion_reason' => $locked->completion_reason,
                    'answers' => $locked->answers, 'correct_count' => $locked->correct_count,
                    'score' => $locked->score, 'percentage' => $locked->percentage,
                ],
            ];
            McAttempt::query()->whereKey($locked->id)->update([
                'reopened_until' => $until, 'reopen_history' => json_encode($history, JSON_THROW_ON_ERROR),
                'completed_at' => null, 'submitted_at' => null, 'completion_reason' => null,
                'correct_count' => null, 'score' => null, 'percentage' => null,
            ]);

            return $locked->fresh();
        }, 3);
    }

    public function closeEarly(McTest $test): McTest
    {
        return DB::transaction(function () use ($test): McTest {
            $locked = McTest::query()->lockForUpdate()->findOrFail($test->id);
            $this->assertPublished($locked);
            $this->assertNotPaused($locked);
            if ($locked->start_mode !== 'individual' && ($locked->starts_at === null || $locked->starts_at->isFuture())) {
                throw ValidationException::withMessages(['test' => 'Der Test hat noch nicht begonnen.']);
            }
            if ($locked->ends_at !== null && $locked->ends_at->isFuture()) {
                McTest::query()->whereKey($locked->id)->update(['ends_at' => now()]);
                $locked = $locked->fresh();
            }
            if ($locked->start_mode === 'individual') {
                McTest::query()->whereKey($locked->id)->update(['closes_at' => now()]);
                $locked = $locked->fresh();
                $locked->attempts()->whereNull('completed_at')->whereNotNull('closes_at')->update(['closes_at' => now()]);
            }
            foreach ($locked->attempts()->whereNull('completed_at')->orderBy('id')->lockForUpdate()->get() as $attempt) {
                if ($attempt->reopened_until?->isFuture()) {
                    McAttempt::query()->whereKey($attempt->id)->update(['reopened_until' => now()]);
                    $attempt = $attempt->fresh();
                }
                $this->complete($locked, $attempt, 'timeout');
            }

            return $locked;
        }, 3);
    }

    private function assertRunning(McTest $test): void
    {
        $this->assertPublished($test);
        $this->assertNotPaused($test);
        if ($test->start_mode === 'individual' || $test->starts_at === null || $test->starts_at->isFuture() || $test->ends_at === null || ! $test->ends_at->isFuture()) {
            throw ValidationException::withMessages(['test' => 'Die Restzeit kann nur während eines laufenden Tests geändert werden.']);
        }
    }

    public function startIndividual(McAttempt $attempt): McAttempt
    {
        return DB::transaction(function () use ($attempt): McAttempt {
            [$test, $locked] = $this->lockAttempt($attempt);
            $this->assertPublished($test);
            $this->assertNotPaused($test);
            if ($test->start_mode !== 'individual') {
                throw ValidationException::withMessages(['attempt' => 'Dieser Test startet gemeinsam.']);
            }
            if ($locked->completed_at !== null) {
                return $locked;
            }
            if ($locked->effectiveDeadline($test) === null || ! $locked->effectiveDeadline($test)->isFuture()) {
                return $this->complete($test, $locked, 'timeout');
            }
            if ($locked->started_at === null) {
                $locked->forceFill(['started_at' => now()->floorSecond(), 'ready_at' => $locked->ready_at ?? now()])->save();
            }

            return $locked;
        }, 3);
    }

    public function changeClosingDeadline(McAttempt $attempt, ?string $closing): McAttempt
    {
        return DB::transaction(function () use ($attempt, $closing): McAttempt {
            [$test, $locked] = $this->lockAttempt($attempt);
            $this->assertPublished($test);
            $this->assertNotPaused($test);
            if ($test->start_mode !== 'individual') {
                throw ValidationException::withMessages(['test' => 'Persönliche Schlussfristen sind nur beim selbstständigen Start möglich.']);
            }
            $values = ['closes_at' => $closing];
            $until = $closing === null ? $test->closes_at : CarbonImmutable::parse($closing);
            if ($locked->started_at === null && $locked->completion_reason === 'timeout' && $until?->isFuture()) {
                $values += ['completed_at' => null, 'completion_reason' => null];
            }
            McAttempt::query()->whereKey($locked->id)->update($values);
            $locked = $locked->fresh();
            if ($locked->completed_at === null && ! $locked->effectiveDeadline($test)?->isFuture()) {
                $locked = $this->complete($test, $locked, 'timeout');
            }

            return $locked;
        }, 3);
    }

    public function markReady(McAttempt $attempt): McAttempt
    {
        return $this->openAttempt($attempt);
    }

    public function openAttempt(McAttempt $attempt): McAttempt
    {
        return DB::transaction(function () use ($attempt): McAttempt {
            [$test, $locked] = $this->lockAttempt($attempt);
            $this->assertPublished($test);
            if ($test->paused_at !== null) {
                return $locked;
            }
            if ($locked->completed_at !== null) {
                return $locked;
            }
            $deadline = $locked->effectiveDeadline($test);
            if ($deadline !== null && now()->greaterThanOrEqualTo($deadline)) {
                return $this->complete($test, $locked, 'timeout');
            }
            $locked->ready_at ??= now();
            if ($test->start_mode !== 'individual' && $test->starts_at !== null && now()->greaterThanOrEqualTo($test->starts_at)) {
                $locked->started_at ??= now();
            }
            $locked->save();

            return $locked;
        }, 3);
    }

    /** @param array<string, list<string>> $answers */
    public function saveAnswers(McAttempt $attempt, array $answers): McAttempt
    {
        return DB::transaction(function () use ($attempt, $answers): McAttempt {
            [$test, $locked] = $this->lockAttempt($attempt);
            $this->assertWritable($test, $locked);
            $validated = $this->validateAnswers($locked, $answers);
            $locked->forceFill([
                'answers' => array_replace($locked->answers ?? [], $validated),
                'started_at' => $locked->started_at ?? now(),
                'ready_at' => $locked->ready_at ?? now(),
            ])->save();

            return $locked;
        }, 3);
    }

    public function submit(McAttempt $attempt): McAttempt
    {
        return DB::transaction(function () use ($attempt): McAttempt {
            [$test, $locked] = $this->lockAttempt($attempt);
            if ($locked->completed_at !== null) {
                return $locked;
            }
            $this->assertPublished($test);
            $this->assertNotPaused($test);
            $deadline = $locked->effectiveDeadline($test);
            if ($deadline !== null && now()->greaterThanOrEqualTo($deadline)) {
                return $this->complete($test, $locked, 'timeout');
            }
            $this->assertWritable($test, $locked);
            $locked->started_at ??= now();

            return $this->complete($test, $locked, 'submitted');
        }, 3);
    }

    public function finalizeExpired(?int $testId = null): int
    {
        $ids = McTest::query()->where('status', 'published')->whereNull('paused_at')
            ->where(fn ($query) => $query->where(fn ($common) => $common->where('ends_at', '<=', now()))
                ->orWhere(fn ($individual) => $individual->where('start_mode', 'individual')->whereHas('attempts', fn ($attempts) => $attempts->whereNull('completed_at'))))
            ->when($testId !== null, fn ($query) => $query->whereKey($testId))->orderBy('id')->pluck('id');
        $count = 0;
        foreach ($ids as $id) {
            $count += DB::transaction(function () use ($id): int {
                $test = McTest::query()->lockForUpdate()->findOrFail($id);
                if ($test->paused_at !== null || ($test->start_mode !== 'individual' && ($test->ends_at === null || now()->lessThan($test->ends_at)))) {
                    return 0;
                }
                $completed = 0;
                $test->attempts()->whereNull('completed_at')->orderBy('id')->lockForUpdate()->chunkById(100, function ($attempts) use ($test, &$completed): void {
                    foreach ($attempts as $attempt) {
                        if ($attempt->effectiveDeadline($test) === null || $attempt->effectiveDeadline($test)->isFuture()) {
                            continue;
                        }
                        $this->complete($test, $attempt, 'timeout');
                        $completed++;
                    }
                });

                return $completed;
            }, 3);
        }

        return $count;
    }

    /** @return array{McTest, McAttempt} */
    private function lockAttempt(McAttempt $attempt): array
    {
        $test = McTest::query()->lockForUpdate()->findOrFail($attempt->test_id);
        $locked = $test->attempts()->whereKey($attempt->id)->lockForUpdate()->firstOrFail();

        return [$test, $locked];
    }

    private function assertPublished(McTest $test): void
    {
        if ($test->status !== 'published') {
            throw ValidationException::withMessages(['test' => 'Der Test ist noch nicht freigegeben.']);
        }
    }

    private function assertWritable(McTest $test, McAttempt $attempt): void
    {
        $this->assertPublished($test);
        $this->assertNotPaused($test);
        if ($attempt->completed_at !== null) {
            throw ValidationException::withMessages(['attempt' => 'Der Versuch ist bereits abgeschlossen.']);
        }
        if ($test->start_mode === 'individual' ? $attempt->started_at === null : ($test->starts_at === null || now()->lessThan($test->starts_at))) {
            throw ValidationException::withMessages(['attempt' => 'Die Bearbeitungszeit hat noch nicht begonnen.']);
        }
        $deadline = $attempt->effectiveDeadline($test);
        if ($deadline === null || now()->greaterThanOrEqualTo($deadline)) {
            throw ValidationException::withMessages(['attempt' => 'Die Bearbeitungszeit ist abgelaufen.']);
        }
    }

    /** @param array<string, list<string>> $answers
     * @return array<string, list<string>>
     */
    private function validateAnswers(McAttempt $attempt, array $answers): array
    {
        $questions = collect($attempt->questions)->keyBy('id');
        foreach ($answers as $questionId => $selected) {
            $question = $questions->get($questionId);
            if ($question === null || ! is_array($selected) || ! array_is_list($selected)) {
                throw ValidationException::withMessages(['answers' => 'Ungültige Frage oder Antwortauswahl.']);
            }
            $optionIds = array_column($question['options'], 'id');
            if (count($selected) !== count(array_unique($selected, SORT_REGULAR))
                || ($question['type'] === 'single' && count($selected) > 1)) {
                throw ValidationException::withMessages(['answers' => 'Die Auswahl passt nicht zum Antworttyp.']);
            }
            foreach ($selected as $optionId) {
                if (! is_string($optionId) || ! in_array($optionId, $optionIds, true)) {
                    throw ValidationException::withMessages(['answers' => 'Eine gewählte Antwort gehört nicht zur Frage.']);
                }
            }
        }

        return $answers;
    }

    private function complete(McTest $test, McAttempt $attempt, string $reason): McAttempt
    {
        app(McAttemptActivityService::class)->finish($attempt, CarbonImmutable::instance($reason === 'timeout' ? $attempt->effectiveDeadline($test) : now()));
        $neverStarted = $reason === 'timeout' && $attempt->started_at === null;
        $evaluation = $this->evaluate($test, $attempt->questions, $attempt->answers ?? []);
        $attempt->forceFill([
            'completed_at' => $reason === 'timeout' ? ($attempt->effectiveDeadline($test)) : now(),
            'submitted_at' => $reason === 'submitted' ? now() : null,
            'completion_reason' => $reason,
            'correct_count' => $neverStarted ? null : $evaluation['correct_count'],
            'score' => $neverStarted ? null : $evaluation['score'],
            'percentage' => $neverStarted ? null : $evaluation['percentage'],
        ])->save();

        return $attempt;
    }

    /** @return array{correct_count: int, score: float, percentage: float} */
    public function evaluate(McTest $test, array $questions, array $answers): array
    {
        $correct = 0;
        foreach ($questions as $question) {
            $expected = $question['correct_option_ids'];
            $actual = $answers[$question['id']] ?? [];
            sort($expected);
            sort($actual);
            if ($expected === $actual) {
                $correct++;
            }
        }
        $count = count($questions);
        $totalCents = (int) round((float) $test->total_points * 100);
        $earnedCents = intdiv(2 * $totalCents * $correct + $count, 2 * $count);

        return ['correct_count' => $correct, 'score' => $earnedCents / 100, 'percentage' => round(100 * $correct / $count, 2)];
    }

    /** @param list<array<string, mixed>> $questions */
    private function validateQuestions(array $questions): void
    {
        $errors = app(McQuestionValidator::class)->errors($questions, true);
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
        Validator::make(['questions' => $questions], [
            'questions' => ['required', 'array', 'min:1'],
            'questions.*.id' => ['required', 'string', 'distinct'],
            'questions.*.prompt' => ['required', 'string', 'max:20000'],
            'questions.*.type' => ['required', 'in:single,multiple'],
            'questions.*.options' => ['required', 'array', 'min:2'],
            'questions.*.options.*.id' => ['required', 'string'],
            'questions.*.options.*.text' => ['required', 'string', 'max:10000'],
            'questions.*.correct_option_ids' => ['required', 'array', 'min:1'],
            'questions.*.correct_option_ids.*' => ['required', 'string'],
        ])->validate();
        foreach ($questions as $question) {
            $ids = array_column($question['options'], 'id');
            $correct = $question['correct_option_ids'];
            if (count($ids) !== count(array_unique($ids)) || count($correct) !== count(array_unique($correct))
                || array_diff($correct, $ids) !== [] || ($question['type'] === 'single' && count($correct) !== 1)) {
                throw ValidationException::withMessages(['questions' => 'Antworten und Lösungsschlüssel einer Frage sind ungültig.']);
            }
        }
    }

    /** @param list<array<string, mixed>> $questions
     * @return list<array<string, mixed>>
     */
    private function selectQuestions(array $questions, McTest $test): array
    {
        if ($test->balance_types) {
            $single = array_values(array_filter($questions, fn (array $question): bool => $question['type'] === 'single'));
            $multiple = array_values(array_filter($questions, fn (array $question): bool => $question['type'] === 'multiple'));
            $multipleCount = (int) round($test->question_count * count($multiple) / count($questions));
            $multipleCount = max($test->question_count - count($single), min(count($multiple), $multipleCount));
            $selected = array_merge($this->randomSubset($single, $test->question_count - $multipleCount), $this->randomSubset($multiple, $multipleCount));
        } else {
            $selected = $this->randomSubset($questions, $test->question_count);
        }
        if ($test->shuffle_questions) {
            $selected = $this->randomSubset($selected, count($selected));
        } else {
            $order = array_flip(array_column($questions, 'id'));
            usort($selected, fn (array $left, array $right): int => $order[$left['id']] <=> $order[$right['id']]);
        }
        if ($test->shuffle_options) {
            foreach ($selected as &$question) {
                $question['options'] = $this->randomSubset($question['options'], count($question['options']));
            }
            unset($question);
        }

        return $selected;
    }

    /** @param list<array<string, mixed>> $items
     * @return list<array<string, mixed>>
     */
    private function randomSubset(array $items, int $count): array
    {
        for ($index = count($items) - 1; $index > 0; $index--) {
            $other = random_int(0, $index);
            [$items[$index], $items[$other]] = [$items[$other], $items[$index]];
        }

        return array_slice($items, 0, $count);
    }
}
