<?php

namespace App\Services;

use App\Models\School;
use App\Models\User;
use Illuminate\Cache\LockTimeoutException;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;

class CloudwaysSchoolSynchronizationService
{
    private const string SOURCE_CONNECTION = 'cloudways';

    private const int INSERT_CHUNK_SIZE = 250;

    private const int LOCK_SECONDS = 1800;

    private const array EXCLUDED_TABLES = [
        'cache',
        'cache_locks',
        'failed_jobs',
        'job_batches',
        'jobs',
        'migrations',
        'password_reset_tokens',
        'personal_access_tokens',
        'queue_tests',
        'sessions',
    ];

    private const array SHARED_TABLES = [
        'licence_user_plans',
        'licences',
        'permissions',
        'role_has_permissions',
        'roles',
    ];

    private const array SEMANTIC_RELATIONS = [
        ['parent' => 'users', 'parent_column' => 'id', 'child' => 'agent_conversations', 'child_column' => 'user_id'],
        ['parent' => 'agent_conversations', 'parent_column' => 'id', 'child' => 'agent_conversation_messages', 'child_column' => 'conversation_id'],
        ['parent' => 'teaching_courses', 'parent_column' => 'id', 'child' => 'teaching_course_students', 'child_column' => 'teaching_course_id'],
        ['parent' => 'teaching_courses', 'parent_column' => 'id', 'child' => 'teaching_course_dates', 'child_column' => 'teaching_course_id'],
        ['parent' => 'teaching_courses', 'parent_column' => 'id', 'child' => 'teaching_course_works', 'child_column' => 'teaching_course_id'],
        ['parent' => 'teaching_courses', 'parent_column' => 'id', 'child' => 'teaching_course_student_entries', 'child_column' => 'teaching_course_id'],
        ['parent' => 'teaching_courses', 'parent_column' => 'id', 'child' => 'teaching_course_behaviour_entries', 'child_column' => 'teaching_course_id'],
        ['parent' => 'teaching_courses', 'parent_column' => 'id', 'child' => 'teaching_course_student_category_evaluations', 'child_column' => 'teaching_course_id'],
        ['parent' => 'teaching_courses', 'parent_column' => 'id', 'child' => 'teaching_course_work_group_students', 'child_column' => 'teaching_course_id'],
        ['parent' => 'teaching_course_works', 'parent_column' => 'id', 'child' => 'teaching_course_student_entries', 'child_column' => 'teaching_course_work_id'],
        ['parent' => 'teaching_course_works', 'parent_column' => 'id', 'child' => 'teaching_course_work_group_students', 'child_column' => 'teaching_course_work_id'],
        ['parent' => 'teaching_course_dates', 'parent_column' => 'id', 'child' => 'teaching_course_date_materials', 'child_column' => 'teaching_course_date_id'],
        ['parent' => 'teaching_course_date_materials', 'parent_column' => 'id', 'child' => 'teaching_course_date_material_attachments', 'child_column' => 'teaching_course_date_material_id'],
        ['parent' => 'teaching_curricula', 'parent_column' => 'id', 'child' => 'teaching_curriculum_documents', 'child_column' => 'teaching_curriculum_id'],
    ];

    private const array SHARED_SEMANTIC_RELATIONS = [
        ['parent' => 'licence_user_plans', 'parent_column' => 'id', 'child' => 'school_user_licences', 'child_column' => 'plan_id'],
    ];

    private const array USER_TRANSIENT_COLUMNS = [
        'remember_token',
        'token_2fa',
        'token_2fa_expires_at',
        'token_2fa_2',
        'token_2fa_2_expires_at',
        'uuid',
        'uuid_at',
    ];

    /**
     * @return array{
     *     school: array{id:int,long_name:string,short_name:string},
     *     tables:int,
     *     rows:int,
     *     counts:array<string,int>,
     *     excluded_tables:array<int,string>,
     *     files_included:bool
     * }
     */
    public function preview(School $school): array
    {
        $this->assertAvailable();

        $source = DB::connection(self::SOURCE_CONNECTION);
        $target = DB::connection();

        $this->assertMySqlConnections($source, $target);
        $this->assertSourceAndTargetDiffer($source, $target);
        $this->assertSchemasCompatible($source, $target);
        $remoteSchool = $this->remoteSchool($source, $school);

        $source->beginTransaction();

        try {
            $graph = $this->captureGraph($source, (int) $school->id, sanitizeAuthenticationData: true);
        } finally {
            $source->rollBack();
        }

        $counts = collect($graph['rows'])
            ->map(fn (array $rows): int => count($rows))
            ->filter()
            ->sortKeys()
            ->all();

        return [
            'school' => [
                'id' => (int) $remoteSchool['id'],
                'long_name' => (string) ($remoteSchool['long_name'] ?? ''),
                'short_name' => (string) ($remoteSchool['short_name'] ?? ''),
            ],
            'tables' => count($counts),
            'rows' => array_sum($counts),
            'counts' => $counts,
            'excluded_tables' => self::EXCLUDED_TABLES,
            'files_included' => false,
        ];
    }

    /**
     * @return array{backup_path:string,tables:int,rows:int,counts:array<string,int>,files_included:bool}
     */
    public function synchronize(School $school, User $actor): array
    {
        $this->assertAvailable();

        try {
            return Cache::lock("cloudways-school-sync:{$school->id}", self::LOCK_SECONDS)
                ->block(1, fn (): array => $this->synchronizeWithLock($school, $actor));
        } catch (LockTimeoutException) {
            abort(409, 'Für diese Schule läuft bereits eine Synchronisierung.');
        }
    }

    /**
     * @return array{backup_path:string,tables:int,rows:int,counts:array<string,int>,files_included:bool}
     */
    private function synchronizeWithLock(School $school, User $actor): array
    {
        $source = DB::connection(self::SOURCE_CONNECTION);
        $target = DB::connection();

        $this->assertMySqlConnections($source, $target);
        $this->assertSourceAndTargetDiffer($source, $target);
        $this->assertSchemasCompatible($source, $target);
        $this->remoteSchool($source, $school);

        $source->beginTransaction();

        try {
            $remoteGraph = $this->captureGraph($source, (int) $school->id, sanitizeAuthenticationData: true);
        } finally {
            $source->rollBack();
        }

        $localGraph = $this->captureGraph($target, (int) $school->id);
        $this->assertActorSurvives($school, $actor, $remoteGraph);
        $this->assertSharedDependenciesMatch($source, $target, $remoteGraph);
        $this->assertNoPrimaryKeyCollisions($target, $remoteGraph, $localGraph);

        $backupPath = $this->writeEncryptedBackup($school, $localGraph);

        $target->transaction(function () use ($target, $school, $localGraph, $remoteGraph): void {
            $this->deleteGraph($target, $localGraph);
            $this->insertGraph($target, $school, $remoteGraph);
        }, attempts: 3);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $counts = collect($remoteGraph['rows'])
            ->map(fn (array $rows): int => count($rows))
            ->filter()
            ->sortKeys()
            ->all();

        return [
            'backup_path' => $backupPath,
            'tables' => count($counts),
            'rows' => array_sum($counts),
            'counts' => $counts,
            'files_included' => false,
        ];
    }

    private function assertAvailable(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            abort(403, 'Cloudways-Synchronisierung ist nur in einer lokalen Umgebung erlaubt.');
        }

        $configuration = config('database.connections.'.self::SOURCE_CONNECTION, []);
        foreach (['host', 'database', 'username', 'password'] as $key) {
            if (! is_string($configuration[$key] ?? null) || trim((string) $configuration[$key]) === '') {
                abort(503, 'Die Cloudways-Datenbankverbindung ist nicht vollständig konfiguriert.');
            }
        }
    }

    private function assertMySqlConnections(Connection $source, Connection $target): void
    {
        if ($source->getDriverName() !== 'mysql' || $target->getDriverName() !== 'mysql') {
            abort(422, 'Die Synchronisierung unterstützt ausschließlich MySQL-Verbindungen.');
        }
    }

    private function assertSourceAndTargetDiffer(Connection $source, Connection $target): void
    {
        $sourceConfiguration = $source->getConfig();
        $targetConfiguration = $target->getConfig();
        $sourceIdentity = mb_strtolower(implode('|', [
            (string) ($sourceConfiguration['host'] ?? ''),
            (string) ($sourceConfiguration['port'] ?? ''),
            (string) $source->getDatabaseName(),
        ]));
        $targetIdentity = mb_strtolower(implode('|', [
            (string) ($targetConfiguration['host'] ?? ''),
            (string) ($targetConfiguration['port'] ?? ''),
            (string) $target->getDatabaseName(),
        ]));

        if (hash_equals($sourceIdentity, $targetIdentity)) {
            abort(409, 'Quell- und Zieldatenbank dürfen nicht identisch sein.');
        }
    }

    private function assertSchemasCompatible(Connection $source, Connection $target): void
    {
        $sourceMigrations = $source->table('migrations')->orderBy('migration')->pluck('migration')->all();
        $targetMigrations = $target->table('migrations')->orderBy('migration')->pluck('migration')->all();

        if ($sourceMigrations !== $targetMigrations) {
            abort(409, 'Cloudways und lokal verwenden nicht denselben Migrationsstand.');
        }
    }

    /** @return array<string, mixed> */
    private function remoteSchool(Connection $source, School $school): array
    {
        $remoteSchool = $source->table('schools')->where('id', $school->id)->first();
        if (! $remoteSchool) {
            abort(404, 'Die ausgewählte Schule wurde auf Cloudways nicht gefunden.');
        }

        $remote = (array) $remoteSchool;
        $localName = mb_strtolower(trim((string) $school->long_name));
        $remoteName = mb_strtolower(trim((string) ($remote['long_name'] ?? '')));
        $localShortName = mb_strtolower(trim((string) $school->short_name));
        $remoteShortName = mb_strtolower(trim((string) ($remote['short_name'] ?? '')));

        if ($localName !== $remoteName || $localShortName !== $remoteShortName) {
            abort(409, 'Die lokale Schule stimmt nicht eindeutig mit der Cloudways-Schule überein.');
        }

        return $remote;
    }

    /**
     * @return array{
     *     rows:array<string,array<int,array<string,mixed>>>,
     *     primary_keys:array<string,array<int,string>>,
     *     relations:array<int,array{parent:string,parent_column:string,child:string,child_column:string}>,
     *     order:array<int,string>
     * }
     */
    private function captureGraph(Connection $connection, int $schoolId, bool $sanitizeAuthenticationData = false): array
    {
        $metadata = $this->metadata($connection);
        $rows = [];

        $this->mergeRows($rows, 'schools', $this->rows($connection, 'schools', 'id', [$schoolId]), $metadata['primary_keys']);

        foreach ($metadata['school_tables'] as $table) {
            $this->mergeRows($rows, $table, $this->rows($connection, $table, 'school_id', [$schoolId]), $metadata['primary_keys']);
        }

        $relations = array_merge($metadata['foreign_keys'], $this->availableSemanticRelations($metadata['tables']));
        $this->captureSemanticUserRows($connection, $rows, $metadata);

        do {
            $changed = false;

            foreach ($relations as $relation) {
                $parentRows = $rows[$relation['parent']] ?? [];
                if ($parentRows === [] || in_array($relation['child'], self::EXCLUDED_TABLES, true)) {
                    continue;
                }

                $values = collect($parentRows)
                    ->pluck($relation['parent_column'])
                    ->filter(fn (mixed $value): bool => $value !== null)
                    ->unique()
                    ->values()
                    ->all();
                if ($values === []) {
                    continue;
                }

                $query = $connection->table($relation['child'])->whereIn($relation['child_column'], $values);
                if (in_array($relation['child'], $metadata['school_tables'], true)) {
                    $query->where('school_id', $schoolId);
                }

                $before = count($rows[$relation['child']] ?? []);
                $this->mergeRows(
                    $rows,
                    $relation['child'],
                    $query->get()->map(fn (object $row): array => (array) $row)->all(),
                    $metadata['primary_keys'],
                );
                $changed = $changed || count($rows[$relation['child']] ?? []) > $before;
            }
        } while ($changed);

        if ($sanitizeAuthenticationData) {
            $this->sanitizeTransientAuthenticationData($rows);
        }
        $order = $this->tableOrder(array_keys($rows), $relations);

        return [
            'rows' => $rows,
            'primary_keys' => $metadata['primary_keys'],
            'relations' => $relations,
            'order' => $order,
        ];
    }

    /** @return array{tables:array<int,string>,school_tables:array<int,string>,primary_keys:array<string,array<int,string>>,foreign_keys:array<int,array{parent:string,parent_column:string,child:string,child_column:string}>} */
    private function metadata(Connection $connection): array
    {
        $database = $connection->getDatabaseName();
        $tableRows = $connection->select(
            'SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = ? ORDER BY TABLE_NAME',
            [$database, 'BASE TABLE'],
        );
        $tables = collect($tableRows)
            ->pluck('TABLE_NAME')
            ->reject(fn (string $table): bool => in_array($table, array_merge(self::EXCLUDED_TABLES, self::SHARED_TABLES), true))
            ->values()
            ->all();

        $schoolTables = collect($connection->select(
            "SELECT TABLE_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND COLUMN_NAME = 'school_id' ORDER BY TABLE_NAME",
            [$database],
        ))
            ->pluck('TABLE_NAME')
            ->filter(fn (string $table): bool => in_array($table, $tables, true))
            ->values()
            ->all();

        $primaryKeys = collect($connection->select(
            "SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = ? AND CONSTRAINT_NAME = 'PRIMARY' ORDER BY TABLE_NAME, ORDINAL_POSITION",
            [$database],
        ))
            ->groupBy('TABLE_NAME')
            ->map(fn ($columns): array => $columns->pluck('COLUMN_NAME')->values()->all())
            ->all();

        $foreignKeys = collect($connection->select(
            'SELECT TABLE_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = ? AND REFERENCED_TABLE_NAME IS NOT NULL ORDER BY TABLE_NAME, ORDINAL_POSITION',
            [$database],
        ))
            ->filter(fn (object $relation): bool => in_array($relation->TABLE_NAME, $tables, true)
                && (in_array($relation->REFERENCED_TABLE_NAME, $tables, true)
                    || in_array($relation->REFERENCED_TABLE_NAME, self::SHARED_TABLES, true)))
            ->map(fn (object $relation): array => [
                'parent' => $relation->REFERENCED_TABLE_NAME,
                'parent_column' => $relation->REFERENCED_COLUMN_NAME,
                'child' => $relation->TABLE_NAME,
                'child_column' => $relation->COLUMN_NAME,
            ])
            ->values()
            ->all();

        return [
            'tables' => $tables,
            'school_tables' => $schoolTables,
            'primary_keys' => $primaryKeys,
            'foreign_keys' => $foreignKeys,
        ];
    }

    /** @return array<int,array<string,mixed>> */
    private function rows(Connection $connection, string $table, string $column, array $values): array
    {
        if ($values === []) {
            return [];
        }

        return $connection->table($table)
            ->whereIn($column, $values)
            ->get()
            ->map(fn (object $row): array => (array) $row)
            ->all();
    }

    /** @param array<string,array<int,array<string,mixed>>> $rows */
    private function captureSemanticUserRows(Connection $connection, array &$rows, array $metadata): void
    {
        $userIds = collect($rows['users'] ?? [])->pluck('id')->filter()->unique()->values()->all();
        if ($userIds === []) {
            return;
        }

        foreach (['model_has_roles', 'model_has_permissions'] as $table) {
            if (! in_array($table, $metadata['tables'], true)) {
                continue;
            }

            $semanticRows = $connection->table($table)
                ->where('model_type', User::class)
                ->whereIn('model_id', $userIds)
                ->get()
                ->map(fn (object $row): array => (array) $row)
                ->all();
            $this->mergeRows($rows, $table, $semanticRows, $metadata['primary_keys']);
        }
    }

    /**
     * @param  array<string,array<int,array<string,mixed>>>  $target
     * @param  array<int,array<string,mixed>>  $incoming
     * @param  array<string,array<int,string>>  $primaryKeys
     */
    private function mergeRows(array &$target, string $table, array $incoming, array $primaryKeys): void
    {
        if ($incoming === []) {
            return;
        }

        $keyColumns = $primaryKeys[$table] ?? [];
        if ($keyColumns === []) {
            throw new RuntimeException("Tabelle {$table} hat keinen Primärschlüssel.");
        }

        $indexed = collect($target[$table] ?? [])->keyBy(fn (array $row): string => $this->rowKey($row, $keyColumns));
        foreach ($incoming as $row) {
            $indexed->put($this->rowKey($row, $keyColumns), $row);
        }

        $target[$table] = $indexed->values()
            ->sort(function (array $left, array $right) use ($keyColumns): int {
                foreach ($keyColumns as $column) {
                    $comparison = ($left[$column] ?? null) <=> ($right[$column] ?? null);
                    if ($comparison !== 0) {
                        return $comparison;
                    }
                }

                return 0;
            })
            ->values()
            ->all();
    }

    /** @param array<int,string> $availableTables */
    private function availableSemanticRelations(array $availableTables): array
    {
        return collect(self::SEMANTIC_RELATIONS)
            ->filter(fn (array $relation): bool => in_array($relation['parent'], $availableTables, true)
                && in_array($relation['child'], $availableTables, true))
            ->values()
            ->all();
    }

    /** @param array<string,array<int,array<string,mixed>>> $rows */
    private function sanitizeTransientAuthenticationData(array &$rows): void
    {
        foreach ($rows['users'] ?? [] as &$user) {
            foreach (self::USER_TRANSIENT_COLUMNS as $column) {
                if (array_key_exists($column, $user)) {
                    $user[$column] = null;
                }
            }
        }
        unset($user);
    }

    private function assertActorSurvives(School $school, User $actor, array $remoteGraph): void
    {
        if ((int) $actor->school_id !== (int) $school->id) {
            return;
        }

        $remoteActorExists = collect($remoteGraph['rows']['users'] ?? [])->contains(
            fn (array $row): bool => (int) ($row['id'] ?? 0) === (int) $actor->id,
        );
        if (! $remoteActorExists) {
            abort(409, 'Der angemeldete lokale Super-Admin fehlt in den Cloudways-Daten.');
        }

        $superAdminRoleId = DB::table('roles')->where('name', 'super_admin')->where('guard_name', 'web')->value('id');
        $remoteActorIsSuperAdmin = collect($remoteGraph['rows']['model_has_roles'] ?? [])->contains(
            fn (array $row): bool => (int) ($row['model_id'] ?? 0) === (int) $actor->id
                && (int) ($row['role_id'] ?? 0) === (int) $superAdminRoleId,
        );
        if (! $remoteActorIsSuperAdmin) {
            abort(409, 'Der angemeldete Benutzer wäre nach der Synchronisierung kein Super-Admin mehr.');
        }
    }

    private function assertSharedDependenciesMatch(Connection $source, Connection $target, array $graph): void
    {
        foreach (array_merge($graph['relations'], self::SHARED_SEMANTIC_RELATIONS) as $relation) {
            if (! in_array($relation['parent'], self::SHARED_TABLES, true)) {
                continue;
            }

            $values = collect($graph['rows'][$relation['child']] ?? [])
                ->pluck($relation['child_column'])
                ->filter(fn (mixed $value): bool => $value !== null)
                ->unique()
                ->values()
                ->all();
            foreach ($values as $value) {
                $sourceRow = (array) ($source->table($relation['parent'])->where($relation['parent_column'], $value)->first() ?? []);
                $targetRow = (array) ($target->table($relation['parent'])->where($relation['parent_column'], $value)->first() ?? []);
                if ($sourceRow === []
                    || $targetRow === []
                    || $this->normalizeSharedReferenceRow($sourceRow) !== $this->normalizeSharedReferenceRow($targetRow)) {
                    abort(409, "Globale Referenzdaten in {$relation['parent']} stimmen nicht überein.");
                }
            }
        }
    }

    /** @param array<string, mixed> $row */
    private function normalizeSharedReferenceRow(array $row): array
    {
        return collect($row)
            ->except(['created_at', 'updated_at'])
            ->sortKeys()
            ->all();
    }

    private function assertNoPrimaryKeyCollisions(Connection $target, array $remoteGraph, array $localGraph): void
    {
        foreach ($remoteGraph['rows'] as $table => $remoteRows) {
            if ($table === 'schools' || $remoteRows === []) {
                continue;
            }

            $primaryKeys = $remoteGraph['primary_keys'][$table] ?? [];
            $localKeys = collect($localGraph['rows'][$table] ?? [])
                ->map(fn (array $row): string => $this->rowKey($row, $primaryKeys))
                ->flip();

            foreach (array_chunk($remoteRows, 100) as $chunk) {
                $query = $target->table($table)->where(function ($outer) use ($chunk, $primaryKeys): void {
                    foreach ($chunk as $index => $row) {
                        $method = $index === 0 ? 'where' : 'orWhere';
                        $outer->{$method}(function ($inner) use ($row, $primaryKeys): void {
                            foreach ($primaryKeys as $column) {
                                $inner->where($column, $row[$column]);
                            }
                        });
                    }
                });

                foreach ($query->get()->map(fn (object $row): array => (array) $row) as $existing) {
                    if (! $localKeys->has($this->rowKey($existing, $primaryKeys))) {
                        abort(409, "ID-Konflikt in Tabelle {$table}; andere lokale Schuldaten bleiben unverändert.");
                    }
                }
            }
        }
    }

    private function writeEncryptedBackup(School $school, array $graph): string
    {
        $directory = storage_path("app/private/cloudways-school-sync-backups/{$school->id}");
        File::ensureDirectoryExists($directory);
        $filename = now()->format('Ymd_His').'_before_sync.enc';
        $absolutePath = $directory.DIRECTORY_SEPARATOR.$filename;

        $json = json_encode([
            'created_at' => now()->toIso8601String(),
            'school_id' => (int) $school->id,
            'graph' => $graph,
        ], JSON_THROW_ON_ERROR);
        File::put($absolutePath, Crypt::encryptString($json));

        return "cloudways-school-sync-backups/{$school->id}/{$filename}";
    }

    private function deleteGraph(Connection $target, array $graph): void
    {
        foreach (array_reverse($graph['order']) as $table) {
            if ($table === 'schools') {
                continue;
            }

            $this->deleteRows($target, $table, $graph['rows'][$table] ?? [], $graph['primary_keys'][$table] ?? []);
        }
    }

    private function insertGraph(Connection $target, School $school, array $graph): void
    {
        $schoolRow = collect($graph['rows']['schools'] ?? [])->firstWhere('id', $school->id);
        if (is_array($schoolRow)) {
            $target->table('schools')->where('id', $school->id)->update(collect($schoolRow)->except('id')->all());
        }

        foreach ($graph['order'] as $table) {
            if ($table === 'schools') {
                continue;
            }

            foreach (array_chunk($graph['rows'][$table] ?? [], self::INSERT_CHUNK_SIZE) as $chunk) {
                $target->table($table)->insert($chunk);
            }
        }
    }

    /** @param array<int,array<string,mixed>> $rows */
    private function deleteRows(Connection $connection, string $table, array $rows, array $primaryKeys): void
    {
        foreach (array_chunk($rows, 100) as $chunk) {
            $connection->table($table)
                ->where(function ($outer) use ($chunk, $primaryKeys): void {
                    foreach ($chunk as $index => $row) {
                        $method = $index === 0 ? 'where' : 'orWhere';
                        $outer->{$method}(function ($inner) use ($row, $primaryKeys): void {
                            foreach ($primaryKeys as $column) {
                                $inner->where($column, $row[$column]);
                            }
                        });
                    }
                })
                ->delete();
        }
    }

    /** @param array<int,string> $tables */
    private function tableOrder(array $tables, array $relations): array
    {
        $tables = array_values(array_unique($tables));
        $incoming = array_fill_keys($tables, 0);
        $children = array_fill_keys($tables, []);

        foreach ($relations as $relation) {
            if ($relation['parent'] === $relation['child']
                || ! isset($incoming[$relation['parent']], $incoming[$relation['child']])) {
                continue;
            }

            if (in_array($relation['child'], $children[$relation['parent']], true)) {
                continue;
            }

            $children[$relation['parent']][] = $relation['child'];
            $incoming[$relation['child']]++;
        }

        $queue = collect($incoming)->filter(fn (int $count): bool => $count === 0)->keys()->sort()->values()->all();
        $ordered = [];

        while ($queue !== []) {
            $table = array_shift($queue);
            $ordered[] = $table;

            foreach ($children[$table] as $child) {
                $incoming[$child]--;
                if ($incoming[$child] === 0) {
                    $queue[] = $child;
                    sort($queue);
                }
            }
        }

        if (count($ordered) !== count($tables)) {
            $cyclicTables = array_values(array_diff($tables, $ordered));
            throw new RuntimeException('Zyklische Tabellenabhängigkeit: '.implode(', ', $cyclicTables));
        }

        return $ordered;
    }

    /** @param array<int,string> $keyColumns */
    private function rowKey(array $row, array $keyColumns): string
    {
        return json_encode(
            collect($keyColumns)->mapWithKeys(fn (string $column): array => [$column => $row[$column] ?? null])->all(),
            JSON_THROW_ON_ERROR,
        );
    }
}
