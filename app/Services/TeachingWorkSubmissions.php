<?php

namespace App\Services;

use App\Models\McAttempt;
use App\Models\McTest;
use App\Models\TeachingCourseWork;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TeachingWorkSubmissions
{
    public const MAX_FILE_KIB = 20480;

    /** @return array{channel:string,email:?string}|null */
    public static function method(TeachingCourseWork $work): ?array
    {
        $method = $work->status['submission_method'] ?? null;
        if (($method['channel'] ?? null) === 'portal') {
            return ['channel' => 'portal', 'email' => null];
        }
        if (($method['channel'] ?? null) === 'email' && filter_var($method['email'] ?? '', FILTER_VALIDATE_EMAIL)) {
            return ['channel' => 'email', 'email' => $method['email']];
        }

        return null;
    }

    /** @return list<int> */
    public static function groupAccounts(TeachingCourseWork $work, ?string $date = null, ?array $groups = null): array
    {
        $groups ??= app(TeachingCourseWorkEntrySyncService::class)->groupsForWork($work);
        $ids = collect($groups)->filter(fn (array $group): bool => $date === null || $date === 'all'
            || ($group['date'] ?? $work->date_for_all_groups?->format('Y-m-d')) === $date)
            ->flatMap(fn (array $group): array => $group['student_ids'] ?? [])->map(fn ($id): int => (int) $id)->filter()->unique();

        return $ids->values()->all();
    }

    /** @return list<int> */
    public static function confirmedEmailRecipients(TeachingCourseWork $work, ?array $groups = null): array
    {
        $status = $work->status ?? [];
        if (empty($status['assignment_mail']['batches']) && empty($status['dispatch_notifications'])) {
            return [];
        }
        $groups ??= app(TeachingCourseWorkEntrySyncService::class)->groupsForWork($work);
        $assigned = self::groupAccounts($work, groups: $groups);
        $students = [];
        foreach ($status['assignment_mail']['batches'] ?? [] as $slot => $batch) {
            if (($batch['transport'] ?? null) !== 'postmark') {
                continue;
            }
            $members = $slot === 'all' ? $assigned : self::groupAccounts($work, (string) $slot, $groups);
            foreach ($batch['recipients'] ?? [] as $row) {
                if (in_array((int) ($row['student_id'] ?? 0), $members, true)
                    && in_array($row['state'] ?? null, ['accepted', 'already_sent'], true)
                    && strtotime($row['accepted_at'] ?? '') !== false) {
                    $students[] = (int) $row['student_id'];
                }
            }
        }
        $logs = collect($status['dispatch_logs'] ?? [])->filter(fn (array $log): bool => ($log['origin'] ?? null) === 'dispatch_import'
            && ($log['purpose'] ?? null) === 'tasks' && ($log['recipient_scope'] ?? null) !== 'teacher')->pluck('sha256')->filter()->all();
        foreach ($status['dispatch_notifications'] ?? [] as $receipt) {
            if (($receipt['origin'] ?? null) === 'dispatch_import' && ($receipt['purpose'] ?? null) === 'tasks'
                && in_array($receipt['log_sha256'] ?? null, $logs, true) && strtotime($receipt['sent_at'] ?? '') !== false
                && in_array((int) ($receipt['student_id'] ?? 0), $assigned, true)) {
                $students[] = (int) $receipt['student_id'];
            }
        }

        return array_values(array_unique($students));
    }

    public static function lockReason(TeachingCourseWork $work, bool $lock = false, ?array $groups = null, ?array $startedByTest = null): ?string
    {
        if ($work->studentReleaseEnded()) {
            return 'ended';
        }
        if ((self::method($work)['channel'] ?? null) === 'portal'
            && (self::expired($work) || ! empty($work->status['portal_submission_reopenings']))) {
            return 'deadline';
        }
        if (! empty($work->status['submission_started_at']) || ! empty($work->status['portal_started_student_ids']) || ! empty($work->status['portal_submissions'])
            || self::hasStartedTest($work, $lock, $groups, $startedByTest)) {
            return 'started';
        }
        if (self::confirmedEmailRecipients($work, $groups) !== []) {
            return 'email_sent';
        }
        $pending = collect($work->status['assignment_mail']['batches'] ?? [])->where('transport', 'postmark')
            ->flatMap(fn (array $batch): array => $batch['recipients'] ?? [])
            ->contains(fn (array $row): bool => in_array($row['state'] ?? null, ['sending', 'uncertain'], true));

        return $pending ? 'email_pending' : null;
    }

    private static function hasStartedTest(TeachingCourseWork $work, bool $lock, ?array $groups, ?array $startedByTest): bool
    {
        $scopes = [];
        foreach ($work->status['multiple_choice_tests'] ?? [] as $slot => $binding) {
            $id = (int) ($binding['test_id'] ?? 0);
            if ((int) ($binding['work_id'] ?? 0) === (int) $work->id && $id > 0) {
                $groups ??= app(TeachingCourseWorkEntrySyncService::class)->groupsForWork($work);
                $scopes[$id] = [...($scopes[$id] ?? []), ...self::groupAccounts($work, (string) $slot, $groups)];
            }
        }
        if ($scopes === []) {
            return false;
        }
        if ($startedByTest === null) {
            $course = $work->teachingCourse;
            if (! $course || ! Schema::hasTable('mc_attempts')) {
                return false;
            }
            // The same test row is locked before a participant can start. Read current attempt rows after it.
            $tests = McTest::query()->whereIn('id', array_keys($scopes))->where('owner_id', $course->user_id)
                ->where('schoolyear_id', $course->schoolyear_id)->where('school_id', $course->school_id)->orderBy('id');
            $testIds = ($lock ? $tests->lockForUpdate() : $tests)->pluck('id');
            $attempts = McAttempt::query()->whereIn('test_id', $testIds)->whereNotNull('started_at');
            $startedByTest = ($lock ? $attempts->lockForUpdate() : $attempts)->get(['test_id', 'student_id'])
                ->groupBy('test_id')->map(fn ($rows): array => $rows->pluck('student_id')->all())->all();
        }
        foreach ($scopes as $id => $students) {
            if (array_intersect($students, $startedByTest[$id] ?? []) !== []) {
                return true;
            }
        }

        return false;
    }

    /** @return list<TeachingCourseWork> */
    public static function lockMcStartWorks(McAttempt $attempt): array
    {
        if ($attempt->started_at !== null || $attempt->completed_at !== null) {
            return [];
        }
        $workIds = $attempt->test->deadlineWorks()->modelKeys();

        if ($workIds === []) {
            return [];
        }
        $works = TeachingCourseWork::query()->whereKey($workIds)->orderBy('id')->lockForUpdate()->get();
        foreach ($works as $work) {
            $work->assertStudentReleaseOpen();
        }

        return $works->all();
    }

    /** @param list<TeachingCourseWork> $works */
    public static function recordMcStart(McAttempt $attempt, McTest $test, array $works): void
    {
        if (! $attempt->wasChanged('started_at') || $attempt->started_at === null) {
            return;
        }
        foreach ($works as $work) {
            if (! empty($work->status['submission_started_at']) || ! $test->deadlineWorks()->contains(fn ($linked) => $linked->is($work))) {
                continue;
            }
            foreach ($work->status['multiple_choice_tests'] ?? [] as $slot => $binding) {
                if ((int) ($binding['work_id'] ?? 0) === (int) $work->id && (int) ($binding['test_id'] ?? 0) === (int) $test->id
                    && in_array((int) $attempt->student_id, self::groupAccounts($work, (string) $slot), true)) {
                    $work->update(['status' => [...$work->status, 'submission_started_at' => $attempt->started_at->toIso8601String()]]);
                    break;
                }
            }
        }
    }

    public static function assertCollectionOpen(TeachingCourseWork $work): void
    {
        abort_unless($work->studentReleaseEnded(), 409, 'Abgaben können erst nach „Freigabe beenden“ eingesammelt werden.');
    }

    public static function acceptedMail(TeachingCourseWork $work, User $student, ?int $testId = null): bool
    {
        if ($work->studentReleaseEnded() || self::method($work) === null) {
            return false;
        }
        $release = $work->status['student_distribution'] ?? [];
        $withdrawn = max(strtotime($release['deleted_at'] ?? '') ?: 0, strtotime($release['withdrawn_at'] ?? '') ?: 0);
        if ($testId === null && array_key_exists('accepted_submission_times', $work->getAttributes())) {
            $times = json_decode($work->getAttribute('accepted_submission_times') ?? '[]', true, flags: JSON_THROW_ON_ERROR);

            return collect($times)->contains(fn (string $time): bool => (strtotime($time) ?: 0) > $withdrawn);
        }
        foreach ($work->status['assignment_mail']['batches'] ?? [] as $batch) {
            if (($batch['transport'] ?? null) !== 'postmark' || ! empty($batch['withdrawn_at'])
                || ($testId !== null && ! in_array($testId, $batch['test_ids'] ?? [], true))) {
                continue;
            }
            foreach ($batch['recipients'] ?? [] as $recipient) {
                if ((int) ($recipient['student_id'] ?? 0) === (int) $student->id && ($recipient['state'] ?? null) === 'accepted'
                    && (strtotime($recipient['accepted_at'] ?? '') ?: 0) > $withdrawn) {
                    return true;
                }
            }
        }

        return false;
    }

    public static function expired(TeachingCourseWork $work): bool
    {
        return self::deadline($work)?->isPast() ?? false;
    }

    public static function deadline(TeachingCourseWork $work): ?CarbonImmutable
    {
        $deadline = $work->finalDeadlineInstant();

        return $work->finish_until_time ? $deadline : $deadline?->endOfDay();
    }

    /** @return list<array<string,mixed>> */
    public static function receipts(TeachingCourseWork $work, User $student): array
    {
        return collect($work->status['portal_submissions'] ?? [])
            ->where('student_id', $student->id)
            ->map(fn (array $file): array => Arr::only($file, ['id', 'name', 'submitted_at', 'withdrawn_at']))
            ->values()->all();
    }

    public static function storagePath(TeachingCourseWork $work, array $file): ?string
    {
        $path = $file['file_path'] ?? null;
        if (($file['storage_disk'] ?? null) !== 'local' || ! is_string($path) || str_contains($path, '..') || str_contains($path, '\\')) {
            return null;
        }
        $schoolId = $work->teachingCourse->school_id;
        $folders = ["teaching/work_submissions/{$schoolId}/{$work->id}/", "teaching/work_submissions/{$schoolId}/restored/",
            "teaching/synchronisation/{$schoolId}/", 'teaching/personal_restores/'];

        return Str::startsWith($path, $folders) ? $path : null;
    }

    /** Save after checking the student's submission window under the caller's work row lock. */
    public function upload(TeachingCourseWork $work, User $student, UploadedFile $upload, ?string &$createdPath): void
    {
        if (! $upload->getSize()) {
            throw ValidationException::withMessages(['file' => 'Die ausgewählte Datei ist leer.']);
        }
        $files = $work->status['portal_submissions'] ?? [];
        $name = mb_substr(trim(preg_replace('/[\x00-\x1f\x7f]/u', '', basename(str_replace('\\', '/', $upload->getClientOriginalName()))) ?? ''), 0, 220);
        $name = $name === '' || in_array($name, ['.', '..'], true) ? 'Abgabe' : $name;
        $digest = hash_file('sha256', $upload->getRealPath());
        $personal = collect($files)->where('student_id', $student->id);
        foreach ($personal as $index => $previous) {
            if (($previous['sha256'] ?? null) === $digest && ($previous['name'] ?? null) === $name
                && ($path = self::storagePath($work, $previous)) !== null && Storage::disk('local')->exists($path)) {
                if (! empty($previous['withdrawn_at'])) {
                    $files[$index]['withdrawn_at'] = null;
                    $files[$index]['submitted_at'] = now()->toIso8601String();
                    $work->update(['status' => [...$work->status, 'portal_submissions' => $files]]);
                }

                return;
            }
        }
        if ($personal->sum('size') + $upload->getSize() > 100 * 1024 * 1024) {
            throw ValidationException::withMessages(['file' => 'Deine Dateien für diese Aufgabe überschreiten zusammen 100 MB.']);
        }
        if ($personal->count() >= 100) {
            throw ValidationException::withMessages(['file' => 'Für diese Aufgabe sind bereits 100 verschiedene Dateien von dir gespeichert.']);
        }
        $id = (string) Str::uuid();
        $directory = 'teaching/work_submissions/'.$work->teachingCourse->school_id.'/'.$work->id;
        $createdPath = $directory.'/'.$id;
        abort_unless(Storage::disk('local')->putFileAs($directory, $upload, $id, ['visibility' => 'private']), 500, 'Die Abgabe konnte nicht gespeichert werden.');
        $files[] = ['id' => $id, 'student_id' => (int) $student->id, 'student_name' => trim($student->first_name.' '.$student->last_name),
            'name' => $name, 'size' => $upload->getSize(), 'sha256' => $digest, 'storage_disk' => 'local',
            'file_path' => $createdPath, 'submitted_at' => now()->toIso8601String(), 'withdrawn_at' => null];
        $work->update(['status' => [...$work->status, 'portal_submissions' => $files]]);
    }

    /** Save after checking the student's submission window under the caller's work row lock. */
    public function withdraw(TeachingCourseWork $work, User $student, string $id): void
    {
        $files = $work->status['portal_submissions'] ?? [];
        $index = collect($files)->search(fn (array $file): bool => $file['id'] === $id && (int) $file['student_id'] === (int) $student->id);
        abort_if($index === false, 404);
        if (empty($files[$index]['withdrawn_at'])) {
            $files[$index]['withdrawn_at'] = now()->toIso8601String();
            $work->update(['status' => [...$work->status, 'portal_submissions' => $files]]);
        }
    }
}
