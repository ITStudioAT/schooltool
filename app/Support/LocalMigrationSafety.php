<?php

namespace App\Support;

class LocalMigrationSafety
{
    /** @param array<string, mixed> $connection */
    public static function allowsTarget(string $environment, bool $preview, string $connectionName, array $connection): bool
    {
        $database = (string) ($connection['database'] ?? '');

        return $environment === 'local'
            && ! $preview
            && $connectionName === 'mysql'
            && ($connection['driver'] ?? null) === 'mysql'
            && in_array($connection['host'] ?? null, ['127.0.0.1', 'localhost', '::1'], true)
            && $database !== ''
            && preg_match('/^schooltool(?:[_-][a-z0-9]+)*$/i', $database)
            && ! preg_match('/(?:^|[_-])(preview|production|prod|live|test)(?:$|[_-])/i', $database)
            && empty($connection['url'])
            && empty($connection['unix_socket'])
            && empty($connection['read'])
            && empty($connection['write']);
    }

    /**
     * @param  array<int, string>  $migrationNames
     * @return ?array<int, string>
     */
    public static function plannedStatements(string $output, array $migrationNames): ?array
    {
        $statementsByMigration = array_fill_keys($migrationNames, []);
        $currentMigration = null;

        foreach (preg_split('/\R/u', $output) as $line) {
            $line = trim(preg_replace('/\x1b\[[0-9;]*m/', '', $line));

            if (preg_match('/^([0-9]{4}_[0-9]{2}_[0-9]{2}_[0-9]{6}_[a-z0-9_]+)\b/i', $line, $matches)
                && array_key_exists($matches[1], $statementsByMigration)) {
                $currentMigration = $matches[1];

                continue;
            }

            if (str_starts_with($line, '⇂')) {
                if ($currentMigration === null) {
                    return null;
                }

                $statementsByMigration[$currentMigration][] = trim(substr($line, strlen('⇂')));
            }
        }

        if (in_array([], $statementsByMigration, true)) {
            return null;
        }

        return array_merge(...array_values($statementsByMigration));
    }

    public static function allowsStatement(string $sql): bool
    {
        $sql = trim($sql);
        if ($sql === '' || str_contains($sql, ';')
            || preg_match('/\b(drop|truncate|rename|change|modify|update|insert|replace)\b/i', $sql)) {
            return false;
        }

        return (bool) (preg_match('/^create\s+table\s+`?[a-z][a-z0-9_]*`?\s*\(/i', $sql)
            || preg_match('/^create\s+(unique\s+)?index\s+`?[a-z][a-z0-9_]*`?\s+on\s+/i', $sql)
            || preg_match('/^alter\s+table\s+`?[a-z][a-z0-9_]*`?\s+add\s+/i', $sql));
    }
}
