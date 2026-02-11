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

            if (is_array($item) || is_object($item)) {
                $data = (array) $item;
                $email = $data['email'] ?? null;
                $id = $data['id'] ?? null;

                if ($email) {
                    $resolvedId = $this->findOrCreateUserIdByEmail($email, $schoolId, $data);
                } elseif ($id) {
                    $resolvedId = $this->resolveStudentIdFromNumeric((int) $id, $schoolId);
                }
            } elseif (is_numeric($item)) {
                $resolvedId = $this->resolveStudentIdFromNumeric((int) $item, $schoolId);
            }

            if ($resolvedId) {
                $ids[] = $resolvedId;
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
            $data = [];
            $comment = null;

            if (is_array($item) || is_object($item)) {
                $data = (array) $item;
                $comment = $data['comment'] ?? null;
                $email = $data['email'] ?? null;
                $id = $data['id'] ?? null;

                if ($email) {
                    $resolvedId = $this->findOrCreateUserIdByEmail($email, $schoolId, $data);
                } elseif ($id) {
                    $resolvedId = $this->resolveStudentIdFromNumeric((int) $id, $schoolId);
                }
            } elseif (is_numeric($item)) {
                $resolvedId = $this->resolveStudentIdFromNumeric((int) $item, $schoolId);
            }

            if ($resolvedId) {
                $entries[$resolvedId] = [
                    'id' => $resolvedId,
                    'comment' => $comment,
                    'sem_1_grade' => $data['sem_1_grade'] ?? null,
                    'sem_2_grade' => $data['sem_2_grade'] ?? null,
                    'sem_grade' => $data['sem_grade'] ?? null,
                    'behaviour_1_grade' => $data['behaviour_1_grade'] ?? null,
                    'behaviour_2_grade' => $data['behaviour_2_grade'] ?? null,
                    'behaviour_grade' => $data['behaviour_grade'] ?? null,
                ];
            }
        }

        return array_values($entries);
    }

    public function resolveStudentIdFromNumeric(int $id, int $schoolId): ?int
    {
        $user = User::find($id);
        if ($user && (int) $user->school_id === $schoolId) {
            return $user->id;
        }

        $import = Import116::find($id);
        if (! $import || (int) $import->school_id !== $schoolId) {
            return null;
        }

        return $this->findOrCreateUserIdFromImport($import, $schoolId);
    }

    public function findOrCreateUserIdByEmail(string $email, int $schoolId, array $data = []): ?int
    {
        $email = trim($email);
        if ($email === '') {
            return null;
        }

        $user = User::where('school_id', $schoolId)
            ->where('email', $email)
            ->first();

        if ($user) {
            return $user->id;
        }

        $import = Import116::where('school_id', $schoolId)
            ->where('email', $email)
            ->first();

        if ($import) {
            return $this->findOrCreateUserIdFromImport($import, $schoolId);
        }

        if (! empty($data)) {
            return $this->createStudentUser([
                'schoolyear_id' => $data['schoolyear_id'] ?? null,
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
        // $user->confirmed_at = now();
        $user->is_active = 1;
        $user->save();

        $user->assignRole('student');

        return $user->id;
    }
}
