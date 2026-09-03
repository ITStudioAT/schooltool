<?php

namespace App\Services;

use App\Models\Teacher;
use App\Models\TeachingClassHeadEmail;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class TeachingClassHeadEmailService
{
    /** @return array<int, array{class_name: string, teacher_1_id: ?int, teacher_2_id: ?int}> */
    public function listForUser(User $user): array
    {
        $teacherIdsByEmail = $this->activeTeachersForUser($user)
            ->mapWithKeys(fn (Teacher $teacher): array => [
                Str::lower(trim($teacher->email)) => (int) $teacher->id,
            ]);

        return TeachingClassHeadEmail::query()
            ->where('school_id', $user->school_id)
            ->where('schoolyear_id', $user->schoolyear_id)
            ->where('user_id', $user->id)
            ->orderBy('class_name')
            ->get(['class_name', 'email_1', 'email_2'])
            ->map(fn (TeachingClassHeadEmail $classHeadEmail): array => [
                'class_name' => $classHeadEmail->class_name,
                'teacher_1_id' => $this->teacherIdForEmail($teacherIdsByEmail, $classHeadEmail->email_1),
                'teacher_2_id' => $this->teacherIdForEmail($teacherIdsByEmail, $classHeadEmail->email_2),
            ])
            ->all();
    }

    /** @return array<int, array{id: int, first_name: ?string, last_name: string, short: ?string}> */
    public function teacherOptionsForUser(User $user): array
    {
        return $this->activeTeachersForUser($user)
            ->map(fn (Teacher $teacher): array => [
                'id' => (int) $teacher->id,
                'first_name' => $teacher->first_name,
                'last_name' => $teacher->last_name,
                'short' => $teacher->short,
            ])
            ->all();
    }

    /** @param array<int, array{class_name: string, teacher_1_id?: ?int, teacher_2_id?: ?int}> $classHeadEmails */
    public function syncForUser(User $user, array $classHeadEmails): void
    {
        $selectedTeacherIds = collect($classHeadEmails)
            ->flatMap(fn (array $classHeadEmail): array => [
                $classHeadEmail['teacher_1_id'] ?? null,
                $classHeadEmail['teacher_2_id'] ?? null,
            ])
            ->filter()
            ->map(fn (mixed $teacherId): int => (int) $teacherId)
            ->unique()
            ->values();
        $teachersById = $this->activeTeachersForUser($user)
            ->whereIn('id', $selectedTeacherIds)
            ->keyBy('id');
        $timestamp = now();
        $rows = collect($classHeadEmails)
            ->map(fn (array $classHeadEmail): array => [
                'school_id' => $user->school_id,
                'schoolyear_id' => $user->schoolyear_id,
                'user_id' => $user->id,
                'class_name' => trim($classHeadEmail['class_name']),
                'email_1' => $this->teacherEmail($teachersById, $classHeadEmail['teacher_1_id'] ?? null),
                'email_2' => $this->teacherEmail($teachersById, $classHeadEmail['teacher_2_id'] ?? null),
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ])
            ->values()
            ->all();

        if ($rows === []) {
            return;
        }

        TeachingClassHeadEmail::query()->upsert(
            $rows,
            ['school_id', 'schoolyear_id', 'user_id', 'class_name'],
            ['email_1', 'email_2', 'updated_at']
        );
    }

    /** @return Collection<int, Teacher> */
    private function activeTeachersForUser(User $user): Collection
    {
        return Teacher::query()
            ->where('school_id', $user->school_id)
            ->where('is_active', true)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->orderBy('short')
            ->get(['id', 'first_name', 'last_name', 'short', 'email']);
    }

    /** @param Collection<string, int> $teacherIdsByEmail */
    private function teacherIdForEmail(Collection $teacherIdsByEmail, ?string $email): ?int
    {
        $normalizedEmail = Str::lower(trim((string) $email));

        if ($normalizedEmail === '') {
            return null;
        }

        $teacherId = $teacherIdsByEmail->get($normalizedEmail);

        return $teacherId !== null ? (int) $teacherId : null;
    }

    /** @param Collection<int, Teacher> $teachersById */
    private function teacherEmail(Collection $teachersById, mixed $teacherId): ?string
    {
        if ($teacherId === null || $teacherId === '') {
            return null;
        }

        return $teachersById->get((int) $teacherId)?->email;
    }
}
