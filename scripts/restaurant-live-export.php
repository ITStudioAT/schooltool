<?php

// Executed in memory through the existing, host-verified MAIN SSH connection.
use App\Models\RestaurantSepaMandate;
use App\Models\User;
use App\Services\FeaturePreviewDatabaseGuard;
use Illuminate\Config\Repository;
use Illuminate\Database\Connection;
use Illuminate\Database\Connectors\ConnectionFactory;
use Illuminate\Encryption\EncryptionServiceProvider;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Facade;

ini_set('display_errors', '0');
ini_set('log_errors', '0');
$stage = 'bootstrap';
try {
    $request = json_decode(base64_decode('__RESTAURANT_REQUEST__', true), true, 512, JSON_THROW_ON_ERROR);
    require getcwd().'/vendor/autoload.php';
    $app = require getcwd().'/bootstrap/app.php';
    $configurationPath = getcwd().'/bootstrap/cache/config.php';
    if (! is_file($configurationPath) || is_link($configurationPath) || realpath($configurationPath) !== $configurationPath) {
        throw new RuntimeException('A canonical existing live configuration cache is required.');
    }
    $configuration = require $configurationPath;
    $app->instance('config', new Repository($configuration));
    $app->instance('env', $configuration['app']['env']);
    $app->instance('db.factory', new ConnectionFactory($app));
    $app->register(EncryptionServiceProvider::class);
    Facade::setFacadeApplication($app);
    // Do not boot application providers or filesystem adapters: neither may create live caches, logs or directories.
    if (! $app->environment('production') || config('schooltool.preview.instance')) {
        throw new RuntimeException('The source must be MAIN production.');
    }
    $stage = 'read_only_connection';
    $snapshot = $app->make(FeaturePreviewDatabaseGuard::class)->withMainReadOnlyConnection(function (Connection $connection) use ($request, $configuration, &$stage): array {
        $stage = 'schema';
        $tables = [...array_keys($request['tables']), 'users', 'import116', 'school_tools', 'schoolyears', 'teachers', 'roles', 'model_has_roles'];
        $engines = $connection->table('information_schema.TABLES')->where('TABLE_SCHEMA', $connection->getDatabaseName())->whereIn('TABLE_NAME', $tables)->pluck('ENGINE');
        if ($engines->count() !== count($tables) || $engines->contains(fn (?string $engine): bool => strcasecmp((string) $engine, 'InnoDB') !== 0)) {
            throw new RuntimeException('The scoped source must consist of InnoDB base tables.');
        }
        $stage = 'school_identity';
        $schoolId = (int) $request['school']['id'];
        $school = $connection->table('schools')->where('id', $schoolId)->first(['id', 'short_name', 'long_name']);
        if (! $school || (array) $school !== $request['school']) {
            throw new RuntimeException('The source school identity differs.');
        }
        $data = ['school' => (array) $school, 'captured_at' => gmdate(DATE_ATOM), 'tables' => [], 'columns' => [], 'files' => []];
        $stage = 'restaurant_rows';
        foreach ($request['tables'] as $table => $scope) {
            if (! preg_match('/\Arestaurant_[a-z_]+\z/', $table)) {
                throw new RuntimeException('Invalid restaurant table.');
            }
            $query = $connection->table($table);
            if ($scope === 'school_id') {
                $query->where('school_id', $schoolId);
            } else {
                [$parent, $column] = $scope;
                $query->whereIn($column, array_column($data['tables'][$parent], 'id'));
            }
            $columns = array_values(array_diff($connection->getSchemaBuilder()->getColumnListing($table), ['booking_slot_key']));
            $data['columns'][$table] = $columns;
            $data['tables'][$table] = $query->get($columns)->map(fn (object $row): array => (array) $row)->all();
        }
        $stage = 'sepa_decryption';
        $encrypted = array_keys(array_filter((new RestaurantSepaMandate)->getCasts(), fn (string $cast): bool => str_starts_with($cast, 'encrypted')));
        $data['encrypted'] = $encrypted;
        foreach ($data['tables']['restaurant_sepa_mandates'] as &$mandate) {
            foreach ($encrypted as $column) {
                if ($mandate[$column] !== null) {
                    $mandate[$column] = Crypt::decryptString($mandate[$column]);
                }
            }
        }
        unset($mandate);

        $stage = 'shared_identity';
        $roleRows = $connection->table('model_has_roles')->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_type', User::class)->whereIn('roles.name', ['lunch_admin', 'lunch_user', 'lunch_candidate'])
            ->get(['model_id', 'roles.name']);
        $userIds = array_merge($roleRows->pluck('model_id')->all(), array_column($data['tables']['restaurant_menu_plan_bookings'], 'user_id'),
            array_column($data['tables']['restaurant_sepa_mandates'], 'user_id'), array_column($data['tables']['restaurant_billings'], 'created_by_user_id'));
        $imports = [];
        $users = [];
        // Close the restaurant's user, child, booking-default and Import 116 relationships.
        do {
            $previousCount = count($users) + count($imports);
            $users = $connection->table('users')->where('school_id', $schoolId)->whereIn('id', array_filter($userIds))->get($request['users'])
                ->map(fn (object $row): array => (array) $row)->all();
            $importIds = array_merge(array_column($users, 'import116_id'), array_column($data['tables']['restaurant_menu_plan_bookings'], 'import116_id'));
            foreach ($users as $user) {
                $defaults = json_decode($user['restaurant_booking_defaults'] ?? 'null', true);
                $importIds = array_merge($importIds, array_column($defaults['recipients'] ?? [], 'import116_id'));
            }
            $emails = array_column($users, 'email');
            $imports = $connection->table('import116')->where('school_id', $schoolId)->where(function ($query) use ($importIds, $emails): void {
                $query->whereIn('id', array_filter($importIds))->orWhereIn('mother_email', $emails)->orWhereIn('father_email', $emails);
            })->get($request['imports'])->map(fn (object $row): array => (array) $row)->all();
            $userIds = array_merge($userIds, array_column($imports, 'user_id'));
        } while ($previousCount !== count($users) + count($imports));
        foreach ($users as &$user) {
            $user['restaurant_roles'] = $roleRows->where('model_id', $user['id'])->pluck('name')->sort()->values()->all();
        }
        unset($user);
        $data['users'] = $users;
        $data['teacher_emails'] = $connection->table('teachers')->where('school_id', $schoolId)->whereIn('email', array_column($users, 'email'))
            ->pluck('email')->map(fn (string $email): string => mb_strtolower(trim($email)))->all();
        $data['imports'] = $imports;
        $yearIds = array_filter(array_column($imports, 'schoolyear_id'));
        $data['schoolyears'] = $connection->table('schoolyears')->where('school_id', $schoolId)->whereIn('id', $yearIds)->get(['id', 'from', 'until'])
            ->map(fn (object $row): array => (array) $row)->all();
        $settings = (array) $connection->table('school_tools')->where('school_id', $schoolId)->first();
        $data['settings'] = array_filter($settings, fn (string $key): bool => str_starts_with($key, 'restaurant_'), ARRAY_FILTER_USE_KEY);

        $stage = 'image_storage_roots';
        $paths = [];
        $diskRoots = [];
        foreach (['local', 'public'] as $disk) {
            $diskConfiguration = $configuration['filesystems']['disks'][$disk];
            $root = realpath($diskConfiguration['root']);
            if ($diskConfiguration['driver'] !== 'local' || ! $root || $root !== rtrim($diskConfiguration['root'], '/')) {
                throw new RuntimeException('Existing canonical local live storage roots are required.');
            }
            $diskRoots[$disk] = $root;
        }
        foreach ($data['tables']['restaurant_foods'] as $food) {
            $paths[] = $food['food_image_path'];
        }
        foreach ($data['tables']['restaurant_ingredient_icons'] as $icon) {
            $paths[] = $icon['image_path'];
        }
        foreach ($data['tables']['restaurant_menu_plan_entries'] as $entry) {
            foreach (json_decode($entry['foods_snapshot'] ?? 'null', true) ?? [] as $food) {
                $paths[] = $food['food_image_path'] ?? null;
                $paths = array_merge($paths, array_column($food['ingredient_icons'] ?? [], 'image_path'));
            }
        }
        foreach (array_unique(array_filter($paths)) as $path) {
            $stage = 'image_path';
            if (strlen($path) > 1024 || ! preg_match('~\Arestaurant/[^\x00-\x1F\x7F\\\\:]+\z~u', $path)
                || array_intersect(explode('/', $path), ['', '.', '..']) !== []) {
                throw new RuntimeException('An image path lies outside restaurant storage.');
            }
            $disk = str_ends_with(strtolower($path), '.svg') && is_file($diskRoots['local'].'/'.$path) ? 'local' : 'public';
            $expectedPath = $diskRoots[$disk].'/'.$path;
            $realPath = realpath($expectedPath);
            $root = realpath($diskRoots[$disk].'/restaurant');
            $stage = ! $realPath || ! is_file((string) $realPath) ? 'image_missing' : 'image_safety';
            if (! $realPath || ! $root || $root !== $diskRoots[$disk].'/restaurant'
                || $realPath !== $expectedPath || ! str_starts_with($realPath, $root.DIRECTORY_SEPARATOR)
                || ! is_file($realPath) || filesize($realPath) > 10 * 1024 * 1024) {
                throw new RuntimeException('An image is missing, unsafe or too large.');
            }
            $stage = 'image_contents';
            $bytes = file_get_contents($realPath);
            if ($bytes === false) {
                throw new RuntimeException('Cannot read restaurant image.');
            }
            $data['files'][] = ['disk' => $disk, 'path' => $path, 'sha256' => hash('sha256', $bytes), 'content' => base64_encode($bytes)];
        }

        return $data;
    });
    $stage = 'encoding';
    $json = json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
    if (strlen($json) > 48 * 1024 * 1024) {
        throw new RuntimeException('The restaurant snapshot exceeds the bounded transfer size.');
    }
    echo $json;
} catch (Throwable) {
    fwrite(STDERR, "Restaurant read-only export failed at {$stage}; no data was imported.\n");
    exit(1);
}
