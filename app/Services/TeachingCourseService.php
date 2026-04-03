<?php

namespace App\Services;

use App\Models\Import116;
use App\Models\TeachingCourse;
use App\Models\TeachingCourseBehaviourEntry;
use App\Models\TeachingCourseDate;
use App\Models\TeachingCourseStudent;
use App\Models\TeachingCourseStudentEntry;
use App\Models\TeachingCourseWork;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TeachingCourseService
{
    public function normalizeStudentIds($value): array
    {
        if (is_array($value)) {
            return array_values(array_filter($value));
        }

        if ($value instanceof Collection) {
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

        if ($value instanceof Collection) {
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
                    'canceled_at' => array_key_exists('canceled_at', $data)
                        ? $this->normalizeCanceledAt($data['canceled_at'])
                        : null,
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
                'canceled_at' => $entry['canceled_at'] ?? null,
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
            if (array_key_exists('canceled_at', $data)) {
                $entry['canceled_at'] = $this->normalizeCanceledAt($data['canceled_at']);
            }

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

        $activeEntries = $this->resolveCourseStudentEntries($studentsPayload, $schoolId);
        $deletedEntries = $this->resolveCourseStudentEntries($studentsDeletedPayload, $schoolId);

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

        $protectedRemovalReasons = $this->collectProtectedRemovalReasons($course, $existingByKey, $activeByKey);

        $updated = 0;
        $restored = 0;
        $markedDeleted = 0;
        $softDeleted = 0;

        foreach ($existingByKey as $key => $courseStudent) {
            if (isset($activeByKey[$key])) {
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
                if (isset($protectedRemovalReasons[$key])) {
                    $payload = $this->buildCourseStudentPayload($deletedByKey[$key]);
                    if (array_key_exists('canceled_at', $payload)) {
                        $courseStudent->fill(['canceled_at' => $payload['canceled_at']]);
                        $courseStudent->save();
                    }

                    unset($deletedByKey[$key]);

                    continue;
                }

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
                if (isset($protectedRemovalReasons[$key])) {
                    continue;
                }

                $courseStudent->delete();
                $softDeleted++;
            }
        }

        $created = 0;
        foreach ($activeByKey as $key => $entry) {
            $course->teachingCourseStudents()->create($this->buildCourseStudentPayload($entry));
            $created++;
        }

        $createdDeleted = 0;
        foreach ($deletedByKey as $key => $entry) {
            $created = $course->teachingCourseStudents()->create($this->buildCourseStudentPayload($entry));
            $created->delete();
            $createdDeleted++;
        }
    }

    /**
     * @return array<string, string>
     */
    public function removalReasonsForCourse(TeachingCourse $course): array
    {
        $rows = $course->relationLoaded('teachingCourseStudents')
            ? $course->teachingCourseStudents
            : $course->teachingCourseStudents()->get();

        $existingByKey = [];
        foreach ($rows as $courseStudent) {
            $key = $this->courseStudentModelKey($courseStudent);
            if (! $key) {
                continue;
            }

            $existingByKey[$key] = $courseStudent;
        }

        return $this->collectProtectedRemovalReasons($course, $existingByKey, []);
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
            if ($date !== null && $date !== '' && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                $date = null;
            }

            $stars[] = [
                'id' => isset($data['id']) && $data['id'] !== '' ? (string) $data['id'] : (string) Str::uuid(),
                'value' => 1,
                'comment' => $comment,
                'date' => $date ?: now()->toDateString(),
            ];
        }

        return $stars;
    }

    public function normalizeCanceledAt(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        try {
            return Carbon::parse($raw)->toDateTimeString();
        } catch (\Throwable $e) {
            return null;
        }
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

            if (isset($data['user_id']) && is_numeric($data['user_id'])) {
                $user = User::find((int) $data['user_id']);
                if ($user && (int) $user->school_id === $schoolId) {
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

            // Legacy clients may send only `id` for Import116 rows.
            // If payload clearly looks like an Import116 student, prefer that source.
            $contextualImportReference = $this->resolveStudentReferenceFromContextualImportId($data, $schoolId);
            if ($contextualImportReference) {
                return $contextualImportReference;
            }

            // Prefer email over ambiguous id - email unambiguously identifies the person
            $email = isset($data['email']) ? trim((string) $data['email']) : '';
            if ($email !== '') {
                $reference = $this->resolveStudentReferenceByEmail($email, $schoolId, $data);
                if ($reference) {
                    return $reference;
                }
            }

            // Fallback: resolve by ambiguous id (could be user_id or import116_id)
            if (isset($data['id']) && is_numeric($data['id'])) {
                $reference = $this->resolveStudentReferenceFromNumeric((int) $data['id'], $schoolId);
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

    private function resolveStudentReferenceFromContextualImportId(array $data, int $schoolId): ?array
    {
        if (! isset($data['id']) || ! is_numeric($data['id'])) {
            return null;
        }

        $importId = $this->findImportIdInSchool((int) $data['id'], $schoolId);
        if (! $importId) {
            return null;
        }

        $import = Import116::find($importId);
        if (! $import || ! $this->itemLooksLikeImportReference($data, $import)) {
            return null;
        }

        if ($import->email) {
            $userId = $this->findOrCreateUserIdFromImport($import, $schoolId);
            if ($userId) {
                return ['user_id' => $userId, 'import116_id' => null];
            }
        }

        return ['user_id' => null, 'import116_id' => $importId];
    }

    private function itemLooksLikeImportReference(array $data, Import116 $import): bool
    {
        $hasImportHint = array_key_exists('class', $data)
            || array_key_exists('student_code', $data)
            || array_key_exists('import116_id', $data);

        if (! $hasImportHint) {
            return false;
        }

        $firstName = isset($data['first_name']) ? trim((string) $data['first_name']) : '';
        if ($firstName !== '' && $firstName !== (string) ($import->first_name ?? '')) {
            return false;
        }

        $lastName = isset($data['last_name']) ? trim((string) $data['last_name']) : '';
        if ($lastName !== '' && $lastName !== (string) ($import->last_name ?? '')) {
            return false;
        }

        $class = isset($data['class']) ? trim((string) $data['class']) : '';
        if ($class !== '' && $class !== (string) ($import->class ?? '')) {
            return false;
        }

        $email = isset($data['email']) ? trim((string) $data['email']) : '';
        if ($email !== '') {
            $importEmail = trim((string) ($import->email ?? ''));
            if ($importEmail === '' || strcasecmp($email, $importEmail) !== 0) {
                return false;
            }
        }

        return true;
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
     * @param  array<string, mixed>  $entry
     * @return array<string, mixed>
     */
    private function buildCourseStudentPayload(array $entry): array
    {
        $payload = [
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

        if (array_key_exists('canceled_at', $entry)) {
            $payload['canceled_at'] = $entry['canceled_at'];
        }

        return $payload;
    }

    /**
     * @param  array<string, TeachingCourseStudent>  $existingByKey
     * @param  array<string, array<string, mixed>>  $activeByKey
     * @return array<string, string>
     */
    private function collectProtectedRemovalReasons(TeachingCourse $course, array $existingByKey, array $activeByKey): array
    {
        $candidates = [];
        foreach ($existingByKey as $key => $courseStudent) {
            if ($courseStudent->trashed() || isset($activeByKey[$key])) {
                continue;
            }

            $candidates[$key] = $courseStudent;
        }

        if (empty($candidates)) {
            return [];
        }

        $candidateUserIds = [];
        foreach ($candidates as $courseStudent) {
            if ($courseStudent->user_id) {
                $candidateUserIds[] = (int) $courseStudent->user_id;
            }
        }

        $dependentUserIdSet = $this->collectDependentUserIdSet($course, $candidateUserIds);

        $protected = [];
        foreach ($candidates as $key => $courseStudent) {
            if ($this->hasProtectedCourseStudentData($courseStudent)) {
                $protected[$key] = 'course_student_data';

                continue;
            }

            $userId = (int) ($courseStudent->user_id ?? 0);
            if ($userId > 0 && isset($dependentUserIdSet[$userId])) {
                $protected[$key] = 'dependent_records';
            }
        }

        return $protected;
    }

    /**
     * @param  array<int, int>  $userIds
     * @return array<int, bool>
     */
    private function collectDependentUserIdSet(TeachingCourse $course, array $userIds): array
    {
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds), fn (int $id) => $id > 0)));
        if (empty($userIds)) {
            return [];
        }

        $dependent = [];
        $courseId = (int) $course->id;

        $entryUserIds = TeachingCourseStudentEntry::query()
            ->where('teaching_course_id', $courseId)
            ->whereIn('user_id', $userIds)
            ->pluck('user_id');
        foreach ($entryUserIds as $userId) {
            $dependent[(int) $userId] = true;
        }

        $behaviourUserIds = TeachingCourseBehaviourEntry::query()
            ->where('teaching_course_id', $courseId)
            ->whereIn('user_id', $userIds)
            ->pluck('user_id');
        foreach ($behaviourUserIds as $userId) {
            $dependent[(int) $userId] = true;
        }

        foreach ($this->collectAttendanceDependentUserIds($courseId, $userIds) as $userId) {
            $dependent[$userId] = true;
        }

        foreach ($this->collectWorkGroupDependentUserIds($courseId, $userIds) as $userId) {
            $dependent[$userId] = true;
        }

        return $dependent;
    }

    /**
     * @param  array<int, int>  $candidateUserIds
     * @return array<int, int>
     */
    private function collectAttendanceDependentUserIds(int $courseId, array $candidateUserIds): array
    {
        if (empty($candidateUserIds)) {
            return [];
        }

        $candidateSet = array_fill_keys($candidateUserIds, true);
        $dependent = [];

        $courseDates = TeachingCourseDate::query()
            ->where('teaching_course_id', $courseId)
            ->get(['attendance', 'status']);

        foreach ($courseDates as $courseDate) {
            $attendance = is_array($courseDate->attendance) ? $courseDate->attendance : [];
            foreach (array_keys($attendance) as $studentKey) {
                $studentId = $this->normalizeAttendanceStudentId($studentKey);
                if ($studentId !== null && isset($candidateSet[$studentId])) {
                    $dependent[$studentId] = true;
                }
            }

            $status = is_array($courseDate->status) ? $courseDate->status : [];
            foreach ($status as $statusItem) {
                if (! is_string($statusItem) || ! str_starts_with($statusItem, 'att:')) {
                    continue;
                }

                $parts = explode(':', $statusItem);
                if (count($parts) < 3) {
                    continue;
                }

                $studentId = $this->normalizeAttendanceStudentId($parts[1] ?? null);
                if ($studentId !== null && isset($candidateSet[$studentId])) {
                    $dependent[$studentId] = true;
                }
            }
        }

        return array_values(array_map('intval', array_keys($dependent)));
    }

    /**
     * @param  array<int, int>  $candidateUserIds
     * @return array<int, int>
     */
    private function collectWorkGroupDependentUserIds(int $courseId, array $candidateUserIds): array
    {
        if (empty($candidateUserIds)) {
            return [];
        }

        $candidateSet = array_fill_keys($candidateUserIds, true);
        $dependent = [];

        $works = TeachingCourseWork::query()
            ->where('teaching_course_id', $courseId)
            ->get(['groups']);

        foreach ($works as $work) {
            $groups = is_array($work->groups) ? $work->groups : [];
            foreach ($groups as $group) {
                if (! is_array($group)) {
                    continue;
                }

                foreach ((array) ($group['student_ids'] ?? []) as $studentId) {
                    $id = (int) $studentId;
                    if ($id > 0 && isset($candidateSet[$id])) {
                        $dependent[$id] = true;
                    }
                }

                foreach ((array) ($group['grades'] ?? []) as $gradeItem) {
                    if (! is_array($gradeItem)) {
                        continue;
                    }

                    $id = (int) ($gradeItem['student_id'] ?? 0);
                    if ($id > 0 && isset($candidateSet[$id])) {
                        $dependent[$id] = true;
                    }
                }

                foreach ((array) ($group['comments'] ?? []) as $commentItem) {
                    if (! is_array($commentItem)) {
                        continue;
                    }

                    $id = (int) ($commentItem['student_id'] ?? 0);
                    if ($id > 0 && isset($candidateSet[$id])) {
                        $dependent[$id] = true;
                    }
                }
            }
        }

        return array_values(array_map('intval', array_keys($dependent)));
    }

    private function normalizeAttendanceStudentId(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }

        $studentKey = trim((string) $value);
        if ($studentKey === '') {
            return null;
        }

        if (str_starts_with($studentKey, 's_')) {
            $studentKey = substr($studentKey, 2);
        }

        if (! ctype_digit($studentKey)) {
            return null;
        }

        $id = (int) $studentKey;

        return $id > 0 ? $id : null;
    }

    private function hasProtectedCourseStudentData(TeachingCourseStudent $courseStudent): bool
    {
        $fields = [
            $courseStudent->comment,
            $courseStudent->sem_1_grade,
            $courseStudent->sem_2_grade,
            $courseStudent->sem_grade,
            $courseStudent->behaviour_1_grade,
            $courseStudent->behaviour_2_grade,
            $courseStudent->behaviour_grade,
        ];

        foreach ($fields as $field) {
            if ($this->hasMeaningfulValue($field)) {
                return true;
            }
        }

        $stars = $courseStudent->stars;

        return is_array($stars) && ! empty($stars);
    }

    private function hasMeaningfulValue(mixed $value): bool
    {
        if ($value === null) {
            return false;
        }

        return trim((string) $value) !== '';
    }
}
