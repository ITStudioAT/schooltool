<?php

namespace App\Services;

use App\Models\TeachingCourseWork;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class TeachingWorkPortalReopening
{
    public function __construct(private TeachingWorkStudentDistribution $distribution) {}

    public function deadlineFor(TeachingCourseWork $work, User $student, string $status): ?CarbonImmutable
    {
        if (! in_array($status, ['released', 'email_delivery', 'ended'], true)
            || (TeachingWorkSubmissions::method($work)['channel'] ?? null) !== 'portal') {
            return null;
        }

        $record = $this->records($work)->firstWhere('student_id', (int) $student->id);
        $deadline = $this->recordDeadline($work, $record);
        if ($deadline === null || $this->distribution->submissionDatesForStudent($work, $student) !== [$record['date'] ?? null]) {
            return null;
        }

        $standard = TeachingWorkSubmissions::deadline($work);

        return $work->studentReleaseEnded() || $standard?->lessThan($deadline) ? $deadline : null;
    }

    /** @return array{date: ?string, ambiguous: bool, students: list<array{id:int,name:string,open_until:?string}>} */
    public function options(TeachingCourseWork $work, ?string $date, bool $lock = false): array
    {
        abort_unless((TeachingWorkSubmissions::method($work)['channel'] ?? null) === 'portal'
            && ($work->studentReleaseEnded() || TeachingWorkSubmissions::expired($work)), 409,
            'Nur geschlossene Portalabgaben können wieder geöffnet werden.');

        // The student portal has one submission per person and work. A person assigned to
        // multiple dates cannot be reopened for just one of those dates.
        $fallback = $work->date_for_all_groups?->format('Y-m-d') ?? '';
        $groupsByDate = collect($work->groups ?? [])->groupBy(fn (array $group): string => $group['date'] ?? $fallback);
        if ($groupsByDate->isEmpty()) {
            $groupsByDate->put($fallback, collect());
        }
        $dates = $groupsByDate->keys()->all();
        $slot = $date ?? (count($dates) === 1 ? $dates[0] : '');
        if (! $groupsByDate->has($slot) || ($date === null && count($dates) > 1)) {
            throw ValidationException::withMessages(['date' => 'Bitte wähle einen gültigen Durchführungstermin dieser Arbeit.']);
        }
        $recipientsByDate = $groupsByDate->map(fn ($groups): array => $this->distribution->recipients($work, $lock, $groups->all()));
        $otherRecipients = $recipientsByDate->except($slot)->flatten()->all();
        $assigned = $recipientsByDate->get($slot);
        $studentIds = array_diff($assigned, $otherRecipients);
        $records = $this->records($work)->keyBy('student_id');
        $students = User::query()->whereIn('id', $studentIds)->orderBy('last_name')->orderBy('first_name')->orderBy('id')
            ->get(['id', 'last_name', 'first_name'])
            ->filter(fn (User $student): bool => in_array($this->distribution->studentTaskStatus($work, $student, knownRecipient: true),
                ['released', 'email_delivery', 'ended'], true))
            ->map(function (User $student) use ($work, $records, $slot): array {
                $record = $records->get($student->id);
                $deadline = ($record['date'] ?? '') === $slot ? $this->recordDeadline($work, $record) : null;

                return ['id' => (int) $student->id, 'name' => $student->full_name,
                    'open_until' => $deadline && ! $deadline->isPast() ? $deadline->toIso8601String() : null];
            })->values()->all();

        return ['date' => $slot === '' ? null : $slot, 'ambiguous' => count($studentIds) !== count($assigned), 'students' => $students];
    }

    /** @param array{date?:?string,scope:string,student_ids?:list<int>,deadline_date:string,deadline_time:string} $choice */
    public function reopen(TeachingCourseWork $work, array $choice): int
    {
        $options = $this->options($work, $choice['date'] ?? null, lock: true);
        $eligible = collect($options['students'])->whereNull('open_until')->pluck('id')->all();
        $selected = $choice['scope'] === 'all' ? $eligible : array_map('intval', $choice['student_ids']);
        if ($selected === [] || array_diff($selected, $eligible) !== []) {
            throw ValidationException::withMessages(['student_ids' => 'Wähle Personen mit geschlossener Portalabgabe aus diesem Termin.']);
        }

        $value = $choice['deadline_date'].' '.$choice['deadline_time'];
        $deadline = CarbonImmutable::createFromFormat('!Y-m-d H:i', $value, 'Europe/Vienna');
        if ($deadline?->format('Y-m-d H:i') !== $value || ! $deadline->isFuture()) {
            throw ValidationException::withMessages(['deadline_time' => 'Gib eine gültige zukünftige Abgabefrist in Wiener Ortszeit ein.']);
        }

        $endedAt = $work->status['student_distribution']['ended_at'] ?? null;
        $records = $this->records($work)->reject(fn (array $record): bool => in_array((int) $record['student_id'], $selected, true));
        foreach ($selected as $id) {
            $records->push(['student_id' => $id, 'date' => $options['date'],
                'ends_at' => $deadline->utc()->toIso8601String(), 'release_ended_at' => $endedAt]);
        }
        $work->update(['status' => [...$work->status, 'portal_submission_reopenings' => $records->values()->all()]]);
        $this->distribution->signalDeadlineChangeAfterCommit($work, $selected);

        return count($selected);
    }

    /** @return Collection<int, array> */
    private function records(TeachingCourseWork $work): Collection
    {
        return collect($work->status['portal_submission_reopenings'] ?? [])->filter(
            fn ($record): bool => is_array($record) && isset($record['student_id'])
        );
    }

    private function recordDeadline(TeachingCourseWork $work, ?array $record): ?CarbonImmutable
    {
        $instant = $record['ends_at'] ?? null;
        if (! is_string($instant) || ! preg_match('/\A\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\+00:00\z/', $instant)
            || ($record['release_ended_at'] ?? null) !== ($work->status['student_distribution']['ended_at'] ?? null)) {
            return null;
        }

        $deadline = CarbonImmutable::createFromFormat('!Y-m-d\TH:i:sP', $instant);

        return $deadline?->toIso8601String() === $instant ? $deadline : null;
    }
}
