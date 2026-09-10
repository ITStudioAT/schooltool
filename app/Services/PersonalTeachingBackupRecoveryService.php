<?php

namespace App\Services;

use App\Models\PersonalTeachingBackup;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PersonalTeachingBackupRecoveryService
{
    private const STUDENT_ROLES = ['student', 'studentstimetables_user'];

    private const STUDENT_TABLES = [
        'teaching_course_students', 'teaching_course_student_entries',
        'teaching_course_behaviour_entries', 'teaching_course_student_category_evaluations',
        'teaching_course_work_group_students',
    ];

    public function requestRecovery(User $user, PersonalTeachingBackup $backup): void
    {
        abort_unless((int) $backup->user_id === (int) $user->id && (int) $backup->school_id === (int) $user->school_id, 404);
        if ($this->missingIdentities($backup) === ['users' => [], 'imports' => []]) {
            $this->invalid('Diese Sicherung enthält keine fehlenden Schülerkonten oder Schülerimporte mit gesicherter Identität.');
        }
        $backup->forceFill(['recovery_requested_at' => now()])->save();
    }

    /** @return array{users:array,imports:array} */
    public function missingIdentities(PersonalTeachingBackup $backup): array
    {
        $payload = $backup->payload;
        $identities = $this->studentIdentities($payload);
        foreach (['users' => 'users', 'imports' => 'import116'] as $key => $table) {
            $existing = DB::table($table)->whereIn('id', array_column($identities[$key], 'id'))->pluck('id')->all();
            $identities[$key] = array_values(array_filter($identities[$key], fn (array $identity): bool => ! in_array((int) $identity['id'], $existing)));
        }

        return $identities;
    }

    public function studentOptions(int $schoolId): array
    {
        return $this->studentQuery($schoolId)->orderBy('last_name')->orderBy('first_name')
            ->get(['id', 'last_name', 'first_name', 'schoolclass'])
            ->map(fn (User $user): array => ['id' => $user->id, 'label' => "{$user->last_name}, {$user->first_name} · {$user->schoolclass} · ID {$user->id}"])->all();
    }

    /** @param array<string, mixed> $studentMappings
     * @param  array<string, mixed>  $importMappings
     */
    public function resolve(User $admin, PersonalTeachingBackup $backup, array $studentMappings, array $importMappings): void
    {
        abort_unless($admin->hasAnyRole(['admin', 'super_admin']) && (int) $backup->school_id === (int) $admin->school_id, 403);
        DB::transaction(function () use ($admin, $backup, $studentMappings, $importMappings): void {
            User::query()->whereKey($backup->user_id)->lockForUpdate()->firstOrFail();
            $backup = PersonalTeachingBackup::query()->lockForUpdate()->findOrFail($backup->id);
            abort_unless($backup->recovery_requested_at && (int) $backup->school_id === (int) $admin->school_id, 409);
            $payload = $backup->payload;
            $studentMappings = array_replace($backup->student_mappings ?? [], $studentMappings);
            $importMappings = array_replace($backup->import_mappings ?? [], $importMappings);
            $this->validateMappings((int) $backup->school_id, $payload, $studentMappings, $importMappings);
            if ($studentMappings === [] && $importMappings === []) {
                $this->invalid('Bitte wählen Sie mindestens eine eindeutige Zuordnung.');
            }
            $backup->forceFill(['student_mappings' => $studentMappings, 'import_mappings' => $importMappings])->save();
        });
    }

    /** @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function applyMappings(User $user, PersonalTeachingBackup $backup, array $payload): array
    {
        abort_unless((int) $backup->user_id === (int) $user->id && (int) $backup->school_id === (int) $user->school_id, 404);
        $users = $backup->student_mappings ?? [];
        $imports = $backup->import_mappings ?? [];
        $this->validateMappings((int) $user->school_id, $payload, $users, $imports);
        $courseMappings = [];
        foreach ($payload['tables']['teaching_course_students'] ?? [] as $student) {
            $source = $student['user_id'] ?: ($student['import116_id'] ?? null);
            $mapping = $student['user_id'] ? $users : $imports;
            if (isset($mapping[$source])) {
                $courseMappings[$student['teaching_course_id']][$source] = (int) $mapping[$source];
            }
        }
        $actorIds = [];
        foreach ($payload['tables'] as $rows) {
            foreach ($rows as $row) {
                foreach (['added_by_user_id', 'confirmed_by_user_id'] as $column) {
                    if (isset($row[$column])) {
                        $actorIds[] = $users[$row[$column]] ?? $row[$column];
                    }
                }
            }
        }
        $validActors = array_flip(DB::table('users')->where('school_id', $user->school_id)->whereIn('id', array_unique($actorIds))->pluck('id')->all());
        foreach ($payload['tables'] as $table => &$rows) {
            foreach ($rows as &$row) {
                foreach (['added_by_user_id', 'confirmed_by_user_id'] as $actorColumn) {
                    if (isset($row[$actorColumn])) {
                        $actorId = $users[$row[$actorColumn]] ?? $row[$actorColumn];
                        $row[$actorColumn] = isset($validActors[$actorId]) ? (int) $actorId : null;
                    }
                }
                if (in_array($table, self::STUDENT_TABLES, true) && isset($users[$row['user_id'] ?? null])) {
                    $row['user_id'] = (int) $users[$row['user_id']];
                }
                if (in_array($table, self::STUDENT_TABLES, true) && isset($imports[$row['import116_id'] ?? null])) {
                    $row['import116_id'] = (int) $imports[$row['import116_id']];
                }
                if ($table === 'teaching_course_student_entry_notifications' && isset($users[$row['confirmed_by_user_id'] ?? null])) {
                    $row['confirmed_by_user_id'] = (int) $users[$row['confirmed_by_user_id']];
                }
                if ($table === 'user_group_members') {
                    if (isset($users[$row['linked_user_id'] ?? null])) {
                        $row['linked_user_id'] = (int) $users[$row['linked_user_id']];
                    }
                    foreach (['user' => $users, 'import116.student' => $imports] as $provider => $mapping) {
                        $prefix = $provider.':';
                        $ref = (string) ($row['member_ref'] ?? '');
                        $id = str_starts_with($ref, $prefix) ? substr($ref, strlen($prefix)) : $ref;
                        if (($row['member_provider'] ?? null) === $provider && isset($mapping[$id])) {
                            $row['member_ref'] = $prefix.$mapping[$id];
                            $meta = $this->decode($row['meta'] ?? null);
                            $meta[$provider === 'user' ? 'user_id' : 'import116_id'] = (int) $mapping[$id];
                            $row['meta'] = json_encode($meta, JSON_THROW_ON_ERROR);
                        }
                    }
                }
                $map = $courseMappings[$row['teaching_course_id'] ?? null] ?? [];
                if ($map !== [] && $table === 'teaching_course_dates') {
                    $attendance = $this->decode($row['attendance'] ?? null);
                    $remapped = [];
                    foreach ($attendance as $id => $state) {
                        $prefix = str_starts_with((string) $id, 's_') ? 's_' : '';
                        $numericId = $prefix === '' ? $id : substr((string) $id, 2);
                        $remapped[$prefix.($map[$numericId] ?? $numericId)] = $state;
                    }
                    if (isset($row['attendance'])) {
                        $row['attendance'] = json_encode($remapped, JSON_THROW_ON_ERROR);
                    }
                    $status = array_map(function (mixed $item) use ($map): mixed {
                        if (is_string($item) && preg_match('/^att:(\d+):(.*)$/D', $item, $matches) && isset($map[$matches[1]])) {
                            return 'att:'.$map[$matches[1]].':'.$matches[2];
                        }

                        return $item;
                    }, $this->decode($row['status'] ?? null));
                    if (isset($row['status'])) {
                        $row['status'] = json_encode($status, JSON_THROW_ON_ERROR);
                    }
                }
                if ($map !== [] && $table === 'teaching_course_works' && isset($row['groups'])) {
                    $groups = $this->decode($row['groups']);
                    foreach ($groups as &$group) {
                        $group['student_ids'] = array_map(fn (mixed $id): mixed => $map[$id] ?? $id, $group['student_ids'] ?? []);
                        if (isset($group['students']) && is_array($group['students'])) {
                            $group['students'] = array_map(fn (mixed $id): mixed => is_scalar($id) ? ($map[$id] ?? $id) : $id, $group['students']);
                        }
                        foreach (['grades', 'comments', 'points'] as $key) {
                            foreach ($group[$key] ?? [] as $index => $item) {
                                if (isset($map[$item['student_id'] ?? null])) {
                                    $group[$key][$index]['student_id'] = $map[$item['student_id']];
                                }
                            }
                        }
                    }
                    unset($group);
                    $row['groups'] = json_encode($groups, JSON_THROW_ON_ERROR);
                }
            }
            unset($row);
        }
        unset($rows);

        return $payload;
    }

    /** @param array<string, mixed> $payload
     * @return array{users:array,imports:array}
     */
    private function studentIdentities(array $payload): array
    {
        $userIds = [];
        $importIds = [];
        foreach (self::STUDENT_TABLES as $table) {
            $userIds = [...$userIds, ...array_column($payload['tables'][$table] ?? [], 'user_id')];
            $importIds = [...$importIds, ...array_column($payload['tables'][$table] ?? [], 'import116_id')];
        }

        return [
            'users' => array_values(array_filter($payload['identities']['users'] ?? [], fn (array $row): bool => in_array($row['id'], $userIds) && (int) $row['school_id'] === (int) $payload['school_id'])),
            'imports' => array_values(array_filter($payload['identities']['imports'] ?? [], fn (array $row): bool => in_array($row['id'], $importIds) && (int) $row['school_id'] === (int) $payload['school_id'])),
        ];
    }

    /** @param array<string, mixed> $payload
     * @param  array<string, mixed>  $users
     * @param  array<string, mixed>  $imports
     */
    private function validateMappings(int $schoolId, array $payload, array $users, array $imports): void
    {
        $identities = $this->studentIdentities($payload);
        foreach (['users' => $users, 'imports' => $imports] as $kind => $mappings) {
            $table = $kind === 'users' ? 'users' : 'import116';
            $sourceRows = collect($identities[$kind])->keyBy('id');
            $destinations = [];
            foreach ($mappings as $oldId => $newId) {
                $source = $sourceRows->get($oldId);
                if (! ctype_digit((string) $oldId) || ! is_numeric($newId) || (int) $newId <= 0 || ! $source
                    || DB::table($table)->where('id', $oldId)->exists()
                    || $sourceRows->has($newId) || in_array((int) $newId, $destinations, true)) {
                    $this->invalid('Nur fehlende gesicherte Schüler dürfen eindeutig einem anderen Schülerkonto zugeordnet werden. Bestehende Konten bleiben unverändert.');
                }
                $destinations[] = (int) $newId;
                $target = $kind === 'users'
                    ? $this->studentQuery($schoolId)->whereKey($newId)->lockForUpdate()->first()
                    : DB::table('import116')->where('school_id', $schoolId)->where('schoolyear_id', $source['schoolyear_id'])->where('id', $newId)->lockForUpdate()->first();
                if (! $target) {
                    $this->invalid('Das Ziel muss ein aktives Schülerkonto derselben Schule oder ein Schülerimport desselben Schuljahres sein.');
                }
            }
        }
    }

    private function studentQuery(int $schoolId): Builder
    {
        return User::query()->where('school_id', $schoolId)->where('is_active', true)
            ->whereHas('roles', fn (Builder $query): Builder => $query->whereIn('name', self::STUDENT_ROLES))
            ->whereDoesntHave('roles', fn (Builder $query): Builder => $query->whereNotIn('name', self::STUDENT_ROLES));
    }

    /** @return array<mixed> */
    private function decode(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        return json_decode($value ?? '[]', true, 512, JSON_THROW_ON_ERROR) ?? [];
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['recovery' => $message]);
    }
}
