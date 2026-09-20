<?php

namespace App\Services;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use PDO;
use RuntimeException;
use Throwable;

class FeaturePreviewDatabaseGuard
{
    /** @return array{database: string, server_fingerprint: string, app_key_fingerprint: string} */
    public function sourceIdentity(): array
    {
        $response = app(FeaturePreviewControlClient::class)->request('status');
        $source = $response['source'] ?? null;
        if (! is_array($source) || ! is_string($source['database'] ?? null)
            || preg_match('/\A[a-zA-Z0-9_]+\z/', $source['database']) !== 1
            || ! is_string($source['server_fingerprint'] ?? null) || preg_match('/\A[a-f0-9]{64}\z/', $source['server_fingerprint']) !== 1
            || ! is_string($source['app_key_fingerprint'] ?? null) || preg_match('/\A[a-f0-9]{64}\z/', $source['app_key_fingerprint']) !== 1) {
            throw new RuntimeException('The authenticated main database identity is unavailable or invalid.');
        }

        return [
            'database' => $source['database'],
            'server_fingerprint' => $source['server_fingerprint'],
            'app_key_fingerprint' => $source['app_key_fingerprint'],
        ];
    }

    public function target(): Connection
    {
        $target = DB::connection();
        $this->assertTargetConnection($target);

        return $target;
    }

    private function assertTargetConnection(Connection $target): void
    {
        if (! config('schooltool.preview.instance')) {
            throw new RuntimeException('Snapshot target operations require the preview instance.');
        }

        $source = $this->sourceIdentity();
        $this->assertConnection($target, false);
        if (strcasecmp($source['database'], $target->getDatabaseName()) === 0) {
            throw new RuntimeException('Preview and live database names must be different, including when their hosts differ.');
        }
        if (hash_equals($source['app_key_fingerprint'], hash('sha256', app('encrypter')->getKey()))
            || config('app.previous_keys', []) !== []) {
            throw new RuntimeException('Preview requires a separate application encryption key without previous live keys.');
        }
    }

    /**
     * @template T
     *
     * @param  callable(Connection): T  $callback
     * @return T
     */
    public function withSnapshotTargetConnection(callable $callback): mixed
    {
        if (! config('schooltool.preview.instance')) {
            throw new RuntimeException('Snapshot target operations require the preview instance.');
        }
        $connection = $this->snapshotConnection('preview_snapshot_target');
        try {
            $pdo = $connection->getPdo();
            $this->extendSnapshotIdleTimeout($pdo);
            $this->assertTargetConnection($connection);
            $connectionId = (string) $pdo->query('SELECT CONNECTION_ID()')->fetchColumn();
            $result = $callback($connection);
            if ((string) $pdo->query('SELECT CONNECTION_ID()')->fetchColumn() !== $connectionId) {
                throw new RuntimeException('The preview snapshot target connection changed during the operation.');
            }

            return $result;
        } finally {
            $connection->disconnect();
        }
    }

    /** @return array{database: string, server_fingerprint: string, app_key_fingerprint: string} */
    public function mainIdentity(): array
    {
        return $this->withMainReadOnlyConnection(fn (Connection $connection): array => $this->connectionIdentity($connection));
    }

    /** @return array{database: string, server_fingerprint: string, app_key_fingerprint: string} */
    public function connectionIdentity(Connection $connection): array
    {
        return [
            'database' => $connection->getDatabaseName(),
            'server_fingerprint' => hash('sha256', $this->serverIdentity($connection)),
            'app_key_fingerprint' => hash('sha256', app('encrypter')->getKey()),
        ];
    }

    /**
     * @template T
     *
     * @param  callable(Connection): T  $callback
     * @return T
     */
    public function withMainReadOnlyConnection(callable $callback): mixed
    {
        if (config('schooltool.preview.instance')) {
            throw new RuntimeException('The main read-only connection is unavailable in preview.');
        }
        $connection = $this->snapshotConnection('preview_snapshot_source');
        $pdo = $connection->getPdo();
        try {
            $this->extendSnapshotIdleTimeout($pdo);
            $pdo->exec('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');
            $pdo->exec('SET SESSION TRANSACTION READ ONLY');
            $pdo->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY');
            $connectionId = (string) $pdo->query('SELECT CONNECTION_ID()')->fetchColumn();
            $this->assertReadOnlyTransaction($pdo, $connectionId);
            $connection->beforeExecuting(function (string $query) use ($pdo, $connectionId): void {
                $this->assertReadOnlyTransaction($pdo, $connectionId);
                if (preg_match('/\A\s*(?:SELECT\b|SHOW (?:GRANTS\b|CREATE TABLE\b|SESSION VARIABLES\b))/i', $query) !== 1
                    || preg_match('/;|\/\*|--|\b(?:INTO|OUTFILE|DUMPFILE|FOR UPDATE|LOCK IN SHARE MODE|GET_LOCK|RELEASE_LOCK|SLEEP|BENCHMARK|LOAD_FILE)\b/i', $query) === 1) {
                    throw new RuntimeException('Only internal read-only queries are permitted on the main snapshot connection.');
                }
            });
            $this->assertConnection($connection, false);
            $grants = $connection->select('SHOW GRANTS');
            $metadataVisible = false;
            foreach ($grants as $grant) {
                $metadataVisible = $metadataVisible || preg_match('/\AGRANT ALL PRIVILEGES ON /i', (string) array_values((array) $grant)[0]) === 1;
            }
            if (! $metadataVisible) {
                throw new RuntimeException('The main account must own all privileges on its single schema so hidden triggers, routines and events cannot escape inspection.');
            }

            $result = $callback($connection);
            $this->assertReadOnlyTransaction($pdo, $connectionId);

            return $result;
        } finally {
            try {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
            } catch (Throwable) {
                // A lost connection has already ended the server-side read-only transaction.
            } finally {
                $connection->disconnect();
            }
        }
    }

    private function snapshotConnection(string $name): Connection
    {
        $configuration = config('database.connections.'.config('database.default'));
        if (! is_array($configuration)) {
            throw new RuntimeException('The snapshot database configuration is unavailable.');
        }
        $this->assertConfiguration($configuration);
        $configuration['options'][PDO::ATTR_PERSISTENT] = false;
        $configuration['options'][PDO::ATTR_ERRMODE] = PDO::ERRMODE_EXCEPTION;
        $configuration['options'][PDO::MYSQL_ATTR_MULTI_STATEMENTS] = false;
        $configuration['options'][PDO::MYSQL_ATTR_LOCAL_INFILE] = false;
        unset($configuration['options'][PDO::MYSQL_ATTR_INIT_COMMAND]);
        $connection = app('db.factory')->make($configuration, $name);
        $connection->unsetEventDispatcher();
        $connection->disableQueryLog();
        $connection->setReconnector(static function (): never {
            throw new RuntimeException('A snapshot connection may never reconnect. Restart the complete snapshot operation.');
        });

        return $connection;
    }

    private function extendSnapshotIdleTimeout(PDO $pdo): void
    {
        // File streaming keeps the snapshot transaction open without issuing SQL.
        $timeout = max(3600, (int) $pdo->query('SELECT @@SESSION.wait_timeout')->fetchColumn());
        $pdo->exec('SET SESSION wait_timeout = '.$timeout);
        if ((int) $pdo->query('SELECT @@SESSION.wait_timeout')->fetchColumn() !== $timeout) {
            throw new RuntimeException('The snapshot connection idle timeout could not be configured.');
        }
    }

    public function assertReadOnlyTransaction(PDO $pdo, string $connectionId): void
    {
        if (! $pdo->inTransaction() || (string) $pdo->query('SELECT CONNECTION_ID()')->fetchColumn() !== $connectionId) {
            throw new RuntimeException('The consistent main snapshot transaction is no longer active.');
        }
        $settings = $pdo->query("SHOW SESSION VARIABLES WHERE Variable_name IN ('transaction_read_only', 'tx_read_only', 'transaction_isolation', 'tx_isolation')")->fetchAll(PDO::FETCH_KEY_PAIR);
        $readOnly = $settings['transaction_read_only'] ?? $settings['tx_read_only'] ?? null;
        $isolation = $settings['transaction_isolation'] ?? $settings['tx_isolation'] ?? null;
        if (! in_array(strtoupper((string) $readOnly), ['ON', '1'], true) || $isolation !== 'REPEATABLE-READ') {
            throw new RuntimeException('The main snapshot connection must remain read-only with repeatable reads.');
        }
    }

    public function assertConnection(Connection $connection, bool $readOnly): void
    {
        $configuration = $connection->getConfig();
        $this->assertConfiguration($configuration);

        $identity = (array) $connection->selectOne('SELECT DATABASE() AS database_name, CURRENT_USER() AS account');
        if (($identity['database_name'] ?? null) !== $connection->getDatabaseName()
            || ! str_starts_with((string) ($identity['account'] ?? ''), $configuration['username'].'@')) {
            throw new RuntimeException('The database connection identity does not match its configured account.');
        }

        $grants = array_map(static fn (object $row): string => (string) array_values((array) $row)[0], $connection->select('SHOW GRANTS'));
        $this->assertGrants($grants, $connection->getDatabaseName(), $readOnly);
    }

    /** @param array<string, mixed> $configuration */
    private function assertConfiguration(array $configuration): void
    {
        if (! in_array($configuration['driver'] ?? null, ['mysql', 'mariadb'], true)
            || ! empty($configuration['url']) || ! empty($configuration['read']) || ! empty($configuration['write'])
            || ! empty($configuration['prefix']) || ! empty($configuration['unix_socket'])
            || ! is_string($configuration['host'] ?? null) || empty($configuration['username'])) {
            throw new RuntimeException('Preview snapshots require explicit, unprefixed MySQL connections without URL, socket or read/write overrides.');
        }

    }

    /** @param list<string> $grants */
    public function assertGrants(array $grants, string $database, bool $readOnly): void
    {
        $databaseAccess = false;
        foreach ($grants as $grant) {
            if (preg_match('/^GRANT USAGE ON \*\.\* TO /i', $grant) === 1 && ! str_contains(strtoupper($grant), 'GRANT OPTION')) {
                continue;
            }
            if (preg_match('/^GRANT ([A-Z ,]+) ON `((?:[^`]|``)+)`\.\* TO /i', $grant, $matches) !== 1
                || str_contains(strtoupper($grant), 'GRANT OPTION')) {
                throw new RuntimeException('Database grants must be explicit and limited to the single configured schema; global privileges and roles are forbidden.');
            }

            $grantedDatabase = str_replace(['\\_', '\\%', '``'], ['_', '%', '`'], $matches[2]);
            if (preg_match('/(?<!\\\\)[_%]/', $matches[2]) === 1 || $grantedDatabase !== $database) {
                throw new RuntimeException('Database grants include a wildcard or another schema.');
            }
            $privileges = array_map('trim', explode(',', strtoupper($matches[1])));
            $allowed = $readOnly ? ['SELECT'] : ['ALL PRIVILEGES', 'SELECT', 'INSERT', 'UPDATE', 'DELETE', 'CREATE', 'DROP', 'INDEX', 'ALTER', 'REFERENCES', 'CREATE TEMPORARY TABLES', 'LOCK TABLES'];
            if (array_diff($privileges, $allowed) !== []) {
                throw new RuntimeException($readOnly ? 'The live snapshot account must have SELECT privileges only.' : 'The preview database account has unsupported privileges.');
            }
            $databaseAccess = $databaseAccess || in_array('SELECT', $privileges, true) || in_array('ALL PRIVILEGES', $privileges, true);
        }
        if (! $databaseAccess) {
            throw new RuntimeException('The database account lacks explicit access to its configured schema.');
        }
    }

    /** @return list<string> */
    public function tables(Connection $connection): array
    {
        $tables = $connection->select('SELECT TABLE_NAME, TABLE_TYPE, ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? ORDER BY TABLE_NAME', [$connection->getDatabaseName()]);
        foreach ($tables as $table) {
            if ($table->TABLE_TYPE !== 'BASE TABLE' || strcasecmp((string) $table->ENGINE, 'InnoDB') !== 0) {
                throw new RuntimeException('Snapshots support only InnoDB base tables; views and nontransactional tables must be reviewed first.');
            }
            $this->identifier($table->TABLE_NAME);
        }
        foreach (['TRIGGERS' => 'TRIGGER_SCHEMA', 'ROUTINES' => 'ROUTINE_SCHEMA', 'EVENTS' => 'EVENT_SCHEMA'] as $table => $column) {
            if ((int) $connection->scalar("SELECT COUNT(*) FROM information_schema.{$table} WHERE {$column} = ?", [$connection->getDatabaseName()]) !== 0) {
                throw new RuntimeException('Snapshots refuse triggers, routines and database events.');
            }
        }

        return array_map(static fn (object $table): string => $table->TABLE_NAME, $tables);
    }

    public function identifier(string $value): string
    {
        if (preg_match('/\A[a-zA-Z0-9_]+\z/', $value) !== 1) {
            throw new RuntimeException('Unsupported snapshot database identifier.');
        }

        return '`'.$value.'`';
    }

    public function assertCreateStatement(string $table, string $statement): void
    {
        $identifier = preg_quote($this->identifier($table), '/');
        // The standard failed_jobs `connection` column is not a CONNECTION table option.
        if (preg_match('/\ACREATE TABLE '.$identifier.' \(/', $statement) !== 1
            || preg_match('/\bENGINE=InnoDB\b/i', $statement) !== 1
            || preg_match('/;|\/\*|\b(?:DATA DIRECTORY|INDEX DIRECTORY|TABLESPACE|(?:(?<!`)CONNECTION|CONNECTION(?!`)))\b|REFERENCES\s+`[^`]+`\s*\./i', $statement) === 1) {
            throw new RuntimeException('The snapshot contains an unsupported table definition.');
        }
    }

    private function serverIdentity(Connection $connection): string
    {
        $server = (array) $connection->selectOne('SELECT @@hostname AS hostname, @@port AS port, @@server_id AS server_id');

        return json_encode([
            'hostname' => (string) $server['hostname'],
            'port' => (int) $server['port'],
            'server_id' => (int) $server['server_id'],
        ], JSON_THROW_ON_ERROR);
    }
}
