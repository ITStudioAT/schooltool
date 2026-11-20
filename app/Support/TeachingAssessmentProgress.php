<?php

namespace App\Support;

use App\Exceptions\DropboxExerciseRequestFailed;

class TeachingAssessmentProgress
{
    private const ATTEMPT_LIMIT = 10;

    public const PREREQUISITES = ['assessment_candidates', 'assessment_assignment', 'assessment_criteria', 'assessment_target', 'assessment_check'];

    private const STEPS = [...self::PREREQUISITES, 'assessment_mc', 'assessment_folder', 'assessment_documents', 'assessment_verify'];

    private const PHASES = ['status', 'documents', 'candidate_root', 'criteria_markdown', 'criteria_pdf', 'candidate_folder',
        'candidate_subfolder', 'assignment_file', 'verification', 'complete'];

    private function __construct(private array $attempts) {}

    public static function begin(?array $previous, bool $withMc): self
    {
        $attempts = $previous['attempts'] ?? [];
        $last = array_key_last($attempts);
        $timestamp = now()->toIso8601String();
        if ($last !== null && $attempts[$last]['state'] === 'running') {
            $attempts[$last]['state'] = 'interrupted';
            $attempts[$last]['updated_at'] = $timestamp;
        }
        $steps = self::PREREQUISITES;
        if ($withMc) {
            $steps[] = 'assessment_mc';
        }
        $steps[] = 'assessment_folder';
        $attempts[] = ['state' => 'running', 'phase' => 'documents', 'completed_steps' => $steps,
            'confirmed_candidates' => [], 'active_candidate' => null, 'error' => null,
            'started_at' => $timestamp, 'updated_at' => $timestamp];

        return new self(array_slice($attempts, -self::ATTEMPT_LIMIT));
    }

    public function confirmDocuments(): void
    {
        $this->update(['phase' => 'candidate_folder', 'active_candidate' => 0], 'assessment_documents');
    }

    public function confirmCandidate(int $index, bool $isLast): void
    {
        $confirmed = $this->attempts[array_key_last($this->attempts)]['confirmed_candidates'];
        $confirmed[] = $index;
        $this->update(['confirmed_candidates' => $confirmed, 'phase' => $isLast ? 'verification' : 'candidate_folder',
            'active_candidate' => $isLast ? null : $index + 1]);
    }

    public function complete(): void
    {
        $this->update(['state' => 'completed', 'phase' => 'complete', 'active_candidate' => null], 'assessment_verify');
    }

    public function fail(string $phase, ?int $candidate, array $error): void
    {
        $this->update(['state' => 'failed', 'phase' => $phase, 'active_candidate' => $candidate, 'error' => $error]);
    }

    public function record(): array
    {
        return ['schema_version' => 1, 'attempts' => $this->attempts];
    }

    private function update(array $changes, ?string $completedStep = null): void
    {
        $last = array_key_last($this->attempts);
        $this->attempts[$last] = array_replace($this->attempts[$last], $changes, ['updated_at' => now()->toIso8601String()]);
        if ($completedStep !== null) {
            $this->attempts[$last]['completed_steps'][] = $completedStep;
        }
    }

    public static function valid(mixed $value, ?bool $prepared = null): bool
    {
        if (! self::hasKeys($value, ['schema_version', 'attempts']) || $value['schema_version'] !== 1
            || ! is_array($value['attempts']) || ! array_is_list($value['attempts']) || $value['attempts'] === []
            || count($value['attempts']) > self::ATTEMPT_LIMIT) {
            return false;
        }
        $last = array_key_last($value['attempts']);
        foreach ($value['attempts'] as $index => $attempt) {
            if (! self::validAttempt($attempt) || ($index !== $last && in_array($attempt['state'], ['running', 'completed'], true))) {
                return false;
            }
        }

        return $prepared === null || $prepared === ($value['attempts'][$last]['state'] === 'completed');
    }

    private static function validAttempt(mixed $attempt): bool
    {
        if (! self::hasKeys($attempt, ['state', 'phase', 'completed_steps', 'confirmed_candidates', 'active_candidate', 'error', 'started_at', 'updated_at'])
            || ! in_array($attempt['state'], ['running', 'interrupted', 'failed', 'completed'], true)
            || ! in_array($attempt['phase'], self::PHASES, true)
            || ! is_array($attempt['completed_steps']) || ! array_is_list($attempt['completed_steps'])
            || count($attempt['completed_steps']) > count(self::STEPS)
            || ! is_array($attempt['confirmed_candidates']) || ! array_is_list($attempt['confirmed_candidates'])
            || count($attempt['confirmed_candidates']) > 200
            || ($attempt['active_candidate'] !== null && (! is_int($attempt['active_candidate']) || $attempt['active_candidate'] < 0 || $attempt['active_candidate'] >= 200))
            || ! self::validTimestamp($attempt['started_at']) || ! self::validTimestamp($attempt['updated_at'])) {
            return false;
        }
        $steps = $attempt['completed_steps'];
        foreach ($steps as $step) {
            if (! in_array($step, self::STEPS, true)) {
                return false;
            }
        }
        if ($steps !== array_values(array_unique($steps))) {
            return false;
        }
        foreach ($attempt['confirmed_candidates'] as $position => $candidate) {
            if ($position !== $candidate) {
                return false;
            }
        }
        $completed = $attempt['state'] === 'completed';
        if ($completed !== ($attempt['phase'] === 'complete') || $completed !== in_array('assessment_verify', $steps, true)
            || ($completed && ($attempt['active_candidate'] !== null || $attempt['confirmed_candidates'] === []))) {
            return false;
        }

        return $attempt['state'] === 'failed' ? self::validError($attempt['error']) : $attempt['error'] === null;
    }

    private static function validError(mixed $error): bool
    {
        if (! self::hasKeys($error, ['operation', 'category', 'http_status']) || ! is_string($error['operation']) || ! is_string($error['category'])
            || ($error['http_status'] !== null && (! is_int($error['http_status']) || $error['http_status'] < 400 || $error['http_status'] > 599))) {
            return false;
        }
        if ($error['operation'] === 'application') {
            return $error['category'] === 'unconfirmed' && $error['http_status'] === null;
        }

        return array_key_exists($error['operation'], DropboxExerciseRequestFailed::OPERATIONS)
            && array_key_exists($error['category'], DropboxExerciseRequestFailed::CATEGORIES);
    }

    private static function validTimestamp(mixed $value): bool
    {
        return is_string($value) && preg_match('/\A[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}[+-][0-9]{2}:[0-9]{2}\z/', $value) === 1;
    }

    private static function hasKeys(mixed $value, array $keys): bool
    {
        return is_array($value) && count($value) === count($keys) && array_diff($keys, array_keys($value)) === [];
    }
}
