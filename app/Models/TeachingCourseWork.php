<?php

namespace App\Models;

use App\Support\SafeHtml;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TeachingCourseWork extends Model
{
    use HasFactory;

    /** These fields are written only by their individual workflow actions. */
    public const ManagedWorkflowFields = ['provision_method', 'submission_method', 'submission_started_at', 'portal_started_student_ids',
        'portal_submissions', 'assignment_mail', 'grade_visibility'];

    public function scopeLinkedToMultipleChoiceTest(Builder $query, int $testId): Builder
    {
        return $query->where(function (Builder $query) use ($testId): void {
            $query->whereRaw('JSON_CONTAINS(JSON_EXTRACT(status, ?), ?)', ['$.multiple_choice_tests.*.test_id', json_encode($testId, JSON_THROW_ON_ERROR)])
                ->orWhereRaw('JSON_CONTAINS(JSON_EXTRACT(status, ?), ?)', ['$.multiple_choice_tests.*.test_id', json_encode((string) $testId, JSON_THROW_ON_ERROR)]);
        });
    }

    protected $fillable = [
        'teaching_course_id',
        'type',
        'title',
        'description',
        'is_group_work',
        'group_size',
        'is_random_groups',
        'date_for_all_groups',
        'finish_until_date',
        'finish_until_time',
        'maximum_plus',
        'groups',
        'status',
    ];

    protected $casts = [
        'date_for_all_groups' => 'date:Y-m-d',
        'finish_until_date' => 'date:Y-m-d',
        'is_group_work' => 'boolean',
        'group_size' => 'integer',
        'maximum_plus' => 'integer',
        'is_random_groups' => 'boolean',
        'groups' => 'array',
        'status' => 'array',
    ];

    public function teachingCourse(): BelongsTo
    {
        return $this->belongsTo(TeachingCourse::class);
    }

    public function studentReleaseEnded(): bool
    {
        return ! empty($this->status['student_distribution']['ended_at']);
    }

    public function assertStudentReleaseOpen(): void
    {
        abort_if($this->studentReleaseEnded(), 409, 'Die Freigabe dieser Arbeit wurde endgültig beendet.');
    }

    public function automationEnabled(): bool
    {
        return ($this->status['automation_enabled'] ?? true) !== false;
    }

    public function gradesVisibleToStudents(): bool
    {
        // Work created before the separate release step already exposed its grades.
        return ! array_key_exists('grade_visibility', $this->status ?? [])
            || ($this->status['grade_visibility']['visible'] ?? null) === true;
    }

    /** @return array{comment: ?string, points: ?float, evaluation_pdf: ?array{name:string,sha256:string}} */
    public function resultDetailsForStudent(int $studentId): array
    {
        $group = collect($this->groups ?? [])->first(fn ($group): bool => is_array($group)
            && in_array($studentId, array_map('intval', $group['student_ids'] ?? []), true));
        $comment = collect($group['comments'] ?? [])->firstWhere('student_id', $studentId)['comment'] ?? null;
        $points = collect($group['points'] ?? [])->firstWhere('student_id', $studentId)['points'] ?? null;
        $pdf = collect($this->status['evaluation_pdfs'] ?? [])->last(fn ($item): bool => is_array($item)
            && (int) ($item['student_id'] ?? 0) === $studentId && filled($item['name'] ?? null) && filled($item['sha256'] ?? null));

        return [
            'comment' => filled($comment) ? $comment : ($group['comment'] ?? null),
            'points' => is_numeric($points) ? (float) $points : null,
            'evaluation_pdf' => $pdf ? ['name' => $pdf['name'], 'sha256' => $pdf['sha256']] : null,
        ];
    }

    public function hasResultForEntry(TeachingCourseStudentEntry $entry): bool
    {
        if (filled($entry->grade)) {
            return true;
        }
        $personal = $this->resultDetailsForStudent((int) $entry->user_id);

        return filled($personal['comment']) || $personal['points'] !== null || $personal['evaluation_pdf'] !== null;
    }

    /** @return array{date: ?string, time: ?string} */
    public function finalDeadline(): array
    {
        return ['date' => $this->finish_until_date?->format('Y-m-d'), 'time' => $this->finish_until_time ?: null];
    }

    public function finalDeadlineInstant(): ?CarbonImmutable
    {
        ['date' => $date, 'time' => $time] = $this->finalDeadline();
        if ($date === null) {
            return null;
        }
        $value = $date.' '.($time ?? '23:59');
        $deadline = CarbonImmutable::createFromFormat('!Y-m-d H:i', $value, 'Europe/Vienna');

        return $deadline?->format('Y-m-d H:i') === $value ? $deadline : null;
    }

    public function finalDeadlineLabel(): ?string
    {
        if ($this->finish_until_date === null) {
            return null;
        }
        $date = $this->finish_until_date->format('d.m.Y');

        return $this->finish_until_time ? $date.' · '.$this->finish_until_time.' Uhr' : $date;
    }

    public function teachingCourseStudentEntries(): HasMany
    {
        return $this->hasMany(TeachingCourseStudentEntry::class);
    }

    public function teachingCourseWorkGroupStudents(): HasMany
    {
        return $this->hasMany(TeachingCourseWorkGroupStudent::class);
    }

    protected function description(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value): string => app(SafeHtml::class)->sanitize($value),
            set: fn (?string $value): string => app(SafeHtml::class)->sanitize($value),
        );
    }
}
