<?php

namespace App\Services;

use App\Models\PersonalTeachingBackup;
use App\Models\RestaurantSepaMandate;
use App\Models\TeachingCourseStudent;
use Generator;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use PDO;
use RuntimeException;
use Throwable;

class FeaturePreviewSnapshotService
{
    private const array EMPTY_TABLES = ['sessions', 'password_reset_tokens', 'personal_access_tokens', 'jobs', 'job_batches', 'failed_jobs', 'cache', 'cache_locks'];

    public function __construct(
        private FeaturePreviewDatabaseGuard $database,
        private FeaturePreviewSnapshotArchive $archive,
        private FeaturePreviewSnapshotIdentityStore $identity,
        private FeaturePreviewSnapshotFiles $files,
    ) {}

    public function generateKey(): string
    {
        if (! config('schooltool.preview.instance')) {
            throw new RuntimeException('Snapshot recipient keys belong exclusively to the preview application.');
        }
        $path = $this->keyPath();
        $mask = umask(0077);
        try {
            $handle = @fopen($path, 'x+b');
        } finally {
            umask($mask);
        }
        if ($handle === false) {
            throw new RuntimeException('The snapshot key already exists or its private directory is not writable.');
        }
        $complete = false;
        try {
            if (! chmod($path, 0600)) {
                throw new RuntimeException('Cannot protect the private snapshot key.');
            }
            $key = FeaturePreviewSnapshotArchive::generateKeyPair();
            if (fwrite($handle, $key) !== strlen($key)) {
                throw new RuntimeException('Cannot write the private snapshot key.');
            }
            $complete = true;
        } finally {
            fclose($handle);
            if (! $complete) {
                unlink($path);
            }
        }

        return FeaturePreviewSnapshotArchive::publicKey($key);
    }

    /** @return array<string, mixed> */
    public function status(string $feature): array
    {
        $this->feature($feature);
        $this->database->target();
        $this->files->assertConfigurationSafe();
        $state = $this->identity->read();
        $pending = $this->identity->read('snapshot-pending.json');
        if ($pending !== []) {
            throw new RuntimeException('A previous preview snapshot deployment is incomplete. Inspect its private backup and complete recovery before another deployment.');
        }

        return [
            'public_key' => FeaturePreviewSnapshotArchive::publicKey($this->keyPair()),
            'needs_snapshot' => ($state['feature_id'] ?? null) !== $feature || ($state['source_identity'] ?? null) !== $this->database->sourceIdentity(),
            'feature_id' => $state['feature_id'] ?? null,
            'snapshot_directory' => str_replace('\\', '/', $this->identity->directory()),
        ];
    }

    /** @return array{artifact: string, path: string, sha256: string} */
    public function export(string $feature, string $recipient, string $artifact): array
    {
        $this->feature($feature);
        $this->feature($artifact);
        if (preg_match('/\A[a-f0-9]{64}\z/', $recipient) !== 1) {
            throw new RuntimeException('Invalid snapshot recipient public key.');
        }
        if (config('schooltool.preview.instance')) {
            throw new RuntimeException('Live snapshots may only be exported by the main application.');
        }
        $this->files->assertConfigurationSafe();
        $locks = $this->liveLocks();
        $path = $this->identity->path($artifact.'.stpreview');
        $written = false;
        try {
            $fileFingerprint = $this->files->fingerprint();
            $this->database->withMainReadOnlyConnection(function (Connection $source) use ($path, $recipient, $feature, $fileFingerprint, &$written): void {
                $this->archive->write($path, $recipient, $this->records($source, $feature, true, $recipient, $fileFingerprint));
                $written = true;
            });
        } catch (Throwable $exception) {
            if ($written) {
                $this->identity->assertPrivateFile($path);
                unlink($path);
            }
            throw $exception;
        } finally {
            foreach ($locks as $lock) {
                flock($lock, LOCK_UN);
                fclose($lock);
            }
        }

        return ['artifact' => $artifact, 'path' => str_replace('\\', '/', $path), 'sha256' => hash_file('sha256', $path)];
    }

    public function deleteExport(string $artifact): void
    {
        $this->feature($artifact);
        if (config('schooltool.preview.instance')) {
            throw new RuntimeException('Only main may remove completed live exports.');
        }
        $path = $this->identity->path($artifact.'.stpreview');
        if (file_exists($path) || is_link($path)) {
            $this->identity->assertPrivateFile($path);
            if (! unlink($path)) {
                throw new RuntimeException('Cannot remove the completed encrypted snapshot export.');
            }
        }
    }

    /** @return array{path: string} */
    public function receive(string $path, string $checksum): array
    {
        $this->database->target();
        $temporaryRoot = realpath(sys_get_temp_dir());
        $directory = dirname($path);
        if ($temporaryRoot === false || realpath(dirname($directory)) !== $temporaryRoot
            || preg_match('/\Aschooltool-preview-[a-f0-9]{32}\z/', basename($directory)) !== 1
            || preg_match('/\A[a-f0-9]{32}\.stpreview\z/', basename($path)) !== 1
            || is_link($directory) || is_link($path) || ! is_file($path)
            || (PHP_OS_FAMILY !== 'Windows' && ((fileperms($directory) & 0077) !== 0 || (fileperms($path) & 0077) !== 0))
            || (function_exists('posix_geteuid') && (fileowner($directory) !== posix_geteuid() || fileowner($path) !== posix_geteuid()))
            || preg_match('/\A[a-f0-9]{64}\z/', $checksum) !== 1
            || ! hash_equals($checksum, (string) hash_file('sha256', $path))) {
            throw new RuntimeException('The incoming encrypted snapshot is not a private, verified transport file.');
        }
        $target = $this->identity->path(bin2hex(random_bytes(16)).'.stpreview');
        $source = fopen($path, 'rb');
        $mask = umask(0077);
        try {
            $destination = fopen($target, 'x+b');
        } finally {
            umask($mask);
        }
        if ($source === false || $destination === false) {
            if (is_resource($source)) {
                fclose($source);
            }
            if (is_resource($destination)) {
                fclose($destination);
                unlink($target);
            }
            throw new RuntimeException('Cannot stage the encrypted snapshot in private storage.');
        }
        $complete = false;
        try {
            if (stream_copy_to_stream($source, $destination) === false || ! fflush($destination)
                || ! hash_equals($checksum, (string) hash_file('sha256', $target))) {
                throw new RuntimeException('The encrypted snapshot changed during transport.');
            }
            $complete = true;
        } finally {
            fclose($source);
            fclose($destination);
            if (! $complete) {
                unlink($target);
            }
        }

        return ['path' => str_replace('\\', '/', $target)];
    }

    /** @return array{backup: ?string, imported: bool} */
    public function import(string $feature, string $path, string $checksum, bool $replace): array
    {
        $this->feature($feature);
        if (! $replace) {
            throw new RuntimeException('Replacing preview data requires explicit snapshot confirmation.');
        }
        $target = $this->database->target();
        $this->files->assertConfigurationSafe();
        $this->requireMaintenance();
        $this->identity->assertPrivateFile($path);
        $this->assertChecksum($path, $checksum);
        if ($this->identity->read('snapshot-pending.json') !== []) {
            throw new RuntimeException('Recover the incomplete snapshot deployment before replacing preview data again.');
        }

        $staging = $this->identity->directory().DIRECTORY_SEPARATOR.'files-'.bin2hex(random_bytes(16));
        if (! mkdir($staging, 0700)) {
            throw new RuntimeException('Cannot stage the private snapshot files.');
        }
        $metadata = [];
        $tables = [];
        $users = [];
        try {
            $this->files->restore($this->validatedRecords($path, $feature, $metadata, $tables, $users), $staging);
            $this->assertChecksum($path, $checksum);

            $backup = $this->identity->path('backup-'.gmdate('YmdHis').'-'.bin2hex(random_bytes(8)).'.stpreview');
            $this->archive->write($backup, FeaturePreviewSnapshotArchive::publicKey($this->keyPair()), $this->records($target, $feature, false));
            $pending = [
                'feature_id' => $feature,
                'users' => $users,
                'snapshot_at' => $metadata['exported_at'],
                'snapshot_source' => $metadata['source_commit'],
                'source_identity' => $metadata['source'],
                'backup' => $backup,
                'backup_sha256' => hash_file('sha256', $backup),
                'phase' => 'importing',
                'staging' => $staging,
            ];
            $this->identity->write($pending, 'snapshot-pending.json');
            $this->replaceTables($target, $path, $tables);
            $this->activateFiles($staging, $pending);
            $this->clearLocalSessions();
            $pending['phase'] = 'imported';
            $this->identity->write($pending, 'snapshot-pending.json');

            return ['backup' => $backup, 'imported' => true];
        } catch (Throwable $exception) {
            // Preserve encrypted backups, staged files and the recovery marker; never reopen a partly updated preview.
            throw new RuntimeException('Preview snapshot import failed. The preview must remain closed; inspect its private recovery files.', previous: $exception);
        }
    }

    public function assertCurrent(string $feature): void
    {
        $this->feature($feature);
        $target = $this->database->target();
        $state = $this->identity->read();
        if (($state['feature_id'] ?? null) !== $feature || $this->identity->read('snapshot-pending.json') !== []
            || ($state['source_identity'] ?? null) !== $this->database->sourceIdentity()) {
            throw new RuntimeException('This feature requires a completed, confirmed live-data snapshot.');
        }
        $available = $this->availableMigrations();
        $applied = $target->table('migrations')->pluck('migration')->all();
        foreach (array_unique([...($state['migrations'] ?? []), ...$applied]) as $migration) {
            if (! in_array($migration, $available, true)) {
                throw new RuntimeException('This candidate is older than the preview database. Refresh its data before deploying older code.');
            }
        }
    }

    public function restore(bool $confirmed): void
    {
        if (! $confirmed) {
            throw new RuntimeException('Restoring the private preview backup requires explicit --replace confirmation.');
        }
        $target = $this->database->target();
        $this->requireMaintenance();
        $this->files->assertConfigurationSafe();
        $pending = $this->identity->read('snapshot-pending.json');
        $path = $pending['backup'] ?? null;
        $checksum = $pending['backup_sha256'] ?? null;
        $feature = $pending['feature_id'] ?? null;
        if (! is_string($path) || ! is_string($checksum) || ! is_string($feature)) {
            throw new RuntimeException('No incomplete snapshot deployment with a verified recovery backup is available.');
        }
        $this->feature($feature);
        $this->identity->assertPrivateFile($path);
        if (! hash_equals($checksum, (string) hash_file('sha256', $path))) {
            throw new RuntimeException('The private recovery backup checksum does not match.');
        }
        $staging = $this->identity->directory().DIRECTORY_SEPARATOR.'restore-'.bin2hex(random_bytes(16));
        if (! mkdir($staging, 0700)) {
            throw new RuntimeException('Cannot stage the private recovery files.');
        }
        $metadata = [];
        $tables = [];
        $users = [];
        $this->files->restore($this->validatedRecords($path, $feature, $metadata, $tables, $users, true), $staging);
        $pending['phase'] = 'restoring';
        $this->identity->write($pending, 'snapshot-pending.json');
        $this->replaceTables($target, $path, $tables);
        $this->activateFiles($staging, $pending);
        $this->clearLocalSessions();
        $this->identity->write($metadata['previous_state']);
        $marker = $this->identity->path('snapshot-pending.json');
        $this->identity->assertPrivateFile($marker);
        if (! unlink($marker)) {
            throw new RuntimeException('The preview backup is restored, but its pending marker requires inspection.');
        }
    }

    public function checkpoint(string $feature): void
    {
        $this->assertCurrent($feature);
        $this->requireMaintenance();
        $this->files->assertConfigurationSafe();
        $target = $this->database->target();
        $backup = $this->identity->path('backup-'.gmdate('YmdHis').'-'.bin2hex(random_bytes(8)).'.stpreview');
        $this->archive->write($backup, FeaturePreviewSnapshotArchive::publicKey($this->keyPair()), $this->records($target, $feature, false));
        $state = $this->identity->read();
        $state['backup'] = $backup;
        $state['backup_sha256'] = hash_file('sha256', $backup);
        $state['phase'] = 'imported';
        $this->identity->write($state, 'snapshot-pending.json');
    }

    public function activate(string $feature, string $source): void
    {
        $this->feature($feature);
        if (preg_match('/\A[a-f0-9]{40,64}\z/', $source) !== 1) {
            throw new RuntimeException('Invalid preview deployment source identity.');
        }
        $this->requireMaintenance();
        $target = $this->database->target();
        $pending = $this->identity->read('snapshot-pending.json');
        $state = $pending !== [] ? $pending : $this->identity->read();
        if (($state['feature_id'] ?? null) !== $feature || ($pending !== [] && ($pending['phase'] ?? null) !== 'imported')
            || ($state['source_identity'] ?? null) !== $this->database->sourceIdentity()) {
            throw new RuntimeException('The snapshot is not ready to activate for this feature.');
        }
        $state['source_commit'] = $source;
        $state['migrations'] = $target->table('migrations')->orderBy('migration')->pluck('migration')->all();
        $state['activated_at'] = gmdate(DATE_ATOM);
        unset($state['phase'], $state['staging']);
        $this->identity->write($state);
        if ($pending !== []) {
            $path = $this->identity->path('snapshot-pending.json');
            $this->identity->assertPrivateFile($path);
            if (! unlink($path)) {
                throw new RuntimeException('Snapshot activated but its pending marker needs recovery.');
            }
        }
    }

    /** @return Generator<int, array<string, mixed>> */
    private function records(Connection $connection, string $feature, bool $live, ?string $recipient = null, ?string $fileFingerprint = null): Generator
    {
        $fileFingerprint ??= $this->files->fingerprint();
        $recipient ??= FeaturePreviewSnapshotArchive::publicKey($this->keyPair());
        $pdo = $connection->getPdo();
        if (! $live) {
            $pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
            $pdo->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY');
        }
        try {
            $tables = $this->database->tables($connection);
            yield [
                'kind' => 'header', 'version' => 2, 'purpose' => $live ? 'live' : 'backup', 'feature_id' => $feature,
                'source' => $this->database->connectionIdentity($connection), 'recipient' => $recipient,
                'exported_at' => gmdate(DATE_ATOM), 'source_commit' => $this->sourceCommit(),
                'previous_state' => $live ? null : $this->identity->read(),
            ];
            foreach ($tables as $table) {
                $quoted = $this->database->identifier($table);
                $definition = array_values((array) $connection->selectOne('SHOW CREATE TABLE '.$quoted))[1];
                $this->database->assertCreateStatement($table, $definition);
                $columns = $connection->select('SELECT COLUMN_NAME, EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? ORDER BY ORDINAL_POSITION', [$connection->getDatabaseName(), $table]);
                $columns = array_values(array_map(static fn (object $column): string => $column->COLUMN_NAME, array_filter($columns, static fn (object $column): bool => preg_match('/\b(?:STORED|VIRTUAL) GENERATED\b/i', $column->EXTRA) !== 1)));
                $encrypted = array_values(array_intersect($columns, $this->encryptedColumns()[$table] ?? []));
                yield ['kind' => 'table', 'name' => $table, 'create' => $definition, 'columns' => $columns, 'encrypted' => $encrypted];
                if ($live && (in_array($table, self::EMPTY_TABLES, true) || str_starts_with($table, 'telescope_') || str_starts_with($table, 'pulse_'))) {
                    continue;
                }
                $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
                $statement = $pdo->query('SELECT '.implode(', ', array_map($this->database->identifier(...), $columns)).' FROM '.$quoted);
                try {
                    while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
                        if ($live && $table === 'users') {
                            yield ['kind' => 'user_identity', 'id' => (string) $row['id'], 'fingerprint' => FeaturePreviewService::authenticationFingerprintFromAttributes($row)];
                            foreach (['remember_token', 'uuid', 'uuid_at', 'token_2fa', 'token_2fa_expires_at', 'token_2fa_2', 'token_2fa_2_expires_at'] as $column) {
                                if (array_key_exists($column, $row)) {
                                    $row[$column] = null;
                                }
                            }
                        }
                        if ($live && in_array($table, ['teachers', 'tutoring_offers', 'tutoring_offer_requests'], true)) {
                            foreach (['token', 'token_expires_at'] as $column) {
                                if (array_key_exists($column, $row)) {
                                    $row[$column] = null;
                                }
                            }
                        }
                        if ($live && $table === 'restaurant_sepa_mandates') {
                            foreach (['confirmation_code', 'confirmation_code_expires_at'] as $column) {
                                if (array_key_exists($column, $row)) {
                                    $row[$column] = null;
                                }
                            }
                        }
                        foreach ($encrypted as $column) {
                            if ($row[$column] !== null) {
                                $row[$column] = Crypt::decryptString($row[$column]);
                            }
                        }
                        if ($live) {
                            foreach ($row as $column => $value) {
                                if (is_string($value) && ! in_array($column, $encrypted, true) && $this->isEncryptedValue($value)) {
                                    throw new RuntimeException('An encrypted database column is missing from the snapshot mapping; review its re-encryption before copying data.');
                                }
                            }
                        }
                        yield ['kind' => 'row', 'table' => $table, 'values' => array_map(static fn (mixed $value): ?string => $value === null ? null : base64_encode((string) $value), array_values($row))];
                    }
                } finally {
                    $statement->closeCursor();
                    $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
                }
            }
        } finally {
            if (! $live && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
        }
        yield from $this->files->records();
        if (! hash_equals($fileFingerprint, $this->files->fingerprint())) {
            throw new RuntimeException('Live files changed during the snapshot. Retry after the file changes have finished.');
        }
        yield ['kind' => 'complete'];
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @param  array<string, array<string, mixed>>  $tables
     * @param  array<int, string>  $users
     * @return Generator<int, array<string, mixed>>
     */
    private function validatedRecords(string $path, string $feature, array &$metadata, array &$tables, array &$users, bool $backup = false): Generator
    {
        $complete = false;
        $filesStarted = false;
        $currentTable = null;
        $availableMigrations = $this->availableMigrations();
        $expectedSource = $backup ? $this->database->connectionIdentity(DB::connection()) : $this->database->sourceIdentity();
        $expectedRecipient = FeaturePreviewSnapshotArchive::publicKey($this->keyPair());
        foreach ($this->archive->read($path, $this->keyPair()) as $record) {
            $kind = $record['kind'] ?? null;
            if ($metadata === []) {
                $sameEncryptionKey = is_string($record['source']['app_key_fingerprint'] ?? null)
                    && hash_equals(hash('sha256', app('encrypter')->getKey()), $record['source']['app_key_fingerprint']);
                if ($kind !== 'header' || ($record['version'] ?? null) !== 2 || ($record['purpose'] ?? null) !== ($backup ? 'backup' : 'live')
                    || ($record['feature_id'] ?? null) !== $feature
                    || ($record['source'] ?? null) !== $expectedSource || ($record['recipient'] ?? null) !== $expectedRecipient
                    || $sameEncryptionKey !== $backup
                    || ! is_string($record['exported_at'] ?? null) || ! array_key_exists('source_commit', $record)
                    || ($backup && ! is_array($record['previous_state'] ?? null))) {
                    throw new RuntimeException('Snapshot identity, source or encryption-key separation is invalid.');
                }
                $metadata = [
                    'exported_at' => $record['exported_at'],
                    'source_commit' => $record['source_commit'],
                    'source' => $record['source'],
                    'previous_state' => $record['previous_state'] ?? null,
                ];

                continue;
            }
            if ($complete) {
                throw new RuntimeException('Unexpected data after the snapshot completion marker.');
            }
            if ($kind === 'complete') {
                $complete = true;

                continue;
            }
            if (in_array($kind, ['file_start', 'file_chunk', 'file_end'], true)) {
                $filesStarted = true;
                yield $record;

                continue;
            }
            if ($filesStarted) {
                throw new RuntimeException('Database records must precede snapshot files.');
            }
            if ($kind === 'table') {
                $name = $record['name'] ?? null;
                if (! is_string($name) || ! is_string($record['create'] ?? null)) {
                    throw new RuntimeException('Invalid snapshot table identity.');
                }
                $this->database->assertCreateStatement($name, $record['create']);
                if (isset($tables[$name]) || ! is_array($record['columns'] ?? null) || $record['columns'] === []
                    || ! is_array($record['encrypted'] ?? null) || array_diff($record['encrypted'], $this->encryptedColumns()[$name] ?? []) !== []) {
                    throw new RuntimeException('Invalid or duplicate snapshot table.');
                }
                foreach ($record['columns'] as $column) {
                    $this->database->identifier($column);
                }
                if (count(array_unique($record['columns'])) !== count($record['columns']) || array_diff($record['encrypted'], $record['columns']) !== []) {
                    throw new RuntimeException('Invalid snapshot column mapping.');
                }
                $tables[$name] = ['create' => $record['create'], 'columns' => $record['columns'], 'encrypted' => $record['encrypted']];
                $currentTable = $name;

                continue;
            }
            if ($kind === 'user_identity' && $currentTable === 'users') {
                if (! is_string($record['id'] ?? null) || ! is_string($record['fingerprint'] ?? null)
                    || preg_match('/\A[1-9][0-9]*\z/', $record['id']) !== 1 || (string) (int) $record['id'] !== $record['id']
                    || preg_match('/\A[a-f0-9]{64}\z/', $record['fingerprint']) !== 1 || isset($users[(int) $record['id']])) {
                    throw new RuntimeException('Invalid snapshot authentication identity.');
                }
                $users[(int) $record['id']] = $record['fingerprint'];

                continue;
            }
            if ($kind !== 'row' || ($record['table'] ?? null) !== $currentTable || ! isset($tables[$currentTable])
                || ! is_array($record['values'] ?? null) || ! array_is_list($record['values'])
                || count($record['values']) !== count($tables[$currentTable]['columns'])) {
                throw new RuntimeException('Invalid snapshot row.');
            }
            foreach ($record['values'] as $value) {
                if ($value !== null && (! is_string($value) || base64_decode($value, true) === false)) {
                    throw new RuntimeException('Invalid snapshot value encoding.');
                }
            }
            if (! $backup && $currentTable === 'migrations') {
                $migrationColumn = array_search('migration', $tables[$currentTable]['columns'], true);
                $encodedMigration = $migrationColumn === false ? null : $record['values'][$migrationColumn];
                $migration = is_string($encodedMigration) ? base64_decode($encodedMigration, true) : null;
                if (! is_string($migration) || ! in_array($migration, $availableMigrations, true)) {
                    throw new RuntimeException('The live snapshot database is newer than this candidate. Update the feature and create a new preview before replacing data.');
                }
            }
        }
        if (! $complete || (! $backup && ! isset($tables['users'], $tables['migrations']))) {
            throw new RuntimeException('Incomplete snapshot or required application tables missing.');
        }
    }

    /** @param array<string, array<string, mixed>> $tables */
    private function replaceTables(Connection $target, string $path, array $tables): void
    {
        $pdo = $target->getPdo();
        $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
        try {
            foreach ($this->database->tables($target) as $table) {
                $pdo->exec('DROP TABLE '.$this->database->identifier($table));
            }
            foreach ($tables as $table) {
                $pdo->exec($table['create']);
            }
            $statements = [];
            foreach ($this->archive->read($path, $this->keyPair()) as $record) {
                if (($record['kind'] ?? null) !== 'row') {
                    continue;
                }
                $name = $record['table'];
                $table = $tables[$name];
                $values = [];
                foreach ($record['values'] as $index => $value) {
                    $value = $value === null ? null : base64_decode($value, true);
                    if ($value !== null && in_array($table['columns'][$index], $table['encrypted'], true)) {
                        $value = Crypt::encryptString($value);
                    }
                    $values[] = $value;
                }
                $statement = $statements[$name] ??= $pdo->prepare('INSERT INTO '.$this->database->identifier($name).' ('.implode(', ', array_map($this->database->identifier(...), $table['columns'])).') VALUES ('.implode(', ', array_fill(0, count($values), '?')).')');
                $statement->execute($values);
            }
        } finally {
            $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
        }
    }

    /** @param array<string, mixed> $pending */
    private function activateFiles(string $staging, array &$pending): void
    {
        $this->files->assertConfigurationSafe();
        foreach (['private', 'public'] as $name) {
            $target = storage_path('app/'.$name);
            $previous = storage_path('app/.preview-'.$name.'-'.bin2hex(random_bytes(8)));
            if (is_link($target) || (file_exists($target) && ! rename($target, $previous))) {
                throw new RuntimeException('Cannot preserve the previous private preview files.');
            }
            $pending['previous_files'][$name] = $previous;
            $this->identity->write($pending, 'snapshot-pending.json');
            if (! rename($staging.DIRECTORY_SEPARATOR.$name, $target)) {
                throw new RuntimeException('Cannot activate the new private preview files.');
            }
        }
    }

    private function clearLocalSessions(): void
    {
        $directory = realpath(storage_path('framework/sessions'));
        if ($directory === false || is_link(storage_path('framework/sessions')) || ! str_starts_with($directory, realpath(storage_path()).DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('The preview session directory must belong to this application.');
        }
        foreach (glob($directory.DIRECTORY_SEPARATOR.'*') ?: [] as $file) {
            if (is_link($file) || ! is_file($file) || ! unlink($file)) {
                throw new RuntimeException('Cannot clear the isolated preview sessions.');
            }
        }
    }

    /** @return array<string, list<string>> */
    private function encryptedColumns(): array
    {
        $mapping = ['users' => ['two_factor_secret', 'two_factor_recovery_codes']];
        foreach ([new TeachingCourseStudent, new RestaurantSepaMandate, new PersonalTeachingBackup] as $model) {
            $mapping[$model->getTable()] = array_keys(array_filter($model->getCasts(), static fn (string $cast): bool => str_starts_with($cast, 'encrypted')));
        }

        return $mapping;
    }

    /** @return list<resource> */
    private function liveLocks(): array
    {
        $locks = [];
        try {
            foreach (['cloudways-pdeploy.lock', 'cloudways-deploy.lock'] as $name) {
                $path = storage_path('framework/'.$name);
                if (is_link($path)) {
                    throw new RuntimeException('The live deployment lock must be a regular local file.');
                }
                $handle = fopen($path, 'c+b');
                if ($handle === false || ! flock($handle, LOCK_SH | LOCK_NB)) {
                    if (is_resource($handle)) {
                        fclose($handle);
                    }
                    throw new RuntimeException('Live is currently deploying; retry the preview snapshot after it finishes.');
                }
                $locks[] = $handle;
            }
        } catch (Throwable $exception) {
            foreach ($locks as $lock) {
                fclose($lock);
            }
            throw $exception;
        }

        return $locks;
    }

    private function keyPath(): string
    {
        $path = (string) config('schooltool.preview.snapshot_key_path');
        if ($path === '' || realpath(dirname($path)) !== $this->identity->directory() || is_link($path)) {
            throw new RuntimeException('Configure the snapshot key inside the private snapshot directory.');
        }

        return $path;
    }

    private function keyPair(): string
    {
        $path = $this->keyPath();
        $this->identity->assertPrivateFile($path);
        $key = file_get_contents($path);
        if (! is_string($key) || strlen($key) !== SODIUM_CRYPTO_BOX_KEYPAIRBYTES) {
            throw new RuntimeException('The preview snapshot key is missing or invalid.');
        }

        return $key;
    }

    private function feature(string $value): void
    {
        if (preg_match('/\A[a-f0-9]{32}\z/', $value) !== 1) {
            throw new RuntimeException('A valid immutable feature identity is required.');
        }
    }

    private function requireMaintenance(): void
    {
        if (! is_file(storage_path('framework/down'))) {
            throw new RuntimeException('Preview snapshot changes require local maintenance mode.');
        }
    }

    private function sourceCommit(): ?string
    {
        $path = base_path('deployment/source-commit');
        $value = is_file($path) ? trim((string) file_get_contents($path)) : '';

        return preg_match('/\A[a-f0-9]{40,64}\z/', $value) === 1 ? $value : null;
    }

    private function assertChecksum(string $path, string $checksum): void
    {
        if (preg_match('/\A[a-f0-9]{64}\z/', $checksum) !== 1 || ! hash_equals($checksum, (string) hash_file('sha256', $path))) {
            throw new RuntimeException('The encrypted snapshot checksum does not match the authenticated export.');
        }
    }

    private function isEncryptedValue(string $value): bool
    {
        $decoded = base64_decode($value, true);
        if ($decoded === false) {
            return false;
        }
        $payload = json_decode($decoded, true);

        return is_array($payload) && isset($payload['iv'], $payload['value'], $payload['mac']);
    }

    /** @return list<string> */
    private function availableMigrations(): array
    {
        return array_map(static fn (string $path): string => basename($path, '.php'), glob(database_path('migrations/*.php')) ?: []);
    }
}
