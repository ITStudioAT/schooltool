<?php

namespace App\Services;

use App\Models\TeachingCourseWork;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
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

    public static function acceptedMail(TeachingCourseWork $work, User $student, ?int $testId = null): bool
    {
        if (self::method($work) === null) {
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
        $deadline = $work->finalDeadlineInstant();
        if ($deadline && ! $work->finish_until_time) {
            $deadline = $deadline->endOfDay();
        }

        return $deadline !== null && $deadline->isPast();
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

    /** Save with the caller's work row lock. */
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

    /** Save with the caller's work row lock. */
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
