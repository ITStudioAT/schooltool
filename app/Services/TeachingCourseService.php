<?php

namespace App\Services;

use App\Models\Import116;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class TeachingCourseService
{
    public function normalizeStudentIds($value): array
    {
        if (is_array($value)) {
            return array_values(array_filter($value));
        }

        if ($value instanceof \Illuminate\Support\Collection) {
            return array_values(array_filter($value->all()));
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return array_values(array_filter($decoded));
            }
        }

        return [];
    }

    public function normalizeStudentItems($value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if ($value instanceof \Illuminate\Support\Collection) {
            return $value->all();
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }

    public function resolveStudentIds($value, int $schoolId): array
    {
        $items = $this->normalizeStudentItems($value);
        $ids = [];

        foreach ($items as $item) {
            $resolvedId = null;
            $fallbackImportId = null;

            if (is_array($item) || is_object($item)) {
                $data = (array) $item;
                $email = $data['email'] ?? null;
                $id = $data['id'] ?? null;

                if ($id) {
                    $resolvedId = $this->resolveStudentIdFromNumeric((int) $id, $schoolId);
                    if (! $resolvedId) {
                        $fallbackImportId = $this->findImportIdInSchool((int) $id, $schoolId);
                    }
                } elseif ($email) {
                    $resolvedId = $this->findOrCreateUserIdByEmail($email, $schoolId, $data);
                }
            } elseif (is_numeric($item)) {
                $resolvedId = $this->resolveStudentIdFromNumeric((int) $item, $schoolId);
                if (! $resolvedId) {
                    $fallbackImportId = $this->findImportIdInSchool((int) $item, $schoolId);
                }
            }

            if ($resolvedId) {
                $ids[] = $resolvedId;
            } elseif ($fallbackImportId) {
                $ids[] = $fallbackImportId;
            }
        }

        return array_values(array_unique($ids));
    }

    public function normalizeStudentEntries($value): array
    {
        $items = $this->normalizeStudentItems($value);
        $entries = [];

        foreach ($items as $item) {
            $data = (is_array($item) || is_object($item)) ? (array) $item : ['id' => $item];
            $id = $data['id'] ?? null;
            $comment = $data['comment'] ?? null;

            if (is_numeric($id)) {
                $entries[] = [
                    'id' => (int) $id,
                    'comment' => $comment,
                    'sem_1_grade' => $data['sem_1_grade'] ?? null,
                    'sem_2_grade' => $data['sem_2_grade'] ?? null,
                    'sem_grade' => $data['sem_grade'] ?? null,
                    'behaviour_1_grade' => $data['behaviour_1_grade'] ?? null,
                    'behaviour_2_grade' => $data['behaviour_2_grade'] ?? null,
                    'behaviour_grade' => $data['behaviour_grade'] ?? null,
                    'stars' => $this->normalizeStars($data['stars'] ?? []),
                ];
            }
        }

        return $entries;
    }

    public function resolveStudentEntries($value, int $schoolId): array
    {
        $items = $this->normalizeStudentItems($value);
        $entries = [];

        foreach ($items as $item) {
            $resolvedId = null;
            $fallbackImportId = null;
            $data = [];
            $comment = null;

            if (is_array($item) || is_object($item)) {
                $data = (array) $item;
                $comment = $data['comment'] ?? null;
                $email = $data['email'] ?? null;
                $id = $data['id'] ?? null;

                if ($id) {
                    $resolvedId = $this->resolveStudentIdFromNumeric((int) $id, $schoolId);
                    if (! $resolvedId) {
                        $fallbackImportId = $this->findImportIdInSchool((int) $id, $schoolId);
                    }
                } elseif ($email) {
                    $resolvedId = $this->findOrCreateUserIdByEmail($email, $schoolId, $data);
                }
            } elseif (is_numeric($item)) {
                $resolvedId = $this->resolveStudentIdFromNumeric((int) $item, $schoolId);
                if (! $resolvedId) {
                    $fallbackImportId = $this->findImportIdInSchool((int) $item, $schoolId);
                }
            }

            $entryId = $resolvedId ?: $fallbackImportId;
            if ($entryId) {
                $entries[$entryId] = [
                    'id' => $entryId,
                    'comment' => $comment,
                    'sem_1_grade' => $data['sem_1_grade'] ?? null,
                    'sem_2_grade' => $data['sem_2_grade'] ?? null,
                    'sem_grade' => $data['sem_grade'] ?? null,
                    'behaviour_1_grade' => $data['behaviour_1_grade'] ?? null,
                    'behaviour_2_grade' => $data['behaviour_2_grade'] ?? null,
                    'behaviour_grade' => $data['behaviour_grade'] ?? null,
                    'stars' => $this->normalizeStars($data['stars'] ?? []),
                ];
            }
        }

        return array_values($entries);
    }

    public function findImportIdInSchool(int $id, int $schoolId): ?int
    {
        $import = Import116::find($id);
        if (! $import || (int) $import->school_id !== $schoolId) {
            return null;
        }

        return $import->id;
    }

    public function normalizeStars($value): array
    {
        $items = $this->normalizeStudentItems($value);
        $stars = [];

        foreach ($items as $item) {
            if (!is_array($item) && !is_object($item)) {
                continue;
            }

            $data = (array) $item;
            $comment = isset($data['comment']) ? trim((string) $data['comment']) : '';
            if ($comment === '') {
                continue;
            }

            $date = isset($data['date']) ? (string) $data['date'] : null;
            if ($date !== null && $date !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                $date = null;
            }

            $stars[] = [
                'id' => isset($data['id']) && $data['id'] !== '' ? (string) $data['id'] : (string) \Illuminate\Support\Str::uuid(),
                'value' => 1,
                'comment' => $comment,
                'date' => $date ?: now()->toDateString(),
            ];
        }

        return $stars;
    }

    public function resolveStudentIdFromNumeric(int $id, int $schoolId): ?int
    {
        // First check if this is an Import116 ID
        $import = Import116::find($id);
        if ($import && (int) $import->school_id === $schoolId) {
            return $this->findOrCreateUserIdFromImport($import, $schoolId);
        }

        // Fall back to checking if it's a User ID (for already-resolved students)
        $user = User::find($id);
        if ($user && (int) $user->school_id === $schoolId) {
            return $user->id;
        }

        return null;
    }

    public function findOrCreateUserIdByEmail(string $email, int $schoolId, array $data = []): ?int
    {
        $email = trim($email);
        if ($email === '') {
            return null;
        }

        $schoolyearId = $data['schoolyear_id'] ?? null;

        $userQuery = User::where('school_id', $schoolId)
            ->where('email', $email);

        if ($schoolyearId) {
            $userQuery->where('schoolyear_id', $schoolyearId);
        }

        $user = $userQuery->first();

        if ($user) {
            return $user->id;
        }

        $importQuery = Import116::where('school_id', $schoolId)
            ->where('email', $email);

        if ($schoolyearId) {
            $importQuery->where('schoolyear_id', $schoolyearId);
        }

        $import = $importQuery->first();

        if ($import) {
            return $this->findOrCreateUserIdFromImport($import, $schoolId);
        }

        if (! empty($data)) {
            return $this->createStudentUser([
                'schoolyear_id' => $schoolyearId,
                'email' => $email,
                'first_name' => $data['first_name'] ?? null,
                'last_name' => $data['last_name'] ?? null,
                'phone' => $data['phone_1'] ?? $data['phone'] ?? null,
                'sex' => $data['sex'] ?? null,
                'schoolclass' => $data['class'] ?? $data['schoolclass'] ?? null,
            ], $schoolId);
        }

        return null;
    }

    public function findOrCreateUserIdFromImport(Import116 $import, int $schoolId): ?int
    {
        if (! $import->email) {
            return null;
        }

        $user = User::where('school_id', $schoolId)
            ->where('schoolyear_id', $import->schoolyear_id)
            ->where('email', $import->email)
            ->first();

        if ($user) {
            return $user->id;
        }

        return $this->createStudentUser([
            'schoolyear_id' => $import->schoolyear_id,
            'email' => $import->email,
            'first_name' => $import->first_name,
            'last_name' => $import->last_name,
            'phone' => $import->phone_1,
            'sex' => $import->sex,
            'schoolclass' => $import->class,
        ], $schoolId);
    }

    public function createStudentUser(array $data, int $schoolId): ?int
    {
        if (empty($data['email'])) {
            return null;
        }

        $user = User::create([
            'school_id' => $schoolId,
            'schoolyear_id' => $data['schoolyear_id'] ?? null,
            'email' => $data['email'],
            'first_name' => $data['first_name'] ?? null,
            'last_name' => $data['last_name'] ?? null,
            'phone' => $data['phone'] ?? null,
            'sex' => $data['sex'] ?? null,
            'schoolclass' => $data['schoolclass'] ?? null,
            'password' => Hash::make(now()),
        ]);

        $user->email_verified_at = now();
        $user->confirmed_at = now();
        $user->is_active = 1;
        $user->save();

        $user->assignRole('student');

        return $user->id;
    }
}
