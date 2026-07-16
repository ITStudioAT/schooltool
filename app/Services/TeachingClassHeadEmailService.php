<?php

namespace App\Services;

use App\Models\TeachingClassHeadEmail;
use App\Models\User;

class TeachingClassHeadEmailService
{
    /** @return array<int, array{class_name: string, email_1: ?string, email_2: ?string}> */
    public function listForUser(User $user): array
    {
        return TeachingClassHeadEmail::query()
            ->where('school_id', $user->school_id)
            ->where('schoolyear_id', $user->schoolyear_id)
            ->where('user_id', $user->id)
            ->orderBy('class_name')
            ->get(['class_name', 'email_1', 'email_2'])
            ->map(fn (TeachingClassHeadEmail $classHeadEmail): array => [
                'class_name' => $classHeadEmail->class_name,
                'email_1' => $classHeadEmail->email_1,
                'email_2' => $classHeadEmail->email_2,
            ])
            ->all();
    }

    /** @param array<int, array{class_name: string, email_1?: ?string, email_2?: ?string}> $classHeadEmails */
    public function syncForUser(User $user, array $classHeadEmails): void
    {
        $timestamp = now();
        $rows = collect($classHeadEmails)
            ->map(fn (array $classHeadEmail): array => [
                'school_id' => $user->school_id,
                'schoolyear_id' => $user->schoolyear_id,
                'user_id' => $user->id,
                'class_name' => trim($classHeadEmail['class_name']),
                'email_1' => $this->normalizeEmail($classHeadEmail['email_1'] ?? null),
                'email_2' => $this->normalizeEmail($classHeadEmail['email_2'] ?? null),
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

    private function normalizeEmail(mixed $email): ?string
    {
        $normalizedEmail = trim((string) $email);

        return $normalizedEmail !== '' ? $normalizedEmail : null;
    }
}
