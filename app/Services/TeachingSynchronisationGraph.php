<?php

namespace App\Services;

use App\Models\User;
use Egulias\EmailValidator\EmailValidator;
use Egulias\EmailValidator\Validation\RFCValidation;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use RuntimeException;

class TeachingSynchronisationGraph
{
    // Dependency order; recovery archives/history are separate functions.
    public const array TABLES = [
        'teaching_curricula' => 'school_id',
        'teaching_curriculum_documents' => ['teaching_curricula', 'teaching_curriculum_id'],
        'teaching_imported_curricula' => 'school_id',
        'teaching_schemas' => 'school_id',
        'teaching_entry_areas' => 'school_id',
        'teaching_entry_grading_parts' => 'school_id',
        'teaching_entry_definitions' => 'school_id',
        'teaching_holidays' => 'school_id',
        'teaching_school_hours' => 'school_id',
        'teaching_class_heads' => 'school_id',
        'teaching_class_head_emails' => 'school_id',
        'teaching_courses' => 'school_id',
        'teaching_course_students' => ['teaching_courses', 'teaching_course_id'],
        'teaching_course_dates' => ['teaching_courses', 'teaching_course_id'],
        'teaching_course_date_materials' => ['teaching_course_dates', 'teaching_course_date_id'],
        'teaching_course_date_material_attachments' => ['teaching_course_date_materials', 'teaching_course_date_material_id'],
        'teaching_course_works' => ['teaching_courses', 'teaching_course_id'],
        'teaching_course_work_group_students' => ['teaching_courses', 'teaching_course_id'],
        'teaching_course_student_entries' => ['teaching_courses', 'teaching_course_id'],
        'teaching_course_student_entry_notifications' => ['teaching_course_student_entries', 'teaching_course_student_entry_id'],
        'teaching_course_behaviour_entries' => ['teaching_courses', 'teaching_course_id'],
        'teaching_course_student_category_evaluations' => ['teaching_courses', 'teaching_course_id'],
        'import116_runs' => 'school_id',
        'import116_run_changes' => ['import116_runs', 'import116_run_id'],
        'user_groups' => ['teaching_courses', 'teaching_course_id'],
        'user_group_members' => ['user_groups', 'user_group_id'],
    ];

    public const array REFERENCES = [
        'schoolyear_id' => 'schoolyears', 'source_schoolyear_id' => 'schoolyears',
        'user_id' => 'users', 'created_by_user_id' => 'users', 'added_by_user_id' => 'users',
        'linked_user_id' => 'users', 'confirmed_by_user_id' => 'users', 'undone_by_user_id' => 'users',
        'import_user_id' => 'users', 'import116_id' => 'import116',
        'teaching_curriculum_id' => 'teaching_curricula', 'adopted_curriculum_id' => 'teaching_curricula',
        'teaching_entry_area_id' => 'teaching_entry_areas', 'teaching_entry_grading_part_id' => 'teaching_entry_grading_parts',
        'teaching_course_id' => 'teaching_courses', 'teaching_course_date_id' => 'teaching_course_dates',
        'teaching_course_date_material_id' => 'teaching_course_date_materials',
        'teaching_course_work_id' => 'teaching_course_works',
        'teaching_course_student_entry_id' => 'teaching_course_student_entries',
        'source_teaching_curriculum_document_id' => 'teaching_curriculum_documents',
        'import116_run_id' => 'import116_runs', 'user_group_id' => 'user_groups',
    ];

    public const array USER_SETTINGS = [
        'teaching_active_semester', 'teaching_count_for_semester_2_date', 'teaching_behaviour',
        'teaching_behaviour_by_schoolyear', 'teaching_notifications', 'teaching_notifications_by_schoolyear',
        'teaching_show_behaviour', 'teaching_grade_columns_by_schoolyear', 'teaching_student_grade_columns_by_schoolyear',
    ];

    public static function query(Connection $connection, string $table, array $tables, int $schoolId): Builder
    {
        $scope = self::TABLES[$table];
        $query = $connection->table($table);

        return is_string($scope) ? $query->where('school_id', $schoolId)
            : $query->whereIn($scope[1], array_column($tables[$scope[0]], 'id'));
    }

    public function capture(Connection $connection, int $schoolId, bool $lock = false, bool $target = false): array
    {
        $schema = $connection->getSchemaBuilder();
        $state = ['tables' => [], 'columns' => [], 'foreign_keys' => [], 'max_ids' => []];
        foreach (array_column($schema->getTables(), 'name') as $name) {
            if (str_starts_with($name, 'teaching_') && ! isset(self::TABLES[$name])
                && ! in_array($name, ['teaching_backups', 'teaching_backup_restore_runs'], true)) {
                throw new RuntimeException("Unbekannte Unterrichtstabelle {$name}; Umfang zuerst prüfen.");
            }
        }
        foreach (self::TABLES as $table => $scope) {
            $query = self::query($connection, $table, $state['tables'], $schoolId)->orderBy('id');
            if ($lock) {
                $query->lockForUpdate();
            }
            $state['tables'][$table] = $query->get()->map(fn (object $row): array => (array) $row)->all();
            $state['columns'][$table] = $schema->getColumns($table);
            $state['foreign_keys'][$table] = $schema->getForeignKeys($table);
        }
        foreach (['schoolyears', 'import116', 'users', 'school_tools', 'teachers'] as $table) {
            $query = $connection->table($table)->where('school_id', $schoolId)->orderBy('id');
            if ($lock) {
                $query->lockForUpdate();
            }
            // Never capture passwords, authentication tokens or permissions.
            $columns = $table === 'users' ? ['id', 'school_id', 'email', 'first_name', 'last_name', 'import116_id', ...self::USER_SETTINGS] : ['*'];
            if ($table === 'teachers') {
                $columns = ['id', 'school_id', 'email', 'first_name', 'last_name', 'short', 'is_active'];
            }
            $state[$table] = $query->get($columns)->map(fn (object $row): array => (array) $row)->all();
            $state['columns'][$table] = $schema->getColumns($table);
        }
        foreach ([...array_keys(self::TABLES), 'import116', 'users', 'schoolyears', 'teachers'] as $table) {
            $maximum = $connection->table($table)->orderByDesc('id');
            if ($lock) {
                $maximum->lockForUpdate();
            }
            $state['max_ids'][$table] = (int) $maximum->value('id');
            if ($target && isset(self::TABLES[$table])) {
                $state['occupied_ids'][$table] = $connection->table($table)
                    ->whereNotIn('id', array_column($state['tables'][$table], 'id'))->pluck('id')->all();
            }
        }
        $state['school_tools'] = array_map(fn (array $row): array => array_filter($row,
            fn (string $key): bool => str_starts_with($key, 'teaching_'), ARRAY_FILTER_USE_KEY), $state['school_tools']);
        $state['user_roles'] = $connection->table('model_has_roles')->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_type', User::class)->whereIn('model_id', array_column($state['users'], 'id'))
            ->orderBy('model_id')->orderBy('roles.name')->get(['model_id', 'roles.name'])
            ->groupBy('model_id')->map(fn ($rows): array => $rows->pluck('name')->all())->all();
        $state['json_columns'] = [];
        foreach ($state['columns'] as $table => $columns) {
            $create = (array) $connection->selectOne('SHOW CREATE TABLE `'.$table.'`');
            preg_match_all('/CHECK\s*\(\s*json_valid\(\s*`([^`]+)`\s*\)\s*\)/i', (string) array_values($create)[1], $matches);
            $state['json_columns'][$table] = array_values(array_unique([...$matches[1], ...array_column(array_filter($columns,
                fn (array $column): bool => strtolower($column['type']) === 'json'), 'name')]));
        }

        return $state;
    }

    public function plan(array $source, array $local, int $schoolId): array
    {
        $conflicts = [];
        $maps = [];
        $newImports = [];
        $updatedImports = [];
        $newYears = [];
        $updatedYears = [];
        $newUsers = [];
        $newTeachers = [];
        $updatedTeachers = [];
        $changedContacts = [];
        $yearMaximum = $local['max_ids']['schoolyears'];
        $userMaximum = $local['max_ids']['users'];
        $identities = [];
        $shape = fn (array $columns, array $jsonColumns): array => array_map(function (array $column) use ($jsonColumns): array {
            $column = array_intersect_key($column, array_flip(['name', 'type', 'nullable', 'auto_increment', 'generation']));
            // MySQL and MariaDB render integer display widths differently; they have no storage/range meaning.
            $column['type'] = preg_replace('/\b(tinyint|smallint|mediumint|int|bigint)\(\d+\)/i', '$1', strtolower($column['type'] ?? ''));
            // MariaDB implements JSON as LONGTEXT plus JSON_VALID; retain proof of that constraint.
            if (in_array($column['name'], $jsonColumns, true) && in_array($column['type'], ['longtext', 'json'], true)) {
                $column['type'] = 'json';
            }

            return $column;
        }, $columns);
        foreach (['schoolyears', 'users', 'import116', 'school_tools', 'teachers', ...array_keys(self::TABLES)] as $table) {
            if ($shape($source['columns'][$table] ?? [], $source['json_columns'][$table] ?? [])
                !== $shape($local['columns'][$table] ?? [], $local['json_columns'][$table] ?? [])) {
                $conflicts[] = "Schema unterscheidet sich: {$table}.";
            }
        }
        foreach ($source['schoolyears'] as $row) {
            $identity = $row['from'].'/'.$row['until'];
            if (isset($identities['years'][$identity]) || ! $row['from'] || ! $row['until']) {
                $conflicts[] = 'Cloud-Schuljahre sind nicht eindeutig oder haben keinen vollständigen Zeitraum.';

                continue;
            }
            $identities['years'][$identity] = true;
            $matches = array_values(array_filter($local['schoolyears'], fn (array $year): bool => $year['from'] === $row['from'] && $year['until'] === $row['until']));
            if (count($matches) > 1) {
                $conflicts[] = "Schuljahr {$row['from']} – {$row['until']} ist lokal mehrdeutig.";

                continue;
            }
            if ($matches === []) {
                $new = $row;
                $new['id'] = ++$yearMaximum;
                $newYears[] = $new;
                $maps['schoolyears'][$row['id']] = $new['id'];

                continue;
            }
            $maps['schoolyears'][$row['id']] = $matches[0]['id'];
            if (array_diff_assoc(array_diff_key($row, array_flip(['id', 'created_at', 'updated_at'])), $matches[0]) !== []) {
                $updatedYears[] = ['id' => $matches[0]['id'], 'values' => array_diff_key($row, array_flip(['id', 'created_at', 'updated_at'])),
                    'changes' => array_map(fn (string $field): array => ['field' => $field, 'before' => $matches[0][$field] ?? null,
                        'after' => $row[$field]], array_keys(array_diff_assoc(array_diff_key($row, array_flip(['id', 'created_at', 'updated_at'])), $matches[0])))];
            }
        }
        foreach ($source['users'] as $row) {
            $email = mb_strtolower(trim((string) $row['email']));
            if (isset($identities['users'][$email])) {
                $conflicts[] = 'Mehrere Cloud-Benutzer haben dieselbe E-Mail-Identität.';

                continue;
            }
            $identities['users'][$email] = true;
            $matches = array_values(array_filter($local['users'], fn (array $user): bool => mb_strtolower(trim($user['email'])) === $email));
            if ($matches === []) {
                $linkedAccount = $this->linkedStudentAccount($row, $source, $local, $maps);
                if ($linkedAccount) {
                    $matches = [$linkedAccount];
                }
            }
            if (! $this->validEmail($email) || count($matches) > 1
                || in_array($matches[0]['id'] ?? null, $maps['users'] ?? [], true)) {
                $conflicts[] = "Benutzer #{$row['id']} benötigt eine eindeutige lokale E-Mail-Zuordnung.";

                continue;
            }
            $maps['users'][$row['id']] = $matches[0]['id'] ?? ++$userMaximum;
            if ($matches === []) {
                $newUsers[$maps['users'][$row['id']]] = array_intersect_key($row, array_flip(['email', 'first_name', 'last_name']));
            }
        }
        $teacherMaximum = $local['max_ids']['teachers'] ?? 0;
        foreach ($source['teachers'] ?? [] as $teacher) {
            $email = mb_strtolower(trim((string) $teacher['email']));
            $matches = array_values(array_filter($local['teachers'] ?? [], fn (array $row): bool => mb_strtolower(trim($row['email'])) === $email));
            if (! $this->validEmail($email) || count($matches) > 1) {
                $conflicts[] = "Lehrerlisten-Identität #{$teacher['id']} ist nicht eindeutig.";

                continue;
            }
            $fields = array_intersect_key($teacher, array_flip(['school_id', 'email', 'first_name', 'last_name', 'short']));
            if ($matches === []) {
                $newTeachers[] = [...$fields, 'id' => ++$teacherMaximum, 'is_active' => false];
            } elseif (array_diff_assoc($fields, $matches[0]) !== []) {
                $updatedTeachers[] = [...$fields, 'id' => $matches[0]['id']];
            }
        }
        $maximum = $local['max_ids']['import116'];
        foreach ($source['import116'] as $row) {
            $identity = $row['schoolyear_id'].'/'.$row['student_code'];
            if (isset($identities['imports'][$identity])) {
                $conflicts[] = 'Mehrdeutige Cloud-Import-116-Identität.';

                continue;
            }
            $identities['imports'][$identity] = true;
            $year = $maps['schoolyears'][$row['schoolyear_id']] ?? null;
            $matches = array_values(array_filter($local['import116'], fn (array $student): bool => $student['student_code'] === $row['student_code'] && $student['schoolyear_id'] === $year));
            if (! $year || trim((string) $row['student_code']) === '' || count($matches) > 1) {
                $conflicts[] = "Import-116-Identität #{$row['id']} oder Schuljahr ist nicht eindeutig.";

                continue;
            }
            $mapped = $row;
            $mapped['schoolyear_id'] = $year;
            // These fields belong to timetable planning rather than Unterricht or shared contact data.
            foreach (['study_program', 'study_selection', 'course_results', 'original_school_level', 'original_attendance_year'] as $column) {
                if ($matches !== [] && array_key_exists($column, $matches[0])) {
                    $mapped[$column] = $matches[0][$column];
                } else {
                    unset($mapped[$column]);
                }
            }
            // Shared account links can exist only locally (for example Restaurant or another year).
            // A missing LIVE link is not an instruction to detach that existing association.
            $mapped['user_id'] = $row['user_id'] === null ? ($matches[0]['user_id'] ?? null) : ($maps['users'][$row['user_id']] ?? null);
            if (isset($row['import_user_id'])) {
                $mapped['import_user_id'] = $maps['users'][$row['import_user_id']] ?? null;
                if ($mapped['import_user_id'] === null) {
                    $conflicts[] = "Importierender Benutzer von Schüler #{$row['id']} fehlt.";
                }
            }
            if ($row['user_id'] !== null && $mapped['user_id'] === null) {
                $conflicts[] = "Import-116-Konto #{$row['id']} kann nicht zugeordnet werden.";
            }
            if ($matches === []) {
                $mapped['id'] = ++$maximum;
                $newImports[] = $mapped;
            } else {
                $mapped['id'] = $matches[0]['id'];
                $ignored = array_flip(['created_at', 'updated_at', 'import_date', 'exists_date']);
                $changes = array_diff_assoc(array_diff_key($mapped, $ignored), $matches[0]);
                if ($mapped['user_id'] !== $matches[0]['user_id'] && $matches[0]['user_id'] !== null) {
                    $conflicts[] = "Bestehende Schüler-Kontozuordnung #{$matches[0]['id']} würde auf ein anderes Konto wechseln.";
                }
                if ($changes !== []) {
                    $updatedImports[] = $mapped;
                    $changedContacts[] = ['id' => $mapped['id'], 'student_code' => $mapped['student_code'],
                        'schoolyear' => $year, 'fields' => array_keys($changes),
                        'changes' => array_map(fn (string $field): array => ['field' => $field,
                            'before' => $matches[0][$field] ?? null, 'after' => $mapped[$field]], array_keys($changes))];
                }
            }
            $maps['import116'][$row['id']] = $mapped['id'];
        }
        $userLinks = [];
        foreach ($source['users'] as $user) {
            if (! isset($maps['users'][$user['id']])) {
                continue;
            }
            $matches = array_values(array_filter($local['users'], fn (array $row): bool => $row['id'] === $maps['users'][$user['id']]));
            $link = $user['import116_id'] === null ? ($matches[0]['import116_id'] ?? null) : ($maps['import116'][$user['import116_id']] ?? -1);
            if ($link === -1) {
                $conflicts[] = "Schülerverknüpfung des Cloud-Kontos #{$user['id']} fehlt oder liegt außerhalb der Schule.";
            }
            $userLinks[$user['id']] = $link === -1 ? null : $link;
            if ($matches !== [] && $link !== $matches[0]['import116_id'] && $matches[0]['import116_id'] !== null) {
                $conflicts[] = "Gemeinsame Schülerverknüpfung des Kontos #{$matches[0]['id']} würde entfernt oder gewechselt.";
            }
        }
        foreach (self::TABLES as $table => $scope) {
            $ids = array_column($source['tables'][$table] ?? [], 'id');
            if (count($ids) !== count(array_unique($ids)) || array_filter($ids, fn ($id): bool => (int) $id <= 0) !== []) {
                $conflicts[] = "Ungültige oder doppelte Cloud-IDs in {$table}.";
            }
            $maximum = max($local['max_ids'][$table] ?? 0, max($ids ?: [0]));
            foreach ($source['tables'][$table] ?? [] as $row) {
                $maps[$table][$row['id']] = in_array($row['id'], $local['occupied_ids'][$table] ?? [], true)
                    ? ++$maximum : (int) $row['id'];
                if (isset($row['school_id']) && (int) $row['school_id'] !== $schoolId) {
                    $conflicts[] = "Fremde Schule in {$table}.";
                }
            }
        }
        $maps['historical_users'] = $this->historicalStudentAliases($source, $maps, $schoolId);
        $mappedTables = [];
        foreach (self::TABLES as $table => $scope) {
            $mappedTables[$table] = [];
            foreach ($source['tables'][$table] ?? [] as $row) {
                try {
                    $row['id'] = $maps[$table][$row['id']];
                    $studentMap = [];
                    if ($table === 'teaching_course_dates') {
                        foreach ($source['tables']['teaching_course_students'] as $student) {
                            if ($student['teaching_course_id'] !== $row['teaching_course_id']) {
                                continue;
                            }
                            $parent = $student['user_id'] ? 'users' : 'import116';
                            $oldId = $student['user_id'] ?: $student['import116_id'];
                            $studentMap[$oldId] = $this->mappedId($oldId, $parent, $maps);
                        }
                    }
                    if ($table === 'teaching_course_works') {
                        // Work groups are normalized to real user IDs by TeachingCourseWorkService.
                        $studentMap = $maps['users'] ?? [];
                        foreach ($source['import116'] as $student) {
                            $studentMap[$student['id']] ??= $student['user_id']
                                ? ($maps['users'][$student['user_id']] ?? null) : ($maps['import116'][$student['id']] ?? null);
                        }
                    }
                    foreach (self::REFERENCES as $column => $parent) {
                        if (isset($row[$column])) {
                            $row[$column] = $this->mappedId($row[$column], $parent, $maps);
                        }
                    }
                    if (isset($row['teaching_schema_id']) && ! in_array($row['teaching_schema_id'], array_column($source['tables']['teaching_schemas'], 'schema_id'), true)) {
                        throw new RuntimeException('Leistungsschema fehlt im Unterrichtsgraphen.');
                    }
                    foreach ($row as $column => $value) {
                        if (is_string($value) && in_array($column, ['classes', 'reminder', 'teaching_student_grade_columns',
                            'attendance', 'status', 'hours', 'stars', 'groups', 'topics', 'materials', 'works', 'grading',
                            'before_snapshot', 'after_snapshot', 'summary', 'meta', 'counts', 'report_summary', 'report_paths',
                            'fixed_properties', 'notification_recipients', 'property_evaluations', 'enabled_special_properties',
                            'maximum_plus_grade_thresholds', 'free_deficit_grade_thresholds', 'free_points_grade_thresholds',
                            'points_grade_thresholds', 'overall_points_grade_thresholds'], true)
                            && in_array(substr(ltrim($value), 0, 1), ['[', '{'], true)) {
                            $historicalStudentSnapshot = $table === 'import116_run_changes' && in_array($column, ['before_snapshot', 'after_snapshot'], true);
                            $row[$column] = json_encode($this->remapJson(json_decode($value, true, 512, JSON_THROW_ON_ERROR), $maps, $column, $studentMap, $historicalStudentSnapshot), JSON_THROW_ON_ERROR);
                        }
                    }
                    if ($table === 'user_group_members' && is_numeric($row['member_ref'] ?? null)) {
                        $parent = match ($row['member_provider']) {
                            'user' => 'users', 'import116.student', 'import116.parent_contact' => 'import116', default => null,
                        };
                        if ($parent) {
                            $row['member_ref'] = (string) $this->mappedId($row['member_ref'], $parent, $maps);
                        }
                    }
                    $mappedTables[$table][] = $row;
                } catch (\JsonException|RuntimeException $exception) {
                    $conflicts[] = "{$table} #{$row['id']}: {$exception->getMessage()}";
                }
            }
        }
        $users = [];
        foreach ($source['users'] as $user) {
            if (! isset($maps['users'][$user['id']])) {
                continue;
            }
            $settings = array_intersect_key($user, array_flip(self::USER_SETTINGS));
            $settings['import116_id'] = $userLinks[$user['id']];
            foreach ($settings as $key => $value) {
                if (! str_ends_with($key, '_by_schoolyear') || $value === null) {
                    continue;
                }
                try {
                    $years = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
                    $mappedYears = [];
                    foreach ($years as $year => $setting) {
                        $mappedYears[$this->mappedId($year, 'schoolyears', $maps)] = $setting;
                    }
                    $settings[$key] = json_encode((object) $mappedYears, JSON_THROW_ON_ERROR);
                } catch (\Throwable) {
                    $conflicts[] = "Schuljahrzuordnung in Benutzereinstellung {$key} ist unvollständig.";
                }
            }
            $users[$maps['users'][$user['id']]] = $settings;
        }

        return ['tables' => $mappedTables, 'users' => $users, 'new_imports' => $newImports,
            'updated_imports' => $updatedImports, 'new_years' => $newYears, 'new_users' => $newUsers,
            'updated_years' => $updatedYears,
            'new_teachers' => $newTeachers, 'updated_teachers' => $updatedTeachers,
            'changed_contacts' => $changedContacts, 'maps' => $maps,
            'conflicts' => array_values(array_unique($conflicts))];
    }

    private function mappedId(mixed $id, string $table, array $maps): int
    {
        return $maps[$table][$id] ?? throw new RuntimeException("Beziehung zu {$table} #{$id} fehlt oder liegt außerhalb der Schule.");
    }

    private function validEmail(string $email): bool
    {
        // Use the same RFC validator as Laravel's email rule, including Unicode addresses.
        return (new EmailValidator)->isValid($email, new RFCValidation);
    }

    private function linkedStudentAccount(array $user, array $source, array $local, array $maps): ?array
    {
        $imports = array_values(array_filter($source['import116'], fn (array $student): bool => $student['user_id'] === $user['id']));
        if ($imports === [] || ($user['import116_id'] !== null && ! in_array($user['import116_id'], array_column($imports, 'id'), true))) {
            return null;
        }
        $candidates = [];
        foreach ($imports as $import) {
            $students = array_values(array_filter($local['import116'], fn (array $student): bool => $student['student_code'] === $import['student_code']
                && $student['schoolyear_id'] === ($maps['schoolyears'][$import['schoolyear_id']] ?? null)));
            if (count($students) > 1) {
                return null;
            }
            if ($students === [] || ! $students[0]['user_id']) {
                continue;
            }
            $student = $students[0];
            $accounts = array_values(array_filter($local['users'], fn (array $row): bool => $row['id'] === $student['user_id']));
            if (count($accounts) !== 1 || ! $this->sameStudent($import, $student, $user, $accounts[0])
                || array_diff($local['user_roles'][$accounts[0]['id']] ?? [], ['student', 'lunch_user', 'lunch_candidate', 'studentstimetables_user']) !== []) {
                return null;
            }
            $candidate = $accounts[0];
            if ($candidate['import116_id'] !== null) {
                $reverse = array_values(array_filter($local['import116'], fn (array $row): bool => $row['id'] === $candidate['import116_id']
                    && $row['user_id'] === $candidate['id'] && $row['student_code'] === $import['student_code']));
                if (count($reverse) !== 1 || ! $this->sameStudent($import, $reverse[0], $user, $candidate)) {
                    return null;
                }
            }
            $candidates[$candidate['id']] = $candidate;
        }

        // Every existing year must point to the same account; never merge competing accounts.
        return count($candidates) === 1 ? array_values($candidates)[0] : null;
    }

    private function sameStudent(array $sourceStudent, array $localStudent, array $sourceUser, array $localUser): bool
    {
        if (($localStudent['birth_date'] ?? null) !== ($sourceStudent['birth_date'] ?? null)) {
            return false;
        }
        foreach (['first_name', 'last_name'] as $field) {
            $name = mb_strtolower(trim((string) ($sourceStudent[$field] ?? '')));
            foreach ([$localStudent, $sourceUser, $localUser] as $row) {
                if ($name === '' || mb_strtolower(trim((string) ($row[$field] ?? ''))) !== $name) {
                    return false;
                }
            }
        }

        return true;
    }

    private function historicalStudentAliases(array $source, array $maps, int $schoolId): array
    {
        $aliases = [];
        $ambiguous = [];
        foreach ($source['tables']['import116_run_changes'] ?? [] as $change) {
            foreach (['before_snapshot', 'after_snapshot'] as $column) {
                try {
                    $snapshot = json_decode($change[$column] ?? 'null', true, 512, JSON_THROW_ON_ERROR);
                } catch (\JsonException) {
                    continue;
                }
                $oldUserId = $snapshot['user_id'] ?? null;
                if (! $oldUserId || isset($maps['users'][$oldUserId]) || ! in_array($oldUserId, $source['missing_user_ids'] ?? [], true)) {
                    continue;
                }
                // Only an account proven absent globally may be an obsolete historical student alias.
                $students = array_values(array_filter($source['import116'], fn (array $row): bool => ($snapshot['school_id'] ?? null) === $schoolId
                    && $row['student_code'] === ($snapshot['student_code'] ?? null) && $row['schoolyear_id'] === ($snapshot['schoolyear_id'] ?? null)));
                $users = count($students) === 1 ? array_values(array_filter($source['users'], fn (array $row): bool => $row['id'] === $students[0]['user_id'])) : [];
                $email = mb_strtolower(trim((string) ($snapshot['email'] ?? '')));
                $target = count($users) === 1 ? ($maps['users'][$users[0]['id']] ?? null) : null;
                if (! $target || ! $this->validEmail($email) || mb_strtolower(trim($users[0]['email'])) !== $email
                    || ! $this->sameStudent($snapshot, $students[0], $users[0], $users[0])
                    || (isset($aliases[$oldUserId]) && $aliases[$oldUserId] !== $target)) {
                    $ambiguous[$oldUserId] = true;

                    continue;
                }
                $aliases[$oldUserId] = $target;
            }
        }

        return array_diff_key($aliases, $ambiguous);
    }

    private function studentId(mixed $id, array $maps, array $studentMap = []): int
    {
        if (isset($studentMap[$id])) {
            return $studentMap[$id];
        }
        $user = $maps['users'][$id] ?? null;
        $import = $maps['import116'][$id] ?? null;
        if ($user !== null && $import !== null && $user !== $import) {
            throw new RuntimeException('Mehrdeutige eingebettete Schüleridentität; Zuordnung zuerst bereinigen.');
        }

        return $user ?? $import ?? throw new RuntimeException('Eingebettete Schüleridentität fehlt (ID #'.(int) $id.').');
    }

    private function remapJson(mixed $value, array $maps, string $context, array $studentMap = [], bool $historicalStudentSnapshot = false): mixed
    {
        if (is_string($value) && preg_match('/\Aatt:(\d+):(.*)\z/', $value, $match)) {
            return 'att:'.$this->studentId($match[1], $maps, $studentMap).':'.$match[2];
        }
        if (! is_array($value)) {
            return $value;
        }
        $result = [];
        foreach ($value as $key => $item) {
            $targetKey = $key;
            if ($context === 'attendance' && preg_match('/\A(s_)?(\d+)\z/', (string) $key, $match)) {
                $targetKey = ($match[1] ?? '').$this->studentId($match[2], $maps, $studentMap);
            }
            if ($context === 'student_ids' || ($key === 'student_id' && $item !== null)) {
                $item = $this->studentId($item, $maps, $studentMap);
            } elseif (isset(self::REFERENCES[$key]) && $item !== null) {
                $item = $historicalStudentSnapshot && $key === 'user_id' && ! isset($maps['users'][$item]) && isset($maps['historical_users'][$item])
                    ? $maps['historical_users'][$item] : $this->mappedId($item, self::REFERENCES[$key], $maps);
            } else {
                $item = $this->remapJson($item, $maps, is_string($key) ? $key : $context, $studentMap, $historicalStudentSnapshot);
            }
            if (array_key_exists($targetKey, $result)) {
                throw new RuntimeException('Eingebettete Identitäten würden zusammenfallen.');
            }
            $result[$targetKey] = $item;
        }

        return $result;
    }
}
