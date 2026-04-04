<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * @var array<string, int|null>
     */
    private array $userByIdCache = [];

    /**
     * @var array<string, int|null>
     */
    private array $importByIdCache = [];

    /**
     * @var array<string, int|null>
     */
    private array $userByEmailCache = [];

    /**
     * @var array<string, int|null>
     */
    private array $importByEmailCache = [];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('teaching_courses') || ! Schema::hasTable('teaching_course_students')) {
            return;
        }

        $hasStudentsColumn = Schema::hasColumn('teaching_courses', 'students');
        $hasStudentsDeletedColumn = Schema::hasColumn('teaching_courses', 'students_deleted');

        if (! $hasStudentsColumn && ! $hasStudentsDeletedColumn) {
            return;
        }

        $columns = ['id', 'school_id', 'schoolyear_id'];
        if ($hasStudentsColumn) {
            $columns[] = 'students';
        }
        if ($hasStudentsDeletedColumn) {
            $columns[] = 'students_deleted';
        }

        DB::table('teaching_courses')
            ->select($columns)
            ->orderBy('id')
            ->chunkById(100, function ($courses) use ($hasStudentsColumn, $hasStudentsDeletedColumn) {
                foreach ($courses as $course) {
                    $this->migrateCourse($course, $hasStudentsColumn, $hasStudentsDeletedColumn);
                }
            });

        if ($hasStudentsColumn) {
            Schema::table('teaching_courses', function (Blueprint $table) {
                $table->dropColumn('students');
            });
        }

        if ($hasStudentsDeletedColumn) {
            Schema::table('teaching_courses', function (Blueprint $table) {
                $table->dropColumn('students_deleted');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('teaching_courses', function (Blueprint $table) {
            if (! Schema::hasColumn('teaching_courses', 'students')) {
                $table->json('students')->nullable()->after('classes');
            }
            if (! Schema::hasColumn('teaching_courses', 'students_deleted')) {
                $table->json('students_deleted')->nullable()->after('students');
            }
        });
    }

    private function migrateCourse(object $course, bool $hasStudentsColumn, bool $hasStudentsDeletedColumn): void
    {
        $courseId = (int) $course->id;
        $schoolId = (int) ($course->school_id ?? 0);
        $schoolyearId = isset($course->schoolyear_id) ? (int) $course->schoolyear_id : null;

        $activeEntries = $hasStudentsColumn
            ? $this->normalizeLegacyEntries($course->students ?? null, $schoolId, $schoolyearId)
            : [];
        $deletedEntries = $hasStudentsDeletedColumn
            ? $this->normalizeLegacyEntries($course->students_deleted ?? null, $schoolId, $schoolyearId)
            : [];

        if (empty($activeEntries) && empty($deletedEntries)) {
            return;
        }

        foreach (array_keys($activeEntries) as $key) {
            unset($deletedEntries[$key]);
        }

        $existingRows = DB::table('teaching_course_students')
            ->where('teaching_course_id', $courseId)
            ->get();

        $existingByKey = [];
        foreach ($existingRows as $existingRow) {
            $key = $this->entryKey(
                $existingRow->user_id ? (int) $existingRow->user_id : null,
                $existingRow->import116_id ? (int) $existingRow->import116_id : null
            );
            if (! $key) {
                continue;
            }
            $existingByKey[$key] = $existingRow;
        }

        $handledKeys = [];
        $now = now();

        foreach ($activeEntries as $key => $entry) {
            $existing = $existingByKey[$key] ?? null;
            $payload = $this->buildPersistPayload($courseId, $entry, false, $now);

            if ($existing) {
                DB::table('teaching_course_students')
                    ->where('id', $existing->id)
                    ->update($payload);
            } else {
                DB::table('teaching_course_students')->insert(array_merge($payload, [
                    'created_at' => $now,
                ]));
            }

            $handledKeys[$key] = true;
        }

        foreach ($deletedEntries as $key => $entry) {
            $existing = $existingByKey[$key] ?? null;
            $payload = $this->buildPersistPayload($courseId, $entry, true, $now);

            if ($existing) {
                if (! $existing->deleted_at) {
                    $payload['deleted_at'] = $now;
                }

                DB::table('teaching_course_students')
                    ->where('id', $existing->id)
                    ->update($payload);
            } else {
                DB::table('teaching_course_students')->insert(array_merge($payload, [
                    'created_at' => $now,
                    'deleted_at' => $now,
                ]));
            }

            $handledKeys[$key] = true;
        }

        foreach ($existingByKey as $key => $existing) {
            if (isset($handledKeys[$key]) || $existing->deleted_at) {
                continue;
            }

            DB::table('teaching_course_students')
                ->where('id', $existing->id)
                ->update([
                    'deleted_at' => $now,
                    'updated_at' => $now,
                ]);
        }
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function normalizeLegacyEntries(mixed $value, int $schoolId, ?int $schoolyearId): array
    {
        $items = $this->normalizeLegacyItems($value);
        $entries = [];

        foreach ($items as $item) {
            $data = (is_array($item) || is_object($item)) ? (array) $item : ['id' => $item];
            $ref = $this->resolveReference($data, $schoolId, $schoolyearId);
            if (! $ref) {
                continue;
            }

            $key = $this->entryKey($ref['user_id'], $ref['import116_id']);
            if (! $key) {
                continue;
            }

            $entries[$key] = [
                'user_id' => $ref['user_id'],
                'import116_id' => $ref['import116_id'],
                'comment' => $this->nullableString($data['comment'] ?? null),
                'sem_1_grade' => $this->nullableString($data['sem_1_grade'] ?? null),
                'sem_2_grade' => $this->nullableString($data['sem_2_grade'] ?? null),
                'sem_grade' => $this->nullableString($data['sem_grade'] ?? null),
                'behaviour_1_grade' => $this->nullableString($data['behaviour_1_grade'] ?? null),
                'behaviour_2_grade' => $this->nullableString($data['behaviour_2_grade'] ?? null),
                'behaviour_grade' => $this->nullableString($data['behaviour_grade'] ?? null),
                'stars' => $this->normalizeStars($data['stars'] ?? []),
            ];
        }

        return $entries;
    }

    /**
     * @return array<int, mixed>
     */
    private function normalizeLegacyItems(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }

    /**
     * @return array{user_id: int|null, import116_id: int|null}|null
     */
    private function resolveReference(array $data, int $schoolId, ?int $schoolyearId): ?array
    {
        $directUserId = isset($data['user_id']) && is_numeric($data['user_id'])
            ? $this->findUserIdInSchool((int) $data['user_id'], $schoolId)
            : null;
        if ($directUserId) {
            return ['user_id' => $directUserId, 'import116_id' => null];
        }

        $directImportId = isset($data['import116_id']) && is_numeric($data['import116_id'])
            ? $this->findImportIdInSchool((int) $data['import116_id'], $schoolId)
            : null;
        if ($directImportId) {
            return ['user_id' => null, 'import116_id' => $directImportId];
        }

        $id = $data['id'] ?? null;
        if (is_numeric($id)) {
            $numericId = (int) $id;
            $userId = $this->findUserIdInSchool($numericId, $schoolId);
            if ($userId) {
                return ['user_id' => $userId, 'import116_id' => null];
            }

            $importId = $this->findImportIdInSchool($numericId, $schoolId);
            if ($importId) {
                return ['user_id' => null, 'import116_id' => $importId];
            }
        }

        $email = isset($data['email']) ? trim((string) $data['email']) : '';
        if ($email === '') {
            return null;
        }

        $userId = $this->findUserIdByEmail($email, $schoolId, $schoolyearId);
        if ($userId) {
            return ['user_id' => $userId, 'import116_id' => null];
        }

        $importId = $this->findImportIdByEmail($email, $schoolId, $schoolyearId);
        if ($importId) {
            return ['user_id' => null, 'import116_id' => $importId];
        }

        return null;
    }

    private function findUserIdInSchool(int $id, int $schoolId): ?int
    {
        $cacheKey = $schoolId.':'.$id;
        if (array_key_exists($cacheKey, $this->userByIdCache)) {
            return $this->userByIdCache[$cacheKey];
        }

        $value = DB::table('users')
            ->where('id', $id)
            ->where('school_id', $schoolId)
            ->value('id');

        return $this->userByIdCache[$cacheKey] = $value ? (int) $value : null;
    }

    private function findImportIdInSchool(int $id, int $schoolId): ?int
    {
        $cacheKey = $schoolId.':'.$id;
        if (array_key_exists($cacheKey, $this->importByIdCache)) {
            return $this->importByIdCache[$cacheKey];
        }

        $value = DB::table('import116')
            ->where('id', $id)
            ->where('school_id', $schoolId)
            ->value('id');

        return $this->importByIdCache[$cacheKey] = $value ? (int) $value : null;
    }

    private function findUserIdByEmail(string $email, int $schoolId, ?int $schoolyearId): ?int
    {
        $cacheKey = $schoolId.':'.($schoolyearId ?? 'null').':'.strtolower($email);
        if (array_key_exists($cacheKey, $this->userByEmailCache)) {
            return $this->userByEmailCache[$cacheKey];
        }

        $query = DB::table('users')
            ->where('school_id', $schoolId)
            ->where('email', $email);

        if ($schoolyearId) {
            $query->where('schoolyear_id', $schoolyearId);
        }

        $value = $query->value('id');

        return $this->userByEmailCache[$cacheKey] = $value ? (int) $value : null;
    }

    private function findImportIdByEmail(string $email, int $schoolId, ?int $schoolyearId): ?int
    {
        $cacheKey = $schoolId.':'.($schoolyearId ?? 'null').':'.strtolower($email);
        if (array_key_exists($cacheKey, $this->importByEmailCache)) {
            return $this->importByEmailCache[$cacheKey];
        }

        $query = DB::table('import116')
            ->where('school_id', $schoolId)
            ->where('email', $email);

        if ($schoolyearId) {
            $query->where('schoolyear_id', $schoolyearId);
        }

        $value = $query->value('id');

        return $this->importByEmailCache[$cacheKey] = $value ? (int) $value : null;
    }

    private function entryKey(?int $userId, ?int $importId): ?string
    {
        if ($userId) {
            return 'u:'.$userId;
        }
        if ($importId) {
            return 'i:'.$importId;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $entry
     * @return array<string, mixed>
     */
    private function buildPersistPayload(int $courseId, array $entry, bool $deleted, Carbon $now): array
    {
        return [
            'teaching_course_id' => $courseId,
            'user_id' => $entry['user_id'] ?? null,
            'import116_id' => $entry['import116_id'] ?? null,
            'comment' => $entry['comment'] ?? null,
            'sem_1_grade' => $entry['sem_1_grade'] ?? null,
            'sem_2_grade' => $entry['sem_2_grade'] ?? null,
            'sem_grade' => $entry['sem_grade'] ?? null,
            'behaviour_1_grade' => $entry['behaviour_1_grade'] ?? null,
            'behaviour_2_grade' => $entry['behaviour_2_grade'] ?? null,
            'behaviour_grade' => $entry['behaviour_grade'] ?? null,
            'stars' => $this->toJson($entry['stars'] ?? []),
            'deleted_at' => $deleted ? $now : null,
            'updated_at' => $now,
        ];
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function normalizeStars(mixed $value): array
    {
        $items = $this->normalizeLegacyItems($value);
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

    private function toJson(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $encoded = json_encode($value);
        if ($encoded === false) {
            return null;
        }

        return $encoded;
    }
};
