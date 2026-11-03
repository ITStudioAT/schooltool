<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class McAttempt extends Model
{
    protected $fillable = [
        'test_id', 'student_id', 'student_name', 'email', 'questions', 'answers',
        'ready_at', 'started_at', 'submitted_at', 'completed_at', 'completion_reason',
        'correct_count', 'score', 'percentage', 'access_token_hash', 'access_token', 'invited_at',
    ];

    protected $hidden = ['questions', 'access_token_hash', 'access_token', 'reopen_history', 'activity_state'];

    protected $casts = [
        'pause_seconds' => 'integer', 'activity_state' => 'array',
        'questions' => 'array', 'answers' => 'array', 'access_token' => 'encrypted',
        'score' => 'decimal:2', 'percentage' => 'decimal:2', 'correct_count' => 'integer',
        'ready_at' => 'immutable_datetime', 'started_at' => 'immutable_datetime',
        'submitted_at' => 'immutable_datetime', 'completed_at' => 'immutable_datetime',
        'closes_at' => 'immutable_datetime', 'invited_at' => 'immutable_datetime', 'reopened_until' => 'immutable_datetime', 'reopen_history' => 'array',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $attempt): void {
            if ($attempt->isDirty(['test_id', 'student_id', 'questions', 'access_token_hash', 'access_token', 'reopened_until', 'reopen_history', 'closes_at', 'pause_seconds', 'activity_state'])) {
                throw ValidationException::withMessages(['attempt' => 'Die persönliche Zuteilung ist unveränderlich.']);
            }
            if ($attempt->getRawOriginal('completed_at') !== null
                && $attempt->isDirty(['answers', 'started_at', 'submitted_at', 'completed_at', 'completion_reason', 'correct_count', 'score', 'percentage'])) {
                throw ValidationException::withMessages(['attempt' => 'Ein abgeschlossener Versuch ist unveränderlich.']);
            }
        });
    }

    public function closingDeadline(?McTest $test = null): ?CarbonImmutable
    {
        return $this->closes_at ?? ($test ?? $this->test)->closes_at;
    }

    /** @return array<string, mixed> */
    public function control(): array
    {
        return $this->activity_state['control'] ?? [];
    }

    public function individualPausedAt(): ?CarbonImmutable
    {
        $value = $this->control()['paused_at'] ?? null;

        return $value ? CarbonImmutable::parse($value) : null;
    }

    public function startsIndividually(McTest $test): bool
    {
        return $test->start_mode === 'individual' || (bool) ($this->control()['restart_individually'] ?? false);
    }

    public function controlRevision(): string
    {
        return hash('sha256', json_encode([$this->id, $this->updated_at?->toIso8601String(), $this->answers,
            $this->ready_at?->toIso8601String(), $this->started_at?->toIso8601String(), $this->submitted_at?->toIso8601String(),
            $this->completed_at?->toIso8601String(), $this->completion_reason, $this->score, $this->percentage,
            $this->correct_count, $this->control()], JSON_THROW_ON_ERROR));
    }

    public function effectiveDeadline(?McTest $test = null): ?CarbonImmutable
    {
        $test ??= $this->test;
        if (! $this->startsIndividually($test)) {
            $deadline = $this->reopened_until ?? $test->ends_at;

            return $deadline?->addSeconds((int) ($this->control()['individual_pause_seconds'] ?? 0));
        }
        $closing = $this->closingDeadline($test);
        $personal = $this->reopened_until ?? $this->started_at?->addSeconds($test->duration_seconds + (int) $this->pause_seconds);

        return $personal === null ? $closing : ($closing === null || $personal->lessThan($closing) ? $personal : $closing);
    }

    public function test(): BelongsTo
    {
        return $this->belongsTo(McTest::class, 'test_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }
}
