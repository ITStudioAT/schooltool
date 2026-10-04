<?php

namespace App\Services;

use App\Models\School;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class TeachingSynchronisationService
{
    public function __construct(
        private TeachingLiveSource $source,
        private TeachingSynchronisationGraph $graph,
        private TeachingSynchronisationFiles $files,
    ) {}

    public static function available(): bool
    {
        return app(RestaurantSynchronisationService::class)::available()
            && in_array(config('filesystems.default'), ['local', 'public'], true)
            && empty(DB::connection()->getConfig('url')) && empty(DB::connection()->getConfig('unix_socket'))
            && empty(DB::connection()->getConfig('prefix'));
    }

    private function authorize(User $actor): void
    {
        abort_unless($actor->hasRole('super_admin'), 403, 'Nur Superadmins dürfen Unterrichtsdaten synchronisieren.');
        abort_unless(static::available(), 403, 'Die Synchronisation ist ausschließlich lokal unter Windows verfügbar.');
    }

    public function preview(User $actor): array
    {
        $this->authorize($actor);
        $school = School::query()->findOrFail($actor->school_id);
        $source = $this->source->snapshot($school);
        if (($source['school'] ?? null) !== ['id' => $school->id, 'short_name' => $school->short_name, 'long_name' => $school->long_name]
            || array_keys($source['tables'] ?? []) !== array_keys(TeachingSynchronisationGraph::TABLES)) {
            throw new RuntimeException('Schulidentität oder Unterrichtsumfang stimmt nicht überein.');
        }
        $local = DB::transaction(fn (): array => $this->localState((int) $actor->school_id));
        $plan = $this->checkedPlan($source, $local, (int) $actor->school_id);
        $summary = [];
        foreach (TeachingSynchronisationGraph::TABLES as $table => $scope) {
            $summary[] = ['table' => $table, 'live' => count($source['tables'][$table]),
                'local' => count($local['tables'][$table]), 'replaced' => count($local['tables'][$table]),
                'bytes' => strlen(json_encode($source['tables'][$table], JSON_THROW_ON_ERROR))];
        }
        $token = null;
        if ($plan['conflicts'] === []) {
            $token = Str::random(64);
            Cache::store('file')->put('teaching-sync:'.$token, Crypt::encryptString(json_encode([
                'actor' => $actor->id, 'school' => $actor->school_id, 'local_hash' => $this->fingerprint($local),
                'source' => $source,
            ], JSON_THROW_ON_ERROR)), now()->addMinutes(15));
        }

        return ['token' => $token, 'school' => $school->long_name, 'captured_at' => $source['captured_at'],
            'expires_in_minutes' => 15, 'summary' => $summary, 'schoolyears' => array_map(fn (array $year): array => array_intersect_key($year, array_flip(['name', 'from', 'until'])), $source['schoolyears']),
            'files' => count(array_filter($source['files'], fn (array $file): bool => ! isset($file['warning']))),
            'file_warnings' => array_values(array_column($source['files'], 'warning')),
            'file_bytes' => array_sum(array_column($source['files'], 'size')),
            'local_files' => count(array_filter($local['files'], fn (array $file): bool => ! isset($file['warning']))),
            'new_schoolyears' => count($plan['new_years']),
            'new_accounts' => count($plan['new_users']), 'new_students' => count($plan['new_imports']),
            'new_teachers' => count($plan['new_teachers']), 'updated_teachers' => count($plan['updated_teachers']),
            'changed_teachers' => array_map(function (array $teacher) use ($local): array {
                $existing = array_values(array_filter($local['teachers'], fn (array $row): bool => $row['id'] === $teacher['id']))[0];
                $changes = array_diff_assoc(array_diff_key($teacher, ['id' => true]), $existing);

                return ['id' => $teacher['id'], 'email' => $teacher['email'],
                    'changes' => array_map(fn (string $field): array => ['field' => $field,
                        'before' => $existing[$field] ?? null, 'after' => $teacher[$field]], array_keys($changes))];
            }, $plan['updated_teachers']),
            'updated_schoolyears' => $plan['updated_years'],
            'changed_contacts' => $plan['changed_contacts'], 'conflicts' => $plan['conflicts'],
            'shared' => ['users' => count($source['users']), 'import116' => count($source['import116']),
                'settings' => count($source['school_tools'][0] ?? []),
                'teachers' => count($source['teachers']),
                'bytes' => strlen(json_encode([$source['users'], $source['import116'], $source['schoolyears'], $source['school_tools'], $source['teachers']], JSON_THROW_ON_ERROR))]];
    }

    public function apply(User $actor, string $token): array
    {
        $this->authorize($actor);

        return Cache::store('file')->lock('teaching-sync-apply', 900)->block(3, function () use ($actor, $token): array {
            $encrypted = Cache::store('file')->get('teaching-sync:'.$token);
            if (! is_string($encrypted)) {
                throw new RuntimeException('Vorschau abgelaufen oder bereits übernommen; bitte erneut prüfen.');
            }
            $preview = json_decode(Crypt::decryptString($encrypted), true, 512, JSON_THROW_ON_ERROR);
            if ($preview['actor'] !== $actor->id || $preview['school'] !== $actor->school_id) {
                throw new RuntimeException('Vorschau gehört nicht zu diesem Benutzer und Schulkontext.');
            }
            $currentSource = $this->source->snapshot(School::query()->findOrFail($actor->school_id));
            $content = fn (array $snapshot): array => array_diff_key($snapshot, array_flip(['captured_at', 'max_ids']));
            if (! hash_equals($this->fingerprint($content($preview['source'])), $this->fingerprint($content($currentSource)))) {
                Cache::store('file')->forget('teaching-sync:'.$token);
                throw new RuntimeException('Der Cloud-Unterrichtsstand oder seine Dateien wurden seit der Vorschau geändert. Bitte den aktuellen Umfang erneut prüfen.');
            }
            $created = [];
            $backup = null;
            try {
                DB::transaction(function () use ($actor, $preview, &$created, &$backup): void {
                    $local = $this->localState((int) $actor->school_id, true);
                    if (! hash_equals($preview['local_hash'], $this->fingerprint($local))) {
                        throw new RuntimeException('Lokale Daten oder Dateien wurden seit der Vorschau geändert. Bitte erneut prüfen.');
                    }
                    $plan = $this->checkedPlan($preview['source'], $local, (int) $actor->school_id);
                    if ($plan['conflicts'] !== []) {
                        throw ValidationException::withMessages(['synchronisation' => $plan['conflicts']]);
                    }
                    // The complete encrypted, private recovery copy must be persisted and verified before any replacement.
                    $backup = Str::random(64);
                    $backupPath = $this->backupPath($actor, $backup);
                    $this->assertTargetParents(Storage::disk('local')->path($backupPath));
                    $backupBytes = Crypt::encryptString(json_encode(['format' => 'schooltool-teaching-sync-safety-v1',
                        'school' => $actor->school_id, 'actor' => $actor->id, 'created_at' => now()->toISOString(), 'state' => $local,
                    ], JSON_THROW_ON_ERROR));
                    if (! Storage::disk('local')->put($backupPath, $backupBytes)
                        || ! hash_equals(hash('sha256', $backupBytes), hash('sha256', Storage::disk('local')->get($backupPath)))) {
                        throw new RuntimeException('Sicherheitsbackup konnte nicht vollständig gespeichert werden.');
                    }
                    $this->files->assertCanonicalFile(Storage::disk('local')->path($backupPath), config('filesystems.disks.local.root'));
                    foreach ($plan['files'] as $file) {
                        $this->writeFile($file, $created);
                    }
                    foreach ($plan['new_years'] as $row) {
                        DB::table('schoolyears')->insert($row);
                    }
                    foreach ($plan['updated_years'] as $year) {
                        DB::table('schoolyears')->where('id', $year['id'])->where('school_id', $actor->school_id)->update($year['values']);
                    }
                    foreach ($plan['new_users'] as $id => $row) {
                        // No LIVE passwords, 2FA secrets, active status or privileged roles are copied.
                        DB::table('users')->insert([...$row, 'id' => $id, 'school_id' => $actor->school_id,
                            'is_active' => false, 'password' => Hash::make(Str::random(64)),
                            'created_at' => now(), 'updated_at' => now()]);
                    }
                    foreach ($plan['new_teachers'] as $row) {
                        DB::table('teachers')->insert([...$row, 'created_at' => now(), 'updated_at' => now()]);
                    }
                    foreach ($plan['updated_teachers'] as $row) {
                        DB::table('teachers')->where('id', $row['id'])->where('school_id', $actor->school_id)
                            ->update(array_diff_key($row, ['id' => true]));
                    }
                    foreach ($plan['new_imports'] as $row) {
                        DB::table('import116')->insert($row);
                    }
                    foreach ($plan['updated_imports'] as $row) {
                        DB::table('import116')->where('id', $row['id'])->where('school_id', $actor->school_id)
                            ->update(array_diff_key($row, array_flip(['id', 'created_at'])));
                    }
                    foreach ($plan['users'] as $id => $settings) {
                        DB::table('users')->where('id', $id)->where('school_id', $actor->school_id)->update($settings);
                    }
                    foreach (array_reverse(array_keys(TeachingSynchronisationGraph::TABLES)) as $table) {
                        TeachingSynchronisationGraph::query(DB::connection(), $table, $local['tables'], (int) $actor->school_id)->delete();
                    }
                    foreach ($plan['tables'] as $table => $rows) {
                        foreach (array_chunk($rows, 100) as $chunk) {
                            DB::table($table)->insert($chunk);
                        }
                    }
                    DB::table('school_tools')->where('school_id', $actor->school_id)->update($preview['source']['school_tools'][0]);
                });
            } catch (\Throwable $exception) {
                // Old files were never overwritten or deleted. Remove only files exclusively created by this attempt.
                foreach ($created as $path) {
                    $absolute = Storage::disk('local')->path($path);
                    $this->files->assertCanonicalFile($absolute, config('filesystems.disks.local.root'));
                    unlink($absolute);
                }
                throw $exception;
            }
            Cache::store('file')->forget('teaching-sync:'.$token);

            return ['backup' => $backup, 'message' => 'Der geprüfte Cloud-Unterrichtsstand wurde lokal übernommen. Sicherheitsbackup gespeichert. Bitte Unterricht neu laden.'];
        });
    }

    public function backupPath(User $actor, string $backup): string
    {
        $this->authorize($actor);
        if (! preg_match('/\A[a-zA-Z0-9]{64}\z/', $backup)) {
            abort(404);
        }

        return "teaching/synchronisation-backups/{$actor->school_id}/{$backup}.enc";
    }

    private function localState(int $schoolId, bool $lock = false): array
    {
        $state = $this->graph->capture(DB::connection(), $schoolId, $lock, true);
        $state['files'] = $this->files->capture($state['tables'], config('filesystems'), false, true);
        $state['conflicts'] = [];
        foreach ($state['tables'] as $table => $rows) {
            foreach ($rows as $row) {
                if (isset($row['school_id']) && (int) $row['school_id'] !== $schoolId) {
                    $state['conflicts'][] = "Lokale Daten aus einer anderen Schule sind in {$table} mit dem Unterricht verknüpft.";
                }
                foreach (TeachingSynchronisationGraph::REFERENCES as $column => $parent) {
                    if (! isset($row[$column])) {
                        continue;
                    }
                    $parents = $state['tables'][$parent] ?? $state[$parent] ?? [];
                    if (! in_array($row[$column], array_column($parents, 'id'), true)) {
                        $state['conflicts'][] = "Lokale Beziehung {$table}.{$column} fehlt oder liegt außerhalb der Schule.";
                    }
                }
            }
        }
        foreach ($state['files'] as $file) {
            if (isset($file['error'])) {
                $state['conflicts'][] = 'Lokale Datei '.$file['path'].': '.$file['error'];
            }
        }
        $tables = [...array_keys(TeachingSynchronisationGraph::TABLES), 'users', 'schoolyears', 'import116', 'school_tools', 'teachers'];
        $engines = DB::table('information_schema.TABLES')->where('TABLE_SCHEMA', DB::connection()->getDatabaseName())->whereIn('TABLE_NAME', $tables)->pluck('ENGINE');
        if ($engines->count() !== count($tables) || $engines->contains(fn ($engine): bool => strcasecmp((string) $engine, 'InnoDB') !== 0)) {
            $state['conflicts'][] = 'Alle betroffenen Tabellen müssen transaktionale InnoDB-Tabellen sein.';
        }
        if (DB::table('information_schema.TRIGGERS')->where('TRIGGER_SCHEMA', DB::connection()->getDatabaseName())->whereIn('EVENT_OBJECT_TABLE', $tables)->exists()) {
            $state['conflicts'][] = 'Datenbank-Trigger könnten fremde Module verändern; Synchronisation gesperrt.';
        }
        // Catch foreign-school, orphan and non-scoped references, including cascades in other modules.
        $references = DB::table('information_schema.KEY_COLUMN_USAGE')
            ->where('TABLE_SCHEMA', DB::connection()->getDatabaseName())
            ->whereIn('REFERENCED_TABLE_NAME', array_keys(TeachingSynchronisationGraph::TABLES))
            ->get(['TABLE_NAME', 'COLUMN_NAME', 'REFERENCED_TABLE_NAME', 'REFERENCED_COLUMN_NAME']);
        foreach ($references as $reference) {
            if ($reference->REFERENCED_COLUMN_NAME !== 'id') {
                $state['conflicts'][] = 'Unbekannte Beziehung zu einer Unterrichtstabelle.';

                continue;
            }
            $query = DB::table($reference->TABLE_NAME)->whereIn($reference->COLUMN_NAME,
                array_column($state['tables'][$reference->REFERENCED_TABLE_NAME], 'id'));
            if (isset($state['tables'][$reference->TABLE_NAME])) {
                $query->whereNotIn('id', array_column($state['tables'][$reference->TABLE_NAME], 'id'));
            }
            if ($query->exists()) {
                $state['conflicts'][] = "Fremde Daten in {$reference->TABLE_NAME} referenzieren zu ersetzende Unterrichtsdaten.";
            }
        }

        return $state;
    }

    private function checkedPlan(array $source, array $local, int $schoolId): array
    {
        $plan = $this->graph->plan($source, $local, $schoolId);
        $plan['conflicts'] = [...$plan['conflicts'], ...($local['conflicts'] ?? [])];
        foreach ($source['files'] as $file) {
            if (isset($file['error'])) {
                $plan['conflicts'][] = 'Cloud-Datei '.$file['path'].': '.$file['error'];
            }
        }
        if (count($source['school_tools']) !== 1 || count($local['school_tools']) !== 1) {
            $plan['conflicts'][] = 'Schuleinstellungen fehlen oder sind mehrdeutig.';
        }
        foreach (TeachingSynchronisationGraph::TABLES as $table => $scope) {
            $owned = array_column($local['tables'][$table], 'id');
            $mappedIds = array_column($plan['tables'][$table], 'id');
            if (DB::table($table)->whereIn('id', $mappedIds)->whereNotIn('id', $owned)->exists()) {
                $plan['conflicts'][] = "ID-Kollision mit fremden Daten in {$table}.";
            }
            $normalize = function (array $keys): array {
                $keys = array_map(function (array $key): array {
                    $key = array_diff_key($key, array_flip(['foreign_schema', 'name']));
                    foreach (['on_update', 'on_delete'] as $action) {
                        $key[$action] = strtolower($key[$action]);
                        if ($key[$action] === 'restrict') {
                            $key[$action] = 'no action';
                        }
                    }

                    return $key;
                }, $keys);
                usort($keys, fn (array $left, array $right): int => strcmp(json_encode($left), json_encode($right)));

                return $keys;
            };
            if ($normalize($source['foreign_keys'][$table] ?? []) !== $normalize($local['foreign_keys'][$table] ?? [])) {
                $plan['conflicts'][] = "Beziehungsschema unterscheidet sich: {$table}.";
            }
            foreach ($source['foreign_keys'][$table] ?? [] as $key) {
                $parent = $key['foreign_table'];
                foreach ($source['tables'][$table] as $row) {
                    if (count($key['columns']) !== 1 || $key['foreign_columns'] !== ['id']) {
                        $plan['conflicts'][] = "Unbekannte zusammengesetzte Beziehung in {$table}.";

                        continue;
                    }
                    $column = $key['columns'][0];
                    if (! isset($row[$column]) || $parent === 'schools') {
                        continue;
                    }
                    $rows = $source['tables'][$parent] ?? $source[$parent] ?? $source['external'][$parent] ?? [];
                    if (! in_array($row[$column], array_column($rows, 'id'), true)) {
                        $plan['conflicts'][] = "Unvollständige Beziehung {$table}.{$column}.";
                    }
                }
            }
            foreach ($source['tables'][$table] as $row) {
                foreach ($local['columns'][$table] as $column) {
                    if (! $column['nullable'] && ! $column['auto_increment'] && $column['generation'] === null
                        && ($row[$column['name']] ?? null) === null) {
                        $plan['conflicts'][] = "Fehlende Pflichtangabe in {$table}.{$column['name']}.";
                    }
                }
            }
        }
        foreach ($source['external'] ?? [] as $table => $rows) {
            if (! in_array($table, ['material_cards', 'material_card_attachments'], true)) {
                $plan['conflicts'][] = 'Unbekannte Beziehung zu einem fremden Modul.';

                continue;
            }
            foreach ($rows as $row) {
                $existing = (array) DB::table($table)->where('id', $row['id'])->first();
                if (isset($row['user_id'], $plan['maps']['users'][$row['user_id']])) {
                    $row['user_id'] = $plan['maps']['users'][$row['user_id']];
                }
                if (array_diff_key($existing, array_flip(['created_at', 'updated_at']))
                    !== array_diff_key($row, array_flip(['created_at', 'updated_at']))) {
                    $plan['conflicts'][] = "Materialbeziehung {$table} #{$row['id']} unterscheidet sich. Materialdaten werden nicht überschrieben.";
                }
            }
        }
        try {
            $rewritten = $this->files->rewrite($plan['tables'], $source['files'], $schoolId);
            $plan['tables'] = $rewritten['tables'];
            $plan['files'] = $rewritten['files'];
        } catch (RuntimeException $exception) {
            $plan['conflicts'][] = $exception->getMessage();
            $plan['files'] = [];
        }
        $plan['conflicts'] = array_values(array_unique($plan['conflicts']));

        return $plan;
    }

    private function fingerprint(array $state): string
    {
        return hash('sha256', json_encode($state, JSON_THROW_ON_ERROR));
    }

    private function writeFile(array $file, array &$created): void
    {
        $disk = Storage::disk('local');
        $path = $file['path'];
        TeachingSynchronisationFiles::assertPath($path);
        $absolute = $disk->path($path);
        $root = config('filesystems.disks.local.root');
        $directory = dirname($absolute);
        $this->assertTargetParents($absolute);
        if (is_file($absolute)) {
            $this->files->assertCanonicalFile($absolute, $root);
            if (! hash_equals($file['sha256'], hash_file('sha256', $absolute))) {
                throw new RuntimeException('Lokales Dateiziel enthält abweichende Daten.');
            }

            return;
        }
        if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new RuntimeException('Lokales Dateiverzeichnis konnte nicht erstellt werden.');
        }
        $stream = fopen($absolute, 'x+b');
        if ($stream === false) {
            throw new RuntimeException('Lokale Datei konnte nicht exklusiv erstellt werden.');
        }
        $created[] = $path;
        try {
            $bytes = base64_decode($file['content'], true);
            if ($bytes === false || fwrite($stream, $bytes) !== strlen($bytes) || ! fflush($stream) || ! fsync($stream)) {
                throw new RuntimeException('Datei konnte nicht vollständig gespeichert werden.');
            }
        } finally {
            fclose($stream);
        }
        $this->files->assertCanonicalFile($absolute, $root);
        if (! hash_equals($file['sha256'], hash_file('sha256', $absolute))) {
            throw new RuntimeException('Lokale Dateiprüfsumme stimmt nicht.');
        }
    }

    private function assertTargetParents(string $absolute): void
    {
        $root = rtrim(config('filesystems.disks.local.root'), '/\\');
        $normalize = fn (string $path): string => strtolower(str_replace('\\', '/', $path));
        if (! str_starts_with($normalize($absolute), $normalize($root).'/')) {
            throw new RuntimeException('Dateiziel liegt außerhalb des privaten lokalen Speichers.');
        }
        $directory = dirname($absolute);
        $current = $directory;
        while (! is_dir($current)) {
            $current = dirname($current);
        }
        // Verify every existing parent, so junctions cannot escape the private root.
        $probe = $current;
        while (strlen($probe) >= strlen($root)) {
            if (is_link($probe) || strcasecmp(str_replace('\\', '/', (string) realpath($probe)), str_replace('\\', '/', $probe)) !== 0) {
                throw new RuntimeException('Unsicheres lokales Dateiziel.');
            }
            $probe = dirname($probe);
        }
    }
}
