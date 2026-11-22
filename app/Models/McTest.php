<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class McTest extends Model
{
    protected $fillable = [
        'school_id', 'schoolyear_id', 'owner_id', 'catalog_id', 'title', 'status', 'question_count',
        'total_points', 'duration_seconds', 'lead_seconds', 'balance_types',
        'shuffle_questions', 'shuffle_options', 'snapshot', 'starts_at', 'ends_at',
        'results_released_at', 'solutions_released_at', 'start_mode', 'closes_at', 'exam_mode',
    ];

    protected $hidden = ['snapshot', 'pause_history'];

    protected $casts = [
        'access_links_disabled_at' => 'immutable_datetime', 'paused_at' => 'immutable_datetime', 'pause_history' => 'array', 'exam_mode' => 'boolean',
        'snapshot' => 'array', 'total_points' => 'decimal:2',
        'question_count' => 'integer', 'duration_seconds' => 'integer', 'lead_seconds' => 'integer',
        'balance_types' => 'boolean', 'shuffle_questions' => 'boolean', 'shuffle_options' => 'boolean',
        'closes_at' => 'immutable_datetime', 'starts_at' => 'immutable_datetime', 'ends_at' => 'immutable_datetime',
        'results_released_at' => 'immutable_datetime', 'solutions_released_at' => 'immutable_datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $test): void {
            if (! in_array($test->getRawOriginal('status'), ['published', 'withdrawn'], true)) {
                return;
            }

            $immutable = ['school_id', 'schoolyear_id', 'owner_id', 'catalog_id', 'title', 'status', 'question_count',
                'total_points', 'duration_seconds', 'lead_seconds', 'balance_types',
                'shuffle_questions', 'shuffle_options', 'snapshot', 'start_mode', 'closes_at', 'exam_mode', 'paused_at', 'pause_history', 'access_links_disabled_at'];
            if ($test->isDirty($immutable)
                || ($test->getRawOriginal('starts_at') !== null && $test->isDirty(['starts_at', 'ends_at']))) {
                throw ValidationException::withMessages(['test' => 'Ein freigegebener Test und seine Startzeit sind unveränderlich.']);
            }
        });
    }

    /** @var Collection<int, TeachingCourseWork>|null */
    private ?Collection $deadlineWorks = null;

    /** @var list<CarbonImmutable|null>|null */
    private ?array $workDeadlines = null;

    /** @return Collection<int, TeachingCourseWork> */
    public function deadlineWorks(): Collection
    {
        return $this->deadlineWorks ??= TeachingCourseWork::query()->linkedToMultipleChoiceTest((int) $this->id)
            ->whereHas('teachingCourse', fn ($query) => $query->where('school_id', $this->school_id)
                ->where('schoolyear_id', $this->schoolyear_id)->where('user_id', $this->owner_id))
            ->get(['id', 'finish_until_date', 'finish_until_time', 'status'])
            ->filter(fn (TeachingCourseWork $work): bool => collect($work->status['multiple_choice_tests'] ?? [])
                ->contains(fn ($binding): bool => is_array($binding) && (int) ($binding['work_id'] ?? 0) === (int) $work->id
                    && (int) ($binding['test_id'] ?? 0) === (int) $this->id));
    }

    public function workReleaseEnded(): bool
    {
        return $this->deadlineWorks()->contains(fn (TeachingCourseWork $work): bool => $work->studentReleaseEnded());
    }

    public function assertWorkReleaseOpen(): void
    {
        abort_if($this->workReleaseEnded(), 409, 'Die Freigabe der zugehörigen Arbeit wurde endgültig beendet.');
    }

    public function hasWorkDeadlineConflict(): bool
    {
        return count($this->workDeadlines()) > 1;
    }

    public function assertConsistentWorkDeadline(): void
    {
        abort_if($this->hasWorkDeadlineConflict(), 409,
            'Der MC-Test ist mehreren Arbeiten mit unterschiedlichen Abgabefristen zugeordnet. Bitte die Zuordnung oder die Fristen klären.');
    }

    public function workDeadline(): ?CarbonImmutable
    {
        return $this->hasWorkDeadlineConflict() ? null : ($this->workDeadlines()[0] ?? null);
    }

    /** @return list<CarbonImmutable|null> */
    private function workDeadlines(): array
    {
        return $this->workDeadlines ??= $this->deadlineWorks()->map(fn (TeachingCourseWork $work) => $work->finalDeadlineInstant())
            ->uniqueStrict(fn (?CarbonImmutable $deadline) => $deadline?->getTimestamp())->values()->all();
    }

    public function individualClosingDeadline(): ?CarbonImmutable
    {
        if ($this->start_mode !== 'individual') {
            return null;
        }
        if (($this->snapshot['work_deadline_inherited'] ?? null) === true) {
            return $this->workDeadline() ?? $this->closes_at;
        }

        return $this->closes_at;
    }

    public function capDeadline(?CarbonImmutable $deadline): ?CarbonImmutable
    {
        $cap = $this->workDeadline();

        return $deadline === null ? $cap : ($cap === null ? $deadline : $deadline->min($cap));
    }

    public function catalog(): BelongsTo
    {
        return $this->belongsTo(McCatalog::class, 'catalog_id');
    }

    public function commonPreparationLocked(): bool
    {
        return $this->start_mode === 'common' && ($this->starts_at !== null || $this->ends_at !== null
            || ($this->snapshot['work_countdown_started'] ?? false));
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(McAttempt::class, 'test_id');
    }
}
