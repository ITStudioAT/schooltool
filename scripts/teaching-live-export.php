<?php

use App\Models\User;
use App\Services\FeaturePreviewDatabaseGuard;
use App\Services\TeachingSynchronisationFiles;
use App\Services\TeachingSynchronisationGraph;
use Illuminate\Config\Repository;
use Illuminate\Database\Connection;
use Illuminate\Database\Connectors\ConnectionFactory;
use Illuminate\Support\Facades\Facade;

ini_set('display_errors', '0');
ini_set('log_errors', '0');
$stage = 'bootstrap';
try {
    $request = json_decode(base64_decode('__TEACHING_REQUEST__', true), true, 512, JSON_THROW_ON_ERROR);
    require getcwd().'/vendor/autoload.php';
    $app = require getcwd().'/bootstrap/app.php';
    $path = getcwd().'/bootstrap/cache/config.php';
    if (! is_file($path) || is_link($path) || realpath($path) !== $path) {
        throw new RuntimeException('Existing canonical production configuration required.');
    }
    $configuration = require $path;
    $app->instance('config', new Repository($configuration));
    $app->instance('env', $configuration['app']['env']);
    $app->instance('db.factory', new ConnectionFactory($app));
    Facade::setFacadeApplication($app);
    // Use the local reviewed reader implementation, without installing code or booting LIVE providers.
    eval(substr(base64_decode('__TEACHING_GRAPH__', true), 5));
    eval(substr(base64_decode('__TEACHING_FILES__', true), 5));
    if (! $app->environment('production') || config('schooltool.preview.instance')) {
        throw new RuntimeException('MAIN production required.');
    }
    $stage = 'read_only_connection';
    $snapshot = $app->make(FeaturePreviewDatabaseGuard::class)->withMainReadOnlyConnection(function (Connection $connection) use ($request, $configuration, &$stage): array {
        $stage = 'school_identity';
        $schoolId = (int) $request['school']['id'];
        $school = $connection->table('schools')->where('id', $schoolId)->first(['id', 'short_name', 'long_name']);
        if (! $school || (array) $school !== $request['school']) {
            throw new RuntimeException('School identity differs.');
        }
        $stage = 'schema';
        $tables = [...array_keys(TeachingSynchronisationGraph::TABLES), 'users', 'import116', 'schoolyears', 'school_tools', 'schools', 'teachers', 'roles', 'model_has_roles'];
        $engines = $connection->table('information_schema.TABLES')->where('TABLE_SCHEMA', $connection->getDatabaseName())->whereIn('TABLE_NAME', $tables)->pluck('ENGINE');
        if ($engines->count() !== count($tables) || $engines->contains(fn ($engine): bool => strcasecmp((string) $engine, 'InnoDB') !== 0)) {
            throw new RuntimeException('Transactional InnoDB base tables required.');
        }
        $stage = 'teaching_rows';
        $data = (new TeachingSynchronisationGraph)->capture($connection, $schoolId);
        $data['school'] = (array) $school;
        $data['captured_at'] = gmdate(DATE_ATOM);
        $userIds = array_merge(array_column($data['import116'], 'user_id'), array_column($data['import116'], 'import_user_id'));
        foreach ($data['tables'] as $rows) {
            foreach (TeachingSynchronisationGraph::REFERENCES as $column => $parent) {
                if ($parent === 'users') {
                    $userIds = array_merge($userIds, array_column($rows, $column));
                }
            }
        }
        $collectUsers = function (mixed $value) use (&$collectUsers, &$userIds): void {
            if (! is_array($value)) {
                return;
            }
            foreach ($value as $key => $item) {
                if ((TeachingSynchronisationGraph::REFERENCES[$key] ?? null) === 'users' && is_numeric($item)) {
                    $userIds[] = (int) $item;
                } elseif (is_array($item)) {
                    $collectUsers($item);
                } elseif (is_string($item) && in_array(substr(ltrim($item), 0, 1), ['[', '{'], true)) {
                    try {
                        $collectUsers(json_decode($item, true, 512, JSON_THROW_ON_ERROR));
                    } catch (JsonException) {
                        // A free-text description may start with a bracket; it is not a relationship container.
                    }
                }
            }
        };
        $collectUsers($data['tables']);
        $referencedUserIds = array_values(array_unique(array_filter(array_map('intval', $userIds), fn (int $id): bool => $id > 0)));
        $existingUserIds = $connection->table('users')->whereIn('id', $referencedUserIds)->pluck('id')->all();
        $data['missing_user_ids'] = array_values(array_diff($referencedUserIds, $existingUserIds));
        sort($data['missing_user_ids']);
        $userIds = array_merge($userIds, $connection->table('model_has_roles')->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_type', User::class)->whereIn('roles.name', ['teacher', 'teaching_admin', 'student'])->pluck('model_id')->all());
        $teacherEmails = array_map(fn (array $teacher): string => mb_strtolower(trim($teacher['email'])), $data['teachers']);
        $data['users'] = array_values(array_filter($data['users'], function (array $user) use ($userIds, $teacherEmails): bool {
            if (in_array($user['id'], $userIds, true) || in_array(mb_strtolower(trim($user['email'])), $teacherEmails, true)) {
                return true;
            }
            foreach (TeachingSynchronisationGraph::USER_SETTINGS as $column) {
                if (($column === 'teaching_behaviour' || $column === 'teaching_notifications' || str_ends_with($column, '_by_schoolyear'))
                    && ! in_array($user[$column], [null, '', '[]', '{}'], true)) {
                    return true;
                }
            }

            return false;
        }));
        $data['external'] = [];
        // Verify referenced material provenance locally; do not synchronize other modules.
        $externalIds = [];
        foreach (['material_card_id' => 'material_cards', 'source_material_card_id' => 'material_cards',
            'material_card_attachment_id' => 'material_card_attachments', 'source_material_card_attachment_id' => 'material_card_attachments'] as $column => $table) {
            $ids = [];
            foreach ($data['tables'] as $rows) {
                $ids = array_merge($ids, array_filter(array_column($rows, $column)));
            }
            $externalIds[$table] = array_unique([...($externalIds[$table] ?? []), ...$ids]);
        }
        foreach ($externalIds as $table => $ids) {
            if ($ids !== []) {
                $data['external'][$table] = $connection->table($table)->whereIn('id', $ids)->get()
                    ->map(fn (object $row): array => (array) $row)->all();
                if (count($data['external'][$table]) !== count(array_unique($ids))) {
                    throw new RuntimeException('Referenced material provenance is missing.');
                }
            }
        }
        $stage = 'files';
        $data['files'] = (new TeachingSynchronisationFiles)->capture($data['tables'], $configuration['filesystems'], true, true);

        return $data;
    });
    $stage = 'encoding';
    $json = json_encode($snapshot, JSON_THROW_ON_ERROR);
    if (strlen($json) > 384 * 1024 * 1024) {
        throw new RuntimeException('Teaching snapshot exceeds bounded transfer size.');
    }
    echo $json;
} catch (Throwable $exception) {
    fwrite(STDERR, "Teaching read-only export failed at {$stage}; no data was imported.\n");
    if ($exception::class === RuntimeException::class) {
        fwrite(STDERR, 'Teaching diagnostic: '.base64_encode($exception->getMessage())."\n");
    }
    exit(1);
}
