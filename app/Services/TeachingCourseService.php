<?php

namespace App\Services;

use App\Models\Import116;
use App\Models\TeachingCourse;
use App\Models\TeachingCourseStudent;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

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
        $entries = $this->resolveCourseStudentEntries($value, $schoolId);
        $ids = [];

        foreach ($entries as $entry) {
            $id = $this->studentEntryId($entry);
            if ($id) {
                $ids[] = $id;
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
        $entries = $this->resolveCourseStudentEntries($value, $schoolId);

        return array_values(array_map(function (array $entry) {
            return [
                'id' => $this->studentEntryId($entry),
                'comment' => $entry['comment'] ?? null,
                'sem_1_grade' => $entry['sem_1_grade'] ?? null,
                'sem_2_grade' => $entry['sem_2_grade'] ?? null,
                'sem_grade' => $entry['sem_grade'] ?? null,
                'behaviour_1_grade' => $entry['behaviour_1_grade'] ?? null,
                'behaviour_2_grade' => $entry['behaviour_2_grade'] ?? null,
                'behaviour_grade' => $entry['behaviour_grade'] ?? null,
                'stars' => $entry['stars'] ?? [],
            ];
        }, $entries));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function resolveCourseStudentEntries($value, int $schoolId): array
    {
        $items = $this->normalizeStudentItems($value);
        $entries = [];

        foreach ($items as $item) {
            $data = (is_array($item) || is_object($item)) ? (array) $item : ['id' => $item];
            $reference = $this->resolveStudentReferenceFromItem($item, $schoolId);

            if (! $reference) {
                continue;
            }

            $entry = [
                'user_id' => $reference['user_id'],
                'import116_id' => $reference['import116_id'],
                'id' => $reference['user_id'] ?: $reference['import116_id'],
                'comment' => $data['comment'] ?? null,
                'sem_1_grade' => $data['sem_1_grade'] ?? null,
                'sem_2_grade' => $data['sem_2_grade'] ?? null,
                'sem_grade' => $data['sem_grade'] ?? null,
                'behaviour_1_grade' => $data['behaviour_1_grade'] ?? null,
                'behaviour_2_grade' => $data['behaviour_2_grade'] ?? null,
                'behaviour_grade' => $data['behaviour_grade'] ?? null,
                'stars' => $this->normalizeStars($data['stars'] ?? []),
            ];

            $key = $this->courseStudentEntryKey($entry);
            if (! $key) {
                continue;
            }

            $entries[$key] = $entry;
        }

        return array_values($entries);
    }

    public function syncCourseStudents(TeachingCourse $course, mixed $studentsPayload, mixed $studentsDeletedPayload = []): void
    {
        $schoolId = (int) $course->school_id;
        if (! $schoolId) {
            return;
        }

        Log::info('=== SYNC COURSE STUDENTS START ===', [
            'course_id' => $course->id,
            'course_title' => $course->title,
            'school_id' => $schoolId,
            'students_payload_count' => is_array($studentsPayload) ? count($studentsPayload) : 0,
            'students_deleted_payload_count' => is_array($studentsDeletedPayload) ? count($studentsDeletedPayload) : 0,
            'students_payload_sample' => is_array($studentsPayload) && count($studentsPayload) > 0 ? array_slice($studentsPayload, 0, 2) : [],
        ]);

        $activeEntries = $this->resolveCourseStudentEntries($studentsPayload, $schoolId);
        $deletedEntries = $this->resolveCourseStudentEntries($studentsDeletedPayload, $schoolId);

        Log::info('Resolved entries', [
            'active_count' => count($activeEntries),
            'deleted_count' => count($deletedEntries),
            'active_sample' => array_slice($activeEntries, 0, 2),
        ]);

        $activeByKey = [];
        foreach ($activeEntries as $entry) {
            $key = $this->courseStudentEntryKey($entry);
            if (! $key) {
                continue;
            }
            $activeByKey[$key] = $entry;
        }

        $deletedByKey = [];
        foreach ($deletedEntries as $entry) {
            $key = $this->courseStudentEntryKey($entry);
            if (! $key || isset($activeByKey[$key])) {
                continue;
            }
            $deletedByKey[$key] = $entry;
        }

        $existing = $course->teachingCourseStudents()->withTrashed()->get();
        $existingByKey = [];
        foreach ($existing as $courseStudent) {
            $key = $this->courseStudentModelKey($courseStudent);
            if (! $key) {
                continue;
            }
            $existingByKey[$key] = $courseStudent;
        }

        Log::info('Existing course students in DB', [
            'existing_count' => count($existing),
            'existing_keys' => array_keys($existingByKey),
            'active_keys' => array_keys($activeByKey),
            'deleted_keys' => array_keys($deletedByKey),
        ]);

        $updated = 0;
        $restored = 0;
        $markedDeleted = 0;
        $softDeleted = 0;

        foreach ($existingByKey as $key => $courseStudent) {
            if (isset($activeByKey[$key])) {
                Log::info('Updating existing active student', ['key' => $key, 'user_id' => $activeByKey[$key]['user_id'] ?? null]);
                $courseStudent->fill($this->buildCourseStudentPayload($activeByKey[$key]));
                $courseStudent->save();
                if ($courseStudent->trashed()) {
                    $courseStudent->restore();
                    $restored++;
                }
                $updated++;
                unset($activeByKey[$key], $deletedByKey[$key]);
                continue;
            }

            if (isset($deletedByKey[$key])) {
                Log::info('Updating existing to deleted', ['key' => $key]);
                $courseStudent->fill($this->buildCourseStudentPayload($deletedByKey[$key]));
                $courseStudent->save();
                if (! $courseStudent->trashed()) {
                    $courseStudent->delete();
                    $markedDeleted++;
                }
                unset($deletedByKey[$key]);
                continue;
            }

            if (! $courseStudent->trashed()) {
                Log::info('Soft deleting student (not in active or deleted)', ['key' => $key]);
                $courseStudent->delete();
                $softDeleted++;
            }
        }

        $created = 0;
        foreach ($activeByKey as $key => $entry) {
            $payload = $this->buildCourseStudentPayload($entry);
            Log::info('Creating NEW student', ['key' => $key, 'payload' => $payload]);
            $course->teachingCourseStudents()->create($payload);
            $created++;
        }

        $createdDeleted = 0;
        foreach ($deletedByKey as $key => $entry) {
            Log::info('Creating student as deleted', ['key' => $key]);
            $created = $course->teachingCourseStudents()->create($this->buildCourseStudentPayload($entry));
            $created->delete();
            $createdDeleted++;
        }

        Log::info('=== SYNC COURSE STUDENTS END ===', [
            'updated' => $updated,
            'restored' => $restored,
            'created' => $created,
            'soft_deleted' => $softDeleted,
            'marked_deleted' => $markedDeleted,
            'created_deleted' => $createdDeleted,
        ]);
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
            if (! is_array($item) && ! is_object($item)) {
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
        // Check both User and Import116 tables
        $user = User::find($id);
        $import = Import116::find($id);

        $userBelongsToSchool = $user && (int) $user->school_id === $schoolId;
        $importBelongsToSchool = $import && (int) $import->school_id === $schoolId;

        // If both exist for this school, prefer User (already resolved entity)
        if ($userBelongsToSchool && $importBelongsToSchool) {
            return $user->id;
        }

        // If only User exists, use it (already resolved)
        if ($userBelongsToSchool) {
            return $user->id;
        }

        // If only Import116 exists, resolve it to User (new assignment)
        if ($importBelongsToSchool) {
            return $this->findOrCreateUserIdFromImport($import, $schoolId);
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

        $userQuery = User::where('school_id', $schoolId)
            ->where('email', $import->email);

        if ($import->schoolyear_id) {
            $userQuery->where('schoolyear_id', $import->schoolyear_id);
        }

        $user = $userQuery->first();

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

        $existingUser = User::where('school_id', $schoolId)
            ->where('email', $data['email'])
            ->first();

        if ($existingUser) {
            return $existingUser->id;
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

    private function resolveStudentReferenceFromItem(mixed $item, int $schoolId): ?array
    {
        if (is_array($item) || is_object($item)) {
            $data = (array) $item;

            Log::debug('Resolving student reference', [
                'id' => $data['id'] ?? null,
                'user_id' => $data['user_id'] ?? null,
                'import116_id' => $data['import116_id'] ?? null,
                'email' => $data['email'] ?? null,
            ]);

            if (isset($data['user_id']) && is_numeric($data['user_id'])) {
                $user = User::find((int) $data['user_id']);
                if ($user && (int) $user->school_id === $schoolId) {
                    Log::debug('Resolved via explicit user_id', ['user_id' => $user->id]);
                    return ['user_id' => $user->id, 'import116_id' => null];
                }
            }

            if (isset($data['import116_id']) && is_numeric($data['import116_id'])) {
                $importId = $this->findImportIdInSchool((int) $data['import116_id'], $schoolId);
                if ($importId) {
                    $import = Import116::find($importId);
                    if ($import && $import->email) {
                        $userId = $this->findOrCreateUserIdFromImport($import, $schoolId);
                        if ($userId) {
                            return ['user_id' => $userId, 'import116_id' => null];
                        }
                    }

                    return ['user_id' => null, 'import116_id' => $importId];
                }
            }

            if (isset($data['id']) && is_numeric($data['id'])) {
                $reference = $this->resolveStudentReferenceFromNumeric((int) $data['id'], $schoolId);
                if ($reference) {
                    Log::debug('Resolved via id', ['id' => $data['id'], 'user_id' => $reference['user_id'], 'import116_id' => $reference['import116_id']]);
                    return $reference;
                }
            }

            $email = isset($data['email']) ? trim((string) $data['email']) : '';
            if ($email !== '') {
                $reference = $this->resolveStudentReferenceByEmail($email, $schoolId, $data);
                if ($reference) {
                    return $reference;
                }
            }
        } elseif (is_numeric($item)) {
            return $this->resolveStudentReferenceFromNumeric((int) $item, $schoolId);
        } elseif (is_string($item)) {
            $email = trim($item);
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return $this->resolveStudentReferenceByEmail($email, $schoolId, []);
            }
        }

        return null;
    }

    private function resolveStudentReferenceFromNumeric(int $id, int $schoolId): ?array
    {
        $resolvedUserId = $this->resolveStudentIdFromNumeric($id, $schoolId);
        if ($resolvedUserId) {
            return ['user_id' => $resolvedUserId, 'import116_id' => null];
        }

        $fallbackImportId = $this->findImportIdInSchool($id, $schoolId);
        if ($fallbackImportId) {
            $import = Import116::find($fallbackImportId);
            if ($import && $import->email) {
                $fallbackUserId = $this->findOrCreateUserIdFromImport($import, $schoolId);
                if ($fallbackUserId) {
                    return ['user_id' => $fallbackUserId, 'import116_id' => null];
                }
            }

            return ['user_id' => null, 'import116_id' => $fallbackImportId];
        }

        return null;
    }

    private function resolveStudentReferenceByEmail(string $email, int $schoolId, array $data): ?array
    {
        $resolvedUserId = $this->findOrCreateUserIdByEmail($email, $schoolId, $data);
        if ($resolvedUserId) {
            return ['user_id' => $resolvedUserId, 'import116_id' => null];
        }

        $schoolyearId = $data['schoolyear_id'] ?? null;

        $importQuery = Import116::where('school_id', $schoolId)
            ->where('email', $email);

        if ($schoolyearId) {
            $importQuery->where('schoolyear_id', $schoolyearId);
        }

        $import = $importQuery->first();
        if (! $import) {
            return null;
        }

        $fallbackUserId = $this->findOrCreateUserIdFromImport($import, $schoolId);
        if ($fallbackUserId) {
            return ['user_id' => $fallbackUserId, 'import116_id' => null];
        }

        return ['user_id' => null, 'import116_id' => (int) $import->id];
    }

    private function courseStudentEntryKey(array $entry): ?string
    {
        $userId = isset($entry['user_id']) ? (int) $entry['user_id'] : 0;
        if ($userId > 0) {
            return 'u:'.$userId;
        }

        $importId = isset($entry['import116_id']) ? (int) $entry['import116_id'] : 0;
        if ($importId > 0) {
            return 'i:'.$importId;
        }

        return null;
    }

    private function courseStudentModelKey(TeachingCourseStudent $courseStudent): ?string
    {
        if ($courseStudent->user_id) {
            return 'u:'.$courseStudent->user_id;
        }

        if ($courseStudent->import116_id) {
            return 'i:'.$courseStudent->import116_id;
        }

        return null;
    }

    private function studentEntryId(array $entry): ?int
    {
        $userId = isset($entry['user_id']) ? (int) $entry['user_id'] : 0;
        if ($userId > 0) {
            return $userId;
        }

        $importId = isset($entry['import116_id']) ? (int) $entry['import116_id'] : 0;
        if ($importId > 0) {
            return $importId;
        }

        $legacyId = isset($entry['id']) ? (int) $entry['id'] : 0;
        return $legacyId > 0 ? $legacyId : null;
    }

    /**
     * @param array<string, mixed> $entry
     * @return array<string, mixed>
     */
    private function buildCourseStudentPayload(array $entry): array
    {
        return [
            'user_id' => $entry['user_id'] ?? null,
            'import116_id' => $entry['import116_id'] ?? null,
            'comment' => $entry['comment'] ?? null,
            'sem_1_grade' => $entry['sem_1_grade'] ?? null,
            'sem_2_grade' => $entry['sem_2_grade'] ?? null,
            'sem_grade' => $entry['sem_grade'] ?? null,
            'behaviour_1_grade' => $entry['behaviour_1_grade'] ?? null,
            'behaviour_2_grade' => $entry['behaviour_2_grade'] ?? null,
            'behaviour_grade' => $entry['behaviour_grade'] ?? null,
            'stars' => $entry['stars'] ?? [],
        ];
    }
}
