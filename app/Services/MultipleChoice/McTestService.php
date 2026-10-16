<?php

namespace App\Services\MultipleChoice;

use App\Models\McAttempt;
use App\Models\McCatalog;
use App\Models\McTest;
use App\Models\User;
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
                'status' => 'published',
                'snapshot' => [
                    'questions' => $questions,
                    'question_count' => $locked->question_count,
                    'total_points' => $locked->total_points,
                    'duration_seconds' => $locked->duration_seconds,
                    'lead_seconds' => $locked->lead_seconds,
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

    public function startCountdown(McTest $test): McTest
    {
        return DB::transaction(function () use ($test): McTest {
            $locked = McTest::query()->lockForUpdate()->findOrFail($test->id);
            $this->assertPublished($locked);
            if ($locked->starts_at !== null) {
                return $locked;
            }
            $start = now()->addSeconds($locked->lead_seconds)->ceilSecond();
            $locked->forceFill(['starts_at' => $start, 'ends_at' => $start->copy()->addSeconds($locked->duration_seconds)])->save();

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
            if ($locked->completed_at !== null) {
                return $locked;
            }
            if ($test->ends_at !== null && now()->greaterThanOrEqualTo($test->ends_at)) {
                return $this->complete($test, $locked, 'timeout');
            }
            $locked->ready_at ??= now();
            if ($test->starts_at !== null && now()->greaterThanOrEqualTo($test->starts_at)) {
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
            if ($test->ends_at !== null && now()->greaterThanOrEqualTo($test->ends_at)) {
                return $this->complete($test, $locked, 'timeout');
            }
            $this->assertWritable($test, $locked);
            $locked->started_at ??= now();

            return $this->complete($test, $locked, 'submitted');
        }, 3);
    }

    public function finalizeExpired(?int $testId = null): int
    {
        $ids = McTest::query()->where('status', 'published')->whereNotNull('ends_at')->where('ends_at', '<=', now())
            ->when($testId !== null, fn ($query) => $query->whereKey($testId))->orderBy('id')->pluck('id');
        $count = 0;
        foreach ($ids as $id) {
            $count += DB::transaction(function () use ($id): int {
                $test = McTest::query()->lockForUpdate()->findOrFail($id);
                if ($test->ends_at === null || now()->lessThan($test->ends_at)) {
                    return 0;
                }
                $completed = 0;
                $test->attempts()->whereNull('completed_at')->orderBy('id')->lockForUpdate()->chunkById(100, function ($attempts) use ($test, &$completed): void {
                    foreach ($attempts as $attempt) {
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
        if ($attempt->completed_at !== null) {
            throw ValidationException::withMessages(['attempt' => 'Der Versuch ist bereits abgeschlossen.']);
        }
        if ($test->starts_at === null || now()->lessThan($test->starts_at)) {
            throw ValidationException::withMessages(['attempt' => 'Die Bearbeitungszeit hat noch nicht begonnen.']);
        }
        if ($test->ends_at === null || now()->greaterThanOrEqualTo($test->ends_at)) {
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
        $neverStarted = $reason === 'timeout' && $attempt->started_at === null;
        $correct = 0;
        foreach ($attempt->questions as $question) {
            $expected = $question['correct_option_ids'];
            $actual = ($attempt->answers ?? [])[$question['id']] ?? [];
            sort($expected);
            sort($actual);
            if ($expected === $actual) {
                $correct++;
            }
        }
        $count = count($attempt->questions);
        $totalCents = (int) round((float) $test->total_points * 100);
        $earnedCents = intdiv(2 * $totalCents * $correct + $count, 2 * $count);
        $attempt->forceFill([
            'completed_at' => $reason === 'timeout' ? $test->ends_at : now(),
            'submitted_at' => $reason === 'submitted' ? now() : null,
            'completion_reason' => $reason,
            'correct_count' => $neverStarted ? null : $correct,
            'score' => $neverStarted ? null : $earnedCents / 100,
            'percentage' => $neverStarted ? null : round(100 * $correct / $count, 2),
        ])->save();

        return $attempt;
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
